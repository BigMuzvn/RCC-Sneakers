<?php

namespace Rcc\Admin;

use Rcc\AdminLog;
use Rcc\Auth;
use Rcc\Database;
use Rcc\Request;
use Rcc\Response;

/**
 * Clients.
 *
 * Trois gestes, et pas un de plus : consulter, suspendre, anonymiser. Pas de
 * modification des coordonnées — elles appartiennent au client, qui les change
 * depuis son espace. Pas de suppression non plus : une commande est une pièce
 * comptable, et la clé étrangère l'interdit d'ailleurs.
 */
class CustomersController
{
    private const STATUSES = ['active', 'suspended'];

    /** @param array<string,mixed> $admin */
    public function index(Request $request, array $admin): Response
    {
        $where = [];
        $params = [];

        $search = trim((string) $request->input('search', ''));

        if ($search !== '') {
            $where[] = '(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.phone_display LIKE ?)';
            $motif = '%' . $search . '%';
            array_push($params, $motif, $motif, $motif, $motif);
        }

        $statut = (string) $request->input('status', '');

        if (in_array($statut, ['active', 'suspended', 'anonymised'], true)) {
            $where[] = 'c.status = ?';
            $params[] = $statut;
        }

        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $total = (int) Database::first("SELECT COUNT(*) c FROM customers c{$clause}", $params)['c'];

        $perPage = min(100, max(5, (int) $request->input('per_page', 25)));
        $page = max(1, (int) $request->input('page', 1));
        $offset = ($page - 1) * $perPage;

        // Le nombre et le total des commandes sont agrégés dans la requête :
        // c'est ce qui distingue un client fidèle d'un compte dormant, et le
        // calculer après coup ferait une requête par ligne.
        $rows = Database::run(
            "SELECT c.id, c.name, c.email, c.phone_display, c.status, c.is_admin,
                    c.email_verified_at, c.created_at,
                    COUNT(o.id) AS orders_count,
                    COALESCE(SUM(CASE WHEN o.status <> 'cancelled' THEN o.total_xof ELSE 0 END), 0) AS spent_xof
               FROM customers c
               LEFT JOIN orders o ON o.customer_id = c.id
               {$clause}
              GROUP BY c.id
              ORDER BY c.created_at DESC, c.id DESC
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        )->fetchAll();

        return Response::data([
            'customers' => array_map(static fn (array $r) => [
                'id' => (int) $r['id'],
                'name' => $r['name'],
                'email' => $r['email'],
                'phone' => $r['phone_display'],
                'status' => $r['status'],
                'is_admin' => (int) $r['is_admin'] === 1,
                'email_verified' => $r['email_verified_at'] !== null,
                'orders_count' => (int) $r['orders_count'],
                'spent_xof' => (int) $r['spent_xof'],
                'created_at' => $r['created_at'],
            ], $rows),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'pages' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    /** @param array<string,mixed> $admin */
    public function show(Request $request, array $admin, string $id): Response
    {
        $customer = Database::first('SELECT * FROM customers WHERE id = ?', [(int) $id]);

        if ($customer === null) {
            return Response::notFound("Ce client n'existe pas.");
        }

        $orders = Database::run(
            'SELECT reference, status, total_xof, created_at FROM orders
              WHERE customer_id = ? ORDER BY created_at DESC',
            [(int) $id]
        )->fetchAll();

        $favorites = Database::run(
            'SELECT item_type, item_id FROM favorites WHERE customer_id = ? ORDER BY created_at DESC',
            [(int) $id]
        )->fetchAll();

        return Response::data([
            'customer' => array_merge(Auth::publicShape($customer), [
                'status' => $customer['status'],
                'anonymised_at' => $customer['anonymised_at'],
                'orders' => array_map(static fn (array $o) => [
                    'reference' => $o['reference'],
                    'status' => $o['status'],
                    'total_xof' => (int) $o['total_xof'],
                    'created_at' => $o['created_at'],
                ], $orders),
                'favorites' => array_map(static fn (array $f) => [
                    'item_type' => $f['item_type'],
                    'item_id' => (int) $f['item_id'],
                ], $favorites),
            ]),
        ]);
    }

    /** @param array<string,mixed> $admin */
    public function updateStatus(Request $request, array $admin, string $id): Response
    {
        $customer = $this->modifiable($admin, (int) $id);

        if ($customer instanceof Response) {
            return $customer;
        }

        $statut = (string) $request->input('status', '');

        if (!in_array($statut, self::STATUSES, true)) {
            return Response::validation(['status' => 'Statut attendu : actif ou suspendu.']);
        }

        if ($customer['status'] === 'anonymised') {
            return Response::validation(['status' => 'Un compte anonymisé ne peut plus changer de statut.']);
        }

        Database::run(
            'UPDATE customers SET status = ?, updated_at = ? WHERE id = ?',
            [$statut, Database::now(), $customer['id']]
        );

        // Suspendre doit fermer les sessions ouvertes sur-le-champ : sinon le
        // compte reste actif dans l'onglet déjà ouvert de son propriétaire.
        if ($statut === 'suspended') {
            Auth::revokeAll((int) $customer['id']);
        }

        AdminLog::record($admin, 'customer.status', $customer['email'], sprintf(
            '%s → %s',
            $customer['status'],
            $statut
        ));

        return Response::data(['status' => $statut]);
    }

    /**
     * Anonymisation — la réponse au droit à l'effacement.
     *
     * Les commandes sont conservées : ce sont des pièces comptables, et la clé
     * étrangère refuse d'ailleurs la suppression. Ce qui disparaît, c'est
     * l'identité : nom, e-mail, téléphone, mot de passe, favoris, et les
     * coordonnées recopiées dans chaque commande passée.
     *
     * Le geste est irréversible, ce que l'interface doit dire clairement.
     *
     * @param array<string,mixed> $admin
     */
    public function anonymise(Request $request, array $admin, string $id): Response
    {
        $customer = $this->modifiable($admin, (int) $id);

        if ($customer instanceof Response) {
            return $customer;
        }

        if ($customer['status'] === 'anonymised') {
            return Response::validation(['status' => 'Ce compte est déjà anonymisé.']);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $marqueur = 'anonyme-' . bin2hex(random_bytes(6));

            Database::run(
                "UPDATE customers
                    SET name = 'Client anonymisé',
                        email = ?,
                        phone = ?,
                        phone_display = '',
                        password_hash = ?,
                        email_verified_at = NULL,
                        status = 'anonymised',
                        anonymised_at = ?,
                        updated_at = ?
                  WHERE id = ?",
                [
                    $marqueur . '@anonyme.invalid',
                    // Le numéro porte une contrainte d'unicité : il faut une
                    // valeur distincte, pas une chaîne vide partagée.
                    $marqueur,
                    // Un hachage d'un secret aléatoire jamais transmis : le
                    // compte devient inatteignable sans laisser de champ nul.
                    password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT, ['cost' => 4]),
                    Database::now(),
                    Database::now(),
                    $customer['id'],
                ]
            );

            // Les coordonnées recopiées dans les commandes doivent partir
            // aussi : les y laisser viderait l'anonymisation de son sens.
            Database::run(
                "UPDATE orders
                    SET contact_name = 'Client anonymisé',
                        contact_email = ?,
                        contact_phone = '',
                        delivery_address = 'Adresse effacée',
                        updated_at = ?
                  WHERE customer_id = ?",
                [$marqueur . '@anonyme.invalid', Database::now(), $customer['id']]
            );

            Database::run('DELETE FROM favorites WHERE customer_id = ?', [$customer['id']]);
            Database::run('DELETE FROM customer_tokens WHERE customer_id = ?', [$customer['id']]);
            Auth::revokeAll((int) $customer['id']);

            // L'adresse quitte aussi la lettre d'information : c'est une seule
            // et même demande d'effacement.
            Database::run('DELETE FROM newsletter_subscribers WHERE email = ?', [$customer['email']]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }

        AdminLog::record($admin, 'customer.anonymise', $customer['email'], 'droit à l’effacement');

        return Response::data(['status' => 'anonymised']);
    }

    /** @param array<string,mixed> $admin */
    public function updateRole(Request $request, array $admin, string $id): Response
    {
        $customer = $this->modifiable($admin, (int) $id);

        if ($customer instanceof Response) {
            return $customer;
        }

        $veutAdmin = in_array($request->input('is_admin'), [true, 1, '1', 'true'], true);

        if ($customer['status'] !== 'active') {
            return Response::validation(['is_admin' => "Un compte qui n'est pas actif ne peut pas administrer."]);
        }

        if (!$veutAdmin) {
            $restants = (int) Database::first(
                'SELECT COUNT(*) c FROM customers WHERE is_admin = 1 AND id <> ?',
                [$customer['id']]
            )['c'];

            // Retirer le dernier accès fermerait l'administration à tout le
            // monde, sans autre recours qu'une commande sur le serveur.
            if ($restants === 0) {
                return Response::validation(['is_admin' => 'Impossible : ce serait le dernier administrateur.']);
            }
        }

        Database::run(
            'UPDATE customers SET is_admin = ?, updated_at = ? WHERE id = ?',
            [$veutAdmin ? 1 : 0, Database::now(), $customer['id']]
        );

        AdminLog::record($admin, 'customer.role', $customer['email'], $veutAdmin ? 'promu administrateur' : 'rétrogradé');

        return Response::data(['is_admin' => $veutAdmin]);
    }

    // ------------------------------------------------------------- privé

    /**
     * Charge un client et refuse les gestes qu'un administrateur ne doit pas
     * s'appliquer à lui-même — se suspendre ou s'anonymiser seul reviendrait à
     * se verrouiller dehors.
     *
     * @param  array<string,mixed> $admin
     * @return array<string,mixed>|Response
     */
    private function modifiable(array $admin, int $id): array|Response
    {
        $customer = Database::first('SELECT * FROM customers WHERE id = ?', [$id]);

        if ($customer === null) {
            return Response::notFound("Ce client n'existe pas.");
        }

        if ((int) $customer['id'] === (int) $admin['id']) {
            return Response::validation(['id' => 'Vous ne pouvez pas appliquer cette action à votre propre compte.']);
        }

        return $customer;
    }
}
