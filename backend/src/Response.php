<?php

namespace Rcc;

/**
 * Réponse JSON, sous forme d'objet plutôt que d'écriture directe : les tests
 * inspectent le statut et la charge utile sans tampon de sortie.
 *
 * Enveloppe constante — { "data": … } ou { "error": { code, message, fields } }.
 * Le front n'a donc qu'une seule forme à connaître.
 */
class Response
{
    /**
     * @param array<string,mixed>  $payload
     * @param array<int,string>    $cookies en-têtes Set-Cookie déjà formatés
     */
    public function __construct(
        public readonly int $status,
        public readonly array $payload,
        public array $cookies = [],
        /** Corps brut — un PDF par exemple. Quand il est là, la charge JSON n'est pas envoyée. */
        public readonly ?string $binary = null,
        /** @var array<string,string> en-têtes supplémentaires */
        public readonly array $headers = [],
    ) {
    }

    /**
     * Fichier à télécharger.
     *
     * Le nom est réduit à ce qui ne peut pas s'échapper de l'en-tête : une
     * référence de commande n'est faite que de lettres, de chiffres et de
     * tirets, mais l'en-tête ne doit pas dépendre de cette confiance.
     */
    public static function download(string $contents, string $filename, string $type = 'application/pdf'): self
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '', $filename) ?: 'document';

        return new self(200, [], [], $contents, [
            'Content-Type' => $type,
            'Content-Disposition' => 'attachment; filename="' . $safe . '"',
            'Content-Length' => (string) strlen($contents),
        ]);
    }

    public static function data(mixed $data, int $status = 200): self
    {
        return new self($status, ['data' => $data]);
    }

    public static function noContent(): self
    {
        return new self(204, []);
    }

    /** @param array<string,string> $fields erreurs par champ de formulaire */
    public static function error(
        string $code,
        string $message,
        array $fields = [],
        int $status = 400,
    ): self {
        $error = ['code' => $code, 'message' => $message];

        if ($fields !== []) {
            $error['fields'] = $fields;
        }

        return new self($status, ['error' => $error]);
    }

    /** @param array<string,string> $fields */
    public static function validation(array $fields): self
    {
        return self::error(
            'validation_failed',
            'Certains champs sont incorrects.',
            $fields,
            422
        );
    }

    public static function unauthorized(string $message = 'Vous devez être connecté.'): self
    {
        return self::error('unauthorized', $message, [], 401);
    }

    /**
     * 403 et non 401 : la différence dit au client « tu es bien identifié, mais
     * ce n'est pas pour toi », ce qui évite une boucle de reconnexion inutile.
     */
    public static function forbidden(string $message = "Vous n'avez pas accès à cette page."): self
    {
        return self::error('forbidden', $message, [], 403);
    }

    /**
     * Trop de requêtes, avec le délai avant la suivante.
     *
     * Le délai est dans la charge utile plutôt que dans un en-tête seul : c'est
     * l'interface qui doit pouvoir dire « réessayez dans trois minutes », et
     * elle ne lit pas les en-têtes.
     *
     * La phrase est un paramètre parce que « trop de tentatives » convient à une
     * connexion refusée, pas à un client qui commande deux fois de suite.
     */
    public static function tooMany(int $retryAfter, string $message = 'Trop de tentatives.'): self
    {
        $minutes = max(1, (int) ceil($retryAfter / 60));

        return new self(429, ['error' => [
            'code' => 'too_many_attempts',
            'message' => sprintf('%s Réessayez dans %d minute%s.', $message, $minutes, $minutes > 1 ? 's' : ''),
            'retry_after' => $retryAfter,
        ]]);
    }

    public static function notFound(string $message = "Cette ressource n'existe pas."): self
    {
        return self::error('not_found', $message, [], 404);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->cookies as $cookie) {
            header('Set-Cookie: ' . $cookie, false);
        }

        if ($this->binary !== null) {
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }

            echo $this->binary;

            return;
        }

        header('Content-Type: application/json; charset=utf-8');

        if ($this->status !== 204) {
            echo json_encode(
                $this->payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }
    }
}
