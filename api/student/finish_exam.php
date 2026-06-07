<?php
/*
 * api/student/finish_exam.php
 * Finalizes an exam, calculates score, and handles disqualification correctly.
 */
header('Content-Type: application/json');
session_start();
require_once '../../config/database.php';

// --- Authorization & Request Method Check ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4) {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized access.']));
}

$data = json_decode(file_get_contents('php://input'), true);
$attempt_id = $data['attempt_id'] ?? null;
$is_disqualified = $data['is_disqualified'] ?? false;

if (!$attempt_id) {
    http_response_code(400);
    exit(json_encode(['error' => 'Missing attempt ID.']));
}

try {
    $pdo->beginTransaction();

    // --- **CRITICAL FIX:** Handle disqualification separately from normal submission ---
    if ($is_disqualified) {
        // If a student is disqualified, we ONLY lock their attempt.
        // We set a submission time to mark the end, but do not calculate a score.
        $sql = "UPDATE student_attempts 
                SET is_disqualified = 1, can_resume = 0, submitted_at = NOW() 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$attempt_id]);
    } else {
        // --- Handle Normal Exam Finish ---
        // 1. Grade the student's answers and calculate the score
        // Fetch Quiz Config for negative marking
        $stmt_quiz = $pdo->prepare("SELECT enable_negative_marking, negative_marks_mcq, negative_marks_msq FROM quizzes JOIN student_attempts ON quizzes.id = student_attempts.quiz_id WHERE student_attempts.id = ?");
        $stmt_quiz->execute([$attempt_id]);
        $quiz_config = $stmt_quiz->fetch(PDO::FETCH_ASSOC);

        $stmt_answers = $pdo->prepare(
            "SELECT sa.id, sa.question_id, sa.selected_option_ids, q.question_type_id, q.points 
             FROM student_answers sa 
             JOIN questions q ON sa.question_id = q.id 
             WHERE sa.attempt_id = ?"
        );
        $stmt_answers->execute([$attempt_id]);
        $student_answers = $stmt_answers->fetchAll();

        $total_score = 0;
        $stmt_update_answer = $pdo->prepare("UPDATE student_answers SET is_correct = ? WHERE id = ?");

        foreach ($student_answers as $answer) {
            $question_id = $answer['question_id'];
            $selected_ids = json_decode($answer['selected_option_ids'], true) ?? [];
            $is_correct_value = null;

            if ($answer['question_type_id'] == 1 || $answer['question_type_id'] == 2) {
                $stmt_correct = $pdo->prepare("SELECT id FROM options WHERE question_id = ? AND is_correct = 1");
                $stmt_correct->execute([$question_id]);
                $correct_ids = $stmt_correct->fetchAll(PDO::FETCH_COLUMN, 0);
                sort($selected_ids);
                sort($correct_ids);
                if (!empty($selected_ids) && $selected_ids == $correct_ids) {
                    $is_correct_value = 1;
                    $total_score += $answer['points'];
                } else if (!empty($selected_ids)) {
                    $is_correct_value = 0;
                    // Apply Negative Marking for incorrect answers
                    if ($quiz_config['enable_negative_marking']) {
                        if ($answer['question_type_id'] == 1) {
                            $total_score -= $quiz_config['negative_marks_mcq'];
                        } else if ($answer['question_type_id'] == 2) {
                            $total_score -= $quiz_config['negative_marks_msq'];
                        }
                    }
                } else {
                    $is_correct_value = 0; // Unanswered
                }
            }
            $stmt_update_answer->execute([$is_correct_value, $answer['id']]);
        }

        // 2. Update the final status of the student's attempt.
        $sql_update_attempt = "UPDATE student_attempts SET 
                                    total_score = ?, 
                                    submitted_at = NOW(), 
                                    is_disqualified = 0,
                                    can_resume = 0
                               WHERE id = ?";
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
