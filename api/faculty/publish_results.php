<?php
/*
 * api/faculty/publish_results.php
 * Updates the show_results_immediately flag to true for a specific quiz.
 */
header('Content-Type: application/json');
session_start();
require_once '../../config/database.php';

// --- Authorization & Input Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['quiz_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Quiz ID is required.']);
    exit();
}

$quiz_id = filter_var($input['quiz_id'], FILTER_VALIDATE_INT);
$faculty_id = $_SESSION['user_id'];

try {
    // Ensure the faculty owns this quiz before updating
    $stmt_check = $pdo->prepare("SELECT id FROM quizzes WHERE id = ? AND faculty_id = ?");
    $stmt_check->execute([$quiz_id, $faculty_id]);
    
    if ($stmt_check->rowCount() === 0) {
        throw new Exception("Quiz not found or you do not have permission to modify it.");
    }

    $stmt_update = $pdo->prepare("UPDATE quizzes SET show_results_immediately = 1 WHERE id = ? AND faculty_id = ?");
    $stmt_update->execute([$quiz_id, $faculty_id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
