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

  // Fetch groups
  $classes = $pdo->query("SELECT id, name FROM classes ORDER BY name ASC")->fetchAll();
  $batches = $pdo->query("SELECT id, name FROM batches ORDER BY name ASC")->fetchAll();
  $electives = $pdo->query("SELECT id, name FROM electives ORDER BY name ASC")->fetchAll();
  $re_exam_groups = $pdo->query("SELECT id, name FROM re_exam_groups ORDER BY name ASC")->fetchAll();

  // Fetch current mappings
  $stmt_c = $pdo->prepare("SELECT class_id FROM quiz_classes WHERE quiz_id = ?"); $stmt_c->execute([$quiz_id]); $current_classes = $stmt_c->fetchAll(PDO::FETCH_COLUMN);
  $stmt_b = $pdo->prepare("SELECT batch_id FROM quiz_batches WHERE quiz_id = ?"); $stmt_b->execute([$quiz_id]); $current_batches = $stmt_b->fetchAll(PDO::FETCH_COLUMN);
  $stmt_e = $pdo->prepare("SELECT elective_id FROM quiz_electives WHERE quiz_id = ?"); $stmt_e->execute([$quiz_id]); $current_electives = $stmt_e->fetchAll(PDO::FETCH_COLUMN);
  $stmt_r = $pdo->prepare("SELECT group_id FROM quiz_re_exam_groups WHERE quiz_id = ?"); $stmt_r->execute([$quiz_id]); $current_re_exam_groups = $stmt_r->fetchAll(PDO::FETCH_COLUMN);
  
  $stmt_m = $pdo->prepare("SELECT s.sap_id FROM quiz_manual_students qms JOIN students s ON qms.student_id = s.user_id WHERE qms.quiz_id = ?"); 
  $stmt_m->execute([$quiz_id]); 
  $current_manual_saps = $stmt_m->fetchAll(PDO::FETCH_COLUMN);

  function format_datetime_for_input($datetime) {
      return date('Y-m-d\TH:i', strtotime($datetime));
  }

  $is_results_immediate_checked = $quiz['show_results_immediately'] ? 'checked' : '';
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
  /* Optional: Improve Select2 appearance */
  .select2-container .select2-selection--single { height: 42px; border: 1px solid #ced4da; padding-top: 5px;}
  .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px; }
</style>

<div class="form-container">
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
            <label for="course_id">Course</label>
            <select id="course_id" name="course_id" required>
                <option value="">-- Loading... --</option>
            </select>
        </div>
    </div>

    <div class="form-row">
      <div class="form-group"><label>Start Time</label><input type="datetime-local" name="start_time" value="<?php echo format_datetime_for_input($quiz['start_time']); ?>" required></div>
      <div class="form-group"><label>Duration (Minutes)</label><input type="number" name="duration_minutes" value="<?php echo htmlspecialchars($quiz['duration_minutes']); ?>" min="1" required></div>
    </div>
    <hr style="margin: 25px 0;">
    <h3 style="text-align: center;">Student & Question Configuration</h3>
    
    <div class="form-row">
        <div class="form-group" style="width: 100%;">
            <label for="exam_groups">Select Exam Groups</label>
            <select name="exam_groups[]" id="exam_groups" class="group-select" multiple>
                <?php if (!empty($classes)): ?>
                <optgroup label="Classes">
                    <?php foreach ($classes as $class): ?>
                        <option value="class_<?php echo $class['id']; ?>" <?php if (in_array($class['id'], $current_classes)) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($class['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
                <?php endif; ?>

                <?php if (!empty($batches)): ?>
                <optgroup label="Batches">
                    <?php foreach ($batches as $batch): ?>
                        <option value="batch_<?php echo $batch['id']; ?>" <?php if (in_array($batch['id'], $current_batches)) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($batch['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
                <?php endif; ?>

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

    <!-- 'Show Results Immediately' removed to enforce manual publishing only via Reports button -->

    <!-- NEW: Calculator Toggle -->
    <div class="form-group toggle-switch">
      <label for="allow_calculator">Allow Calculator during Exam?</label>
      <label class="switch">
        <input type="checkbox" id="allow_calculator" name="allow_calculator" <?= $quiz['allow_calculator'] ? 'checked' : '' ?>>
        <span class="slider"></span>
      </label>
    </div>

    <!-- NEW: Negative Marking Toggle -->
    <div class="form-group toggle-switch">
      <label for="enable_negative_marking">Enable Negative Marking?</label>
      <label class="switch">
        <input type="checkbox" id="enable_negative_marking" name="enable_negative_marking" <?= $quiz['enable_negative_marking'] ? 'checked' : '' ?>>
        <span class="slider"></span>
      </label>
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
        allowClear: true
    });

    // --- Existing script for cascading dropdowns ---
    const schoolSelect = document.getElementById('school_id');
    const courseSelect = document.getElementById('course_id');

    const preselectedCourseId = <?php echo json_encode($quiz['course_id']); ?>;

    async function populateCourses(schoolId, selectedCourseId = null) {
        courseSelect.innerHTML = '<option value="">Loading...</option>';
        courseSelect.disabled = true;
        const response = await fetch(BASE_URL + `api/shared/get_courses_by_school.php?school_id=${schoolId}`);
        const courses = await response.json();
        courseSelect.innerHTML = '<option value="" disabled>-- Select a Course --</option>';
        courses.forEach(course => {
            const option = new Option(course.name, course.id);
            if (course.id == selectedCourseId) {
                option.selected = true;
            }
            courseSelect.add(option);
        });
        courseSelect.disabled = false;
    }

    schoolSelect.addEventListener('change', function() {
        populateCourses(this.value);
    });

    async function initializeDropdowns() {
        await populateCourses(schoolSelect.value, preselectedCourseId);
    }
    
    initializeDropdowns();
});
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>