<?php
/*
 * api/student/finish_exam.php
 * Finalizes an exam, calculates score, and handles disqualification server-side.
 */
header('Content-Type: application/json');
session_start();
require_once '../../config/database.php';
require_once '../../config/authz.php';
require_once '../../config/grading.php';

// --- Authorization & Request Method Check ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4) {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized access.']));
}

$data = json_decode(file_get_contents('php://input'), true);

// SECURITY FIX #3: is_disqualified is never read from client input.
// Validate attempt_id as integer.
$attempt_id = filter_var($data['attempt_id'] ?? null, FILTER_VALIDATE_INT);
if ($attempt_id === false || !$attempt_id) {
    http_response_code(400);
    exit(json_encode(['error' => 'Missing attempt ID.']));
}

$student_id = (int) $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    // SECURITY FIX #3: Ownership check — attempt must belong to this student.
    require_own_attempt($pdo, $attempt_id, $student_id);

    // Already submitted (or graded when the quiz ended) is still a success for the student.
    finalize_attempt($pdo, $attempt_id);

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    error_log("Finish exam failed for attempt_id {$attempt_id}: " . $e->getMessage());
    echo json_encode(['error' => 'Database error while finalizing exam.']);
}
