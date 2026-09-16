<?php

namespace Rcc;

/**
 * Limitation de débit adossée à la base.
 *
 * Pas de Redis ni d'APCu : sur un mutualisé on ne dispose que de MySQL, et une
 * limite qui s'évapore au redémarrage du serveur ne limite rien.
 *
 * Deux plafonds simultanés. Celui par identifiant protège un compte précis du
 * bourrage de mots de passe. Celui par IP arrête le bourrage d'identifiants,
 * où un attaquant essaie un seul mot de passe sur des milliers de comptes et ne
 * déclenche donc jamais la limite par identifiant.
 */
class RateLimiter
{
    public function __construct(
        private string $action,
        private int $maxPerIdentifier,
        private int $maxPerIp,
        private int $windowSeconds,
    ) {
    }

    /** Plafonds de la connexion : 5 échecs par compte, 20 par IP, sur 15 minutes. */
    public static function login(): self
    {
        return new self('login', maxPerIdentifier: 5, maxPerIp: 20, windowSeconds: 900);
    }

    /**
     * Plafonds des routes qui envoient un e-mail. Le forfait Brevo est à 300
     * envois par jour : sans cette limite, marteler « mot de passe oublié »
     * épuise le quota en une minute et coupe tous les e-mails de la boutique,
     * confirmations de commande comprises, jusqu'au lendemain.
     */
    public static function email(string $action): self
    {
        return new self($action, maxPerIdentifier: 3, maxPerIp: 10, windowSeconds: 3600);
    }

    public function isBlocked(string $identifier, string $ip): bool
    {
        return $this->countByIdentifier($identifier) >= $this->maxPerIdentifier
            || $this->countByIp($ip) >= $this->maxPerIp;
    }

    public function record(string $identifier, string $ip): void
    {
        Database::run(
            'INSERT INTO auth_attempts (action, identifier_hash, ip, attempted_at) VALUES (?, ?, ?, ?)',
            [$this->action, $this->hash($identifier), $ip, Database::now()]
        );
    }

    /** Appelé après un succès : le compteur de l'identifiant repart de zéro. */
    public function clear(string $identifier): void
    {
        Database::run(
            'DELETE FROM auth_attempts WHERE action = ? AND identifier_hash = ?',
            [$this->action, $this->hash($identifier)]
        );
    }

    /** Secondes restantes avant que la plus ancienne tentative ne sorte de la fenêtre. */
    public function retryAfter(string $identifier, string $ip): int
    {
        $row = Database::first(
            'SELECT MIN(attempted_at) AS oldest
               FROM auth_attempts
              WHERE action = ?
                AND attempted_at > ?
                AND (identifier_hash = ? OR ip = ?)',
            [$this->action, $this->windowStart(), $this->hash($identifier), $ip]
        );

        if ($row === null || $row['oldest'] === null) {
            return 0;
        }

        $freeAt = strtotime($row['oldest'] . ' UTC') + $this->windowSeconds;

        return max(0, $freeAt - time());
    }

    private function countByIdentifier(string $identifier): int
    {
        $row = Database::first(
            'SELECT COUNT(*) AS total FROM auth_attempts
              WHERE action = ? AND identifier_hash = ? AND attempted_at > ?',
            [$this->action, $this->hash($identifier), $this->windowStart()]
        );

        return (int) ($row['total'] ?? 0);
    }

    private function countByIp(string $ip): int
    {
        $row = Database::first(
            'SELECT COUNT(*) AS total FROM auth_attempts
              WHERE action = ? AND ip = ? AND attempted_at > ?',
            [$this->action, $ip, $this->windowStart()]
        );

        return (int) ($row['total'] ?? 0);
    }

    private function windowStart(): string
    {
        return Database::now(-$this->windowSeconds);
    }

    /**
     * L'identifiant est haché avant d'être écrit : ce journal ne doit pas
     * devenir une liste exploitable des adresses et numéros de la clientèle.
     */
    private function hash(string $identifier): string
    {
        return hash('sha256', mb_strtolower(trim($identifier)));
    }
}
