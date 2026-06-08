<?php
require_once '../../config/database.php';
require_once '../../config/base_url.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ' . get_base_url() . 'views/auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['elective_name'] ?? '');

    if (empty($name)) {
        header('Location: ' . get_base_url() . 'views/admin/electives.php?error=' . urlencode("Elective name is required."));
        exit();
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO electives (name) VALUES (?)");
        $stmt->execute([$name]);
        header('Location: ' . get_base_url() . 'views/admin/electives.php?success=' . urlencode("Elective added successfully."));
    } catch (\PDOException $e) {
        error_log($e->getMessage());
        header('Location: ' . get_base_url() . 'views/admin/electives.php?error=' . urlencode("Error adding elective. Name might already exist."));
    }
} else {
    header('Location: ' . get_base_url() . 'views/admin/electives.php');
}
?>
