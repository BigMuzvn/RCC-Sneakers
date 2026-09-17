<?php

namespace Rcc\Tests\Feature;

use Rcc\Database;
use Rcc\Tests\ApiTestCase;

class NewsletterTest extends ApiTestCase
{
    public function test_une_inscription_est_enregistree(): void
    {
        $response = $this->post('/newsletter', ['email' => 'client@exemple.com']);

        $this->assertSame(201, $response->status);
        $this->assertSame('client@exemple.com', Database::first('SELECT email FROM newsletter_subscribers')['email']);
    }

    public function test_ladresse_est_normalisee(): void
    {
        $this->post('/newsletter', ['email' => '  Client@Exemple.COM ']);

        $this->assertSame('client@exemple.com', Database::first('SELECT email FROM newsletter_subscribers')['email']);
    }

    /**
     * Se réinscrire est le geste le plus banal du formulaire — on ne sait plus
     * si on l'a déjà fait. Ça ne doit produire ni erreur ni doublon.
     */
    public function test_sinscrire_deux_fois_ne_cree_pas_de_doublon(): void
    {
        $this->post('/newsletter', ['email' => 'client@exemple.com']);
        $second = $this->post('/newsletter', ['email' => 'client@exemple.com']);

        $this->assertSame(201, $second->status);
        $this->assertSame(1, (int) Database::first('SELECT COUNT(*) c FROM newsletter_subscribers')['c']);
    }

    public function test_une_reinscription_apres_desinscription_reactive(): void
    {
        $this->post('/newsletter', ['email' => 'client@exemple.com']);
        Database::run("UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = ?", [Database::now()]);

        $this->post('/newsletter', ['email' => 'client@exemple.com']);

        $row = Database::first('SELECT status, unsubscribed_at FROM newsletter_subscribers');
        $this->assertSame('subscribed', $row['status']);
        $this->assertNull($row['unsubscribed_at']);
    }

    public function test_une_adresse_malformee_est_refusee(): void
    {
        $response = $this->post('/newsletter', ['email' => 'pas-une-adresse']);

        $this->assertSame(422, $response->status);
        $this->assertSame(0, (int) Database::first('SELECT COUNT(*) c FROM newsletter_subscribers')['c']);
    }

    /**
     * L'adresse part vers une liste de contacts Brevo, où se composeront les
     * campagnes. La copie locale reste la référence : elle permet de savoir qui
     * est inscrit sans dépendre d'un tiers, et de rejouer une synchronisation
     * qui a échoué.
     */
    public function test_ladresse_est_poussee_vers_brevo(): void
    {
        $this->post('/newsletter', ['email' => 'client@exemple.com']);

        $this->assertSame(['client@exemple.com'], $this->contacts->added);
        $this->assertNotNull(Database::first('SELECT synced_at FROM newsletter_subscribers')['synced_at']);
    }

    /**
     * Même règle que l'inscription au site : une panne chez le prestataire ne
     * doit pas faire perdre un inscrit. On garde la ligne, sans date de
     * synchronisation, pour pouvoir la rejouer.
     */
    public function test_une_panne_de_brevo_ne_perd_pas_linscrit(): void
    {
        $this->contacts->fail();

        $response = $this->post('/newsletter', ['email' => 'client@exemple.com']);

        $this->assertSame(201, $response->status);
        $this->assertSame(1, (int) Database::first('SELECT COUNT(*) c FROM newsletter_subscribers')['c']);
        $this->assertNull(Database::first('SELECT synced_at FROM newsletter_subscribers')['synced_at']);
    }

    /**
     * Le formulaire est public et sans compte : sans plafond, il suffit d'un
     * script pour remplir la table de milliers d'adresses inventées.
     */
    public function test_les_inscriptions_en_rafale_sont_plafonnees(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/newsletter', ['email' => "client{$i}@exemple.com"]);
        }

        $response = $this->post('/newsletter', ['email' => 'encore@exemple.com']);

        $this->assertSame(429, $response->status);
        $this->assertSame(10, (int) Database::first('SELECT COUNT(*) c FROM newsletter_subscribers')['c']);
    }
}
