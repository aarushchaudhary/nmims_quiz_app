<?php
require_once '../../config/database.php';
require_once '../../config/base_url.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ' . get_base_url() . 'views/auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $school_id = trim($_POST['school_id'] ?? '');
    $course_id = trim($_POST['course_id'] ?? '');
    
    $graduation_year = (int)($_POST['graduation_year'] ?? 0);

    if (empty($school_id) || empty($course_id) || $graduation_year === 0) {
        header('Location: ' . get_base_url() . 'views/admin/batches.php?error=' . urlencode("School, Course, and Batch are required."));
        exit();
    }

    try {
        // Get school name
        $stmtSchool = $pdo->prepare("SELECT name FROM schools WHERE id = ?");
        $stmtSchool->execute([$school_id]);
        $school = $stmtSchool->fetch();
        $school_name = $school ? $school['name'] : 'Unknown School';

        // Get course name
        $stmtCourse = $pdo->prepare("SELECT name FROM courses WHERE id = ?");
        $stmtCourse->execute([$course_id]);
        $course = $stmtCourse->fetch();
        $course_name = $course ? $course['name'] : 'Unknown Course';

        $class_name = "$school_name $course_name $graduation_year Batch";

        // Get SAP ID range from students
        $stmtRange = $pdo->prepare("SELECT MIN(CAST(sap_id AS UNSIGNED)) as min_sap, MAX(CAST(sap_id AS UNSIGNED)) as max_sap FROM students WHERE course_id = ? AND graduation_year = ?");
        $stmtRange->execute([$course_id, $graduation_year]);
        $range = $stmtRange->fetch();
        $start = $range['min_sap'] ?: 0;
        $end = $range['max_sap'] ?: 999999999999999;

        // Check if class already exists
        $stmtCheck = $pdo->prepare("SELECT id FROM classes WHERE name = ? AND school_id = ? AND course_id = ?");
        $stmtCheck->execute([$class_name, $school_id, $course_id]);
        if ($stmtCheck->fetch()) {
            header('Location: ' . get_base_url() . 'views/admin/batches.php?error=' . urlencode("Batch '$class_name' already exists."));
            exit();
        }

        $stmt = $pdo->prepare("INSERT INTO classes (name, school_id, course_id, graduation_year, sap_id_range_start, sap_id_range_end) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$class_name, $school_id, $course_id, $graduation_year, $start, $end]);
        
        header('Location: ' . get_base_url() . 'views/admin/batches.php?success=' . urlencode("Batch '$class_name' added successfully."));
    } catch (\PDOException $e) {
        error_log($e->getMessage());
        header('Location: ' . get_base_url() . 'views/admin/batches.php?error=' . urlencode("Error adding batch. Please try again."));
    }
} else {
    header('Location: ' . get_base_url() . 'views/admin/batches.php');
}
?>
