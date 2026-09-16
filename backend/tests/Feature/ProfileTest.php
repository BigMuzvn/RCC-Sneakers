<?php

namespace Rcc\Tests\Feature;

use Rcc\Database;
use Rcc\Tests\ApiTestCase;

class ProfileTest extends ApiTestCase
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

        $this->mailer->sent = [];
    }

    // ------------------------------------------------------ informations

    public function test_modifier_son_profil_exige_une_session(): void
    {
        $this->forgetCookies();

        $this->assertSame(401, $this->post('/auth/profile', ['name' => 'Autre'])->status);
    }

    public function test_on_peut_changer_son_nom_et_son_telephone(): void
    {
        $response = $this->post('/auth/profile', [
            'name' => 'Lemaye K.',
            'email' => 'lemaye@exemple.com',
            'phone' => '+229 01 97 55 44 33',
        ]);

        $this->assertSame(200, $response->status);
        $this->assertSame('Lemaye K.', $response->payload['data']['customer']['name']);
        $this->assertSame('2290197554433', Database::first('SELECT phone FROM customers')['phone']);
    }

    public function test_un_numero_deja_pris_par_un_autre_est_refuse(): void
    {
        $this->forgetCookies();
        $this->post('/auth/register', [
            'name' => 'Autre', 'email' => 'autre@exemple.com',
            'phone' => '0197000001', 'password' => 'motdepasse', 'terms' => true,
        ]);

        $response = $this->post('/auth/profile', [
            'name' => 'Autre', 'email' => 'autre@exemple.com', 'phone' => '0197000000',
        ]);

        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('phone', $response->payload['error']['fields']);
    }

    public function test_garder_son_propre_numero_nest_pas_un_doublon(): void
    {
        $response = $this->post('/auth/profile', [
            'name' => 'Lemaye K.',
            'email' => 'lemaye@exemple.com',
            'phone' => '0197000000',
        ]);

        $this->assertSame(200, $response->status);
    }

    /**
     * Changer d'adresse remet le compte en « non vérifié » et déclenche un
     * nouvel envoi : sans cela, quelqu'un pourrait inscrire une adresse qu'il
     * contrôle, la faire vérifier, puis la remplacer par celle d'un tiers tout
     * en gardant la pastille « vérifié ».
     */
    public function test_changer_dadresse_annule_la_verification_et_renvoie_un_lien(): void
    {
        Database::run('UPDATE customers SET email_verified_at = ?', [Database::now()]);

        $response = $this->post('/auth/profile', [
            'name' => 'Lemaye Kpatinde',
            'email' => 'nouvelle@exemple.com',
            'phone' => '0197000000',
        ]);

        $this->assertSame(200, $response->status);
        $this->assertFalse($response->payload['data']['customer']['email_verified']);
        $this->assertSame(1, $this->mailer->count());
        $this->assertSame('nouvelle@exemple.com', $this->mailer->last()['to']);
    }

    public function test_garder_son_adresse_ne_declenche_aucun_envoi(): void
    {
        Database::run('UPDATE customers SET email_verified_at = ?', [Database::now()]);

        $response = $this->post('/auth/profile', [
            'name' => 'Lemaye K.',
            'email' => 'lemaye@exemple.com',
            'phone' => '0197000000',
        ]);

        $this->assertTrue($response->payload['data']['customer']['email_verified']);
        $this->assertSame(0, $this->mailer->count());
    }

    // --------------------------------------------------- mot de passe

    public function test_changer_de_mot_de_passe_exige_lancien(): void
    {
        $response = $this->post('/auth/password', [
            'current_password' => 'pas-le-bon',
            'password' => 'nouveau-mot-de-passe',
        ]);

        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('current_password', $response->payload['error']['fields']);
        $this->assertTrue(password_verify('motdepasse', Database::first('SELECT password_hash FROM customers')['password_hash']));
    }

    public function test_changer_de_mot_de_passe_avec_le_bon(): void
    {
        $response = $this->post('/auth/password', [
            'current_password' => 'motdepasse',
            'password' => 'nouveau-mot-de-passe',
        ]);

        $this->assertSame(200, $response->status);

        $this->forgetCookies();
        $this->assertSame(200, $this->post('/auth/login', [
            'identifier' => 'lemaye@exemple.com',
            'password' => 'nouveau-mot-de-passe',
        ])->status);
    }

    /**
     * Toutes les autres sessions tombent, mais celle qui vient de faire le
     * changement doit survivre : se faire déconnecter juste après avoir validé
     * son propre formulaire serait incompréhensible.
     */
    public function test_les_autres_sessions_tombent_mais_pas_la_sienne(): void
    {
        $sienne = $this->cookies;

        // Un second appareil se connecte.
        $this->forgetCookies();
        $this->post('/auth/login', ['identifier' => 'lemaye@exemple.com', 'password' => 'motdepasse']);
        $autreAppareil = $this->cookies;

        $this->cookies = $sienne;
        $this->post('/auth/password', [
            'current_password' => 'motdepasse',
            'password' => 'nouveau-mot-de-passe',
        ]);

        $this->assertSame(200, $this->get('/auth/me')->status, 'la session courante devait survivre');

        $this->cookies = $autreAppareil;
        $this->assertSame(401, $this->get('/auth/me')->status, "l'autre appareil devait être déconnecté");
    }

    public function test_un_nouveau_mot_de_passe_trop_court_est_refuse(): void
    {
        $response = $this->post('/auth/password', [
            'current_password' => 'motdepasse',
            'password' => 'court',
        ]);

        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('password', $response->payload['error']['fields']);
    }

    public function test_changer_de_mot_de_passe_exige_une_session(): void
    {
        $this->forgetCookies();

        $this->assertSame(401, $this->post('/auth/password', [
            'current_password' => 'motdepasse',
            'password' => 'nouveau-mot-de-passe',
        ])->status);
    }
}
