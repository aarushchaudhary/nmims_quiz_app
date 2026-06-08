<?php
  $pageTitle = 'Admin Dashboard';
  require_once '../../assets/templates/header.php';

  // --- Authorization Check for Admin (role_id = 1) ---
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
      redirect('login.php');
      exit();
  }
  
  // **FIX:** Changed 'name' to 'full_name' to match the correct session variable.
  $adminName = isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Admin';
?>

<div class="manage-container">
    <h2 style="margin-bottom: 10px;">Welcome, <?php echo $adminName; ?>!</h2>
    <p style="text-align:center; color: #555; margin-top:0;">Here's a summary of the application activity.</p>
    
    <div class="dashboard-grid" id="dashboard-grid">
        </div>

    <div class="section-box" style="text-align:center;">
        <h3>Admin Tools</h3>
        <div class="button-group" style="justify-content:center; flex-wrap:wrap;">
            <a href="user_management.php" class="button-red" style="width:auto;">Manage Users</a>
            <a href="manage_schools.php" class="button-red" style="width:auto; background-color:#17a2b8;">Manage Schools</a>
            <a href="manage_courses.php" class="button-red" style="width:auto; background-color:#ffc107; color:#333;">Manage Courses</a>
            <a href="exam_groups.php" class="button-red">Exam Groups</a>
            <a href="manage_roles.php" class="button-red" style="width:auto; background-color:#6f42c1;">Manage Roles</a>
        </div>
    </div>
</div>

<script>
const BASE_URL = '<?= get_base_url() ?>';
// This script remains the same to load the stats
document.addEventListener('DOMContentLoaded', async function() {
    try {
        const response = await fetch(BASE_URL + 'api/admin/get_dashboard_stats.php');
        if (!response.ok) throw new Error('Failed to fetch stats.');
        const stats = await response.json();
        
        const grid = document.getElementById('dashboard-grid');
        grid.innerHTML = `
            <div class="dashboard-card card-students"><div class="card-icon">&#127891;</div><div class="card-info"><p class="card-title">Total Students</p><p class="card-number">${stats.students || 0}</p></div></div>
            <div class="dashboard-card card-faculty"><div class="card-icon">&#129489;&#8205;&#127979;</div><div class="card-info"><p class="card-title">Total Faculty</p><p class="card-number">${stats.faculty || 0}</p></div></div>
            <div class="dashboard-card card-quizzes"><div class="card-icon">&#128221;</div><div class="card-info"><p class="card-title">Total Quizzes</p><p class="card-number">${stats.quizzes || 0}</p></div></div>
            <div class="dashboard-card card-active"><div class="card-icon">&#128308;</div><div class="card-info"><p class="card-title">Active Quizzes</p><p class="card-number">${stats.active_quizzes || 0}</p></div></div>
        `;

    } catch (error) {
        console.error("Error loading dashboard stats:", error);
    }
});
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>
