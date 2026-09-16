<?php

namespace Rcc\Tests\Support;

use Rcc\Mailer\Mailer;

/**
 * Mailer de test : retient les messages au lieu de les envoyer.
 *
 * Il vit dans tests/ et non dans src/ : c'est un double de test, pas un pilote
 * de production. `fail()` permet de simuler une panne de Brevo et de vérifier
 * qu'une inscription aboutit quand même.
 */
class ArrayMailer implements Mailer
{
    /** @var array<int,array{to:string,name:string,subject:string,html:string}> */
    public array $sent = [];

    private bool $shouldFail = false;

    public function fail(): void
    {
        $this->shouldFail = true;
    }

    public function send(string $toEmail, string $toName, string $subject, string $html): bool
    {
        if ($this->shouldFail) {
            return false;
        }

        $this->sent[] = [
            'to' => $toEmail,
            'name' => $toName,
            'subject' => $subject,
            'html' => $html,
        ];

        return true;
    }

    public function count(): int
    {
        return count($this->sent);
    }

    /** @return array{to:string,name:string,subject:string,html:string}|null */
    public function last(): ?array
    {
        return $this->sent === [] ? null : $this->sent[count($this->sent) - 1];
    }

    /** Extrait le jeton du lien contenu dans le dernier message. */
    public function lastToken(): ?string
    {
        $last = $this->last();

        if ($last === null) {
            return null;
        }

        return preg_match('/token=([A-Za-z0-9]+)/', $last['html'], $m) ? $m[1] : null;
    }
}
