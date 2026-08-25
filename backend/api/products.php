<?php
// ========================================================
// RCC SNEAKERS — API REST PHP : Produits & Catalogue
// ========================================================

require_once __DIR__ . '/../config/db.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : null;
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : null;
$gender = isset($_GET['gender']) ? trim($_GET['gender']) : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;

try {
    if ($slug) {
        // 1. Récupération d'un produit par son slug
        $stmt = $pdo->prepare("
            SELECT p.*, b.name AS brand_name, b.slug AS brand_slug, b.logo_url AS brand_logo
            FROM products p
            JOIN brands b ON p.brand_id = b.id
            WHERE p.slug = :slug
            LIMIT 1
        ");
        $stmt->execute(['slug' => $slug]);
        $product = $stmt->fetch();

        if (!$product) {
            http_response_code(404);
            echo json_encode(['error' => true, 'message' => 'Produit non trouvé']);
            exit();
        }

        // Images du produit
        $imgStmt = $pdo->prepare("SELECT id, url, is_primary FROM product_images WHERE product_id = :id ORDER BY display_order ASC");
        $imgStmt->execute(['id' => $product['id']]);
        $images = $imgStmt->fetchAll();

        // Tailles & stocks
        $sizeStmt = $pdo->prepare("SELECT id, size, stock FROM product_sizes WHERE product_id = :id ORDER BY CAST(size AS UNSIGNED) ASC");
        $sizeStmt->execute(['id' => $product['id']]);
        $sizes = $sizeStmt->fetchAll();

        $product['brand'] = [
            'id' => $product['brand_id'],
            'name' => $product['brand_name'],
            'slug' => $product['brand_slug'],
            'logoUrl' => $product['brand_logo']
        ];
        $product['images'] = $images;
        $product['sizes'] = $sizes;

        echo json_encode(['success' => true, 'data' => $product]);
        exit();
    }

    // 2. Récupération de la liste des produits avec filtres
    $sql = "
        SELECT p.*, b.name AS brand_name, b.slug AS brand_slug
        FROM products p
        JOIN brands b ON p.brand_id = b.id
        WHERE 1=1
    ";
    $params = [];

    if ($filter === 'drops') {
        $sql .= " AND p.is_new_drop = 1";
    }
    if ($gender) {
        $sql .= " AND p.gender = :gender";
        $params['gender'] = strtoupper($gender);
    }
    if ($search) {
        $sql .= " AND (p.name LIKE :search OR p.description LIKE :search OR b.name LIKE :search)";
        $params['search'] = '%' . $search . '%';
    }

    $sql .= " ORDER BY p.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    // Attacher les images et tailles pour chaque produit
    foreach ($products as &$prod) {
        $imgStmt = $pdo->prepare("SELECT id, url, is_primary FROM product_images WHERE product_id = :id ORDER BY display_order ASC");
        $imgStmt->execute(['id' => $prod['id']]);
        $prod['images'] = $imgStmt->fetchAll();

        $sizeStmt = $pdo->prepare("SELECT id, size, stock FROM product_sizes WHERE product_id = :id ORDER BY CAST(size AS UNSIGNED) ASC");
        $sizeStmt->execute(['id' => $prod['id']]);
        $prod['sizes'] = $sizeStmt->fetchAll();

        $prod['brand'] = [
            'id' => $prod['brand_id'],
            'name' => $prod['brand_name'],
            'slug' => $prod['brand_slug']
        ];
    }

    echo json_encode(['success' => true, 'data' => $products]);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => 'Erreur de requete SQL: ' . $e->getMessage()]);
}
