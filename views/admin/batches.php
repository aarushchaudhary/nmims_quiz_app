<?php
  $pageTitle = 'Manage Batch';
  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
      redirect('login.php');
      exit();
  }

  // Fetch all schools
  $schools = $pdo->query("SELECT * FROM schools ORDER BY name ASC")->fetchAll();
  
  // Fetch all courses
  $courses = $pdo->query("SELECT * FROM courses ORDER BY name ASC")->fetchAll();
  $coursesBySchool = [];
  foreach ($courses as $course) {
      $coursesBySchool[$course['school_id']][] = $course;
  }

  // Fetch classes
  $classes = $pdo->query("
      SELECT c.*, s.name as school_name, co.name as course_name 
      FROM classes c 
      JOIN schools s ON c.school_id = s.id 
      JOIN courses co ON c.course_id = co.id 
      ORDER BY c.name ASC
  ")->fetchAll();
?>

<style>
.autocomplete-container {
    position: relative;
}
.autocomplete-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #ccc;
    border-top: none;
    z-index: 1000;
    max-height: 200px;
    overflow-y: auto;
    border-radius: 0 0 4px 4px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.autocomplete-item {
    padding: 8px 12px;
    cursor: pointer;
    font-size: 14px;
}
.autocomplete-item:hover {
    background-color: #f0f0f0;
}
</style>
<div class="manage-container">

    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h2>All Batches (<?php echo count($classes); ?>)</h2>
    </div>

    <?php
    if (isset($_GET['success'])) { echo '<div class="message-box success-message">' . htmlspecialchars($_GET['success']) . '</div>'; }
    if (isset($_GET['error'])) { echo '<div class="message-box error-message">' . htmlspecialchars($_GET['error']) . '</div>'; }
    ?>
    
    <div class="section-box">
    <div class="section-box">
        <h3>Add Batches</h3>
        
        <div class="message-box" style="background-color: #e8f4fd; color: #0056b3; border: 1px solid #b8daff; margin-bottom: 15px;">
            <strong>Naming Convention:</strong> [School Name] [Course Name] [Graduation Year] Batch (e.g., "STME MCA 2026 Batch")
        </div>

        <form action="<?= get_base_url() ?>api/admin/add_batch.php" method="POST" id="addBatchForm" style="display:flex; flex-direction:column; gap: 15px;">
            
            <div style="display:flex; gap: 15px;">
                <select name="school_id" id="school_id" class="input-field" required onchange="updateCourses()" style="flex: 1;">
                    <option value="">Select School</option>
                    <?php foreach ($schools as $school): ?>
                        <option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="course_id" id="course_id" class="input-field" required style="flex: 1;">
                    <option value="">Select Course</option>
                    <!-- Populated via JS -->
                </select>

                <select id="batch_select" name="graduation_year" class="input-field" required style="flex: 1;">
                    <option value="">Select Batch</option>
                    <!-- Populated via JS -->
                </select>
            </div>

            <button type="submit" class="button-red" style="width:auto; align-self: flex-start; padding: 12px 30px;">Save Batch</button>
        </form>
    </div>

    <table class="data-table" style="margin-top: 20px;">
        <thead>
            <tr>
                <th>Batch Name</th>
                <th>School</th>
                <th>Course</th>
                <th>Year</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($classes as $cls): ?>
                <tr>
                    <td><?php echo htmlspecialchars($cls['name']); ?></td>
                    <td><?php echo htmlspecialchars($cls['school_name']); ?></td>
                    <td><?php echo htmlspecialchars($cls['course_name']); ?></td>
                    <td><?php echo htmlspecialchars($cls['graduation_year']); ?></td>
                    <td class="action-buttons" style="flex-direction:row;">
                        <a href="<?= get_base_url() ?>api/admin/delete_batch.php?id=<?php echo $cls['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this batch?')" style="background-color:#dc3545; color:white; border:none; padding: 8px 12px; font-size: 14px; border-radius: 6px; cursor:pointer; text-decoration:none;">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
    const coursesBySchool = <?php echo json_encode($coursesBySchool); ?>;
    
    function updateCourses() {
        const schoolId = document.getElementById('school_id').value;
        const courseSelect = document.getElementById('course_id');
        
        courseSelect.innerHTML = '<option value="">Select Course</option>';
        
        if (schoolId && coursesBySchool[schoolId]) {
            coursesBySchool[schoolId].forEach(course => {
                const option = document.createElement('option');
                option.value = course.id;
                option.textContent = course.name;
                // Add duration dataset attribute if you plan to auto-fill course duration in future
                option.dataset.name = course.name; 
                courseSelect.appendChild(option);
            });
        }
        updateBatches(); // Update batches based on course
    }

    function updateBatches() {
        const courseId = document.getElementById('course_id').value;
        const batchSelect = document.getElementById('batch_select');
        
        batchSelect.innerHTML = '<option value="">Select Batch</option>';
        
        if (courseId) {
            fetch('<?= get_base_url() ?>api/admin/get_course_batches.php?course_id=' + encodeURIComponent(courseId))
                .then(res => res.json())
                .then(data => {
                    data.forEach(batch => {
                        const option = document.createElement('option');
                        option.value = batch.graduation_year;
                        option.textContent = `${batch.batch} (Class of ${batch.graduation_year})`;
                        batchSelect.appendChild(option);
                    });
                })
                .catch(err => console.error(err));
        }
    }

    document.getElementById('course_id').addEventListener('change', updateBatches);
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>
