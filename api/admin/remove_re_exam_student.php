<?php
require_once '../../config/database.php';
require_once '../../config/base_url.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ' . get_base_url() . 'views/auth/login.php');
    exit();
}

if (isset($_GET['group_id']) && isset($_GET['student_id'])) {
    $group_id = $_GET['group_id'];
    $student_id = $_GET['student_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM re_exam_group_students WHERE group_id = ? AND student_id = ?");
        $stmt->execute([$group_id, $student_id]);
        header('Location: ' . get_base_url() . 'views/admin/manage_re_exam_group.php?id=' . $group_id . '&success=' . urlencode("Student removed from group."));
    } catch (\PDOException $e) {
        error_log($e->getMessage());
        header('Location: ' . get_base_url() . 'views/admin/manage_re_exam_group.php?id=' . $group_id . '&error=' . urlencode("Error removing student."));
    }
} else {
    header('Location: ' . get_base_url() . 'views/admin/re_exam_groups.php');
}
?>
