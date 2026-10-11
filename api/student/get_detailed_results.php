<?php
/*
 * api/student/get_detailed_results.php
 * Fetches a detailed, question-by-question breakdown of a student's exam attempt.
 * Results are only returned when show_results_immediately = 1 on the quiz.
 */
header('Content-Type: application/json');
session_start();
require_once '../../config/database.php';
require_once '../../config/authz.php';

// --- Authorization & Input Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4 || !isset($_GET['attempt_id'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized access.']));
}

// Validate attempt_id as integer; 400 on false.
$attempt_id = filter_var($_GET['attempt_id'], FILTER_VALIDATE_INT);
if ($attempt_id === false || !$attempt_id) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid attempt ID.']));
}

$student_id = (int) $_SESSION['user_id'];

try {
    // SECURITY FIX #1: Ownership check — attempt must belong to this student.
    $attempt_row = require_own_attempt($pdo, $attempt_id, $student_id);

    // SECURITY FIX #1: Load the quiz and enforce show_results_immediately.
    $stmt_quiz = $pdo->prepare(
        'SELECT show_results_immediately, descriptive_published
         FROM quizzes WHERE id = ?'
    );
    $stmt_quiz->execute([$attempt_row['quiz_id']]);
    $quiz = $stmt_quiz->fetch();

    if (!$quiz || !$quiz['show_results_immediately']) {
        // Results not yet published — return a safe, minimal response.
        exit(json_encode(['results_pending' => true]));
    }

    $descriptive_published = (bool) $quiz['descriptive_published'];

    // Fetch questions and student answers for this attempt.
    $sql_questions = 'SELECT q.id, q.question_text, q.question_type_id, q.points,
                             sa.selected_option_ids, sa.answer_text, sa.is_correct, sa.score_awarded
                      FROM student_answers sa
                      JOIN questions q ON sa.question_id = q.id
                      WHERE sa.attempt_id = ?
                      ORDER BY q.id';
    $stmt_questions = $pdo->prepare($sql_questions);
    $stmt_questions->execute([$attempt_id]);
    $questions_data = $stmt_questions->fetchAll();

    $detailed_results = [];

    // For each question, fetch options.
    // SECURITY FIX #1: is_correct is included because show_results_immediately is verified above.
    $stmt_options = $pdo->prepare(
        'SELECT id, option_text, is_correct FROM options WHERE question_id = ?'
    );

    foreach ($questions_data as $question) {
        // Respect descriptive_published for descriptive questions.
        if ($question['question_type_id'] == 3 && !$descriptive_published) {
            continue;
        }

        $stmt_options->execute([$question['id']]);
        $all_options = $stmt_options->fetchAll();

        $detailed_results[] = [
            'question_text'     => $question['question_text'],
            'question_type_id'  => $question['question_type_id'],
            'points'            => (float) $question['points'],
            'is_correct'        => $question['is_correct'],    // 0=wrong, 1=correct, 2=partial, 3=to-eval, null=unanswered
            'score_awarded'     => $question['score_awarded'] !== null ? (float) $question['score_awarded'] : null,
            'student_selection' => json_decode($question['selected_option_ids'], true) ?? [],
            'answer_text'       => $question['answer_text'],
            'options'           => $all_options,
        ];
    }

    echo json_encode($detailed_results);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('Get detailed results failed: ' . $e->getMessage());
    echo json_encode(['error' => 'Database error.']);
}
