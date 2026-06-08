<?php
require_once '../../config/database.php';
require_once '../../config/base_url.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ' . get_base_url() . 'views/auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['class_name'] ?? '');
    $school_id = $_POST['school_id'] ?? '';
    $course_id = $_POST['course_id'] ?? '';
    $graduation_year = $_POST['graduation_year'] ?? '';
    $sap_id_range_start = $_POST['sap_id_range_start'] ?? '';
    $sap_id_range_end = $_POST['sap_id_range_end'] ?? '';

    if (empty($name) || empty($school_id) || empty($course_id) || empty($graduation_year) || empty($sap_id_range_start) || empty($sap_id_range_end)) {
        header('Location: ' . get_base_url() . 'views/admin/classes.php?error=' . urlencode("All fields are required."));
        exit();
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO classes (name, school_id, course_id, graduation_year, sap_id_range_start, sap_id_range_end) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $school_id, $course_id, $graduation_year, $sap_id_range_start, $sap_id_range_end]);
        header('Location: ' . get_base_url() . 'views/admin/classes.php?success=' . urlencode("Class added successfully."));
    } catch (\PDOException $e) {
        error_log($e->getMessage());
        header('Location: ' . get_base_url() . 'views/admin/classes.php?error=' . urlencode("Error adding class. Please try again."));
    }
} else {
    header('Location: ' . get_base_url() . 'views/admin/classes.php');
}
?>
