<?php
/*
 * api/student/save_answer.php
 * Saves a student's answer for a single question to the database.
 * Includes ownership, submission-state, and question-set membership checks.
 */
header('Content-Type: application/json');
session_start();
require_once '../../config/database.php';
require_once '../../config/authz.php';

// --- Authorization & Request Method Check ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4) {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized access.']));
}

$data = json_decode(file_get_contents('php://input'), true);

// Validate attempt_id and question_id as integers (400 on failure)
$attempt_id  = filter_var($data['attempt_id']  ?? null, FILTER_VALIDATE_INT);
$question_id = filter_var($data['question_id'] ?? null, FILTER_VALIDATE_INT);

if ($attempt_id === false || $question_id === false || !$attempt_id || !$question_id) {
    http_response_code(400);
    exit(json_encode(['error' => 'Missing or invalid required data.']));
}

$selected_option_ids = $data['selected_option_ids'] ?? [];
$time_spent  = (int) ($data['time_spent'] ?? 0);
$answer_text = $data['answer_text'] ?? null;

// Validate selected_option_ids is an array of integers
if (!is_array($selected_option_ids)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid option data.']));
}
foreach ($selected_option_ids as $oid) {
    if (filter_var($oid, FILTER_VALIDATE_INT) === false) {
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid option data.']));
    }
}

$student_id = (int) $_SESSION['user_id'];

try {
    // SECURITY FIX #2: Ownership check — attempt must belong to this student.
    $attempt_info = require_own_attempt($pdo, $attempt_id, $student_id);

    // Reject if already submitted
    if ($attempt_info['submitted_at'] !== null) {
        http_response_code(403);
        exit(json_encode(['error' => 'This attempt has already been submitted.']));
    }

    // Check manual lock
    if ($attempt_info['is_manually_locked'] == 1) {
        http_response_code(403);
        exit(json_encode(['error' => 'Your exam session has been locked by the faculty.']));
    }

    // Check quiz is still in progress (status_id 3)
    $stmt_status = $pdo->prepare('SELECT status_id FROM quizzes WHERE id = ?');
    $stmt_status->execute([$attempt_info['quiz_id']]);
    $status_id = $stmt_status->fetchColumn();

    if ($status_id != 3) {
        http_response_code(403);
        exit(json_encode(['error' => 'The exam has been ended by the faculty. Your answer was not saved.']));
    }

    // SECURITY FIX #2: Validate question_id appears in this attempt's assigned question set.
    $assigned_questions = json_decode($attempt_info['questions_json'] ?? '[]', true) ?? [];
    $assigned_ids = array_column($assigned_questions, 'id');
    if (!in_array($question_id, $assigned_ids, true)) {
        http_response_code(403);
        exit(json_encode(['error' => 'Not found or not authorized.']));
    }

    // SECURITY FIX #2: Validate every selected option belongs to this question.
    if (!empty($selected_option_ids)) {
        $placeholders = implode(',', array_fill(0, count($selected_option_ids), '?'));
        $stmt_opts = $pdo->prepare(
            "SELECT COUNT(*) FROM options WHERE id IN ($placeholders) AND question_id = ?"
        );
        $stmt_opts->execute(array_merge(array_map('intval', $selected_option_ids), [$question_id]));
        $valid_count = (int) $stmt_opts->fetchColumn();
        if ($valid_count !== count($selected_option_ids)) {
            http_response_code(403);
            exit(json_encode(['error' => 'Not found or not authorized.']));
        }
    }

    $selected_option_ids_json = json_encode(array_map('intval', $selected_option_ids));

    // Upsert logic
    $stmt_check = $pdo->prepare('SELECT id FROM student_answers WHERE attempt_id = ? AND question_id = ?');
    $stmt_check->execute([$attempt_id, $question_id]);
    $existing_answer = $stmt_check->fetch();

    if ($existing_answer) {
        $sql = 'UPDATE student_answers SET selected_option_ids = ?, answer_text = ?, time_spent_seconds = time_spent_seconds + ? WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$selected_option_ids_json, $answer_text, $time_spent, $existing_answer['id']]);
    } else {
        $sql = 'INSERT INTO student_answers (attempt_id, question_id, selected_option_ids, answer_text, time_spent_seconds) VALUES (?, ?, ?, ?, ?)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$attempt_id, $question_id, $selected_option_ids_json, $answer_text, $time_spent]);
    }

    echo json_encode(['success' => true, 'message' => 'Answer saved.']);

} catch (Exception $e) {
    http_response_code(500);
    error_log('Save answer failed: ' . $e->getMessage());
    echo json_encode(['error' => 'Database error while saving answer.']);
}
