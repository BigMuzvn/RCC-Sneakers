<?php

namespace Rcc;

/**
 * Cookies entrants et sortants.
 *
 * Les en-têtes sont construits plutôt qu'émis par setcookie() : les tests
 * peuvent donc vérifier les attributs de sécurité sans tampon de sortie, et
 * c'est Response::send() qui les écrit réellement.
 */
class CookieJar
{
    /** @var array<int,string> */
    private array $outgoing = [];

    /** @param array<string,string> $incoming */
    public function __construct(private array $incoming = [])
    {
    }

    public function get(string $name): ?string
    {
        $value = $this->incoming[$name] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param int $maxAge 0 = cookie de session, effacé à la fermeture du navigateur */
    public function set(string $name, string $value, int $maxAge = 0): void
    {
        $parts = [sprintf('%s=%s', $name, rawurlencode($value)), 'Path=/'];

        if ($maxAge > 0) {
            $parts[] = 'Max-Age=' . $maxAge;
            $parts[] = 'Expires=' . gmdate('D, d M Y H:i:s \G\M\T', time() + $maxAge);
        }

        // Sans HttpOnly, tout script injecté dans la page lit le jeton et prend
        // le compte. C'est la protection décisive de ce cookie.
        $parts[] = 'HttpOnly';

        // Lax bloque l'envoi sur une requête POST venue d'un autre site, ce qui
        // neutralise le CSRF sans avoir à gérer un jeton dédié.
        $parts[] = 'SameSite=Lax';

        // Jamais en clair en production : intercepté sur un réseau ouvert, ce
        // cookie vaut le mot de passe. En local il doit rester non-Secure,
        // sinon le navigateur refuse de le poser sur http://localhost.
        if (Config::get('session.secure') === true || Config::isProduction()) {
            $parts[] = 'Secure';
        }

        $this->outgoing[] = implode('; ', $parts);

        // Relisible dans la même requête. Sans cela, un contrôleur qui ouvre
        // une session puis demande « qui est connecté ? » obtient null, parce
        // qu'il interrogerait les cookies reçus et non ceux qu'il vient de poser.
        $this->incoming[$name] = $value;
    }

    public function forget(string $name): void
    {
        unset($this->incoming[$name]);

        $this->outgoing[] = implode('; ', [
            $name . '=',
            'Path=/',
            'Max-Age=0',
            'Expires=Thu, 01 Jan 1970 00:00:00 GMT',
            'HttpOnly',
            'SameSite=Lax',
        ]);
    }

    /** @return array<int,string> en-têtes Set-Cookie à émettre */
    public function headers(): array
    {
        return $this->outgoing;
    }
}
