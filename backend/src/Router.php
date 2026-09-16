<?php

namespace Rcc;

use Throwable;

/**
 * Table de routes.
 *
 * Correspondance exacte d'abord, puis motifs à segments variables — `{nom}`.
 * L'ordre n'est pas un détail : sans lui, `/orders/recents` serait avalé par
 * `/orders/{reference}` et traité comme une référence de commande.
 *
 * Un paramètre ne franchit jamais une barre oblique : il désigne un segment,
 * pas un chemin.
 */
class Router
{
    /** @var array<string,array<string,callable>> chemin => verbe => gestionnaire */
    private array $routes = [];

    /** @var array<int,array{segments:array<int,string>,method:string,handler:callable}> */
    private array $patterns = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $path = $this->normalize($path);
        $method = strtoupper($method);

        if (!str_contains($path, '{')) {
            $this->routes[$path][$method] = $handler;

            return;
        }

        $this->patterns[] = [
            'segments' => explode('/', trim($path, '/')),
            'method' => $method,
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $path = $this->normalize($request->path);

        if (isset($this->routes[$path])) {
            $handler = $this->routes[$path][$request->method] ?? null;

            return $handler === null
                ? $this->methodNotAllowed()
                : $this->run($handler, $request);
        }

        $chemin = $path === '/' ? [] : explode('/', trim($path, '/'));
        $connu = false;

        foreach ($this->patterns as $pattern) {
            $params = $this->match($pattern['segments'], $chemin);

            if ($params === null) {
                continue;
            }

            $connu = true;

            if ($pattern['method'] === $request->method) {
                return $this->run($pattern['handler'], $request, $params);
            }
        }

        return $connu
            ? $this->methodNotAllowed()
            : Response::error('not_found', "Cette adresse n'existe pas.", [], 404);
    }

    /**
     * @param  array<int,string> $segments
     * @param  array<int,string> $chemin
     * @return array<int,string>|null valeurs capturées, ou null si pas de correspondance
     */
    private function match(array $segments, array $chemin): ?array
    {
        if (count($segments) !== count($chemin)) {
            return null;
        }

        $params = [];

        foreach ($segments as $i => $segment) {
            if (str_starts_with($segment, '{')) {
                // Un segment vide ne capture rien : /orders/ n'est pas une
                // commande dont la référence serait la chaîne vide.
                if ($chemin[$i] === '') {
                    return null;
                }

                $params[] = rawurldecode($chemin[$i]);

                continue;
            }

            if ($segment !== $chemin[$i]) {
                return null;
            }
        }

        return $params;
    }

    /** @param array<int,string> $params */
    private function run(callable $handler, Request $request, array $params = []): Response
    {
        try {
            return $handler($request, ...$params);
        } catch (Throwable $e) {
            return $this->serverError($e);
        }
    }

    private function methodNotAllowed(): Response
    {
        return Response::error(
            'method_not_allowed',
            'Méthode non autorisée pour cette adresse.',
            [],
            405
        );
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
