<?php

namespace Rcc\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rcc\Config;
use Rcc\Request;
use Rcc\Response;
use Rcc\Router;
use RuntimeException;

class RouterTest extends TestCase
{
    private function get(string $path): Request
    {
        return new Request('GET', $path);
    }

    public function test_achemine_vers_le_gestionnaire_correspondant(): void
    {
        $router = new Router();
        $router->add('GET', '/auth/me', fn () => Response::data(['ok' => true]));

        $response = $router->dispatch($this->get('/auth/me'));

        $this->assertSame(200, $response->status);
        $this->assertSame(['ok' => true], $response->payload['data']);
    }

    public function test_transmet_la_requete_au_gestionnaire(): void
    {
        $router = new Router();
        $router->add('POST', '/echo', fn (Request $r) => Response::data($r->input('nom')));

        $response = $router->dispatch(new Request('POST', '/echo', ['nom' => 'Lemaye']));

        $this->assertSame('Lemaye', $response->payload['data']);
    }

    public function test_une_route_inconnue_renvoie_404(): void
    {
        $router = new Router();

        $response = $router->dispatch($this->get('/nexiste-pas'));

        $this->assertSame(404, $response->status);
        $this->assertSame('not_found', $response->payload['error']['code']);
    }

    /**
     * 405 plutôt que 404 : le chemin existe, c'est le verbe qui est faux.
     * Un 404 ici enverrait le développeur front chercher une faute de frappe.
     */
    public function test_un_chemin_connu_avec_le_mauvais_verbe_renvoie_405(): void
    {
        $router = new Router();
        $router->add('POST', '/auth/login', fn () => Response::data([]));

        $response = $router->dispatch($this->get('/auth/login'));

        $this->assertSame(405, $response->status);
    }

    public function test_la_barre_finale_ne_change_pas_la_route(): void
    {
        $router = new Router();
        $router->add('GET', '/auth/me', fn () => Response::data(['ok' => true]));

        $this->assertSame(200, $router->dispatch($this->get('/auth/me/'))->status);
    }

    /**
     * Le détail d'une exception ne doit jamais atteindre le client en
     * production : un message PDO cite le SQL, donc la structure de la base.
     */
    public function test_en_production_une_exception_ne_fuit_pas(): void
    {
        Config::load(['app' => ['env' => 'production', 'debug' => false]]);

        $router = new Router();
        $router->add('GET', '/boum', function () {
            throw new RuntimeException('SQLSTATE[42S02] table customers introuvable');
        });

        $response = $router->dispatch($this->get('/boum'));

        $this->assertSame(500, $response->status);
        $this->assertStringNotContainsString('customers', json_encode($response->payload));
        $this->assertStringNotContainsString('SQLSTATE', json_encode($response->payload));
    }

    public function test_en_developpement_lexception_est_visible(): void
    {
        Config::load(['app' => ['env' => 'local', 'debug' => true]]);

        $router = new Router();
        $router->add('GET', '/boum', fn () => throw new RuntimeException('détail utile'));

        $response = $router->dispatch($this->get('/boum'));

        $this->assertSame(500, $response->status);
        $this->assertSame('détail utile', $response->payload['error']['message']);
    }

    protected function tearDown(): void
    {
        Config::load(require dirname(__DIR__, 2) . '/config.php');
    }
}
