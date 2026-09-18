<?php

namespace Rcc\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rcc\Config;
use Rcc\Request;

/**
 * L'adresse retenue pour un visiteur.
 *
 * C'est elle qui porte toutes les limitations par IP du site. Elle était lue
 * dans `X-Forwarded-For`, un en-tête envoyé par celui-là même qu'on cherche à
 * compter : en changer la valeur à chaque requête repartait d'un compteur
 * neuf, sur la connexion comme sur l'inscription.
 */
class RequestIpTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

        // La configuration est statique et partagée : on la rend telle qu'on
        // l'a trouvée, sinon le test suivant hériterait de nos relais.
        Config::load();

        parent::tearDown();
    }

    /** @param array<int,string> $relais */
    private function relais(array $relais): void
    {
        Config::load(['app' => ['trusted_proxies' => $relais]]);
    }

    private function ipVue(): string
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/products';

        return Request::fromGlobals()->ip;
    }

    public function test_sans_relais_declare_l_entete_est_ignore(): void
    {
        $this->relais([]);
        $_SERVER['REMOTE_ADDR'] = '41.85.10.20';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';

        $this->assertSame('41.85.10.20', $this->ipVue(), "l'en-tête ne doit pas l'emporter");
    }

    public function test_derriere_un_relais_declare_l_entete_fait_foi(): void
    {
        $this->relais(['10.0.0.1']);
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '41.85.10.20, 10.0.0.1';

        $this->assertSame('41.85.10.20', $this->ipVue());
    }

    public function test_un_entete_absurde_venu_d_un_relais_ne_passe_pas(): void
    {
        $this->relais(['10.0.0.1']);
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'pas-une-adresse';

        $this->assertSame('10.0.0.1', $this->ipVue());
    }

    /**
     * Le cœur du correctif : douze requêtes annonçant douze adresses
     * différentes ne doivent donner qu'une seule adresse, celle de la connexion.
     */
    public function test_changer_d_entete_ne_donne_pas_douze_visiteurs(): void
    {
        $this->relais([]);
        $_SERVER['REMOTE_ADDR'] = '41.85.10.20';

        $vues = [];

        for ($i = 0; $i < 12; $i++) {
            $_SERVER['HTTP_X_FORWARDED_FOR'] = "1.2.3.{$i}";
            $vues[] = $this->ipVue();
        }

        $this->assertSame(['41.85.10.20'], array_values(array_unique($vues)));
    }
}
