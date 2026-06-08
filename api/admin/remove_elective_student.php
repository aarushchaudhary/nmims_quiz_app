<?php
require_once '../../config/database.php';
require_once '../../config/base_url.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ' . get_base_url() . 'views/auth/login.php');
    exit();
}

if (isset($_GET['elective_id']) && isset($_GET['student_id'])) {
    $elective_id = $_GET['elective_id'];
    $student_id = $_GET['student_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM elective_students WHERE elective_id = ? AND student_id = ?");
        $stmt->execute([$elective_id, $student_id]);
        header('Location: ' . get_base_url() . 'views/admin/manage_elective.php?id=' . $elective_id . '&success=' . urlencode("Student removed from elective."));
    } catch (\PDOException $e) {
        error_log($e->getMessage());
        header('Location: ' . get_base_url() . 'views/admin/manage_elective.php?id=' . $elective_id . '&error=' . urlencode("Error removing student."));
    }
} else {
    header('Location: ' . get_base_url() . 'views/admin/electives.php');
}
?>
