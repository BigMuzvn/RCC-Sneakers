<?php

namespace Rcc\Tests\Feature;

use Rcc\Database;
use Rcc\Tests\ApiTestCase;

class FavoritesTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->post('/auth/register', [
            'name' => 'Lemaye Kpatinde',
            'email' => 'lemaye@exemple.com',
            'phone' => '0197000000',
            'password' => 'motdepasse',
            'terms' => true,
        ]);
    }

    public function test_les_favoris_exigent_une_session(): void
    {
        $this->forgetCookies();

        $this->assertSame(401, $this->get('/favorites')->status);
        $this->assertSame(401, $this->post('/favorites/toggle', ['item_type' => 'sneaker', 'item_id' => 3])->status);
    }

    public function test_un_compte_neuf_na_aucun_favori(): void
    {
        $response = $this->get('/favorites');

        $this->assertSame(200, $response->status);
        $this->assertSame([], $response->payload['data']['favorites']);
    }

    public function test_le_bouton_ajoute_puis_retire(): void
    {
        $ajout = $this->post('/favorites/toggle', ['item_type' => 'sneaker', 'item_id' => 3]);
        $this->assertTrue($ajout->payload['data']['favorited']);
        $this->assertCount(1, $this->get('/favorites')->payload['data']['favorites']);

        $retrait = $this->post('/favorites/toggle', ['item_type' => 'sneaker', 'item_id' => 3]);
        $this->assertFalse($retrait->payload['data']['favorited']);
        $this->assertSame([], $this->get('/favorites')->payload['data']['favorites']);
    }

    public function test_sneakers_et_maillots_ne_se_confondent_pas(): void
    {
        $this->post('/favorites/toggle', ['item_type' => 'sneaker', 'item_id' => 3]);
        $this->post('/favorites/toggle', ['item_type' => 'jersey', 'item_id' => 3]);

        $this->assertCount(2, $this->get('/favorites')->payload['data']['favorites']);
    }

    /**
     * Le double clic est le cas courant sur mobile. La contrainte d'unicité le
     * rend inoffensif, mais la réponse doit rester cohérente.
     */
    public function test_ajouter_deux_fois_ne_cree_quune_ligne(): void
    {
        $this->post('/favorites/toggle', ['item_type' => 'sneaker', 'item_id' => 3]);
        Database::run(
            'INSERT IGNORE INTO favorites (customer_id, item_type, item_id, created_at) VALUES (1, ?, ?, ?)',
            ['sneaker', 3, Database::now()]
        );

        $this->assertSame(1, (int) Database::first('SELECT COUNT(*) c FROM favorites')['c']);
    }

    public function test_un_type_inconnu_est_refuse(): void
    {
        $response = $this->post('/favorites/toggle', ['item_type' => 'voiture', 'item_id' => 3]);

        $this->assertSame(422, $response->status);
        $this->assertSame(0, (int) Database::first('SELECT COUNT(*) c FROM favorites')['c']);
    }

    public function test_un_identifiant_absent_est_refuse(): void
    {
        $this->assertSame(422, $this->post('/favorites/toggle', ['item_type' => 'sneaker'])->status);
    }

    /**
     * Les favoris d'un client ne doivent jamais apparaître chez un autre.
     */
    public function test_les_favoris_sont_cloisonnes_par_client(): void
    {
        $this->post('/favorites/toggle', ['item_type' => 'sneaker', 'item_id' => 3]);

        $this->forgetCookies();
        $this->post('/auth/register', [
            'name' => 'Autre Client',
            'email' => 'autre@exemple.com',
            'phone' => '0197000001',
            'password' => 'motdepasse',
            'terms' => true,
        ]);

        $this->assertSame([], $this->get('/favorites')->payload['data']['favorites']);
    }

    public function test_les_favoris_reviennent_du_plus_recent_au_plus_ancien(): void
    {
        foreach ([3, 7, 11] as $id) {
            $this->post('/favorites/toggle', ['item_type' => 'sneaker', 'item_id' => $id]);
        }

        // Les trois insertions tombent dans la même seconde ; on désambiguïse.
        Database::run('UPDATE favorites SET created_at = ? WHERE item_id = 3', [Database::now(-120)]);
        Database::run('UPDATE favorites SET created_at = ? WHERE item_id = 7', [Database::now(-60)]);

        $ids = array_column($this->get('/favorites')->payload['data']['favorites'], 'item_id');

        $this->assertSame([11, 7, 3], $ids);
    }

    public function test_supprimer_un_compte_emporte_ses_favoris(): void
    {
        $this->post('/favorites/toggle', ['item_type' => 'sneaker', 'item_id' => 3]);

        Database::run('DELETE FROM customers WHERE email = ?', ['lemaye@exemple.com']);

        $this->assertSame(0, (int) Database::first('SELECT COUNT(*) c FROM favorites')['c']);
    }
}
