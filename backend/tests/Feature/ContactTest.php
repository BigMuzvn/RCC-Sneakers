<?php

namespace Rcc\Tests\Feature;

use Rcc\Database;
use Rcc\Settings;
use Rcc\Tests\ApiTestCase;

class ContactTest extends ApiTestCase
{
    /** @param array<string,mixed> $override */
    private function message(array $override = []): array
    {
        return array_merge([
            'name' => 'Lemaye Kpatinde',
            'email' => 'client@exemple.com',
            'phone' => '0197000000',
            'subject' => 'Disponibilité taille 44',
            'message' => 'Bonjour, avez-vous la Air Max 95 en 44 ? Merci.',
        ], $override);
    }

    public function test_un_message_est_enregistre(): void
    {
        $response = $this->post('/contact', $this->message());

        $this->assertSame(201, $response->status);

        $row = Database::first('SELECT name, email, subject, status FROM contact_messages');
        $this->assertSame('Lemaye Kpatinde', $row['name']);
        $this->assertSame('Disponibilité taille 44', $row['subject']);
        $this->assertSame('new', $row['status']);
    }

    /**
     * Un message que personne ne lit ne vaut pas mieux qu'un formulaire mort :
     * la boutique doit être prévenue.
     */
    public function test_la_boutique_est_prevenue(): void
    {
        Settings::set('shop_notification_email', 'boutique@exemple.com');

        $this->post('/contact', $this->message());

        $this->assertSame(1, $this->mailer->count());
        $this->assertSame('boutique@exemple.com', $this->mailer->last()['to']);
        $this->assertStringContainsString('Disponibilité taille 44', $this->mailer->last()['html']);
    }

    /**
     * Répondre au client depuis sa messagerie doit fonctionner d'un clic : le
     * message de notification porte donc son adresse en évidence.
     */
    public function test_la_notification_porte_ladresse_du_client(): void
    {
        Settings::set('shop_notification_email', 'boutique@exemple.com');

        $this->post('/contact', $this->message());

        $this->assertStringContainsString('client@exemple.com', $this->mailer->last()['html']);
    }

    public function test_une_panne_denvoi_ne_perd_pas_le_message(): void
    {
        $this->mailer->fail();

        $response = $this->post('/contact', $this->message());

        $this->assertSame(201, $response->status);
        $this->assertSame(1, (int) Database::first('SELECT COUNT(*) c FROM contact_messages')['c']);
    }

    public function test_les_champs_obligatoires_sont_verifies(): void
    {
        foreach (['name', 'email', 'subject', 'message'] as $champ) {
            $response = $this->post('/contact', $this->message([$champ => '']));

            $this->assertSame(422, $response->status, "« {$champ} » vide aurait dû être refusé");
            $this->assertArrayHasKey($champ, $response->payload['error']['fields']);
        }
    }

    /**
     * Le téléphone est facultatif : tout le monde n'a pas envie de le laisser
     * pour poser une question.
     */
    public function test_le_telephone_est_facultatif(): void
    {
        $response = $this->post('/contact', $this->message(['phone' => '']));

        $this->assertSame(201, $response->status);
        $this->assertNull(Database::first('SELECT phone FROM contact_messages')['phone']);
    }

    public function test_un_message_demesure_est_refuse(): void
    {
        $response = $this->post('/contact', $this->message(['message' => str_repeat('a', 6000)]));

        $this->assertSame(422, $response->status);
    }

    /**
     * Formulaire public et anonyme : sans plafond, c'est une boîte à spam
     * ouverte, et chaque message déclenche un envoi qui grignote le quota.
     */
    public function test_les_envois_en_rafale_sont_plafonnes(): void
    {
        // Trois messages par heure et par adresse : au-delà, c'est du bruit.
        for ($i = 0; $i < 3; $i++) {
            $this->post('/contact', $this->message(['subject' => "Question {$i}"]));
        }

        $response = $this->post('/contact', $this->message(['subject' => 'Une de trop']));

        $this->assertSame(429, $response->status);
        $this->assertSame(3, (int) Database::first('SELECT COUNT(*) c FROM contact_messages')['c']);
    }
}
