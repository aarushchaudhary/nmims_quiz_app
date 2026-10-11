<?php
/*
 * api/student/log_event.php
 * Logs a proctoring event (like leaving the tab) to the database.
 */
header('Content-Type: application/json');
session_start();
require_once '../../config/database.php';

// --- Authorization & Request Method Check ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4) {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized access.']));
}

$data = json_decode(file_get_contents('php://input'), true);
$attempt_id = $data['attempt_id'] ?? null;
$event_type = $data['event_type'] ?? 'Unknown';
$description = $data['description'] ?? '';

if (!$attempt_id) {
    http_response_code(400);
    exit(json_encode(['error' => 'Missing attempt ID.']));
}

try {
    $sql = "INSERT INTO event_logs (attempt_id, user_id, event_type, description, ip_address) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $attempt_id,
        $_SESSION['user_id'],
        $event_type,
        $description,
        $_SERVER['REMOTE_ADDR'] // Get user's IP address
    ]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Log event failed: " . $e->getMessage());
    echo json_encode(['error' => 'Database error while logging event.']);
}
