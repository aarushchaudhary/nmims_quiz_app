<?php
require_once '../../config/database.php';
require_once '../../config/base_url.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ' . get_base_url() . 'views/auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['batch_name'] ?? '');
    $class_id = $_POST['class_id'] ?? '';
    $sap_id_range_start = $_POST['sap_id_range_start'] ?? '';
    $sap_id_range_end = $_POST['sap_id_range_end'] ?? '';

    if (empty($name) || empty($class_id) || empty($sap_id_range_start) || empty($sap_id_range_end)) {
        header('Location: ' . get_base_url() . 'views/admin/batches.php?error=' . urlencode("All fields are required."));
        exit();
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO batches (name, class_id, sap_id_range_start, sap_id_range_end) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $class_id, $sap_id_range_start, $sap_id_range_end]);
        header('Location: ' . get_base_url() . 'views/admin/batches.php?success=' . urlencode("Batch added successfully."));
    } catch (\PDOException $e) {
        error_log($e->getMessage());
        header('Location: ' . get_base_url() . 'views/admin/batches.php?error=' . urlencode("Error adding batch. Please try again."));
    }
} else {
    header('Location: ' . get_base_url() . 'views/admin/batches.php');
}
?>
