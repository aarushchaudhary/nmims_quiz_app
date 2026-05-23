<?php
/**
 * router.php
 * Router for PHP's Built-in Development Server
 * 
 * This script allows PHP's built-in server to serve static files properly.
 * Without this router, the server would not serve CSS, JS, images, etc.
 * 
 * Usage: php -S localhost:8080 router.php
 */

// Get the requested file path
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requested_file = __DIR__ . $path;

// If the file exists and is not a directory, serve it
if (is_file($requested_file)) {
    // Return the file (PHP will automatically set the correct MIME type)
    return false;
}

// If it's a directory or doesn't exist, route to index.php
// This allows for clean URLs like /admin/dashboard instead of /views/admin/dashboard.php
if (is_dir($requested_file) || !file_exists($requested_file)) {
    include __DIR__ . '/index.php';
}
