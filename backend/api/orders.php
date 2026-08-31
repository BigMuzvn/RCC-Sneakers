<?php
// ========================================================
// RCC SNEAKERS — API REST PHP : Passation & Traitement des Commandes
// ========================================================

require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => true, 'message' => 'Méthode non autorisée. Seul POST est accepté.']);
    exit();
}

// Lecture du corps de la requête JSON
$inputData = file_get_contents('php://input');
$data = json_decode($inputData, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => 'Données JSON invalides ou manquantes.']);
    exit();
}

// Extraction des données de commande
$customerName    = isset($data['customerName']) ? trim($data['customerName']) : '';
$customerEmail   = isset($data['customerEmail']) ? trim($data['customerEmail']) : '';
$customerPhone   = isset($data['customerPhone']) ? trim($data['customerPhone']) : '';
$deliveryAddress = isset($data['deliveryAddress']) ? trim($data['deliveryAddress']) : '';
$paymentMethod   = isset($data['paymentMethod']) ? trim($data['paymentMethod']) : 'WAVE';
$items           = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];

// Validation minimale des champs
if (empty($customerName) || empty($customerPhone) || empty($deliveryAddress) || empty($items)) {
    http_response_code(422);
    echo json_encode(['error' => true, 'message' => 'Veuillez remplir tous les champs obligatoires (Nom, Téléphone, Adresse et Panier).']);
    exit();
}

try {
    // Début de la transaction SQL atomique
    $pdo->beginTransaction();

    // 1. Calcul du montant total de la commande
    $totalAmount = 0;
    foreach ($items as $item) {
        $price = isset($item['price']) ? (int)$item['price'] : 0;
        $qty   = isset($item['quantity']) ? (int)$item['quantity'] : 1;
        $totalAmount += ($price * $qty);
    }

    // 2. Génération d'un numéro de commande unique
    $orderNumber = 'RCC-' . date('Y') . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

    // 3. Insertion de la commande principale dans la table orders
    $stmtOrder = $pdo->prepare("
        INSERT INTO orders (order_number, customer_name, customer_email, customer_phone, delivery_address, total_amount, payment_method, status, created_at)
        VALUES (:order_number, :customer_name, :customer_email, :customer_phone, :delivery_address, :total_amount, :payment_method, 'PAID', NOW())
    ");
    $stmtOrder->execute([
        'order_number'    => $orderNumber,
        'customer_name'   => $customerName,
        'customer_email'  => $customerEmail,
        'customer_phone'  => $customerPhone,
        'delivery_address' => $deliveryAddress,
        'total_amount'    => $totalAmount,
        'payment_method'  => $paymentMethod,
    ]);

    $orderId = $pdo->lastInsertId();

    // 4. Insertion des items & décrémentation des stocks par pointure
    $stmtItem = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, product_name, size, price, quantity)
        VALUES (:order_id, :product_id, :product_name, :size, :price, :quantity)
    ");

    $stmtStock = $pdo->prepare("
        UPDATE product_sizes
        SET stock = GREATEST(0, stock - :qty)
        WHERE product_id = :product_id AND size = :size
    ");

    foreach ($items as $item) {
        $productId   = isset($item['productId']) ? (int)$item['productId'] : null;
        $productName = isset($item['name']) ? trim($item['name']) : 'Sneaker RCC';
        $size        = isset($item['size']) ? trim($item['size']) : '42';
        $price       = isset($item['price']) ? (int)$item['price'] : 0;
        $quantity    = isset($item['quantity']) ? (int)$item['quantity'] : 1;

        // Ajouter l'item de commande
        $stmtItem->execute([
            'order_id'     => $orderId,
            'product_id'   => $productId,
            'product_name' => $productName,
            'size'         => $size,
            'price'        => $price,
            'quantity'     => $quantity
        ]);

        // Décrémenter le stock dans la base de données
        if ($productId) {
            $stmtStock->execute([
                'qty'        => $quantity,
                'product_id' => $productId,
                'size'       => $size
            ]);
        }
    }

    // Validation définitive de la transaction
    $pdo->commit();

    // Réponse JSON de succès
    http_response_code(201);
    echo json_encode([
        'success'      => true,
        'message'      => 'Commande enregistrée avec succès !',
        'orderNumber'  => $orderNumber,
        'totalAmount'  => $totalAmount,
        'paymentMethod' => $paymentMethod,
        'createdAt'    => date('d/m/Y à H:i')
    ]);

} catch (\Exception $e) {
    // Annulation de la transaction en cas d'erreur
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'error'   => true,
        'message' => 'Erreur lors du traitement de la commande : ' . $e->getMessage()
    ]);
}
