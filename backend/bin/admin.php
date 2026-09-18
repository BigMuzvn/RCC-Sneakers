<?php

/**
 * Gestion des administrateurs depuis le serveur.
 *
 *   php bin/admin.php lister
 *   php bin/admin.php promouvoir lemaye@exemple.com
 *   php bin/admin.php retrograder lemaye@exemple.com
 *
 * Le premier administrateur ne peut naître que d'ici. Une page « devenir
 * administrateur », même bien cachée, finit toujours par être trouvée ; exiger
 * un accès au serveur ferme la question. Les suivants peuvent ensuite être
 * promus depuis l'interface par un administrateur existant.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/autoload.php';

use Rcc\Config;
use Rcc\Database;

Config::load();

$commande = $argv[1] ?? 'aide';
$email = isset($argv[2]) ? mb_strtolower(trim($argv[2])) : null;

$sortie = static function (string $message, int $code = 0): never {
    fwrite($code === 0 ? STDOUT : STDERR, $message . "\n");
    exit($code);
};

switch ($commande) {
    case 'lister':
        $admins = Database::run(
            'SELECT id, name, email, status, is_super_admin FROM customers
              WHERE is_admin = 1 ORDER BY is_super_admin DESC, id'
        )->fetchAll();

        if ($admins === []) {
            $sortie("Aucun administrateur.\nCréez-en un : php bin/admin.php promouvoir <e-mail>");
        }

        echo count($admins) . " administrateur(s) :\n";

        foreach ($admins as $a) {
            printf("  #%-3d %-30s %-24s %s\n", $a['id'], $a['email'], $a['name'], $a['status'] . ((int) $a['is_super_admin'] === 1 ? '  SUPER' : ''));
        }

        break;

    /**
     * Désigne le super administrateur.
     *
     * Le rang est **unique** : le désigner ailleurs le retire à qui l'avait.
     * C'est annoncé plutôt que fait en silence — cette commande décide qui
     * garde la main sur la boutique.
     */
    case 'super':
        if ($email === null) {
            $sortie('Adresse manquante : php bin/admin.php super <e-mail>', 1);
        }

        $customer = Database::first(
            'SELECT id, name, email, status, is_super_admin FROM customers WHERE email = ?',
            [$email]
        );

        if ($customer === null) {
            $sortie("Aucun compte pour « {$email} ».", 1);
        }

        if ((int) $customer['is_super_admin'] === 1) {
            $sortie("{$email} est déjà super administrateur.");
        }

        if ($customer['status'] !== 'active') {
            $sortie("Refus : le compte « {$email} » n'est pas actif.", 1);
        }

        $ancien = Database::first('SELECT email FROM customers WHERE is_super_admin = 1');

        Database::run(
            'UPDATE customers SET is_super_admin = 0, updated_at = ? WHERE is_super_admin = 1',
            [Database::now()]
        );
        Database::run(
            'UPDATE customers SET is_admin = 1, is_super_admin = 1, updated_at = ? WHERE id = ?',
            [Database::now(), $customer['id']]
        );

        $sortie($ancien === null
            ? "{$customer['name']} ({$email}) est désormais super administrateur."
            : "{$customer['name']} ({$email}) est désormais super administrateur. {$ancien['email']} redevient administrateur ordinaire.");

        // no break — $sortie termine le script

    case 'promouvoir':
    case 'retrograder':
        if ($email === null) {
            $sortie("Adresse manquante : php bin/admin.php {$commande} <e-mail>", 1);
        }

        $customer = Database::first(
            'SELECT id, name, email, is_admin, is_super_admin FROM customers WHERE email = ?',
            [$email]
        );

        if ($customer === null) {
            // Le compte doit exister : on promeut un client, on ne crée pas un
            // administrateur à partir de rien. C'est ce qui garantit qu'il a un
            // mot de passe choisi par lui et jamais transmis.
            $sortie("Aucun compte pour « {$email} ». Inscrivez-vous d'abord sur le site.", 1);
        }

        $vers = $commande === 'promouvoir' ? 1 : 0;

        if ((int) $customer['is_admin'] === $vers) {
            $sortie($vers === 1
                ? "{$email} est déjà administrateur."
                : "{$email} n'est pas administrateur.");
        }

        if ($vers === 0) {
            // Le super administrateur ne se rétrograde pas : il se remplace.
            // Sans ce refus, la boutique garderait des administrateurs mais
            // plus personne pour distribuer les accès ni régler la boutique.
            if ((int) $customer['is_super_admin'] === 1) {
                $sortie("Refus : {$email} est super administrateur. Désignez d'abord son remplaçant : php bin/admin.php super <e-mail>", 1);
            }

            $restants = (int) Database::first(
                'SELECT COUNT(*) c FROM customers WHERE is_admin = 1 AND id <> ?',
                [$customer['id']]
            )['c'];

            // Se retirer soi-même le dernier accès fermerait l'administration à
            // tout le monde, sans autre recours que cette commande.
            if ($restants === 0) {
                $sortie('Refus : ce serait le dernier administrateur.', 1);
            }
        }

        Database::run(
            'UPDATE customers SET is_admin = ?, updated_at = ? WHERE id = ?',
            [$vers, Database::now(), $customer['id']]
        );

        $sortie($vers === 1
            ? "{$customer['name']} ({$email}) est désormais administrateur."
            : "{$customer['name']} ({$email}) n'est plus administrateur.");

        // no break — $sortie termine le script

    default:
        $sortie(<<<AIDE
        Gestion des administrateurs RCC

          php bin/admin.php lister
          php bin/admin.php promouvoir <e-mail>
          php bin/admin.php retrograder <e-mail>
          php bin/admin.php super <e-mail>

        Le compte doit déjà exister : inscrivez-vous sur le site, puis promouvez
        cette adresse. Le mot de passe reste celui que vous avez choisi.

        « super » désigne le seul compte qui puisse distribuer des accès et
        toucher aux réglages. Le rang est unique : le déplacer le retire à qui
        l'avait. Les autres administrateurs tiennent les commandes, le
        catalogue, les clients et la messagerie.
        AIDE);
}
