<?php
/*
 * api/student/fetch_exam_questions.php
 * Fetches questions for a student.
 * - Creates a new attempt with a unique, randomized question set if one doesn't exist.
 * - Resumes an existing attempt, preserving the original questions and timer.
 * - Adds a final confirmation question at the end of a new attempt.
 */
header('Content-Type: application/json');
session_start();
require_once '../../config/database.php';

// --- Authorization & Input Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4 || !isset($_GET['id'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized access.']));
}

$quiz_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
$student_user_id = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    // Check for an existing, active attempt to determine if we should resume or start new.
    $stmt_check = $pdo->prepare("SELECT * FROM student_attempts WHERE quiz_id = ? AND student_id = ?");
    $stmt_check->execute([$quiz_id, $student_user_id]);
    $attempt = $stmt_check->fetch(PDO::FETCH_ASSOC);

    // Get quiz configuration, especially the duration.
    $quiz_config_stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ?");
    $quiz_config_stmt->execute([$quiz_id]);
    $quiz_config = $quiz_config_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quiz_config) {
        throw new Exception("Quiz configuration not found.");
    }
    $duration_minutes = $quiz_config['duration_minutes'];

    $attempt_id = null;
    $remaining_seconds = 0;
    $questions_with_options = [];

    if ($attempt && !empty($attempt['questions_json'])) {
        // --- RESUME EXISTING ATTEMPT ---
        if ($attempt['submitted_at'] !== null) {
            throw new Exception("You have already completed and submitted this exam.");
        }
        if ($attempt['is_disqualified'] && !$attempt['can_resume']) {
            throw new Exception("Your exam session is locked due to proctoring violations. Please contact the faculty.");
        }

        // Use the existing attempt data
        $attempt_id = $attempt['id'];
        $questions_json = $attempt['questions_json'];
        $questions_with_options = json_decode($questions_json, true);

        // Calculate remaining time
        $end_time_db = new DateTime($attempt['attempt_end_time']);
        $now = new DateTime();
        $remaining_seconds = $end_time_db > $now ? $end_time_db->getTimestamp() - $now->getTimestamp() : 0;

    } else {
        // --- CREATE NEW ATTEMPT ---
        // If there's a partial attempt record without questions, use its ID. Otherwise, create a new one.
        $attempt_id = $attempt ? $attempt['id'] : null;

        if (!$attempt_id) {
            $sql_attempt = "INSERT INTO student_attempts (quiz_id, student_id) VALUES (?, ?)";
            $stmt_attempt = $pdo->prepare($sql_attempt);
            $stmt_attempt->execute([$quiz_id, $student_user_id]);
            $attempt_id = $pdo->lastInsertId();
        }

        // Fetch questions based on quiz difficulty configuration
        $final_questions = [];
        $difficulty_map = [
            1 => $quiz_config['config_easy_count'],
            2 => $quiz_config['config_medium_count'],
            3 => $quiz_config['config_hard_count']
        ];

        foreach ($difficulty_map as $diff_id => $count_needed) {
            if ($count_needed > 0) {
                $sql_q = "SELECT id, question_text, question_type_id, points FROM questions WHERE quiz_id = ? AND difficulty_id = ?";
                $stmt_q = $pdo->prepare($sql_q);
                $stmt_q->execute([$quiz_id, $diff_id]);
                $available_questions = $stmt_q->fetchAll(PDO::FETCH_ASSOC);

                if (count($available_questions) < $count_needed) {
                    throw new Exception("Not enough questions in the bank for difficulty {$diff_id}. Please contact the faculty.");
                }
                
                shuffle($available_questions);
                $selected_questions = array_slice($available_questions, 0, $count_needed);
                $final_questions = array_merge($final_questions, $selected_questions);
            }
        }
        shuffle($final_questions);

        // Fetch and add randomized options to each real question
        $stmt_options = $pdo->prepare("SELECT id, option_text FROM options WHERE question_id = ? ORDER BY RAND()");
        foreach ($final_questions as $key => $question) {
            $stmt_options->execute([$question['id']]);
            $final_questions[$key]['options'] = $stmt_options->fetchAll(PDO::FETCH_ASSOC);
        }

        // Submit confirmation is now handled by the frontend's explicit Submit button
        
        $questions_with_options = $final_questions;
        
        // Now, calculate the end time and store the generated questions and end time in the database
        $end_time = (new DateTime())->add(new DateInterval("PT{$duration_minutes}M"));
        $questions_json = json_encode($questions_with_options);
        
        $stmt_update = $pdo->prepare("UPDATE student_attempts SET attempt_end_time = ?, questions_json = ? WHERE id = ?");
        $stmt_update->execute([$end_time->format('Y-m-d H:i:s'), $questions_json, $attempt_id]);
        
        // For a new attempt, the remaining time is the full duration.
        $remaining_seconds = $duration_minutes * 60;
    }
    
    $pdo->commit();

    echo json_encode([
        'attempt_id' => $attempt_id,
        'remaining_seconds' => $remaining_seconds,
        'questions' => $questions_with_options,
        'config' => [
            'allow_calculator' => (bool)$quiz_config['allow_calculator'],
            'enable_negative_marking' => (bool)$quiz_config['enable_negative_marking'],
            'negative_marks_mcq' => (float)$quiz_config['negative_marks_mcq'],
            'negative_marks_msq' => (float)$quiz_config['negative_marks_msq'],
            'negative_marks_descriptive' => (float)$quiz_config['negative_marks_descriptive']
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    error_log("Fetch questions failed: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
