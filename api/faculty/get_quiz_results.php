<?php
/*
 * api/faculty/get_quiz_results.php
 * Results of one of this faculty's quizzes (JSON). Table built in config/results.php.
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../config/authz.php';
require_once '../../config/results.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    json_fail(403, 'Unauthorized access.');
}
$quiz_id = filter_var($_GET['quiz_id'] ?? null, FILTER_VALIDATE_INT);
if (!$quiz_id) {
    json_fail(400, 'Invalid or missing quiz ID.');
}
require_owned_quiz($pdo, $quiz_id, (int) $_SESSION['user_id']);

echo json_encode(quiz_results($pdo, $quiz_id));
