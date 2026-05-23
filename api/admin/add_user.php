<?php
/*
 * api/admin/add_user.php
 * Handles creating a new user account with role-specific fields.
 */
session_start();
require_once '../../config/database.php';

// --- Authorization Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied.');
}

// --- Retrieve Core User Data ---
$email = trim($_POST['email']);
$password = $_POST['password'];
$role_id = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT);
$full_name = trim($_POST['full_name']);
$is_visiting = isset($_POST['is_visiting']) ? 1 : 0;

// --- Validation ---
if (empty($email) || empty($password) || empty($full_name) || !$role_id) {
    redirect('views/admin/add_user.php?error=missing_fields');
    exit();
}

// --- Email Domain Validation ---
if (!$is_visiting) {
    $allowed_domains = ['nmims.in', 'nmims.edu', 'svkmgroup.onmicrosoft.com'];
    $email_parts = explode('@', strtolower($email));
    $domain = end($email_parts);
    
    if (!in_array($domain, $allowed_domains)) {
        redirect('views/admin/add_user.php?error=invalid_email_domain');
        exit();
    }
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $pdo->beginTransaction();

    // 1. Insert into the main 'users' table
    $sql_user = "INSERT INTO users (email, password_hash, role_id) VALUES (?, ?, ?)";
    $stmt_user = $pdo->prepare($sql_user);
    $stmt_user->execute([$email, $password_hash, $role_id]);
    $new_user_id = $pdo->lastInsertId();

    // 2. Insert into the appropriate details table based on the role
    if ($role_id == 4) { // Student
        $sql = "INSERT INTO students (user_id, name, sap_id, roll_no, course_id, batch, graduation_year) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$new_user_id, $full_name, $_POST['sap_id'], $_POST['roll_no'], $_POST['course_id'], $_POST['batch'], $_POST['graduation_year']]);
    
    } elseif ($role_id == 2) { // Faculty
        $sql = "INSERT INTO faculties (user_id, name, sap_id, school_id, is_visiting) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$new_user_id, $full_name, $_POST['staff_sap_id'], $_POST['staff_school_id'], $is_visiting]);
    
    } elseif ($role_id == 3) { // Placement Officer
        $sql = "INSERT INTO placement_officers (user_id, name) VALUES (?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$new_user_id, $full_name]);
    
    } elseif ($role_id == 5) { // School Head
        // Insert the School Head and link them to their assigned school
        $sql = "INSERT INTO heads (user_id, name, school_id) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$new_user_id, $full_name, $_POST['staff_school_id']]);
        
    } else { // Any other role (e.g., Admin, Director)
        // Default fallback for roles that don't need a school assignment
        $sql = "INSERT INTO admins (user_id, name) VALUES (?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$new_user_id, $full_name]);
    }

    $pdo->commit();
    redirect('views/admin/user_management.php?success=User+created+successfully.');

} catch (PDOException $e) {
    $pdo->rollBack();
    if ($e->getCode() == 23000) {
        redirect('views/admin/add_user.php?error=username_exists');
    } else {
        error_log("Add user failed: " . $e->getMessage());
        redirect('views/admin/add_user.php?error=db_error');
    }
}
exit();
