<?php
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $currentYear = (int)date('Y');
    $retentionYear = $currentYear - 1; // Anything with graduation_year < this will be deleted

    // Count old classes
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE graduation_year < ?");
    $stmt->execute([$retentionYear]);
    $classesCount = $stmt->fetchColumn();

    // Count old batches (based on old classes)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM batches b JOIN classes c ON b.class_id = c.id WHERE c.graduation_year < ?");
    $stmt->execute([$retentionYear]);
    $batchesCount = $stmt->fetchColumn();

    // Count old students
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE graduation_year < ?");
    $stmt->execute([$retentionYear]);
    $studentsCount = $stmt->fetchColumn();

    // Count old quizzes (tied to old classes or old batches)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT q.id) 
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
    $quizzesCount = $stmt->fetchColumn();
    
    // Count student attempts associated with old students
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_attempts sa JOIN students s ON sa.student_id = s.user_id WHERE s.graduation_year < ?");
    $stmt->execute([$retentionYear]);
    $attemptsCount = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'retentionYearThreshold' => $retentionYear,
        'counts' => [
            'classes' => $classesCount,
            'batches' => $batchesCount,
            'students' => $studentsCount,
            'quizzes' => $quizzesCount,
            'attempts' => $attemptsCount
        ]
    ]);

} catch (PDOException $e) {
    error_log("Cleanup Preview Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
