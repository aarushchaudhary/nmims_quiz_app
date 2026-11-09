<?php
/*
 * api/placecom/get_all_quiz_results.php
 * Results of any quiz (JSON) for staff: any non-student role; school heads only their school.
 * Table built in config/results.php.
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../config/authz.php';
require_once '../../config/results.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] == 4) {
    json_fail(403, 'Unauthorized access.');
}
$quiz_id = filter_var($_GET['quiz_id'] ?? null, FILTER_VALIDATE_INT);
if (!$quiz_id) {
    json_fail(400, 'Invalid or missing quiz ID.');
}
if (!head_can_view_quiz($pdo, $quiz_id)) {
    json_fail(403, 'Not found or not authorized.');
}

echo json_encode(quiz_results($pdo, $quiz_id));
