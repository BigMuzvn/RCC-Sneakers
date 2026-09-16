<?php

/**
 * Chargement automatique PSR-4, sans Composer.
 *
 * Le dossier vendor/ ne contient que PHPUnit, une dépendance de développement,
 * et ne part donc pas sur l'hébergeur. S'appuyer sur vendor/autoload.php en
 * production annulerait cette promesse : l'API n'a besoin que de src/.
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'Rcc\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});
