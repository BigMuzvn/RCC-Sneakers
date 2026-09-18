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
 * Quatre valeurs restent à renseigner : elles n'appartiennent qu'à
 * l'hébergeur, et les inventer ici ferait échouer le déploiement en silence.
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

$config = <<<PHP
<?php

/**
 * Configuration du site en ligne.
 *
 * Quatre valeurs sont à renseigner, marquées ci-dessous. Elles appartiennent à
 * l'hébergeur : les inventer ferait échouer le déploiement sans rien dire.
 */

return [
    'app' => [
        'env'   => 'production',
        'debug' => false,

        // << À REMPLIR >> l'adresse publique du site, sans barre finale.
        // C'est elle qui fabrique les liens des e-mails : laissée fausse,
        // aucune vérification d'adresse ni réinitialisation de mot de passe
        // n'aboutira, et rien ne le signalera.
        'url'   => 'https://exemple.infinityfreeapp.com',

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
        'host' => 'sqlXXX.infinityfree.com',  // << À REMPLIR >>
        'port' => 3306,
        'name' => 'ifX_XXXXXXX_rcc',          // << À REMPLIR >>
        'user' => 'ifX_XXXXXXX',              // << À REMPLIR >>
        'pass' => '',                         // << À REMPLIR >>
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
echo "\nQuatre valeurs restent à renseigner dans htdocs/config.php avant l'envoi.\n";
