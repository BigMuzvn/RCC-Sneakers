<?php

namespace Rcc\Tests\Feature;

use Rcc\Database;
use Rcc\Tests\ApiTestCase;

class EmailVerificationTest extends ApiTestCase
{
    private function register(): string
    {
        $this->post('/auth/register', [
            'name' => 'Lemaye Kpatinde',
            'email' => 'lemaye@exemple.com',
            'phone' => '0197000000',
            'password' => 'motdepasse',
            'terms' => true,
        ]);

        return $this->mailer->lastToken();
    }

    public function test_le_lien_recu_par_mail_verifie_ladresse(): void
    {
        $token = $this->register();

        $response = $this->post('/auth/verify-email', ['token' => $token]);

        $this->assertSame(200, $response->status);
        $this->assertTrue($this->get('/auth/me')->payload['data']['customer']['email_verified']);
    }

    /**
     * Un lien de vérification traîne dans une boîte mail pour toujours. S'il
     * restait réutilisable, quiconque accède plus tard à cet e-mail — un
     * téléphone prêté, une boîte partagée — pourrait s'en resservir.
     */
    public function test_le_lien_ne_fonctionne_quune_fois(): void
    {
        $token = $this->register();

        $this->post('/auth/verify-email', ['token' => $token]);
        $second = $this->post('/auth/verify-email', ['token' => $token]);

        $this->assertSame(422, $second->status);
    }

    public function test_un_jeton_inventé_est_refuse(): void
    {
        $this->register();

        $response = $this->post('/auth/verify-email', ['token' => str_repeat('a', 64)]);

        $this->assertSame(422, $response->status);
    }

    public function test_un_jeton_vide_est_refuse(): void
    {
        $this->register();

        $this->assertSame(422, $this->post('/auth/verify-email', ['token' => ''])->status);
    }

    public function test_un_jeton_expire_est_refuse(): void
    {
        $token = $this->register();

        Database::run('UPDATE customer_tokens SET expires_at = ?', [Database::now(-60)]);

        $this->assertSame(422, $this->post('/auth/verify-email', ['token' => $token])->status);
    }

    public function test_le_lien_est_valable_vingt_quatre_heures(): void
    {
        $this->register();

        $row = Database::first('SELECT created_at, expires_at FROM customer_tokens');
        $duree = strtotime($row['expires_at'] . ' UTC') - strtotime($row['created_at'] . ' UTC');

        $this->assertSame(86400, $duree);
    }

    public function test_on_peut_redemander_le_mail_de_verification(): void
    {
        $this->register();
        $this->mailer->sent = [];

        $response = $this->post('/auth/resend-verification');

        $this->assertSame(200, $response->status);
        $this->assertSame(1, $this->mailer->count());
        $this->assertNotNull($this->mailer->lastToken());
    }

    public function test_redemander_le_mail_exige_dêtre_connecte(): void
    {
        $this->register();
        $this->forgetCookies();

        $this->assertSame(401, $this->post('/auth/resend-verification')->status);
    }

    /**
     * Le quota Brevo est de 300 envois par jour : redemander en boucle doit
     * cesser d'envoyer bien avant de l'épuiser.
     */
    public function test_redemander_en_boucle_est_plafonne(): void
    {
        $this->register();
        $this->mailer->sent = [];

        for ($i = 0; $i < 3; $i++) {
            $this->post('/auth/resend-verification');
        }

        $response = $this->post('/auth/resend-verification');

        $this->assertSame(429, $response->status);
        $this->assertSame(3, $this->mailer->count());
    }

    public function test_une_adresse_deja_verifiee_ne_declenche_aucun_envoi(): void
    {
        $token = $this->register();
        $this->post('/auth/verify-email', ['token' => $token]);
        $this->mailer->sent = [];

        $response = $this->post('/auth/resend-verification');

        $this->assertSame(200, $response->status);
        $this->assertSame(0, $this->mailer->count());
    }

    /**
     * Le jeton envoyé par mail ne doit pas être lisible en base : sinon une
     * fuite permettrait de valider n'importe quelle adresse.
     */
    public function test_le_jeton_nest_pas_stocke_en_clair(): void
    {
        $token = $this->register();

        $stored = Database::first('SELECT token_hash FROM customer_tokens')['token_hash'];

        $this->assertNotSame($token, $stored);
        $this->assertSame(hash('sha256', $token), $stored);
    }
}
