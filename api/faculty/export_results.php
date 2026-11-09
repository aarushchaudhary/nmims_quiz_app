<?php
/*
 * api/faculty/export_results.php
 * Excel download of one of this faculty's quizzes. Built in config/results.php.
 */
require_once '../../config/database.php';
require_once '../../config/authz.php';
require_once '../../config/results.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    exit('Access Denied.');
}
$quiz_id = filter_var($_GET['quiz_id'] ?? null, FILTER_VALIDATE_INT);
if (!$quiz_id) {
    exit('Invalid Quiz ID.');
}
require_owned_quiz($pdo, $quiz_id, (int) $_SESSION['user_id'], false);

send_results_xlsx($pdo, $quiz_id);
