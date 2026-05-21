<?php
  $pageTitle = 'Manage Courses';
  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  // --- Authorization Check ---
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
      redirect('login.php');
      exit();
  }

  // Fetch all schools for the 'Add' form dropdown
  $schools = $pdo->query("SELECT * FROM schools ORDER BY name ASC")->fetchAll();
  
  // Fetch all existing courses with their school name
  $courses_sql = "SELECT c.id, c.name, c.code, s.name as school_name 
                  FROM courses c 
                  JOIN schools s ON c.school_id = s.id 
                  ORDER BY s.name, c.name ASC";
  $courses = $pdo->query($courses_sql)->fetchAll();
?>

<div class="manage-container">
    <a href="dashboard.php" style="text-decoration: none; color: #007bff; margin-bottom: 20px; display: inline-block;">&larr; Back to Dashboard</a>
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h2>All Courses (<?php echo count($courses); ?>)</h2>
    </div>

    <?php
    if (isset($_GET['success'])) { echo '<div class="message-box success-message">' . htmlspecialchars($_GET['success']) . '</div>'; }
    if (isset($_GET['error'])) { echo '<div class="message-box error-message">' . htmlspecialchars($_GET['error']) . '</div>'; }
    ?>
    
    <div class="section-box">
        <h3>Add New Course</h3>
        <form action="<?= get_base_url() ?>api/admin/add_course.php" method="POST" class="form-container" style="padding:0; box-shadow:none;">
            <div class="form-row">
                <div class="form-group">
                    <label for="course_name">Course Name</label>
                    <input type="text" id="course_name" name="course_name" class="input-field" required>
                </div>
                <div class="form-group">
                    <label for="course_code">Course Code</label>
                    <input type="text" id="course_code" name="course_code" class="input-field" required>
                </div>
            </div>
            <div class="form-group">
                <label for="school_id">School</label>
                <select id="school_id" name="school_id" class="input-field" required>
                    <option value="" disabled selected>-- Select a School --</option>
                    <?php foreach ($schools as $school): ?>
                    <option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="button-red" style="width:auto;">Add Course</button>
        </form>
    </div>

    <table class="data-table">
        <thead>
            <tr><th>Course Name</th><th>Code</th><th>School</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($courses as $course): ?>
                <tr>
                    <td><?php echo htmlspecialchars($course['name']); ?></td>
                    <td><?php echo htmlspecialchars($course['code']); ?></td>
                    <td><?php echo htmlspecialchars($course['school_name']); ?></td>
                    <td class="action-buttons" style="flex-direction:row;">
                        <a href="<?= get_base_url() ?>api/admin/delete_course.php?id=<?php echo $course['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this course?')" style="background-color:#dc3545; color:white; border:none; padding: 8px 12px; font-size: 14px; border-radius: 6px; cursor:pointer;">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
  require_once '../../assets/templates/footer.php';
?>
