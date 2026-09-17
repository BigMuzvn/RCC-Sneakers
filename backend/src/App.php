<?php

namespace Rcc;

use Rcc\Controllers\AuthController;
use Rcc\Controllers\CatalogueController;
use Rcc\Controllers\FavoritesController;
use Rcc\Controllers\OrdersController;
use Rcc\Controllers\PublicController;
use Rcc\Mailer\BrevoMailer;
use Rcc\Mailer\LogMailer;
use Rcc\Mailer\Mailer;
use Rcc\Newsletter\BrevoContactList;
use Rcc\Newsletter\ContactList;

/**
 * Assemblage de l'application : routes, dépendances, cookies.
 *
 * Point d'entrée unique, partagé par public/index.php et par les tests — ce
 * qui garantit que les tests exercent exactement le même chemin que la
 * production, et non une version simplifiée.
 */
class App
{
    public function __construct(
        private Mailer $mailer,
        private ContactList $contacts,
    ) {
    }

    public function handle(Request $request): Response
    {
        $cookies = new CookieJar($request->cookies);
        $auth = new Auth($cookies);
        $controller = new AuthController($auth, $this->mailer);

        $router = new Router();
        $router->add('POST', '/auth/register', fn (Request $r) => $controller->register($r));
        $router->add('POST', '/auth/login', fn (Request $r) => $controller->login($r));
        $router->add('POST', '/auth/logout', fn (Request $r) => $controller->logout($r));
        $router->add('GET', '/auth/me', fn (Request $r) => $controller->me($r));
        $router->add('POST', '/auth/verify-email', fn (Request $r) => $controller->verifyEmail($r));
        $router->add('POST', '/auth/resend-verification', fn (Request $r) => $controller->resendVerification($r));
        $router->add('POST', '/auth/forgot-password', fn (Request $r) => $controller->forgotPassword($r));
        $router->add('POST', '/auth/reset-password', fn (Request $r) => $controller->resetPassword($r));
        $router->add('POST', '/auth/profile', fn (Request $r) => $controller->updateProfile($r));
        $router->add('POST', '/auth/password', fn (Request $r) => $controller->updatePassword($r));

        $favorites = new FavoritesController($auth);
        $router->add('GET', '/favorites', fn (Request $r) => $favorites->index($r));
        $router->add('POST', '/favorites/toggle', fn (Request $r) => $favorites->toggle($r));

        $catalogue = new CatalogueController();
        $router->add('GET', '/products', fn (Request $r) => $catalogue->products($r));
        $router->add('GET', '/jerseys', fn (Request $r) => $catalogue->jerseys($r));
        $router->add('GET', '/delivery-zones', fn (Request $r) => $catalogue->deliveryZones($r));

        $orders = new OrdersController($auth, $this->mailer);
        $router->add('POST', '/orders', fn (Request $r) => $orders->store($r));
        $router->add('GET', '/orders', fn (Request $r) => $orders->index($r));
        $router->add('GET', '/orders/{reference}', fn (Request $r, string $ref) => $orders->show($r, $ref));

        $public = new PublicController($this->mailer, $this->contacts);
        $router->add('POST', '/newsletter', fn (Request $r) => $public->subscribe($r));
        $router->add('POST', '/contact', fn (Request $r) => $public->contact($r));

        $response = $router->dispatch($request);

        // Les cookies posés pendant le traitement sont recollés ici : le
        // contrôleur n'a jamais à s'occuper d'en-têtes HTTP.
        $response->cookies = $cookies->headers();

        return $response;
    }

    /** Liste de contacts de la lettre d'information. */
    public static function contactList(): ContactList
    {
        return new BrevoContactList();
    }

    /** Pilote d'envoi choisi par config.php. */
    public static function mailer(): Mailer
    {
        return Config::get('mail.driver') === 'log'
            ? new LogMailer()
            : new BrevoMailer();
    }
}
