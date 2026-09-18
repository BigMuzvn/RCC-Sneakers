<?php

/**
 * Gabarit de configuration. Copier en config.php et renseigner.
 * config.php est git-ignoré : aucun secret ne doit entrer dans le dépôt.
 */

return [
    'app' => [
        'env'   => 'local',                     // 'local' ou 'production'
        'debug' => true,                        // false en production : masque les traces
        'url'   => 'http://localhost:5173',     // origine du front, pour les liens des e-mails

        // Adresses des relais dont on accepte l'en-tête X-Forwarded-For.
        // Laisser vide sur un hébergement ordinaire : c'est alors la seule
        // adresse de la connexion TCP qui compte, et elle ne se falsifie pas.
        // À ne remplir que derrière un Cloudflare ou un répartiteur de charge,
        // avec ses adresses à lui — sinon n'importe qui se donne l'IP qu'il
        // veut et contourne toutes les limitations de débit du site.
        'trusted_proxies' => [],

        // Origines autorisées à appeler l'API depuis un autre domaine, avec le
        // cookie de session. En développement, le front tourne sur le serveur
        // Vite et l'API sur un autre port : il faut l'y déclarer.
        //
        // **À vider en production** : le front et l'API partagent alors le
        // domaine et n'ont besoin d'aucune autorisation croisée. Une origine
        // absente de cette liste n'obtient rien — c'est elle, et non
        // l'environnement déclaré, qui décide.
        'cors_origins' => ['http://localhost:5173'],
    ],

    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'rcc_sneakers',
        'user' => 'root',
        'pass' => '',
    ],

    // Base dédiée aux tests. Elle est vidée à chaque exécution : jamais la base de travail.
    'db_test' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'rcc_sneakers_test',
        'user' => 'root',
        'pass' => '',
    ],

    'auth' => [
        // Coût bcrypt. 12 est un équilibre raisonnable en 2026 ; les tests le
        // ramènent à 4 pour rester rapides.
        //
        // Volontairement bcrypt et non Argon2id : beaucoup de mutualisés
        // compilent PHP sans sodium. Un hachage que l'hébergeur ne sait pas
        // relire enfermerait tous les clients dehors, sans message exploitable.
        'bcrypt_cost' => 12,
    ],

    'session' => [
        'name'          => 'rcc_session',
        'remember_days' => 30,
        'secure'        => false,               // true en production : cookie HTTPS uniquement
    ],

    'mail' => [
        'driver'     => 'brevo',                // 'brevo' pour envoyer, 'log' pour écrire dans storage/logs/mail.log
        'brevo_key'  => 'xkeysib-...',
        'from_email' => 'contact@exemple.com',  // DOIT être un expéditeur vérifié dans Brevo
        'from_name'  => 'RCC Sneakers',

        // Liste de contacts qui reçoit les inscrits à la lettre d'information.
        // Se lit dans Brevo, onglet Contacts > Listes. À 0, la synchronisation
        // est ignorée et les inscrits restent seulement en base.
        'brevo_list_id' => 0,
    ],
];
