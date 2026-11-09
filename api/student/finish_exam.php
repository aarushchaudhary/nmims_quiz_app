<?php
/*
 * api/student/finish_exam.php
 * Finalizes an exam, calculates score, and handles disqualification server-side.
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

// SECURITY FIX #3: is_disqualified is never read from client input.
// Validate attempt_id as integer.
$attempt_id = filter_var($data['attempt_id'] ?? null, FILTER_VALIDATE_INT);
if ($attempt_id === false || !$attempt_id) {
    http_response_code(400);
    exit(json_encode(['error' => 'Missing attempt ID.']));
}

// Violation threshold — must match the client-side constant in assets/js/script.js.
const VIOLATION_LIMIT = 3;

$student_id = (int) $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    // SECURITY FIX #3: Ownership check — attempt must belong to this student.
    require_own_attempt($pdo, $attempt_id, $student_id);

    // Lock the attempt row: a second submit waits here, then sees submitted_at and stops.
    $stmt_lock = $pdo->prepare('SELECT submitted_at FROM student_attempts WHERE id = ? FOR UPDATE');
    $stmt_lock->execute([$attempt_id]);
    if ($stmt_lock->fetchColumn() !== null) {
        $pdo->rollBack();
        exit(json_encode(['success' => true]));
    }

    // Determine disqualification server-side by counting logged violations.
    $stmt_violations = $pdo->prepare(
        "SELECT COUNT(*) FROM event_logs
         WHERE attempt_id = ? AND user_id = ? AND event_type = 'Violation'"
    );
    $stmt_violations->execute([$attempt_id, $student_id]);
    $violation_count = (int) $stmt_violations->fetchColumn();

    $is_disqualified = ($violation_count >= VIOLATION_LIMIT);

    if ($is_disqualified) {
        // Disqualified: lock the attempt without grading.
        $sql = 'UPDATE student_attempts
                SET is_disqualified = 1, can_resume = 0, submitted_at = NOW()
                WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$attempt_id]);
    } else {
        // --- Handle Normal Exam Finish ---

        // The deadline is enforced in save_answer.php, so late answers never reach grading.

        // 0. Ensure all assigned questions exist in student_answers so missing ones
        //    are properly categorised.
        $stmt_attempt_info = $pdo->prepare(
            'SELECT questions_json FROM student_attempts WHERE id = ?'
        );
        $stmt_attempt_info->execute([$attempt_id]);
        $attempt_info = $stmt_attempt_info->fetch(PDO::FETCH_ASSOC);
        $assigned_questions = json_decode($attempt_info['questions_json'], true) ?? [];

        $stmt_answered_ids = $pdo->prepare(
            'SELECT question_id FROM student_answers WHERE attempt_id = ?'
        );
        $stmt_answered_ids->execute([$attempt_id]);
        $answered_ids = $stmt_answered_ids->fetchAll(PDO::FETCH_COLUMN, 0);

        $stmt_insert_blank = $pdo->prepare(
            "INSERT IGNORE INTO student_answers (attempt_id, question_id, selected_option_ids, answer_text, time_spent_seconds, is_correct)
             VALUES (?, ?, '[]', '', 0, null)"
        );

        foreach ($assigned_questions as $q) {
            if (!in_array($q['id'], $answered_ids)) {
                $stmt_insert_blank->execute([$attempt_id, $q['id']]);
            }
        }

        // 1. Grade the student's answers and calculate the score.
        $stmt_quiz = $pdo->prepare(
            'SELECT enable_negative_marking, negative_marks_mcq, negative_marks_msq
             FROM quizzes
             JOIN student_attempts ON quizzes.id = student_attempts.quiz_id
             WHERE student_attempts.id = ?'
        );
        $stmt_quiz->execute([$attempt_id]);
        $quiz_config = $stmt_quiz->fetch(PDO::FETCH_ASSOC);

        $stmt_answers = $pdo->prepare(
            'SELECT sa.id, sa.question_id, sa.selected_option_ids, q.question_type_id, q.points
             FROM student_answers sa
             JOIN questions q ON sa.question_id = q.id
             WHERE sa.attempt_id = ?'
        );
        $stmt_answers->execute([$attempt_id]);
        $student_answers = $stmt_answers->fetchAll();

        // Correct option ids for every answered question, in one query.
        $stmt_correct = $pdo->prepare(
            'SELECT o.question_id, o.id FROM options o
             JOIN student_answers sa ON sa.question_id = o.question_id
             WHERE sa.attempt_id = ? AND o.is_correct = 1'
        );
        $stmt_correct->execute([$attempt_id]);
        $correct_by_question = $stmt_correct->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);

        $total_score = 0;
        $stmt_update_answer = $pdo->prepare(
            'UPDATE student_answers SET is_correct = ? WHERE id = ?'
        );

        foreach ($student_answers as $answer) {
            $question_id     = $answer['question_id'];
            $selected_ids    = json_decode($answer['selected_option_ids'], true) ?? [];
            $is_correct_value = null;

            if ($answer['question_type_id'] == 1 || $answer['question_type_id'] == 2) {
                $correct_ids = $correct_by_question[$question_id] ?? [];
                sort($selected_ids);
                sort($correct_ids);

                if (empty($selected_ids)) {
                    $is_correct_value = null; // Unanswered
                } elseif ($selected_ids == $correct_ids) {
                    $is_correct_value = 1;
                    $total_score += $answer['points'];
                } else {
                    if ($answer['question_type_id'] == 2) {
                        $correct_selections = array_intersect($selected_ids, $correct_ids);

                        // Partial credit only if every picked option is correct; one wrong pick makes it wrong.
                        if (!empty($correct_selections) && !array_diff($selected_ids, $correct_ids)) {
                            $is_correct_value = 2; // Partially correct
                            $partial_points   = ($answer['points'] / count($correct_ids)) * count($correct_selections);
                            $total_score     += $partial_points;
                        } else {
                            $is_correct_value = 0; // Incorrect
                            if ($quiz_config['enable_negative_marking']) {
                                $total_score -= $quiz_config['negative_marks_msq'];
                            }
                        }
                    } else {
                        // MCQ
                        $is_correct_value = 0; // Incorrect
                        if ($quiz_config['enable_negative_marking']) {
                            $total_score -= $quiz_config['negative_marks_mcq'];
                        }
                    }
                }
            } elseif ($answer['question_type_id'] == 3) {
                $is_correct_value = 3; // Descriptive — to be evaluated
            }
            $stmt_update_answer->execute([$is_correct_value, $answer['id']]);
        }

        // 2. Update the final status of the student's attempt.
        $sql_update_attempt = 'UPDATE student_attempts SET
                                   total_score    = ?,
                                   submitted_at   = NOW(),
                                   is_disqualified = 0,
                                   can_resume     = 0
                               WHERE id = ?';
        $stmt_update = $pdo->prepare($sql_update_attempt);
        $stmt_update->execute([$total_score, $attempt_id]);
    }

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
