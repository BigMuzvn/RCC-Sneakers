<?php

namespace Rcc\Tests\Feature;

use Rcc\Database;
use Rcc\Tests\ApiTestCase;

class PasswordResetTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->post('/auth/register', [
            'name' => 'Lemaye Kpatinde',
            'email' => 'lemaye@exemple.com',
            'phone' => '0197000000',
            'password' => 'ancien-mot-de-passe',
            'terms' => true,
        ]);

        $this->forgetCookies();
        $this->mailer->sent = [];
    }

    private function askReset(): string
    {
        $this->post('/auth/forgot-password', ['email' => 'lemaye@exemple.com']);

        return $this->mailer->lastToken();
    }

    public function test_une_adresse_connue_recoit_un_lien(): void
    {
        $this->post('/auth/forgot-password', ['email' => 'lemaye@exemple.com']);

        $this->assertSame(1, $this->mailer->count());
        $this->assertSame('lemaye@exemple.com', $this->mailer->last()['to']);
    }

    /**
     * Même raisonnement que la connexion : si une adresse inconnue répondait
     * autrement, ce formulaire permettrait de vérifier qui est client.
     * Rien n'est envoyé, mais la réponse est identique au mot près.
     */
    public function test_une_adresse_inconnue_repond_exactement_pareil(): void
    {
        $connue = $this->post('/auth/forgot-password', ['email' => 'lemaye@exemple.com']);
        $inconnue = $this->post('/auth/forgot-password', ['email' => 'personne@exemple.com']);

        $this->assertSame($connue->status, $inconnue->status);
        $this->assertSame($connue->payload, $inconnue->payload);
        $this->assertSame(1, $this->mailer->count());
    }

    public function test_le_lien_permet_de_choisir_un_nouveau_mot_de_passe(): void
    {
        $token = $this->askReset();

        $response = $this->post('/auth/reset-password', [
            'token' => $token,
            'password' => 'nouveau-mot-de-passe',
        ]);

        $this->assertSame(200, $response->status);

        $this->assertSame(200, $this->post('/auth/login', [
            'identifier' => 'lemaye@exemple.com',
            'password' => 'nouveau-mot-de-passe',
        ])->status);
    }

    public function test_lancien_mot_de_passe_ne_fonctionne_plus(): void
    {
        $token = $this->askReset();
        $this->post('/auth/reset-password', ['token' => $token, 'password' => 'nouveau-mot-de-passe']);
        $this->forgetCookies();

        $response = $this->post('/auth/login', [
            'identifier' => 'lemaye@exemple.com',
            'password' => 'ancien-mot-de-passe',
        ]);

        $this->assertSame(401, $response->status);
    }

    /**
     * Sans cette révocation, une session détournée survit au changement de mot
     * de passe — et le geste ne sert alors à rien.
     */
    public function test_la_reinitialisation_coupe_toutes_les_sessions_ouvertes(): void
    {
        $this->post('/auth/login', ['identifier' => 'lemaye@exemple.com', 'password' => 'ancien-mot-de-passe']);
        $sessionOuverte = $this->cookies;
        $this->assertSame(200, $this->get('/auth/me')->status);

        $this->forgetCookies();
        $token = $this->askReset();
        $this->post('/auth/reset-password', ['token' => $token, 'password' => 'nouveau-mot-de-passe']);

        $this->cookies = $sessionOuverte;

        $this->assertSame(401, $this->get('/auth/me')->status);
    }

    public function test_le_lien_ne_fonctionne_quune_fois(): void
    {
        $token = $this->askReset();

        $this->post('/auth/reset-password', ['token' => $token, 'password' => 'nouveau-mot-de-passe']);
        $second = $this->post('/auth/reset-password', ['token' => $token, 'password' => 'encore-un-autre']);

        $this->assertSame(422, $second->status);
    }

    /**
     * Demander un nouveau lien doit périmer le précédent : sinon un lien
     * ancien, resté dans une boîte mail, ouvre encore le compte.
     */
    public function test_un_nouveau_lien_perime_le_precedent(): void
    {
        $premier = $this->askReset();
        $second = $this->askReset();

        $this->assertNotSame($premier, $second);
        $this->assertSame(422, $this->post('/auth/reset-password', [
            'token' => $premier,
            'password' => 'nouveau-mot-de-passe',
        ])->status);
    }

    public function test_un_jeton_expire_est_refuse(): void
    {
        $token = $this->askReset();

        Database::run("UPDATE customer_tokens SET expires_at = ? WHERE purpose = 'password_reset'", [Database::now(-60)]);

        $this->assertSame(422, $this->post('/auth/reset-password', [
            'token' => $token,
            'password' => 'nouveau-mot-de-passe',
        ])->status);
    }

    public function test_le_lien_de_reinitialisation_vaut_une_heure(): void
    {
        $this->askReset();

        $row = Database::first("SELECT created_at, expires_at FROM customer_tokens WHERE purpose = 'password_reset'");
        $duree = strtotime($row['expires_at'] . ' UTC') - strtotime($row['created_at'] . ' UTC');

        $this->assertSame(3600, $duree);
    }

    public function test_un_nouveau_mot_de_passe_trop_court_est_refuse(): void
    {
        $token = $this->askReset();

        $response = $this->post('/auth/reset-password', ['token' => $token, 'password' => '123']);

        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('password', $response->payload['error']['fields']);
    }

    /**
     * Cliquer le lien prouve l'accès à la boîte mail : autant en tirer la
     * vérification d'adresse, plutôt que de la redemander ensuite.
     */
    public function test_la_reinitialisation_vaut_verification_de_ladresse(): void
    {
        $token = $this->askReset();
        $this->post('/auth/reset-password', ['token' => $token, 'password' => 'nouveau-mot-de-passe']);

        $this->post('/auth/login', ['identifier' => 'lemaye@exemple.com', 'password' => 'nouveau-mot-de-passe']);

        $this->assertTrue($this->get('/auth/me')->payload['data']['customer']['email_verified']);
    }

    /**
     * Le quota Brevo est de 300 envois par jour. Sans ce plafond, marteler ce
     * formulaire l'épuise en une minute et coupe tous les e-mails de la
     * boutique — confirmations de commande comprises — jusqu'au lendemain.
     */
    public function test_les_demandes_repetees_sont_plafonnees(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->post('/auth/forgot-password', ['email' => 'lemaye@exemple.com']);
        }

        $response = $this->post('/auth/forgot-password', ['email' => 'lemaye@exemple.com']);

        $this->assertSame(429, $response->status);
        $this->assertSame(3, $this->mailer->count());
    }

    public function test_une_adresse_malformee_est_refusee(): void
    {
        $response = $this->post('/auth/forgot-password', ['email' => 'pas-une-adresse']);

        $this->assertSame(422, $response->status);
        $this->assertSame(0, $this->mailer->count());
    }
}
