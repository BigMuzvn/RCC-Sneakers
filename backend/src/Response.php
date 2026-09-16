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
    ) {
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

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');

        foreach ($this->cookies as $cookie) {
            header('Set-Cookie: ' . $cookie, false);
        }

        if ($this->status !== 204) {
            echo json_encode(
                $this->payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }
    }
}
