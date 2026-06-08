<?php
  $pageTitle = 'Edit Class';
  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
      redirect('login.php');
      exit();
  }

  $class_id = $_GET['id'] ?? null;
  if (!$class_id) {
      header('Location: classes.php');
      exit();
  }

  // Fetch class details
  $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
  $stmt->execute([$class_id]);
  $class = $stmt->fetch();

  if (!$class) {
      header('Location: classes.php?error=' . urlencode("Class not found."));
      exit();
  }

  // Fetch schools and courses
  $schools = $pdo->query("SELECT * FROM schools ORDER BY name ASC")->fetchAll();
  $courses = $pdo->query("SELECT * FROM courses ORDER BY name ASC")->fetchAll();
  $coursesBySchool = [];
  foreach ($courses as $course) {
      $coursesBySchool[$course['school_id']][] = $course;
  }

  // Fetch student names for autocomplete display
  $start_student_name = "";
  if ($class['sap_id_range_start']) {
      $stmt = $pdo->prepare("SELECT name FROM students WHERE sap_id = ?");
      $stmt->execute([$class['sap_id_range_start']]);
      if ($row = $stmt->fetch()) {
          $start_student_name = $row['name'] . " (" . $class['sap_id_range_start'] . ")";
      } else {
          $start_student_name = $class['sap_id_range_start'];
      }
  }

  $end_student_name = "";
  if ($class['sap_id_range_end']) {
      $stmt = $pdo->prepare("SELECT name FROM students WHERE sap_id = ?");
      $stmt->execute([$class['sap_id_range_end']]);
      if ($row = $stmt->fetch()) {
          $end_student_name = $row['name'] . " (" . $class['sap_id_range_end'] . ")";
      } else {
          $end_student_name = $class['sap_id_range_end'];
      }
  }
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
    <a href="classes.php" style="text-decoration: none; color: #007bff; margin-bottom: 20px; display: inline-block;">&larr; Back to Classes</a>
    
    <div class="section-box">
        <h3>Edit Class</h3>
        <form action="<?= get_base_url() ?>api/admin/edit_class.php" method="POST" style="display:flex; flex-direction:column; gap: 15px;">
            <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
            
            <input type="text" name="class_name" class="input-field" placeholder="Enter class name (e.g., MCA 2026 Batch A)" required value="<?php echo htmlspecialchars($class['name']); ?>">
            
            <div style="display:flex; gap: 15px;">
                <select name="school_id" id="school_id" class="input-field" required onchange="updateCourses()">
                    <option value="">Select School</option>
                    <?php foreach ($schools as $school): ?>
                        <option value="<?php echo $school['id']; ?>" <?php if ($class['school_id'] == $school['id']) echo 'selected'; ?>><?php echo htmlspecialchars($school['name']); ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="course_id" id="course_id" class="input-field" required>
                    <option value="">Select Course</option>
                    <!-- Populated via JS -->
                </select>
            </div>

            <div style="display:flex; gap: 15px;">
                <input type="number" name="graduation_year" class="input-field" placeholder="Graduation Year (e.g., 2026)" required min="2000" max="2100" style="flex: 1;" value="<?php echo htmlspecialchars($class['graduation_year']); ?>">
                
                <div class="autocomplete-container" style="flex: 1;">
                    <input type="text" id="sap_id_range_start_display" class="input-field" placeholder="Search Start SAP ID (Name/ID)" required style="width: 100%; box-sizing: border-box;" autocomplete="off" onkeyup="searchStudent(this, 'sap_id_range_start')" value="<?php echo htmlspecialchars($start_student_name); ?>">
                    <input type="hidden" name="sap_id_range_start" id="sap_id_range_start" value="<?php echo htmlspecialchars($class['sap_id_range_start']); ?>">
                    <div id="sap_id_range_start_results" class="autocomplete-results" style="display: none;"></div>
                </div>
                
                <div class="autocomplete-container" style="flex: 1;">
                    <input type="text" id="sap_id_range_end_display" class="input-field" placeholder="Search End SAP ID (Name/ID)" required style="width: 100%; box-sizing: border-box;" autocomplete="off" onkeyup="searchStudent(this, 'sap_id_range_end')" value="<?php echo htmlspecialchars($end_student_name); ?>">
                    <input type="hidden" name="sap_id_range_end" id="sap_id_range_end" value="<?php echo htmlspecialchars($class['sap_id_range_end']); ?>">
                    <div id="sap_id_range_end_results" class="autocomplete-results" style="display: none;"></div>
                </div>
            </div>

            <button type="submit" class="button-red" style="width:auto; align-self: flex-start;">Update Class</button>
        </form>
    </div>
</div>

<script>
    const coursesBySchool = <?php echo json_encode($coursesBySchool); ?>;
    const initialCourseId = <?php echo json_encode($class['course_id']); ?>;
    
    function updateCourses(selectedCourseId = null) {
        const schoolId = document.getElementById('school_id').value;
        const courseSelect = document.getElementById('course_id');
        
        courseSelect.innerHTML = '<option value="">Select Course</option>';
        
        if (schoolId && coursesBySchool[schoolId]) {
            coursesBySchool[schoolId].forEach(course => {
                const option = document.createElement('option');
                option.value = course.id;
                option.textContent = course.name;
                if (selectedCourseId && course.id == selectedCourseId) {
                    option.selected = true;
                }
                courseSelect.appendChild(option);
            });
        }
    }

    // Initialize courses based on selected school
    document.addEventListener('DOMContentLoaded', function() {
        updateCourses(initialCourseId);
    });

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
