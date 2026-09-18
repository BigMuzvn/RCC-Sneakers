<?php

/**
 * Exporte la base en un fichier SQL prêt à importer.
 *
 *   php bin/exporter.php                      # vers storage/export-AAAAMMJJ.sql
 *   php bin/exporter.php chemin/vers/fic.sql  # vers un chemin choisi
 *
 * Écrit pour les hébergements sans accès SSH — la quasi-totalité des
 * mutualisés, et notamment ceux où l'on pose une démonstration. Là-bas,
 * `migrations/run.php` et `bin/admin.php` ne peuvent pas être lancés : ils
 * refusent de tourner ailleurs qu'en ligne de commande, et c'est voulu. Une
 * page « devenir administrateur », même bien cachée, finit toujours par être
 * trouvée.
 *
 * Importer ce fichier par phpMyAdmin emporte donc d'un seul geste le schéma,
 * le catalogue, les réglages, les commandes **et** le compte administrateur
 * déjà promu. Il ne reste que les visuels à envoyer par FTP.
 *
 * Le fichier ne contient ni `CREATE DATABASE` ni `USE` : le nom de la base est
 * imposé par l'hébergeur, et le choisir ici ferait échouer l'import.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/autoload.php';

use Rcc\Config;
use Rcc\Database;

Config::load();

/**
 * Tables dont la **structure** part, mais pas le contenu.
 *
 * Des sessions ouvertes, des jetons à usage unique et des compteurs de
 * limitation de débit : tout cela est attaché à une machine, à un instant et à
 * des cookies qui n'existeront pas là-bas. Les emporter n'aurait aucun effet
 * utile, et poserait des jetons valides sur un serveur public.
 */
const SANS_DONNEES = ['auth_tokens', 'auth_attempts', 'customer_tokens'];

/** Insertions groupées : phpMyAdmin avale mal une requête de mille lignes. */
const PAR_PAQUET = 40;

$destination = $argv[1] ?? null;

if ($destination === null) {
    $dossier = dirname(__DIR__) . '/storage';

    if (!is_dir($dossier) && !@mkdir($dossier, 0775, true) && !is_dir($dossier)) {
        fwrite(STDERR, "Le dossier storage/ n'a pas pu être créé.\n");
        exit(1);
    }

    $destination = $dossier . '/export-' . gmdate('Ymd-Hi') . '.sql';
}

$pdo = Database::connection();
$base = (string) Config::get('db.name');

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
sort($tables);

$sortie = fopen($destination, 'wb');

if ($sortie === false) {
    fwrite(STDERR, "Écriture impossible dans {$destination}\n");
    exit(1);
}

$ecrire = static function (string $ligne) use ($sortie): void {
    fwrite($sortie, $ligne . "\n");
};

$ecrire('-- RCC Sneakers — export de la base « ' . $base . ' »');
$ecrire('-- Produit le ' . gmdate('d/m/Y à H:i') . ' UTC par bin/exporter.php');
$ecrire('--');
$ecrire('-- À importer tel quel dans phpMyAdmin, sur une base déjà créée par');
$ecrire("-- l'hébergeur. Les visuels des articles ne sont pas ici : ce sont des");
$ecrire('-- fichiers, à envoyer par FTP dans api/uploads/.');
$ecrire('');
$ecrire('SET NAMES utf8mb4;');
$ecrire('SET FOREIGN_KEY_CHECKS = 0;');
$ecrire('');

$resume = [];

foreach ($tables as $table) {
    $creation = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);

    $ecrire('-- ---------------------------------------------------------------');
    $ecrire("-- {$table}");
    $ecrire('-- ---------------------------------------------------------------');
    $ecrire('');
    $ecrire("DROP TABLE IF EXISTS `{$table}`;");
    $ecrire($creation['Create Table'] . ';');
    $ecrire('');

    if (in_array($table, SANS_DONNEES, true)) {
        $ecrire("-- Contenu volontairement omis : attaché à une machine et à un instant.");
        $ecrire('');
        $resume[$table] = 'structure seule';

        continue;
    }

    $lignes = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);

    if ($lignes === []) {
        $ecrire('');
        $resume[$table] = '0 ligne';

        continue;
    }

    $colonnes = '`' . implode('`, `', array_keys($lignes[0])) . '`';

    foreach (array_chunk($lignes, PAR_PAQUET) as $paquet) {
        $valeurs = [];

        foreach ($paquet as $ligne) {
            $cellules = array_map(
                static fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v),
                array_values($ligne)
            );

            $valeurs[] = '(' . implode(', ', $cellules) . ')';
        }

        $ecrire("INSERT INTO `{$table}` ({$colonnes}) VALUES");
        $ecrire(implode(",\n", $valeurs) . ';');
        $ecrire('');
    }

    $resume[$table] = count($lignes) . ' ligne' . (count($lignes) > 1 ? 's' : '');
}

$ecrire('SET FOREIGN_KEY_CHECKS = 1;');
fclose($sortie);

// ------------------------------------------------------------------ rapport

echo "Export écrit : {$destination}\n";
printf("Poids : %s Ko\n\n", number_format(filesize($destination) / 1024, 1, ',', ' '));

foreach ($resume as $table => $etat) {
    printf("  %-24s %s\n", $table, $etat);
}

$admins = Database::run('SELECT email FROM customers WHERE is_admin = 1')->fetchAll(PDO::FETCH_COLUMN);
$visuels = glob(dirname(__DIR__) . '/public/uploads/*.webp') ?: [];

echo "\nAdministrateur(s) emporté(s) : " . (implode(', ', $admins) ?: 'aucun') . "\n";
echo 'Visuels à envoyer par FTP    : ' . count($visuels) . " fichiers dans public/uploads/\n";
