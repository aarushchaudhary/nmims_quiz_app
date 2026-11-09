<?php
/*
 * api/shared/export_all_results.php
 * Excel download of any quiz for staff: any non-student role; school heads only their school.
 * Built in config/results.php.
 */
require_once '../../config/database.php';
require_once '../../config/authz.php';
require_once '../../config/results.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] == 4) {
    exit('Access Denied.');
}
$quiz_id = filter_var($_GET['quiz_id'] ?? null, FILTER_VALIDATE_INT);
if (!$quiz_id) {
    exit('Invalid Quiz ID.');
}
if (!head_can_view_quiz($pdo, $quiz_id)) {
    exit('Access Denied.');
}

send_results_xlsx($pdo, $quiz_id);
