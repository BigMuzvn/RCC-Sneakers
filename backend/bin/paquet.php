<?php

/**
 * Fabrique l'archive à téléverser chez l'hébergeur.
 *
 *   php bin/paquet.php                # vers storage/rcc-AAAAMMJJ.zip
 *   php bin/paquet.php mon-paquet.zip
 *
 * Le contenu de `htdocs/` dans l'archive est **exactement** ce qui doit se
 * retrouver dans la racine web. C'est une disposition à plat, choisie pour les
 * hébergements qui n'offrent aucun dossier au-dessus de cette racine — le cas
 * de la plupart des offres gratuites :
 *
 *   htdocs/
 *     index.html, assets/, …      le site
 *     autoload.php, config.php    le code de l'API, fermé par le .htaccess
 *     src/                        du site, juste au-dessus
 *     api/                        le point d'entrée, et les visuels
 *
 * `config.php` y est réécrit pour cette disposition : environnement de
 * production, traces masquées, aucune origine croisée à autoriser puisque le
 * site et l'API partagent le domaine, et le dossier des visuels désigné
 * explicitement — sans quoi l'API les chercherait à côté de `src/`, où ils ne
 * sont pas.
 *
 * Les valeurs propres à l'hébergement sont lues dans `storage/deploiement.php`,
 * git-ignoré — les identifiants d'un hébergeur n'ont rien à faire dans le
 * dépôt. Sans ce fichier, le paquet se fabrique avec des marqueurs bien
 * visibles : mieux vaut un déploiement qui s'arrête qu'un déploiement qui
 * tourne avec l'adresse de quelqu'un d'autre.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/autoload.php';

use Rcc\Config;

Config::load();

$racine = dirname(__DIR__, 2);
$backend = dirname(__DIR__);
$dist = $racine . '/frontend/dist';

if (!is_file($dist . '/index.html')) {
    fwrite(STDERR, "frontend/dist est absent ou incomplet. Lancez d'abord : cd frontend && npm run build\n");
    exit(1);
}

$destination = $argv[1] ?? ($backend . '/storage/rcc-' . gmdate('Ymd-Hi') . '.zip');

if (!is_dir(dirname($destination))) {
    @mkdir(dirname($destination), 0775, true);
}

$zip = new ZipArchive();

if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "L'archive n'a pas pu être créée : {$destination}\n");
    exit(1);
}

$compte = 0;
$poids = 0;

/** Ajoute un fichier, en gardant trace de ce qui est parti. */
$ajouter = static function (string $source, string $dans) use ($zip, &$compte, &$poids): void {
    $zip->addFile($source, $dans);
    $compte++;
    $poids += filesize($source);
};

/** Ajoute un dossier entier, fichiers cachés compris — `.htaccess` en est un. */
$ajouterDossier = static function (string $source, string $dans) use ($ajouter, &$ajouterDossier): void {
    foreach (scandir($source) ?: [] as $entree) {
        if ($entree === '.' || $entree === '..') {
            continue;
        }

        $chemin = $source . '/' . $entree;

        if (is_dir($chemin)) {
            $ajouterDossier($chemin, $dans . '/' . $entree);
        } else {
            $ajouter($chemin, $dans . '/' . $entree);
        }
    }
};

// ------------------------------------------------------------------- le site

$ajouterDossier($dist, 'htdocs');

// ------------------------------------------------------------------- l'API

$ajouter($backend . '/autoload.php', 'htdocs/autoload.php');
$ajouterDossier($backend . '/src', 'htdocs/src');
$ajouterDossier($backend . '/public', 'htdocs/api');

// ------------------------------------------------------- la configuration

$cle = (string) Config::get('mail.brevo_key', '');

$fichierCible = $backend . '/storage/deploiement.php';
$cible = is_file($fichierCible) ? require $fichierCible : [];

$url = trim((string) ($cible['url'] ?? ''));
$db = ($cible['db'] ?? []) + ['host' => '', 'port' => 3306, 'name' => '', 'user' => '', 'pass' => ''];

/** Ce qui manque est nommé, jamais deviné. */
$manquants = [];

foreach (['url' => $url, 'db.host' => $db['host'], 'db.name' => $db['name'], 'db.user' => $db['user']] as $quoi => $valeur) {
    if ((string) $valeur === '') {
        $manquants[] = $quoi;
    }
}

$lignesDb = sprintf(
    "        'host' => %s
        'port' => %d,
        'name' => %s
        'user' => %s
        'pass' => %s",
    $db['host'] !== '' ? var_export($db['host'], true) . ',' : "'sqlXXX.hebergeur.com',  // << À REMPLIR >>",
    (int) $db['port'],
    $db['name'] !== '' ? var_export($db['name'], true) . ',' : "'nom_de_la_base',  // << À REMPLIR >>",
    $db['user'] !== '' ? var_export($db['user'], true) . ',' : "'utilisateur',  // << À REMPLIR >>",
    var_export((string) $db['pass'], true) . ','
);

// L'en-tête ne parle de valeurs à renseigner que s'il en reste : annoncer un
// travail déjà fait envoie chercher quelque chose qui n'existe pas.
$avertissement = $manquants === []
    ? ''
    : "
 *
 * Ce qui reste marqué « À REMPLIR » appartient à l'hébergeur : l'inventer
"
        . " * ferait échouer le déploiement sans rien dire.";

$ligneUrl = $url !== ''
    ? var_export($url, true) . ','
    : "'https://exemple.infinityfreeapp.com',  // << À REMPLIR >>";

$config = <<<PHP
<?php

/**
 * Configuration du site en ligne, fabriquée par bin/paquet.php.{$avertissement}
 */

return [
    'app' => [
        'env'   => 'production',
        'debug' => false,

        // L'adresse publique du site, sans barre finale. C'est elle qui
        // fabrique les liens des e-mails : fausse, aucune vérification
        // d'adresse ni réinitialisation de mot de passe n'aboutira, et rien
        // ne le signalera.
        'url'   => {$ligneUrl}

        // Vides : le site et l'API partagent le domaine.
        'trusted_proxies' => [],
        'cors_origins'    => [],
    ],

    // Les visuels vivent à côté du point d'entrée, et non à côté de `src/`
    // comme dans le dépôt. Sans cette ligne, l'administration les chercherait
    // au mauvais endroit et les nouveaux envois tomberaient dans le vide.
    'storage' => [
        'uploads' => __DIR__ . '/api/uploads',
    ],

    'db' => [
{$lignesDb}
    ],

    'session' => [
        'remember_days' => 30,
    ],

    'auth' => [
        'bcrypt_cost' => 12,
    ],

    'mail' => [
        'driver'        => 'brevo',
        'brevo_key'     => '{$cle}',
        'brevo_list_id' => 0,
        'from_email'    => 'godsonmailperso@gmail.com',
        'from_name'     => 'RCC Sneakers',
    ],
];

PHP;

$zip->addFromString('htdocs/config.php', $config);
$compte++;

// ------------------------------------------------- la base et la marche à suivre

$exports = glob($backend . '/storage/export-*.sql') ?: [];

if ($exports !== []) {
    usort($exports, static fn ($a, $b) => filemtime($b) <=> filemtime($a));
    $zip->addFile($exports[0], 'base-a-importer.sql');
    $compte++;
    $poids += filesize($exports[0]);
}

$zip->addFromString('LISEZ-MOI.txt', require __DIR__ . '/paquet-notice.php');
$compte++;

$zip->close();

printf("Archive : %s\n", $destination);
printf("Poids   : %s Ko (%d fichiers)\n\n", number_format(filesize($destination) / 1024, 1, ',', ' '), $compte);
printf("Visuels emportés : %d\n", count(glob($backend . '/public/uploads/*.webp') ?: []));
printf("Base emportée    : %s\n", $exports === [] ? 'AUCUNE — lancez bin/exporter.php' : basename($exports[0]));
if ($manquants === []) {
    echo "\nConfiguration complète : rien à renseigner avant l'envoi.\n";
} else {
    echo "\nÀ renseigner dans htdocs/config.php avant l'envoi : " . implode(', ', $manquants) . "\n";
    echo "(ou dans storage/deploiement.php, puis refabriquer le paquet)\n";
}
