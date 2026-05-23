<?php
/**
 * database.php
 * Database connection configuration for NMIMS Quiz App
 *
 * PDO (PHP Data Objects) is used for secure database access
 * with prepared statements to prevent SQL injection.
 */

// Suppress errors and warnings to prevent corrupting JSON responses
// Errors will be logged instead
error_reporting(E_ALL);
ini_set("display_errors", 0); // Don't display errors in output
ini_set("log_errors", 1); // Log errors to error log

// Set up error handler to output JSON for API endpoints
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    if (php_sapi_name() !== "cli") {
        // Only affect web requests, not CLI
        header("Content-Type: application/json", true);
        http_response_code(500);
        echo json_encode([
            "error" => "An internal server error occurred. Please try again.",
        ]);
        exit();
    }
    return false; // Let PHP's internal error handler also run
});

// Set up exception handler to output JSON
set_exception_handler(function ($e) {
    error_log("Exception: " . $e->getMessage());
    header("Content-Type: application/json", true);
    http_response_code(500);
    echo json_encode([
        "error" => "An internal server error occurred. Please try again.",
    ]);
    exit();
});

// Require base URL configuration
require_once __DIR__ . "/base_url.php";

// --- MariaDB/MySQL Credentials ---
define("DB_HOST", "127.0.0.1");
define("DB_PORT", "3306");
define("DB_NAME", "nmims_quiz_app");
define("DB_USER", "nmims_quiz_app");
define("DB_PASS", "123456");

// --- Data Source Name (DSN) ---
$dsn =
    "mysql:host=" .
    DB_HOST .
    ":" .
    DB_PORT .
    ";dbname=" .
    DB_NAME .
    ";charset=utf8mb4";

// --- PDO Connection Options ---
$options = [
    // Throw an exception if a database error occurs
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    // Use the default fetch mode (associative array)
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Disable emulation of prepared statements for security
    PDO::ATTR_EMULATE_PREPARES => false,
];

// --- Create PDO Instance ---
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    http_response_code(500);
    header("Content-Type: application/json");
    echo json_encode([
        "error" =>
            "Database connection failed. Please check server configuration.",
    ]);
    exit();
}
