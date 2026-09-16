<?php

namespace Rcc\Tests;

use Rcc\App;
use Rcc\Config;
use Rcc\Request;
use Rcc\Response;
use Rcc\Tests\Support\ArrayMailer;

/**
 * Socle des tests qui passent par l'API complète.
 *
 * Les cookies posés par une réponse sont rejoués dans la requête suivante :
 * une session se comporte donc ici comme dans un navigateur, sans qu'aucun
 * test n'ait à manipuler de jeton à la main.
 */
abstract class ApiTestCase extends DatabaseTestCase
{
    protected ArrayMailer $mailer;

    /** @var array<string,string> */
    protected array $cookies = [];

    protected string $ip = '1.2.3.4';

    protected function setUp(): void
    {
        parent::setUp();

        $config = require dirname(__DIR__) . '/config.php';
        // Coût 4 au lieu de 12 : sinon chaque inscription testée coûte 250 ms.
        $config['auth']['bcrypt_cost'] = 4;
        Config::load($config);

        $this->mailer = new ArrayMailer();
        $this->cookies = [];
    }

    /** @param array<string,mixed> $body */
    protected function post(string $path, array $body = []): Response
    {
        return $this->request('POST', $path, $body);
    }

    protected function get(string $path): Response
    {
        return $this->request('GET', $path);
    }

    /** @param array<string,mixed> $body */
    protected function request(string $method, string $path, array $body = []): Response
    {
        $response = (new App($this->mailer))->handle(
            new Request($method, $path, $body, $this->ip, [], $this->cookies)
        );

        $this->captureCookies($response);

        return $response;
    }

    /** Simule un autre navigateur : même client, session repartie de zéro. */
    protected function forgetCookies(): void
    {
        $this->cookies = [];
    }

    private function captureCookies(Response $response): void
    {
        foreach ($response->cookies as $header) {
            $pair = explode(';', $header)[0];

            if (!str_contains($pair, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $pair, 2);

            if ($value === '') {
                unset($this->cookies[$name]);
            } else {
                $this->cookies[$name] = rawurldecode($value);
            }
        }
    }
}
