<?php
// api/faculty/update_quiz.php

require_once '../../config/database.php';

// --- Authorization Check ---
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    redirect('login.php?error=unauthorized');
    exit();
}

// --- Input Validation ---
$required_fields = [
    'quiz_id', 'title', 'school_id', 'course_id', 'graduation_year', 
    'start_time', 'end_time', 'duration_minutes',
    'config_easy_count', 'config_medium_count', 'config_hard_count'
];

foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || $_POST[$field] === '') {
        // Redirect back with an error message
        header('Location: ' . $_SERVER['HTTP_REFERER'] . '&error=missing_fields');
        exit();
    }
}

// --- Data Preparation ---
$quiz_id = filter_var($_POST['quiz_id'], FILTER_VALIDATE_INT);
$title = $_POST['title'];
$course_id = filter_var($_POST['course_id'], FILTER_VALIDATE_INT);
$graduation_year = filter_var($_POST['graduation_year'], FILTER_VALIDATE_INT);
$start_time = $_POST['start_time'];
$end_time = $_POST['end_time'];
$duration_minutes = filter_var($_POST['duration_minutes'], FILTER_VALIDATE_INT);

// --- Time Validation ---
$start_timestamp = strtotime($start_time);
$end_timestamp = strtotime($end_time);

if ($end_timestamp <= $start_timestamp) {
    header('Location: ../../views/faculty/edit_quiz.php?id=' . $quiz_id . '&error=invalid_time');
    exit();
}

$max_duration = floor(($end_timestamp - $start_timestamp) / 60);
if ($duration_minutes > $max_duration) {
    header('Location: ../../views/faculty/edit_quiz.php?id=' . $quiz_id . '&error=duration_exceeded');
    exit();
}

// Optional Fields
$sap_id_start = !empty($_POST['sap_id_range_start']) ? filter_var($_POST['sap_id_range_start'], FILTER_SANITIZE_NUMBER_INT) : null;
$sap_id_end = !empty($_POST['sap_id_range_end']) ? filter_var($_POST['sap_id_range_end'], FILTER_SANITIZE_NUMBER_INT) : null;
// NEW: Handle specialization_id, setting to null if empty
$specialization_id = !empty($_POST['specialization_id']) ? filter_var($_POST['specialization_id'], FILTER_VALIDATE_INT) : null; 

// Configuration
$config_easy_count = filter_var($_POST['config_easy_count'], FILTER_VALIDATE_INT);
$config_medium_count = filter_var($_POST['config_medium_count'], FILTER_VALIDATE_INT);
$config_hard_count = filter_var($_POST['config_hard_count'], FILTER_VALIDATE_INT);
$show_results_immediately = isset($_POST['show_results_immediately']) ? 1 : 0;
$allow_calculator = isset($_POST['allow_calculator']) ? 1 : 0;
$enable_negative_marking = isset($_POST['enable_negative_marking']) ? 1 : 0;
$negative_marks_mcq = !empty($_POST['negative_marks_mcq']) ? filter_var($_POST['negative_marks_mcq'], FILTER_VALIDATE_FLOAT) : 0.00;
$negative_marks_msq = !empty($_POST['negative_marks_msq']) ? filter_var($_POST['negative_marks_msq'], FILTER_VALIDATE_FLOAT) : 0.00;
$negative_marks_descriptive = !empty($_POST['negative_marks_descriptive']) ? filter_var($_POST['negative_marks_descriptive'], FILTER_VALIDATE_FLOAT) : 0.00;
$faculty_id = $_SESSION['user_id'];

// --- Database Update ---
$sql = "UPDATE quizzes SET
            title = :title,
            course_id = :course_id,
            graduation_year = :graduation_year,
            start_time = :start_time,
            end_time = :end_time,
            duration_minutes = :duration_minutes,
            sap_id_range_start = :sap_id_start,
            sap_id_range_end = :sap_id_end,
            config_easy_count = :easy_count,
            config_medium_count = :medium_count,
            config_hard_count = :hard_count,
            show_results_immediately = :show_results,
            specialization_id = :specialization_id,
            allow_calculator = :allow_calculator,
            enable_negative_marking = :enable_negative_marking,
            negative_marks_mcq = :negative_marks_mcq,
            negative_marks_msq = :negative_marks_msq,
            negative_marks_descriptive = :negative_marks_descriptive
        WHERE id = :quiz_id AND faculty_id = :faculty_id"; // Security check to ensure ownership

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':title' => $title,
        ':course_id' => $course_id,
        ':graduation_year' => $graduation_year,
        ':start_time' => $start_time,
        ':end_time' => $end_time,
        ':duration_minutes' => $duration_minutes,
        ':sap_id_start' => $sap_id_start,
        ':sap_id_end' => $sap_id_end,
        ':easy_count' => $config_easy_count,
        ':medium_count' => $config_medium_count,
        ':hard_count' => $config_hard_count,
        ':show_results' => $show_results_immediately,
        ':specialization_id' => $specialization_id,
        ':allow_calculator' => $allow_calculator,
        ':enable_negative_marking' => $enable_negative_marking,
        ':negative_marks_mcq' => $negative_marks_mcq,
        ':negative_marks_msq' => $negative_marks_msq,
        ':negative_marks_descriptive' => $negative_marks_descriptive,
        ':quiz_id' => $quiz_id,
        ':faculty_id' => $faculty_id
    ]);

    // Redirect back to the view page with a success message
    redirect('views/faculty/view_quiz.php?id=' . $quiz_id . '&updated=true');
    exit();

} catch (PDOException $e) {
    // In a real application, you would log this error.
    error_log('Quiz update failed: ' . $e->getMessage());
    redirect('views/faculty/edit_quiz.php?id=' . $quiz_id . '&error=db_error');
    exit();
}
?>