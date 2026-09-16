<?php

namespace Rcc\Controllers;

use Rcc\Auth;
use Rcc\Database;
use Rcc\Request;
use Rcc\Response;

/**
 * Favoris du client.
 *
 * Comme le panier, seuls des identifiants sont stockés : le catalogue vit dans
 * les modules du front. Y recopier le nom ou le prix d'une paire créerait deux
 * vérités qui divergeraient au premier changement de tarif.
 */
class FavoritesController
{
    private const TYPES = ['sneaker', 'jersey'];

    public function __construct(private Auth $auth)
    {
    }

    public function index(Request $request): Response
    {
        $id = $this->auth->id();

        if ($id === null) {
            return Response::unauthorized();
        }

        $rows = Database::run(
            'SELECT item_type, item_id, created_at
               FROM favorites
              WHERE customer_id = ?
              ORDER BY created_at DESC, id DESC',
            [$id]
        )->fetchAll();

        return Response::data([
            'favorites' => array_map(static fn (array $r) => [
                'item_type' => $r['item_type'],
                'item_id' => (int) $r['item_id'],
                'created_at' => $r['created_at'],
            ], $rows),
        ]);
    }

    /**
     * Bascule plutôt qu'un ajout et une suppression séparés : le bouton cœur
     * est un interrupteur, et un seul aller-retour évite que deux clics rapides
     * laissent l'interface et la base en désaccord.
     */
    public function toggle(Request $request): Response
    {
        $customerId = $this->auth->id();

        if ($customerId === null) {
            return Response::unauthorized();
        }

        $type = (string) $request->input('item_type', '');
        $itemId = $request->input('item_id');

        $fields = [];

        if (!in_array($type, self::TYPES, true)) {
            $fields['item_type'] = 'Type inconnu.';
        }

        if (!is_numeric($itemId) || (int) $itemId < 1) {
            $fields['item_id'] = 'Identifiant manquant.';
        }

        if ($fields !== []) {
            return Response::validation($fields);
        }

        $itemId = (int) $itemId;

        $existing = Database::first(
            'SELECT id FROM favorites WHERE customer_id = ? AND item_type = ? AND item_id = ?',
            [$customerId, $type, $itemId]
        );

        if ($existing !== null) {
            Database::run('DELETE FROM favorites WHERE id = ?', [$existing['id']]);

            return Response::data(['favorited' => false]);
        }

        // INSERT IGNORE : deux clics simultanés ne doivent pas produire une
        // erreur 500 sur la contrainte d'unicité.
        Database::run(
            'INSERT IGNORE INTO favorites (customer_id, item_type, item_id, created_at) VALUES (?, ?, ?, ?)',
            [$customerId, $type, $itemId, Database::now()]
        );

        return Response::data(['favorited' => true]);
    }
}
