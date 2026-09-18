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
    /**
     * Tables épargnées par le nettoyage : le catalogue est une donnée de
     * référence, chargée une fois par `php migrations/seed.php --test`. La vider
     * à chaque test obligerait à la recharger 130 fois pour rien.
     *
     * Les tests qui touchent au stock le fixent explicitement (`setStock`) au
     * lieu de dépendre des valeurs du semoir : une assertion qui repose sur
     * « il en reste 4 » casse le jour où le catalogue change.
     */
    private const PRESERVED = [
        'migrations',
        'products', 'product_variants',
        'jerseys', 'jersey_variants',
    ];

    /**
     * Zones de livraison remises à neuf avant chaque test.
     *
     * Elles ne sont pas préservées comme le catalogue : elles tiennent en trois
     * lignes, et l'administration les modifie. Les garder ferait fuir un tarif
     * ou un libellé changé par un test dans tous les suivants — ce qui est
     * arrivé, un test de commande héritant d'une zone renommée.
     *
     * @var array<int,array{0:string,1:string,2:string,3:int,4:int}>
     */
    private const ZONES = [
        ['cotonou', 'Cotonou', 'sous 24 h', 1000, 1],
        ['nokoue', 'Grand Nokoué', 'sous 48 h', 1500, 2],
        ['benin', 'Reste du Bénin', 'sous 72 h', 2500, 3],
    ];

    protected function setUp(): void
    {
        Config::load();
        $this->truncateAll();
        $this->resetDeliveryZones();
    }

    private function resetDeliveryZones(): void
    {
        foreach (self::ZONES as [$id, $label, $delay, $fee, $position]) {
            Database::run(
                'INSERT INTO delivery_zones (id, label, delay_label, fee_xof, position, is_active)
                 VALUES (?, ?, ?, ?, ?, 1)',
                [$id, $label, $delay, $fee, $position]
            );
        }
    }

    /** Fixe le stock d'une taille précise, pour rendre le test déterministe. */
    protected function setStock(string $type, int $id, string $size, int $stock): void
    {
        [$table, $key] = $type === 'jersey'
            ? ['jersey_variants', 'jersey_id']
            : ['product_variants', 'product_id'];

        Database::run(
            "UPDATE {$table} SET stock = ? WHERE {$key} = ? AND size = ?",
            [$stock, $id, $size]
        );
    }

    protected function stockOf(string $type, int $id, string $size): int
    {
        [$table, $key] = $type === 'jersey'
            ? ['jersey_variants', 'jersey_id']
            : ['product_variants', 'product_id'];

        $row = Database::first("SELECT stock FROM {$table} WHERE {$key} = ? AND size = ?", [$id, $size]);

        return (int) ($row['stock'] ?? -1);
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
            if (!in_array($table, self::PRESERVED, true)) {
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
