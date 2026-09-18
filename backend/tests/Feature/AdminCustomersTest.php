<?php

namespace Rcc\Tests\Feature;

use Rcc\Database;
use Rcc\Tests\ApiTestCase;

class AdminCustomersTest extends ApiTestCase
{
    private int $clientId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientId = $this->loginAsCustomer();
        $this->forgetCookies();
        $this->loginAsAdmin();
    }

    // ------------------------------------------------------------ liste

    public function test_la_liste_montre_les_clients_et_leur_activite(): void
    {
        $response = $this->get('/admin/customers');

        $this->assertSame(200, $response->status);
        $this->assertCount(2, $response->payload['data']['customers']);

        $client = array_values(array_filter(
            $response->payload['data']['customers'],
            static fn ($c) => $c['email'] === 'client@exemple.com'
        ))[0];

        $this->assertSame(0, $client['orders_count']);
        $this->assertSame(0, $client['spent_xof']);
        $this->assertFalse($client['is_admin']);
    }

    public function test_la_recherche_trouve_par_nom_email_et_telephone(): void
    {
        foreach (['Ordinaire', 'client@', '0197000011'] as $terme) {
            $trouve = $this->request('GET', '/admin/customers', ['search' => $terme]);

            $this->assertCount(1, $trouve->payload['data']['customers'], "« {$terme} » aurait dû trouver le client");
        }
    }

    public function test_la_fiche_montre_les_commandes_du_client(): void
    {
        $response = $this->get("/admin/customers/{$this->clientId}");

        $this->assertSame(200, $response->status);
        $this->assertSame('client@exemple.com', $response->payload['data']['customer']['email']);
        $this->assertSame([], $response->payload['data']['customer']['orders']);
    }

    // ------------------------------------------------------- suspension

    public function test_un_compte_se_suspend(): void
    {
        $response = $this->post("/admin/customers/{$this->clientId}/status", ['status' => 'suspended']);

        $this->assertSame(200, $response->status);
        $this->assertSame('suspended', Database::first('SELECT status FROM customers WHERE id = ?', [$this->clientId])['status']);
    }

    /** Suspendre doit fermer les sessions ouvertes, pas seulement bloquer la prochaine connexion. */
    public function test_la_suspension_ferme_les_sessions_ouvertes(): void
    {
        $this->assertGreaterThan(0, (int) Database::first(
            'SELECT COUNT(*) c FROM auth_tokens WHERE customer_id = ?',
            [$this->clientId]
        )['c']);

        $this->post("/admin/customers/{$this->clientId}/status", ['status' => 'suspended']);

        $this->assertSame(0, (int) Database::first(
            'SELECT COUNT(*) c FROM auth_tokens WHERE customer_id = ?',
            [$this->clientId]
        )['c']);
    }

    public function test_un_compte_se_reactive(): void
    {
        $this->post("/admin/customers/{$this->clientId}/status", ['status' => 'suspended']);
        $this->post("/admin/customers/{$this->clientId}/status", ['status' => 'active']);

        $this->assertSame('active', Database::first('SELECT status FROM customers WHERE id = ?', [$this->clientId])['status']);
    }

    /** Se suspendre soi-même reviendrait à se verrouiller dehors. */
    public function test_un_administrateur_ne_peut_pas_sappliquer_ces_actions(): void
    {
        $moi = (int) Database::first("SELECT id FROM customers WHERE email = 'admin@exemple.com'")['id'];

        foreach (['status' => ['status' => 'suspended'], 'anonymise' => [], 'role' => ['is_admin' => false]] as $action => $corps) {
            $response = $this->post("/admin/customers/{$moi}/{$action}", $corps);

            $this->assertSame(422, $response->status, "« {$action} » sur soi-même aurait dû être refusé");
        }
    }

    // ----------------------------------------------------- anonymisation

    /**
     * La réponse au droit à l'effacement que promettent les pages légales :
     * l'identité disparaît, la comptabilité reste.
     */
    public function test_lanonymisation_efface_lidentite_et_garde_la_commande(): void
    {
        // Le client passe une commande, pour vérifier qu'elle survit.
        $this->forgetCookies();
        $this->post('/auth/login', ['identifier' => 'client@exemple.com', 'password' => 'motdepasse']);
        $this->setStock('sneaker', 1, '42', 5);

        $reference = $this->post('/orders', [
            'name' => 'Client Ordinaire', 'email' => 'client@exemple.com', 'phone' => '0197000011',
            'zone' => 'cotonou', 'address' => 'Gbetagbo, lot 42', 'payment_method' => 'cash',
            'items' => [['item_type' => 'sneaker', 'item_id' => 1, 'size' => '42', 'qty' => 1]],
        ])->payload['data']['order']['reference'];

        $this->post('/favorites/toggle', ['item_type' => 'sneaker', 'item_id' => 3]);

        $this->forgetCookies();
        $this->loginAsAdmin();

        $response = $this->post("/admin/customers/{$this->clientId}/anonymise");
        $this->assertSame(200, $response->status);

        $client = Database::first('SELECT * FROM customers WHERE id = ?', [$this->clientId]);

        $this->assertSame('Client anonymisé', $client['name']);
        $this->assertStringNotContainsString('client@exemple.com', $client['email']);
        $this->assertSame('', $client['phone_display']);
        $this->assertSame('anonymised', $client['status']);
        $this->assertNotNull($client['anonymised_at']);

        // La commande existe toujours — c'est une pièce comptable — mais elle
        // ne porte plus d'identité.
        $commande = Database::first('SELECT * FROM orders WHERE reference = ?', [$reference]);

        $this->assertNotNull($commande);
        $this->assertSame('Client anonymisé', $commande['contact_name']);
        $this->assertSame('', $commande['contact_phone']);
        $this->assertSame('Adresse effacée', $commande['delivery_address']);
        $this->assertSame(1, (int) Database::first('SELECT COUNT(*) c FROM order_items')['c'], 'les lignes restent');

        $this->assertSame(0, (int) Database::first('SELECT COUNT(*) c FROM favorites WHERE customer_id = ?', [$this->clientId])['c']);
        $this->assertSame(0, (int) Database::first('SELECT COUNT(*) c FROM auth_tokens WHERE customer_id = ?', [$this->clientId])['c']);
    }

    public function test_un_compte_anonymise_ne_peut_plus_se_connecter(): void
    {
        $this->post("/admin/customers/{$this->clientId}/anonymise");

        $this->forgetCookies();
        $response = $this->post('/auth/login', [
            'identifier' => 'client@exemple.com',
            'password' => 'motdepasse',
        ]);

        $this->assertSame(401, $response->status, "l'adresse n'existe plus, donc identifiants incorrects");
    }

    public function test_lanonymisation_ne_se_repete_pas(): void
    {
        $this->post("/admin/customers/{$this->clientId}/anonymise");

        $this->assertSame(422, $this->post("/admin/customers/{$this->clientId}/anonymise")->status);
    }

    public function test_lanonymisation_retire_de_la_lettre_dinformation(): void
    {
        $this->forgetCookies();
        $this->post('/newsletter', ['email' => 'client@exemple.com']);
        $this->loginAsAdmin('admin2@exemple.com', '0197000098');

        $this->post("/admin/customers/{$this->clientId}/anonymise");

        $this->assertSame(0, (int) Database::first(
            "SELECT COUNT(*) c FROM newsletter_subscribers WHERE email = 'client@exemple.com'"
        )['c']);
    }

    // --------------------------------------------------------- rôle

    public function test_un_client_se_promeut_administrateur(): void
    {
        $response = $this->post("/admin/customers/{$this->clientId}/role", ['is_admin' => true]);

        $this->assertSame(200, $response->status);
        $this->assertSame(1, (int) Database::first('SELECT is_admin FROM customers WHERE id = ?', [$this->clientId])['is_admin']);
    }

    public function test_un_administrateur_se_retrograde(): void
    {
        $this->post("/admin/customers/{$this->clientId}/role", ['is_admin' => true]);
        $response = $this->post("/admin/customers/{$this->clientId}/role", ['is_admin' => false]);

        $this->assertSame(200, $response->status);
        $this->assertSame(0, (int) Database::first('SELECT is_admin FROM customers WHERE id = ?', [$this->clientId])['is_admin']);
    }

    public function test_un_compte_suspendu_ne_peut_pas_administrer(): void
    {
        $this->post("/admin/customers/{$this->clientId}/status", ['status' => 'suspended']);

        $response = $this->post("/admin/customers/{$this->clientId}/role", ['is_admin' => true]);

        $this->assertSame(422, $response->status);
    }

    public function test_les_actions_sur_un_client_sont_journalisees(): void
    {
        $this->post("/admin/customers/{$this->clientId}/status", ['status' => 'suspended']);
        $this->post("/admin/customers/{$this->clientId}/anonymise");

        $actions = array_column(
            Database::run('SELECT action FROM admin_log ORDER BY id')->fetchAll(),
            'action'
        );

        $this->assertContains('customer.status', $actions);
        $this->assertContains('customer.anonymise', $actions);
    }
}
