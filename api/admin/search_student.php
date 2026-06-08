<?php
require_once '../../config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT sap_id, name FROM students WHERE name LIKE ? OR sap_id LIKE ? LIMIT 15");
    $searchTerm = "%$query%";
    $stmt->execute([$searchTerm, $searchTerm]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($results);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>
