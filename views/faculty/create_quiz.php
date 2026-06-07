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

  // --- NEW: Fetch specializations for the dropdown ---
  $specializations = $pdo->query("SELECT id, name FROM specializations ORDER BY name ASC")->fetchAll();
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
  /* Optional: Improve Select2 appearance */
  .select2-container .select2-selection--single { height: 42px; border: 1px solid #ced4da; padding-top: 5px;}
  .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px; }
</style>

<div class="form-container">
  <h2>Quiz Setup</h2>
  <form action="<?= get_base_url() ?>api/faculty/create_quiz.php" method="POST">
    
    <div class="form-group">
      <label for="title">Quiz Title</label>
      <input type="text" id="title" name="title" placeholder="e.g., Data Structures - Mid Term Exam" required>
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
            <label for="course_id">Course</label>
            <select id="course_id" name="course_id" required disabled>
                <option value="">-- Select School First --</option>
            </select>
        </div>
        <div class="form-group">
            <label for="graduation_year">Graduation Year / Batch</label>
            <select id="graduation_year" name="graduation_year" required disabled>
                <option value="">-- Select Course First --</option>
            </select>
        </div>
    </div>
    
    <div class="form-group">
        <label for="specialization_id">Specialization (Optional)</label>
        <select id="specialization_id" name="specialization_id">
            <option value="">-- General Quiz for all Specializations --</option>
            <?php foreach ($specializations as $spec): ?>
            <option value="<?php echo $spec['id']; ?>"><?php echo htmlspecialchars($spec['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>


    <div class="form-row">
      <div class="form-group">
        <label for="start_time">Start Time</label>
        <input type="datetime-local" id="start_time" name="start_time" required>
      </div>
      <div class="form-group">
        <label for="end_time">End Time</label>
        <input type="datetime-local" id="end_time" name="end_time" required>
      </div>
      <div class="form-group">
        <label for="duration">Duration (Minutes)</label>
        <input type="number" id="duration" name="duration_minutes" min="1" placeholder="e.g., 60" required>
      </div>
    </div>
    <hr style="margin: 25px 0;">
    <h3 style="text-align: center; margin-bottom: 20px;">Student & Question Configuration</h3>
    
    <div class="form-row">
        <div class="form-group">
            <label for="sap_id_start">Start SAP ID (Optional)</label>
            <select name="sap_id_range_start" id="sap_id_start" class="student-select">
                <option></option> 
                <?php foreach ($students as $student): ?>
                    <option value="<?php echo htmlspecialchars($student['sap_id']); ?>">
                        <?php echo htmlspecialchars($student['full_name'] . ' (' . $student['sap_id'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="sap_id_end">End SAP ID (Optional)</label>
            <select name="sap_id_range_end" id="sap_id_end" class="student-select">
                <option></option> 
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

    <div class="form-group toggle-switch">
      <label for="show_results_immediately">Show Results to Students Immediately?</label>
      <label class="switch">
        <input type="checkbox" id="show_results_immediately" name="show_results_immediately">
        <span class="slider"></span>
      </label>
    </div>

    <!-- NEW: Calculator Toggle -->
    <div class="form-group toggle-switch">
      <label for="allow_calculator">Allow Calculator during Exam?</label>
      <label class="switch">
        <input type="checkbox" id="allow_calculator" name="allow_calculator">
        <span class="slider"></span>
      </label>
    </div>

    <!-- NEW: Negative Marking Toggle -->
    <div class="form-group toggle-switch">
      <label for="enable_negative_marking">Enable Negative Marking?</label>
      <label class="switch">
        <input type="checkbox" id="enable_negative_marking" name="enable_negative_marking">
        <span class="slider"></span>
      </label>
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
      <button type="submit" class="button-red" style="width: auto; padding: 12px 40px;">Create Quiz</button>
    </div>
  </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
const BASE_URL = '<?= get_base_url() ?>';
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Select2 on the student dropdowns
    $('.student-select').select2({
        placeholder: "Search by Name or SAP ID",
        allowClear: true
    });

    // --- Existing script for cascading dropdowns (unchanged) ---
    const schoolSelect = document.getElementById('school_id');
    const courseSelect = document.getElementById('course_id');
    const yearSelect = document.getElementById('graduation_year');

    schoolSelect.addEventListener('change', async function() {
        const schoolId = this.value;
        courseSelect.innerHTML = '<option value="">Loading...</option>';
        courseSelect.disabled = true;
        yearSelect.innerHTML = '<option value="">-- Select Course First --</option>';
        yearSelect.disabled = true;

        if (!schoolId) return;

        const response = await fetch(BASE_URL + `api/shared/get_courses_by_school.php?school_id=${schoolId}`);
        const courses = await response.json();
        
        courseSelect.innerHTML = '<option value="" disabled selected>-- Select a Course --</option>';
        courses.forEach(course => {
            const option = new Option(course.name, course.id);
            courseSelect.add(option);
        });
        courseSelect.disabled = false;
    });

    courseSelect.addEventListener('change', async function() {
        const courseId = this.value;
        yearSelect.innerHTML = '<option value="">Loading...</option>';
        yearSelect.disabled = true;

        if (!courseId) return;

        const response = await fetch(BASE_URL + `api/shared/get_years_by_course.php?course_id=${courseId}`);
        const years = await response.json();
        
        yearSelect.innerHTML = '<option value="" disabled selected>-- Select a Year --</option>';
        years.forEach(year => {
            const option = new Option(year, year);
            yearSelect.add(option);
        });
        yearSelect.disabled = false;
      
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
});
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>