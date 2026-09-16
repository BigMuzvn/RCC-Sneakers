<?php

namespace Rcc\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rcc\Phone;

class PhoneTest extends TestCase
{
    /**
     * Le point décisif : la contrainte d'unicité sur `customers.phone` ne vaut
     * que si toutes les écritures d'un même numéro tombent sur une seule chaîne.
     * Sinon le même client peut ouvrir deux comptes sans s'en apercevoir.
     */
    public function test_toutes_les_ecritures_dun_meme_numero_convergent(): void
    {
        $ecritures = [
            '+229 01 97 00 00 00',
            '+2290197000000',
            '00229 01 97 00 00 00',
            '0197000000',
            '01 97 00 00 00',
            '01-97-00-00-00',
            '01.97.00.00.00',
            ' 0197000000 ',
        ];

        $canoniques = array_map(fn ($e) => Phone::normalize($e), $ecritures);

        $this->assertCount(1, array_unique($canoniques), sprintf(
            "Ces écritures devaient converger, elles ont donné : %s",
            implode(', ', array_unique($canoniques))
        ));
    }

    public function test_la_forme_canonique_ne_contient_que_des_chiffres(): void
    {
        $this->assertSame('2290197000000', Phone::normalize('+229 01 97 00 00 00'));
    }

    public function test_deux_numeros_differents_ne_se_confondent_pas(): void
    {
        $this->assertNotSame(
            Phone::normalize('0197000000'),
            Phone::normalize('0197000001')
        );
    }

    /**
     * Un indicatif étranger déjà présent est conservé tel quel : un client
     * de la diaspora doit pouvoir s'inscrire avec son numéro français.
     */
    public function test_un_indicatif_etranger_est_conserve(): void
    {
        $this->assertSame('33612345678', Phone::normalize('+33 6 12 34 56 78'));
    }

    public function test_rejette_ce_qui_ne_peut_pas_etre_un_numero(): void
    {
        $this->assertNull(Phone::normalize(''));
        $this->assertNull(Phone::normalize('abc'));
        $this->assertNull(Phone::normalize('12'));
    }
}
