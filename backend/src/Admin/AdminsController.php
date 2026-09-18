<?php

namespace Rcc\Admin;

use Rcc\AdminLog;
use Rcc\Auth;
use Rcc\Config;
use Rcc\CustomerToken;
use Rcc\Database;
use Rcc\Mailer\Emails;
use Rcc\Mailer\Mailer;
use Rcc\Request;
use Rcc\Response;
use Rcc\Validator;

/**
 * Les accès à l'administration.
 *
 * Cet écran n'appartient qu'au **super administrateur** : celui qui distribue
 * les accès ne peut pas être quelqu'un à qui on vient d'en donner un. Sans
 * cette règle, le premier administrateur ajouté pourrait s'en ajouter d'autres,
 * puis retirer le sien à celui qui l'a fait entrer.
 *
 * Les administrateurs ne sont pas des clients et ne figurent plus dans l'écran
 * « Clients », qui redevient ce qu'il annonce : les gens qui achètent.
 */
class AdminsController
{
    public function __construct(private Mailer $mailer)
    {
    }

    /** @param array<string,mixed> $admin */
    public function index(Request $request, array $admin): Response
    {
        $rows = Database::run(
            "SELECT id, name, email, phone_display, status, is_super_admin,
                    email_verified_at, password_hash, created_at
               FROM customers
              WHERE is_admin = 1
              ORDER BY is_super_admin DESC, created_at, id"
        )->fetchAll();

        return Response::data([
            'admins' => array_map(static fn (array $r) => [
                'id' => (int) $r['id'],
                'name' => $r['name'],
                'email' => $r['email'],
                'phone' => $r['phone_display'],
                'status' => $r['status'],
                'is_super_admin' => (int) $r['is_super_admin'] === 1,
                'email_verified' => $r['email_verified_at'] !== null,
                // Un compte créé par invitation porte un haché impossible à
                // retrouver : tant que la personne n'a pas choisi son mot de
                // passe, l'invitation est en attente et il faut pouvoir le dire.
                'pending' => str_starts_with((string) $r['password_hash'], self::INVITE_PREFIX),
                'created_at' => $r['created_at'],
            ], $rows),
        ]);
    }

    /**
     * Ajoute un administrateur.
     *
     * Deux chemins, selon que l'adresse est déjà connue de la boutique :
     *
     *  - **le compte existe** — il est promu, sans rien changer d'autre. C'est
     *    le cas du vendeur qui commandait déjà sur le site ;
     *  - **le compte n'existe pas** — il est créé, puis la personne reçoit un
     *    lien pour **choisir elle-même son mot de passe**. À aucun moment un
     *    mot de passe n'est fabriqué ici ni transmis par message : ce qui n'est
     *    jamais écrit ne peut pas être lu par-dessus une épaule.
     *
     * @param array<string,mixed> $admin
     */
    public function store(Request $request, array $admin): Response
    {
        $v = new Validator($request->body);
        $email = $v->email('email');

        $existant = $email === '' ? null : Database::first('SELECT * FROM customers WHERE email = ?', [$email]);

        // Le nom et le téléphone ne sont exigés que pour une création : promouvoir
        // un client existant n'a pas à redemander ce qu'il a déjà donné.
        if ($existant === null) {
            $name = $v->text('name', 2, 120);
            $phone = $v->phone('phone');
        }

        if ($v->fails()) {
            return Response::validation($v->errors());
        }

        if ($existant !== null) {
            return $this->promote($admin, $existant);
        }

        // `phone_display` garde l'écriture du gérant, telle qu'il la lira ;
        // `phone` porte la forme normalisée qui sert d'identifiant.
        return $this->invite($admin, $name ?? '', $email, $phone ?? '', trim((string) $request->input('phone')));
    }

    /**
     * Retire l'accès. Le compte reste, et redevient un compte comme un autre.
     *
     * @param array<string,mixed> $admin
     */
    public function revoke(Request $request, array $admin, string $id): Response
    {
        $cible = Database::first('SELECT * FROM customers WHERE id = ? AND is_admin = 1', [(int) $id]);

        if ($cible === null) {
            return Response::notFound("Cet administrateur n'existe pas.");
        }

        if ((int) $cible['id'] === (int) $admin['id']) {
            return Response::validation(['id' => 'Vous ne pouvez pas retirer votre propre accès.']);
        }

        // La garde tient même si un jour deux comptes portaient le rang : le
        // super administrateur ne se retire pas depuis le web, seulement depuis
        // le serveur. C'est ce qui empêche une administration sans maître.
        if ((int) $cible['is_super_admin'] === 1) {
            return Response::validation([
                'id' => "Le super administrateur ne peut pas être retiré depuis ici.",
            ]);
        }

        Database::run(
            'UPDATE customers SET is_admin = 0, updated_at = ? WHERE id = ?',
            [Database::now(), $cible['id']]
        );

        // Ses sessions ouvertes tombent avec l'accès : sans cela, l'onglet
        // resté ouvert dans l'arrière-boutique continuerait d'administrer.
        Auth::revokeAll((int) $cible['id']);

        AdminLog::record($admin, 'admin.revoke', $cible['email'], 'accès retiré');

        return Response::data(['revoked' => true]);
    }

    /**
     * Renvoie l'invitation d'un compte qui n'a pas encore choisi son mot de
     * passe. Un lien de ce genre expire, et un e-mail se perd.
     *
     * @param array<string,mixed> $admin
     */
    public function resend(Request $request, array $admin, string $id): Response
    {
        $cible = Database::first('SELECT * FROM customers WHERE id = ? AND is_admin = 1', [(int) $id]);

        if ($cible === null) {
            return Response::notFound("Cet administrateur n'existe pas.");
        }

        if (!str_starts_with((string) $cible['password_hash'], self::INVITE_PREFIX)) {
            return Response::validation([
                'id' => 'Ce compte a déjà son mot de passe. Il peut utiliser « mot de passe oublié ».',
            ]);
        }

        $this->sendInvitation($cible['name'], $cible['email'], (int) $cible['id'], $admin);

        return Response::data(['sent' => true]);
    }

    // ------------------------------------------------------------- privé

    /**
     * Marque un haché de mot de passe inutilisable, posé sur un compte invité.
     *
     * `password_verify` échoue sur n'importe quelle valeur qui n'est pas un
     * haché valide : le compte existe donc sans qu'aucun mot de passe n'y
     * donne accès, et sans ligne supplémentaire en base pour le dire.
     */
    private const INVITE_PREFIX = 'invitation:';

    /** Une invitation n'est pas un mot de passe oublié : on laisse le temps d'ouvrir sa boîte. */
    private const INVITE_TTL = 172800; // 48 h

    /**
     * @param array<string,mixed> $admin
     * @param array<string,mixed> $customer
     */
    private function promote(array $admin, array $customer): Response
    {
        if ((int) $customer['is_admin'] === 1) {
            return Response::validation(['email' => 'Ce compte est déjà administrateur.']);
        }

        if ($customer['status'] !== 'active') {
            return Response::validation(['email' => "Ce compte n'est pas actif : il ne peut pas administrer."]);
        }

        Database::run(
            'UPDATE customers SET is_admin = 1, updated_at = ? WHERE id = ?',
            [Database::now(), $customer['id']]
        );

        AdminLog::record($admin, 'admin.promote', $customer['email'], 'client existant promu');

        return Response::data([
            'created' => false,
            'message' => sprintf(
                '%s avait déjà un compte : il est maintenant administrateur, avec son mot de passe habituel.',
                $customer['name']
            ),
        ], 201);
    }

    /** @param array<string,mixed> $admin */
    private function invite(array $admin, string $name, string $email, string $phone, string $display): Response
    {
        if (Database::first('SELECT id FROM customers WHERE phone = ?', [$phone]) !== null) {
            return Response::validation(['phone' => 'Ce numéro est déjà rattaché à un compte.']);
        }

        $now = Database::now();

        Database::run(
            'INSERT INTO customers
                (name, email, phone, phone_display, password_hash, is_admin, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?)',
            [$name, $email, $phone, $display, self::INVITE_PREFIX . bin2hex(random_bytes(16)), 'active', $now, $now]
        );

        $id = (int) Database::connection()->lastInsertId();

        $this->sendInvitation($name, $email, $id, $admin);

        return Response::data([
            'created' => true,
            'message' => sprintf(
                "Compte créé. %s reçoit un lien pour choisir son mot de passe ; il est valable 48 heures.",
                $name
            ),
        ], 201);
    }

    /** @param array<string,mixed> $admin */
    private function sendInvitation(string $name, string $email, int $id, array $admin): void
    {
        $token = CustomerToken::issue($id, CustomerToken::PASSWORD_RESET, self::INVITE_TTL);

        $lien = rtrim((string) Config::get('app.url'), '/') . '/compte/reinitialiser?token=' . $token;
        $mail = Emails::adminInvitation($name, $lien);

        // Résultat ignoré, comme partout ailleurs : une panne du service d'envoi
        // ne doit pas laisser un compte à moitié créé. Le lien peut être renvoyé.
        $this->mailer->send($email, $name, $mail['subject'], $mail['html']);

        AdminLog::record($admin, 'admin.invite', $email, 'invitation envoyée');
    }
}
