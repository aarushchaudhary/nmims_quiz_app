<?php
require_once '../../config/database.php';
require_once '../../config/base_url.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ' . get_base_url() . 'views/auth/login.php');
    exit();
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM re_exam_groups WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: ' . get_base_url() . 'views/admin/re_exam_groups.php?success=' . urlencode("Group deleted successfully."));
    } catch (\PDOException $e) {
        error_log($e->getMessage());
        header('Location: ' . get_base_url() . 'views/admin/re_exam_groups.php?error=' . urlencode("Error deleting group."));
    }
} else {
    header('Location: ' . get_base_url() . 'views/admin/re_exam_groups.php');
}
?>
