<?php

namespace Rcc\Tests\Feature;

use Rcc\Settings;
use Rcc\Tests\ApiTestCase;

/**
 * Ce que la boutique lit d'elle-même avant toute connexion.
 *
 * Le pied de page, la page contact et l'accueil portaient ces textes en dur.
 * Ils les demandent maintenant au serveur, ce qui donne enfin un effet à
 * l'écran « Réglages » de l'administration.
 */
class ShopTest extends ApiTestCase
{
    public function test_un_visiteur_obtient_les_coordonnees_et_la_vitrine(): void
    {
        Settings::set('shop_phone', '+229 01 55 44 33 22');
        Settings::set('shop_city', 'Cotonou, Bénin');
        Settings::set('featured_slugs', json_encode(['une-paire', 'une-autre'], JSON_UNESCAPED_SLASHES));

        $response = $this->get('/shop');

        $this->assertSame(200, $response->status);
        $this->assertSame('+229 01 55 44 33 22', $response->payload['data']['settings']['shop_phone']);
        $this->assertSame('Cotonou, Bénin', $response->payload['data']['settings']['shop_city']);
        $this->assertSame(['une-paire', 'une-autre'], $response->payload['data']['featured']);
    }

    /**
     * L'adresse qui reçoit les commandes est un réglage comme les autres, dans
     * la même table. Renvoyer `Settings::all()` l'aurait publiée sur toutes les
     * pages du site — d'où la liste blanche explicite dans le contrôleur.
     */
    public function test_l_adresse_de_notification_ne_sort_jamais(): void
    {
        Settings::set('shop_notification_email', 'gerant@interne.test');

        $response = $this->get('/shop');

        $this->assertArrayNotHasKey('shop_notification_email', $response->payload['data']['settings']);
        $this->assertStringNotContainsString('gerant@interne.test', json_encode($response->payload));
    }

    /**
     * Un réglage jamais renseigné doit arriver en chaîne vide, et non manquer :
     * le front décide d'afficher ou non la ligne, il ne doit pas avoir à
     * distinguer « absent » de « vide ».
     */
    public function test_un_reglage_absent_arrive_vide(): void
    {
        $response = $this->get('/shop');
        $settings = $response->payload['data']['settings'];

        foreach (['shop_city', 'shop_phone', 'shop_email', 'shop_hours', 'social_instagram', 'social_facebook', 'social_whatsapp'] as $name) {
            $this->assertArrayHasKey($name, $settings, "réglage {$name} manquant");
            $this->assertIsString($settings[$name]);
        }
    }

    public function test_une_vitrine_jamais_reglee_renvoie_une_liste_vide(): void
    {
        $response = $this->get('/shop');

        $this->assertSame([], $response->payload['data']['featured']);
    }
}
