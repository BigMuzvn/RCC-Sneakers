<?php

namespace Rcc\Tests\Feature;

use Rcc\Database;
use Rcc\RateLimiter;
use Rcc\Tests\DatabaseTestCase;

class RateLimiterTest extends DatabaseTestCase
{
    private function limiter(): RateLimiter
    {
        return new RateLimiter('login', maxPerIdentifier: 3, maxPerIp: 5, windowSeconds: 900);
    }

    public function test_un_identifiant_neuf_nest_pas_bloque(): void
    {
        $this->assertFalse($this->limiter()->isBlocked('client@exemple.com', '1.2.3.4'));
    }

    public function test_bloque_une_fois_le_plafond_atteint(): void
    {
        $limiter = $this->limiter();

        for ($i = 0; $i < 3; $i++) {
            $limiter->record('client@exemple.com', '1.2.3.4');
        }

        $this->assertTrue($limiter->isBlocked('client@exemple.com', '1.2.3.4'));
    }

    public function test_le_blocage_ne_deborde_pas_sur_un_autre_client(): void
    {
        $limiter = $this->limiter();

        for ($i = 0; $i < 3; $i++) {
            $limiter->record('victime@exemple.com', '1.2.3.4');
        }

        $this->assertFalse($limiter->isBlocked('autre@exemple.com', '9.9.9.9'));
    }

    /**
     * Le plafond par IP existe pour le bourrage d'identifiants : un attaquant
     * qui essaie un mot de passe sur mille comptes différents ne déclenche
     * jamais la limite par identifiant.
     */
    public function test_le_plafond_par_ip_arrete_le_bourrage_didentifiants(): void
    {
        $limiter = $this->limiter();

        for ($i = 0; $i < 5; $i++) {
            $limiter->record("cible{$i}@exemple.com", '6.6.6.6');
        }

        $this->assertTrue($limiter->isBlocked('encore-un-autre@exemple.com', '6.6.6.6'));
    }

    public function test_une_connexion_reussie_remet_le_compteur_a_zero(): void
    {
        $limiter = $this->limiter();

        for ($i = 0; $i < 3; $i++) {
            $limiter->record('client@exemple.com', '1.2.3.4');
        }

        $limiter->clear('client@exemple.com');

        $this->assertFalse($limiter->isBlocked('client@exemple.com', '1.2.3.4'));
    }

    public function test_les_tentatives_hors_fenetre_ne_comptent_plus(): void
    {
        $limiter = $this->limiter();

        for ($i = 0; $i < 3; $i++) {
            $limiter->record('client@exemple.com', '1.2.3.4');
        }

        // On recule les tentatives au-delà de la fenêtre de 900 secondes.
        Database::run(
            'UPDATE auth_attempts SET attempted_at = ?',
            [Database::now(-1000)]
        );

        $this->assertFalse($limiter->isBlocked('client@exemple.com', '1.2.3.4'));
    }

    public function test_indique_dans_combien_de_temps_reessayer(): void
    {
        $limiter = $this->limiter();

        for ($i = 0; $i < 3; $i++) {
            $limiter->record('client@exemple.com', '1.2.3.4');
        }

        $seconds = $limiter->retryAfter('client@exemple.com', '1.2.3.4');

        $this->assertGreaterThan(0, $seconds);
        $this->assertLessThanOrEqual(900, $seconds);
    }

    /**
     * Le journal des tentatives ne doit pas devenir une liste en clair des
     * adresses de la clientèle, exploitable telle quelle en cas de fuite.
     */
    public function test_lidentifiant_nest_jamais_stocke_en_clair(): void
    {
        $this->limiter()->record('client@exemple.com', '1.2.3.4');

        $row = Database::first('SELECT identifier_hash FROM auth_attempts');

        $this->assertNotSame('client@exemple.com', $row['identifier_hash']);
        $this->assertSame(64, strlen($row['identifier_hash']));
    }

    /**
     * Deux actions distinctes ont des compteurs distincts : épuiser le quota de
     * « mot de passe oublié » ne doit pas empêcher de se connecter.
     */
    public function test_les_actions_ont_des_compteurs_separes(): void
    {
        $forgot = new RateLimiter('forgot_password', maxPerIdentifier: 3, maxPerIp: 5, windowSeconds: 900);

        for ($i = 0; $i < 3; $i++) {
            $forgot->record('client@exemple.com', '1.2.3.4');
        }

        $this->assertTrue($forgot->isBlocked('client@exemple.com', '1.2.3.4'));
        $this->assertFalse($this->limiter()->isBlocked('client@exemple.com', '1.2.3.4'));
    }
}
