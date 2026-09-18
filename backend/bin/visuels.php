<?php

/**
 * Importe les rendus livrés avec le dépôt dans les visuels du serveur.
 *
 *   php bin/visuels.php            # importe ce qui manque
 *   php bin/visuels.php --liste    # n'écrit rien, dit seulement l'état
 *
 * Les quatre premiers rendus ont été intégrés au front avant qu'il existe une
 * administration : ce sont des modules compilés par Vite, et `products.image`
 * n'en gardait que le nom de fichier. Or cette colonne désigne un fichier de
 * `public/uploads` — le nom ne pointait donc sur rien, et l'administration
 * affichait ces paires sans leur image.
 *
 * L'import passe par `ImageStore`, exactement comme un envoi depuis
 * l'administration : même ré-encodage en WebP, même limite de 1200 px, même
 * allègement. Aucune seconde voie d'entrée pour les images, donc aucune voie
 * moins surveillée que l'autre.
 *
 * Le script est idempotent : un visuel déjà en place est laissé tel quel. Il
 * peut donc être rejoué sans risque après un semis, et ne touche jamais à un
 * visuel envoyé par le gérant.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/autoload.php';

use Rcc\Config;
use Rcc\Database;
use Rcc\ImageStore;

Config::load();

$listeSeulement = in_array('--liste', $argv, true);

/** Les rendus vivent avec le front, seul endroit où ils étaient utilisés. */
$depot = dirname(__DIR__, 2) . '/frontend/src/assets';

if (!is_dir($depot)) {
    fwrite(STDERR, "Dossier des rendus introuvable : {$depot}\n");
    exit(1);
}

$uploads = ImageStore::directory();
$poids = static fn (int $octets): string => number_format($octets / 1024, 0, ',', ' ') . ' Ko';

$importes = 0;
$enPlace = 0;
$manquants = 0;
$gagne = 0;

foreach ([['products', 'sneakers'], ['jerseys', 'maillots']] as [$table, $libelle]) {
    $lignes = Database::run(
        "SELECT id, slug, image FROM {$table} WHERE image IS NOT NULL AND image <> '' ORDER BY id"
    )->fetchAll();

    echo strtoupper($libelle) . ' — ' . count($lignes) . " article(s) avec une référence de visuel\n";

    foreach ($lignes as $ligne) {
        $nom = (string) $ligne['image'];
        $slug = $ligne['slug'];

        // Déjà servable : rien à faire. C'est ce test qui rend le script
        // rejouable, et qui protège les envois du gérant.
        if (str_ends_with($nom, '.webp') && is_file($uploads . '/' . basename($nom))) {
            $enPlace++;
            echo "  = {$slug} — déjà en place ({$nom})\n";
            continue;
        }

        $source = $depot . '/' . basename($nom);

        if (!is_file($source)) {
            $manquants++;
            echo "  ! {$slug} — « {$nom} » ne correspond à aucun fichier, ni dans les visuels ni dans le dépôt\n";
            continue;
        }

        if ($listeSeulement) {
            $importes++;
            echo "  + {$slug} — à importer depuis {$nom} (" . $poids(filesize($source)) . ")\n";
            continue;
        }

        $avant = filesize($source);

        try {
            // ImageStore attend une entrée de $_FILES : on lui en fabrique une.
            // Le fichier vient du dépôt et non du réseau, mais il subit
            // exactement les mêmes vérifications et le même ré-encodage.
            $nouveau = ImageStore::store([
                'error' => UPLOAD_ERR_OK,
                'tmp_name' => $source,
                'size' => $avant,
                'name' => basename($source),
            ]);
        } catch (Throwable $e) {
            $manquants++;
            echo "  ! {$slug} — import refusé : " . $e->getMessage() . "\n";
            continue;
        }

        Database::run(
            "UPDATE {$table} SET image = ?, updated_at = ? WHERE id = ?",
            [$nouveau, Database::now(), (int) $ligne['id']]
        );

        $apres = filesize($uploads . '/' . $nouveau);
        $gagne += $avant - $apres;
        $importes++;

        $reduction = $avant > 0 ? (int) round(100 - ($apres * 100 / $avant)) : 0;
        echo "  + {$slug} — {$poids($avant)} → {$poids($apres)} (−{$reduction} %) {$nouveau}\n";
    }

    echo "\n";
}

echo $listeSeulement
    ? "{$importes} à importer, {$enPlace} déjà en place, {$manquants} sans fichier correspondant.\n"
    : "{$importes} importé(s), {$enPlace} déjà en place, {$manquants} sans fichier correspondant"
        . ($gagne > 0 ? ' — ' . $poids($gagne) . " économisés.\n" : ".\n");
