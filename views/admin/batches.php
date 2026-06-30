<?php
  $pageTitle = 'Manage Batches';
  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
      redirect('login.php');
      exit();
  }

  // Fetch schools
  $schools = $pdo->query("SELECT * FROM schools ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
  
  // Fetch courses
  $courses = $pdo->query("SELECT * FROM courses ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
  
  // Fetch classes
  $classes = $pdo->query("SELECT * FROM classes ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

  // Fetch batches with their associated class
  $batches = $pdo->query("
      SELECT b.*, c.name as class_name 
      FROM batches b 
      JOIN classes c ON b.class_id = c.id 
      ORDER BY c.name ASC, b.name ASC
  ")->fetchAll(PDO::FETCH_ASSOC);
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
        <h2>All Batches (<?php echo count($batches); ?>)</h2>
    </div>

    <?php
    if (isset($_GET['success'])) { echo '<div class="message-box success-message">' . htmlspecialchars($_GET['success']) . '</div>'; }
    if (isset($_GET['error'])) { echo '<div class="message-box error-message">' . htmlspecialchars($_GET['error']) . '</div>'; }
    ?>
    
    <div class="section-box">
        <h3>Add New Batch(es)</h3>
        <form action="<?= get_base_url() ?>api/admin/add_batch.php" method="POST" id="addBatchForm" style="display:flex; flex-direction:column; gap: 20px;">
            
            <div style="display:flex; gap: 15px;">
                <select name="school_id" id="school_id" class="input-field" required style="flex: 1;">
                    <option value="">Select School</option>
                    <?php foreach ($schools as $school): ?>
                        <option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <select name="course_id" id="course_id" class="input-field" required style="flex: 1;">
                    <option value="">Select Course</option>
                </select>
                
                <select name="class_id" id="class_id" class="input-field" required style="flex: 1;">
                    <option value="">Select Class (Section)</option>
                </select>
            </div>

            <div id="batch-rows-container" style="display:flex; flex-direction:column; gap: 15px;">
                <div class="batch-row" style="display:flex; gap: 15px; align-items: flex-start; background: #f9fafb; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb; position: relative;">
                    <div style="flex: 1;">
                        <label style="font-size: 13px; font-weight: bold; margin-bottom: 5px; display: block;">Batch Name</label>
                        <input type="text" name="batch_name[]" class="input-field" placeholder="e.g., B1" required style="margin-bottom:0;">
                    </div>
                    <div class="autocomplete-container" style="flex: 1.5;">
                        <label style="font-size: 13px; font-weight: bold; margin-bottom: 5px; display: block;">Start SAP ID</label>
                        <input type="text" id="sap_id_range_start_display_0" class="input-field sap-search" data-index="0" data-type="start" placeholder="Search (Name/ID)" required style="width: 100%; box-sizing: border-box; margin-bottom:0;" autocomplete="off">
                        <input type="hidden" name="sap_id_range_start[]" id="sap_id_range_start_0">
                        <div id="sap_id_range_start_results_0" class="autocomplete-results" style="display: none;"></div>
                    </div>
                    <div class="autocomplete-container" style="flex: 1.5;">
                        <label style="font-size: 13px; font-weight: bold; margin-bottom: 5px; display: block;">End SAP ID</label>
                        <input type="text" id="sap_id_range_end_display_0" class="input-field sap-search" data-index="0" data-type="end" placeholder="Search (Name/ID)" required style="width: 100%; box-sizing: border-box; margin-bottom:0;" autocomplete="off">
                        <input type="hidden" name="sap_id_range_end[]" id="sap_id_range_end_0">
                        <div id="sap_id_range_end_results_0" class="autocomplete-results" style="display: none;"></div>
                    </div>
                    <button type="button" class="btn-remove-row" style="display:none; background: #dc3545; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; margin-top: 24px;">🗑️</button>
                </div>
            </div>

            <div style="display:flex; justify-content: space-between; align-items: center;">
                <button type="button" id="btn-add-row" style="background: #e5f9f0; color: #28a745; border: 1px solid #28a745; padding: 10px 15px; border-radius: 8px; font-weight: bold; cursor: pointer;">+ Add Another Batch</button>
                <button type="submit" class="button-red" style="width:auto; padding: 12px 30px;">Save All Batches</button>
            </div>
        </form>
    </div>

    <table class="data-table" style="margin-top: 20px;">
        <thead>
            <tr>
                <th>Batch Name</th>
                <th>Class</th>
                <th>SAP ID Range</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($batches as $batch): ?>
                <tr>
                    <td><?php echo htmlspecialchars($batch['name']); ?></td>
                    <td><?php echo htmlspecialchars($batch['class_name']); ?></td>
                    <td><?php echo htmlspecialchars($batch['sap_id_range_start'] . ' - ' . $batch['sap_id_range_end']); ?></td>
                    <td class="action-buttons" style="flex-direction:row;">
                        <a href="edit_batch.php?id=<?php echo $batch['id']; ?>" class="btn-edit" style="background-color:#ffc107; color:black; border:none; padding: 8px 12px; font-size: 14px; border-radius: 6px; cursor:pointer; text-decoration:none; margin-right: 5px;">Edit</a>
                        <a href="<?= get_base_url() ?>api/admin/delete_batch.php?id=<?php echo $batch['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this batch?')" style="background-color:#dc3545; color:white; border:none; padding: 8px 12px; font-size: 14px; border-radius: 6px; cursor:pointer; text-decoration:none;">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
    const coursesData = <?php echo json_encode($courses); ?>;
    const classesData = <?php echo json_encode($classes); ?>;

    const schoolSelect = document.getElementById('school_id');
    const courseSelect = document.getElementById('course_id');
    const classSelect = document.getElementById('class_id');

    // Filter courses based on school
    schoolSelect.addEventListener('change', function() {
        const schoolId = this.value;
        courseSelect.innerHTML = '<option value="">Select Course</option>';
        classSelect.innerHTML = '<option value="">Select Class (Section)</option>';
        
        if (schoolId) {
            const filteredCourses = coursesData.filter(c => c.school_id == schoolId);
            filteredCourses.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.name;
                courseSelect.appendChild(opt);
            });
        }
    });

    // Filter classes based on course
    courseSelect.addEventListener('change', function() {
        const courseId = this.value;
        classSelect.innerHTML = '<option value="">Select Class (Section)</option>';
        
        if (courseId) {
            const filteredClasses = classesData.filter(c => c.course_id == courseId);
            filteredClasses.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.name;
                classSelect.appendChild(opt);
            });
        }
    });

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
                const classId = document.getElementById('class_id').value;
                if (classId) {
                    url += '&class_id=' + encodeURIComponent(classId);
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

    // Initialize search on existing rows
    document.querySelectorAll('.sap-search').forEach(input => {
        attachSearchEvent(input);
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.autocomplete-container')) {
            document.querySelectorAll('.autocomplete-results').forEach(res => res.style.display = 'none');
        }
    });

    // Add Dynamic Rows
    let rowIndex = 1;
    const batchRowsContainer = document.getElementById('batch-rows-container');
    const btnAddRow = document.getElementById('btn-add-row');

    btnAddRow.addEventListener('click', function() {
        const row = document.createElement('div');
        row.className = 'batch-row';
        row.style.cssText = 'display:flex; gap: 15px; align-items: flex-start; background: #f9fafb; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb; position: relative;';
        
        row.innerHTML = `
            <div style="flex: 1;">
                <label style="font-size: 13px; font-weight: bold; margin-bottom: 5px; display: block;">Batch Name</label>
                <input type="text" name="batch_name[]" class="input-field" placeholder="e.g., B2" required style="margin-bottom:0;">
            </div>
            <div class="autocomplete-container" style="flex: 1.5;">
                <label style="font-size: 13px; font-weight: bold; margin-bottom: 5px; display: block;">Start SAP ID</label>
                <input type="text" id="sap_id_range_start_display_${rowIndex}" class="input-field sap-search" data-index="${rowIndex}" data-type="start" placeholder="Search (Name/ID)" required style="width: 100%; box-sizing: border-box; margin-bottom:0;" autocomplete="off">
                <input type="hidden" name="sap_id_range_start[]" id="sap_id_range_start_${rowIndex}">
                <div id="sap_id_range_start_results_${rowIndex}" class="autocomplete-results" style="display: none;"></div>
            </div>
            <div class="autocomplete-container" style="flex: 1.5;">
                <label style="font-size: 13px; font-weight: bold; margin-bottom: 5px; display: block;">End SAP ID</label>
                <input type="text" id="sap_id_range_end_display_${rowIndex}" class="input-field sap-search" data-index="${rowIndex}" data-type="end" placeholder="Search (Name/ID)" required style="width: 100%; box-sizing: border-box; margin-bottom:0;" autocomplete="off">
                <input type="hidden" name="sap_id_range_end[]" id="sap_id_range_end_${rowIndex}">
                <div id="sap_id_range_end_results_${rowIndex}" class="autocomplete-results" style="display: none;"></div>
            </div>
            <button type="button" class="btn-remove-row" style="background: #dc3545; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; margin-top: 24px;">🗑️</button>
        `;
        
        batchRowsContainer.appendChild(row);

        // Attach events to new inputs
        attachSearchEvent(row.querySelector(`#sap_id_range_start_display_${rowIndex}`));
        attachSearchEvent(row.querySelector(`#sap_id_range_end_display_${rowIndex}`));

        // Attach remove row event
        row.querySelector('.btn-remove-row').addEventListener('click', function() {
            batchRowsContainer.removeChild(row);
        });

        rowIndex++;
    });
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>
