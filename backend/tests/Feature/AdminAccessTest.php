<?php

namespace Rcc\Tests\Feature;

use Rcc\Database;
use Rcc\Tests\ApiTestCase;

/**
 * La garde d'accès à l'administration.
 *
 * C'est le test le plus important de cette tranche : une seule route oubliée
 * ouvre la boutique. Il les parcourt donc toutes, plutôt que d'en vérifier
 * quelques-unes et d'espérer.
 */
class AdminAccessTest extends ApiTestCase
{
    /** @return array<int,array{0:string,1:string}> */
    private function routes(): array
    {
        return [
            ['GET', '/admin/overview'],
            ['GET', '/admin/log'],
            ['GET', '/admin/orders'],
            ['GET', '/admin/orders/RCC-000000-0000'],
            ['POST', '/admin/orders/RCC-000000-0000/status'],
            ['GET', '/admin/products'],
            ['POST', '/admin/products'],
            ['POST', '/admin/products/1'],
            ['POST', '/admin/products/1/stock'],
            ['GET', '/admin/jerseys'],
            ['POST', '/admin/jerseys'],
            ['POST', '/admin/jerseys/1'],
            ['POST', '/admin/jerseys/1/stock'],
            ['POST', '/admin/uploads'],
            ['GET', '/admin/customers'],
            ['GET', '/admin/customers/1'],
            ['POST', '/admin/customers/1/status'],
            ['POST', '/admin/customers/1/anonymise'],
            ['POST', '/admin/customers/1/role'],
            ['GET', '/admin/messages'],
            ['POST', '/admin/messages/1/status'],
            ['GET', '/admin/newsletter'],
            ['POST', '/admin/newsletter/1/unsubscribe'],
            ['POST', '/admin/newsletter/sync'],
            ['GET', '/admin/settings'],
            ['POST', '/admin/settings'],
            ['POST', '/admin/featured'],
            ['POST', '/admin/delivery-zones/cotonou'],
        ];
    }

    public function test_aucune_route_dadministration_nest_ouverte_aux_visiteurs(): void
    {
        foreach ($this->routes() as [$method, $path]) {
            $response = $this->request($method, $path);

            $this->assertSame(401, $response->status, "{$method} {$path} devait exiger une session");
        }
    }

    /**
     * Un client identifié mais ordinaire reçoit 403 et non 401 : il est bien
     * connecté, ce n'est simplement pas pour lui. Le renvoyer vers la connexion
     * l'enfermerait dans une boucle.
     */
    public function test_aucune_route_nest_ouverte_a_un_client_ordinaire(): void
    {
        $this->loginAsCustomer();

        foreach ($this->routes() as [$method, $path]) {
            $response = $this->request($method, $path);

            $this->assertSame(403, $response->status, "{$method} {$path} devait être refusée à un client");
        }
    }

    /**
     * L'administrateur, lui, passe la garde partout. Le contenu peut être une
     * erreur de validation ou un 404 — ce qui compte est que ce ne soit plus
     * un refus d'accès.
     */
    public function test_un_administrateur_franchit_la_garde_partout(): void
    {
        $this->loginAsAdmin();

        foreach ($this->routes() as [$method, $path]) {
            $response = $this->request($method, $path);

            $this->assertNotSame(401, $response->status, "{$method} {$path} refusait l'administrateur");
            $this->assertNotSame(403, $response->status, "{$method} {$path} refusait l'administrateur");
        }
    }

    /**
     * Le drapeau est relu à chaque requête : rétrograder quelqu'un ferme son
     * accès immédiatement, sans attendre sa déconnexion.
     */
    public function test_une_retrogradation_prend_effet_aussitot(): void
    {
        $id = $this->loginAsAdmin();
        $this->assertSame(200, $this->get('/admin/overview')->status);

        Database::run('UPDATE customers SET is_admin = 0 WHERE id = ?', [$id]);

        $this->assertSame(403, $this->get('/admin/overview')->status);
    }

    /** Un administrateur suspendu n'administre plus rien — ni ne se connecte. */
    public function test_un_administrateur_suspendu_perd_tout_acces(): void
    {
        $id = $this->loginAsAdmin();

        Database::run("UPDATE customers SET status = 'suspended' WHERE id = ?", [$id]);

        $this->assertSame(401, $this->get('/admin/overview')->status, 'la session devait tomber');

        $this->forgetCookies();
        $reconnexion = $this->post('/auth/login', [
            'identifier' => 'admin@exemple.com',
            'password' => 'motdepasse',
        ]);

        $this->assertSame(403, $reconnexion->status);
        $this->assertSame('account_suspended', $reconnexion->payload['error']['code']);
    }

    /**
     * Le drapeau voyage jusqu'au front pour qu'il sache afficher l'accès — mais
     * il ne protège rien : le serveur revérifie à chaque appel.
     */
    public function test_le_client_sait_sil_est_administrateur(): void
    {
        $this->loginAsCustomer();
        $this->assertFalse($this->get('/auth/me')->payload['data']['customer']['is_admin']);

        $this->forgetCookies();
        $this->loginAsAdmin();
        $this->assertTrue($this->get('/auth/me')->payload['data']['customer']['is_admin']);
    }
}
