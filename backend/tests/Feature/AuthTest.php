<?php

namespace Rcc\Tests\Feature;

use Rcc\Auth;
use Rcc\CookieJar;
use Rcc\Database;
use Rcc\Tests\DatabaseTestCase;

class AuthTest extends DatabaseTestCase
{
    private function auth(array $cookies = []): Auth
    {
        return new Auth(new CookieJar($cookies));
    }

    /** Rejoue la requête suivante avec le cookie que la précédente a posé. */
    private function cookieFrom(CookieJar $jar): array
    {
        preg_match('/^rcc_session=([^;]*)/', $jar->headers()[0], $m);

        return ['rcc_session' => rawurldecode($m[1])];
    }

    public function test_sans_cookie_personne_nest_connecte(): void
    {
        $this->assertNull($this->auth()->id());
        $this->assertNull($this->auth()->customer());
    }

    public function test_apres_connexion_le_client_est_reconnu_a_la_requete_suivante(): void
    {
        $id = $this->createCustomer();

        $jar = new CookieJar();
        (new Auth($jar))->login($id, remember: false);

        $suivante = $this->auth($this->cookieFrom($jar));

        $this->assertSame($id, $suivante->id());
        $this->assertSame('client@exemple.com', $suivante->customer()['email']);
    }

    /**
     * Une fuite de la base ne doit donner aucune session utilisable : seule la
     * moitié secrète hachée y figure, jamais le jeton envoyé au navigateur.
     */
    public function test_le_jeton_nest_jamais_stocke_en_clair(): void
    {
        $id = $this->createCustomer();
        $jar = new CookieJar();
        (new Auth($jar))->login($id, remember: false);

        $cookie = $this->cookieFrom($jar)['rcc_session'];
        $row = Database::first('SELECT selector, validator_hash FROM auth_tokens');

        $this->assertStringNotContainsString($row['validator_hash'], $cookie);
        $this->assertStringNotContainsString($cookie, $row['validator_hash']);
    }

    public function test_un_jeton_inventé_ne_connecte_personne(): void
    {
        $this->createCustomer();

        $this->assertNull($this->auth(['rcc_session' => 'nimportequoi'])->id());
        $this->assertNull($this->auth(['rcc_session' => str_repeat('a', 32) . '.' . str_repeat('b', 64)])->id());
    }

    /**
     * Le sélecteur peut être connu — il est en base — mais sans le validateur
     * il ne doit rien ouvrir.
     */
    public function test_un_bon_selecteur_avec_un_mauvais_validateur_echoue(): void
    {
        $id = $this->createCustomer();
        $jar = new CookieJar();
        (new Auth($jar))->login($id, remember: false);

        $selector = Database::first('SELECT selector FROM auth_tokens')['selector'];

        $this->assertNull($this->auth(['rcc_session' => $selector . '.' . str_repeat('0', 64)])->id());
    }

    public function test_un_jeton_expire_ne_connecte_plus(): void
    {
        $id = $this->createCustomer();
        $jar = new CookieJar();
        (new Auth($jar))->login($id, remember: false);

        Database::run('UPDATE auth_tokens SET expires_at = ?', [Database::now(-60)]);

        $this->assertNull($this->auth($this->cookieFrom($jar))->id());
    }

    public function test_la_deconnexion_revoque_le_jeton_en_base(): void
    {
        $id = $this->createCustomer();
        $jar = new CookieJar();
        (new Auth($jar))->login($id, remember: false);

        $cookies = $this->cookieFrom($jar);
        $sortie = new CookieJar($cookies);
        (new Auth($sortie))->logout();

        $this->assertSame(0, (int) Database::first('SELECT COUNT(*) c FROM auth_tokens')['c']);
        $this->assertNull($this->auth($cookies)->id());
    }

    public function test_se_souvenir_de_moi_pose_un_cookie_persistant(): void
    {
        $id = $this->createCustomer();

        $sans = new CookieJar();
        (new Auth($sans))->login($id, remember: false);
        $this->assertStringNotContainsString('Max-Age', $sans->headers()[0]);

        $avec = new CookieJar();
        (new Auth($avec))->login($id, remember: true);
        $this->assertStringContainsString('Max-Age', $avec->headers()[0]);
    }

    /**
     * Après un changement de mot de passe, une session détournée ne doit pas
     * survivre — sinon le geste ne sert à rien.
     */
    public function test_on_peut_revoquer_toutes_les_sessions_dun_client(): void
    {
        $id = $this->createCustomer();

        $telephone = new CookieJar();
        (new Auth($telephone))->login($id, remember: true);
        $ordinateur = new CookieJar();
        (new Auth($ordinateur))->login($id, remember: true);

        $this->assertSame(2, (int) Database::first('SELECT COUNT(*) c FROM auth_tokens')['c']);

        Auth::revokeAll($id);

        $this->assertNull($this->auth($this->cookieFrom($telephone))->id());
        $this->assertNull($this->auth($this->cookieFrom($ordinateur))->id());
    }

    public function test_deux_appareils_ont_des_sessions_independantes(): void
    {
        $id = $this->createCustomer();

        $telephone = new CookieJar();
        (new Auth($telephone))->login($id, remember: true);
        $ordinateur = new CookieJar();
        (new Auth($ordinateur))->login($id, remember: true);

        $cookiesTelephone = $this->cookieFrom($telephone);
        (new Auth(new CookieJar($cookiesTelephone)))->logout();

        $this->assertNull($this->auth($cookiesTelephone)->id());
        $this->assertSame($id, $this->auth($this->cookieFrom($ordinateur))->id());
    }

    public function test_le_mot_de_passe_hache_ne_sort_jamais_du_client(): void
    {
        $id = $this->createCustomer();
        $jar = new CookieJar();
        (new Auth($jar))->login($id, remember: false);

        $public = $this->auth($this->cookieFrom($jar))->publicCustomer();

        $this->assertArrayNotHasKey('password_hash', $public);
        $this->assertArrayHasKey('email', $public);
    }
}
