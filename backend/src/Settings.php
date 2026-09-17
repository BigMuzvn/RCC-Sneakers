<?php

namespace Rcc;

/**
 * Réglages de la boutique, en base plutôt qu'en dur.
 *
 * Coordonnées du pied de page, adresse qui reçoit les commandes, paires mises
 * en avant sur l'accueil : autant de textes que le gérant doit pouvoir changer
 * sans redéploiement.
 */
class Settings
{
    /** @var array<string,string>|null */
    private static ?array $cache = null;

    public static function get(string $name, string $default = ''): string
    {
        self::load();

        return self::$cache[$name] ?? $default;
    }

    /** @return array<int,string> valeur JSON décodée en liste de chaînes */
    public static function list(string $name): array
    {
        $decoded = json_decode(self::get($name, '[]'), true);

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $decoded), static fn ($v) => $v !== ''));
    }

    public static function set(string $name, string $value): void
    {
        Database::run(
            'INSERT INTO settings (name, value, updated_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)',
            [$name, $value, Database::now()]
        );

        self::$cache = null;
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        self::load();

        return self::$cache ?? [];
    }

    /**
     * Le cache est vidé à chaque écriture et n'existe que le temps d'une
     * requête : en PHP rien ne survit d'un appel au suivant, il n'y a donc
     * aucun risque qu'un réglage modifié reste périmé.
     */
    public static function forget(): void
    {
        self::$cache = null;
    }

    private static function load(): void
    {
        if (self::$cache !== null) {
            return;
        }

        $rows = Database::run('SELECT name, value FROM settings')->fetchAll();
        self::$cache = array_column($rows, 'value', 'name');
    }
}
