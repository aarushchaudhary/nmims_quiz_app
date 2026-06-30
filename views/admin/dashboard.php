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

<!-- How to Use Modal -->
<div class="confirm-modal-overlay" id="how-to-use-modal">
    <div class="confirm-modal" style="max-width: 700px; max-height: 85vh; overflow-y: auto; text-align: left; padding: 30px;">
        <h3 style="text-align: center; margin-bottom: 20px; font-size: 22px;">📖 How to Use — Admin Guide</h3>

        <h4 style="text-align: center; color: #333; margin-bottom: 15px; border-bottom: 2px solid #e60000; padding-bottom: 8px;">Getting Started (Follow in Order)</h4>

        <div style="margin-bottom: 20px;">
            <h4 style="color: #17a2b8; margin-bottom: 6px;">Step 1: Set Up Schools</h4>
            <p style="color: #555; margin: 0; line-height: 1.6;">Click <strong>Manage Schools</strong> and add every school or department in your institution (e.g., STME, SBM, SOL). Schools are the top-level grouping — everything else (courses, students) falls under a school.</p>
        </div>

        <div style="margin-bottom: 20px;">
            <h4 style="color: #ffc107; margin-bottom: 6px;">Step 2: Add Courses</h4>
            <p style="color: #555; margin: 0; line-height: 1.6;">Click <strong>Manage Courses</strong> and add courses under each school (e.g., B.Tech CSE under STME, MBA under SBM). Each course belongs to one school. Students and quizzes are linked to courses.</p>
        </div>

        <div style="margin-bottom: 20px;">
            <h4 style="color: #e60000; margin-bottom: 6px;">Step 3: Create Exam Groups</h4>
            <p style="color: #555; margin: 0; line-height: 1.6;">Click <strong>Exam Groups</strong> to create <strong>Classes</strong>, <strong>Batches</strong>, <strong>Electives</strong>, or <strong>Re-Exam Groups</strong>. These define which students appear in a quiz by SAP ID range. Faculty will use these groups when creating quizzes.</p>
        </div>

        <div style="margin-bottom: 20px;">
            <h4 style="color: #28a745; margin-bottom: 6px;">Step 4: Add Users</h4>
            <p style="color: #555; margin: 0; line-height: 1.6;">Click <strong>Manage Users</strong> → then <strong>Add New User</strong> to create accounts one by one. For bulk student import, use the <strong>Upload Students</strong> button on the dashboard with an Excel file.</p>
            <ul style="color: #555; margin: 8px 0 0 20px; line-height: 1.8;">
                <li><strong>Admin</strong> — Full system access, manages everything</li>
                <li><strong>Faculty</strong> — Creates quizzes, adds questions, runs exams, views results</li>
                <li><strong>Placecom</strong> — Read-only access to all quiz reports across all schools</li>
                <li><strong>Student</strong> — Takes exams and views published results. Enter their 11-digit SAP ID and school/course auto-fills</li>
                <li><strong>School Head</strong> — Views quiz reports filtered to their assigned school only</li>
            </ul>
        </div>

        <hr style="border: none; border-top: 2px solid #6c757d; margin: 25px 0;">

        <h4 style="text-align: center; color: #333; margin-bottom: 15px; border-bottom: 2px solid #6c757d; padding-bottom: 8px;">Dashboard Buttons Reference</h4>

        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 10px 8px; font-weight: bold; color: #e60000; width: 35%; white-space: nowrap;">Manage Users</td>
                <td style="padding: 10px 8px; color: #555;">View, search, edit, delete, and reset passwords for all users in the system.</td>
            </tr>
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 10px 8px; font-weight: bold; color: #17a2b8;">Manage Schools</td>
                <td style="padding: 10px 8px; color: #555;">Add, edit, or remove schools/departments (e.g., STME, SBM).</td>
            </tr>
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 10px 8px; font-weight: bold; color: #b8860b;">Manage Courses</td>
                <td style="padding: 10px 8px; color: #555;">Add, edit, or remove courses. Each course is linked to a school.</td>
            </tr>
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 10px 8px; font-weight: bold; color: #e60000;">Create Student Groups</td>
                <td style="padding: 10px 8px; color: #555;">Manage Sections, Batches, Electives, and Re-Exam Groups. These define which students get assigned to quizzes by SAP ID ranges.</td>
            </tr>
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 10px 8px; font-weight: bold; color: #6f42c1;">Manage Roles</td>
                <td style="padding: 10px 8px; color: #555;">View and manage user roles available in the system (Admin, Faculty, Placecom, Student, Head).</td>
            </tr>
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 10px 8px; font-weight: bold; color: #dc3545;">Data Cleanup</td>
                <td style="padding: 10px 8px; color: #555;">Remove old or orphaned data from the database such as expired re-exam groups, unused quiz data, or stale records.</td>
            </tr>
            <tr>
                <td style="padding: 10px 8px; font-weight: bold; color: #fd7e14;">Demote Students</td>
                <td style="padding: 10px 8px; color: #555;">Move students to a different graduation year (e.g., when a student is held back). Updates their batch/class assignments accordingly.</td>
            </tr>
        </table>

        <div style="text-align: center; margin-top: 25px;">
            <button class="button-red" id="close-how-to-use" style="width: auto; padding: 10px 40px;">Got it!</button>
        </div>
    </div>
</div>

<div class="manage-container">
    <h2 style="margin-bottom: 10px;">Welcome, <?php echo $adminName; ?>!</h2>
    <p style="text-align:center; color: #555; margin-top:0;">Here's a summary of the application activity.</p>
    
    <div style="text-align: center; margin-bottom: 15px;">
        <button class="button-red" id="how-to-use-btn" style="width:auto; padding: 8px 20px; background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); font-size: 14px; border: none; cursor: pointer; border-radius: 6px; color: white;">📖 How to Use</button>
    </div>

    <div class="dashboard-grid" id="dashboard-grid">
        </div>

    <div class="section-box" style="text-align:center;">
        <h3>Admin Tools</h3>
        <div class="button-group" style="justify-content:center; flex-wrap:wrap;">
            <a href="user_management.php" class="button-red" style="width:auto;">Manage Users</a>
            <a href="manage_schools.php" class="button-red" style="width:auto; background-color:#17a2b8;">Manage Schools</a>
            <a href="manage_courses.php" class="button-red" style="width:auto; background-color:#ffc107; color:#333;">Manage Courses</a>
            <a href="exam_groups.php" class="button-red">Create Student Groups</a>
            <a href="manage_roles.php" class="button-red" style="width:auto; background-color:#6f42c1;">Manage Roles</a>
            <a href="cleanup.php" class="button-red" style="width:auto; background-color:#dc3545;">Data Cleanup</a>
            <a href="demote_students.php" class="button-red" style="width:auto; background-color:#fd7e14;">Demote Students</a>
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

    // --- How to Use Modal Logic ---
    const howToUseModal = document.getElementById('how-to-use-modal');
    document.getElementById('how-to-use-btn').addEventListener('click', function() {
        howToUseModal.style.display = 'flex';
    });
    document.getElementById('close-how-to-use').addEventListener('click', function() {
        howToUseModal.style.display = 'none';
    });
    howToUseModal.addEventListener('click', function(e) {
        if (e.target === howToUseModal) {
            howToUseModal.style.display = 'none';
        }
    });
});
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>
