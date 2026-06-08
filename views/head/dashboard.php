<?php
  session_start();
  // Dynamically set the page title
  $roleName = isset($_SESSION['role_name']) ? ucfirst($_SESSION['role_name']) : 'School Head';
  $pageTitle = $roleName . ' Dashboard';
  
  require_once '../../assets/templates/header.php';

  // --- Authorization Check for School Head (role_id = 5) ---
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 5) {
      // Note: redirect() is a helper from base_url.php
      header('Location: ' . get_base_url() . 'login.php');
      exit();
  }
  
  $headName = isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'School Head';
?>

<div class="manage-container">
    <h2 style="margin-bottom: 10px;">Welcome, <?php echo $headName; ?>!</h2>
    <p style="text-align:center; color: #555; margin-top:0;">From here you can oversee all quizzes and reports for your school.</p>
    
    <div class="section-box" style="text-align:center;">
        <h3>School Tools</h3>
        <div class="button-group" style="justify-content:center;">
            <a href="../placecom/reports.php" class="button-red" style="width:auto;">View Quiz Reports</a>
        </div>
    </div>
</div>

<?php
  require_once '../../assets/templates/footer.php';
?>
