<?php
/*
 * api/faculty/update_quiz_status.php
 * Handles updating the status of a quiz for AJAX requests.
 */
header('Content-Type: application/json'); // This is crucial
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../../config/database.php';

// --- Authorization & Request Method Check ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'Access denied.']));
}

$data = json_decode(file_get_contents('php://input'), true);
$quiz_id = $data['quiz_id'] ?? null;
$new_status_id = $data['new_status_id'] ?? null;
$faculty_id = $_SESSION['user_id'];

// --- Validation ---
if (!is_numeric($quiz_id) || !is_numeric($new_status_id)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Invalid request data.']));
}

try {
    $pdo->beginTransaction();
    
    // --- Verify Question Requirements if Starting ---
    if ($new_status_id == 2 || $new_status_id == 3) {
        $stmt_quiz = $pdo->prepare("SELECT config_easy_count, config_medium_count, config_hard_count FROM quizzes WHERE id = :quiz_id AND faculty_id = :faculty_id");
        $stmt_quiz->execute([':quiz_id' => $quiz_id, ':faculty_id' => $faculty_id]);
        $quiz = $stmt_quiz->fetch();
        
        if ($quiz) {
            $q_stmt = $pdo->prepare("SELECT difficulty_id, COUNT(*) as count FROM questions WHERE quiz_id = :quiz_id GROUP BY difficulty_id");
            $q_stmt->execute([':quiz_id' => $quiz_id]);
            $actual_counts = [];
            while ($row = $q_stmt->fetch()) {
                $actual_counts[$row['difficulty_id']] = $row['count'];
            }
            
            $actual_easy = $actual_counts[1] ?? 0;
            $actual_medium = $actual_counts[2] ?? 0;
            $actual_hard = $actual_counts[3] ?? 0;
            
            if ($actual_easy < $quiz['config_easy_count'] || $actual_medium < $quiz['config_medium_count'] || $actual_hard < $quiz['config_hard_count']) {
                throw new Exception('Cannot start quiz. Required questions have not been added yet.');
            }
        }
    }

    $sql = "UPDATE quizzes SET status_id = :new_status_id WHERE id = :quiz_id AND faculty_id = :faculty_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':new_status_id' => $new_status_id,
        ':quiz_id' => $quiz_id,
        ':faculty_id' => $faculty_id
    ]);

    if ($stmt->rowCount() > 0) {
        // Fetch the new status name to send back to the client
        $stmt_status = $pdo->prepare("SELECT name FROM exam_statuses WHERE id = ?");
        $stmt_status->execute([$new_status_id]);
        $new_status_name = $stmt_status->fetchColumn();

        $pdo->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Quiz status updated successfully.',
            'new_status_name' => $new_status_name
        ]);
    } else {
        throw new Exception('No changes made or you are not authorized to perform this action.');
    }

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    error_log("Quiz status update failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
