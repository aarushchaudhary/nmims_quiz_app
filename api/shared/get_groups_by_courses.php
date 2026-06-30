<?php
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['course_ids']) || empty($_GET['course_ids'])) {
    echo json_encode(['classes' => [], 'batches' => []]);
    exit();
}

$course_ids = explode(',', $_GET['course_ids']);
$course_ids = array_values(array_filter(array_map('intval', $course_ids)));

if (empty($course_ids)) {
    echo json_encode(['classes' => [], 'batches' => []]);
    exit();
}

try {
    $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
    
    // Fetch classes for these courses
    $stmt = $pdo->prepare("SELECT id, name FROM classes WHERE course_id IN ($placeholders) ORDER BY name ASC");
    $stmt->execute($course_ids);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch batches for these classes
    $batches = [];
    if (!empty($classes)) {
        $class_ids = array_column($classes, 'id');
        $batch_placeholders = str_repeat('?,', count($class_ids) - 1) . '?';
        $b_stmt = $pdo->prepare("SELECT id, name, class_id FROM batches WHERE class_id IN ($batch_placeholders) ORDER BY name ASC");
        $b_stmt->execute($class_ids);
        $batches = $b_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'classes' => $classes,
        'batches' => $batches
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
