<?php

namespace Rcc\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rcc\Validator;

class ValidatorTest extends TestCase
{
    public function test_une_saisie_complete_ne_produit_aucune_erreur(): void
    {
        $v = new Validator([
            'name' => 'Lemaye Kpatinde',
            'email' => 'client@exemple.com',
            'phone' => '0197000000',
            'password' => 'motdepasse',
            'terms' => true,
        ]);

        $v->text('name', 2, 120);
        $v->email('email');
        $v->phone('phone');
        $v->password('password');
        $v->accepted('terms');

        $this->assertFalse($v->fails());
        $this->assertSame([], $v->errors());
    }

    public function test_un_champ_absent_est_signale_par_son_nom(): void
    {
        $v = new Validator([]);
        $v->text('name', 2, 120);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('name', $v->errors());
    }

    public function test_les_espaces_autour_du_texte_sont_retires(): void
    {
        $v = new Validator(['name' => '  Lemaye  ']);

        $this->assertSame('Lemaye', $v->text('name', 2, 120));
    }

    /**
     * L'unicité de l'e-mail repose sur une collation insensible à la casse, mais
     * on écrit quand même en minuscules : sinon la base garde « Client@… » tel
     * que saisi et tout affichage ultérieur trahit la frappe du client.
     */
    public function test_ladresse_est_ramenee_en_minuscules(): void
    {
        $v = new Validator(['email' => '  Client@Exemple.COM ']);

        $this->assertSame('client@exemple.com', $v->email('email'));
    }

    public function test_une_adresse_malformee_est_rejetee(): void
    {
        $v = new Validator(['email' => 'pas-une-adresse']);
        $v->email('email');

        $this->assertArrayHasKey('email', $v->errors());
    }

    public function test_le_telephone_est_renvoye_sous_sa_forme_canonique(): void
    {
        $v = new Validator(['phone' => '+229 01 97 00 00 00']);

        $this->assertSame('2290197000000', $v->phone('phone'));
        $this->assertFalse($v->fails());
    }

    public function test_un_mot_de_passe_trop_court_est_rejete(): void
    {
        $v = new Validator(['password' => '1234567']);
        $v->password('password');

        $this->assertArrayHasKey('password', $v->errors());
    }

    /**
     * Le mot de passe n'est jamais rogné : une espace finale peut être
     * délibérée, et la retirer en silence empêcherait le client de se
     * reconnecter avec ce qu'il a réellement tapé.
     */
    public function test_le_mot_de_passe_nest_pas_rogne(): void
    {
        $v = new Validator(['password' => ' 12345678 ']);

        $this->assertSame(' 12345678 ', $v->password('password'));
        $this->assertFalse($v->fails());
    }

    public function test_les_conditions_non_cochees_sont_rejetees(): void
    {
        $v = new Validator(['terms' => false]);
        $v->accepted('terms');

        $this->assertArrayHasKey('terms', $v->errors());
    }

    public function test_les_messages_derreur_sont_en_francais(): void
    {
        $v = new Validator(['email' => 'nope']);
        $v->email('email');

        $this->assertMatchesRegularExpression(
            '/adresse|valide/i',
            $v->errors()['email']
        );
    }
}
