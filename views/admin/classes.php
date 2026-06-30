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

    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h2>All Classes (<?php echo count($classes); ?>)</h2>
    </div>

    <?php
    if (isset($_GET['success'])) { echo '<div class="message-box success-message">' . htmlspecialchars($_GET['success']) . '</div>'; }
    if (isset($_GET['error'])) { echo '<div class="message-box error-message">' . htmlspecialchars($_GET['error']) . '</div>'; }
    ?>
    
    <div class="section-box">
    <div class="section-box">
        <h3>Add New Class(es)</h3>
        
        <div class="message-box" style="background-color: #e8f4fd; color: #0056b3; border: 1px solid #b8daff; margin-bottom: 15px;">
            <strong>Naming Convention:</strong> [School Name] [Course Name] [Graduation Year] Section [Letter] (e.g., "STME MCA 2026 Section A")
        </div>

        <form action="<?= get_base_url() ?>api/admin/add_class.php" method="POST" id="addClassForm" style="display:flex; flex-direction:column; gap: 15px;">
            
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
            </div>

            <div style="display:flex; gap: 15px;">
                <input type="number" id="admission_year" name="admission_year" class="input-field" placeholder="Admission Year (e.g., 2024)" required min="2000" max="2100" style="flex: 1;">
                <input type="number" id="course_duration" name="course_duration" class="input-field" placeholder="Course Duration in Years (e.g., 4)" required min="1" max="10" style="flex: 1;">
                
                <select id="num_sections" class="input-field" required style="flex: 1;">
                    <option value="">Select Number of Sections</option>
                    <?php for ($i=1; $i<=10; $i++): ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?> Section(s)</option>
                    <?php endfor; ?>
                </select>
            </div>

            <div id="sections-container" style="display:flex; flex-direction:column; gap: 15px;">
                <!-- Dynamic rows will be inserted here -->
            </div>

            <button type="submit" class="button-red" style="width:auto; align-self: flex-start; padding: 12px 30px;">Save All Classes</button>
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
                // Add duration dataset attribute if you plan to auto-fill course duration in future
                option.dataset.name = course.name; 
                courseSelect.appendChild(option);
            });
        }
        generateSections(); // Update derived names if course changes
    }

    let searchTimeout;
    function attachSearchEvent(inputElement) {
        inputElement.addEventListener('keyup', function() {
            const query = this.value;
            const index = this.getAttribute('data-index');
            const type = this.getAttribute('data-type');
            const hiddenId = `sap_id_range_${type}_${index}`;
            const resultsContainer = document.getElementById(`sap_id_range_${type}_results_${index}`);
            const hiddenInput = document.getElementById(hiddenId);

            clearTimeout(searchTimeout);

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
                let url = '<?= get_base_url() ?>api/admin/search_student.php?q=' + encodeURIComponent(query);
                const courseId = document.getElementById('course_id').value;
                if (courseId) {
                    url += '&course_id=' + encodeURIComponent(courseId);
                }
                fetch(url)
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
        });
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.autocomplete-container')) {
            document.querySelectorAll('.autocomplete-results').forEach(res => res.style.display = 'none');
        }
    });

    const sectionsContainer = document.getElementById('sections-container');
    const numSectionsInput = document.getElementById('num_sections');
    const admissionYearInput = document.getElementById('admission_year');
    const courseDurationInput = document.getElementById('course_duration');
    const courseSelect = document.getElementById('course_id');

    function generateSections() {
        sectionsContainer.innerHTML = ''; // clear existing rows
        const num = parseInt(numSectionsInput.value) || 0;
        const admissionYear = parseInt(admissionYearInput.value) || 0;
        const duration = parseInt(courseDurationInput.value) || 0;
        
        let schoolName = '';
        const schoolSelect = document.getElementById('school_id');
        if (schoolSelect.options.length > 0 && schoolSelect.selectedIndex > 0) {
            schoolName = schoolSelect.options[schoolSelect.selectedIndex].text;
        }
        
        let courseName = '';
        if (courseSelect.options.length > 0 && courseSelect.selectedIndex > 0) {
            courseName = courseSelect.options[courseSelect.selectedIndex].text;
        }

        const graduationYear = (admissionYear > 0 && duration > 0) ? (admissionYear + duration) : 'XXXX';
        const displaySchool = schoolName ? schoolName : '[School Name]';
        const displayCourse = courseName ? courseName : '[Course Name]';

        for (let i = 0; i < num; i++) {
            const letter = String.fromCharCode(65 + i); // 65 is 'A'
            const derivedName = `${displaySchool} ${displayCourse} ${graduationYear} Section ${letter}`;

            const row = document.createElement('div');
            row.style.cssText = 'display:flex; gap: 15px; align-items: flex-start; background: #f9fafb; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb;';
            
            row.innerHTML = `
                <div style="flex: 1;">
                    <label style="font-size: 13px; font-weight: bold; margin-bottom: 5px; display: block;">Class Name</label>
                    <input type="text" name="class_name[]" class="input-field" value="${derivedName}" required style="margin-bottom:0;">
                </div>
                <div class="autocomplete-container" style="flex: 1;">
                    <label style="font-size: 13px; font-weight: bold; margin-bottom: 5px; display: block;">Section ${letter} Start SAP ID</label>
                    <input type="text" id="sap_id_range_start_display_${i}" class="input-field sap-search" data-index="${i}" data-type="start" placeholder="Search (Name/ID)" required style="width: 100%; box-sizing: border-box; margin-bottom:0;" autocomplete="off">
                    <input type="hidden" name="sap_id_range_start[]" id="sap_id_range_start_${i}">
                    <div id="sap_id_range_start_results_${i}" class="autocomplete-results" style="display: none;"></div>
                </div>
                <div class="autocomplete-container" style="flex: 1;">
                    <label style="font-size: 13px; font-weight: bold; margin-bottom: 5px; display: block;">Section ${letter} End SAP ID</label>
                    <input type="text" id="sap_id_range_end_display_${i}" class="input-field sap-search" data-index="${i}" data-type="end" placeholder="Search (Name/ID)" required style="width: 100%; box-sizing: border-box; margin-bottom:0;" autocomplete="off">
                    <input type="hidden" name="sap_id_range_end[]" id="sap_id_range_end_${i}">
                    <div id="sap_id_range_end_results_${i}" class="autocomplete-results" style="display: none;"></div>
                </div>
            `;
            sectionsContainer.appendChild(row);

            // Attach search events
            attachSearchEvent(row.querySelector(`#sap_id_range_start_display_${i}`));
            attachSearchEvent(row.querySelector(`#sap_id_range_end_display_${i}`));
        }
    }

    numSectionsInput.addEventListener('change', generateSections);
    admissionYearInput.addEventListener('input', generateSections);
    courseDurationInput.addEventListener('input', generateSections);
    courseSelect.addEventListener('change', generateSections);
    document.getElementById('school_id').addEventListener('change', generateSections);

</script>

<?php
  require_once '../../assets/templates/footer.php';
?>
