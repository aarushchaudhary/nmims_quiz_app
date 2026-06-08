<?php
// api/faculty/create_quiz.php

header('Content-Type: application/json');
require_once '../../config/database.php';

// --- Authorization Check ---
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// --- Input Validation ---
$required_fields = [
    'title', 'school_id', 'course_id', 
    'start_time', 'end_time', 'duration_minutes',
    'config_easy_count', 'config_medium_count', 'config_hard_count'
];

foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || $_POST[$field] === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit();
    }
}

// --- Data Preparation ---
$title = $_POST['title'];
$school_id = filter_var($_POST['school_id'], FILTER_VALIDATE_INT);
$course_id = filter_var($_POST['course_id'], FILTER_VALIDATE_INT);
$start_time = $_POST['start_time'];
$end_time = $_POST['end_time'];
$duration_minutes = filter_var($_POST['duration_minutes'], FILTER_VALIDATE_INT);

// --- Time Validation ---
$start_timestamp = strtotime($start_time);
$end_timestamp = strtotime($end_time);

if ($end_timestamp <= $start_timestamp) {
    header('Location: ../../views/faculty/create_quiz.php?error=invalid_time');
    exit();
}

$max_duration = floor(($end_timestamp - $start_timestamp) / 60);
if ($duration_minutes > $max_duration) {
    header('Location: ../../views/faculty/create_quiz.php?error=duration_exceeded');
    exit();
}

// Group arrays
$classes = [];
$batches = [];
$electives = [];
$re_exam_groups = [];

if (isset($_POST['exam_groups']) && is_array($_POST['exam_groups'])) {
    foreach ($_POST['exam_groups'] as $group) {
        if (strpos($group, 'class_') === 0) {
            $classes[] = (int) substr($group, 6);
        } elseif (strpos($group, 'batch_') === 0) {
            $batches[] = (int) substr($group, 6);
        } elseif (strpos($group, 'elective_') === 0) {
            $electives[] = (int) substr($group, 9);
        } elseif (strpos($group, 'reexam_') === 0) {
            $re_exam_groups[] = (int) substr($group, 7);
        }
    }
}

// Manual students
$manual_saps = isset($_POST['manual_student_ids']) && is_array($_POST['manual_student_ids']) ? $_POST['manual_student_ids'] : [];

// Configuration
$config_easy_count = filter_var($_POST['config_easy_count'], FILTER_VALIDATE_INT);
$config_medium_count = filter_var($_POST['config_medium_count'], FILTER_VALIDATE_INT);
$config_hard_count = filter_var($_POST['config_hard_count'], FILTER_VALIDATE_INT);
$show_results_immediately = 0; // Forced to 0 to require manual publish
$allow_calculator = isset($_POST['allow_calculator']) ? 1 : 0;
$enable_negative_marking = isset($_POST['enable_negative_marking']) ? 1 : 0;
$negative_marks_mcq = !empty($_POST['negative_marks_mcq']) ? filter_var($_POST['negative_marks_mcq'], FILTER_VALIDATE_FLOAT) : 0.00;
$negative_marks_msq = !empty($_POST['negative_marks_msq']) ? filter_var($_POST['negative_marks_msq'], FILTER_VALIDATE_FLOAT) : 0.00;
$negative_marks_descriptive = !empty($_POST['negative_marks_descriptive']) ? filter_var($_POST['negative_marks_descriptive'], FILTER_VALIDATE_FLOAT) : 0.00;

$faculty_id = $_SESSION['user_id'];
$status_id = 1; // Default to 'Not Started'

// --- Database Insertion ---
$sql = "INSERT INTO quizzes (
            title, course_id, start_time, end_time, 
            duration_minutes, faculty_id, status_id,
            config_easy_count, config_medium_count, config_hard_count, 
            show_results_immediately,
            allow_calculator, enable_negative_marking, negative_marks_mcq, negative_marks_msq, negative_marks_descriptive
        ) VALUES (
            :title, :course_id, :start_time, :end_time, 
            :duration_minutes, :faculty_id, :status_id,
            :easy_count, :medium_count, :hard_count, 
            :show_results,
            :allow_calculator, :enable_negative_marking, :negative_marks_mcq, :negative_marks_msq, :negative_marks_descriptive
        )";

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':title' => $title,
        ':course_id' => $course_id,
        ':start_time' => $start_time,
        ':end_time' => $end_time,
        ':duration_minutes' => $duration_minutes,
        ':faculty_id' => $faculty_id,
        ':status_id' => $status_id,
        ':easy_count' => $config_easy_count,
        ':medium_count' => $config_medium_count,
        ':hard_count' => $config_hard_count,
        ':show_results' => $show_results_immediately,
        ':allow_calculator' => $allow_calculator,
        ':enable_negative_marking' => $enable_negative_marking,
        ':negative_marks_mcq' => $negative_marks_mcq,
        ':negative_marks_msq' => $negative_marks_msq,
        ':negative_marks_descriptive' => $negative_marks_descriptive
    ]);

    $quiz_id = $pdo->lastInsertId();

    // Insert group mappings
    if (!empty($classes)) {
        $stmt_c = $pdo->prepare("INSERT INTO quiz_classes (quiz_id, class_id) VALUES (?, ?)");
        foreach ($classes as $cid) $stmt_c->execute([$quiz_id, $cid]);
    }
    if (!empty($batches)) {
        $stmt_b = $pdo->prepare("INSERT INTO quiz_batches (quiz_id, batch_id) VALUES (?, ?)");
        foreach ($batches as $bid) $stmt_b->execute([$quiz_id, $bid]);
    }
    if (!empty($electives)) {
        $stmt_e = $pdo->prepare("INSERT INTO quiz_electives (quiz_id, elective_id) VALUES (?, ?)");
        foreach ($electives as $eid) $stmt_e->execute([$quiz_id, $eid]);
    }
    if (!empty($re_exam_groups)) {
        $stmt_r = $pdo->prepare("INSERT INTO quiz_re_exam_groups (quiz_id, group_id) VALUES (?, ?)");
        foreach ($re_exam_groups as $rid) $stmt_r->execute([$quiz_id, $rid]);
    }

    // Insert manual students
    if (!empty($manual_saps)) {
        $stmt_s = $pdo->prepare("SELECT user_id FROM students WHERE sap_id = ?");
        $stmt_ms = $pdo->prepare("INSERT IGNORE INTO quiz_manual_students (quiz_id, student_id) VALUES (?, ?)");
        foreach ($manual_saps as $sap) {
            $stmt_s->execute([$sap]);
            if ($row = $stmt_s->fetch()) {
                $stmt_ms->execute([$quiz_id, $row['user_id']]);
            }
        }
    }

    $pdo->commit();

    // Redirect to the view/edit page for the new quiz
    redirect('views/faculty/view_quiz.php?id=' . $quiz_id . '&created=true');
    exit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // In a real app, you would log this error.
    // For now, redirect with a generic error message.
    error_log('Quiz creation failed: ' . $e->getMessage());
    redirect('views/faculty/create_quiz.php?error=db_error');
    exit();
}
?>