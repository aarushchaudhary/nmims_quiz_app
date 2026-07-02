<?php
require_once '../../config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$current_password = $input['current_password'] ?? '';
$new_password = $input['new_password'] ?? '';
$confirm_password = $input['confirm_password'] ?? '';
$is_forced = filter_var($input['is_forced'] ?? false, FILTER_VALIDATE_BOOLEAN);

if (empty($new_password) || empty($confirm_password) || (!$is_forced && empty($current_password))) {
    echo json_encode(['error' => 'Please fill all required fields.']);
    exit();
}

if ($new_password !== $confirm_password) {
    echo json_encode(['error' => 'New passwords do not match.']);
    exit();
}

if (strlen($new_password) < 6) {
    echo json_encode(['error' => 'Password must be at least 6 characters long.']);
    exit();
}

try {
    // Check current password if not forced
    if (!$is_forced) {
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($current_password, $hash)) {
            echo json_encode(['error' => 'Incorrect current password.']);
            exit();
        }
    }

    // Check that new password isn't the same as SAP ID (if student)
    if ($_SESSION['role_id'] == 4) {
        $stmt = $pdo->prepare("SELECT sap_id FROM students WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $sap_id = $stmt->fetchColumn();

        if ($new_password === $sap_id) {
            echo json_encode(['error' => 'Your new password cannot be your SAP ID.']);
            exit();
        }
    }

    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->execute([$hashed_password, $_SESSION['user_id']]);

    // Clear the force flag if it existed
    $_SESSION['force_password_change'] = false;

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log("Change Password Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred.']);
}
