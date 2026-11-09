<?php
/*
 * api/faculty/get_live_monitoring_data.php
 * Fetches a paginated, real-time status of all students for a specific quiz.
 * MODIFIED: Now handles specialization-specific quizzes.
 */
header('Content-Type: application/json');
session_start();
require_once '../../config/database.php';
require_once '../../config/authz.php';

// --- Authorization & Input Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2 || !isset($_GET['id'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized access.']));
}
$quiz_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if ($quiz_id === false || !$quiz_id) {
    json_fail(400, 'Invalid quiz ID.');
}

$faculty_id = (int) $_SESSION['user_id'];
// SECURITY FIX #7: Ownership check.
require_owned_quiz($pdo, $quiz_id, $faculty_id);

// --- Pagination Parameters ---
$page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$limit = max(1, min(100, isset($_GET['limit']) ? (int)$_GET['limit'] : 10)); // 0 would divide by zero below
$offset = ($page - 1) * $limit;

try {
    // 1. Get quiz info, including specialization
    $stmt_quiz = $pdo->prepare(
        "SELECT q.config_easy_count, q.config_medium_count, q.config_hard_count, es.name as status_name 
         FROM quizzes q 
         JOIN exam_statuses es ON q.status_id = es.id 
         WHERE q.id = ?"
    );
    $stmt_quiz->execute([$quiz_id]);
    $quiz_info = $stmt_quiz->fetch();

    if (!$quiz_info) {
        http_response_code(404);
        exit(json_encode(['error' => 'Quiz not found.']));
    }
    
    $total_questions = ($quiz_info['config_easy_count'] ?? 0) + ($quiz_info['config_medium_count'] ?? 0) + ($quiz_info['config_hard_count'] ?? 0);
    $quiz_status = $quiz_info['status_name'] ?? 'Unknown';

    // 3. Get the TOTAL number of students for this quiz for pagination
    // Who is assigned: the shared rule in config/authz.php (same as the student dashboard and exam).
    $stmt_total = $pdo->prepare(
        'SELECT COUNT(*) FROM students s JOIN quizzes q ON q.id = ?
         WHERE ' . student_quiz_eligibility_sql()
    );
    $stmt_total->execute([$quiz_id]);
    $total_students = $stmt_total->fetchColumn();
    $total_pages = $total_students > 0 ? ceil($total_students / $limit) : 0;

    $stmt_students = $pdo->prepare(
        "SELECT u.id AS user_id, s.name AS name, s.sap_id,
                sa.id AS attempt_id, sa.submitted_at, sa.is_disqualified, sa.is_manually_locked,
                (SELECT COUNT(*) FROM event_logs el WHERE el.attempt_id = sa.id AND el.event_type = 'Violation') AS violation_count
         FROM students s
         JOIN users u ON s.user_id = u.id
         JOIN quizzes q ON q.id = ?
         LEFT JOIN student_attempts sa ON sa.student_id = s.user_id AND sa.quiz_id = q.id
         WHERE " . student_quiz_eligibility_sql() . "
         ORDER BY sa.is_disqualified DESC, sa.is_manually_locked DESC, s.name ASC
         LIMIT ? OFFSET ?"
    );
    foreach ([$quiz_id, $limit, $offset] as $i => $value) {
        $stmt_students->bindValue($i + 1, $value, PDO::PARAM_INT);
    }
    $stmt_students->execute();
    $students = $stmt_students->fetchAll();

    // 5. Get progress data for the current page of students
    $progress_data = [];
    if (!empty($students)) {
        $attempt_ids = array_filter(array_column($students, 'attempt_id'));
        if (!empty($attempt_ids)) {
            $attempt_ids = array_values($attempt_ids);
            $placeholders = implode(',', array_fill(0, count($attempt_ids), '?'));
            $sql_progress = "SELECT attempt_id, COUNT(id) as answered_count FROM student_answers WHERE attempt_id IN ($placeholders) GROUP BY attempt_id";
            $stmt_progress = $pdo->prepare($sql_progress);
            $stmt_progress->execute($attempt_ids);
            $progress_data = $stmt_progress->fetchAll(PDO::FETCH_KEY_PAIR);
        }
    }
    
    // 6. Get lobby data
    $stmt_lobby = $pdo->prepare("SELECT student_id FROM quiz_lobby WHERE quiz_id = ?");
    $stmt_lobby->execute([$quiz_id]);
    $lobby_students = $stmt_lobby->fetchAll(PDO::FETCH_COLUMN, 0);

    // 7. Process and combine data
    $monitoring_data = [];
    foreach ($students as $student) {
        $status = 'Not Started';
        $progress = 'N/A';
        if ($student['attempt_id']) {
            $answered_count = $progress_data[$student['attempt_id']] ?? 0;
            $progress = "{$answered_count} / {$total_questions}";
        }

        if ($student['is_manually_locked']) { $status = 'Locked'; }
        elseif ($student['is_disqualified']) { $status = 'Disqualified'; }
        elseif ($student['submitted_at']) { $status = 'Finished'; }
        elseif ($student['attempt_id']) { $status = 'In Progress'; }
        elseif (in_array($student['user_id'], $lobby_students)) {
            $status = 'In Lobby';
        }
        $monitoring_data[] = [
            'name' => $student['name'],
            'sap_id' => $student['sap_id'],
            'status' => $status,
            'progress' => $progress,
            'attempt_id' => $student['attempt_id'],
            'is_disqualified' => (bool)$student['is_disqualified'],
            'is_manually_locked' => (bool)$student['is_manually_locked'],
            'violation_count' => (int)($student['violation_count'] ?? 0)
        ];
    }

    // 8. Return the combined data package with full pagination info
    echo json_encode([
        'quiz_status' => $quiz_status,
        'students' => $monitoring_data,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_students' => (int)$total_students,
            'limit' => $limit
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Live monitoring failed: " . $e->getMessage());
    echo json_encode(['error' => 'Database error. Please check logs.']);
}
