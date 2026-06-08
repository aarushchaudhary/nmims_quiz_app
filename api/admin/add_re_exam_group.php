<?php
require_once '../../config/database.php';
require_once '../../config/base_url.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ' . get_base_url() . 'views/auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['group_name'] ?? '');
    $expires_at = $_POST['expires_at'] ?? '';

    if (empty($name) || empty($expires_at)) {
        header('Location: ' . get_base_url() . 'views/admin/re_exam_groups.php?error=' . urlencode("Name and expiration time are required."));
        exit();
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO re_exam_groups (name, expires_at) VALUES (?, ?)");
        $stmt->execute([$name, $expires_at]);
        header('Location: ' . get_base_url() . 'views/admin/re_exam_groups.php?success=' . urlencode("Re Exam Group added successfully."));
    } catch (\PDOException $e) {
        error_log($e->getMessage());
        header('Location: ' . get_base_url() . 'views/admin/re_exam_groups.php?error=' . urlencode("Error adding group. Name might already exist."));
    }
} else {
    header('Location: ' . get_base_url() . 'views/admin/re_exam_groups.php');
}
?>
