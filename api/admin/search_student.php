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
$course_id = $_GET['course_id'] ?? null;
$class_id = $_GET['class_id'] ?? null;

if (strlen($query) < 2) {
    echo json_encode([]);
    exit();
}

try {
    $searchTerm = "%$query%";
    $params = [$searchTerm, $searchTerm];
    $sql = "SELECT sap_id, name FROM students WHERE (name LIKE ? OR sap_id LIKE ?)";
    
    if ($course_id) {
        $sql .= " AND course_id = ?";
        $params[] = $course_id;
    }
    
    if ($class_id) {
        $stmtClass = $pdo->prepare("SELECT sap_id_range_start, sap_id_range_end FROM classes WHERE id = ?");
        $stmtClass->execute([$class_id]);
        $classData = $stmtClass->fetch(PDO::FETCH_ASSOC);
        if ($classData) {
            $sql .= " AND sap_id >= ? AND sap_id <= ?";
            $params[] = $classData['sap_id_range_start'];
            $params[] = $classData['sap_id_range_end'];
        }
    }
    
    $sql .= " LIMIT 15";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($results);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>
