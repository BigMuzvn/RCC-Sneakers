<?php

namespace Rcc;

/**
 * Validation des entrées. Chaque méthode renvoie la valeur nettoyée et
 * enregistre une erreur au passage — l'appelant lit le résultat et interroge
 * `fails()` une seule fois à la fin.
 *
 * Les messages sont en français : ils s'affichent tels quels sous les champs
 * du formulaire, il n'y a pas de couche de traduction côté front.
 */
class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    /** @param array<string,mixed> $data */
    public function __construct(private array $data)
    {
    }

    public function text(string $field, int $min = 1, int $max = 255): string
    {
        $value = trim((string) ($this->data[$field] ?? ''));

        if ($value === '') {
            $this->fail($field, 'Ce champ est obligatoire.');

            return '';
        }

        $length = mb_strlen($value);

        if ($length < $min) {
            $this->fail($field, sprintf('%d caractères minimum.', $min));
        } elseif ($length > $max) {
            $this->fail($field, sprintf('%d caractères maximum.', $max));
        }

        return $value;
    }

    public function email(string $field): string
    {
        $value = mb_strtolower(trim((string) ($this->data[$field] ?? '')));

        if ($value === '') {
            $this->fail($field, 'Ce champ est obligatoire.');

            return '';
        }

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->fail($field, "Cette adresse e-mail n'est pas valide.");
        } elseif (mb_strlen($value) > 191) {
            // 191 est la largeur de la colonne indexée, cf. migrations/001_auth.sql.
            $this->fail($field, 'Cette adresse e-mail est trop longue.');
        }

        return $value;
    }

    public function phone(string $field): string
    {
        $raw = trim((string) ($this->data[$field] ?? ''));

        if ($raw === '') {
            $this->fail($field, 'Ce champ est obligatoire.');

            return '';
        }

        $normalized = Phone::normalize($raw);

        if ($normalized === null) {
            $this->fail($field, "Ce numéro de téléphone n'est pas valide.");

            return '';
        }

        return $normalized;
    }

    /**
     * Le mot de passe n'est jamais rogné : une espace en bordure peut être
     * délibérée, et la retirer en silence empêcherait le client de se
     * reconnecter avec ce qu'il a réellement tapé.
     */
    public function password(string $field, int $min = 8): string
    {
        $value = (string) ($this->data[$field] ?? '');

        if ($value === '') {
            $this->fail($field, 'Ce champ est obligatoire.');

            return '';
        }

        if (mb_strlen($value) < $min) {
            $this->fail($field, sprintf('%d caractères minimum.', $min));
        } elseif (mb_strlen($value) > 200) {
            // bcrypt tronque au-delà de 72 octets ; on refuse plutôt que de
            // laisser croire qu'un mot de passe très long est pris en compte.
            $this->fail($field, '200 caractères maximum.');
        }

        return $value;
    }

    public function accepted(string $field): bool
    {
        $value = $this->data[$field] ?? false;
        $ok = in_array($value, [true, 1, '1', 'true', 'on'], true);

        if (!$ok) {
            $this->fail($field, 'Vous devez accepter les conditions.');
        }

        return $ok;
    }

    /** Valeur libre, sans contrainte : utile pour les booléens optionnels. */
    public function flag(string $field): bool
    {
        return in_array($this->data[$field] ?? false, [true, 1, '1', 'true', 'on'], true);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    private function fail(string $field, string $message): void
    {
        // La première erreur d'un champ est la plus parlante : « obligatoire »
        // vaut mieux que « 8 caractères minimum » sur un champ vide.
        $this->errors[$field] ??= $message;
    }
}
