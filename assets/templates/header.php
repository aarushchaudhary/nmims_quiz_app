<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Load base URL configuration
require_once __DIR__ . '/../../config/base_url.php';


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'NMIMS Quiz App'; ?></title>
    
    <link rel="icon" type="image/png" href="<?= get_asset_url('assets/images/favicon.jpg') ?>">
    
    <!-- Consolidated stylesheet: Replaces base.css, components.css, login.css, manage.css, exam.css -->
    <link rel="stylesheet" href="<?= get_asset_url('assets/css/main.css') ?>" />
</head>
<body>
    <header class="ribbon">
        <img src="<?= get_asset_url('assets/images/logostme.png') ?>" alt="Logo" class="logo" />
        <h1 class="site-title"><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'NMIMS Quiz App'; ?></h1>
        
        <div class="header-buttons">
            <?php 
            // **CRITICAL FIX:** This PHP block now checks if it's the exam page.
            // The buttons will only be displayed if $isExamPage is NOT set to true.
            if (isset($_SESSION['user_id']) && (!isset($isExamPage) || $isExamPage !== true)): 
            ?>

                
                <a href="<?= get_base_url() ?>index.php" class="home-button">Home</a>
                <a href="<?= get_base_url() ?>logout.php" class="logout-button">Logout</a>
            <?php endif; ?>
        </div>
    </header>
    
    <main>
