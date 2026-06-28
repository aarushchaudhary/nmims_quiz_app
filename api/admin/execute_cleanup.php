<?php
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

try {
    $currentYear = (int)date('Y');
    $retentionYear = $currentYear - 1;

    $pdo->beginTransaction();

    // 1. Identify old students
    $stmt = $pdo->prepare("CREATE TEMPORARY TABLE old_student_users (user_id INT PRIMARY KEY)");
    $stmt->execute();
    
    $stmt = $pdo->prepare("INSERT INTO old_student_users (user_id) SELECT user_id FROM students WHERE graduation_year < ?");
    $stmt->execute([$retentionYear]);

    // 2. Identify old quizzes
    $stmt = $pdo->prepare("CREATE TEMPORARY TABLE old_quizzes (quiz_id INT PRIMARY KEY)");
    $stmt->execute();
    
    $stmt = $pdo->prepare("
        INSERT INTO old_quizzes (quiz_id) 
        SELECT DISTINCT q.id 
        FROM quizzes q
        LEFT JOIN quiz_classes qc ON q.id = qc.quiz_id
        LEFT JOIN classes c ON qc.class_id = c.id
        LEFT JOIN quiz_batches qb ON q.id = qb.quiz_id
        LEFT JOIN batches b ON qb.batch_id = b.id
        LEFT JOIN classes c2 ON b.class_id = c2.id
        WHERE (qc.class_id IS NOT NULL AND c.graduation_year < ?)
           OR (qb.batch_id IS NOT NULL AND c2.graduation_year < ?)
    ");
    $stmt->execute([$retentionYear, $retentionYear]);

    // 3. Delete event_logs related to old students OR old quizzes
    // (event_logs has foreign keys to users and student_attempts which don't cascade on users)
    $pdo->exec("DELETE FROM event_logs WHERE user_id IN (SELECT user_id FROM old_student_users)");
    $pdo->exec("DELETE FROM event_logs WHERE attempt_id IN (SELECT id FROM student_attempts WHERE quiz_id IN (SELECT quiz_id FROM old_quizzes))");

    // 4. Delete quiz_lobby for old students
    $pdo->exec("DELETE FROM quiz_lobby WHERE student_id IN (SELECT user_id FROM old_student_users)");
    $pdo->exec("DELETE FROM quiz_lobby WHERE quiz_id IN (SELECT quiz_id FROM old_quizzes)");

    // 5. Delete student_attempts for old students (cascades student_answers)
    $pdo->exec("DELETE FROM student_attempts WHERE student_id IN (SELECT user_id FROM old_student_users)");

    // 6. Delete old quizzes (cascades questions, options, student_attempts, quiz_classes, etc.)
    $pdo->exec("DELETE FROM quizzes WHERE id IN (SELECT quiz_id FROM old_quizzes)");

    // 7. Delete student specializations, electives, etc. (cascades, but just in case)
    $pdo->exec("DELETE FROM student_specializations WHERE student_id IN (SELECT user_id FROM old_student_users)");
    $pdo->exec("DELETE FROM elective_students WHERE student_id IN (SELECT user_id FROM old_student_users)");
    
    // 8. Delete students
    $pdo->exec("DELETE FROM students WHERE user_id IN (SELECT user_id FROM old_student_users)");

    // 9. Delete users (the students we just deleted)
    $pdo->exec("DELETE FROM users WHERE id IN (SELECT user_id FROM old_student_users)");

    // 10. Delete old classes (cascades batches)
    $stmt = $pdo->prepare("DELETE FROM classes WHERE graduation_year < ?");
    $stmt->execute([$retentionYear]);

    // Drop temp tables
    $pdo->exec("DROP TEMPORARY TABLE IF EXISTS old_student_users");
    $pdo->exec("DROP TEMPORARY TABLE IF EXISTS old_quizzes");

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Old data deleted successfully.']);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Execute Cleanup Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error during cleanup: ' . $e->getMessage()]);
}
