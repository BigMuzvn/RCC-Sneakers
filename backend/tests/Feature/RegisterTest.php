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

    /**
     * L'inscription était le seul formulaire public sans limitation de débit.
     * Chaque compte créé envoie un e-mail de vérification : un script qui
     * boucle épuisait le forfait Brevo et coupait tous les envois de la
     * boutique, confirmations de commande comprises.
     */
    public function test_les_inscriptions_en_rafale_sont_arretees(): void
    {
        $refus = null;

        // Adresses et numéros tous différents : c'est le plafond par IP qui
        // doit arrêter, celui par identifiant ne verrait jamais rien.
        for ($i = 0; $i < 12; $i++) {
            $response = $this->post('/auth/register', $this->valid([
                'email' => "robot{$i}@exemple.com",
                'phone' => '01970001' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            ]));

            $this->forgetCookies();

            if ($response->status === 429) {
                $refus = $i;
                break;
            }
        }

        $this->assertNotNull($refus, 'une rafale d’inscriptions aurait dû être arrêtée');
        $this->assertSame(
            $refus,
            (int) Database::first('SELECT COUNT(*) c FROM customers')['c'],
            'aucun compte ne doit être créé après le refus'
        );
    }

    /** Le refus laisse un délai, pas une porte close sans explication. */
    public function test_le_refus_annonce_quand_reessayer(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $response = $this->post('/auth/register', $this->valid([
                'email' => "rafale{$i}@exemple.com",
                'phone' => '01970002' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            ]));

            $this->forgetCookies();

            if ($response->status === 429) {
                $this->assertSame('too_many_attempts', $response->payload['error']['code']);
                $this->assertMatchesRegularExpression('/\d+ minute/', $response->payload['error']['message']);

                return;
            }
        }

        $this->fail('la rafale n’a jamais été arrêtée');
    }

    /**
     * Une adresse déjà prise n'envoie aucun e-mail : la reprendre pour corriger
     * son numéro ne doit pas consommer le quota de quelqu'un d'honnête.
     */
    public function test_une_tentative_refusee_pour_doublon_ne_consomme_pas_le_quota(): void
    {
        $this->post('/auth/register', $this->valid());
        $this->forgetCookies();

        for ($i = 0; $i < 6; $i++) {
            $this->post('/auth/register', $this->valid(['phone' => '0197000999']));
            $this->forgetCookies();
        }

        // Après six refus pour doublon, une inscription neuve doit encore passer.
        $response = $this->post('/auth/register', $this->valid([
            'email' => 'honnete@exemple.com',
            'phone' => '0197000123',
        ]));

        $this->assertSame(201, $response->status, 'les refus pour doublon ne doivent pas compter');
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
