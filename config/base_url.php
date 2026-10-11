<?php
/**
 * base_url.php
 * Centralized Base URL configuration for NMIMS Quiz App
 * 
 * This file provides a unified way to handle URLs across different environments:
 * - XAMPP/Apache subdirectory: /nmims_quiz_app/
 * - Built-in server root: /
 * - Environment variables or $_ENV can be used for runtime configuration
 */

// Detect environment and set BASE_URL accordingly
$environment = $_ENV['APP_ENV'] ?? 'development';

if ($environment === 'production') {
    // For production, configure based on your actual domain
    define('BASE_URL', $_ENV['BASE_URL'] ?? 'http://localhost:8080/');
} else {
    // For development (built-in server), use root path
    // For XAMPP, change to '/nmims_quiz_app/'
    define('BASE_URL', '/');
}

// Additional utility functions for path handling
function get_base_url() {
    return BASE_URL;
}

function get_asset_url($path) {
    return BASE_URL . ltrim($path, '/');
}

function get_api_url($path) {
    return BASE_URL . 'api/' . ltrim($path, '/');
}

function redirect($path) {
    $full_url = BASE_URL . ltrim($path, '/');
    header('Location: ' . $full_url);
    exit();
}


/**
 * Migration Notes:
 * - For XAMPP (Apache in subdirectory): Change BASE_URL to '/nmims_quiz_app/'
 * - For built-in server (port 8080): Keep BASE_URL as '/'
 * - For Docker/production: Set BASE_URL via environment variable
 * 
 * Usage:
 * - In PHP: echo get_asset_url('assets/css/main.css');
 * - In HTML: <link href="<?= get_asset_url('assets/css/main.css') ?>">
 * - For redirects: redirect('login.php?error=db_error');
 */
