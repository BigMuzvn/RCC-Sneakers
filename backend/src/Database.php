<?php

namespace Rcc;

use PDO;

/**
 * Connexion PDO unique.
 *
 * La constante RCC_TESTING est définie par tests/bootstrap.php : les tests
 * tombent donc sur rcc_sneakers_test et ne peuvent pas toucher la base de
 * travail, même en cas d'erreur d'un test.
 */
class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $db = Config::get(defined('RCC_TESTING') ? 'db_test' : 'db');

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $db['host'],
            $db['port'],
            $db['name']
        );

        self::$pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Les vraies requêtes préparées, pas l'émulation : les entiers
            // restent des entiers et l'injection SQL est structurellement exclue.
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$pdo;
    }

    /** @param array<int|string,mixed> $params */
    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    /**
     * @param array<int|string,mixed> $params
     * @return array<string,mixed>|null
     */
    public static function first(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /** Horodatage UTC au format DATETIME de MySQL. */
    public static function now(int $offsetSeconds = 0): string
    {
        return gmdate('Y-m-d H:i:s', time() + $offsetSeconds);
    }
}
