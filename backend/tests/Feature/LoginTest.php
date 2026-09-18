<?php

namespace Rcc\Tests\Feature;

use Rcc\Database;
use Rcc\Tests\ApiTestCase;

class LoginTest extends ApiTestCase
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

        $this->forgetCookies();
        $this->mailer->sent = [];
    }

    public function test_connexion_par_adresse_email(): void
    {
        $response = $this->post('/auth/login', [
            'identifier' => 'lemaye@exemple.com',
            'password' => 'motdepasse',
        ]);

        $this->assertSame(200, $response->status);
        $this->assertSame('lemaye@exemple.com', $response->payload['data']['customer']['email']);
    }

    public function test_la_casse_de_ladresse_est_sans_importance(): void
    {
        $response = $this->post('/auth/login', [
            'identifier' => 'Lemaye@Exemple.COM',
            'password' => 'motdepasse',
        ]);

        $this->assertSame(200, $response->status);
    }

    /**
     * À Cotonou beaucoup de clients ont un numéro et pas d'adresse mail
     * consultée : le téléphone doit ouvrir la session aussi bien que l'e-mail,
     * et sous n'importe quelle écriture.
     */
    public function test_connexion_par_telephone_quelle_quen_soit_lecriture(): void
    {
        foreach (['0197000000', '+229 01 97 00 00 00', '00229 01 97 00 00 00', '01-97-00-00-00'] as $ecriture) {
            $this->forgetCookies();

            $response = $this->post('/auth/login', [
                'identifier' => $ecriture,
                'password' => 'motdepasse',
            ]);

            $this->assertSame(200, $response->status, "Échec pour l'écriture « {$ecriture} »");
        }
    }

    public function test_un_mauvais_mot_de_passe_est_refuse(): void
    {
        $response = $this->post('/auth/login', [
            'identifier' => 'lemaye@exemple.com',
            'password' => 'pas-le-bon',
        ]);

        $this->assertSame(401, $response->status);
        $this->assertSame(401, $this->get('/auth/me')->status);
    }

    /**
     * Le test qui compte le plus ici. Si une adresse inconnue répondait
     * différemment d'un mot de passe faux, le formulaire deviendrait un
     * annuaire : n'importe qui pourrait vérifier qui est client de la boutique.
     */
    public function test_un_compte_inconnu_et_un_mauvais_mot_de_passe_sont_indiscernables(): void
    {
        $inconnu = $this->post('/auth/login', [
            'identifier' => 'personne@exemple.com',
            'password' => 'motdepasse',
        ]);

        $this->forgetCookies();

        $mauvais = $this->post('/auth/login', [
            'identifier' => 'lemaye@exemple.com',
            'password' => 'pas-le-bon',
        ]);

        $this->assertSame($mauvais->status, $inconnu->status);
        $this->assertSame($mauvais->payload, $inconnu->payload);
    }

    public function test_bloque_apres_cinq_echecs(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/auth/login', [
                'identifier' => 'lemaye@exemple.com',
                'password' => 'faux',
            ]);
        }

        $response = $this->post('/auth/login', [
            'identifier' => 'lemaye@exemple.com',
            'password' => 'motdepasse',   // le bon, mais trop tard
        ]);

        $this->assertSame(429, $response->status);
        $this->assertArrayHasKey('retry_after', $response->payload['error']);
    }

    public function test_une_connexion_reussie_remet_le_compteur_a_zero(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->post('/auth/login', [
                'identifier' => 'lemaye@exemple.com',
                'password' => 'faux',
            ]);
        }

        $this->post('/auth/login', ['identifier' => 'lemaye@exemple.com', 'password' => 'motdepasse']);

        for ($i = 0; $i < 4; $i++) {
            $this->post('/auth/login', ['identifier' => 'lemaye@exemple.com', 'password' => 'faux']);
        }

        $response = $this->post('/auth/login', [
            'identifier' => 'lemaye@exemple.com',
            'password' => 'motdepasse',
        ]);

        $this->assertSame(200, $response->status);
    }

    public function test_se_souvenir_de_moi_pose_un_cookie_persistant(): void
    {
        $response = $this->post('/auth/login', [
            'identifier' => 'lemaye@exemple.com',
            'password' => 'motdepasse',
            'remember' => true,
        ]);

        $this->assertStringContainsString('Max-Age', $response->cookies[0]);
    }

    /**
     * Une adresse non vérifiée ne bloque pas la connexion : exiger un clic dans
     * un e-mail peut-être classé en indésirables refoulerait de vrais clients.
     */
    public function test_une_adresse_non_verifiee_nempeche_pas_de_se_connecter(): void
    {
        $response = $this->post('/auth/login', [
            'identifier' => 'lemaye@exemple.com',
            'password' => 'motdepasse',
        ]);

        $this->assertSame(200, $response->status);
        $this->assertFalse($response->payload['data']['customer']['email_verified']);
    }

    public function test_la_deconnexion_ferme_la_session(): void
    {
        $this->post('/auth/login', ['identifier' => 'lemaye@exemple.com', 'password' => 'motdepasse']);
        $this->assertSame(200, $this->get('/auth/me')->status);

        $avant = (int) Database::first('SELECT COUNT(*) c FROM auth_tokens')['c'];
        $this->post('/auth/logout');

        $this->assertSame(401, $this->get('/auth/me')->status);

        // Exactement un jeton révoqué, pas davantage : l'inscription de setUp a
        // laissé une autre session ouverte, et se déconnecter d'un appareil ne
        // doit pas fermer ceux des autres.
        $this->assertSame($avant - 1, (int) Database::first('SELECT COUNT(*) c FROM auth_tokens')['c']);
    }

    public function test_la_deconnexion_dun_visiteur_ne_casse_rien(): void
    {
        $this->assertSame(204, $this->post('/auth/logout')->status);
    }

    public function test_me_repond_401_sans_session(): void
    {
        $response = $this->get('/auth/me');

        $this->assertSame(401, $response->status);
        $this->assertSame('unauthorized', $response->payload['error']['code']);
    }

    public function test_un_identifiant_vide_est_refuse_sans_toucher_a_la_base(): void
    {
        $response = $this->post('/auth/login', ['identifier' => '', 'password' => '']);

        $this->assertSame(422, $response->status);

        // Le compteur est filtré sur l'action : l'inscription du setUp en
        // enregistre une de son côté depuis qu'elle est elle aussi limitée, et
        // ce test-ci ne parle que des tentatives de connexion.
        $this->assertSame(
            0,
            (int) Database::first("SELECT COUNT(*) c FROM auth_attempts WHERE action = 'login'")['c']
        );
    }
}
