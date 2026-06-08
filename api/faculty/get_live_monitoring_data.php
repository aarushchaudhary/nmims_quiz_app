<?php
/*
 * api/faculty/get_live_monitoring_data.php
 * Fetches a paginated, real-time status of all students for a specific quiz.
 * MODIFIED: Now handles specialization-specific quizzes.
 */
header('Content-Type: application/json');
session_start();
require_once '../../config/database.php';

// --- Authorization & Input Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2 || !isset($_GET['id'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized access.']));
}
$quiz_id = $_GET['id'];

// --- Pagination Parameters ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
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
    $sql_total = "SELECT COUNT(DISTINCT s.user_id) 
                  FROM students s 
                  LEFT JOIN quiz_manual_students qms ON qms.quiz_id = ? AND qms.student_id = s.user_id
                  LEFT JOIN quiz_classes qc ON qc.quiz_id = ?
                  LEFT JOIN classes c ON qc.class_id = c.id
                  LEFT JOIN quiz_batches qb ON qb.quiz_id = ?
                  LEFT JOIN batches b ON qb.batch_id = b.id
                  LEFT JOIN classes bc ON b.class_id = bc.id
                  LEFT JOIN quiz_electives qe ON qe.quiz_id = ?
                  LEFT JOIN elective_students es_stu ON qe.elective_id = es_stu.elective_id AND es_stu.student_id = s.user_id
                  LEFT JOIN quiz_re_exam_groups qrg ON qrg.quiz_id = ?
                  LEFT JOIN re_exam_group_students regs ON qrg.group_id = regs.group_id AND regs.student_id = s.user_id
                  WHERE (
                      qms.student_id IS NOT NULL
                      OR (
                          c.id IS NOT NULL AND 
                          s.course_id = c.course_id AND 
                          s.graduation_year = c.graduation_year AND 
                          CAST(s.sap_id AS UNSIGNED) BETWEEN c.sap_id_range_start AND c.sap_id_range_end
                      )
                      OR (
                          b.id IS NOT NULL AND 
                          s.course_id = bc.course_id AND 
                          s.graduation_year = bc.graduation_year AND 
                          CAST(s.sap_id AS UNSIGNED) BETWEEN b.sap_id_range_start AND b.sap_id_range_end
                      )
                      OR es_stu.student_id IS NOT NULL
                      OR regs.student_id IS NOT NULL
                  )";
    $stmt_total = $pdo->prepare($sql_total);
    $stmt_total->execute([$quiz_id, $quiz_id, $quiz_id, $quiz_id, $quiz_id]);
    $total_students = $stmt_total->fetchColumn();
    $total_pages = $total_students > 0 ? ceil($total_students / $limit) : 0;

    // 4. Fetch one page of students, with disqualified students first
    $sql_students = "SELECT DISTINCT
                        u.id as user_id, 
                        s.name as name, 
                        s.sap_id, 
                        sa.id as attempt_id, 
                        sa.submitted_at, 
                        sa.is_disqualified, 
                        sa.is_manually_locked
                    FROM students s
                    JOIN users u ON s.user_id = u.id
                    LEFT JOIN student_attempts sa ON s.user_id = sa.student_id AND sa.quiz_id = ?
                    LEFT JOIN quiz_manual_students qms ON qms.quiz_id = ? AND qms.student_id = s.user_id
                    LEFT JOIN quiz_classes qc ON qc.quiz_id = ?
                    LEFT JOIN classes c ON qc.class_id = c.id
                    LEFT JOIN quiz_batches qb ON qb.quiz_id = ?
                    LEFT JOIN batches b ON qb.batch_id = b.id
                    LEFT JOIN classes bc ON b.class_id = bc.id
                    LEFT JOIN quiz_electives qe ON qe.quiz_id = ?
                    LEFT JOIN elective_students es_stu ON qe.elective_id = es_stu.elective_id AND es_stu.student_id = s.user_id
                    LEFT JOIN quiz_re_exam_groups qrg ON qrg.quiz_id = ?
                    LEFT JOIN re_exam_group_students regs ON qrg.group_id = regs.group_id AND regs.student_id = s.user_id
                    WHERE (
                        qms.student_id IS NOT NULL
                        OR (
                            c.id IS NOT NULL AND 
                            s.course_id = c.course_id AND 
                            s.graduation_year = c.graduation_year AND 
                            CAST(s.sap_id AS UNSIGNED) BETWEEN c.sap_id_range_start AND c.sap_id_range_end
                        )
                        OR (
                            b.id IS NOT NULL AND 
                            s.course_id = bc.course_id AND 
                            s.graduation_year = bc.graduation_year AND 
                            CAST(s.sap_id AS UNSIGNED) BETWEEN b.sap_id_range_start AND b.sap_id_range_end
                        )
                        OR es_stu.student_id IS NOT NULL
                        OR regs.student_id IS NOT NULL
                    )
                    ORDER BY sa.is_disqualified DESC, sa.is_manually_locked DESC, s.name ASC
                    LIMIT ? OFFSET ?";
    
    $student_params = [$quiz_id, $quiz_id, $quiz_id, $quiz_id, $quiz_id, $quiz_id, $limit, $offset];
    $stmt_students = $pdo->prepare($sql_students);

    // Bind parameters dynamically
    for ($i = 0; $i < count($student_params); $i++) {
        // Use PARAM_INT for all parameters as they are expected to be integers
        $stmt_students->bindValue($i + 1, $student_params[$i], PDO::PARAM_INT);
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
            'is_manually_locked' => (bool)$student['is_manually_locked']
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
