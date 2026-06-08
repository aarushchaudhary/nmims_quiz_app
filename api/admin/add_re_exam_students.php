<?php
require_once '../../config/database.php';
require_once '../../config/base_url.php';
require_once '../../lib/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ' . get_base_url() . 'views/auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $group_id = $_POST['group_id'] ?? '';
    if (!$group_id) {
        header('Location: ' . get_base_url() . 'views/admin/re_exam_groups.php');
        exit();
    }

    $sap_ids = [];
    $errors = [];
    $successCount = 0;

    // Check if comma-separated text is provided
    if (!empty(trim($_POST['sap_ids'] ?? ''))) {
        $raw_ids = explode(',', $_POST['sap_ids']);
        foreach ($raw_ids as $id) {
            $id = trim($id);
            if (!empty($id)) {
                $sap_ids[] = $id;
            }
        }
    }

    // Check if file is uploaded
    if (isset($_FILES['sap_file']) && $_FILES['sap_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['sap_file']['tmp_name'];
        try {
            $spreadsheet = IOFactory::load($file);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();

            for ($row = 1; $row <= $highestRow; $row++) {
                $id = trim($sheet->getCell('A' . $row)->getValue() ?? '');
                if (!empty($id)) {
                    $sap_ids[] = $id;
                }
            }
        } catch (Exception $e) {
            $errors[] = "Failed to parse XLSX file: " . $e->getMessage();
        }
    }

    $sap_ids = array_unique($sap_ids);

    if (empty($sap_ids) && empty($errors)) {
        header('Location: ' . get_base_url() . 'views/admin/manage_re_exam_group.php?id=' . $group_id . '&error=' . urlencode("No SAP IDs provided."));
        exit();
    }

    $stmt_find_student = $pdo->prepare("SELECT user_id FROM students WHERE sap_id = ?");
    $stmt_add_student = $pdo->prepare("INSERT IGNORE INTO re_exam_group_students (group_id, student_id) VALUES (?, ?)");

    foreach ($sap_ids as $sap_id) {
        $stmt_find_student->execute([$sap_id]);
        $student = $stmt_find_student->fetch();
        if ($student) {
            $stmt_add_student->execute([$group_id, $student['user_id']]);
            if ($stmt_add_student->rowCount() > 0) {
                $successCount++;
            }
        } else {
            $errors[] = "SAP ID $sap_id not found in system.";
        }
    }

    $_SESSION['upload_errors'] = $errors;
    $msg = "$successCount students added successfully.";
    if (count($errors) > 0) {
        $msg .= " Some errors occurred.";
    }

    header('Location: ' . get_base_url() . 'views/admin/manage_re_exam_group.php?id=' . $group_id . '&success=' . urlencode($msg));
    exit();
} else {
    header('Location: ' . get_base_url() . 'views/admin/re_exam_groups.php');
}
?>
