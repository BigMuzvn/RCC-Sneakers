<?php

require dirname(__DIR__) . '/vendor/autoload.php';

// Les tests tournent sur rcc_sneakers_test et jamais sur la base de travail.
// La constante est lue par Rcc\Database pour choisir la connexion.
define('RCC_TESTING', true);

date_default_timezone_set('UTC');

// Les journaux d'erreur partent dans un fichier : la sortie de PHPUnit doit
// rester propre, sinon un vrai avertissement passe inaperçu au milieu du bruit.
@mkdir(dirname(__DIR__) . '/storage/logs', 0775, true);
ini_set('error_log', dirname(__DIR__) . '/storage/logs/test.log');
