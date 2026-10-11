<?php
/*
 * api/faculty/save_evaluation.php
 * Saves the score for a manually graded descriptive answer.
 */
header('Content-Type: application/json');
session_start();
require_once '../../config/database.php';
require_once '../../config/authz.php';

// --- Authorization & Request Method Check ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized access.']));
}

$data = json_decode(file_get_contents('php://input'), true);
$answer_id = $data['answer_id'] ?? null;
$score = $data['score'] ?? null;

if (!is_numeric($answer_id) || !is_numeric($score)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid input.']));
}

try {
    $pdo->beginTransaction();

    // 1. Get the old score and attempt ID from the answer.
    $stmt_old = $pdo->prepare('SELECT attempt_id, score_awarded FROM student_answers WHERE id = ?');
    $stmt_old->execute([$answer_id]);
    $answer_data = $stmt_old->fetch();
    if (!$answer_data) {
        $pdo->rollBack();
        json_fail(403, 'Not found or not authorized.');
    }
    $attempt_id = (int) $answer_data['attempt_id'];
    $old_score  = $answer_data['score_awarded'] ?? 0;

    // SECURITY FIX #7: Ownership check — attempt must belong to a quiz owned by this faculty.
    // SECURITY-TODO(#8): score bounds are not clamped here.
    $faculty_id = (int) $_SESSION['user_id'];
    require_owned_attempt_as_faculty($pdo, $attempt_id, $faculty_id);

    // 2. Update the score for this specific answer
    $stmt_update_answer = $pdo->prepare("UPDATE student_answers SET score_awarded = ? WHERE id = ?");
    $stmt_update_answer->execute([$score, $answer_id]);

    // 3. Calculate the difference and update the total score in the student_attempts table
    $score_difference = $score - $old_score;
    $stmt_update_total = $pdo->prepare("UPDATE student_attempts SET total_score = total_score + ? WHERE id = ?");
    $stmt_update_total->execute([$score_difference, $attempt_id]);

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    error_log("Save evaluation failed: " . $e->getMessage());
    echo json_encode(['error' => 'Database error.']);
}
