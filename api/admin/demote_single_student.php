<?php
require_once '../../config/database.php';
require_once '../../config/base_url.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ' . get_base_url() . 'views/auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'] ?? null;

    if (empty($student_id)) {
        header('Location: ' . get_base_url() . 'views/admin/demote_students.php?error=' . urlencode("Student ID is required."));
        exit();
    }

    try {
        $pdo->beginTransaction();

        $stmt_select = $pdo->prepare("SELECT user_id, batch, graduation_year FROM students WHERE user_id = ?");
        $stmt_update = $pdo->prepare("UPDATE students SET batch = ?, graduation_year = ? WHERE user_id = ?");

        $stmt_select->execute([$student_id]);
        $student = $stmt_select->fetch();

        if ($student) {
            $current_batch = $student['batch'];
            $current_grad_year = (int)$student['graduation_year'];

            // Parse batch string
            $new_batch = $current_batch;
            if (preg_match('/^(\d{4})-(\d{4})$/', $current_batch, $matches)) {
                $start_year = (int)$matches[1] + 1;
                $end_year = (int)$matches[2] + 1;
                $new_batch = $start_year . '-' . $end_year;
            } elseif (preg_match('/(\d{4})/', $current_batch, $matches)) {
                $year = (int)$matches[1] + 1;
                $new_batch = str_replace($matches[1], (string)$year, $current_batch);
            }

            $new_grad_year = $current_grad_year + 1;

            $stmt_update->execute([$new_batch, $new_grad_year, $student_id]);
            
            $pdo->commit();
            header('Location: ' . get_base_url() . 'views/admin/demote_students.php?success=' . urlencode("Student demoted successfully to graduation year $new_grad_year."));
        } else {
            $pdo->rollBack();
            header('Location: ' . get_base_url() . 'views/admin/demote_students.php?error=' . urlencode("Student not found."));
        }
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($e->getMessage());
        header('Location: ' . get_base_url() . 'views/admin/demote_students.php?error=' . urlencode("Error demoting student. Please try again."));
    }
} else {
    header('Location: ' . get_base_url() . 'views/admin/demote_students.php');
}
?>
