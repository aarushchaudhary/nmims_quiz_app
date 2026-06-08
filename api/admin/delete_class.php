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
        $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: ' . get_base_url() . 'views/admin/classes.php?success=' . urlencode("Class deleted successfully."));
    } catch (\PDOException $e) {
        error_log($e->getMessage());
        header('Location: ' . get_base_url() . 'views/admin/classes.php?error=' . urlencode("Error deleting class."));
    }
} else {
    header('Location: ' . get_base_url() . 'views/admin/classes.php');
}
?>
