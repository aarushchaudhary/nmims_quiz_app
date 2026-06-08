<?php
/*
 * api/student/get_detailed_results.php
 * Fetches a detailed, question-by-question breakdown of a student's exam attempt.
 */
header('Content-Type: application/json');
session_start();
require_once '../../config/database.php';

// --- Authorization & Input Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4 || !isset($_GET['attempt_id'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized access.']));
}

$attempt_id = filter_var($_GET['attempt_id'], FILTER_VALIDATE_INT);

try {
    $sql_questions = "SELECT q.id, q.question_text, q.question_type_id, sa.selected_option_ids, sa.answer_text 
                      FROM student_answers sa
                      JOIN questions q ON sa.question_id = q.id
                      WHERE sa.attempt_id = ?";
    $stmt_questions = $pdo->prepare($sql_questions);
    $stmt_questions->execute([$attempt_id]);
    $questions_data = $stmt_questions->fetchAll();

    $detailed_results = [];

    // 2. For each question, get all its options and the correct ones
    $stmt_options = $pdo->prepare("SELECT id, option_text, is_correct FROM options WHERE question_id = ?");

    foreach ($questions_data as $question) {
        $stmt_options->execute([$question['id']]);
        $all_options = $stmt_options->fetchAll();

        $detailed_results[] = [
            'question_text' => $question['question_text'],
            'question_type_id' => $question['question_type_id'],
            'student_selection' => json_decode($question['selected_option_ids'], true) ?? [],
            'answer_text' => $question['answer_text'],
            'options' => $all_options
        ];
    }

    echo json_encode($detailed_results);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Get detailed results failed: " . $e->getMessage());
    echo json_encode(['error' => 'Database error.']);
}
