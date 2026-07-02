<?php
require_once '../../config/database.php';

if (!isset($_GET['course_id'])) {
    echo json_encode([]);
    exit;
}

$course_id = (int)$_GET['course_id'];
$stmt = $pdo->prepare("SELECT DISTINCT batch, graduation_year FROM students WHERE course_id = ? ORDER BY graduation_year DESC");
$stmt->execute([$course_id]);
$batches = $stmt->fetchAll();

echo json_encode($batches);
