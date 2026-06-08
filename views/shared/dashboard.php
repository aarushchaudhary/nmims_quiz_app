<?php
  session_start();
  // Dynamically set the page title from the session
  $roleName = isset($_SESSION['role_name']) ? ucfirst($_SESSION['role_name']) : 'User';
  $pageTitle = $roleName . ' Dashboard';
  
  require_once '../../assets/templates/header.php';

  // --- Authorization Check ---
  if (!isset($_SESSION['user_id'])) {
      redirect('login.php');
      exit();
  }
  
  $userName = isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : $roleName;
?>

<div class="manage-container">
    <h2 style="margin-bottom: 10px;">Welcome, <?php echo $userName; ?>!</h2>
    <p style="text-align:center; color: #555; margin-top:0;">From here you can access reports for quizzes conducted on the platform.</p>
    
    <div class="section-box" style="text-align:center;">
        <h3>Tools</h3>
        <div class="button-group" style="justify-content:center;">
            <a href="../placecom/reports.php" class="button-red" style="width:auto;">View All Quiz Reports</a>
        </div>
    </div>
</div>

<?php
  require_once '../../assets/templates/footer.php';
?>
