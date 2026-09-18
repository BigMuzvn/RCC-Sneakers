<?php

/**
 * Contrôleur frontal — le seul fichier de l'API exposé au web.
 *
 * En production, seul ce dossier va dans public_html/api/ ; src/, config.php et
 * migrations/ restent au-dessus de la racine web, hors de portée du navigateur.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use Rcc\App;
use Rcc\Config;
use Rcc\Request;
use Rcc\Response;

Config::load();

// En développement, le front tourne sur le serveur Vite et l'API sur un autre
// port : le navigateur les voit alors comme deux origines. En production les
// deux partagent le domaine et rien de tout ceci ne s'applique.
//
// L'origine reçue était renvoyée telle quelle, avec les identifiants. Ce reflet
// ne tenait que par un `if` sur `app.env` — dont la valeur du gabarit est
// « local ». Une mise en ligne qui oublie cette ligne, et n'importe quel site
// visité par un client lisait son compte et agissait en son nom.
//
// La liste ferme cette porte pour de bon : une origine absente n'obtient rien,
// quel que soit l'environnement déclaré. Un oubli de configuration ne peut plus
// rendre l'API permissive, seulement inutilisable depuis un poste de
// développement — une panne qui se voit, là où l'autre ne se voyait pas.
$autorisees = Config::get('app.cors_origins', []);
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($origin !== '' && is_array($autorisees) && in_array($origin, $autorisees, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    // Indispensable pour que le navigateur accepte d'envoyer le cookie de
    // session sur une requête inter-origines.
    header('Access-Control-Allow-Credentials: true');
    // Sans ce Vary, un cache intermédiaire servirait à une origine la réponse
    // préparée pour une autre, en-tête d'autorisation compris.
    header('Vary: Origin');

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Max-Age: 86400');
        http_response_code(204);

        return;
    }
}

// Ces en-têtes ne coûtent rien et ferment des portes : pas d'interprétation
// hasardeuse du type de contenu, pas d'affichage dans une iframe tierce.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

try {
    (new App(App::mailer(), App::contactList()))->handle(Request::fromGlobals())->send();
} catch (Throwable $e) {
    error_log(sprintf('[rcc] fatal %s: %s dans %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));

    Response::error(
        'server_error',
        'Une erreur est survenue. Réessayez dans un instant.',
        [],
        500
    )->send();
}
