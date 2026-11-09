<?php
/*
 * logout.php
 * This script handles the user logout process.
 */

// 1. Start or resume the current session
// This is necessary to access and then destroy the session data.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Unset all of the session variables.
// This clears all data stored in the $_SESSION superglobal array.
// Free the account so the next login is not reported as "logged in elsewhere".
require_once __DIR__ . '/config/database.php';
if (!empty($_SESSION['user_id'])) {
    $pdo->prepare('UPDATE users SET active_session_id = NULL WHERE id = ? AND active_session_id = ?')
        ->execute([$_SESSION['user_id'], session_id()]);
}

$_SESSION = array();

// 3. If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Finally, destroy the session.
// This function releases all session data and ends the session.
session_destroy();

// 5. Redirect the user to the login page.
// A 'logout=success' parameter can be used to optionally show a message.
header('Location: login.php?logout=success');
exit(); // Ensure no further code is executed after the redirect.
