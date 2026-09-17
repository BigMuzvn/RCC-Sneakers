<?php

namespace Rcc\Newsletter;

use Rcc\Config;

/**
 * Liste de contacts Brevo.
 *
 * `updateEnabled` à true : une adresse déjà connue est mise à jour au lieu de
 * produire une erreur. Se réinscrire est le geste le plus banal du formulaire,
 * il ne doit rien casser.
 */
class BrevoContactList implements ContactList
{
    private const ENDPOINT = 'https://api.brevo.com/v3/contacts';

    public function add(string $email): bool
    {
        $listId = (int) Config::get('mail.brevo_list_id', 0);

        if ($listId === 0) {
            // Aucune liste configurée : on ne prétend pas avoir synchronisé.
            // La ligne locale restera sans date, prête à être rejouée.
            error_log('[rcc] newsletter: aucune liste Brevo configurée (mail.brevo_list_id)');

            return false;
        }

        [$status, $body] = $this->call('POST', self::ENDPOINT, [
            'email' => $email,
            'listIds' => [$listId],
            'updateEnabled' => true,
        ]);

        if ($status >= 200 && $status < 300) {
            return true;
        }

        error_log(sprintf('[rcc] newsletter: échec ajout %s (HTTP %d) %s', $email, $status, $body));

        return false;
    }

    public function remove(string $email): bool
    {
        $listId = (int) Config::get('mail.brevo_list_id', 0);

        if ($listId === 0) {
            return false;
        }

        // On retire de la liste sans supprimer le contact : l'historique
        // d'envois du prestataire garde ainsi son sens.
        [$status] = $this->call(
            'POST',
            sprintf('https://api.brevo.com/v3/contacts/lists/%d/contacts/remove', $listId),
            ['emails' => [$email]]
        );

        return $status >= 200 && $status < 300;
    }

    /**
     * @param  array<string,mixed> $payload
     * @return array{0:int,1:string}
     */
    private function call(string $method, string $url, array $payload): array
    {
        $key = (string) Config::get('mail.brevo_key', '');

        if ($key === '') {
            return [0, 'aucune clé'];
        }

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) Config::get('mail.timeout', 10),
            CURLOPT_HTTPHEADER => [
                'api-key: ' . $key,
                'accept: application/json',
                'content-type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [$status, $error !== '' ? $error : (string) $body];
    }
}
