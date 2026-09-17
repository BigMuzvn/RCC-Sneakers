<?php

namespace Rcc;

/**
 * Session client, adossée à la base plutôt qu'aux sessions natives de PHP.
 *
 * Sur un mutualisé, les fichiers de session atterrissent souvent dans un
 * répertoire temporaire partagé entre comptes et session.save_path n'est pas
 * toujours modifiable. Un jeton en base évite ce terrain, survit à un
 * changement de serveur, et se révoque réellement côté serveur — une session
 * PHP ou un JWT ne se reprennent pas une fois émis.
 *
 * Schéma sélecteur/validateur : le cookie porte « sélecteur.validateur », la
 * base ne garde que le sélecteur en clair — pour retrouver la ligne — et le
 * SHA-256 du validateur. Une fuite de la table ne donne aucune session.
 */
class Auth
{
    public const COOKIE = 'rcc_session';

    /** Session ordinaire : 12 heures côté serveur, cookie effacé à la fermeture. */
    private const SESSION_SECONDS = 43200;

    private ?array $customer = null;
    private bool $resolved = false;

    public function __construct(private CookieJar $cookies)
    {
    }

    public function login(int $customerId, bool $remember): void
    {
        $selector = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));

        $lifetime = $remember
            ? (int) Config::get('session.remember_days', 30) * 86400
            : self::SESSION_SECONDS;

        Database::run(
            'INSERT INTO auth_tokens (customer_id, selector, validator_hash, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?)',
            [
                $customerId,
                $selector,
                hash('sha256', $validator),
                Database::now($lifetime),
                Database::now(),
            ]
        );

        // Sans « se souvenir de moi », le cookie n'a pas de Max-Age : il meurt
        // avec le navigateur, même si le jeton reste valide 12 h côté serveur.
        $this->cookies->set(
            self::COOKIE,
            $selector . '.' . $validator,
            $remember ? $lifetime : 0
        );

        $this->customer = null;
        $this->resolved = false;
    }

    public function logout(): void
    {
        $parts = $this->parseCookie();

        if ($parts !== null) {
            Database::run('DELETE FROM auth_tokens WHERE selector = ?', [$parts[0]]);
        }

        $this->cookies->forget(self::COOKIE);
        $this->customer = null;
        $this->resolved = true;
    }

    /** Coupe toutes les sessions d'un client, sur tous ses appareils. */
    public static function revokeAll(int $customerId): void
    {
        Database::run('DELETE FROM auth_tokens WHERE customer_id = ?', [$customerId]);
    }

    /**
     * Coupe les autres appareils mais épargne celui-ci. Utilisé au changement
     * de mot de passe : être déconnecté juste après avoir validé son propre
     * formulaire serait incompréhensible pour le client.
     */
    public function revokeOtherSessions(int $customerId): void
    {
        $parts = $this->parseCookie();

        if ($parts === null) {
            self::revokeAll($customerId);

            return;
        }

        Database::run(
            'DELETE FROM auth_tokens WHERE customer_id = ? AND selector <> ?',
            [$customerId, $parts[0]]
        );
    }

    public function id(): ?int
    {
        $customer = $this->customer();

        return $customer === null ? null : (int) $customer['id'];
    }

    /** @return array<string,mixed>|null la ligne complète, mot de passe compris */
    public function customer(): ?array
    {
        if ($this->resolved) {
            return $this->customer;
        }

        $this->resolved = true;
        $this->customer = $this->resolve();

        return $this->customer;
    }

    /** @return array<string,mixed> ce qui peut être renvoyé au navigateur */
    public function publicCustomer(): array
    {
        $customer = $this->customer();

        if ($customer === null) {
            return [];
        }

        return self::publicShape($customer);
    }

    /**
     * Forme publique d'un client. Le hachage du mot de passe ne sort jamais
     * d'ici : c'est la seule porte par laquelle un client atteint le front.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public static function publicShape(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'phone' => $row['phone_display'],
            'email_verified' => $row['email_verified_at'] !== null,
            // Le front en a besoin pour afficher l'accès à l'administration.
            // Ce n'est pas ce drapeau qui protège les routes : le serveur
            // revérifie à chaque appel, un client qui le falsifierait dans sa
            // mémoire ne gagnerait qu'un lien vers une page qui lui répond 403.
            'is_admin' => (int) ($row['is_admin'] ?? 0) === 1,
            'created_at' => $row['created_at'],
        ];
    }

    private function resolve(): ?array
    {
        $parts = $this->parseCookie();

        if ($parts === null) {
            return null;
        }

        [$selector, $validator] = $parts;

        $token = Database::first(
            'SELECT customer_id, validator_hash, expires_at FROM auth_tokens WHERE selector = ?',
            [$selector]
        );

        if ($token === null) {
            return null;
        }

        if (strtotime($token['expires_at'] . ' UTC') <= time()) {
            Database::run('DELETE FROM auth_tokens WHERE selector = ?', [$selector]);

            return null;
        }

        // hash_equals et non « === » : la comparaison doit prendre le même temps
        // quel que soit l'endroit où les chaînes divergent, sinon la durée de
        // réponse laisse deviner le jeton caractère par caractère.
        if (!hash_equals($token['validator_hash'], hash('sha256', $validator))) {
            return null;
        }

        $customer = Database::first('SELECT * FROM customers WHERE id = ?', [$token['customer_id']]);

        // Un compte suspendu ou anonymisé perd ses sessions sur-le-champ, y
        // compris celles déjà ouvertes dans un onglet. Vérifier seulement à la
        // connexion laisserait un compte abusif actif jusqu'à sa déconnexion.
        if ($customer !== null && ($customer['status'] ?? 'active') !== 'active') {
            self::revokeAll((int) $customer['id']);

            return null;
        }

        return $customer;
    }

    /**
     * Le client courant s'il est administrateur, sinon null.
     *
     * Le statut est revérifié ici : un administrateur suspendu n'administre
     * plus rien.
     *
     * @return array<string,mixed>|null
     */
    public function admin(): ?array
    {
        $customer = $this->customer();

        if ($customer === null || (int) ($customer['is_admin'] ?? 0) !== 1) {
            return null;
        }

        return $customer;
    }

    /** @return array{0:string,1:string}|null */
    private function parseCookie(): ?array
    {
        $raw = $this->cookies->get(self::COOKIE);

        if ($raw === null || substr_count($raw, '.') !== 1) {
            return null;
        }

        [$selector, $validator] = explode('.', $raw);

        if (strlen($selector) !== 32 || strlen($validator) !== 64) {
            return null;
        }

        return [$selector, $validator];
    }
}
