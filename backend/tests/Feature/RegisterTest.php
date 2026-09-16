<?php

namespace Rcc\Tests\Feature;

use Rcc\Database;
use Rcc\Tests\ApiTestCase;

class RegisterTest extends ApiTestCase
{
    /** @return array<string,mixed> */
    private function valid(array $override = []): array
    {
        return array_merge([
            'name' => 'Lemaye Kpatinde',
            'email' => 'lemaye@exemple.com',
            'phone' => '0197000000',
            'password' => 'motdepasse',
            'terms' => true,
        ], $override);
    }

    public function test_une_inscription_valide_cree_le_compte(): void
    {
        $response = $this->post('/auth/register', $this->valid());

        $this->assertSame(201, $response->status);
        $this->assertSame('lemaye@exemple.com', $response->payload['data']['customer']['email']);
        $this->assertSame(1, (int) Database::first('SELECT COUNT(*) c FROM customers')['c']);
    }

    public function test_le_client_est_connecte_dans_la_foulee(): void
    {
        $this->post('/auth/register', $this->valid());

        $me = $this->get('/auth/me');

        $this->assertSame(200, $me->status);
        $this->assertSame('lemaye@exemple.com', $me->payload['data']['customer']['email']);
    }

    public function test_le_mot_de_passe_nest_jamais_stocke_en_clair(): void
    {
        $this->post('/auth/register', $this->valid());

        $hash = Database::first('SELECT password_hash FROM customers')['password_hash'];

        $this->assertNotSame('motdepasse', $hash);
        $this->assertTrue(password_verify('motdepasse', $hash));
    }

    public function test_le_hachage_ne_sort_jamais_dans_la_reponse(): void
    {
        $response = $this->post('/auth/register', $this->valid());

        $this->assertStringNotContainsString('password', json_encode($response->payload['data']));
    }

    public function test_une_adresse_deja_prise_est_refusee(): void
    {
        $this->post('/auth/register', $this->valid());
        $this->forgetCookies();

        $response = $this->post('/auth/register', $this->valid(['phone' => '0197000001']));

        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('email', $response->payload['error']['fields']);
        $this->assertSame(1, (int) Database::first('SELECT COUNT(*) c FROM customers')['c']);
    }

    /**
     * Le test qui justifie toute la normalisation : le même numéro écrit
     * autrement doit être reconnu comme déjà pris. Sans cela le client ouvre
     * un second compte sans s'en apercevoir, et ses commandes se retrouvent
     * réparties entre deux dossiers.
     */
    public function test_un_numero_deja_pris_ecrit_autrement_est_refuse(): void
    {
        $this->post('/auth/register', $this->valid(['phone' => '0197000000']));
        $this->forgetCookies();

        $response = $this->post('/auth/register', $this->valid([
            'email' => 'autre@exemple.com',
            'phone' => '+229 01 97 00 00 00',
        ]));

        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('phone', $response->payload['error']['fields']);
        $this->assertSame(1, (int) Database::first('SELECT COUNT(*) c FROM customers')['c']);
    }

    public function test_le_numero_saisi_est_conserve_pour_laffichage(): void
    {
        $response = $this->post('/auth/register', $this->valid(['phone' => '+229 01 97 00 00 00']));

        $row = Database::first('SELECT phone, phone_display FROM customers');

        $this->assertSame('2290197000000', $row['phone']);
        $this->assertSame('+229 01 97 00 00 00', $row['phone_display']);
        $this->assertSame('+229 01 97 00 00 00', $response->payload['data']['customer']['phone']);
    }

    public function test_les_conditions_doivent_etre_acceptees(): void
    {
        $response = $this->post('/auth/register', $this->valid(['terms' => false]));

        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('terms', $response->payload['error']['fields']);
    }

    public function test_un_mot_de_passe_trop_court_est_refuse(): void
    {
        $response = $this->post('/auth/register', $this->valid(['password' => '1234']));

        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('password', $response->payload['error']['fields']);
    }

    public function test_une_adresse_malformee_est_refusee(): void
    {
        $response = $this->post('/auth/register', $this->valid(['email' => 'pas-une-adresse']));

        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('email', $response->payload['error']['fields']);
    }

    public function test_un_numero_invalide_est_refuse(): void
    {
        $response = $this->post('/auth/register', $this->valid(['phone' => '12']));

        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('phone', $response->payload['error']['fields']);
    }

    public function test_un_email_de_verification_est_envoye(): void
    {
        $this->post('/auth/register', $this->valid());

        $this->assertSame(1, $this->mailer->count());
        $this->assertSame('lemaye@exemple.com', $this->mailer->last()['to']);
        $this->assertNotNull($this->mailer->lastToken());
    }

    public function test_le_compte_nest_pas_verifie_a_la_creation(): void
    {
        $response = $this->post('/auth/register', $this->valid());

        $this->assertFalse($response->payload['data']['customer']['email_verified']);
    }

    /**
     * Le point décisif. Si l'inscription dépendait de la disponibilité de
     * Brevo, une panne chez un tiers fermerait la boutique aux nouveaux
     * clients. Le compte est créé, l'échec journalisé, le mail redemandable.
     */
    public function test_une_panne_de_brevo_nempeche_pas_de_creer_un_compte(): void
    {
        $this->mailer->fail();

        $response = $this->post('/auth/register', $this->valid());

        $this->assertSame(201, $response->status);
        $this->assertSame(1, (int) Database::first('SELECT COUNT(*) c FROM customers')['c']);
        $this->assertSame(200, $this->get('/auth/me')->status);
    }
}
