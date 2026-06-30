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
    
    $admission_year = (int)($_POST['admission_year'] ?? 0);
    $course_duration = (int)($_POST['course_duration'] ?? 0);
    $graduation_year = $admission_year + $course_duration;

    $class_names = $_POST['class_name'] ?? [];
    $sap_id_starts = $_POST['sap_id_range_start'] ?? [];
    $sap_id_ends = $_POST['sap_id_range_end'] ?? [];

    if (empty($school_id) || empty($course_id) || $admission_year === 0 || $course_duration === 0) {
        header('Location: ' . get_base_url() . 'views/admin/classes.php?error=' . urlencode("School, Course, Admission Year, and Course Duration are required."));
        exit();
    }

    if (!is_array($class_names) || count($class_names) === 0) {
        header('Location: ' . get_base_url() . 'views/admin/classes.php?error=' . urlencode("At least one class section must be provided."));
        exit();
    }

    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO classes (name, school_id, course_id, graduation_year, sap_id_range_start, sap_id_range_end) VALUES (?, ?, ?, ?, ?, ?)");
        
        $added_count = 0;
        for ($i = 0; $i < count($class_names); $i++) {
            $name = trim($class_names[$i]);
            $start = trim($sap_id_starts[$i] ?? '');
            $end = trim($sap_id_ends[$i] ?? '');

            if (!empty($name) && !empty($start) && !empty($end)) {
                $stmt->execute([$name, $school_id, $course_id, $graduation_year, $start, $end]);
                $added_count++;
            }
        }
        
        $pdo->commit();
        
        if ($added_count > 0) {
            header('Location: ' . get_base_url() . 'views/admin/classes.php?success=' . urlencode("$added_count class section(s) added successfully."));
        } else {
            header('Location: ' . get_base_url() . 'views/admin/classes.php?error=' . urlencode("No valid classes were submitted. Make sure all fields are filled."));
        }
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($e->getMessage());
        header('Location: ' . get_base_url() . 'views/admin/classes.php?error=' . urlencode("Error adding classes. Please try again."));
    }
} else {
    header('Location: ' . get_base_url() . 'views/admin/classes.php');
}
?>
