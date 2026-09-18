<?php

namespace Rcc\Tests\Feature;

use Rcc\Database;
use Rcc\Tests\ApiTestCase;

class AdminInboxTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Un message et deux inscrits, déposés par le public.
        $this->post('/contact', [
            'name' => 'Lemaye Kpatinde',
            'email' => 'client@exemple.com',
            'phone' => '0197000011',
            'subject' => 'Disponibilité taille 44',
            'message' => 'Bonjour, avez-vous la Air Max 95 en 44 ? Merci.',
        ]);

        $this->post('/newsletter', ['email' => 'un@exemple.com']);
        $this->post('/newsletter', ['email' => 'deux@exemple.com']);

        $this->loginAsAdmin();
    }

    // --------------------------------------------------------- messages

    public function test_les_messages_arrivent_dans_ladministration(): void
    {
        $response = $this->get('/admin/messages');

        $this->assertSame(200, $response->status);
        $this->assertCount(1, $response->payload['data']['messages']);

        $message = $response->payload['data']['messages'][0];
        $this->assertSame('Disponibilité taille 44', $message['subject']);
        $this->assertSame('new', $message['status']);
        $this->assertSame('client@exemple.com', $message['email']);
        $this->assertSame(1, $response->payload['data']['counts']['new']);
    }

    public function test_un_message_se_marque_comme_traite(): void
    {
        $id = $this->get('/admin/messages')->payload['data']['messages'][0]['id'];

        $response = $this->post("/admin/messages/{$id}/status", ['status' => 'handled']);

        $this->assertSame(200, $response->status);
        $this->assertSame(0, $response->payload['data']['counts']['new']);
        $this->assertNotNull(Database::first('SELECT handled_at FROM contact_messages')['handled_at']);
    }

    /** Les non lus remontent en premier : c'est la seule pile qui demande une action. */
    public function test_les_non_lus_remontent_en_premier(): void
    {
        $premier = $this->get('/admin/messages')->payload['data']['messages'][0]['id'];
        $this->post("/admin/messages/{$premier}/status", ['status' => 'handled']);

        $this->forgetCookies();
        $this->post('/contact', [
            'name' => 'Autre', 'email' => 'autre@exemple.com',
            'subject' => 'Question récente', 'message' => 'Un message tout neuf à traiter.',
        ]);
        $this->loginAsAdmin();

        $messages = $this->get('/admin/messages')->payload['data']['messages'];

        $this->assertSame('Question récente', $messages[0]['subject']);
    }

    public function test_un_statut_de_message_inconnu_est_refuse(): void
    {
        $id = $this->get('/admin/messages')->payload['data']['messages'][0]['id'];

        $this->assertSame(422, $this->post("/admin/messages/{$id}/status", ['status' => 'urgent'])->status);
    }

    public function test_le_filtre_par_statut_fonctionne(): void
    {
        $this->assertCount(1, $this->request('GET', '/admin/messages', ['status' => 'new'])->payload['data']['messages']);
        $this->assertCount(0, $this->request('GET', '/admin/messages', ['status' => 'handled'])->payload['data']['messages']);
    }

    // ------------------------------------------------------- newsletter

    public function test_les_inscrits_sont_listes_avec_leur_synchronisation(): void
    {
        $response = $this->get('/admin/newsletter');

        $this->assertSame(200, $response->status);
        $this->assertCount(2, $response->payload['data']['subscribers']);
        $this->assertSame(2, $response->payload['data']['counts']['subscribed']);
        $this->assertTrue($response->payload['data']['subscribers'][0]['synced']);
    }

    public function test_la_recherche_trouve_un_inscrit(): void
    {
        $trouve = $this->request('GET', '/admin/newsletter', ['search' => 'deux@']);

        $this->assertCount(1, $trouve->payload['data']['subscribers']);
    }

    /**
     * Désinscrire doit aussi retirer de la liste du prestataire, sinon la
     * personne continue de recevoir les campagnes — et la désinscription ne
     * vaut rien.
     */
    public function test_une_desinscription_retire_aussi_de_la_liste_brevo(): void
    {
        $id = $this->get('/admin/newsletter')->payload['data']['subscribers'][0]['id'];
        $email = $this->get('/admin/newsletter')->payload['data']['subscribers'][0]['email'];

        $response = $this->post("/admin/newsletter/{$id}/unsubscribe");

        $this->assertSame(200, $response->status);
        $this->assertContains($email, $this->contacts->removed);
        $this->assertSame('unsubscribed', Database::first('SELECT status FROM newsletter_subscribers WHERE id = ?', [$id])['status']);
    }

    /**
     * Les inscrits que le prestataire n'a jamais reçus — ceux dont l'ajout a
     * échoué pendant une panne — doivent pouvoir être rejoués.
     */
    public function test_la_synchronisation_se_rejoue(): void
    {
        Database::run('UPDATE newsletter_subscribers SET synced_at = NULL');

        $this->assertSame(2, $this->get('/admin/newsletter')->payload['data']['counts']['unsynced']);

        $this->contacts->added = [];
        $response = $this->post('/admin/newsletter/sync');

        $this->assertSame(2, $response->payload['data']['synced']);
        $this->assertCount(2, $this->contacts->added);
        $this->assertSame(0, $this->get('/admin/newsletter')->payload['data']['counts']['unsynced']);
    }

    public function test_une_synchronisation_en_panne_ne_perd_rien(): void
    {
        Database::run('UPDATE newsletter_subscribers SET synced_at = NULL');
        $this->contacts->fail();

        $response = $this->post('/admin/newsletter/sync');

        $this->assertSame(0, $response->payload['data']['synced']);
        $this->assertSame(2, $response->payload['data']['remaining']);
        $this->assertSame(2, (int) Database::first(
            "SELECT COUNT(*) c FROM newsletter_subscribers WHERE status = 'subscribed'"
        )['c'], 'les inscrits restent, prêts à être rejoués');
    }

    public function test_un_inscrit_inconnu_repond_404(): void
    {
        $this->assertSame(404, $this->post('/admin/newsletter/9999/unsubscribe')->status);
    }
}
