<?php

namespace Rcc\Controllers;

use Rcc\Database;
use Rcc\Request;
use Rcc\Response;

/**
 * Lecture du catalogue.
 *
 * La forme renvoyée reproduit exactement celle des modules `src/data/` du
 * front : le jour où les composants passeront de l'import au `fetch`, il n'y
 * aura aucune couche de correspondance à écrire.
 *
 * `image` est un **nom de fichier**, pas une URL : côté navigateur les assets
 * sont des empreintes de build qui changent à chaque compilation, le serveur ne
 * peut pas les connaître.
 */
class CatalogueController
{
    public function products(Request $request): Response
    {
        $rows = Database::run(
            'SELECT id, slug, brand, model, sku, category, gender, colorway, description,
                    price_xof, old_price_xof, is_new_drop, image, accent
               FROM products WHERE is_active = 1 ORDER BY id'
        )->fetchAll();

        $variants = $this->variantsBy('product_variants', 'product_id');

        return Response::data([
            'products' => array_map(static fn (array $r) => [
                'id' => (int) $r['id'],
                'slug' => $r['slug'],
                'brand' => $r['brand'],
                'model' => $r['model'],
                'sku' => $r['sku'],
                'category' => $r['category'],
                'gender' => $r['gender'],
                'colorway' => $r['colorway'],
                'description' => $r['description'],
                'price_xof' => (int) $r['price_xof'],
                'old_price_xof' => $r['old_price_xof'] === null ? null : (int) $r['old_price_xof'],
                'is_new_drop' => (bool) $r['is_new_drop'],
                'image' => $r['image'],
                'accent' => $r['accent'],
                'variants' => $variants[(int) $r['id']] ?? [],
            ], $rows),
        ]);
    }

    public function jerseys(Request $request): Response
    {
        $rows = Database::run(
            'SELECT id, slug, club, league, brand, kit, season, colorway,
                    price_xof, old_price_xof, is_new_drop, image, accent
               FROM jerseys WHERE is_active = 1 ORDER BY id'
        )->fetchAll();

        $variants = $this->variantsBy('jersey_variants', 'jersey_id');

        return Response::data([
            'jerseys' => array_map(static fn (array $r) => [
                'id' => (int) $r['id'],
                'slug' => $r['slug'],
                'club' => $r['club'],
                'league' => $r['league'],
                'brand' => $r['brand'],
                'kit' => $r['kit'],
                'season' => $r['season'],
                'colorway' => $r['colorway'],
                'price_xof' => (int) $r['price_xof'],
                'old_price_xof' => $r['old_price_xof'] === null ? null : (int) $r['old_price_xof'],
                'is_new_drop' => (bool) $r['is_new_drop'],
                'image' => $r['image'],
                'accent' => $r['accent'],
                'variants' => $variants[(int) $r['id']] ?? [],
            ], $rows),
        ]);
    }

    /**
     * Zones et tarifs de livraison.
     *
     * Le tunnel de commande les lit ici plutôt que de les porter en dur : le
     * jour où les tarifs réels des coursiers seront connus, c'est une mise à
     * jour de données. Et le montant appliqué reste celui de la base, quoi que
     * le navigateur envoie.
     */
    public function deliveryZones(Request $request): Response
    {
        $rows = Database::run(
            'SELECT id, label, delay_label, fee_xof FROM delivery_zones
              WHERE is_active = 1 ORDER BY position, id'
        )->fetchAll();

        return Response::data([
            'zones' => array_map(static fn (array $r) => [
                'id' => $r['id'],
                'label' => $r['label'],
                'delay_label' => $r['delay_label'],
                'fee_xof' => (int) $r['fee_xof'],
            ], $rows),
        ]);
    }

    /**
     * Une seule requête pour toutes les variantes, regroupées en mémoire :
     * une requête par article ferait 32 allers-retours pour afficher la
     * boutique.
     *
     * @return array<int,array<int,array{size:string,stock:int}>>
     */
    private function variantsBy(string $table, string $key): array
    {
        $rows = Database::run(
            "SELECT {$key} AS owner, size, stock FROM {$table} ORDER BY id"
        )->fetchAll();

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(int) $row['owner']][] = [
                'size' => $row['size'],
                'stock' => (int) $row['stock'],
            ];
        }

        return $grouped;
    }
}
