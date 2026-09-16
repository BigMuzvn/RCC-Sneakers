<?php

namespace Rcc\Mailer;

/**
 * Écrit les messages dans storage/logs/mail.log au lieu de les envoyer.
 *
 * Utile en développement : le forfait Brevo est à 300 envois par jour, et
 * itérer sur un formulaire d'inscription le consommerait pour rien. Se règle
 * avec 'driver' => 'log' dans config.php.
 */
class LogMailer implements Mailer
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? dirname(__DIR__, 2) . '/storage/logs/mail.log';

        if (!is_dir(dirname($this->path))) {
            @mkdir(dirname($this->path), 0775, true);
        }
    }

    public function send(string $toEmail, string $toName, string $subject, string $html): bool
    {
        $entry = sprintf(
            "\n===== %s =====\nÀ       : %s <%s>\nSujet   : %s\n%s\n",
            gmdate('Y-m-d H:i:s') . ' UTC',
            $toName,
            $toEmail,
            $subject,
            $html
        );

        return file_put_contents($this->path, $entry, FILE_APPEND | LOCK_EX) !== false;
    }
}
