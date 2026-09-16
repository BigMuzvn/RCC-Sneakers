<?php

/**
 * Charge le catalogue en base depuis migrations/catalogue.json.
 *
 *   php migrations/seed.php           base de travail
 *   php migrations/seed.php --test    base de test
 *
 * Le JSON est exporté depuis les modules TypeScript du front, qui restent la
 * source d'écriture tant qu'il n'y a pas d'interface d'administration. Le
 * semoir est **idempotent** : il met à jour les fiches existantes et n'écrase
 * jamais le stock d'une variante déjà connue — sinon rejouer le semoir après
 * quelques ventes ressusciterait des paires déjà vendues.
 */

require dirname(__DIR__) . '/autoload.php';

use Rcc\Config;

Config::load();

$useTest = in_array('--test', $argv, true);
$db = Config::get($useTest ? 'db_test' : 'db');

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']),
    $db['user'],
    $db['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

echo "Base : {$db['name']}\n";

$catalogue = json_decode(file_get_contents(__DIR__ . '/catalogue.json'), true);
$now = gmdate('Y-m-d H:i:s');

/** Insère ou met à jour, puis renvoie le nombre de variantes créées. */
$seedVariants = function (string $table, string $key, int $id, array $variants) use ($pdo): int {
    $created = 0;

    foreach ($variants as $variant) {
        $stmt = $pdo->prepare(
            "INSERT INTO {$table} ({$key}, size, stock) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE size = size"   // ne touche pas au stock existant
        );
        $stmt->execute([$id, (string) $variant['size'], (int) $variant['stock']]);
        $created += $stmt->rowCount() > 0 ? 1 : 0;
    }

    return $created;
};

// ------------------------------------------------------------------ sneakers

$variants = 0;

foreach ($catalogue['products'] as $p) {
    $pdo->prepare(
        'INSERT INTO products
            (id, slug, brand, model, sku, category, gender, colorway, description,
             price_xof, old_price_xof, is_new_drop, image, accent, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            slug = VALUES(slug), brand = VALUES(brand), model = VALUES(model), sku = VALUES(sku),
            category = VALUES(category), gender = VALUES(gender), colorway = VALUES(colorway),
            description = VALUES(description), price_xof = VALUES(price_xof),
            old_price_xof = VALUES(old_price_xof), is_new_drop = VALUES(is_new_drop),
            image = VALUES(image), accent = VALUES(accent), updated_at = VALUES(updated_at)'
    )->execute([
        $p['id'], $p['slug'], $p['brand'], $p['model'], $p['sku'], $p['category'], $p['gender'],
        $p['colorway'], $p['description'], $p['price_xof'], $p['old_price_xof'],
        $p['is_new_drop'] ? 1 : 0, $p['image'], $p['accent'], $now, $now,
    ]);

    $variants += $seedVariants('product_variants', 'product_id', (int) $p['id'], $p['variants']);
}

printf("  %d sneakers, %d variantes créées\n", count($catalogue['products']), $variants);

// ------------------------------------------------------------------ maillots

$variants = 0;

foreach ($catalogue['jerseys'] as $j) {
    $pdo->prepare(
        'INSERT INTO jerseys
            (id, slug, club, league, brand, kit, season, colorway,
             price_xof, old_price_xof, is_new_drop, image, accent, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            slug = VALUES(slug), club = VALUES(club), league = VALUES(league), brand = VALUES(brand),
            kit = VALUES(kit), season = VALUES(season), colorway = VALUES(colorway),
            price_xof = VALUES(price_xof), old_price_xof = VALUES(old_price_xof),
            is_new_drop = VALUES(is_new_drop), image = VALUES(image), accent = VALUES(accent),
            updated_at = VALUES(updated_at)'
    )->execute([
        $j['id'], $j['slug'], $j['club'], $j['league'], $j['brand'], $j['kit'], $j['season'],
        $j['colorway'], $j['price_xof'], $j['old_price_xof'], $j['is_new_drop'] ? 1 : 0,
        $j['image'], $j['accent'], $now, $now,
    ]);

    $variants += $seedVariants('jersey_variants', 'jersey_id', (int) $j['id'], $j['variants']);
}

printf("  %d maillots, %d variantes créées\n", count($catalogue['jerseys']), $variants);

// ---------------------------------------------------------- zones de livraison

/**
 * Tarifs de remplacement, à confirmer auprès des coursiers avant la mise en
 * ligne. Ils sont en base pour que cette confirmation soit une mise à jour de
 * données et non un redéploiement.
 */
$zones = [
    ['cotonou', 'Cotonou', 'sous 24 h', 1000, 1],
    ['nokoue', 'Grand Nokoué', 'sous 48 h', 1500, 2],
    ['benin', 'Reste du Bénin', 'sous 72 h', 2500, 3],
];

foreach ($zones as [$id, $label, $delay, $fee, $position]) {
    $pdo->prepare(
        'INSERT INTO delivery_zones (id, label, delay_label, fee_xof, position)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE label = VALUES(label), delay_label = VALUES(delay_label),
                                 position = VALUES(position)'   // le tarif n'est pas écrasé
    )->execute([$id, $label, $delay, $fee, $position]);
}

printf("  %d zones de livraison\n", count($zones));

$total = $pdo->query('SELECT SUM(stock) FROM product_variants')->fetchColumn()
    + $pdo->query('SELECT SUM(stock) FROM jersey_variants')->fetchColumn();

printf("Stock total en base : %d pièces\n", $total);
