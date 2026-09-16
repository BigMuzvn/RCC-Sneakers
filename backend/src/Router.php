<?php

namespace Rcc;

use Throwable;

/**
 * Table de routes à correspondance exacte. Pas de paramètres d'URL : les huit
 * routes d'authentification n'en ont pas besoin, et un routeur à motifs coûte
 * plus qu'il ne rapporte ici.
 */
class Router
{
    /** @var array<string,array<string,callable>> chemin => verbe => gestionnaire */
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[$this->normalize($path)][strtoupper($method)] = $handler;
    }

    public function dispatch(Request $request): Response
    {
        $path = $this->normalize($request->path);

        if (!isset($this->routes[$path])) {
            return Response::error('not_found', "Cette adresse n'existe pas.", [], 404);
        }

        $handler = $this->routes[$path][$request->method] ?? null;

        if ($handler === null) {
            return Response::error(
                'method_not_allowed',
                'Méthode non autorisée pour cette adresse.',
                [],
                405
            );
        }

        try {
            return $handler($request);
        } catch (Throwable $e) {
            return $this->serverError($e);
        }
    }

    /**
     * Le détail d'une exception ne sort qu'en développement : un message PDO
     * cite le SQL, donc la structure de la base.
     */
    private function serverError(Throwable $e): Response
    {
        error_log(sprintf('[rcc] %s: %s dans %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));

        if (Config::get('app.debug') === true && !Config::isProduction()) {
            return Response::error(
                'server_error',
                $e->getMessage(),
                ['trace' => $e->getFile() . ':' . $e->getLine()],
                500
            );
        }

        return Response::error(
            'server_error',
            'Une erreur est survenue. Réessayez dans un instant.',
            [],
            500
        );
    }

    private function normalize(string $path): string
    {
        return '/' . trim($path, '/');
    }
}
