<?php
/*
 * index.php
 * The main router for the application.
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config/base_url.php';

if (isset($_SESSION['user_id']) && isset($_SESSION['role_id'])) {
    switch ($_SESSION['role_id']) {
        case 1: // Admin
            redirect('views/admin/dashboard.php');
        case 2: // Faculty
            redirect('views/faculty/dashboard.php');
        case 3: // Placecom Officer
            redirect('views/placecom/dashboard.php');
        case 4: // Student
            redirect('views/student/dashboard.php');
        case 5: // School Head
            redirect('views/head/dashboard.php');
        // **NEW:** A default case to handle all other roles
        default:
            redirect('views/shared/dashboard.php');
    }
} else {
    redirect('login.php');
}
