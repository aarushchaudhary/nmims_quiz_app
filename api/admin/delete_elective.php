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
        $stmt = $pdo->prepare("DELETE FROM electives WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: ' . get_base_url() . 'views/admin/electives.php?success=' . urlencode("Elective deleted successfully."));
    } catch (\PDOException $e) {
        error_log($e->getMessage());
        header('Location: ' . get_base_url() . 'views/admin/electives.php?error=' . urlencode("Error deleting elective."));
    }
} else {
    header('Location: ' . get_base_url() . 'views/admin/electives.php');
}
?>
