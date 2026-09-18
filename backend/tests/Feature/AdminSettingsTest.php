<?php

namespace Rcc\Tests\Feature;

use Rcc\Database;
use Rcc\Settings;
use Rcc\Tests\ApiTestCase;

/**
 * Réglages, vitrine d'accueil, zones de livraison, messagerie.
 *
 * Tout ce qui était écrit en dur et réclamait un développeur pour changer.
 */
class AdminSettingsTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // La table des réglages est vidée entre les tests — chacun doit partir
        // d'un état connu. On repose donc la vitrine que le semoir installe en
        // production, plutôt que de dépendre de ce qu'un test précédent a
        // laissé.
        $slugs = array_column(
            Database::run('SELECT slug FROM products WHERE is_active = 1 ORDER BY id LIMIT 4')->fetchAll(),
            'slug'
        );

        Settings::set('featured_slugs', json_encode($slugs, JSON_UNESCAPED_SLASHES));

        $this->loginAsAdmin();
    }

    // -------------------------------------------------------- réglages

    public function test_les_coordonnees_de_la_boutique_se_modifient(): void
    {
        $response = $this->post('/admin/settings', [
            'shop_phone' => '+229 01 55 44 33 22',
            'shop_email' => 'boutique@rccsneakers.bj',
            'shop_city' => 'Cotonou, Bénin',
        ]);

        $this->assertSame(200, $response->status);

        Settings::forget();
        $this->assertSame('+229 01 55 44 33 22', Settings::get('shop_phone'));
        $this->assertSame('boutique@rccsneakers.bj', Settings::get('shop_email'));
    }

    public function test_une_adresse_email_invalide_est_refusee(): void
    {
        $response = $this->post('/admin/settings', ['shop_email' => 'pas-une-adresse']);

        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('shop_email', $response->payload['error']['fields']);
    }

    public function test_un_lien_social_doit_etre_une_url(): void
    {
        $this->assertSame(422, $this->post('/admin/settings', ['social_instagram' => 'rccsneakers'])->status);
        $this->assertSame(200, $this->post('/admin/settings', ['social_instagram' => 'https://instagram.com/rccsneakers'])->status);
    }

    /** Tout le monde n'a pas de page Facebook : un champ facultatif doit pouvoir se vider. */
    public function test_un_lien_facultatif_peut_etre_vide(): void
    {
        $this->post('/admin/settings', ['social_facebook' => 'https://facebook.com/rcc']);
        $response = $this->post('/admin/settings', ['social_facebook' => '']);

        $this->assertSame(200, $response->status);
        Settings::forget();
        $this->assertSame('', Settings::get('social_facebook'));
    }

    /** On modifie ce qu'on envoie, pas tout le formulaire. */
    public function test_un_reglage_absent_de_la_requete_nest_pas_vide(): void
    {
        $this->post('/admin/settings', ['shop_phone' => '+229 01 11 11 11 11']);
        $this->post('/admin/settings', ['shop_city' => 'Porto-Novo']);

        Settings::forget();
        $this->assertSame('+229 01 11 11 11 11', Settings::get('shop_phone'));
        $this->assertSame('Porto-Novo', Settings::get('shop_city'));
    }

    /** La liste est fermée : une clé inventée ne doit pas entrer en base. */
    public function test_une_cle_inconnue_est_ignoree(): void
    {
        $this->post('/admin/settings', ['cle_inventee' => 'valeur']);

        $this->assertSame(0, (int) Database::first(
            "SELECT COUNT(*) c FROM settings WHERE name = 'cle_inventee'"
        )['c']);
    }

    // --------------------------------------------------------- vitrine

    public function test_la_vitrine_renvoie_les_paires_avec_leur_fiche(): void
    {
        $response = $this->get('/admin/settings');

        $this->assertSame(200, $response->status);
        $this->assertCount(4, $response->payload['data']['featured']);

        $premiere = $response->payload['data']['featured'][0];
        $this->assertTrue($premiere['found']);
        $this->assertNotNull($premiere['title']);
    }

    public function test_la_vitrine_se_change(): void
    {
        $slugs = array_column(
            Database::run('SELECT slug FROM products WHERE is_active = 1 ORDER BY id LIMIT 4 OFFSET 5')->fetchAll(),
            'slug'
        );

        $response = $this->post('/admin/featured', ['slugs' => $slugs]);

        $this->assertSame(200, $response->status);
        Settings::forget();
        $this->assertSame($slugs, Settings::list('featured_slugs'));
    }

    public function test_il_faut_exactement_quatre_paires(): void
    {
        $slugs = array_column(
            Database::run('SELECT slug FROM products ORDER BY id LIMIT 3')->fetchAll(),
            'slug'
        );

        $this->assertSame(422, $this->post('/admin/featured', ['slugs' => $slugs])->status);
    }

    public function test_les_doublons_sont_refuses(): void
    {
        $slug = Database::first('SELECT slug FROM products WHERE id = 1')['slug'];

        $response = $this->post('/admin/featured', ['slugs' => [$slug, $slug, $slug, $slug]]);

        $this->assertSame(422, $response->status);
    }

    /**
     * Une vitrine qui pointe vers un article retiré afficherait un vide sur la
     * page d'accueil, là où tout le monde regarde en premier.
     */
    public function test_un_article_retire_ne_peut_pas_etre_mis_en_avant(): void
    {
        Database::run('UPDATE products SET is_active = 0 WHERE id = 7');

        $slugs = array_column(
            Database::run('SELECT slug FROM products WHERE id IN (5,6,7,8) ORDER BY id')->fetchAll(),
            'slug'
        );

        $response = $this->post('/admin/featured', ['slugs' => $slugs]);

        Database::run('UPDATE products SET is_active = 1');

        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('slugs', $response->payload['error']['fields']);
    }

    public function test_un_slug_inexistant_est_refuse(): void
    {
        $response = $this->post('/admin/featured', [
            'slugs' => ['aucun-produit', 'ni-celui-la', 'ni-encore', 'ni-enfin'],
        ]);

        $this->assertSame(422, $response->status);
    }

    // ------------------------------------------------------- livraison

    public function test_un_tarif_de_livraison_se_corrige(): void
    {
        $response = $this->post('/admin/delivery-zones/cotonou', [
            'label' => 'Cotonou centre',
            'delay_label' => 'sous 12 h',
            'fee_xof' => 1500,
        ]);

        $this->assertSame(200, $response->status);

        $zone = Database::first("SELECT * FROM delivery_zones WHERE id = 'cotonou'");
        $this->assertSame('Cotonou centre', $zone['label']);
        $this->assertSame(1500, (int) $zone['fee_xof']);
    }

    public function test_un_tarif_absurde_est_refuse(): void
    {
        $this->assertSame(422, $this->post('/admin/delivery-zones/cotonou', ['fee_xof' => -500])->status);
        $this->assertSame(422, $this->post('/admin/delivery-zones/cotonou', ['label' => 'x'])->status);
    }

    public function test_une_zone_inconnue_repond_404(): void
    {
        $this->assertSame(404, $this->post('/admin/delivery-zones/mars', ['fee_xof' => 100])->status);
    }

    /**
     * Le tarif corrigé doit s'appliquer aux commandes suivantes : c'est tout
     * l'intérêt de l'avoir sorti du code.
     */
    public function test_le_nouveau_tarif_sapplique_a_la_commande_suivante(): void
    {
        $this->post('/admin/delivery-zones/cotonou', ['fee_xof' => 2000]);

        $this->forgetCookies();
        $this->loginAsCustomer();
        $this->setStock('sneaker', 1, '42', 5);

        $this->post('/orders', [
            'name' => 'Client Ordinaire', 'email' => 'client@exemple.com', 'phone' => '0197000011',
            'zone' => 'cotonou', 'address' => 'Gbetagbo', 'payment_method' => 'cash',
            'items' => [['item_type' => 'sneaker', 'item_id' => 1, 'size' => '42', 'qty' => 1]],
        ]);

        $this->assertSame(2000, (int) Database::first('SELECT delivery_fee_xof FROM orders')['delivery_fee_xof']);
    }

    // -------------------------------------------------------- vue d'ensemble

    public function test_la_vue_densemble_repond(): void
    {
        $response = $this->get('/admin/overview');

        $this->assertSame(200, $response->status);
        $this->assertArrayHasKey('orders', $response->payload['data']);
        $this->assertArrayHasKey('catalogue', $response->payload['data']);
        $this->assertArrayHasKey('customers', $response->payload['data']);
        $this->assertArrayHasKey('inbox', $response->payload['data']);
        $this->assertSame(20, $response->payload['data']['catalogue']['products']);
    }

    public function test_le_journal_liste_les_actions(): void
    {
        $this->post('/admin/settings', ['shop_city' => 'Porto-Novo']);

        $entrees = $this->get('/admin/log')->payload['data']['entries'];

        $this->assertNotEmpty($entrees);
        $this->assertSame('settings.update', $entrees[0]['action']);
    }
}
