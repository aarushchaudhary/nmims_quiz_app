<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) { exit('Access Denied'); }

$role_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$role_id) {
    redirect('views/admin/manage_roles.php?error=Invalid+ID.');
    exit();
}

try {
    // Prevent deleting core roles
    $stmt_role = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
    $stmt_role->execute([$role_id]);
    $role_name = $stmt_role->fetchColumn();
    
    if (in_array($role_name, ['admin', 'faculty', 'student', 'placecom', 'director', 'school head'])) {
        throw new Exception('Cannot delete core roles.');
    }

    // First, check if any users are assigned this role
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?");
    $stmt_check->execute([$role_id]);
    if ($stmt_check->fetchColumn() > 0) {
        throw new Exception('Cannot delete role as it is currently assigned to one or more users.');
    }

    // If no users have this role, proceed with deletion
    $stmt_delete = $pdo->prepare("DELETE FROM roles WHERE id = ?");
    $stmt_delete->execute([$role_id]);
    redirect('views/admin/manage_roles.php?success=Role+deleted+successfully.');

} catch (Exception $e) {
    redirect('views/admin/manage_roles.php?error=' . urlencode($e->getMessage()));
}
