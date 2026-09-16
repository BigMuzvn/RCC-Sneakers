<?php

namespace Rcc;

/**
 * Jetons à usage unique envoyés par e-mail.
 *
 * Vérification d'adresse et réinitialisation de mot de passe ont la même
 * mécanique — un secret aléatoire, une échéance, une seule consommation — donc
 * une seule table avec une colonne `purpose` plutôt que deux tables jumelles.
 *
 * Seul le SHA-256 est écrit : une fuite de la base ne permet de valider aucune
 * adresse ni de réinitialiser aucun mot de passe.
 */
class CustomerToken
{
    public const VERIFY_EMAIL = 'email_verify';
    public const PASSWORD_RESET = 'password_reset';

    public const VERIFY_TTL = 86400;  // 24 h
    public const RESET_TTL = 3600;    // 1 h

    /** Renvoie le jeton en clair — la seule fois où il existe sous cette forme. */
    public static function issue(int $customerId, string $purpose, int $ttl): string
    {
        // Émettre un nouveau lien périme les précédents : sinon un lien ancien,
        // resté dans une boîte mail, continue d'ouvrir le compte.
        Database::run(
            'DELETE FROM customer_tokens WHERE customer_id = ? AND purpose = ?',
            [$customerId, $purpose]
        );

        $token = bin2hex(random_bytes(32));

        Database::run(
            'INSERT INTO customer_tokens (customer_id, purpose, token_hash, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?)',
            [
                $customerId,
                $purpose,
                hash('sha256', $token),
                Database::now($ttl),
                Database::now(),
            ]
        );

        return $token;
    }

    /**
     * Vérifie et consomme un jeton. Renvoie l'identifiant du client, ou null si
     * le jeton est inconnu, déjà utilisé ou expiré — les trois cas sont
     * indiscernables de l'extérieur, ce qui est voulu.
     */
    public static function consume(string $token, string $purpose): ?int
    {
        $token = trim($token);

        if ($token === '') {
            return null;
        }

        $row = Database::first(
            'SELECT id, customer_id, expires_at, used_at
               FROM customer_tokens
              WHERE token_hash = ? AND purpose = ?',
            [hash('sha256', $token), $purpose]
        );

        if ($row === null || $row['used_at'] !== null) {
            return null;
        }

        if (strtotime($row['expires_at'] . ' UTC') <= time()) {
            return null;
        }

        Database::run(
            'UPDATE customer_tokens SET used_at = ? WHERE id = ? AND used_at IS NULL',
            [Database::now(), $row['id']]
        );

        return (int) $row['customer_id'];
    }
}
