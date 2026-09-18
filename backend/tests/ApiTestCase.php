<?php

namespace Rcc\Tests;

use Rcc\App;
use Rcc\Config;
use Rcc\Request;
use Rcc\Settings;
use Rcc\Response;
use Rcc\Tests\Support\ArrayContactList;
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
    protected ArrayContactList $contacts;

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
        $this->contacts = new ArrayContactList();
        Settings::forget();
        $this->cookies = [];
    }

    /** @param array<string,mixed> $body */
    protected function post(string $path, array $body = []): Response
    {
        return $this->request('POST', $path, $body);
    }

    /**
     * Crée un compte et le promeut, puis laisse la session ouverte.
     *
     * Pas de reconnexion nécessaire après la promotion : la garde relit la
     * ligne du client à chaque requête, elle voit donc le drapeau aussitôt.
     */
    /**
     * Le gérant : super administrateur, qui peut tout.
     *
     * C'est le rang de celui qui tient la boutique, et donc celui que la
     * plupart des tests veulent dire par « administrateur ».
     */
    protected function loginAsAdmin(string $email = 'admin@exemple.com', string $phone = '0197000099'): int
    {
        $id = $this->openSession('Administrateur RCC', $email, $phone);
        \Rcc\Database::run('UPDATE customers SET is_admin = 1, is_super_admin = 1 WHERE id = ?', [$id]);

        return $id;
    }

    /** Un administrateur ajouté : tout sauf les réglages et les accès. */
    protected function loginAsSubAdmin(string $email = 'second@exemple.com', string $phone = '0197000098'): int
    {
        $id = $this->openSession('Second Administrateur', $email, $phone);
        \Rcc\Database::run('UPDATE customers SET is_admin = 1, is_super_admin = 0 WHERE id = ?', [$id]);

        return $id;
    }

    /** Crée un client ordinaire et laisse sa session ouverte. */
    protected function loginAsCustomer(string $email = 'client@exemple.com', string $phone = '0197000011'): int
    {
        return $this->openSession('Client Ordinaire', $email, $phone);
    }

    /**
     * Ouvre une session : inscription si le compte est neuf, connexion sinon.
     *
     * Un test enchaîne souvent plusieurs rôles et revient au premier ; sans ce
     * repli, la seconde inscription est refusée et le test se poursuit sans
     * session, en produisant des 401 qui n'ont rien à voir avec ce qu'il vérifie.
     */
    private function openSession(string $name, string $email, string $phone): int
    {
        $existant = \Rcc\Database::first('SELECT id FROM customers WHERE email = ?', [$email]);

        if ($existant === null) {
            $this->post('/auth/register', [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => 'motdepasse',
                'terms' => true,
            ]);
        } else {
            $this->post('/auth/login', ['identifier' => $email, 'password' => 'motdepasse']);
        }

        $this->mailer->sent = [];

        return (int) \Rcc\Database::first('SELECT id FROM customers WHERE email = ?', [$email])['id'];
    }

    protected function get(string $path): Response
    {
        return $this->request('GET', $path);
    }

    /** @param array<string,mixed> $body */
    protected function request(string $method, string $path, array $body = []): Response
    {
        $response = (new App($this->mailer, $this->contacts))->handle(
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
