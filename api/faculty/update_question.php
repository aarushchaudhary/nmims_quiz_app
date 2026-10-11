<?php
/*
 * api/faculty/update_question.php
 * Handles the server-side logic for updating an existing question.
 */
session_start();
require_once '../../config/database.php';
require_once '../../config/authz.php';

// --- Authorization & Request Method Check ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    http_response_code(403);
    exit('Access denied.');
}

// --- Retrieve Form Data ---
$question_id      = filter_input(INPUT_POST, 'question_id', FILTER_VALIDATE_INT);
$quiz_id          = filter_input(INPUT_POST, 'quiz_id',     FILTER_VALIDATE_INT);
$question_text    = trim($_POST['question_text']);
$question_type_id = filter_input(INPUT_POST, 'question_type_id', FILTER_VALIDATE_INT);
$difficulty_id    = filter_input(INPUT_POST, 'difficulty_id',    FILTER_VALIDATE_INT);
$points           = filter_input(INPUT_POST, 'points', FILTER_VALIDATE_FLOAT);
$options          = $_POST['options']   ?? [];
$option_ids       = $_POST['option_ids'] ?? [];
$correct_answers  = $_POST['correct_answers'] ?? [];

// --- Validation ---
if (!$question_id || !$quiz_id || empty($question_text) || $points === false) {
    redirect('views/faculty/question_view.php?quiz_id=' . $quiz_id . '&error=missing_fields');
    exit();
}

$faculty_id = (int) $_SESSION['user_id'];

// SECURITY FIX #7: Ownership check — question must belong to a quiz owned by this faculty.
// $json = false because this is a form-post endpoint that redirects on error.
require_owned_question($pdo, $question_id, $faculty_id, false);

try {
    $pdo->beginTransaction();

    // 1. Update the main question details.
    $sql_q = 'UPDATE questions SET question_text = ?, question_type_id = ?, difficulty_id = ?, points = ? WHERE id = ?';
    $stmt_q = $pdo->prepare($sql_q);
    $stmt_q->execute([$question_text, $question_type_id, $difficulty_id, $points, $question_id]);

    // 2. Update the options.
    if ($question_type_id != 3) { // Not a descriptive question.
        // SECURITY FIX #7: WHERE clause now includes question_id so no option row
        // belonging to another question can be modified, even if option_ids[] is forged.
        $stmt_o = $pdo->prepare(
            'UPDATE options SET option_text = ?, is_correct = ? WHERE id = ? AND question_id = ?'
        );

        foreach ($options as $index => $option_text) {
            if (!empty($option_ids[$index])) {
                // Validate each option_id as an integer.
                $option_id_val = filter_var($option_ids[$index], FILTER_VALIDATE_INT);
                if ($option_id_val === false) {
                    continue; // Skip non-integer option IDs silently.
                }
                $is_correct = in_array($index, $correct_answers) ? 1 : 0;
                $stmt_o->execute([$option_text, $is_correct, $option_id_val, $question_id]);
            }
        }
    }

    $pdo->commit();
    redirect('views/faculty/question_view.php?quiz_id=' . $quiz_id . '&success=Question+updated+successfully.');

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('Update question failed: ' . $e->getMessage());
    redirect('views/faculty/edit_question.php?id=' . $question_id . '&error=db_error');
}
exit();
