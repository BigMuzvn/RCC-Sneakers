<?php

namespace Rcc\Tests\Feature;

use Rcc\Database;
use Rcc\Tests\ApiTestCase;

class OrdersTest extends ApiTestCase
{
    /** Nike P-6000, taille 42 — le prix réel est lu en base par les tests. */
    private const PRODUIT = 1;
    private const TAILLE = '42';

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

        $this->mailer->sent = [];
        $this->setStock('sneaker', self::PRODUIT, self::TAILLE, 5);
    }

    /** @param array<string,mixed> $override */
    private function commande(array $override = []): array
    {
        return array_merge([
            'name' => 'Lemaye Kpatinde',
            'email' => 'lemaye@exemple.com',
            'phone' => '0197000000',
            'zone' => 'cotonou',
            'address' => 'Gbetagbo, lot 42, Abomey-Calavi',
            'payment_method' => 'cash',
            'items' => [
                ['item_type' => 'sneaker', 'item_id' => self::PRODUIT, 'size' => self::TAILLE, 'qty' => 2],
            ],
        ], $override);
    }

    private function prixCatalogue(): int
    {
        return (int) Database::first('SELECT price_xof FROM products WHERE id = ?', [self::PRODUIT])['price_xof'];
    }

    private function fraisZone(string $zone = 'cotonou'): int
    {
        return (int) Database::first('SELECT fee_xof FROM delivery_zones WHERE id = ?', [$zone])['fee_xof'];
    }

    // ------------------------------------------------------------- accès

    public function test_commander_exige_un_compte(): void
    {
        $this->forgetCookies();

        $response = $this->post('/orders', $this->commande());

        $this->assertSame(401, $response->status);
        $this->assertSame(0, (int) Database::first('SELECT COUNT(*) c FROM orders')['c']);
    }

    // ------------------------------------------------------ cas nominal

    public function test_une_commande_valide_est_enregistree(): void
    {
        $response = $this->post('/orders', $this->commande());

        $this->assertSame(201, $response->status);
        $this->assertSame(1, (int) Database::first('SELECT COUNT(*) c FROM orders')['c']);
        $this->assertNotSame('', $response->payload['data']['order']['reference']);
    }

    /**
     * La référence est fabriquée par le serveur. Avant, elle était générée dans
     * le navigateur : deux clients simultanés pouvaient repartir avec la même.
     */
    public function test_la_reference_vient_du_serveur_et_est_unique(): void
    {
        $premiere = $this->post('/orders', $this->commande())->payload['data']['order']['reference'];
        $seconde = $this->post('/orders', $this->commande())->payload['data']['order']['reference'];

        $this->assertNotSame($premiere, $seconde);
        $this->assertSame(2, (int) Database::first('SELECT COUNT(DISTINCT reference) c FROM orders')['c']);
    }

    // --------------------------------------------- intégrité des montants

    /**
     * Le test qui justifie d'avoir porté le catalogue en base. Le navigateur
     * annonce un prix dérisoire ; le serveur ne le regarde même pas.
     */
    public function test_le_prix_envoye_par_le_navigateur_est_ignore(): void
    {
        $items = [[
            'item_type' => 'sneaker', 'item_id' => self::PRODUIT, 'size' => self::TAILLE, 'qty' => 2,
            'unit_price_xof' => 1,
            'title' => 'Paire offerte',
        ]];

        $this->post('/orders', $this->commande(['items' => $items]));

        $ligne = Database::first('SELECT unit_price_xof, title FROM order_items');

        $this->assertSame($this->prixCatalogue(), (int) $ligne['unit_price_xof']);
        $this->assertStringNotContainsString('offerte', $ligne['title']);
    }

    public function test_les_frais_de_livraison_viennent_de_la_base(): void
    {
        $this->post('/orders', $this->commande(['delivery_fee_xof' => 0, 'total_xof' => 1]));

        $order = Database::first('SELECT delivery_fee_xof, subtotal_xof, total_xof FROM orders');

        $this->assertSame($this->fraisZone(), (int) $order['delivery_fee_xof']);
        $this->assertSame($this->prixCatalogue() * 2, (int) $order['subtotal_xof']);
        $this->assertSame(
            $this->prixCatalogue() * 2 + $this->fraisZone(),
            (int) $order['total_xof']
        );
    }

    public function test_une_zone_inconnue_est_refusee(): void
    {
        $response = $this->post('/orders', $this->commande(['zone' => 'mars']));

        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('zone', $response->payload['error']['fields']);
        $this->assertSame(0, (int) Database::first('SELECT COUNT(*) c FROM orders')['c']);
    }

    // ------------------------------------------------------------ stock

    public function test_le_stock_est_decremente(): void
    {
        $this->post('/orders', $this->commande());

        $this->assertSame(3, $this->stockOf('sneaker', self::PRODUIT, self::TAILLE));
    }

    /**
     * Sans cette vérification côté serveur, deux clients peuvent acheter la
     * même dernière paire : le stock affiché dans le navigateur ne prouve rien.
     */
    public function test_un_stock_insuffisant_est_refuse(): void
    {
        $this->setStock('sneaker', self::PRODUIT, self::TAILLE, 1);

        $response = $this->post('/orders', $this->commande());

        $this->assertSame(422, $response->status);
        $this->assertSame(0, (int) Database::first('SELECT COUNT(*) c FROM orders')['c']);
        $this->assertSame(1, $this->stockOf('sneaker', self::PRODUIT, self::TAILLE));
    }

    /**
     * Si une seule ligne échoue, rien ne doit rester : ni commande partielle,
     * ni stock amputé sur les lignes qui étaient passées avant l'échec.
     */
    public function test_une_ligne_impossible_annule_toute_la_commande(): void
    {
        $this->setStock('sneaker', 2, '41', 0);

        $response = $this->post('/orders', $this->commande([
            'items' => [
                ['item_type' => 'sneaker', 'item_id' => self::PRODUIT, 'size' => self::TAILLE, 'qty' => 1],
                ['item_type' => 'sneaker', 'item_id' => 2, 'size' => '41', 'qty' => 1],
            ],
        ]));

        $this->assertSame(422, $response->status);
        $this->assertSame(0, (int) Database::first('SELECT COUNT(*) c FROM orders')['c']);
        $this->assertSame(0, (int) Database::first('SELECT COUNT(*) c FROM order_items')['c']);
        $this->assertSame(5, $this->stockOf('sneaker', self::PRODUIT, self::TAILLE), 'le stock de la 1re ligne devait être restauré');
    }

    // ---------------------------------------------------- panier invalide

    public function test_un_panier_vide_est_refuse(): void
    {
        $response = $this->post('/orders', $this->commande(['items' => []]));

        $this->assertSame(422, $response->status);
    }

    public function test_un_article_inconnu_est_refuse(): void
    {
        $response = $this->post('/orders', $this->commande([
            'items' => [['item_type' => 'sneaker', 'item_id' => 9999, 'size' => '42', 'qty' => 1]],
        ]));

        $this->assertSame(422, $response->status);
    }

    public function test_une_taille_inexistante_est_refusee(): void
    {
        $response = $this->post('/orders', $this->commande([
            'items' => [['item_type' => 'sneaker', 'item_id' => self::PRODUIT, 'size' => '99', 'qty' => 1]],
        ]));

        $this->assertSame(422, $response->status);
    }

    public function test_une_quantite_absurde_est_refusee(): void
    {
        foreach ([0, -3, 500] as $qty) {
            $response = $this->post('/orders', $this->commande([
                'items' => [['item_type' => 'sneaker', 'item_id' => self::PRODUIT, 'size' => self::TAILLE, 'qty' => $qty]],
            ]));

            $this->assertSame(422, $response->status, "qty = {$qty} aurait dû être refusé");
        }
    }

    public function test_les_maillots_se_commandent_aussi(): void
    {
        $this->setStock('jersey', 1, 'L', 3);

        $response = $this->post('/orders', $this->commande([
            'items' => [['item_type' => 'jersey', 'item_id' => 1, 'size' => 'L', 'qty' => 1]],
        ]));

        $this->assertSame(201, $response->status);
        $this->assertSame(2, $this->stockOf('jersey', 1, 'L'));
    }

    // ---------------------------------------------------------- paiement

    /**
     * Aucun agrégateur n'est encore branché. Accepter une commande « en ligne »
     * reviendrait à promettre un règlement impossible ; le refus est explicite
     * et côté serveur, car l'interface est contournable.
     */
    public function test_le_paiement_en_ligne_est_refuse_tant_quil_nexiste_pas(): void
    {
        $response = $this->post('/orders', $this->commande(['payment_method' => 'online']));

        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('payment_method', $response->payload['error']['fields']);
        $this->assertSame(0, (int) Database::first('SELECT COUNT(*) c FROM orders')['c']);
    }

    // ------------------------------------------------------ confirmation

    public function test_un_email_de_confirmation_est_envoye(): void
    {
        $response = $this->post('/orders', $this->commande());
        $reference = $response->payload['data']['order']['reference'];

        $this->assertSame(1, $this->mailer->count());
        $this->assertStringContainsString($reference, $this->mailer->last()['html']);
    }

    /**
     * Même raisonnement que l'inscription : une panne du service d'envoi ne
     * doit pas faire perdre une vente.
     */
    public function test_une_panne_denvoi_ne_perd_pas_la_commande(): void
    {
        $this->mailer->fail();

        $response = $this->post('/orders', $this->commande());

        $this->assertSame(201, $response->status);
        $this->assertSame(1, (int) Database::first('SELECT COUNT(*) c FROM orders')['c']);
    }

    // ------------------------------------------------------------ relecture

    public function test_on_relit_ses_commandes(): void
    {
        $this->post('/orders', $this->commande());
        $this->post('/orders', $this->commande());

        $response = $this->get('/orders');

        $this->assertSame(200, $response->status);
        $this->assertCount(2, $response->payload['data']['orders']);
        $this->assertArrayHasKey('items', $response->payload['data']['orders'][0]);
    }

    public function test_on_ne_voit_jamais_les_commandes_dun_autre(): void
    {
        $reference = $this->post('/orders', $this->commande())->payload['data']['order']['reference'];

        $this->forgetCookies();
        $this->post('/auth/register', [
            'name' => 'Autre', 'email' => 'autre@exemple.com',
            'phone' => '0197000001', 'password' => 'motdepasse', 'terms' => true,
        ]);

        $this->assertSame([], $this->get('/orders')->payload['data']['orders']);
        $this->assertSame(404, $this->get("/orders/{$reference}")->status);
    }

    public function test_on_relit_une_commande_par_sa_reference(): void
    {
        $reference = $this->post('/orders', $this->commande())->payload['data']['order']['reference'];

        $response = $this->get("/orders/{$reference}");

        $this->assertSame(200, $response->status);
        $this->assertSame($reference, $response->payload['data']['order']['reference']);
        $this->assertCount(1, $response->payload['data']['order']['items']);
    }

    /**
     * Les lignes sont figées : changer le prix au catalogue ne doit pas
     * réécrire une commande déjà passée, sinon les factures mentent.
     */
    public function test_un_changement_de_prix_ne_reecrit_pas_lhistorique(): void
    {
        $reference = $this->post('/orders', $this->commande())->payload['data']['order']['reference'];
        $prixInitial = $this->prixCatalogue();

        Database::run('UPDATE products SET price_xof = ? WHERE id = ?', [999999, self::PRODUIT]);

        $order = $this->get("/orders/{$reference}")->payload['data']['order'];

        $this->assertSame($prixInitial, $order['items'][0]['unit_price_xof']);
        $this->assertSame($prixInitial * 2, $order['subtotal_xof']);
    }

    public function test_les_coordonnees_de_livraison_sont_conservees(): void
    {
        $this->post('/orders', $this->commande(['address' => 'Gbetagbo, lot 42']));

        $order = Database::first('SELECT delivery_address, delivery_label, contact_phone FROM orders');

        $this->assertSame('Gbetagbo, lot 42', $order['delivery_address']);
        $this->assertSame('Cotonou', $order['delivery_label']);
        $this->assertNotSame('', $order['contact_phone']);
    }
}
