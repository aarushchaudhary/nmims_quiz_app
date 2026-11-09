<?php
/*
 * auth.php
 * Handles user authentication with single-session enforcement.
 */
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) {
    // SameSite=Lax: the browser will not send this cookie on cross-site POSTs (CSRF).
    // HttpOnly: page scripts cannot read it, so an injected script cannot steal the session.
    session_start(['cookie_samesite' => 'Lax', 'cookie_httponly' => true]);
}
require_once '../config/database.php';

$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? '';
$password = $data['password'] ?? '';
$force_login = $data['force'] ?? false;

// After this many wrong passwords in a row from one machine, that machine is locked out of
// this account for LOCKOUT_MINUTES. Keyed by email + IP, so a classmate guessing from their
// own computer can't lock the real owner out.
const MAX_FAILED_LOGINS = 5;
const LOCKOUT_MINUTES   = 15;
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

// Checked before the password, so guesses made while locked are not even tried.
$stmt_lock = $pdo->prepare('SELECT 1 FROM login_failures WHERE email = ? AND ip_address = ? AND locked_until > NOW()');
$stmt_lock->execute([$email, $ip]);
if ($stmt_lock->fetchColumn()) {
    http_response_code(429);
    exit(json_encode(['status' => 'error',
        'message' => 'Too many failed login attempts. Try again in ' . LOCKOUT_MINUTES . ' minutes.']));
}

// Fetch user data
$sql_user = "SELECT u.id, u.password_hash, u.active_session_id, u.role_id, u.must_change_password, s.sap_id
             FROM users u
             LEFT JOIN students s ON u.id = s.user_id
             WHERE u.email = :email AND u.is_active = 1";
$stmt_user = $pdo->prepare($sql_user);
$stmt_user->execute(['email' => $email]);
$user = $stmt_user->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    if ($user) { // only real accounts, so random emails don't fill the table
        // locked_until is assigned first: MariaDB applies SET left to right, so it must see the old count.
        $pdo->prepare('INSERT INTO login_failures (email, ip_address, failed_count) VALUES (?, ?, 1)
                       ON DUPLICATE KEY UPDATE
                           locked_until = IF(failed_count + 1 >= ?, NOW() + INTERVAL ? MINUTE, NULL),
                           failed_count = IF(failed_count + 1 >= ?, 0, failed_count + 1)')
            ->execute([$email, $ip, MAX_FAILED_LOGINS, LOCKOUT_MINUTES, MAX_FAILED_LOGINS]);
    }
    http_response_code(401);
    exit(json_encode(['status' => 'error', 'message' => 'Invalid credentials.']));
}

// Correct password: forget this machine's earlier failures.
$pdo->prepare('DELETE FROM login_failures WHERE email = ? AND ip_address = ?')->execute([$email, $ip]);

// Check for an existing active session
if (!empty($user['active_session_id']) && !$force_login) {
    // User is logged in elsewhere and this is the first login attempt
    exit(json_encode(['status' => 'conflict', 'message' => 'This account is already logged in elsewhere. Continuing will log out the other session.']));
}

// --- Proceed with Login ---
session_regenerate_id(true); // Create a new session ID
$new_session_id = session_id();

// Update the user's record with the new session ID
$sql_update = "UPDATE users SET active_session_id = ? WHERE id = ?";
$stmt_update = $pdo->prepare($sql_update);
$stmt_update->execute([$new_session_id, $user['id']]);

// Fetch full user details and set session variables
$sql_details = "SELECT r.name as role_name, COALESCE(s.name, f.name, p.name, a.name, h.name) as full_name
                FROM users u
                JOIN roles r ON u.role_id = r.id
                LEFT JOIN students s ON u.id = s.user_id
                LEFT JOIN faculties f ON u.id = f.user_id
                LEFT JOIN placecom_officers p ON u.id = p.user_id
                LEFT JOIN admins a ON u.id = a.user_id
                LEFT JOIN heads h ON u.id = h.user_id
                WHERE u.id = ?";
$stmt_details = $pdo->prepare($sql_details);
$stmt_details->execute([$user['id']]);
$details = $stmt_details->fetch();

$_SESSION['user_id'] = $user['id'];
$_SESSION['role_id'] = $user['role_id'];
$_SESSION['full_name'] = $details['full_name'];
$_SESSION['role_name'] = $details['role_name'];

// Temporary passwords must be changed; so must the old SAP-ID default on older accounts.
$_SESSION['force_password_change'] = $user['must_change_password']
    || ($user['role_id'] == 4 && $password === $user['sap_id']);

echo json_encode(['status' => 'success', 'message' => 'Login successful.']);
