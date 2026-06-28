<?php
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$student_ids = $input['student_ids'] ?? [];

if (empty($student_ids) || !is_array($student_ids)) {
    echo json_encode(['error' => 'No students selected.']);
    exit();
}

try {
    $pdo->beginTransaction();

    $stmt_select = $pdo->prepare("SELECT user_id, batch, graduation_year FROM students WHERE user_id = ?");
    $stmt_update = $pdo->prepare("UPDATE students SET batch = ?, graduation_year = ? WHERE user_id = ?");

    $demotedCount = 0;

    foreach ($student_ids as $id) {
        $stmt_select->execute([$id]);
        $student = $stmt_select->fetch();

        if ($student) {
            $current_batch = $student['batch'];
            $current_grad_year = (int)$student['graduation_year'];

            // Parse batch string (e.g., "2021-2024")
            $new_batch = $current_batch;
            if (preg_match('/^(\d{4})-(\d{4})$/', $current_batch, $matches)) {
                $start_year = (int)$matches[1] + 1;
                $end_year = (int)$matches[2] + 1;
                $new_batch = $start_year . '-' . $end_year;
            } elseif (preg_match('/(\d{4})/', $current_batch, $matches)) {
                // If it just has a single year in it, like "Batch 2024", we increment it
                $year = (int)$matches[1] + 1;
                $new_batch = str_replace($matches[1], (string)$year, $current_batch);
            }

            $new_grad_year = $current_grad_year + 1;

            $stmt_update->execute([$new_batch, $new_grad_year, $id]);
            $demotedCount++;
        }
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => "Successfully demoted $demotedCount students."]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Demote Students Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error during demotion.']);
}
