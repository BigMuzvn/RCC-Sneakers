<?php

namespace Rcc\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rcc\Config;
use Rcc\CookieJar;

class CookieJarTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(['app' => ['env' => 'local'], 'session' => ['secure' => false]]);
    }

    protected function tearDown(): void
    {
        Config::load(require dirname(__DIR__, 2) . '/config.php');
    }

    public function test_relit_un_cookie_entrant(): void
    {
        $jar = new CookieJar(['rcc_session' => 'abc']);

        $this->assertSame('abc', $jar->get('rcc_session'));
        $this->assertNull($jar->get('inconnu'));
    }

    /**
     * HttpOnly est la protection décisive : sans lui, n'importe quel script
     * injecté dans la page lit le jeton et prend le compte du client.
     */
    public function test_le_cookie_est_httponly(): void
    {
        $jar = new CookieJar();
        $jar->set('rcc_session', 'jeton');

        $this->assertStringContainsString('HttpOnly', $jar->headers()[0]);
    }

    /**
     * SameSite=Lax empêche l'envoi du cookie sur une requête POST venue d'un
     * autre site : c'est ce qui neutralise le CSRF sans jeton supplémentaire.
     */
    public function test_le_cookie_est_samesite_lax(): void
    {
        $jar = new CookieJar();
        $jar->set('rcc_session', 'jeton');

        $this->assertStringContainsString('SameSite=Lax', $jar->headers()[0]);
    }

    public function test_sans_duree_le_cookie_expire_avec_le_navigateur(): void
    {
        $jar = new CookieJar();
        $jar->set('rcc_session', 'jeton');

        $this->assertStringNotContainsString('Max-Age', $jar->headers()[0]);
    }

    public function test_avec_duree_le_cookie_porte_un_max_age(): void
    {
        $jar = new CookieJar();
        $jar->set('rcc_session', 'jeton', 2592000);

        $this->assertStringContainsString('Max-Age=2592000', $jar->headers()[0]);
    }

    /**
     * En production le cookie ne doit jamais partir en clair : intercepté sur
     * un réseau ouvert, il vaut le mot de passe.
     */
    public function test_en_production_le_cookie_est_marque_secure(): void
    {
        Config::load(['app' => ['env' => 'production'], 'session' => ['secure' => true]]);

        $jar = new CookieJar();
        $jar->set('rcc_session', 'jeton');

        $this->assertStringContainsString('Secure', $jar->headers()[0]);
    }

    public function test_en_local_le_cookie_nest_pas_secure(): void
    {
        $jar = new CookieJar();
        $jar->set('rcc_session', 'jeton');

        // Sinon le cookie ne serait jamais posé sur http://localhost.
        $this->assertStringNotContainsString('Secure', $jar->headers()[0]);
    }

    public function test_oublier_un_cookie_le_fait_expirer_dans_le_passe(): void
    {
        $jar = new CookieJar(['rcc_session' => 'abc']);
        $jar->forget('rcc_session');

        $header = $jar->headers()[0];

        $this->assertStringContainsString('rcc_session=;', $header);
        $this->assertStringContainsString('Max-Age=0', $header);
    }

    public function test_la_valeur_est_encodee(): void
    {
        $jar = new CookieJar();
        $jar->set('rcc_session', 'a b;c');

        $this->assertStringNotContainsString('a b;c', $jar->headers()[0]);
    }
}
