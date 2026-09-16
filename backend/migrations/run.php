<?php

/**
 * Applique les migrations SQL dans l'ordre des noms de fichiers.
 *
 *   php migrations/run.php           base de travail
 *   php migrations/run.php --test    base de test
 *   php migrations/run.php --fresh   supprime toutes les tables puis rejoue
 *
 * Volontairement minimal : pas d'annulation. Sur un mutualisé on ne dispose ni
 * de shell ni d'outil de migration, donc le schéma doit rester rejouable à la
 * main depuis phpMyAdmin si besoin — chaque fichier est autonome et idempotent.
 */

$root = dirname(__DIR__);
$config = require $root . '/config.php';

$useTest = in_array('--test', $argv, true);
$fresh = in_array('--fresh', $argv, true);
$db = $config[$useTest ? 'db_test' : 'db'];

$dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'], $db['port']);
$pdo = new PDO($dsn, $db['user'], $db['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$pdo->exec(sprintf(
    'CREATE DATABASE IF NOT EXISTS `%s` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
    $db['name']
));
$pdo->exec(sprintf('USE `%s`', $db['name']));

echo "Base : {$db['name']}\n";

if ($fresh) {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $pdo->exec(sprintf('DROP TABLE `%s`', $table));
        echo "  supprimée  $table\n";
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migrations (
        id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        filename   VARCHAR(191) NOT NULL,
        applied_at DATETIME     NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_migrations_filename (filename)
    ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$applied = $pdo->query('SELECT filename FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
$files = glob(__DIR__ . '/*.sql');
sort($files);

$count = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        echo "  déjà là    $name\n";
        continue;
    }

    $pdo->exec(file_get_contents($file));

    $stmt = $pdo->prepare('INSERT INTO migrations (filename, applied_at) VALUES (?, ?)');
    $stmt->execute([$name, gmdate('Y-m-d H:i:s')]);

    echo "  appliquée  $name\n";
    $count++;
}

echo $count === 0 ? "Rien à faire.\n" : "$count migration(s) appliquée(s).\n";
