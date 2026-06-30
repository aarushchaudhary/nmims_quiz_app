<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Load base URL configuration
require_once __DIR__ . '/../../config/base_url.php';

// --- Global Password Change Enforcement ---
$currentScript = $_SERVER['SCRIPT_FILENAME'] ?? '';
$isChangePasswordPage = strpos(str_replace('\\', '/', $currentScript), '/views/student/change_password.php') !== false;
$isLogoutPage = strpos(str_replace('\\', '/', $currentScript), '/logout.php') !== false;
$isApi = strpos(str_replace('\\', '/', $currentScript), '/api/') !== false;

if (!empty($_SESSION['force_password_change']) && !$isChangePasswordPage && !$isLogoutPage && !$isApi) {
    header('Location: ' . get_base_url() . 'views/student/change_password.php');
    exit();
}
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
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <script>
        window.BASE_URL = '<?= get_base_url() ?>';
    </script>
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

                <?php
                // Show Back button on all pages EXCEPT student pages
                $currentScript = $_SERVER['SCRIPT_FILENAME'] ?? '';
                $isStudentPage = strpos(str_replace('\\', '/', $currentScript), '/views/student/') !== false;
                if (!$isStudentPage):
                    $backText = isset($customBackButtonText) ? $customBackButtonText : '&#8592; Back';
                    $backUrl = isset($customBackButtonUrl) ? $customBackButtonUrl : 'javascript:history.back()';
                ?>
                <a href="<?php echo $backUrl; ?>" class="back-button"><?php echo $backText; ?></a>
                <?php endif; ?>

                <?php
                if (isset($customProceedButtonText) && isset($customProceedButtonUrl)):
                ?>
                <a href="<?php echo $customProceedButtonUrl; ?>" class="back-button" style="background-color: #28a745;"><?php echo $customProceedButtonText; ?></a>
                <?php endif; ?>
                
                <a href="<?= get_base_url() ?>index.php" class="home-button">Home</a>
                <a href="<?= get_base_url() ?>logout.php" class="logout-button">Logout</a>
            <?php endif; ?>
        </div>
    </header>
    
    <main>
