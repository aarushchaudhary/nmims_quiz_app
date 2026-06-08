<?php
/*
 * api/faculty/upload_questions.php
 * Handles the server-side logic for uploading questions from an Excel file.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';
require_once '../../lib/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// --- Authorization & Request Method Check ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    http_response_code(403);
    exit('Access denied.');
}

// --- File and Input Validation ---
if (!isset($_FILES['question_file']) || $_FILES['question_file']['error'] !== UPLOAD_ERR_OK || !isset($_POST['quiz_id'])) {
    redirect('views/faculty/view_quiz.php?id=' . ($_POST['quiz_id'] ?? '') . '&error=File+upload+failed.');
    exit();
}

$quiz_id = filter_input(INPUT_POST, 'quiz_id', FILTER_VALIDATE_INT);
if (!$quiz_id) {
    redirect('views/faculty/manage_quizzes.php?error=Invalid+Quiz+ID.');
    exit();
}

$file = $_FILES['question_file']['tmp_name'];

// --- Pre-fetch lookup data ---
$question_types = $pdo->query("SELECT id, name FROM question_types")->fetchAll(PDO::FETCH_KEY_PAIR);
$question_difficulties = $pdo->query("SELECT id, level FROM question_difficulties")->fetchAll(PDO::FETCH_KEY_PAIR);

try {
    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestRow();

    $pdo->beginTransaction();

    // Loop through each row of the spreadsheet, starting from row 2 to skip the header
    for ($row = 2; $row <= $highestRow; $row++) {
        // **MODIFIED:** Reading new column order
        $question_text = trim($sheet->getCell('A' . $row)->getValue() ?? '');
        $type_name = trim($sheet->getCell('B' . $row)->getValue() ?? '');
        $difficulty_level = trim($sheet->getCell('C' . $row)->getValue() ?? '');
        $points = trim($sheet->getCell('D' . $row)->getValue() ?? ''); // NEW
        $option1 = trim($sheet->getCell('E' . $row)->getValue() ?? '');
        $option2 = trim($sheet->getCell('F' . $row)->getValue() ?? '');
        $option3 = trim($sheet->getCell('G' . $row)->getValue() ?? '');
        $option4 = trim($sheet->getCell('H' . $row)->getValue() ?? '');
        $correct_option_nums = explode(',', trim($sheet->getCell('I' . $row)->getValue() ?? ''));

        if (empty($question_text)) continue;

        // --- Data Validation & Lookup ---
        $type_id = array_search($type_name, $question_types);
        $difficulty_id = array_search($difficulty_level, $question_difficulties);

        if ($type_id === false || $difficulty_id === false) {
            throw new Exception("Invalid type or difficulty ('{$type_name}'/'{$difficulty_level}') on row {$row}.");
        }
        if (!is_numeric($points) || $points < 0) {
            $points = 1.0; // Default to 1 point if points value is invalid or not specified
        }

        // --- Insert Question with Points ---
        $sql_question = "INSERT INTO questions (quiz_id, question_type_id, difficulty_id, points, question_text) VALUES (?, ?, ?, ?, ?)";
        $stmt_question = $pdo->prepare($sql_question);
        $stmt_question->execute([$quiz_id, $type_id, $difficulty_id, $points, $question_text]);
        $question_id = $pdo->lastInsertId();

        // --- Insert Options (unchanged) ---
        if ($type_id == 1 || $type_id == 2) {
            $options = [$option1, $option2, $option3, $option4];
            $sql_option = "INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, ?)";
            $stmt_option = $pdo->prepare($sql_option);

            foreach ($options as $index => $option_text) {
                if (!empty($option_text)) {
                    $option_num = $index + 1;
                    $is_correct = in_array($option_num, $correct_option_nums) ? 1 : 0;
                    $stmt_option->execute([$question_id, $option_text, $is_correct]);
                }
            }
        }
    }

    $pdo->commit();
    redirect('views/faculty/view_quiz.php?id=' . $quiz_id . '&success=Questions+uploaded+successfully.');

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Question upload failed: " . $e->getMessage());
    redirect('views/faculty/view_quiz.php?id=' . $quiz_id . '&error=' . urlencode($e->getMessage()));
}
exit();
