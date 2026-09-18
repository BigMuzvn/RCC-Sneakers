<?php

namespace Rcc;

/**
 * Requête entrante. Construite depuis les superglobales en production,
 * directement dans les tests.
 */
class Request
{
    /**
     * @param array<string,mixed>  $body
     * @param array<string,string> $headers
     * @param array<string,string> $cookies
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $body = [],
        public readonly string $ip = '127.0.0.1',
        public readonly array $headers = [],
        public readonly array $cookies = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        // En production l'API vit dans public_html/api/ : le préfixe est retiré
        // pour que les routes s'écrivent /auth/login et non /api/auth/login.
        $path = '/' . trim((string) preg_replace('#^/api#', '', $path), '/');

        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);
        $body = is_array($decoded) ? $decoded : $_POST;

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = (string) $value;
            }
        }

        return new self(
            method: strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            path: $path,
            body: $body,
            ip: self::clientIp(),
            headers: $headers,
            cookies: $_COOKIE,
        );
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * L'adresse du client, telle qu'on peut la croire.
     *
     * `X-Forwarded-For` est un en-tête, c'est-à-dire du texte envoyé par celui
     * qu'on cherche à identifier. Le lire sans condition annulait toutes les
     * limitations par IP du site : il suffisait d'en changer la valeur à chaque
     * requête pour repartir d'un compteur neuf, sur la connexion comme sur
     * l'inscription ou le mot de passe oublié.
     *
     * Il n'est donc cru que si la requête arrive **d'un relais déclaré** dans
     * `app.trusted_proxies`. Sans relais configuré — le cas d'un hébergement
     * mutualisé ordinaire — seule `REMOTE_ADDR` fait foi : elle vient de la
     * connexion TCP et ne se falsifie pas.
     *
     * Si l'hébergeur relaie tout son trafic, cette adresse sera la sienne pour
     * tout le monde et les limitations par IP deviendront communes à tous les
     * visiteurs. Cela se voit — le site refuse tout le monde à la fois — et se
     * règle en déclarant son relais. L'inverse, un en-tête cru sur parole, ne
     * se voit pas.
     *
     * Cette adresse sert la limitation de débit, jamais une décision
     * d'authentification.
     */
    private static function clientIp(): string
    {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $trusted = Config::get('app.trusted_proxies', []);

        if (!is_array($trusted) || !in_array($remote, $trusted, true)) {
            return $remote;
        }

        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';

        if ($forwarded !== '') {
            // Le premier de la liste est le client d'origine ; les suivants sont
            // les relais traversés, qui ajoutent chacun le leur à droite.
            $first = trim(explode(',', $forwarded)[0]);

            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }

        return $remote;
    }
}
