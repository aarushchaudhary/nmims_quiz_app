<?php
/*
 * api/student/get_attempt_status.php
 * Fetches the can_resume status for a student's attempt.
 */
header('Content-Type: application/json');
session_start();
require_once '../../config/database.php';

// --- Authorization & Input Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4 || !isset($_GET['id'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized access.']));
}

$attempt_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

if (!$attempt_id) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid attempt ID.']));
}

try {
    $stmt = $pdo->prepare("SELECT quiz_id, can_resume FROM student_attempts WHERE id = ? AND student_id = ?");
    $stmt->execute([$attempt_id, $_SESSION['user_id']]);
    $result = $stmt->fetch();

    if ($result) {
        echo json_encode([
            'can_resume' => (bool)$result['can_resume'],
            'quiz_id' => $result['quiz_id']
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Attempt not found or you are not authorized to view it.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Get attempt status failed: " . $e->getMessage());
    echo json_encode(['error' => 'Database error.']);
}
