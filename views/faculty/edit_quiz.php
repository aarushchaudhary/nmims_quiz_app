<?php
  $pageTitle = 'Edit Quiz'; 
  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  // --- Authorization & Input Check ---
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2 || !isset($_GET['id'])) {
      redirect('login.php');
      exit();
  }
  $quiz_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
  $faculty_id = $_SESSION['user_id'];

  // --- Fetch the quiz data ---
  $stmt_quiz = $pdo->prepare("
      SELECT q.*, c.school_id 
      FROM quizzes q
      JOIN courses c ON q.course_id = c.id
      WHERE q.id = :quiz_id AND q.faculty_id = :faculty_id");
  $stmt_quiz->execute([':quiz_id' => $quiz_id, ':faculty_id' => $faculty_id]);
  $quiz = $stmt_quiz->fetch();

  if (!$quiz) {
      header('Location: manage_quizzes.php?error=not_found');
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

  // Fetch groups (classes and batches will be loaded dynamically via JS)
  $electives = $pdo->query("SELECT id, name FROM electives ORDER BY name ASC")->fetchAll();
  $re_exam_groups = $pdo->query("SELECT id, name FROM re_exam_groups ORDER BY name ASC")->fetchAll();

  // Fetch current mappings
  $stmt_c = $pdo->prepare("SELECT class_id FROM quiz_classes WHERE quiz_id = ?"); $stmt_c->execute([$quiz_id]); $current_classes = $stmt_c->fetchAll(PDO::FETCH_COLUMN);
  $stmt_b = $pdo->prepare("SELECT batch_id FROM quiz_batches WHERE quiz_id = ?"); $stmt_b->execute([$quiz_id]); $current_batches = $stmt_b->fetchAll(PDO::FETCH_COLUMN);
  $stmt_e = $pdo->prepare("SELECT elective_id FROM quiz_electives WHERE quiz_id = ?"); $stmt_e->execute([$quiz_id]); $current_electives = $stmt_e->fetchAll(PDO::FETCH_COLUMN);
  $stmt_r = $pdo->prepare("SELECT group_id FROM quiz_re_exam_groups WHERE quiz_id = ?"); $stmt_r->execute([$quiz_id]); $current_re_exam_groups = $stmt_r->fetchAll(PDO::FETCH_COLUMN);
  
  // Fetch all course_ids associated with this quiz's classes and batches
  $course_ids = [$quiz['course_id']];
  
  if (!empty($current_classes)) {
      $placeholders = implode(',', array_fill(0, count($current_classes), '?'));
      $stmt = $pdo->prepare("SELECT DISTINCT course_id FROM classes WHERE id IN ($placeholders)");
      $stmt->execute($current_classes);
      $course_ids = array_merge($course_ids, $stmt->fetchAll(PDO::FETCH_COLUMN));
  }
  
  if (!empty($current_batches)) {
      $placeholders = implode(',', array_fill(0, count($current_batches), '?'));
      $stmt = $pdo->prepare("SELECT DISTINCT course_id FROM classes WHERE id IN (SELECT class_id FROM batches WHERE id IN ($placeholders))");
      $stmt->execute($current_batches);
      $course_ids = array_merge($course_ids, $stmt->fetchAll(PDO::FETCH_COLUMN));
  }
  
  $course_ids = array_unique(array_filter($course_ids));
  
  $stmt_m = $pdo->prepare("SELECT s.sap_id FROM quiz_manual_students qms JOIN students s ON qms.student_id = s.user_id WHERE qms.quiz_id = ?"); 
  $stmt_m->execute([$quiz_id]); 
  $current_manual_saps = $stmt_m->fetchAll(PDO::FETCH_COLUMN);

  function format_datetime_for_input($datetime) {
      return date('Y-m-d\TH:i', strtotime($datetime));
  }

  $is_results_immediate_checked = $quiz['show_results_immediately'] ? 'checked' : '';
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
      margin-bottom: 8px;
  }
</style>

<div class="form-container" style="max-width: 1000px;">
  <h2>Edit Quiz Details</h2>
  <form action="<?= get_base_url() ?>api/faculty/update_quiz.php" method="POST">
    <input type="hidden" name="quiz_id" value="<?php echo htmlspecialchars($quiz['id']); ?>">
    
    <div class="form-group">
      <label for="title">Quiz Title</label>
      <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($quiz['title']); ?>" required>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label for="school_id">School</label>
            <select id="school_id" name="school_id" required>
                <option value="" disabled>-- Select a School --</option>
                <?php foreach ($schools as $school): ?>
                <option value="<?php echo $school['id']; ?>" <?php if($school['id'] == $quiz['school_id']) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($school['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="course_id">Course(s)</label>
            <select id="course_id" name="course_ids[]" class="course-select" multiple required>
                <option value="">-- Loading... --</option>
            </select>
        </div>
    </div>

    <div class="form-row">
      <div class="form-group"><label>Start Time</label><input type="text" id="start_time" name="start_time" value="<?php echo format_datetime_for_input($quiz['start_time']); ?>" required></div>
      <div class="form-group"><label>End Time</label><input type="text" id="end_time" name="end_time" value="<?php echo format_datetime_for_input($quiz['end_time']); ?>" required></div>
      <div class="form-group"><label>Duration (Minutes)</label><input type="number" name="duration_minutes" value="<?php echo htmlspecialchars($quiz['duration_minutes']); ?>" min="1" required></div>
    </div>
    <hr style="margin: 25px 0;">
    <h3 style="text-align: center;">Student & Question Configuration</h3>
    
    <div class="form-row">
        <div class="form-group" style="width: 100%;">
            <label for="exam_groups">Select Exam Groups</label>
            <select name="exam_groups[]" id="exam_groups" class="group-select" multiple>
                <!-- Classes and Batches will be populated dynamically via JavaScript based on selected courses -->

                <?php if (!empty($electives)): ?>
                <optgroup label="Electives">
                    <?php foreach ($electives as $elective): ?>
                        <option value="elective_<?php echo $elective['id']; ?>" <?php if (in_array($elective['id'], $current_electives)) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($elective['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
                <?php endif; ?>

                <?php if (!empty($re_exam_groups)): ?>
                <optgroup label="Re-Exam Groups">
                    <?php foreach ($re_exam_groups as $group): ?>
                        <option value="reexam_<?php echo $group['id']; ?>" <?php if (in_array($group['id'], $current_re_exam_groups)) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($group['name']); ?>
                        </option>
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
                    <option value="<?php echo htmlspecialchars($student['sap_id']); ?>" <?php if (in_array($student['sap_id'], $current_manual_saps)) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($student['full_name'] . ' (' . $student['sap_id'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    
    <div class="form-row">
      <div class="form-group"><label>Easy Questions</label><input type="number" name="config_easy_count" value="<?php echo htmlspecialchars($quiz['config_easy_count']); ?>" min="0" required></div>
      <div class="form-group"><label>Medium Questions</label><input type="number" name="config_medium_count" value="<?php echo htmlspecialchars($quiz['config_medium_count']); ?>" min="0" required></div>
      <div class="form-group"><label>Hard Questions</label><input type="number" name="config_hard_count" value="<?php echo htmlspecialchars($quiz['config_hard_count']); ?>" min="0" required></div>
    </div>

    <!-- NEW: Show Results Toggle -->
    <div class="form-group toggle-switch" style="display: flex; align-items: center; justify-content: flex-start; gap: 15px; background: #fff; padding: 15px 20px; border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
      <label class="switch" style="margin-bottom: 0; display: flex; align-items: center;">
        <input type="checkbox" id="show_results_immediately" name="show_results_immediately" value="1" <?= $quiz['show_results_immediately'] ? 'checked' : '' ?>>
      </label>
      <div style="display: flex; align-items: center; flex-wrap: wrap;">
        <label for="show_results_immediately" style="margin-bottom: 0; font-weight: 600; color: #444; font-size: 0.95em; letter-spacing: 0.5px;">SHOW RESULTS IMMEDIATELY?</label>
        <span style="font-size: 0.85em; color: #888; margin-left: 10px; font-style: italic;">(Descriptive questions will be evaluated later)</span>
      </div>
    </div>

    <!-- NEW: Calculator Toggle -->
    <div class="form-group toggle-switch" style="display: flex; align-items: center; justify-content: flex-start; gap: 15px; background: #fff; padding: 15px 20px; border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
      <label class="switch" style="margin-bottom: 0; display: flex; align-items: center;">
        <input type="checkbox" id="allow_calculator" name="allow_calculator" value="1" <?= $quiz['allow_calculator'] ? 'checked' : '' ?>>
      </label>
      <label for="allow_calculator" style="margin-bottom: 0; font-weight: 600; color: #444; font-size: 0.95em; letter-spacing: 0.5px;">ALLOW CALCULATOR DURING EXAM?</label>
    </div>

    <!-- NEW: Negative Marking Toggle -->
    <div class="form-group toggle-switch" style="display: flex; align-items: center; justify-content: flex-start; gap: 15px; background: #fff; padding: 15px 20px; border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
      <label class="switch" style="margin-bottom: 0; display: flex; align-items: center;">
        <input type="checkbox" id="enable_negative_marking" name="enable_negative_marking" value="1" <?= $quiz['enable_negative_marking'] ? 'checked' : '' ?>>
      </label>
      <label for="enable_negative_marking" style="margin-bottom: 0; font-weight: 600; color: #444; font-size: 0.95em; letter-spacing: 0.5px;">ENABLE NEGATIVE MARKING?</label>
    </div>

    <!-- NEW: Negative Marks Inputs (hidden by default unless enabled) -->
    <div class="form-row" id="negative_marks_row" style="<?= $quiz['enable_negative_marking'] ? '' : 'display: none;' ?> background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
      <div class="form-group" style="margin-bottom: 0;">
        <label for="negative_marks_mcq">MCQ</label>
        <input type="number" id="negative_marks_mcq" name="negative_marks_mcq" min="0" step="0.25" value="<?= htmlspecialchars($quiz['negative_marks_mcq']) ?>" class="input-field">
      </div>
      <div class="form-group" style="margin-bottom: 0;">
        <label for="negative_marks_msq">MSQ</label>
        <input type="number" id="negative_marks_msq" name="negative_marks_msq" min="0" step="0.25" value="<?= htmlspecialchars($quiz['negative_marks_msq']) ?>" class="input-field">
      </div>
      <div class="form-group" style="margin-bottom: 0;">
        <label for="negative_marks_descriptive">Descriptive</label>
        <input type="number" id="negative_marks_descriptive" name="negative_marks_descriptive" min="0" step="0.25" value="<?= htmlspecialchars($quiz['negative_marks_descriptive']) ?>" class="input-field">
      </div>
    </div>

    <div class="form-group" style="text-align: center; margin-top: 30px;">
      <button type="submit" class="button-red" style="width: auto; padding: 12px 40px;">Save Changes</button>
      <a href="manage_quizzes.php" style="display:inline-block; margin-left:15px; color:#555;">Cancel</a>
    </div>
  </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
const BASE_URL = '<?= get_base_url() ?>';
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Select2 on the new student dropdowns
    $('.student-select').select2({
        placeholder: "Search by Name or SAP ID",
        allowClear: true
    });
    $('.group-select').select2({
        placeholder: "Select groups",
        allowClear: true,
        width: '100%',
        dropdownCssClass: 'side-by-side-dropdown'
    });
    $('.course-select').select2({
        placeholder: "Select Courses",
        allowClear: true,
        width: '100%'
    });

    flatpickr("#start_time", {
        enableTime: true,
        dateFormat: "Y-m-d H:i",
        time_24hr: false
    });
    
    flatpickr("#end_time", {
        enableTime: true,
        dateFormat: "Y-m-d H:i",
        time_24hr: false
    });

    // --- Script for cascading dropdowns ---
    const schoolSelect = document.getElementById('school_id');
    const courseSelect = $('#course_id');
    const examGroupsSelect = $('#exam_groups');

    const preselectedCourseIds = <?php echo json_encode(array_values($course_ids)); ?>.map(String);
    
    // Pass existing selected classes and batches directly from PHP
    const currentClasses = <?php echo json_encode(array_map(function($id) { return 'class_'.$id; }, $current_classes)); ?>;
    const currentBatches = <?php echo json_encode(array_map(function($id) { return 'batch_'.$id; }, $current_batches)); ?>;
    const allCurrentSelections = currentClasses.concat(currentBatches);

    async function populateCourses(schoolId, selectedCourseIds = []) {
        courseSelect.empty();
        courseSelect.prop('disabled', true);
        if (!schoolId) return;

        const response = await fetch(BASE_URL + `api/shared/get_courses_by_school.php?school_id=${schoolId}`);
        const courses = await response.json();
        
        courses.forEach(course => {
            const option = new Option(course.name, course.id);
            if (selectedCourseIds.includes(String(course.id))) {
                option.selected = true;
            }
            courseSelect.append(option);
        });
        courseSelect.prop('disabled', false);
        courseSelect.trigger('change');
    }

    schoolSelect.addEventListener('change', function() {
        populateCourses(this.value);
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

        // Get currently selected values in the multiselect to preserve them
        const currentSelections = examGroupsSelect.val() || [];
        const mergedSelections = Array.from(new Set([...currentSelections, ...allCurrentSelections]));

        if (data.classes && data.classes.length > 0) {
            let classGroup = $('<optgroup label="Classes"></optgroup>');
            data.classes.forEach(cls => {
                let opt = new Option(cls.name, 'class_' + cls.id);
                if (mergedSelections.includes('class_' + cls.id)) {
                    opt.selected = true;
                }
                classGroup.append(opt);
            });
            examGroupsSelect.prepend(classGroup);
        }

        if (data.batches && data.batches.length > 0) {
            let batchGroup = $('<optgroup label="Batches"></optgroup>');
            data.batches.forEach(bat => {
                let opt = new Option(bat.name, 'batch_' + bat.id);
                $(opt).attr('data-class-id', bat.class_id);
                if (mergedSelections.includes('batch_' + bat.id)) {
                    opt.selected = true;
                }
                batchGroup.append(opt);
            });
            examGroupsSelect.find('optgroup[label="Classes"]').after(batchGroup);
        }

        examGroupsSelect.trigger('change');
    });

    examGroupsSelect.on('change', function() {
        const selected = $(this).val() || [];
        const selectedClassIds = selected
            .filter(val => val.startsWith('class_'))
            .map(val => val.replace('class_', ''));

        $(this).find('optgroup[label="Batches"] option').each(function() {
            const batchClassId = $(this).attr('data-class-id');
            if (selectedClassIds.includes(batchClassId)) {
                $(this).prop('disabled', true);
                if ($(this).is(':selected')) {
                    $(this).prop('selected', false);
                }
            } else {
                $(this).prop('disabled', false);
            }
        });
        
        $(this).select2({
            placeholder: "Select groups",
            allowClear: true,
            width: '100%',
            dropdownCssClass: 'side-by-side-dropdown'
        });
    });

    async function initializeDropdowns() {
        await populateCourses(schoolSelect.value, preselectedCourseIds);
    }
    
    initializeDropdowns();

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

    // Trigger change on load to set initial state
    $('#enable_negative_marking').trigger('change');
});
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>