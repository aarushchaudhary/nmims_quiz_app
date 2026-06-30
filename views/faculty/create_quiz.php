<?php
  $pageTitle = 'Create New Quiz';
  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  // --- Authorization Check ---
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
      redirect('login.php');
      exit();
  }

  // --- Fetch data for dropdowns ---
  $schools = $pdo->query("SELECT id, name FROM schools ORDER BY name ASC")->fetchAll();
  
  $students_stmt = $pdo->query("
    SELECT s.sap_id, s.name as full_name
    FROM students s
    WHERE s.sap_id IS NOT NULL AND s.name IS NOT NULL
    ORDER BY s.name ASC
  ");
  $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

  // Fetch groups
  $classes = $pdo->query("SELECT id, name FROM classes ORDER BY name ASC")->fetchAll();
  $batches = $pdo->query("SELECT id, name FROM batches ORDER BY name ASC")->fetchAll();
  $electives = $pdo->query("SELECT id, name FROM electives ORDER BY name ASC")->fetchAll();
  $re_exam_groups = $pdo->query("SELECT id, name FROM re_exam_groups WHERE expires_at > NOW() ORDER BY name ASC")->fetchAll();
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
  /* Optional: Improve Select2 appearance */
  .flatpickr-calendar {
      box-shadow: 0 10px 25px rgba(0,0,0,0.15) !important;
      border: 1px solid #ddd !important;
      border-radius: 8px !important;
  }

  /* Make Select2 optgroups side-by-side */
  .side-by-side-dropdown > .select2-results > .select2-results__options {
      display: flex;
      flex-wrap: wrap;
      padding: 10px;
      background: #f8f9fa;
  }
  .side-by-side-dropdown .select2-results__option[role="group"] {
      flex: 1 1 200px;
      padding: 10px;
      margin: 5px;
      background: #ffffff;
      border-radius: 6px;
      border: 1px solid #e0e0e0;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
      vertical-align: top;
  }
  .side-by-side-dropdown .select2-results__group {
      font-weight: bold;
      color: #333;
      border-bottom: 2px solid #e60000;
      padding-bottom: 5px;
      margin-bottom: 5px;
  }
</style>

<div class="form-container" style="max-width: 1000px;">
  <h2>Quiz Setup</h2>
  
  <div class="message-box" style="background-color: #e8f4fd; color: #0056b3; border: 1px solid #b8daff; margin-bottom: 20px;">
      <strong>Naming Convention:</strong> Quiz titles are dynamically generated using:<br>
      <em>[School] - [Course] - [Exam Title] - [Exam Date] - [Groups]</em>
  </div>

  <form action="<?= get_base_url() ?>api/faculty/create_quiz.php" method="POST">
    
    <div class="form-row">
      <div class="form-group">
        <label for="exam_title">Exam Title</label>
        <input type="text" id="exam_title" placeholder="e.g., Mid Term Exam" required>
      </div>
      <div class="form-group">
        <label for="generated_title">Generated Quiz Title</label>
        <input type="text" id="generated_title" class="input-field" readonly style="background-color: #e9ecef; cursor: not-allowed; color: #495057;">
        <input type="hidden" name="title" id="title">
      </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label for="school_id">School</label>
            <select id="school_id" name="school_id" required>
                <option value="" disabled selected>-- Select a School --</option>
                <?php foreach ($schools as $school): ?>
                <option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="course_id">Course(s)</label>
            <select id="course_id" name="course_ids[]" class="course-select" multiple required disabled>
                <option value="">-- Select School First --</option>
            </select>
        </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label for="start_time">Start Time</label>
        <input type="text" id="start_time" name="start_time" placeholder="Select Start Time" required>
      </div>
      <div class="form-group">
        <label for="end_time">End Time</label>
        <input type="text" id="end_time" name="end_time" placeholder="Select End Time" required>
      </div>
      <div class="form-group">
        <label for="duration">Duration (Minutes)</label>
        <input type="number" id="duration" name="duration_minutes" min="1" placeholder="e.g., 60" required>
      </div>
    </div>
    <div style="font-size: 0.85em; color: #666; margin-top: -10px; margin-bottom: 20px;">
      <p style="margin: 0;"><strong>Start & End Time:</strong> The specific window of time when the quiz is open and available for students to start.</p>
      <p style="margin: 5px 0 0 0;"><strong>Duration:</strong> The actual time limit a student has to complete the quiz once they begin.</p>
    </div>
    <hr style="margin: 25px 0;">
    <h3 style="text-align: center; margin-bottom: 20px;">Student & Question Configuration</h3>
    
    <div class="form-row">
        <div class="form-group" style="width: 100%;">
            <label for="exam_groups">Select Exam Groups</label>
            <select name="exam_groups[]" id="exam_groups" class="group-select" multiple>
                <?php if (!empty($electives)): ?>
                <optgroup label="Electives" id="optgroup-electives">
                    <?php foreach ($electives as $elective): ?>
                        <option value="elective_<?php echo $elective['id']; ?>"><?php echo htmlspecialchars($elective['name']); ?></option>
                    <?php endforeach; ?>
                </optgroup>
                <?php endif; ?>

                <?php if (!empty($re_exam_groups)): ?>
                <optgroup label="Re-Exam Groups" id="optgroup-reexams">
                    <?php foreach ($re_exam_groups as $group): ?>
                        <option value="reexam_<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                    <?php endforeach; ?>
                </optgroup>
                <?php endif; ?>
            </select>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group" style="width: 100%;">
            <label for="manual_student_ids">Add Specific Students by SAP ID (Optional)</label>
            <select name="manual_student_ids[]" id="manual_student_ids" class="student-select" multiple>
                <?php foreach ($students as $student): ?>
                    <option value="<?php echo htmlspecialchars($student['sap_id']); ?>">
                        <?php echo htmlspecialchars($student['full_name'] . ' (' . $student['sap_id'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-row">
      <div class="form-group"><label for="easy_count">Easy Questions</label><input type="number" id="easy_count" name="config_easy_count" min="0" value="0" required></div>
      <div class="form-group"><label for="medium_count">Medium Questions</label><input type="number" id="medium_count" name="config_medium_count" min="0" value="0" required></div>
      <div class="form-group"><label for="hard_count">Hard Questions</label><input type="number" id="hard_count" name="config_hard_count" min="0" value="0" required></div>
    </div>

    <!-- NEW: Show Results Toggle -->
    <div class="form-group toggle-switch" style="display: flex; align-items: center; justify-content: flex-start; gap: 15px; background: #fff; padding: 15px 20px; border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
      <label class="switch" style="margin-bottom: 0; display: flex; align-items: center;">
        <input type="checkbox" id="show_results_immediately" name="show_results_immediately">
      </label>
      <div style="display: flex; align-items: center; flex-wrap: wrap;">
        <label for="show_results_immediately" style="margin-bottom: 0; font-weight: 600; color: #444; font-size: 0.95em; letter-spacing: 0.5px;">SHOW RESULTS IMMEDIATELY?</label>
        <span style="font-size: 0.85em; color: #888; margin-left: 10px; font-style: italic;">(Only MCQ and MSQ questions can be added if enabled)</span>
      </div>
    </div>

    <!-- NEW: Calculator Toggle -->
    <div class="form-group toggle-switch" style="display: flex; align-items: center; justify-content: flex-start; gap: 15px; background: #fff; padding: 15px 20px; border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
      <label class="switch" style="margin-bottom: 0; display: flex; align-items: center;">
        <input type="checkbox" id="allow_calculator" name="allow_calculator">
      </label>
      <label for="allow_calculator" style="margin-bottom: 0; font-weight: 600; color: #444; font-size: 0.95em; letter-spacing: 0.5px;">ALLOW CALCULATOR DURING EXAM?</label>
    </div>

    <!-- NEW: Negative Marking Toggle -->
    <div class="form-group toggle-switch" style="display: flex; align-items: center; justify-content: flex-start; gap: 15px; background: #fff; padding: 15px 20px; border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
      <label class="switch" style="margin-bottom: 0; display: flex; align-items: center;">
        <input type="checkbox" id="enable_negative_marking" name="enable_negative_marking">
      </label>
      <label for="enable_negative_marking" style="margin-bottom: 0; font-weight: 600; color: #444; font-size: 0.95em; letter-spacing: 0.5px;">ENABLE NEGATIVE MARKING?</label>
    </div>

    <!-- NEW: Negative Marks Inputs (hidden by default) -->
    <div class="form-row" id="negative_marks_row" style="display: none; background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
      <div class="form-group" style="margin-bottom: 0;">
        <label for="negative_marks_mcq">MCQ</label>
        <input type="number" id="negative_marks_mcq" name="negative_marks_mcq" min="0" step="0.25" value="0.00" class="input-field">
      </div>
      <div class="form-group" style="margin-bottom: 0;">
        <label for="negative_marks_msq">MSQ</label>
        <input type="number" id="negative_marks_msq" name="negative_marks_msq" min="0" step="0.25" value="0.00" class="input-field">
      </div>
      <div class="form-group" style="margin-bottom: 0;">
        <label for="negative_marks_descriptive">Descriptive</label>
        <input type="number" id="negative_marks_descriptive" name="negative_marks_descriptive" min="0" step="0.25" value="0.00" class="input-field">
      </div>
    </div>

    <div class="form-group" style="text-align: center; margin-top: 30px;">
      <button type="submit" class="button-red" style="width: auto; padding: 12px 40px;">Add Questions</button>
    </div>
  </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
const BASE_URL = '<?= get_base_url() ?>';
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Select2 on the student dropdowns and group dropdowns
    $('.student-select').select2({
        placeholder: "Search by Name or SAP ID",
        allowClear: true,
        width: '100%'
    });
    $('.group-select').select2({
        placeholder: "Select groups",
        allowClear: true,
        width: '100%',
        dropdownCssClass: 'side-by-side-dropdown'
    });

    // Initialize Flatpickr for date-time inputs
    flatpickr("#start_time", {
        enableTime: true,
        dateFormat: "Y-m-d H:i",
        time_24hr: false, // User friendly 12hr am/pm
        onChange: function() {
            updateQuizTitle();
        }
    });
    
    flatpickr("#end_time", {
        enableTime: true,
        dateFormat: "Y-m-d H:i",
        time_24hr: false
    });
    $('.course-select').select2({
        placeholder: "Select Courses",
        allowClear: true,
        width: '100%'
    });

    const schoolSelect = document.getElementById('school_id');
    const courseSelect = $('#course_id');
    const examGroupsSelect = $('#exam_groups');

    schoolSelect.addEventListener('change', async function() {
        const schoolId = this.value;
        courseSelect.empty();
        courseSelect.prop('disabled', true);
        examGroupsSelect.find('optgroup[label="Classes"]').remove();
        examGroupsSelect.find('optgroup[label="Batches"]').remove();

        if (!schoolId) return;

        const response = await fetch(BASE_URL + `api/shared/get_courses_by_school.php?school_id=${schoolId}`);
        const courses = await response.json();
        
        courses.forEach(course => {
            const option = new Option(course.name, course.id);
            courseSelect.append(option);
        });
        courseSelect.prop('disabled', false);
        courseSelect.trigger('change');
    });

    courseSelect.on('change', async function() {
        const courseIds = $(this).val();
        
        // Remove existing dynamic classes and batches
        examGroupsSelect.find('optgroup[label="Classes"]').remove();
        examGroupsSelect.find('optgroup[label="Batches"]').remove();

        if (!courseIds || courseIds.length === 0) {
            examGroupsSelect.trigger('change');
            return;
        }

        const response = await fetch(BASE_URL + `api/shared/get_groups_by_courses.php?course_ids=${courseIds.join(',')}`);
        const data = await response.json();

        if (data.classes && data.classes.length > 0) {
            let classGroup = $('<optgroup label="Classes"></optgroup>');
            data.classes.forEach(cls => {
                classGroup.append(new Option(cls.name, 'class_' + cls.id));
            });
            // Prepend so classes show up before electives
            examGroupsSelect.prepend(classGroup);
        }

        if (data.batches && data.batches.length > 0) {
            let batchGroup = $('<optgroup label="Batches"></optgroup>');
            data.batches.forEach(bat => {
                let opt = new Option(bat.name, 'batch_' + bat.id);
                $(opt).attr('data-class-id', bat.class_id);
                batchGroup.append(opt);
            });
            // Insert batches after classes
            examGroupsSelect.find('optgroup[label="Classes"]').after(batchGroup);
        }

        examGroupsSelect.trigger('change');
    });

    // Handle disabling batches when parent class is selected
    examGroupsSelect.on('change', function() {
        const selected = $(this).val() || [];
        const selectedClassIds = selected
            .filter(val => val.startsWith('class_'))
            .map(val => val.replace('class_', ''));

        $(this).find('optgroup[label="Batches"] option').each(function() {
            const batchClassId = $(this).attr('data-class-id');
            if (selectedClassIds.includes(batchClassId)) {
                $(this).prop('disabled', true);
                // If it was selected, unselect it
                if ($(this).is(':selected')) {
                    $(this).prop('selected', false);
                }
            } else {
                $(this).prop('disabled', false);
            }
        });
        
        // Re-render select2 to show disabled states
        $(this).select2({
            placeholder: "Select groups",
            allowClear: true,
            width: '100%',
            dropdownCssClass: 'side-by-side-dropdown'
        });
        updateQuizTitle();
    });

    // Toggle Negative Marks visibility
    $('#enable_negative_marking').on('change', function() {
        if($(this).is(':checked')) {
            $('#negative_marks_row').slideDown();
        } else {
            $('#negative_marks_row').slideUp();
            $('#negative_marks_mcq').val('0.00');
            $('#negative_marks_msq').val('0.00');
            $('#negative_marks_descriptive').val('0.00');
        }
    });

    // Dynamic Title Generation Logic
    function updateQuizTitle() {
        let school = '';
        if(schoolSelect.selectedIndex > 0) school = schoolSelect.options[schoolSelect.selectedIndex].text;

        let course = '';
        const selectedCourses = courseSelect.select2('data');
        if(selectedCourses && selectedCourses.length > 0) {
            course = selectedCourses.map(c => c.text).join(', ');
        }

        const examTitle = document.getElementById('exam_title').value.trim();
        
        let dateStr = '';
        const startVal = document.getElementById('start_time').value;
        if (startVal) {
            const d = new Date(startVal);
            const day = String(d.getDate()).padStart(2, '0');
            const month = d.toLocaleString('default', { month: 'short' });
            const year = d.getFullYear();
            dateStr = `${day} ${month} ${year}`;
        }

        let groups = [];
        const selectedData = $('#exam_groups').select2('data');
        selectedData.forEach(item => {
            let text = item.text;
            // Simplify class names if they contain "Section X"
            if (item.id.startsWith('class_')) {
                const match = text.match(/(Section \w+)$/i);
                if (match) {
                    text = match[1];
                }
            }
            groups.push(text);
        });

        const groupStr = groups.length > 0 ? groups.join(', ') : '';

        const parts = [school, course, examTitle, dateStr, groupStr].filter(Boolean);
        const finalTitle = parts.join(' - ');

        document.getElementById('generated_title').value = finalTitle;
        document.getElementById('title').value = finalTitle;
    }

    // Attach event listeners for dynamic title
    $('#school_id, #course_id').on('change', updateQuizTitle);
    $('#exam_groups').on('change', updateQuizTitle);
    $('#exam_title').on('input', updateQuizTitle);
    $('#start_time').on('input change', updateQuizTitle);

});
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>