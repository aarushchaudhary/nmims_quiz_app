<?php
require_once '../../config/database.php';
require_once '../../config/base_url.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ' . get_base_url() . 'views/auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section_id = $_POST['section_id'] ?? '';
    $name = trim($_POST['class_name'] ?? '');
    $parent_batch_id = $_POST['parent_batch_id'] ?? '';
    $sap_id_range_start = $_POST['sap_id_range_start'] ?? '';
    $sap_id_range_end = $_POST['sap_id_range_end'] ?? '';

    if (empty($section_id) || empty($name) || empty($parent_batch_id) || empty($sap_id_range_start) || empty($sap_id_range_end)) {
        header('Location: ' . get_base_url() . 'views/admin/classes.php?error=' . urlencode("All fields are required."));
        exit();
    }

    try {
        $stmt = $pdo->prepare("UPDATE batches SET name = ?, class_id = ?, sap_id_range_start = ?, sap_id_range_end = ? WHERE id = ?");
        $stmt->execute([$name, $parent_batch_id, $sap_id_range_start, $sap_id_range_end, $section_id]);
        header('Location: ' . get_base_url() . 'views/admin/classes.php?success=' . urlencode("Section updated successfully."));
    } catch (\PDOException $e) {
        error_log($e->getMessage());
        header('Location: ' . get_base_url() . 'views/admin/classes.php?error=' . urlencode("Error updating section. Please try again."));
    }
} else {
    header('Location: ' . get_base_url() . 'views/admin/classes.php');
}
?>
