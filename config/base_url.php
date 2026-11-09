<?php
/**
 * base_url.php
 * The URL path the app is served under, used for every link, asset and redirect.
 *
 * Set BASE_URL in the environment (Apache: `SetEnv BASE_URL /quiz/`):
 * - "/"       app at the root of a domain or virtual host (default; also `php -S`)
 * - "/quiz/"  app in a sub-folder of the web root
 * getenv() is used, not $_ENV: production php.ini leaves $_ENV empty (variables_order = "GPCS").
 */
define('BASE_URL', rtrim(getenv('BASE_URL') ?: '/', '/') . '/');

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

/*
 * Usage:
 * - In PHP: echo get_asset_url('assets/css/main.css');
 * - In HTML: <link href="<?= get_asset_url('assets/css/main.css') ?>">
 * - For redirects: redirect('login.php?error=db_error');
 */
