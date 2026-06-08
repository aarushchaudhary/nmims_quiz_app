<?php
  $pageTitle = 'Manage Classes';
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
    <a href="exam_groups.php" style="text-decoration: none; color: #007bff; margin-bottom: 20px; display: inline-block;">&larr; Back to Exam Groups</a>
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h2>All Classes (<?php echo count($classes); ?>)</h2>
    </div>

    <?php
    if (isset($_GET['success'])) { echo '<div class="message-box success-message">' . htmlspecialchars($_GET['success']) . '</div>'; }
    if (isset($_GET['error'])) { echo '<div class="message-box error-message">' . htmlspecialchars($_GET['error']) . '</div>'; }
    ?>
    
    <div class="section-box">
        <h3>Add New Class</h3>
        <form action="<?= get_base_url() ?>api/admin/add_class.php" method="POST" style="display:flex; flex-direction:column; gap: 15px;">
            <input type="text" name="class_name" class="input-field" placeholder="Enter class name (e.g., MCA 2026 Batch A)" required>
            
            <div style="display:flex; gap: 15px;">
                <select name="school_id" id="school_id" class="input-field" required onchange="updateCourses()">
                    <option value="">Select School</option>
                    <?php foreach ($schools as $school): ?>
                        <option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="course_id" id="course_id" class="input-field" required>
                    <option value="">Select Course</option>
                    <!-- Populated via JS -->
                </select>
            </div>

            <div style="display:flex; gap: 15px;">
                <input type="number" name="graduation_year" class="input-field" placeholder="Graduation Year (e.g., 2026)" required min="2000" max="2100" style="flex: 1;">
                
                <div class="autocomplete-container" style="flex: 1;">
                    <input type="text" id="sap_id_range_start_display" class="input-field" placeholder="Search Start SAP ID (Name/ID)" required style="width: 100%; box-sizing: border-box;" autocomplete="off" onkeyup="searchStudent(this, 'sap_id_range_start')">
                    <input type="hidden" name="sap_id_range_start" id="sap_id_range_start">
                    <div id="sap_id_range_start_results" class="autocomplete-results" style="display: none;"></div>
                </div>
                
                <div class="autocomplete-container" style="flex: 1;">
                    <input type="text" id="sap_id_range_end_display" class="input-field" placeholder="Search End SAP ID (Name/ID)" required style="width: 100%; box-sizing: border-box;" autocomplete="off" onkeyup="searchStudent(this, 'sap_id_range_end')">
                    <input type="hidden" name="sap_id_range_end" id="sap_id_range_end">
                    <div id="sap_id_range_end_results" class="autocomplete-results" style="display: none;"></div>
                </div>
            </div>

            <button type="submit" class="button-red" style="width:auto; align-self: flex-start;">Add Class</button>
        </form>
    </div>

    <table class="data-table" style="margin-top: 20px;">
        <thead>
            <tr>
                <th>Class Name</th>
                <th>School</th>
                <th>Course</th>
                <th>Year</th>
                <th>SAP ID Range</th>
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
                    <td><?php echo htmlspecialchars($cls['sap_id_range_start'] . ' - ' . $cls['sap_id_range_end']); ?></td>
                    <td class="action-buttons" style="flex-direction:row;">
                        <a href="edit_class.php?id=<?php echo $cls['id']; ?>" class="btn-edit" style="background-color:#ffc107; color:black; border:none; padding: 8px 12px; font-size: 14px; border-radius: 6px; cursor:pointer; text-decoration:none; margin-right: 5px;">Edit</a>
                        <a href="<?= get_base_url() ?>api/admin/delete_class.php?id=<?php echo $cls['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this class?')" style="background-color:#dc3545; color:white; border:none; padding: 8px 12px; font-size: 14px; border-radius: 6px; cursor:pointer; text-decoration:none;">Delete</a>
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
                courseSelect.appendChild(option);
            });
        }
    }

    let searchTimeout;
    function searchStudent(inputElement, hiddenId) {
        clearTimeout(searchTimeout);
        const query = inputElement.value;
        const resultsContainer = document.getElementById(hiddenId + '_results');
        const hiddenInput = document.getElementById(hiddenId);

        if (query.trim() === '') {
            hiddenInput.value = '';
            resultsContainer.style.display = 'none';
            return;
        }

        if (query.length < 2) {
            resultsContainer.style.display = 'none';
            return;
        }

        searchTimeout = setTimeout(() => {
            fetch('<?= get_base_url() ?>api/admin/search_student.php?q=' + encodeURIComponent(query))
                .then(res => res.json())
                .then(data => {
                    resultsContainer.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(student => {
                            const div = document.createElement('div');
                            div.className = 'autocomplete-item';
                            div.textContent = `${student.name} (${student.sap_id})`;
                            div.onclick = function() {
                                inputElement.value = `${student.name} (${student.sap_id})`;
                                hiddenInput.value = student.sap_id;
                                resultsContainer.style.display = 'none';
                            };
                            resultsContainer.appendChild(div);
                        });
                        resultsContainer.style.display = 'block';
                    } else {
                        resultsContainer.innerHTML = '<div style="padding: 8px 12px; font-size: 14px; color: #777;">No students found</div>';
                        resultsContainer.style.display = 'block';
                    }
                })
                .catch(err => console.error(err));
        }, 300);
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.autocomplete-container')) {
            document.getElementById('sap_id_range_start_results').style.display = 'none';
            document.getElementById('sap_id_range_end_results').style.display = 'none';
        }
    });
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>
