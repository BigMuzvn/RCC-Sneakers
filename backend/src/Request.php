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
     * Sur un mutualisé le trafic passe par un proxy, donc REMOTE_ADDR vaut
     * souvent l'adresse du proxy. X-Forwarded-For est cependant falsifiable par
     * le client : cette adresse sert la limitation de débit, jamais une
     * décision d'authentification.
     */
    private static function clientIp(): string
    {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';

        if ($forwarded !== '') {
            $first = trim(explode(',', $forwarded)[0]);

            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }

        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}
