<?php
/*
 * api/faculty/get_quiz_results.php
 * Fetches all student attempt data for a specific quiz.
 */
header('Content-Type: application/json');
session_start();
require_once '../../config/database.php';

// --- Authorization & Input Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    http_response_code(403);
    error_log("Authorization failed for get_quiz_results.php. Role ID: " . ($_SESSION['role_id'] ?? 'N/A'));
    exit(json_encode(['error' => 'Unauthorized access.']));
}
if (!isset($_GET['quiz_id']) || !filter_var($_GET['quiz_id'], FILTER_VALIDATE_INT)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid or missing quiz ID.']));
}

$quiz_id = (int)$_GET['quiz_id'];
$faculty_id = (int)$_SESSION['user_id'];

try {
    // **FIX:** Reverted to a more direct and standard SQL query.
    // This version uses a direct JOIN to the quizzes table and includes the
    // faculty_id check in the main WHERE clause for better reliability.
    $sql = "SELECT 
                st.name as student_name,
                st.sap_id,
                sa.total_score,
                sa.started_at,
                sa.submitted_at,
                sa.is_disqualified,
                q.show_results_immediately
            FROM student_attempts sa
            LEFT JOIN students st ON sa.student_id = st.user_id
            JOIN quizzes q ON sa.quiz_id = q.id
            WHERE sa.quiz_id = :quiz_id AND q.faculty_id = :faculty_id
            ORDER BY sa.total_score DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':quiz_id' => $quiz_id, ':faculty_id' => $faculty_id]);
    $results = $stmt->fetchAll();
    
    $is_published = !empty($results) ? (bool)$results[0]['show_results_immediately'] : false;

    // Calculate summary statistics
    $total_attempts = count($results);
    $total_score_sum = 0;
    $disqualified_count = 0;

    foreach ($results as &$result) { // Use reference to modify array directly
        // Use null coalescing operator for safety in case of missing student data
        $result['student_name'] = $result['student_name'] ?? '[Student Deleted]';
        $result['sap_id'] = $result['sap_id'] ?? 'N/A';
        
        $total_score_sum += $result['total_score'];
        if ($result['is_disqualified']) {
            $disqualified_count++;
        }
    }
    unset($result); // Unset the reference

    $average_score = ($total_attempts > 0) ? $total_score_sum / $total_attempts : 0;

    echo json_encode([
        'summary' => [
            'total_attempts' => $total_attempts,
            'average_score' => number_format($average_score, 2),
            'disqualified_count' => $disqualified_count,
            'is_published' => $is_published
        ],
        'details' => $results
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Get results failed for quiz_id: {$quiz_id}, faculty_id: {$faculty_id}. SQL ERROR: " . $e->getMessage());
    echo json_encode(['error' => 'A database error occurred while fetching the report.']);
}
