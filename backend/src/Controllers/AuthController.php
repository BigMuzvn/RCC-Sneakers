<?php

namespace Rcc\Controllers;

use PDOException;
use Rcc\Auth;
use Rcc\Config;
use Rcc\CustomerToken;
use Rcc\Database;
use Rcc\Mailer\Emails;
use Rcc\Mailer\Mailer;
use Rcc\Phone;
use Rcc\RateLimiter;
use Rcc\Request;
use Rcc\Response;
use Rcc\Validator;

class AuthController
{
    public function __construct(
        private Auth $auth,
        private Mailer $mailer,
    ) {
    }

    // ---------------------------------------------------------------- inscription

    public function register(Request $request): Response
    {
        $v = new Validator($request->body);

        $name = $v->text('name', 2, 120);
        $email = $v->email('email');
        $phone = $v->phone('phone');
        $password = $v->password('password');
        $v->accepted('terms');

        if ($v->fails()) {
            return Response::validation($v->errors());
        }

        $taken = $this->takenFields($email, $phone);

        if ($taken !== []) {
            return Response::validation($taken);
        }

        try {
            Database::run(
                'INSERT INTO customers
                    (name, email, phone, phone_display, password_hash, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $name,
                    $email,
                    $phone,
                    trim((string) $request->input('phone')),
                    $this->hash($password),
                    Database::now(),
                    Database::now(),
                ]
            );
        } catch (PDOException $e) {
            // Deux inscriptions simultanées peuvent passer la vérification
            // ci-dessus avant que l'une n'insère. C'est la contrainte d'unicité
            // qui tranche, et on la traduit en erreur de formulaire plutôt que
            // de laisser remonter une erreur 500.
            if ($e->getCode() === '23000') {
                $taken = $this->takenFields($email, $phone);

                return Response::validation($taken !== [] ? $taken : [
                    'email' => 'Ce compte existe déjà.',
                ]);
            }

            throw $e;
        }

        $id = (int) Database::connection()->lastInsertId();

        $this->sendVerification($id, $name, $email);
        $this->auth->login($id, remember: false);

        return Response::data(['customer' => $this->auth->publicCustomer()], 201);
    }

    // ------------------------------------------------------------------ connexion

    public function login(Request $request): Response
    {
        $v = new Validator($request->body);

        $identifier = $v->text('identifier', 1, 191);
        // Longueur minimale de 1 : à la connexion on vérifie la présence, pas
        // la robustesse. Un ancien mot de passe court doit rester utilisable.
        $password = $v->password('password', 1);

        if ($v->fails()) {
            return Response::validation($v->errors());
        }

        $key = $this->limiterKey($identifier);
        $limiter = RateLimiter::login();

        if ($limiter->isBlocked($key, $request->ip)) {
            return $this->tooMany($limiter->retryAfter($key, $request->ip));
        }

        $customer = $this->findByIdentifier($identifier);

        // password_verify est exécuté même sans compte trouvé, contre un
        // hachage factice : sans cela la réponse serait nettement plus rapide
        // pour une adresse inconnue, et la durée trahirait qui est client.
        $hash = $customer['password_hash'] ?? $this->dummyHash();
        $valid = password_verify($password, $hash) && $customer !== null;

        if (!$valid) {
            $limiter->record($key, $request->ip);

            // Message volontairement unique : distinguer « compte inconnu » de
            // « mot de passe faux » transformerait ce formulaire en annuaire.
            return Response::error(
                'invalid_credentials',
                'Identifiants incorrects.',
                [],
                401
            );
        }

        $limiter->clear($key);
        $this->auth->login((int) $customer['id'], $v->flag('remember'));

        return Response::data(['customer' => Auth::publicShape($customer)]);
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout();

        return Response::noContent();
    }

    public function me(Request $request): Response
    {
        $customer = $this->auth->customer();

        if ($customer === null) {
            return Response::unauthorized();
        }

        return Response::data(['customer' => Auth::publicShape($customer)]);
    }

    // --------------------------------------------------------------- vérification

    public function verifyEmail(Request $request): Response
    {
        $customerId = CustomerToken::consume(
            (string) $request->input('token', ''),
            CustomerToken::VERIFY_EMAIL
        );

        if ($customerId === null) {
            return Response::validation([
                'token' => "Ce lien n'est plus valable. Demandez-en un nouveau depuis votre compte.",
            ]);
        }

        Database::run(
            'UPDATE customers SET email_verified_at = ?, updated_at = ? WHERE id = ?',
            [Database::now(), Database::now(), $customerId]
        );

        return Response::data(['email_verified' => true]);
    }

    public function resendVerification(Request $request): Response
    {
        $customer = $this->auth->customer();

        if ($customer === null) {
            return Response::unauthorized();
        }

        if ($customer['email_verified_at'] !== null) {
            return Response::data([
                'sent' => false,
                'message' => 'Votre adresse est déjà vérifiée.',
            ]);
        }

        $limiter = RateLimiter::email('resend_verification');

        if ($limiter->isBlocked($customer['email'], $request->ip)) {
            return $this->tooMany($limiter->retryAfter($customer['email'], $request->ip));
        }

        $limiter->record($customer['email'], $request->ip);
        $this->sendVerification((int) $customer['id'], $customer['name'], $customer['email']);

        return Response::data([
            'sent' => true,
            'message' => 'Un nouveau lien vient de vous être envoyé.',
        ]);
    }

    // ------------------------------------------------------------ mot de passe

    public function forgotPassword(Request $request): Response
    {
        $v = new Validator($request->body);
        $email = $v->email('email');

        if ($v->fails()) {
            return Response::validation($v->errors());
        }

        $limiter = RateLimiter::email('forgot_password');

        if ($limiter->isBlocked($email, $request->ip)) {
            return $this->tooMany($limiter->retryAfter($email, $request->ip));
        }

        $limiter->record($email, $request->ip);

        $customer = Database::first('SELECT * FROM customers WHERE email = ?', [$email]);

        if ($customer !== null) {
            $token = CustomerToken::issue(
                (int) $customer['id'],
                CustomerToken::PASSWORD_RESET,
                CustomerToken::RESET_TTL
            );

            $mail = Emails::passwordReset(
                $customer['name'],
                $this->frontUrl('/compte/reinitialiser', $token)
            );

            $this->mailer->send($customer['email'], $customer['name'], $mail['subject'], $mail['html']);
        }

        // Réponse identique que l'adresse existe ou non : sinon ce formulaire
        // permettrait de vérifier qui est client de la boutique.
        return Response::data([
            'message' => "Si un compte existe pour cette adresse, un lien vient d'y être envoyé.",
        ]);
    }

    public function resetPassword(Request $request): Response
    {
        $v = new Validator($request->body);
        $password = $v->password('password');

        // Le mot de passe est validé avant de consommer le jeton : une saisie
        // trop courte ne doit pas brûler le lien reçu par e-mail.
        if ($v->fails()) {
            return Response::validation($v->errors());
        }

        $customerId = CustomerToken::consume(
            (string) $request->input('token', ''),
            CustomerToken::PASSWORD_RESET
        );

        if ($customerId === null) {
            return Response::validation([
                'token' => "Ce lien n'est plus valable. Demandez-en un nouveau.",
            ]);
        }

        Database::run(
            'UPDATE customers
                SET password_hash = ?,
                    email_verified_at = COALESCE(email_verified_at, ?),
                    updated_at = ?
              WHERE id = ?',
            [$this->hash($password), Database::now(), Database::now(), $customerId]
        );

        // Sans cette révocation, une session détournée survivrait au changement
        // de mot de passe et le geste ne servirait à rien.
        Auth::revokeAll($customerId);

        return Response::data([
            'message' => 'Votre mot de passe a été modifié. Vous pouvez vous connecter.',
        ]);
    }

    // ------------------------------------------------------------------- privé

    /** @return array<string,string> */
    private function takenFields(string $email, string $phone): array
    {
        $fields = [];

        if (Database::first('SELECT id FROM customers WHERE email = ?', [$email]) !== null) {
            $fields['email'] = 'Cette adresse e-mail est déjà utilisée.';
        }

        if (Database::first('SELECT id FROM customers WHERE phone = ?', [$phone]) !== null) {
            $fields['phone'] = 'Ce numéro de téléphone est déjà utilisé.';
        }

        return $fields;
    }

    /** @return array<string,mixed>|null */
    private function findByIdentifier(string $identifier): ?array
    {
        if (str_contains($identifier, '@')) {
            return Database::first(
                'SELECT * FROM customers WHERE email = ?',
                [mb_strtolower(trim($identifier))]
            );
        }

        $phone = Phone::normalize($identifier);

        if ($phone === null) {
            return null;
        }

        return Database::first('SELECT * FROM customers WHERE phone = ?', [$phone]);
    }

    /**
     * Clé du compteur de tentatives. Elle est canonique pour que « 0197000000 »
     * et « +229 01 97 00 00 00 » partagent le même compteur — sinon il suffit
     * de changer d'écriture pour repartir à zéro.
     */
    private function limiterKey(string $identifier): string
    {
        if (str_contains($identifier, '@')) {
            return mb_strtolower(trim($identifier));
        }

        return Phone::normalize($identifier) ?? mb_strtolower(trim($identifier));
    }

    private function sendVerification(int $customerId, string $name, string $email): void
    {
        $token = CustomerToken::issue($customerId, CustomerToken::VERIFY_EMAIL, CustomerToken::VERIFY_TTL);
        $mail = Emails::verification($name, $this->frontUrl('/compte/verifier', $token));

        // Le résultat est volontairement ignoré : une panne du service d'envoi
        // ne doit pas empêcher un client de créer son compte. L'échec est
        // journalisé par le mailer, et le lien est redemandable.
        $this->mailer->send($email, $name, $mail['subject'], $mail['html']);
    }

    private function frontUrl(string $path, string $token): string
    {
        return rtrim((string) Config::get('app.url'), '/') . $path . '?token=' . $token;
    }

    private function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, [
            'cost' => (int) Config::get('auth.bcrypt_cost', 12),
        ]);
    }

    private function dummyHash(): string
    {
        return password_hash('aucun-compte', PASSWORD_BCRYPT, [
            'cost' => (int) Config::get('auth.bcrypt_cost', 12),
        ]);
    }

    private function tooMany(int $retryAfter): Response
    {
        $minutes = max(1, (int) ceil($retryAfter / 60));

        $response = Response::error(
            'too_many_attempts',
            sprintf('Trop de tentatives. Réessayez dans %d minute%s.', $minutes, $minutes > 1 ? 's' : ''),
            [],
            429
        );

        return new Response(
            $response->status,
            ['error' => $response->payload['error'] + ['retry_after' => $retryAfter]]
        );
    }
}
