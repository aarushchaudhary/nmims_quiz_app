<?php
  session_start();
  // Dynamically set the page title
  $roleName = isset($_SESSION['role_name']) ? ucfirst($_SESSION['role_name']) : 'Placement';
  $pageTitle = $roleName . ' Dashboard';
  
  require_once '../../assets/templates/header.php';

  // --- Authorization Check for Placement Officer (role_id = 3) ---
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
      redirect('login.php');
      exit();
  }
  
  $placecomName = isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Placement Officer';
?>

<div class="manage-container">
    <h2 style="margin-bottom: 10px;">Welcome, <?php echo $placecomName; ?>!</h2>
    <p style="text-align:center; color: #555; margin-top:0;">From here you can access reports for all quizzes conducted on the platform.</p>
    
    <div class="section-box" style="text-align:center;">
        <h3>Placement Tools</h3>
        <div class="button-group" style="justify-content:center;">
            <a href="reports.php" class="button-red" style="width:auto;">View All Quiz Reports</a>
            <a href="../shared/event_log_report.php" class="button-red" style="width:auto; background-color:#6c757d;">View Event Logs</a>
        </div>
    </div>
</div>

<?php
  require_once '../../assets/templates/footer.php';
?>
