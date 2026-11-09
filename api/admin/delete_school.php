<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) { exit('Access Denied'); }

$school_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$school_id) {
    redirect('views/admin/manage_schools.php?error=Invalid+ID.');
    exit();
}

try {
    // Note: Deleting a school might fail if courses are linked to it.
    // A more robust system would reassign courses or delete them first.
    $stmt = $pdo->prepare("DELETE FROM schools WHERE id = ?");
    $stmt->execute([$school_id]);
    redirect('views/admin/manage_schools.php?success=School+deleted+successfully.');
} catch (PDOException $e) {
        redirect('views/admin/manage_schools.php?error=Cannot+delete+school+as+it+is+linked+to+existing+courses.');
}
