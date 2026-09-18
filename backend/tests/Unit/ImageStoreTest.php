<?php

namespace Rcc\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rcc\ImageStore;
use RuntimeException;

class ImageStoreTest extends TestCase
{
    /** @var array<int,string> noms créés pendant le test, effacés après */
    private array $crees = [];

    protected function tearDown(): void
    {
        foreach ($this->crees as $nom) {
            ImageStore::delete($nom);
        }

        $this->crees = [];
    }

    /** Fabrique une entrée $_FILES à partir d'un fichier de travail. */
    private function upload(string $path): array
    {
        return ['tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path), 'name' => basename($path)];
    }

    /** Crée un PNG transparent de la taille demandée. */
    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocatealpha($image, 0, 0, 0, 127));
        // Un disque opaque au centre, pour vérifier que le contenu survit.
        imagefilledellipse($image, (int) ($width / 2), (int) ($height / 2), (int) ($width / 2), (int) ($height / 2), imagecolorallocate($image, 220, 40, 60));

        $path = sys_get_temp_dir() . '/rcc-test-' . bin2hex(random_bytes(6)) . '.png';
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    public function test_un_png_est_accepte_et_converti_en_webp(): void
    {
        $source = $this->png(800, 600);

        $nom = ImageStore::store($this->upload($source));
        $this->crees[] = $nom;
        unlink($source);

        $this->assertStringEndsWith('.webp', $nom);
        $this->assertFileExists(ImageStore::directory() . '/' . $nom);
        $this->assertSame(IMAGETYPE_WEBP, getimagesize(ImageStore::directory() . '/' . $nom)[2]);
    }

    /**
     * Les rendus produits sont détourés : un fond opaque les collerait sur un
     * carré blanc au milieu d'une carte.
     */
    public function test_la_transparence_est_conservee(): void
    {
        $source = $this->png(400, 400);

        $nom = ImageStore::store($this->upload($source));
        $this->crees[] = $nom;
        unlink($source);

        $image = imagecreatefromwebp(ImageStore::directory() . '/' . $nom);
        $coin = imagecolorat($image, 2, 2);
        $alpha = ($coin >> 24) & 0x7F;
        imagedestroy($image);

        $this->assertGreaterThan(100, $alpha, 'le coin devait rester transparent');
    }

    /**
     * Une image démesurée est ramenée à une taille utile : au-delà de 1200 px,
     * aucune carte ni fiche n'en profite, et le poids pénalise chaque visiteur.
     */
    public function test_une_image_trop_grande_est_reduite(): void
    {
        $source = $this->png(2400, 1800);

        $nom = ImageStore::store($this->upload($source));
        $this->crees[] = $nom;
        unlink($source);

        [$width, $height] = getimagesize(ImageStore::directory() . '/' . $nom);

        $this->assertSame(1200, $width);
        $this->assertSame(900, $height, 'les proportions devaient être conservées');
    }

    public function test_une_petite_image_nest_pas_agrandie(): void
    {
        $source = $this->png(300, 200);

        $nom = ImageStore::store($this->upload($source));
        $this->crees[] = $nom;
        unlink($source);

        $this->assertSame([300, 200], array_slice(getimagesize(ImageStore::directory() . '/' . $nom), 0, 2));
    }

    /**
     * Le test qui justifie le ré-encodage. Un fichier renommé en .png passerait
     * tout contrôle basé sur l'extension ; ici c'est l'en-tête réel qui décide.
     */
    public function test_un_fichier_deguise_en_image_est_refuse(): void
    {
        $path = sys_get_temp_dir() . '/rcc-test-' . bin2hex(random_bytes(6)) . '.png';
        file_put_contents($path, "<?php echo 'bonjour'; ?>");

        $this->expectException(RuntimeException::class);

        try {
            ImageStore::store($this->upload($path));
        } finally {
            unlink($path);
        }
    }

    public function test_un_televersement_en_erreur_est_signale_clairement(): void
    {
        $this->expectExceptionMessageMatches('/trop lourd/i');

        ImageStore::store(['tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE]);
    }

    /**
     * Un nom fabriqué ne doit pas permettre de sortir du dossier des visuels et
     * d'effacer autre chose.
     */
    public function test_la_suppression_ne_sort_pas_du_dossier(): void
    {
        $temoin = ImageStore::directory() . '/../temoin-a-ne-pas-effacer.txt';
        file_put_contents($temoin, 'intact');

        ImageStore::delete('../temoin-a-ne-pas-effacer.txt');
        ImageStore::delete('../../config.php');

        $this->assertFileExists($temoin);
        $this->assertFileExists(dirname(__DIR__, 2) . '/config.php');

        unlink($temoin);
    }
}
