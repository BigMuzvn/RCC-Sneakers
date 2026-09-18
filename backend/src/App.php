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
        $router->add('GET', '/orders/{reference}/facture', fn (Request $r, string $ref) => $orders->invoice($r, $ref));

        $public = new PublicController($this->mailer, $this->contacts);
        $router->add('GET', '/shop', fn (Request $r) => $public->shop($r));
        $router->add('POST', '/newsletter', fn (Request $r) => $public->subscribe($r));
        $router->add('POST', '/contact', fn (Request $r) => $public->contact($r));

        $this->addAdminRoutes($router, $auth);

        $response = $router->dispatch($request);

        // Les cookies posés pendant le traitement sont recollés ici : le
        // contrôleur n'a jamais à s'occuper d'en-têtes HTTP.
        $response->cookies = $cookies->headers();

        return $response;
    }

    /**
     * Routes d'administration.
     *
     * La vérification d'accès est **appliquée ici, une seule fois**, et non
     * répétée au début de chaque méthode : une garde recopiée trente fois finit
     * par être oubliée une fois, et cet oubli-là ouvre la boutique.
     *
     * Le contrôleur ne reçoit donc jamais une requête non autorisée ; il reçoit
     * l'administrateur en second argument, déjà vérifié.
     */
    private function addAdminRoutes(Router $router, Auth $auth): void
    {
        /** Enveloppe un gestionnaire d'administration de sa garde d'accès. */
        $guard = static function (callable $handler) use ($auth): callable {
            return static function (Request $request, string ...$params) use ($auth, $handler): Response {
                $admin = $auth->admin();

                if ($admin !== null) {
                    return $handler($request, $admin, ...$params);
                }

                // 401 pour un visiteur, 403 pour un client identifié : la
                // différence évite au second une boucle de reconnexion inutile.
                return $auth->id() === null ? Response::unauthorized() : Response::forbidden();
            };
        };

        $dashboard = new Admin\DashboardController();
        $router->add('GET', '/admin/overview', $guard([$dashboard, 'overview']));
        $router->add('GET', '/admin/log', $guard([$dashboard, 'log']));

        $orders = new Admin\OrdersController($this->mailer);
        $router->add('GET', '/admin/orders', $guard([$orders, 'index']));
        $router->add('GET', '/admin/orders/{reference}', $guard([$orders, 'show']));
        $router->add('POST', '/admin/orders/{reference}/status', $guard([$orders, 'updateStatus']));

        $catalogue = new Admin\CatalogueController();
        $router->add('GET', '/admin/products', $guard([$catalogue, 'products']));
        $router->add('POST', '/admin/products', $guard([$catalogue, 'createProduct']));
        $router->add('POST', '/admin/products/{id}', $guard([$catalogue, 'updateProduct']));
        $router->add('POST', '/admin/products/{id}/stock', $guard([$catalogue, 'updateProductStock']));
        $router->add('GET', '/admin/jerseys', $guard([$catalogue, 'jerseys']));
        $router->add('POST', '/admin/jerseys', $guard([$catalogue, 'createJersey']));
        $router->add('POST', '/admin/jerseys/{id}', $guard([$catalogue, 'updateJersey']));
        $router->add('POST', '/admin/jerseys/{id}/stock', $guard([$catalogue, 'updateJerseyStock']));
        $router->add('POST', '/admin/uploads', $guard([$catalogue, 'upload']));

        $customers = new Admin\CustomersController();
        $router->add('GET', '/admin/customers', $guard([$customers, 'index']));
        $router->add('GET', '/admin/customers/{id}', $guard([$customers, 'show']));
        $router->add('POST', '/admin/customers/{id}/status', $guard([$customers, 'updateStatus']));
        $router->add('POST', '/admin/customers/{id}/anonymise', $guard([$customers, 'anonymise']));
        $router->add('POST', '/admin/customers/{id}/role', $guard([$customers, 'updateRole']));

        $inbox = new Admin\InboxController($this->contacts);
        $router->add('GET', '/admin/messages', $guard([$inbox, 'messages']));
        $router->add('POST', '/admin/messages/{id}/status', $guard([$inbox, 'updateMessageStatus']));
        $router->add('GET', '/admin/newsletter', $guard([$inbox, 'subscribers']));
        $router->add('POST', '/admin/newsletter/{id}/unsubscribe', $guard([$inbox, 'unsubscribe']));
        $router->add('POST', '/admin/newsletter/sync', $guard([$inbox, 'sync']));

        $settings = new Admin\SettingsController();
        $router->add('GET', '/admin/settings', $guard([$settings, 'index']));
        $router->add('POST', '/admin/settings', $guard([$settings, 'update']));
        $router->add('POST', '/admin/featured', $guard([$settings, 'updateFeatured']));
        $router->add('POST', '/admin/delivery-zones/{id}', $guard([$settings, 'updateZone']));
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
