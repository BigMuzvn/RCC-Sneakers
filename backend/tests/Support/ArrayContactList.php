<?php

namespace Rcc\Tests\Support;

use Rcc\Newsletter\ContactList;

/**
 * Liste de contacts de test : retient les adresses au lieu de les envoyer.
 *
 * Elle vit dans tests/ et non dans src/ : c'est un double de test, pas un
 * pilote de production. `fail()` simule une panne du prestataire.
 */
class ArrayContactList implements ContactList
{
    /** @var array<int,string> */
    public array $added = [];

    /** @var array<int,string> */
    public array $removed = [];

    private bool $shouldFail = false;

    public function fail(): void
    {
        $this->shouldFail = true;
    }

    public function add(string $email): bool
    {
        if ($this->shouldFail) {
            return false;
        }

        $this->added[] = $email;

        return true;
    }

    public function remove(string $email): bool
    {
        if ($this->shouldFail) {
            return false;
        }

        $this->removed[] = $email;

        return true;
    }
}
