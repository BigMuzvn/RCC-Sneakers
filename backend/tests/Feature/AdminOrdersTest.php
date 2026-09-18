<?php

namespace Rcc\Tests\Feature;

use Rcc\Database;
use Rcc\Tests\ApiTestCase;

class AdminOrdersTest extends ApiTestCase
{
    private const PRODUIT = 1;
    private const TAILLE = '42';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setStock('sneaker', self::PRODUIT, self::TAILLE, 10);
    }

    /** Passe une commande en tant que client, puis rend la référence. */
    private function passerCommande(string $email = 'client@exemple.com', string $phone = '0197000011'): string
    {
        $this->forgetCookies();
        $this->loginAsCustomer($email, $phone);

        $reference = $this->post('/orders', [
            'name' => 'Client Ordinaire',
            'email' => $email,
            'phone' => $phone,
            'zone' => 'cotonou',
            'address' => 'Gbetagbo, lot 42',
            'payment_method' => 'cash',
            'items' => [['item_type' => 'sneaker', 'item_id' => self::PRODUIT, 'size' => self::TAILLE, 'qty' => 2]],
        ])->payload['data']['order']['reference'];

        $this->forgetCookies();
        $this->loginAsAdmin();

        return $reference;
    }

    // ------------------------------------------------------------ liste

    public function test_la_liste_montre_les_commandes(): void
    {
        $reference = $this->passerCommande();

        $response = $this->get('/admin/orders');

        $this->assertSame(200, $response->status);
        $this->assertCount(1, $response->payload['data']['orders']);
        $this->assertSame($reference, $response->payload['data']['orders'][0]['reference']);
        $this->assertSame(2, $response->payload['data']['orders'][0]['items_count']);
    }

    public function test_la_recherche_trouve_par_reference_et_par_nom(): void
    {
        $reference = $this->passerCommande();

        foreach ([$reference, 'Ordinaire', '0197000011'] as $terme) {
            $trouve = $this->request('GET', '/admin/orders', ['search' => $terme]);

            $this->assertCount(1, $trouve->payload['data']['orders'], "« {$terme} » aurait dû trouver la commande");
        }

        $this->assertCount(0, $this->request('GET', '/admin/orders', ['search' => 'introuvable'])->payload['data']['orders']);
    }

    public function test_le_filtre_par_statut_fonctionne(): void
    {
        $this->passerCommande();

        $this->assertCount(1, $this->request('GET', '/admin/orders', ['status' => 'pending'])->payload['data']['orders']);
        $this->assertCount(0, $this->request('GET', '/admin/orders', ['status' => 'delivered'])->payload['data']['orders']);
    }

    public function test_la_pagination_est_renvoyee(): void
    {
        $this->passerCommande();

        $pagination = $this->request('GET', '/admin/orders', ['per_page' => 5])->payload['data']['pagination'];

        $this->assertSame(1, $pagination['total']);
        $this->assertSame(5, $pagination['per_page']);
        $this->assertSame(1, $pagination['pages']);
    }

    public function test_le_detail_montre_les_lignes_et_les_suites_possibles(): void
    {
        $reference = $this->passerCommande();

        $order = $this->get("/admin/orders/{$reference}")->payload['data']['order'];

        $this->assertCount(1, $order['items']);
        $this->assertSame('Gbetagbo, lot 42', $order['delivery_address']);
        $this->assertSame(['confirmed', 'cancelled'], array_column($order['next_statuses'], 'id'));
    }

    public function test_une_reference_inconnue_repond_404(): void
    {
        $this->loginAsAdmin();

        $this->assertSame(404, $this->get('/admin/orders/RCC-000000-0000')->status);
    }

    // ----------------------------------------------------------- statut

    public function test_le_statut_avance(): void
    {
        $reference = $this->passerCommande();

        $response = $this->post("/admin/orders/{$reference}/status", ['status' => 'confirmed']);

        $this->assertSame(200, $response->status);
        $this->assertSame('Confirmée', $response->payload['data']['status_label']);
        $this->assertSame('confirmed', Database::first('SELECT status FROM orders')['status']);
    }

    /**
     * Une commande ne revient pas en arrière : « livrée » qui repasse « en
     * préparation » ne veut rien dire.
     */
    public function test_un_retour_en_arriere_est_refuse(): void
    {
        $reference = $this->passerCommande();

        $this->post("/admin/orders/{$reference}/status", ['status' => 'confirmed']);
        $response = $this->post("/admin/orders/{$reference}/status", ['status' => 'pending']);

        $this->assertSame(422, $response->status);
        $this->assertSame('confirmed', Database::first('SELECT status FROM orders')['status']);
    }

    public function test_on_ne_saute_pas_une_etape(): void
    {
        $reference = $this->passerCommande();

        $this->assertSame(422, $this->post("/admin/orders/{$reference}/status", ['status' => 'delivered'])->status);
    }

    /**
     * Sans cette restitution, chaque annulation retirerait définitivement des
     * paires de la vente.
     */
    public function test_une_annulation_rend_le_stock(): void
    {
        $reference = $this->passerCommande();
        $this->assertSame(8, $this->stockOf('sneaker', self::PRODUIT, self::TAILLE));

        $this->post("/admin/orders/{$reference}/status", ['status' => 'cancelled']);

        $this->assertSame(10, $this->stockOf('sneaker', self::PRODUIT, self::TAILLE));
    }

    public function test_une_commande_annulee_est_close(): void
    {
        $reference = $this->passerCommande();
        $this->post("/admin/orders/{$reference}/status", ['status' => 'cancelled']);

        $this->assertSame(422, $this->post("/admin/orders/{$reference}/status", ['status' => 'confirmed'])->status);
        $this->assertSame(10, $this->stockOf('sneaker', self::PRODUIT, self::TAILLE), 'le stock ne doit pas être rendu deux fois');
    }

    /** Le règlement se fait à la livraison : c'est elle qui le constate. */
    public function test_la_livraison_marque_le_reglement(): void
    {
        $reference = $this->passerCommande();

        foreach (['confirmed', 'shipped', 'delivered'] as $etape) {
            $this->post("/admin/orders/{$reference}/status", ['status' => $etape]);
        }

        $order = Database::first('SELECT status, payment_status FROM orders');

        $this->assertSame('delivered', $order['status']);
        $this->assertSame('paid', $order['payment_status']);
    }

    /** Un suivi que le client n'apprend pas ne lui sert à rien. */
    public function test_le_client_est_prevenu_du_changement(): void
    {
        $reference = $this->passerCommande();
        $this->mailer->sent = [];

        $this->post("/admin/orders/{$reference}/status", ['status' => 'confirmed']);

        $this->assertSame(1, $this->mailer->count());
        $this->assertSame('client@exemple.com', $this->mailer->last()['to']);
        $this->assertStringContainsString($reference, $this->mailer->last()['html']);
    }

    public function test_le_changement_est_journalise(): void
    {
        $reference = $this->passerCommande();

        $this->post("/admin/orders/{$reference}/status", ['status' => 'confirmed']);

        $entree = Database::first('SELECT admin_email, action, target, detail FROM admin_log ORDER BY id DESC');

        $this->assertSame('admin@exemple.com', $entree['admin_email']);
        $this->assertSame('order.status', $entree['action']);
        $this->assertSame($reference, $entree['target']);
        $this->assertStringContainsString('Confirmée', $entree['detail']);
    }
}
