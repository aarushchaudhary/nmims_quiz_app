<?php
/*
 * api/admin/delete_user.php
 * Handles the server-side logic for PERMANENTLY deleting a user account and all their data.
 */
header('Content-Type: application/json');
session_start();
require_once '../../config/database.php';

// --- Authorization & Request Method Check ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'Unauthorized access.']));
}

$data = json_decode(file_get_contents('php://input'), true);
$user_id_to_delete = $data['user_id'] ?? null;

if (!$user_id_to_delete) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Invalid user ID.']));
}

if ($user_id_to_delete == $_SESSION['user_id']) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'You cannot delete your own account.']));
}

try {
    $pdo->beginTransaction();

    // --- **NEW FIX:** Delete the user's records from the quiz_lobby first ---
    $pdo->prepare("DELETE FROM quiz_lobby WHERE student_id = ?")->execute([$user_id_to_delete]);

    // --- (The rest of the deletion logic remains the same) ---

    // Handle quizzes created by the user if they are a faculty member
    $stmt_quizzes = $pdo->prepare("SELECT id FROM quizzes WHERE faculty_id = ?");
    $stmt_quizzes->execute([$user_id_to_delete]);
    $quiz_ids = $stmt_quizzes->fetchAll(PDO::FETCH_COLUMN, 0);

    if (!empty($quiz_ids)) {
        $in_clause_quizzes = implode(',', array_fill(0, count($quiz_ids), '?'));
        
        $stmt_attempts_q = $pdo->prepare("SELECT id FROM student_attempts WHERE quiz_id IN ($in_clause_quizzes)");
        $stmt_attempts_q->execute($quiz_ids);
        $attempt_ids_from_quizzes = $stmt_attempts_q->fetchAll(PDO::FETCH_COLUMN, 0);

        if(!empty($attempt_ids_from_quizzes)) {
            $in_clause_attempts_q = implode(',', array_fill(0, count($attempt_ids_from_quizzes), '?'));
            $pdo->prepare("DELETE FROM event_logs WHERE attempt_id IN ($in_clause_attempts_q)")->execute($attempt_ids_from_quizzes);
            $pdo->prepare("DELETE FROM student_answers WHERE attempt_id IN ($in_clause_attempts_q)")->execute($attempt_ids_from_quizzes);
        }

        $stmt_questions_q = $pdo->prepare("SELECT id FROM questions WHERE quiz_id IN ($in_clause_quizzes)");
        $stmt_questions_q->execute($quiz_ids);
        $question_ids_from_quizzes = $stmt_questions_q->fetchAll(PDO::FETCH_COLUMN, 0);

        if(!empty($question_ids_from_quizzes)) {
            $in_clause_questions_q = implode(',', array_fill(0, count($question_ids_from_quizzes), '?'));
            $pdo->prepare("DELETE FROM options WHERE question_id IN ($in_clause_questions_q)")->execute($question_ids_from_quizzes);
        }
        
        $pdo->prepare("DELETE FROM student_attempts WHERE quiz_id IN ($in_clause_quizzes)")->execute($quiz_ids);
        $pdo->prepare("DELETE FROM questions WHERE quiz_id IN ($in_clause_quizzes)")->execute($quiz_ids);
        $pdo->prepare("DELETE FROM quiz_lobby WHERE quiz_id IN ($in_clause_quizzes)")->execute($quiz_ids);
        $pdo->prepare("DELETE FROM quizzes WHERE id IN ($in_clause_quizzes)")->execute($quiz_ids);
    }

    // Existing logic to delete student-specific data
    $stmt_attempts = $pdo->prepare("SELECT id FROM student_attempts WHERE student_id = ?");
    $stmt_attempts->execute([$user_id_to_delete]);
    $attempt_ids = $stmt_attempts->fetchAll(PDO::FETCH_COLUMN, 0);

    if (!empty($attempt_ids)) {
        $in_clause_attempts = implode(',', array_fill(0, count($attempt_ids), '?'));
        $pdo->prepare("DELETE FROM event_logs WHERE attempt_id IN ($in_clause_attempts)")->execute($attempt_ids);
        $pdo->prepare("DELETE FROM student_answers WHERE attempt_id IN ($in_clause_attempts)")->execute($attempt_ids);
        $pdo->prepare("DELETE FROM student_attempts WHERE id IN ($in_clause_attempts)")->execute($attempt_ids);
    }
    
    // Final Deletion from user and role tables
    $pdo->prepare("DELETE FROM students WHERE user_id = ?")->execute([$user_id_to_delete]);
    $pdo->prepare("DELETE FROM faculties WHERE user_id = ?")->execute([$user_id_to_delete]);
    $pdo->prepare("DELETE FROM placecom_officers WHERE user_id = ?")->execute([$user_id_to_delete]);
    $pdo->prepare("DELETE FROM heads WHERE user_id = ?")->execute([$user_id_to_delete]);
    $pdo->prepare("DELETE FROM admins WHERE user_id = ?")->execute([$user_id_to_delete]);
    
    $stmt_delete_user = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt_delete_user->execute([$user_id_to_delete]);

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    error_log("Force delete user failed for user_id {$user_id_to_delete}: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'A database error occurred. The user could not be deleted.']);
}
