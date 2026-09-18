<?php

namespace Rcc\Controllers;

use Rcc\Database;
use Rcc\Mailer\Emails;
use Rcc\Mailer\Mailer;
use Rcc\Newsletter\ContactList;
use Rcc\Phone;
use Rcc\RateLimiter;
use Rcc\Request;
use Rcc\Response;
use Rcc\Settings;
use Rcc\Validator;

/**
 * Ce que la boutique expose sans compte : ses propres réglages, et les
 * formulaires de lettre d'information et de contact.
 *
 * Les deux formulaires sont anonymes. C'est ce qui impose une limitation de
 * débit sérieuse : sans elle, un script remplit la table d'adresses inventées
 * et, pour le contact, grignote le quota d'envois à chaque message.
 */
class PublicController
{
    /**
     * Réglages affichables. La liste est **explicite** : `Settings::all()`
     * renverrait aussi l'adresse qui reçoit les commandes, qui n'a rien à
     * faire dans une réponse publique. Un réglage devient visible parce qu'on
     * l'a décidé, jamais parce qu'on a oublié de l'exclure.
     */
    private const PUBLIC_SETTINGS = [
        'shop_city',
        'shop_phone',
        'shop_email',
        'shop_hours',
        'social_instagram',
        'social_facebook',
        'social_whatsapp',
    ];

    public function __construct(
        private Mailer $mailer,
        private ContactList $contacts,
    ) {
    }

    /**
     * Coordonnées et vitrine, en un seul appel.
     *
     * Le pied de page et la page contact portaient ces textes en dur ; l'écran
     * « Réglages » de l'administration les changeait en base sans que rien ne
     * bouge sur le site. Même chose pour les quatre paires de l'accueil, qui
     * étaient une liste écrite dans `Hero.tsx`.
     *
     * Les slugs de la vitrine sont renvoyés **tels quels**, sans vérifier que
     * les articles existent : l'accueil lit déjà le catalogue et ignore ce
     * qu'il n'y trouve pas. Deux requêtes de moins à chaque visite.
     */
    public function shop(Request $request): Response
    {
        $settings = Settings::all();

        $public = [];

        foreach (self::PUBLIC_SETTINGS as $name) {
            $public[$name] = $settings[$name] ?? '';
        }

        return Response::data([
            'settings' => $public,
            'featured' => Settings::list('featured_slugs'),
        ]);
    }

    // ------------------------------------------------------- newsletter

    public function subscribe(Request $request): Response
    {
        $v = new Validator($request->body);
        $email = $v->email('email');

        if ($v->fails()) {
            return Response::validation($v->errors());
        }

        $limiter = new RateLimiter('newsletter', maxPerIdentifier: 3, maxPerIp: 10, windowSeconds: 3600);

        if ($limiter->isBlocked($email, $request->ip)) {
            return $this->tooMany($limiter->retryAfter($email, $request->ip));
        }

        $limiter->record($email, $request->ip);

        // Une réinscription réactive au lieu d'échouer : se réinscrire est le
        // geste le plus banal du formulaire, on ne sait plus si on l'a fait.
        Database::run(
            "INSERT INTO newsletter_subscribers (email, status, source, created_at)
             VALUES (?, 'subscribed', ?, ?)
             ON DUPLICATE KEY UPDATE status = 'subscribed', unsubscribed_at = NULL",
            [$email, mb_substr((string) $request->input('source', 'footer'), 0, 32), Database::now()]
        );

        // Résultat retenu mais jamais bloquant : perdre un inscrit parce qu'un
        // service tiers est en panne serait absurde. Sans date de
        // synchronisation, la ligne reste rejouable depuis l'administration.
        if ($this->contacts->add($email)) {
            Database::run(
                'UPDATE newsletter_subscribers SET synced_at = ? WHERE email = ?',
                [Database::now(), $email]
            );
        }

        return Response::data([
            'message' => 'Vous êtes inscrit. Vous recevrez les prochains drops en avant-première.',
        ], 201);
    }

    // ---------------------------------------------------------- contact

    public function contact(Request $request): Response
    {
        $v = new Validator($request->body);
        $name = $v->text('name', 2, 120);
        $email = $v->email('email');
        $subject = $v->text('subject', 3, 160);
        $body = $v->text('message', 10, 5000);

        // Le téléphone est facultatif : tout le monde n'a pas envie de le
        // laisser pour poser une simple question.
        $rawPhone = trim((string) $request->input('phone', ''));
        $phone = $rawPhone === '' ? null : Phone::normalize($rawPhone);

        if ($rawPhone !== '' && $phone === null) {
            $v->addError('phone', "Ce numéro de téléphone n'est pas valide.");
        }

        if ($v->fails()) {
            return Response::validation($v->errors());
        }

        $limiter = new RateLimiter('contact', maxPerIdentifier: 3, maxPerIp: 5, windowSeconds: 3600);

        if ($limiter->isBlocked($email, $request->ip)) {
            return $this->tooMany($limiter->retryAfter($email, $request->ip));
        }

        $limiter->record($email, $request->ip);

        Database::run(
            "INSERT INTO contact_messages (name, email, phone, subject, body, status, created_at)
             VALUES (?, ?, ?, ?, ?, 'new', ?)",
            [$name, $email, $rawPhone === '' ? null : $rawPhone, $subject, $body, Database::now()]
        );

        $this->notifyShop(Emails::contactMessage([
            'name' => $name,
            'email' => $email,
            'phone' => $rawPhone,
            'subject' => $subject,
            'body' => $body,
        ]));

        return Response::data([
            'message' => 'Message reçu. Nous vous répondons sous 24 h ouvrées.',
        ], 201);
    }

    // ------------------------------------------------------------ privé

    /**
     * Envoie à l'adresse de la boutique, si elle est configurée.
     *
     * Sans adresse, on n'envoie rien plutôt que d'échouer : le message est
     * enregistré de toute façon, et il vaut mieux une notification manquante
     * qu'un formulaire cassé.
     *
     * @param array{subject:string,html:string} $mail
     */
    private function notifyShop(array $mail): void
    {
        $destinataire = Settings::get('shop_notification_email');

        if ($destinataire === '') {
            return;
        }

        $this->mailer->send($destinataire, 'RCC Sneakers', $mail['subject'], $mail['html']);
    }

    private function tooMany(int $retryAfter): Response
    {
        $minutes = max(1, (int) ceil($retryAfter / 60));

        return new Response(429, ['error' => [
            'code' => 'too_many_attempts',
            'message' => sprintf('Trop de tentatives. Réessayez dans %d minute%s.', $minutes, $minutes > 1 ? 's' : ''),
            'retry_after' => $retryAfter,
        ]]);
    }
}
