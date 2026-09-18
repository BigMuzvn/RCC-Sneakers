<?php

namespace Rcc\Admin;

use Rcc\Database;
use Rcc\Request;
use Rcc\Response;

/**
 * Vue d'ensemble : ce qu'on veut savoir en ouvrant l'administration le matin.
 */
class DashboardController
{
    /** @param array<string,mixed> $admin */
    public function overview(Request $request, array $admin): Response
    {
        $today = Database::now(-86400);
        $month = Database::now(-2592000);

        $parStatut = [];

        foreach (Database::run('SELECT status, COUNT(*) c FROM orders GROUP BY status')->fetchAll() as $row) {
            $parStatut[$row['status']] = (int) $row['c'];
        }

        return Response::data([
            'orders' => [
                // Ce qui appelle une action immédiate passe en premier.
                'to_handle' => ($parStatut['pending'] ?? 0) + ($parStatut['confirmed'] ?? 0),
                'by_status' => $parStatut,
                'last_24h' => $this->count('SELECT COUNT(*) c FROM orders WHERE created_at > ?', [$today]),
                'revenue_30d' => (int) (Database::first(
                    "SELECT COALESCE(SUM(total_xof), 0) t FROM orders
                      WHERE created_at > ? AND status <> 'cancelled'",
                    [$month]
                )['t'] ?? 0),
            ],

            'catalogue' => [
                'products' => $this->count('SELECT COUNT(*) c FROM products WHERE is_active = 1'),
                'jerseys' => $this->count('SELECT COUNT(*) c FROM jerseys WHERE is_active = 1'),
                'without_image' => $this->count(
                    'SELECT COUNT(*) c FROM products WHERE is_active = 1 AND (image IS NULL OR image = "")'
                ) + $this->count(
                    'SELECT COUNT(*) c FROM jerseys WHERE is_active = 1 AND (image IS NULL OR image = "")'
                ),
                // Une taille à zéro est une vente qu'on ne fera pas : c'est la
                // première chose à réapprovisionner.
                'out_of_stock_sizes' => $this->count('SELECT COUNT(*) c FROM product_variants WHERE stock = 0')
                    + $this->count('SELECT COUNT(*) c FROM jersey_variants WHERE stock = 0'),
                'units_in_stock' => (int) (Database::first(
                    'SELECT COALESCE(SUM(stock), 0) t FROM product_variants'
                )['t'] ?? 0) + (int) (Database::first(
                    'SELECT COALESCE(SUM(stock), 0) t FROM jersey_variants'
                )['t'] ?? 0),
            ],

            'customers' => [
                'total' => $this->count("SELECT COUNT(*) c FROM customers WHERE status = 'active'"),
                'unverified' => $this->count(
                    "SELECT COUNT(*) c FROM customers WHERE status = 'active' AND email_verified_at IS NULL"
                ),
                'last_30d' => $this->count('SELECT COUNT(*) c FROM customers WHERE created_at > ?', [$month]),
            ],

            'inbox' => [
                'new_messages' => $this->count("SELECT COUNT(*) c FROM contact_messages WHERE status = 'new'"),
                'subscribers' => $this->count("SELECT COUNT(*) c FROM newsletter_subscribers WHERE status = 'subscribed'"),
                // Inscrits que le prestataire n'a jamais reçus : à rejouer.
                'unsynced' => $this->count(
                    "SELECT COUNT(*) c FROM newsletter_subscribers WHERE status = 'subscribed' AND synced_at IS NULL"
                ),
            ],
        ]);
    }

    /** @param array<string,mixed> $admin */
    public function log(Request $request, array $admin): Response
    {
        $limit = min(200, max(10, (int) $request->input('limit', 50)));

        $rows = Database::run(
            'SELECT admin_email, action, target, detail, created_at
               FROM admin_log ORDER BY id DESC LIMIT ' . $limit
        )->fetchAll();

        return Response::data(['entries' => $rows]);
    }

    /** @param array<int|string,mixed> $params */
    private function count(string $sql, array $params = []): int
    {
        return (int) (Database::first($sql, $params)['c'] ?? 0);
    }
}
