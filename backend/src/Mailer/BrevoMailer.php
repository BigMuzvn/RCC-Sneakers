<?php

namespace Rcc\Mailer;

use Rcc\Config;

/**
 * Envoi via l'API transactionnelle de Brevo.
 *
 * curl plutôt qu'un client HTTP tiers : aucune dépendance Composer ne doit
 * partir en production, et ext-curl est présente sur tout mutualisé.
 */
class BrevoMailer implements Mailer
{
    private const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    public function send(string $toEmail, string $toName, string $subject, string $html): bool
    {
        $key = (string) Config::get('mail.brevo_key', '');

        if ($key === '') {
            error_log('[rcc] mail: aucune clé Brevo configurée, envoi abandonné');

            return false;
        }

        $ch = curl_init((string) Config::get('mail.endpoint', self::ENDPOINT));

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) Config::get('mail.timeout', 10),
            CURLOPT_HTTPHEADER => [
                'api-key: ' . $key,
                'accept: application/json',
                'content-type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode(
                $this->payload($toEmail, $toName, $subject, $html),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status < 200 || $status >= 300) {
            // Journalisé, jamais propagé : une panne chez Brevo ne doit pas
            // empêcher un client de créer son compte.
            $this->logFailure(sprintf(
                'échec pour %s (HTTP %d) %s',
                $toEmail,
                $status,
                $error !== '' ? $error : (string) $body
            ));

            return false;
        }

        return true;
    }

    /**
     * Écrit dans un fichier en plus de error_log.
     *
     * error_log part sur la sortie d'erreur de PHP, dont la destination dépend
     * de la façon dont le serveur a été lancé — avec `php -S`, elle peut aller
     * nulle part. Un envoi raté est silencieux par conception ; s'il est en plus
     * intraçable, on ne peut pas distinguer « Brevo a refusé » de « le message
     * est dans les indésirables », et on cherche la panne du mauvais côté.
     */
    private function logFailure(string $message): void
    {
        $line = sprintf('[%s] mail: %s', gmdate('Y-m-d H:i:s') . ' UTC', $message);

        error_log('[rcc] ' . $message);

        $dir = dirname(__DIR__, 2) . '/storage/logs';

        if (is_dir($dir) || @mkdir($dir, 0775, true)) {
            @file_put_contents($dir . '/mail-errors.log', $line . "\n", FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Charge utile de l'API Brevo, isolée pour être vérifiable sans réseau.
     *
     * @return array<string,mixed>
     */
    public function payload(string $toEmail, string $toName, string $subject, string $html): array
    {
        return [
            'sender' => [
                'name' => Config::get('mail.from_name'),
                'email' => Config::get('mail.from_email'),
            ],
            'to' => [['email' => $toEmail, 'name' => $toName]],
            'subject' => $subject,
            'htmlContent' => $html,
            // Une version texte accompagne toujours l'HTML : son absence pèse
            // dans le score anti-spam, et l'expéditeur actuel échoue déjà
            // l'alignement SPF puisque c'est une adresse Gmail.
            'textContent' => $this->toText($html),
        ];
    }

    private function toText(string $html): string
    {
        $text = preg_replace('#<(br|/p|/div|/h[1-6])[^>]*>#i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
