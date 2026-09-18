<?php

namespace Rcc\Tests\Feature;

use Rcc\Database;
use Rcc\ImageStore;
use Rcc\Tests\ApiTestCase;

class AdminCatalogueTest extends ApiTestCase
{
    /** Dernier identifiant du catalogue chargé par le semoir. */
    private const DERNIER_PRODUIT = 20;
    private const DERNIER_MAILLOT = 12;

    protected function setUp(): void
    {
        parent::setUp();

        // Le catalogue est préservé entre les tests — c'est une donnée de
        // référence, la recharger 180 fois serait absurde. En contrepartie, les
        // articles créés par un test survivraient au suivant et fausseraient
        // ses comptes : on les retire, et on remet en vente ce qui a été
        // archivé.
        Database::run('DELETE FROM products WHERE id > ?', [self::DERNIER_PRODUIT]);
        Database::run('DELETE FROM jerseys WHERE id > ?', [self::DERNIER_MAILLOT]);
        Database::run('UPDATE products SET is_active = 1');
        Database::run('UPDATE jerseys SET is_active = 1');

        $this->loginAsAdmin();
    }

    /** @param array<string,mixed> $override */
    private function produit(array $override = []): array
    {
        return array_merge([
            'brand' => 'New Balance',
            'model' => '1906R',
            'sku' => 'M1906RCD',
            'category' => 'lifestyle',
            'gender' => 'unisexe',
            'colorway' => 'Gris / Argent',
            'description' => 'Une silhouette de running du début des années 2000, remise au goût du jour.',
            'price_xof' => 89000,
            'accent' => '#8A8F96',
        ], $override);
    }

    /** @param array<string,mixed> $override */
    private function maillot(array $override = []): array
    {
        return array_merge([
            'club' => 'AS Roma',
            'league' => 'Serie A',
            'brand' => 'Adidas',
            'kit' => 'Domicile',
            'season' => '2025/26',
            'colorway' => 'Bordeaux / Or',
            'price_xof' => 62000,
            'accent' => '#6E1B2B',
        ], $override);
    }

    // ------------------------------------------------------- lecture

    public function test_la_liste_montre_le_catalogue_avec_son_stock(): void
    {
        $response = $this->get('/admin/products');

        $this->assertSame(200, $response->status);
        $this->assertCount(20, $response->payload['data']['products']);

        $premier = $response->payload['data']['products'][0];
        $this->assertArrayHasKey('variants', $premier);
        $this->assertArrayHasKey('stock_total', $premier);
        $this->assertSame(['39', '40', '41', '42', '43', '44', '45'], $premier['sizes']);
    }

    public function test_la_recherche_filtre_le_catalogue(): void
    {
        $trouve = $this->request('GET', '/admin/products', ['search' => 'Shox']);

        $this->assertCount(1, $trouve->payload['data']['products']);
        $this->assertSame('Shox TL', $trouve->payload['data']['products'][0]['model']);
    }

    // ------------------------------------------------------- création

    public function test_un_produit_se_cree_avec_toutes_ses_tailles(): void
    {
        $response = $this->post('/admin/products', $this->produit());

        $this->assertSame(201, $response->status);
        $this->assertSame('new-balance-1906r-gris-argent', $response->payload['data']['slug']);

        $id = $response->payload['data']['id'];

        // Toutes les tailles existent d'emblée, à zéro : une taille jamais
        // saisie ne pourrait plus jamais être réapprovisionnée.
        $this->assertSame(7, (int) Database::first(
            'SELECT COUNT(*) c FROM product_variants WHERE product_id = ?',
            [$id]
        )['c']);
    }

    /** Le gérant n'a pas à savoir ce qu'est un slug pour créer un produit. */
    public function test_ladresse_se_deduit_du_nom_et_gere_les_accents(): void
    {
        $response = $this->post('/admin/products', $this->produit([
            'brand' => 'Nike',
            'model' => 'Air Max Déluxe',
            'colorway' => 'Écarlate',
        ]));

        $this->assertSame(201, $response->status, json_encode($response->payload, JSON_UNESCAPED_UNICODE));
        $this->assertSame('nike-air-max-deluxe-ecarlate', $response->payload['data']['slug']);
    }

    public function test_une_adresse_deja_prise_est_refusee(): void
    {
        $this->post('/admin/products', $this->produit());
        $response = $this->post('/admin/products', $this->produit());

        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('slug', $response->payload['error']['fields']);
    }

    public function test_les_champs_sont_valides(): void
    {
        $cas = [
            ['price_xof' => 'gratuit'],
            ['accent' => 'bleu'],
            ['category' => 'aviation'],
            ['gender' => 'martien'],
            ['description' => 'trop court'],
            ['brand' => ''],
        ];

        foreach ($cas as $mauvais) {
            $response = $this->post('/admin/products', $this->produit($mauvais));

            $this->assertSame(422, $response->status, json_encode($mauvais) . ' aurait dû être refusé');
        }
    }

    /** Un prix barré inférieur au prix courant afficherait une remise négative. */
    public function test_un_prix_barre_incoherent_est_refuse(): void
    {
        $response = $this->post('/admin/products', $this->produit([
            'price_xof' => 89000,
            'old_price_xof' => 50000,
        ]));

        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('old_price_xof', $response->payload['error']['fields']);
    }

    public function test_un_maillot_se_cree_avec_ses_cinq_tailles(): void
    {
        $response = $this->post('/admin/jerseys', $this->maillot());

        $this->assertSame(201, $response->status);

        $this->assertSame(5, (int) Database::first(
            'SELECT COUNT(*) c FROM jersey_variants WHERE jersey_id = ?',
            [$response->payload['data']['id']]
        )['c']);
    }

    // ---------------------------------------------------- modification

    public function test_un_produit_se_modifie(): void
    {
        $response = $this->post('/admin/products/1', $this->produit([
            'brand' => 'Nike',
            'model' => 'Shox TL',
            'colorway' => 'Noir / Racer Blue',
            'price_xof' => 91000,
        ]));

        $this->assertSame(200, $response->status);
        $this->assertSame(91000, (int) Database::first('SELECT price_xof FROM products WHERE id = 1')['price_xof']);
    }

    /**
     * Retirer un article le masque de la boutique sans effacer l'historique :
     * les commandes passées le citent encore, avec leur prix d'alors.
     */
    public function test_un_produit_se_retire_de_la_vente(): void
    {
        $this->post('/admin/products/1', $this->produit([
            'brand' => 'Nike', 'model' => 'Shox TL', 'colorway' => 'Noir / Racer Blue',
            'is_active' => false,
        ]));

        $this->assertSame(0, (int) Database::first('SELECT is_active FROM products WHERE id = 1')['is_active']);

        // Invisible dans la liste courante, retrouvable en le demandant.
        $this->assertCount(19, $this->get('/admin/products')->payload['data']['products']);
        $this->assertCount(20, $this->request('GET', '/admin/products', ['include_archived' => '1'])->payload['data']['products']);
    }

    public function test_une_modification_est_journalisee_avec_son_detail(): void
    {
        $this->post('/admin/products/1', $this->produit([
            'brand' => 'Nike', 'model' => 'Shox TL', 'colorway' => 'Noir / Racer Blue',
            'price_xof' => 91000,
        ]));

        $entree = Database::first("SELECT action, target, detail FROM admin_log WHERE action = 'product.update'");

        $this->assertNotNull($entree);
        $this->assertStringContainsString('price_xof', $entree['detail']);
        $this->assertStringContainsString('91000', $entree['detail']);
    }

    // ----------------------------------------------------------- stock

    public function test_le_stock_se_corrige_taille_par_taille(): void
    {
        $response = $this->post('/admin/products/1/stock', ['stock' => ['42' => 12, '43' => 0]]);

        $this->assertSame(200, $response->status);
        $this->assertSame(12, $this->stockOf('sneaker', 1, '42'));
        $this->assertSame(0, $this->stockOf('sneaker', 1, '43'));
    }

    public function test_une_taille_inconnue_est_refusee(): void
    {
        $response = $this->post('/admin/products/1/stock', ['stock' => ['99' => 3]]);

        $this->assertSame(422, $response->status);
    }

    public function test_une_quantite_negative_est_refusee(): void
    {
        $response = $this->post('/admin/products/1/stock', ['stock' => ['42' => -5]]);

        $this->assertSame(422, $response->status);
    }

    public function test_le_reapprovisionnement_est_journalise(): void
    {
        $avant = $this->stockOf('sneaker', 1, '42');
        $this->post('/admin/products/1/stock', ['stock' => ['42' => $avant + 7]]);

        $entree = Database::first("SELECT detail FROM admin_log WHERE action = 'product.stock'");

        $this->assertStringContainsString((string) ($avant + 7), $entree['detail']);
    }

    // --------------------------------------------------------- visuels

    /**
     * La garde qui compte à ce niveau : le fichier doit venir d'un vrai
     * téléversement. Sans elle, un chemin local glissé dans la requête ferait
     * lire n'importe quel fichier du serveur.
     *
     * La conversion elle-même — format, transparence, redimensionnement,
     * refus d'un exécutable déguisé — est vérifiée dans ImageStoreTest, où
     * elle se teste sans simuler HTTP.
     */
    public function test_un_chemin_local_deguise_en_televersement_est_refuse(): void
    {
        $source = sys_get_temp_dir() . '/rcc-admin-' . bin2hex(random_bytes(4)) . '.png';
        $image = imagecreatetruecolor(60, 40);
        imagepng($image, $source);
        imagedestroy($image);

        $_FILES['image'] = [
            'tmp_name' => $source,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($source),
            'name' => 'x.png',
        ];

        $response = $this->post('/admin/uploads');

        unset($_FILES['image']);
        @unlink($source);

        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('image', $response->payload['error']['fields']);
    }

    public function test_un_televersement_sans_fichier_est_refuse(): void
    {
        unset($_FILES['image']);

        $response = $this->post('/admin/uploads');

        $this->assertSame(422, $response->status);
    }
}
