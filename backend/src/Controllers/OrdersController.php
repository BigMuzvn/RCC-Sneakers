<?php

namespace Rcc\Controllers;

use Rcc\Auth;
use Rcc\Database;
use Rcc\Mailer\Emails;
use Rcc\Mailer\Mailer;
use Rcc\Request;
use Rcc\Response;
use Rcc\Validator;
use Throwable;

/**
 * Commandes.
 *
 * Règle qui gouverne tout ce fichier : **rien de ce qui touche à l'argent ou au
 * stock ne vient du navigateur**. Le client envoie quels articles, quelle
 * taille, quelle quantité ; les prix, les frais de livraison et la
 * disponibilité sont relus en base. Faire autrement, c'est accepter des
 * commandes à 0 F et vendre trois fois la dernière paire.
 */
class OrdersController
{
    /** Au-delà, c'est une erreur de saisie ou une tentative d'épuiser le stock. */
    private const MAX_QTY = 20;

    /** Pas d'agrégateur de paiement branché : seule l'espèce est honorable. */
    private const PAYMENT_METHODS = ['cash'];

    public function __construct(
        private Auth $auth,
        private Mailer $mailer,
    ) {
    }

    public function store(Request $request): Response
    {
        $customer = $this->auth->customer();

        if ($customer === null) {
            return Response::unauthorized();
        }

        $v = new Validator($request->body);
        $name = $v->text('name', 2, 120);
        $email = $v->email('email');
        $phone = $v->phone('phone');
        $address = $v->text('address', 6, 255);

        $method = (string) $request->input('payment_method', '');

        if (!in_array($method, self::PAYMENT_METHODS, true)) {
            // Message explicite plutôt qu'un « champ invalide » : l'option
            // existe dans l'interface, le client doit comprendre pourquoi elle
            // ne passe pas.
            $v->errors(); // no-op, conserve l'ordre de lecture
            $fields = $v->fails() ? $v->errors() : [];
            $fields['payment_method'] = $method === 'online'
                ? "Le paiement en ligne n'est pas encore disponible. Choisissez le paiement à la livraison."
                : 'Mode de paiement inconnu.';

            return Response::validation($fields);
        }

        $zone = Database::first(
            'SELECT id, label, fee_xof FROM delivery_zones WHERE id = ? AND is_active = 1',
            [(string) $request->input('zone', '')]
        );

        if ($zone === null) {
            $fields = $v->errors();
            $fields['zone'] = 'Choisissez une zone de livraison.';

            return Response::validation($fields);
        }

        if ($v->fails()) {
            return Response::validation($v->errors());
        }

        $items = $request->input('items');

        if (!is_array($items) || $items === []) {
            return Response::validation(['items' => 'Votre panier est vide.']);
        }

        if (count($items) > 60) {
            return Response::validation(['items' => 'Trop d’articles dans une même commande.']);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $lines = [];
            $subtotal = 0;

            foreach ($items as $index => $item) {
                $line = $this->resolveLine($item);

                if (is_string($line)) {
                    $pdo->rollBack();

                    return Response::validation(['items' => $line, "items.{$index}" => $line]);
                }

                $subtotal += $line['line_total_xof'];
                $lines[] = $line;
            }

            $fee = (int) $zone['fee_xof'];
            $reference = $this->nextReference();

            Database::run(
                'INSERT INTO orders
                    (reference, customer_id, status, contact_name, contact_email, contact_phone,
                     delivery_zone, delivery_label, delivery_address,
                     payment_method, payment_status,
                     subtotal_xof, delivery_fee_xof, total_xof, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $reference, (int) $customer['id'], 'pending',
                    $name, $email, $phone,
                    $zone['id'], $zone['label'], $address,
                    $method, 'unpaid',
                    $subtotal, $fee, $subtotal + $fee,
                    Database::now(), Database::now(),
                ]
            );

            $orderId = (int) $pdo->lastInsertId();

            foreach ($lines as $line) {
                Database::run(
                    'INSERT INTO order_items
                        (order_id, item_type, item_id, title, subtitle, size,
                         unit_price_xof, qty, line_total_xof, image)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $orderId, $line['item_type'], $line['item_id'], $line['title'], $line['subtitle'],
                        $line['size'], $line['unit_price_xof'], $line['qty'], $line['line_total_xof'],
                        $line['image'],
                    ]
                );

                $this->decrementStock($line);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }

        $order = $this->loadOrder($orderId);

        // Résultat ignoré : une panne du service d'envoi ne doit pas faire
        // perdre une vente. L'échec est journalisé par le mailer.
        $mail = Emails::orderConfirmation($name, $order);
        $this->mailer->send($email, $name, $mail['subject'], $mail['html']);

        return Response::data(['order' => $order], 201);
    }

    public function index(Request $request): Response
    {
        $customerId = $this->auth->id();

        if ($customerId === null) {
            return Response::unauthorized();
        }

        $rows = Database::run(
            'SELECT id FROM orders WHERE customer_id = ? ORDER BY created_at DESC, id DESC',
            [$customerId]
        )->fetchAll();

        return Response::data([
            'orders' => array_map(fn (array $r) => $this->loadOrder((int) $r['id']), $rows),
        ]);
    }

    public function show(Request $request, string $reference): Response
    {
        $customerId = $this->auth->id();

        if ($customerId === null) {
            return Response::unauthorized();
        }

        // Le filtre sur customer_id est dans la requête, pas après : une
        // commande qui ne vous appartient pas ne doit pas être chargée du tout.
        $row = Database::first(
            'SELECT id FROM orders WHERE reference = ? AND customer_id = ?',
            [$reference, $customerId]
        );

        if ($row === null) {
            return Response::error('not_found', "Cette commande n'existe pas.", [], 404);
        }

        return Response::data(['order' => $this->loadOrder((int) $row['id'])]);
    }

    // -------------------------------------------------------------- privé

    /**
     * Relit une ligne de panier depuis le catalogue.
     *
     * @param  mixed $item
     * @return array<string,mixed>|string la ligne, ou le message d'erreur
     */
    private function resolveLine(mixed $item): array|string
    {
        if (!is_array($item)) {
            return 'Article invalide.';
        }

        $type = (string) ($item['item_type'] ?? '');
        $id = $item['item_id'] ?? null;
        $size = trim((string) ($item['size'] ?? ''));
        $qty = $item['qty'] ?? null;

        if (!in_array($type, ['sneaker', 'jersey'], true) || !is_numeric($id) || $size === '') {
            return 'Article invalide.';
        }

        if (!is_numeric($qty) || (int) $qty < 1 || (int) $qty > self::MAX_QTY) {
            return 'Quantité invalide.';
        }

        $id = (int) $id;
        $qty = (int) $qty;

        $article = $type === 'sneaker'
            ? Database::first(
                'SELECT p.brand, p.model, p.colorway, p.price_xof, p.image, v.stock
                   FROM products p
                   JOIN product_variants v ON v.product_id = p.id AND v.size = ?
                  WHERE p.id = ? AND p.is_active = 1',
                [$size, $id]
            )
            : Database::first(
                'SELECT j.club, j.kit, j.season, j.colorway, j.price_xof, j.image, v.stock
                   FROM jerseys j
                   JOIN jersey_variants v ON v.jersey_id = j.id AND v.size = ?
                  WHERE j.id = ? AND j.is_active = 1',
                [$size, $id]
            );

        if ($article === null) {
            return "Un article de votre panier n'est plus disponible dans cette taille.";
        }

        if ((int) $article['stock'] < $qty) {
            return sprintf(
                'Stock insuffisant en taille %s : il n’en reste que %d.',
                $size,
                (int) $article['stock']
            );
        }

        // Titre et sous-titre sont figés ici : c'est cette copie qui apparaîtra
        // sur la facture, même si l'article change de nom ou quitte le catalogue.
        $title = $type === 'sneaker'
            ? $article['brand'] . ' ' . $article['model']
            : $article['club'] . ' — ' . $article['kit'];

        $subtitle = $type === 'sneaker'
            ? (string) $article['colorway']
            : $article['season'] . ' · ' . $article['colorway'];

        $price = (int) $article['price_xof'];

        return [
            'item_type' => $type,
            'item_id' => $id,
            'title' => $title,
            'subtitle' => $subtitle,
            'size' => $size,
            'unit_price_xof' => $price,
            'qty' => $qty,
            'line_total_xof' => $price * $qty,
            'image' => $article['image'],
        ];
    }

    /** @param array<string,mixed> $line */
    private function decrementStock(array $line): void
    {
        [$table, $key] = $line['item_type'] === 'jersey'
            ? ['jersey_variants', 'jersey_id']
            : ['product_variants', 'product_id'];

        // La condition sur le stock est dans la requête : entre la lecture et
        // l'écriture, une autre commande a pu passer. Si zéro ligne n'est
        // touchée, la course est perdue et la transaction doit tomber.
        $stmt = Database::run(
            "UPDATE {$table} SET stock = stock - ? WHERE {$key} = ? AND size = ? AND stock >= ?",
            [$line['qty'], $line['item_id'], $line['size'], $line['qty']]
        );

        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException(sprintf(
                'stock épuisé pendant la commande : %s #%d taille %s',
                $line['item_type'],
                $line['item_id'],
                $line['size']
            ));
        }
    }

    /**
     * Référence lisible et non devinable : la date situe la commande, le
     * suffixe aléatoire évite qu'un client déduise le numéro d'un autre — et
     * donc le volume d'affaires de la boutique.
     */
    private function nextReference(): string
    {
        do {
            $reference = sprintf('RCC-%s-%s', gmdate('ymd'), strtoupper(bin2hex(random_bytes(2))));
        } while (Database::first('SELECT id FROM orders WHERE reference = ?', [$reference]) !== null);

        return $reference;
    }

    /** @return array<string,mixed> */
    private function loadOrder(int $id): array
    {
        $order = Database::first('SELECT * FROM orders WHERE id = ?', [$id]);

        $items = Database::run(
            'SELECT item_type, item_id, title, subtitle, size, unit_price_xof, qty, line_total_xof, image
               FROM order_items WHERE order_id = ? ORDER BY id',
            [$id]
        )->fetchAll();

        return [
            'reference' => $order['reference'],
            'status' => $order['status'],
            'payment_method' => $order['payment_method'],
            'payment_status' => $order['payment_status'],
            'contact_name' => $order['contact_name'],
            'contact_email' => $order['contact_email'],
            'contact_phone' => $order['contact_phone'],
            'delivery_zone' => $order['delivery_zone'],
            'delivery_label' => $order['delivery_label'],
            'delivery_address' => $order['delivery_address'],
            'subtotal_xof' => (int) $order['subtotal_xof'],
            'delivery_fee_xof' => (int) $order['delivery_fee_xof'],
            'total_xof' => (int) $order['total_xof'],
            'created_at' => $order['created_at'],
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
        ];
    }
}
