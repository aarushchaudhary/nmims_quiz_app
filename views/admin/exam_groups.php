<?php
  $pageTitle = 'Create Student Groups';
  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
      redirect('login.php');
      exit();
  }
?>


<div class="dashboard-center-content" style="width: 100%;">
    <div class="exam-groups-dashboard">
        <a href="batches.php" class="button-red">Create Batches</a>
        <a href="classes.php" class="button-red">Create Sections</a>
        <a href="electives.php" class="button-red">Create Electives & Add Students</a>
        <a href="re_exam_groups.php" class="button-red">Create Re-Exam Groups</a>
    </div>
</div>

<?php
  require_once '../../assets/templates/footer.php';
?>