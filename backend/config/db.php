<?php
// ========================================================
// RCC SNEAKERS — Configuration PDO MySQL & Headers CORS API
// ========================================================

// En-têtes HTTP pour API REST JSON et CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PATCH, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Traitement des requêtes d'option (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$host = '127.0.0.1';
$db   = 'rcc_sneakers';
$user = 'root';
$pass = ''; // Modifiable selon la configuration locale MySQL
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Si la connexion MySQL échoue, renvoyer une erreur JSON explicite
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Impossible de se connecter à la base de données MySQL: ' . $e->getMessage()
    ]);
    exit();
}
