<?php
/*
 * api/admin/update_user.php
 * Handles the server-side logic for updating a user's account details.
 */
session_start();
require_once '../../config/database.php';

// --- Authorization Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    http_response_code(403);
    exit('Access denied.');
}

// --- Retrieve User Data ---
$user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$role_id = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT);
$email = trim($_POST['email']);
$full_name = trim($_POST['full_name']);
$password = $_POST['password'];
$is_visiting = isset($_POST['is_visiting']) ? 1 : 0;

// --- Validation ---
if (!$user_id || !$role_id || empty($email) || empty($full_name)) {
    redirect('views/admin/user_management.php?error=missing_fields');
    exit();
}

// --- Email Domain Validation ---
if (!$is_visiting) {
    $allowed_domains = ['nmims.in', 'nmims.edu', 'svkmgroup.onmicrosoft.com'];
    $email_parts = explode('@', strtolower($email));
    $domain = end($email_parts);
    
    if (!in_array($domain, $allowed_domains)) {
        redirect('views/admin/edit_user.php?id=' . $user_id . '&error=invalid_email_domain');
        exit();
    }
}

try {
    // --- NEW: Check for duplicate email before updating ---
    // Check if another user (not the current one) already has this email.
    $stmt_check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt_check->execute([$email, $user_id]);
    if ($stmt_check->fetch()) {
        // If a user is found, the email is already taken. Redirect with an error.
        redirect('views/admin/edit_user.php?id=' . $user_id . '&error=email_exists');
        exit();
    }

    $pdo->beginTransaction();

    // 1. Update the main 'users' table
    $sql_user = "UPDATE users SET email = ? ";
    $params_user = [$email];
    // Only update the password if a new one was provided
    if (!empty($password)) {
        $sql_user .= ", password_hash = ? ";
        $params_user[] = password_hash($password, PASSWORD_DEFAULT);
    }
    $sql_user .= "WHERE id = ?";
    $params_user[] = $user_id;
    $stmt_user = $pdo->prepare($sql_user);
    $stmt_user->execute($params_user);

    // 2. Update the role-specific table
    if ($role_id == 4) { // Student
        $sql_role = "UPDATE students SET name = ?, sap_id = ?, roll_no = ?, course_id = ?, batch = ?, graduation_year = ? WHERE user_id = ?";
        $stmt_role = $pdo->prepare($sql_role);
        $stmt_role->execute([$full_name, $_POST['sap_id'], $_POST['roll_no'], $_POST['course_id'], $_POST['batch'], $_POST['graduation_year'], $user_id]);

        // --- NEW: Handle Specializations server-side ---
        $stmt_del = $pdo->prepare("DELETE FROM student_specializations WHERE student_id = ?");
        $stmt_del->execute([$user_id]);
        
        if (isset($_POST['specialization_ids']) && is_array($_POST['specialization_ids'])) {
            $stmt_ins = $pdo->prepare("INSERT INTO student_specializations (student_id, specialization_id) VALUES (?, ?)");
            foreach ($_POST['specialization_ids'] as $spec_id) {
                $stmt_ins->execute([$user_id, $spec_id]);
            }
        }
    } elseif ($role_id == 2) { // Faculty
        $sql_role = "UPDATE faculties SET name = ?, sap_id = ?, school_id = ?, is_visiting = ? WHERE user_id = ?";
        $stmt_role = $pdo->prepare($sql_role);
        $sap_id_input = isset($_POST['faculty_sap_id']) ? $_POST['faculty_sap_id'] : (isset($_POST['staff_sap_id']) ? $_POST['staff_sap_id'] : '');
        $school_id_input = isset($_POST['department']) ? $_POST['department'] : (isset($_POST['staff_school_id']) ? $_POST['staff_school_id'] : null);
        $stmt_role->execute([$full_name, $sap_id_input, $school_id_input, $is_visiting, $user_id]);
    } elseif ($role_id == 3) { // Placement Officer
        $sql_role = "UPDATE placement_officers SET name = ? WHERE user_id = ?";
        $stmt_role = $pdo->prepare($sql_role);
        $stmt_role->execute([$full_name, $user_id]);
    } elseif ($role_id == 5) { // School Head
        $sql_role = "UPDATE heads SET name = ?, school_id = ? WHERE user_id = ?";
        $stmt_role = $pdo->prepare($sql_role);
        $school_id_input = isset($_POST['department']) ? $_POST['department'] : (isset($_POST['staff_school_id']) ? $_POST['staff_school_id'] : null);
        $stmt_role->execute([$full_name, $school_id_input, $user_id]);
    } else { // Admin or Director (roles 1 and 6)
        $sql_role = "UPDATE admins SET name = ? WHERE user_id = ?";
        $stmt_role = $pdo->prepare($sql_role);
        $stmt_role->execute([$full_name, $user_id]);
    }

    $pdo->commit();
    redirect('views/admin/user_management.php?success=User+updated+successfully.');

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Update user failed: " . $e->getMessage());
    redirect('views/admin/edit_user.php?id=' . $user_id . '&error=db_error');
}
exit();
