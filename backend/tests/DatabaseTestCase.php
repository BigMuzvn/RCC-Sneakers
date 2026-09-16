<?php

namespace Rcc\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use Rcc\Config;
use Rcc\Database;
use RuntimeException;

/**
 * Socle des tests qui touchent la base. Chaque test part d'une base vide.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        Config::load();
        $this->truncateAll();
    }

    private function truncateAll(): void
    {
        $name = Config::get('db_test.name');

        // Garde-fou. Une faute de frappe dans config.php ne doit jamais pouvoir
        // vider la base de travail : on refuse tout nom qui ne finit pas par _test.
        if (!is_string($name) || !str_ends_with($name, '_test')) {
            throw new RuntimeException(
                "Refus de vider « {$name} » : le nom d'une base de test doit finir par _test."
            );
        }

        $pdo = Database::connection();
        $actual = $pdo->query('SELECT DATABASE()')->fetchColumn();

        if ($actual !== $name) {
            throw new RuntimeException(
                "La connexion pointe sur « {$actual} » et non sur « {$name} »."
            );
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            if ($table !== 'migrations') {
                // DELETE et non TRUNCATE : sur InnoDB, TRUNCATE supprime et
                // recrée le fichier de tablespace. Mesuré sur ces quatre tables
                // vides — 4 260 ms contre 10 ms, soit 426 fois plus lent. Une
                // suite de tests lente finit par ne plus être lancée.
                $pdo->exec(sprintf('DELETE FROM `%s`', $table));
            }
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /** Insère un client et renvoie son identifiant. */
    protected function createCustomer(
        string $email = 'client@exemple.com',
        string $phone = '2290197000000',
        string $password = 'motdepasse',
        ?string $verifiedAt = null,
    ): int {
        Database::run(
            'INSERT INTO customers
                (name, email, phone, phone_display, password_hash, email_verified_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                'Client Test',
                $email,
                $phone,
                $phone,
                password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]),
                $verifiedAt,
                Database::now(),
                Database::now(),
            ]
        );

        return (int) Database::connection()->lastInsertId();
    }
}
