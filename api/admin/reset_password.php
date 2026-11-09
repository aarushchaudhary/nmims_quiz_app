<?php
/*
 * api/admin/reset_password.php
 * Allows an admin to reset a user's password.
 */
header('Content-Type: application/json');
session_start();
require_once '../../config/database.php';

// --- Authorization & Request Method Check ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized access.']));
}

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $data['user_id'] ?? null;
$new_password = $data['new_password'] ?? null;

if (!$user_id || empty($new_password)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid input. Please provide a user ID and a new password.']));
}

try {
    // Hash the new password for secure storage
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

    // Update the password; the user must change it at next login. A reset also lifts a login lockout.
    $sql = "UPDATE users SET password_hash = ?, must_change_password = 1, failed_login_count = 0, locked_until = NULL WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$password_hash, $user_id]);

    // Check if the update was successful
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("User not found or password could not be updated.");
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Password reset failed: " . $e->getMessage());
    echo json_encode(['error' => 'A database error occurred.']);
}
