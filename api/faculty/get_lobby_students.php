<?php
/*
 * api/faculty/get_lobby_students.php
 * Fetches a list of students currently in a specific quiz lobby.
 */

header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../config/authz.php';

// --- Authorization & Input Check ---
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied.']);
    exit();
}
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or missing quiz ID.']);
    exit();
}

$quiz_id = (int) $_GET['id'];

$faculty_id = (int) $_SESSION['user_id'];
// SECURITY FIX #7: Ownership check.
require_owned_quiz($pdo, $quiz_id, $faculty_id);

try {
    // This query joins three tables to get student details based on who is in the lobby
    $sql = "SELECT s.name, s.sap_id 
            FROM quiz_lobby ql
            JOIN students s ON ql.student_id = s.user_id
            WHERE ql.quiz_id = ?
            ORDER BY ql.joined_at ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$quiz_id]);
    $students = $stmt->fetchAll();

    echo json_encode($students);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Get lobby students failed: " . $e->getMessage());
    echo json_encode(['error' => 'Database error.']);
}
