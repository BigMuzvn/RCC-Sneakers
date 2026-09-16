<?php

namespace Rcc\Mailer;

/**
 * Envoi d'e-mail transactionnel.
 *
 * L'interface existe pour deux raisons : les tests capturent les messages au
 * lieu de les émettre — le forfait Brevo est à 300 envois par jour et une suite
 * de tests l'épuiserait — et un changement de prestataire ne toucherait aucun
 * contrôleur.
 */
interface Mailer
{
    /**
     * Rend false en cas d'échec, ne lève jamais d'exception : l'appelant décide
     * si un envoi raté doit interrompre son traitement. Pour une inscription,
     * la réponse est non.
     */
    public function send(string $toEmail, string $toName, string $subject, string $html): bool;
}
