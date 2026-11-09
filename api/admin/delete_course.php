<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) { exit('Access Denied'); }

$course_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$course_id) {
    redirect('views/admin/manage_courses.php?error=Invalid+ID.');
    exit();
}

try {
    // Note: Deleting a course might fail if students or quizzes are linked to it.
    $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    redirect('views/admin/manage_courses.php?success=Course+deleted+successfully.');
} catch (PDOException $e) {
        redirect('views/admin/manage_courses.php?error=Cannot+delete+course+as+it+is+linked+to+students+or+quizzes.');
}
