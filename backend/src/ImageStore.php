<?php

namespace Rcc;

use RuntimeException;

/**
 * Stockage des visuels produits.
 *
 * Le fichier sur le disque, son chemin en base. Mettre l'image *dans* la base
 * gonflerait chaque sauvegarde, se relirait lentement et empêcherait le
 * navigateur de la mettre en cache.
 *
 * Toute image reçue est **ré-encodée**, jamais recopiée telle quelle. Trois
 * bénéfices d'un seul geste :
 *
 *  - sécurité : un PHP dissimulé dans les octets d'un PNG ne survit pas à un
 *    décodage suivi d'un ré-encodage ;
 *  - poids : les rendus actuels pèsent 1,5 à 2,6 Mo, ce qui est intenable pour
 *    une page de boutique. En WebP à 1200 px, ils tombent à quelques dizaines
 *    de kilo-octets ;
 *  - cohérence : tous les visuels finissent au même format et à la même échelle,
 *    quoi qu'envoie le gérant.
 */
class ImageStore
{
    /** Largeur maximale conservée : au-delà, aucune carte ni fiche n'en profite. */
    private const MAX_WIDTH = 1200;
    private const MAX_HEIGHT = 1200;

    private const QUALITY = 82;

    /** @var array<string,true> types acceptés, vérifiés sur le contenu et non sur le nom */
    private const ACCEPTED = [
        IMAGETYPE_PNG => true,
        IMAGETYPE_JPEG => true,
        IMAGETYPE_WEBP => true,
    ];

    /**
     * Enregistre un fichier téléversé et renvoie son nom.
     *
     * @param  array{tmp_name?:string,error?:int,size?:int,name?:string} $file entrée de $_FILES
     * @throws RuntimeException message destiné au gérant
     */
    public static function store(array $file): string
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException(self::describeError((int) $error));
        }

        $tmp = $file['tmp_name'] ?? '';

        if ($tmp === '' || !is_readable($tmp)) {
            throw new RuntimeException('Le fichier envoyé est illisible.');
        }

        // getimagesize lit l'en-tête réel : un .php renommé en .png échoue ici,
        // là où se fier à l'extension l'aurait laissé passer.
        $info = @getimagesize($tmp);

        if ($info === false || !isset(self::ACCEPTED[$info[2]])) {
            throw new RuntimeException('Format non reconnu. Envoyez un PNG, un JPEG ou un WebP.');
        }

        [$width, $height, $type] = $info;

        // Garde-fou mémoire avant de décoder : une image de 20 000 px de côté
        // tient dans quelques kilo-octets compressés et réclame des gigaoctets
        // une fois dépliée en mémoire.
        if ($width * $height > 40_000_000) {
            throw new RuntimeException('Image trop grande. 40 millions de pixels au maximum.');
        }

        $source = match ($type) {
            IMAGETYPE_PNG => @imagecreatefrompng($tmp),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
            IMAGETYPE_WEBP => @imagecreatefromwebp($tmp),
            default => false,
        };

        if ($source === false) {
            throw new RuntimeException("Cette image n'a pas pu être lue.");
        }

        try {
            $canvas = self::resize($source, $width, $height);
            $name = bin2hex(random_bytes(12)) . '.webp';
            $path = self::directory() . '/' . $name;

            // La transparence doit survivre : les rendus produits sont détourés,
            // un fond opaque les collerait sur un carré blanc.
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);

            if (!imagewebp($canvas, $path, self::QUALITY)) {
                throw new RuntimeException("L'image n'a pas pu être enregistrée.");
            }

            if ($canvas !== $source) {
                imagedestroy($canvas);
            }

            return $name;
        } finally {
            imagedestroy($source);
        }
    }

    /** Supprime un visuel. Silencieux : un fichier déjà absent n'est pas une erreur. */
    public static function delete(?string $name): void
    {
        if ($name === null || $name === '' || !str_ends_with($name, '.webp')) {
            return;
        }

        // basename coupe court à toute tentative de remonter l'arborescence
        // avec un nom du genre « ../../config.php ».
        $path = self::directory() . '/' . basename($name);

        if (is_file($path)) {
            @unlink($path);
        }
    }

    /** Dossier des visuels, créé au besoin. */
    public static function directory(): string
    {
        $path = dirname(__DIR__) . '/public/uploads';

        if (!is_dir($path) && !@mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException("Le dossier des visuels n'a pas pu être créé.");
        }

        return $path;
    }

    /** @param \GdImage $source */
    private static function resize(\GdImage $source, int $width, int $height): \GdImage
    {
        if ($width <= self::MAX_WIDTH && $height <= self::MAX_HEIGHT) {
            return $source;
        }

        $ratio = min(self::MAX_WIDTH / $width, self::MAX_HEIGHT / $height);
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $canvas;
    }

    private static function describeError(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => sprintf(
                'Fichier trop lourd. Maximum %s.',
                ini_get('upload_max_filesize')
            ),
            UPLOAD_ERR_PARTIAL => 'Le transfert a été interrompu. Réessayez.',
            UPLOAD_ERR_NO_FILE => 'Aucun fichier reçu.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => "Le serveur n'a pas pu écrire le fichier.",
            default => "Le téléversement a échoué.",
        };
    }
}
