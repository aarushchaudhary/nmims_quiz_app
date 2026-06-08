<?php
/*
 * api/admin/upload_students.php
 * Handles bulk creation of student accounts from an Excel file, including specializations.
 */
session_start();
require_once '../../config/database.php';
require_once '../../lib/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// --- Authorization & File Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    redirect('views/admin/upload_students.php?error=true&message=Unauthorized+access.');
    exit();
}
if (!isset($_FILES['student_file']) || $_FILES['student_file']['error'] !== UPLOAD_ERR_OK) {
    redirect('views/admin/upload_students.php?error=true&message=File+upload+failed.');
    exit();
}

$file = $_FILES['student_file']['tmp_name'];
$student_role_id = 4; // Assuming 4 is the role ID for students

try {
    $pdo->beginTransaction();
    
    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestRow();

    // --- Prepare reusable statements for performance ---
    $stmt_user = $pdo->prepare("INSERT INTO users (email, password_hash, role_id) VALUES (?, ?, ?)");
    $stmt_student = $pdo->prepare("INSERT INTO students (user_id, name, sap_id, roll_no, course_id, graduation_year, batch) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt_course = $pdo->prepare("SELECT id, duration_years FROM courses WHERE code = ?");

    $successCount = 0;
    $errorCount = 0;
    $errorMessages = [];

    // --- Loop from row 2 to skip the header ---
    for ($row = 2; $row <= $highestRow; $row++) {
        $full_name = trim($sheet->getCell('A' . $row)->getValue() ?? '');
        $email = trim($sheet->getCell('B' . $row)->getValue() ?? '');
        $sap_id = trim($sheet->getCell('C' . $row)->getValue() ?? '');

        if (empty($email) || empty($full_name) || empty($sap_id)) {
            // Check if row is completely empty before logging an error
            if (empty($email) && empty($full_name) && empty($sap_id)) {
                continue;
            }
            $errorCount++;
            $errorMessages[] = "Skipping row $row: Missing name, email, or SAP ID.";
            continue;
        }

        if (!preg_match('/nmims\.in$/i', $email)) {
            $errorCount++;
            $errorMessages[] = "Skipping row $row: Email ($email) must end in nmims.in.";
            continue;
        }
        
        if (!preg_match('/^\d{11}$/', $sap_id)) {
            $errorCount++;
            $errorMessages[] = "Skipping row $row: SAP ID ($sap_id) must be exactly 11 digits.";
            continue;
        }

        $course_code = substr($sap_id, 0, 4);
        $year_str = substr($sap_id, 4, 4);
        $start_year = 2000 + (int)substr($year_str, 0, 2);
        
        $stmt_course->execute([$course_code]);
        $course = $stmt_course->fetch();
        if (!$course) {
            $errorCount++;
            $errorMessages[] = "Skipping row $row for user '$full_name': Invalid course code in SAP ID ($course_code).";
            continue;
        }
        
        $course_id = $course['id'];
        $end_year = $start_year + $course['duration_years'];
        $graduation_year = $end_year;
        $batch = $start_year . '-' . $end_year;
        $roll_no = $sap_id;
        
        $password_hash = password_hash($sap_id, PASSWORD_DEFAULT);
        
        try {
            // Create user
            $stmt_user->execute([$email, $password_hash, $student_role_id]);
            $new_user_id = $pdo->lastInsertId();

            // Create student
            $stmt_student->execute([$new_user_id, $full_name, $sap_id, $roll_no, $course_id, $graduation_year, $batch]);
            $successCount++;
        } catch (Exception $e) {
            $errorCount++;
            $errorMessages[] = "Skipping row $row for user '$full_name': Email or SAP ID already exists in system.";
        }
    }

    $pdo->commit();

    // Prepare final feedback message
    $final_message = "$successCount students uploaded successfully.";
    if ($errorCount > 0) {
        $final_message .= " $errorCount rows were skipped due to errors.";
    }
    $_SESSION['upload_errors'] = $errorMessages;

    redirect('views/admin/upload_students.php?success=true&message=' . urlencode($final_message));

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Student upload failed: " . $e->getMessage());
    redirect('views/admin/upload_students.php?error=true&message=' . urlencode('A critical error occurred: ' . $e->getMessage()));
}
exit();
?>