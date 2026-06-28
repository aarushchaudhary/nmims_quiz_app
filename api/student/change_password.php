<?php
require_once '../../config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4) {
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
$new_password = $input['new_password'] ?? '';
$confirm_password = $input['confirm_password'] ?? '';

if (empty($new_password) || empty($confirm_password)) {
    echo json_encode(['error' => 'Please fill all fields.']);
    exit();
}

if ($new_password !== $confirm_password) {
    echo json_encode(['error' => 'Passwords do not match.']);
    exit();
}

if (strlen($new_password) < 6) {
    echo json_encode(['error' => 'Password must be at least 6 characters long.']);
    exit();
}

try {
    // We should also check that the new password isn't their SAP ID again.
    $stmt = $pdo->prepare("SELECT sap_id FROM students WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $sap_id = $stmt->fetchColumn();

    if ($new_password === $sap_id) {
        echo json_encode(['error' => 'Your new password cannot be your SAP ID.']);
        exit();
    }

    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->execute([$hashed_password, $_SESSION['user_id']]);

    // Clear the force flag
    $_SESSION['force_password_change'] = false;

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log("Change Password Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred.']);
}
