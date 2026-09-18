<?php

namespace Rcc\Admin;

use Rcc\AdminLog;
use Rcc\Database;
use Rcc\Mailer\Emails;
use Rcc\Mailer\Mailer;
use Rcc\Request;
use Rcc\Response;
use Throwable;

/**
 * Traitement des commandes.
 *
 * Le champ `status` existait sans que rien ne puisse le faire avancer : le
 * client voyait « en préparation » indéfiniment. C'est ici que la boutique
 * reprend la main.
 */
class OrdersController
{
    /**
     * Enchaînement autorisé. Une commande ne revient pas en arrière — une
     * commande livrée qui repasse « en préparation » ne veut rien dire, et
     * l'annulation après livraison relève du retour, pas du statut.
     *
     * @var array<string,array<int,string>>
     */
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'cancelled'],
        'delivered' => [],
        'cancelled' => [],
    ];

    private const LABELS = [
        'pending' => 'En préparation',
        'confirmed' => 'Confirmée',
        'shipped' => 'Expédiée',
        'delivered' => 'Livrée',
        'cancelled' => 'Annulée',
    ];

    public function __construct(private Mailer $mailer)
    {
    }

    /** @param array<string,mixed> $admin */
    public function index(Request $request, array $admin): Response
    {
        $where = [];
        $params = [];

        $status = (string) $request->input('status', '');

        if ($status !== '' && isset(self::LABELS[$status])) {
            $where[] = 'status = ?';
            $params[] = $status;
        }

        $search = trim((string) $request->input('search', ''));

        if ($search !== '') {
            // Une seule barre de recherche : le gérant tape ce qu'il a sous les
            // yeux — une référence sur un colis, un nom au téléphone.
            $where[] = '(reference LIKE ? OR contact_name LIKE ? OR contact_phone LIKE ? OR contact_email LIKE ?)';
            $motif = '%' . $search . '%';
            array_push($params, $motif, $motif, $motif, $motif);
        }

        foreach ([['from', '>='], ['to', '<=']] as [$champ, $operateur]) {
            $valeur = trim((string) $request->input($champ, ''));

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur)) {
                $where[] = "created_at {$operateur} ?";
                $params[] = $valeur . ($champ === 'from' ? ' 00:00:00' : ' 23:59:59');
            }
        }

        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $total = (int) Database::first("SELECT COUNT(*) c FROM orders{$clause}", $params)['c'];

        $perPage = min(100, max(5, (int) $request->input('per_page', 20)));
        $page = max(1, (int) $request->input('page', 1));
        $offset = ($page - 1) * $perPage;

        // LIMIT et OFFSET sont interpolés après avoir été forcés en entiers :
        // les placeholders PDO ne sont pas acceptés à cet endroit par MySQL.
        $rows = Database::run(
            "SELECT id, reference, status, payment_method, contact_name, contact_phone,
                    delivery_label, total_xof, created_at
               FROM orders{$clause}
              ORDER BY created_at DESC, id DESC
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        )->fetchAll();

        $ids = array_column($rows, 'id');
        $articles = $this->itemCounts($ids);

        return Response::data([
            'orders' => array_map(static fn (array $r) => [
                'reference' => $r['reference'],
                'status' => $r['status'],
                'status_label' => self::LABELS[$r['status']] ?? $r['status'],
                'payment_method' => $r['payment_method'],
                'contact_name' => $r['contact_name'],
                'contact_phone' => $r['contact_phone'],
                'delivery_label' => $r['delivery_label'],
                'total_xof' => (int) $r['total_xof'],
                'items_count' => $articles[(int) $r['id']] ?? 0,
                'created_at' => $r['created_at'],
            ], $rows),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'pages' => (int) ceil($total / $perPage),
            ],
            'statuses' => self::LABELS,
        ]);
    }

    /** @param array<string,mixed> $admin */
    public function show(Request $request, array $admin, string $reference): Response
    {
        $order = Database::first('SELECT * FROM orders WHERE reference = ?', [$reference]);

        if ($order === null) {
            return Response::notFound("Cette commande n'existe pas.");
        }

        $items = Database::run(
            'SELECT item_type, item_id, title, subtitle, size, unit_price_xof, qty, line_total_xof, image
               FROM order_items WHERE order_id = ? ORDER BY id',
            [$order['id']]
        )->fetchAll();

        return Response::data([
            'order' => [
                'reference' => $order['reference'],
                'status' => $order['status'],
                'status_label' => self::LABELS[$order['status']] ?? $order['status'],
                'next_statuses' => array_map(
                    static fn (string $s) => ['id' => $s, 'label' => self::LABELS[$s]],
                    self::TRANSITIONS[$order['status']] ?? []
                ),
                'payment_method' => $order['payment_method'],
                'payment_status' => $order['payment_status'],
                'contact_name' => $order['contact_name'],
                'contact_email' => $order['contact_email'],
                'contact_phone' => $order['contact_phone'],
                'delivery_label' => $order['delivery_label'],
                'delivery_address' => $order['delivery_address'],
                'subtotal_xof' => (int) $order['subtotal_xof'],
                'delivery_fee_xof' => (int) $order['delivery_fee_xof'],
                'total_xof' => (int) $order['total_xof'],
                'created_at' => $order['created_at'],
                'updated_at' => $order['updated_at'],
                'items' => array_map(static fn (array $i) => [
                    'item_type' => $i['item_type'],
                    'item_id' => (int) $i['item_id'],
                    'title' => $i['title'],
                    'subtitle' => $i['subtitle'],
                    'size' => $i['size'],
                    'unit_price_xof' => (int) $i['unit_price_xof'],
                    'qty' => (int) $i['qty'],
                    'line_total_xof' => (int) $i['line_total_xof'],
                    'image' => $i['image'],
                ], $items),
            ],
        ]);
    }

    /** @param array<string,mixed> $admin */
    public function updateStatus(Request $request, array $admin, string $reference): Response
    {
        $order = Database::first('SELECT * FROM orders WHERE reference = ?', [$reference]);

        if ($order === null) {
            return Response::notFound("Cette commande n'existe pas.");
        }

        $cible = (string) $request->input('status', '');
        $autorises = self::TRANSITIONS[$order['status']] ?? [];

        if (!in_array($cible, $autorises, true)) {
            return Response::validation(['status' => sprintf(
                'Une commande « %s » ne peut pas passer à « %s ».',
                self::LABELS[$order['status']] ?? $order['status'],
                self::LABELS[$cible] ?? $cible
            )]);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            // Une annulation rend le stock. Sans cela, chaque commande annulée
            // retirerait définitivement des paires de la vente.
            if ($cible === 'cancelled') {
                $this->restoreStock((int) $order['id']);
            }

            Database::run(
                'UPDATE orders SET status = ?, payment_status = ?, updated_at = ? WHERE id = ?',
                [
                    $cible,
                    // Le règlement se fait à la livraison : c'est le passage à
                    // « livrée » qui le constate.
                    $cible === 'delivered' ? 'paid' : $order['payment_status'],
                    Database::now(),
                    $order['id'],
                ]
            );

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }

        AdminLog::record($admin, 'order.status', $reference, sprintf(
            'statut %s → %s',
            self::LABELS[$order['status']] ?? $order['status'],
            self::LABELS[$cible] ?? $cible
        ));

        // Le client est prévenu : c'est tout l'intérêt d'un suivi. L'échec
        // d'envoi ne défait pas le changement de statut.
        $mail = Emails::orderStatus($order, $cible, self::LABELS[$cible]);
        $this->mailer->send($order['contact_email'], $order['contact_name'], $mail['subject'], $mail['html']);

        return Response::data([
            'status' => $cible,
            'status_label' => self::LABELS[$cible],
            'next_statuses' => array_map(
                static fn (string $s) => ['id' => $s, 'label' => self::LABELS[$s]],
                self::TRANSITIONS[$cible] ?? []
            ),
        ]);
    }

    // ------------------------------------------------------------- privé

    private function restoreStock(int $orderId): void
    {
        $items = Database::run(
            'SELECT item_type, item_id, size, qty FROM order_items WHERE order_id = ?',
            [$orderId]
        )->fetchAll();

        foreach ($items as $item) {
            [$table, $key] = $item['item_type'] === 'jersey'
                ? ['jersey_variants', 'jersey_id']
                : ['product_variants', 'product_id'];

            Database::run(
                "UPDATE {$table} SET stock = stock + ? WHERE {$key} = ? AND size = ?",
                [(int) $item['qty'], (int) $item['item_id'], $item['size']]
            );
        }
    }

    /**
     * Compte les articles de plusieurs commandes en une requête : une par ligne
     * ferait vingt allers-retours pour afficher une page de liste.
     *
     * @param  array<int,mixed> $ids
     * @return array<int,int>
     */
    private function itemCounts(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $rows = Database::run(
            "SELECT order_id, COALESCE(SUM(qty), 0) total FROM order_items
              WHERE order_id IN ({$placeholders}) GROUP BY order_id",
            array_map('intval', $ids)
        )->fetchAll();

        return array_map('intval', array_column($rows, 'total', 'order_id'));
    }
}
