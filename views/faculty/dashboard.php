<?php
  // Set page-specific variables
  $pageTitle = 'Faculty Dashboard';
  
  // Include the header template
  require_once '../../assets/templates/header.php';

  // --- Authorization Check ---
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
      // If the user is not a faculty member, redirect them to the login page
      redirect('login.php');
      exit();
  }
  
  // **FIX:** Changed 'name' to 'full_name' to match the correct session variable.
  $facultyName = isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Faculty';
?>

<!-- The main content for the faculty dashboard -->
<div class="dashboard-center-content">
  <div class="welcome-message">
    Welcome, <?php echo $facultyName; ?>!
  </div>
  
  <!-- Button group for faculty actions -->
  <div class="button-group">
    <a href="create_quiz.php" class="button-red">Create Quiz</a>
    <a href="manage_quizzes.php" class="button-red">Manage Quizzes</a>
    <a href="reports.php" class="button-red">View Results</a>
  </div>
</div>

<?php
  // Include the footer template to close the page
  require_once '../../assets/templates/footer.php';
?>
