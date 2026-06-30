<?php
  $pageTitle = 'Manage Quizzes'; 
  
  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  // --- Authorization Check ---
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
      redirect('login.php');
      exit();
  }

  $faculty_id = $_SESSION['user_id'];

  // --- Get Filter Values & Build Query ---
  $search_query = $_GET['search'] ?? '';
  $school_filter = $_GET['school'] ?? '';
  $course_filter = $_GET['course'] ?? '';
  $date_filter = $_GET['date'] ?? '';

  $where_clauses = ['q.faculty_id = :faculty_id'];
  $params = [':faculty_id' => $faculty_id];

  if (!empty($search_query)) {
      $where_clauses[] = 'q.title LIKE :search';
      $params[':search'] = '%' . $search_query . '%';
  }
  if (!empty($school_filter)) {
      $where_clauses[] = 'c.school_id = :school_id';
      $params[':school_id'] = $school_filter;
  }
  if (!empty($course_filter)) {
      $where_clauses[] = 'q.course_id = :course_id';
      $params[':course_id'] = $course_filter;
  }
  if (!empty($date_filter)) {
      $where_clauses[] = 'DATE(q.start_time) = :start_date';
      $params[':start_date'] = $date_filter;
  }

  $where_sql = implode(' AND ', $where_clauses);

  // --- Pagination Logic ---
  $items_per_page = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
  $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
  $offset = ($current_page - 1) * $items_per_page;

  // Get total quizzes based on filters
  $stmt_total = $pdo->prepare("SELECT COUNT(DISTINCT q.id) FROM quizzes q JOIN courses c ON q.course_id = c.id WHERE $where_sql");
  $stmt_total->execute($params);
  $total_quizzes = $stmt_total->fetchColumn();
  $total_pages = ceil($total_quizzes / $items_per_page);

  // --- Fetch Quizzes for the current page ---
  $sql = "SELECT q.id, q.title, q.duration_minutes, q.start_time, q.end_time, q.show_results_immediately, c.name as course_name, es.name as status_name
          FROM quizzes q
          JOIN courses c ON q.course_id = c.id
          JOIN exam_statuses es ON q.status_id = es.id
          WHERE $where_sql
          ORDER BY q.created_at DESC
          LIMIT :limit OFFSET :offset";
  
  $stmt = $pdo->prepare($sql);
  
  // **FIX:** Bind LIMIT and OFFSET as integer parameters
  $stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  foreach ($params as $key => &$val) {
      $param_type = is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR;
      $stmt->bindParam($key, $val, $param_type);
  }
  unset($val);

  $stmt->execute();
  $quizzes = $stmt->fetchAll();

  // --- Check pending evaluations for ended quizzes ---
  $pending_evals = [];
  foreach ($quizzes as $q) {
      if ($q['status_name'] === 'Completed' || strtotime($q['end_time']) < time()) {
          $eval_stmt = $pdo->prepare("
              SELECT COUNT(*) FROM student_answers sa
              JOIN questions q ON sa.question_id = q.id
              WHERE q.quiz_id = :quiz_id AND q.question_type_id = 3 AND sa.score_awarded IS NULL
          ");
          $eval_stmt->execute([':quiz_id' => $q['id']]);
          $pending_evals[$q['id']] = $eval_stmt->fetchColumn();
      }
  }

  // Fetch all schools for the filter dropdown
  $schools = $pdo->query("SELECT id, name FROM schools ORDER BY name ASC")->fetchAll();
?>

<div class="confirm-modal-overlay" id="delete-quiz-modal">
    <div class="confirm-modal">
        <h3>Confirm Quiz Deletion</h3>
        <p>Are you sure you want to permanently delete this quiz? All associated data will be lost.</p>
        <div class="button-group">
            <button class="btn-cancel" id="cancel-delete-btn">Cancel</button>
            <button class="btn-confirm-delete" id="confirm-delete-btn">Yes, Delete Quiz</button>
        </div>
    </div>
</div>

<div class="manage-container">
    <h2>My Quizzes</h2>
    <?php if (isset($_GET['success'])) { echo '<div class="message-box success-message">' . htmlspecialchars($_GET['success']) . '</div>'; } ?>

    <div class="section-box" style="margin-bottom: 25px; padding: 20px 25px;">
        <h4 style="margin: 0 0 15px 0; color: #555; font-size: 0.95em; text-transform: uppercase; letter-spacing: 0.5px;">Search & Filter Quizzes</h4>
        <form method="GET" action="manage_quizzes.php">
            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 15px; align-items: end;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="search" style="font-size: 0.85em; color: #666;">Search by Title</label>
                    <input type="text" name="search" id="search" class="input-field" style="margin-bottom: 0;" placeholder="Enter quiz title..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="school" style="font-size: 0.85em; color: #666;">School</label>
                    <select name="school" id="school" class="input-field" style="margin-bottom: 0;">
                        <option value="">All Schools</option>
                        <?php foreach($schools as $school): ?>
                        <option value="<?php echo $school['id']; ?>" <?php if($school['id'] == $school_filter) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($school['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="course" style="font-size: 0.85em; color: #666;">Course</label>
                    <select name="course" id="course" class="input-field" style="margin-bottom: 0;">
                        <option value="">All Courses</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="date" style="font-size: 0.85em; color: #666;">Date</label>
                    <input type="date" name="date" id="date" class="input-field" style="margin-bottom: 0;" value="<?php echo htmlspecialchars($date_filter); ?>">
                </div>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 15px;">
                <button type="submit" class="button-red" style="width: auto; padding: 10px 24px; margin-bottom: 0;">Find Quizzes</button>
                <a href="manage_quizzes.php" class="button-red" style="width: auto; padding: 10px 24px; background-color: #6c757d; margin-bottom: 0; text-decoration: none;">Clear</a>
            </div>
        </form>
    </div>

    <style>
        .filter-form {
            background-color: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 20px 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .quiz-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .quiz-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .quiz-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        .quiz-card-header h3 {
            margin: 0;
            font-size: 1.35em;
            color: #222;
            font-weight: 700;
            line-height: 1.3;
            max-width: 70%;
            word-wrap: break-word;
        }
        .quiz-card-body {
            margin-bottom: 20px;
            font-size: 0.9em;
            color: #555;
            flex-grow: 1;
        }
        .quiz-card-body p {
            margin: 5px 0;
        }
        .quiz-card-actions-primary {
            margin-bottom: 12px;
        }
        .quiz-card-actions-primary a, .quiz-card-actions-primary button {
            text-align: center;
            padding: 12px 10px;
            font-size: 1.05em;
            font-weight: 600;
            border-radius: 6px;
            margin: 0 !important;
            box-sizing: border-box;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            text-decoration: none;
            background-color: #198754;
            color: #ffffff;
            border: 1px solid #146c43;
            transition: all 0.2s ease;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(25, 135, 84, 0.2);
        }
        .quiz-card-actions-primary a:hover:not([disabled]), .quiz-card-actions-primary button:hover:not([disabled]) {
            background-color: #157347;
            border-color: #0f5132;
        }
        .quiz-card-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .quiz-card-actions a, .quiz-card-actions button {
            text-align: center;
            padding: 10px 5px;
            font-size: 0.85em;
            border-radius: 6px;
            margin: 0 !important;
            box-sizing: border-box;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            text-decoration: none;
            font-weight: 500;
            background-color: #f8f9fa;
            color: #495057;
            border: 1px solid #ced4da;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .quiz-card-actions a:hover, .quiz-card-actions button:hover {
            background-color: #e9ecef;
            color: #212529;
            border-color: #adb5bd;
        }
    </style>

    <?php
    // Prepare statements for counting students
    $stmt_appeared = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM student_attempts WHERE quiz_id = ?");
    $stmt_total = $pdo->prepare("
        SELECT COUNT(DISTINCT s.user_id) 
        FROM students s 
        LEFT JOIN quiz_manual_students qms ON qms.quiz_id = ? AND qms.student_id = s.user_id
        LEFT JOIN quiz_classes qc ON qc.quiz_id = ?
        LEFT JOIN classes c ON qc.class_id = c.id
        LEFT JOIN quiz_batches qb ON qb.quiz_id = ?
        LEFT JOIN batches b ON qb.batch_id = b.id
        LEFT JOIN classes bc ON b.class_id = bc.id
        LEFT JOIN quiz_electives qe ON qe.quiz_id = ?
        LEFT JOIN elective_students es_stu ON qe.elective_id = es_stu.elective_id AND es_stu.student_id = s.user_id
        LEFT JOIN quiz_re_exam_groups qrg ON qrg.quiz_id = ?
        LEFT JOIN re_exam_group_students regs ON qrg.group_id = regs.group_id AND regs.student_id = s.user_id
        WHERE (
            qms.student_id IS NOT NULL
            OR (
                c.id IS NOT NULL AND 
                s.course_id = c.course_id AND 
                s.graduation_year = c.graduation_year AND 
                CAST(s.sap_id AS UNSIGNED) BETWEEN c.sap_id_range_start AND c.sap_id_range_end
            )
            OR (
                b.id IS NOT NULL AND 
                s.course_id = bc.course_id AND 
                s.graduation_year = bc.graduation_year AND 
                CAST(s.sap_id AS UNSIGNED) BETWEEN b.sap_id_range_start AND b.sap_id_range_end
            )
            OR es_stu.student_id IS NOT NULL
            OR regs.student_id IS NOT NULL
        )
    ");

    // Prepare statements for fetching specific groups assigned to the quiz
    $stmt_classes = $pdo->prepare("SELECT c.name FROM classes c JOIN quiz_classes qc ON qc.class_id = c.id WHERE qc.quiz_id = ?");
    $stmt_batches = $pdo->prepare("SELECT b.name FROM batches b JOIN quiz_batches qb ON qb.batch_id = b.id WHERE qb.quiz_id = ?");
    $stmt_electives = $pdo->prepare("SELECT e.name FROM electives e JOIN quiz_electives qe ON qe.elective_id = e.id WHERE qe.quiz_id = ?");
    $stmt_re_exams = $pdo->prepare("SELECT r.name FROM re_exam_groups r JOIN quiz_re_exam_groups qr ON qr.group_id = r.id WHERE qr.quiz_id = ?");
    ?>

    <div class="quiz-grid" id="quiz-table-body">
        <?php if (empty($quizzes)): ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 40px; background: #fff; border-radius: 8px; border: 1px solid #e0e0e0;">
                <p>No quizzes found matching your criteria.</p>
            </div>
        <?php else: ?>
            <?php foreach ($quizzes as $quiz): 
                // Parse the title
                $title_parts = explode(' - ', $quiz['title']);
                if (count($title_parts) >= 4) {
                    $school = trim($title_parts[0]);
                    $course = trim($title_parts[1]);
                    $display_title = trim($title_parts[2]);
                    $sections = isset($title_parts[4]) ? trim($title_parts[4]) : 'N/A';
                } else {
                    $display_title = $quiz['title'];
                    $school = 'N/A';
                    $course = $quiz['course_name'];
                    $sections = 'N/A';
                }

                // Get student counts
                $stmt_appeared->execute([$quiz['id']]);
                $students_appeared = $stmt_appeared->fetchColumn();
                $stmt_total->execute([$quiz['id'], $quiz['id'], $quiz['id'], $quiz['id'], $quiz['id']]);
                $total_students_quiz = $stmt_total->fetchColumn();

                // Get specific groups assigned to this quiz
                $stmt_classes->execute([$quiz['id']]);
                $classes_list = $stmt_classes->fetchAll(PDO::FETCH_COLUMN);

                $stmt_batches->execute([$quiz['id']]);
                $batches_list = $stmt_batches->fetchAll(PDO::FETCH_COLUMN);
                
                $sections_arr = array_merge($classes_list, $batches_list);
                $sections_str = !empty($sections_arr) ? implode(', ', $sections_arr) : 'N/A';

                $stmt_electives->execute([$quiz['id']]);
                $electives_list = $stmt_electives->fetchAll(PDO::FETCH_COLUMN);
                $electives_str = !empty($electives_list) ? implode(', ', $electives_list) : 'N/A';

                $stmt_re_exams->execute([$quiz['id']]);
                $re_exams_list = $stmt_re_exams->fetchAll(PDO::FETCH_COLUMN);
                $re_exams_str = !empty($re_exams_list) ? implode(', ', $re_exams_list) : 'N/A';
            ?>
                <div class="quiz-card" id="quiz-row-<?php echo $quiz['id']; ?>">
                    <div class="quiz-card-header" style="display: flex; align-items: center;">
                        <h3 style="margin: 0;"><?php echo htmlspecialchars($display_title); ?></h3>
                        <?php $status_class = strtolower(str_replace(' ', '_', $quiz['status_name'])); ?>
                        <span class="status-badge status-<?php echo htmlspecialchars($status_class); ?>" style="font-size: 0.65em; padding: 4px 8px; margin-left: 12px; flex-shrink: 0; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;"><?php echo htmlspecialchars($quiz['status_name']); ?></span>
                        
                        <button class="btn-delete-quiz" data-quiz-id="<?php echo $quiz['id']; ?>" title="Delete Quiz" style="margin-left: auto; background: transparent; border: none; cursor: pointer; padding: 6px; color: #dc3545; display: flex; align-items: center; justify-content: center; border-radius: 4px; transition: background 0.2s;" onmouseover="this.style.background='#ffebeb'" onmouseout="this.style.background='transparent'">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                        </button>
                    </div>
                    <div class="quiz-card-body">
                        <p><strong>School:</strong> <?php echo htmlspecialchars($school); ?></p>
                        <p><strong>Course:</strong> <?php echo htmlspecialchars($course); ?></p>
                        <p><strong>Sections:</strong> <?php echo htmlspecialchars($sections_str); ?></p>
                        <?php if ($electives_str !== 'N/A'): ?>
                        <p><strong>Electives:</strong> <?php echo htmlspecialchars($electives_str); ?></p>
                        <?php endif; ?>
                        <?php if ($re_exams_str !== 'N/A'): ?>
                        <p><strong>Re-exams:</strong> <?php echo htmlspecialchars($re_exams_str); ?></p>
                        <?php endif; ?>
                        <p><strong>Start Time:</strong> <?php echo date('M j, Y, g:i A', strtotime($quiz['start_time'])); ?></p>
                        <p><strong>End Time:</strong> <?php echo date('M j, Y, g:i A', strtotime($quiz['end_time'])); ?></p>
                        <p><strong>Duration:</strong> <?php echo htmlspecialchars($quiz['duration_minutes']); ?> mins</p>
                        <p><strong>Students:</strong> 
                            <?php if ($quiz['status_name'] == 'Not Started'): ?>
                                N/A
                            <?php else: ?>
                                <?php echo $students_appeared; ?> / <?php echo $total_students_quiz; ?> appeared
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="quiz-card-actions-primary">
                        <?php if ($quiz['status_name'] === 'Completed' || strtotime($quiz['end_time']) < time()): ?>
                            <?php 
                                $has_pending = isset($pending_evals[$quiz['id']]) && $pending_evals[$quiz['id']] > 0;
                                $has_descriptive = !$quiz['show_results_immediately'];
                                $is_published = $quiz['show_results_immediately'];
                            ?>
                            <?php if ($is_published): ?>
                                <button class="btn-start-quiz btn-unpublish-results" data-quiz-id="<?php echo $quiz['id']; ?>" style="background-color: #dc3545; border-color: #c82333; color: #fff; cursor: pointer;" title="Unpublish results">
                                    Unpublish Results
                                </button>
                            <?php elseif ($has_descriptive && $has_pending): ?>
                                <a href="evaluate_descriptive.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn-start-quiz" style="background-color: #ffc107; border-color: #e0a800; color: #333; text-decoration: none; font-size: 0.85em;" title="Evaluate answers before publishing">
                                    Evaluate Answers to Publish Results
                                </a>
                            <?php else: ?>
                                <button class="btn-start-quiz btn-publish-results" data-quiz-id="<?php echo $quiz['id']; ?>" style="background-color: #28a745; border-color: #218838; color: #fff; cursor: pointer;" title="Publish results for students">
                                    Publish Results
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="start_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn-start-quiz" title="Start Quiz">Start Quiz</a>
                        <?php endif; ?>
                    </div>
                    <div class="quiz-card-actions">
                        <?php 
                            $is_ended = ($quiz['status_name'] === 'Completed' || strtotime($quiz['end_time']) < time());
                            $disabled_style = $is_published ? 'opacity: 0.5; pointer-events: none; cursor: default; background-color: #f8f9fa; color: #6c757d; border-color: #dee2e6;' : '';
                            $edit_disabled_style = ($is_ended || $is_published) ? 'opacity: 0.5; pointer-events: none; cursor: default; background-color: #f8f9fa; color: #6c757d; border-color: #dee2e6;' : '';
                        ?>
                        <a href="<?php echo $is_ended ? 'display_questions.php?id=' : 'view_quiz.php?id='; ?><?php echo $quiz['id']; ?>" class="btn-manage" title="<?php echo $is_ended ? 'Display Questions' : 'Manage Questions'; ?>">
                            <?php echo $is_ended ? 'Display Questions' : 'Manage Questions'; ?>
                        </a>
                        <a href="edit_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn-edit" style="<?php echo $edit_disabled_style; ?>" title="Edit Details">Edit Details</a>
                        <a href="reports.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn-reports" title="View Reports">View Reports</a>
                        
                        <a href="evaluate_descriptive.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn-evaluate" style="<?php echo $disabled_style; ?>" title="Evaluate Answers">Evaluate Answers</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="pagination-controls">
        <span class="page-info">Showing <?php echo count($quizzes); ?> of <?php echo $total_quizzes; ?> quizzes</span>
        <div class="page-links">
            <?php
            $query_params = http_build_query(['limit' => $items_per_page, 'search' => $search_query, 'school' => $school_filter, 'course' => $course_filter, 'date' => $date_filter]);
            for ($i = 1; $i <= $total_pages; $i++):
            ?>
                <a href="?page=<?php echo $i; ?>&<?php echo $query_params; ?>" class="<?php if ($i == $current_page) echo 'current-page'; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    </div>
</div>

<script>
const BASE_URL = '<?= get_base_url() ?>';
document.addEventListener('DOMContentLoaded', function() {
    const schoolSelect = document.getElementById('school');
    const courseSelect = document.getElementById('course');
    const preselectedCourseId = <?php echo json_encode($course_filter); ?>;

    async function populateCourses(schoolId, selectedCourseId = null) {
        if (!schoolId) {
            courseSelect.innerHTML = '<option value="">All Courses</option>';
            return;
        }
        courseSelect.innerHTML = '<option value="">Loading...</option>';
        try {
            const response = await fetch(BASE_URL + `api/shared/get_courses_by_school.php?school_id=${schoolId}`);
            const courses = await response.json();
            courseSelect.innerHTML = '<option value="">All Courses</option>';
            courses.forEach(course => {
                const option = new Option(course.name, course.id);
                if (course.id == selectedCourseId) {
                    option.selected = true;
                }
                courseSelect.add(option);
            });
        } catch (error) {
            console.error('Failed to load courses:', error);
            courseSelect.innerHTML = '<option value="">Error loading</option>';
        }
    }

    schoolSelect.addEventListener('change', function() {
        populateCourses(this.value);
    });

    if (schoolSelect.value) {
        populateCourses(schoolSelect.value, preselectedCourseId);
    }
    
    // --- Delete Modal Logic ---
    const modal = document.getElementById('delete-quiz-modal');
    const cancelBtn = document.getElementById('cancel-delete-btn');
    const confirmBtn = document.getElementById('confirm-delete-btn');
    const quizTableBody = document.getElementById('quiz-table-body');
    let quizIdToDelete = null;

    quizTableBody.addEventListener('click', function(e) {
        const deleteBtn = e.target.closest('.btn-delete-quiz');
        if (deleteBtn) {
            quizIdToDelete = deleteBtn.dataset.quizId;
            modal.style.display = 'flex';
        }
    });

    cancelBtn.addEventListener('click', () => {
        modal.style.display = 'none';
        quizIdToDelete = null;
    });

    confirmBtn.addEventListener('click', async () => {
        if (quizIdToDelete) {
            try {
                const response = await fetch(BASE_URL+'api/faculty/delete_quiz.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ quiz_id: quizIdToDelete })
                });
                const result = await response.json();
                if (result.success) {
                    document.getElementById(`quiz-row-${quizIdToDelete}`).remove();
                } else {
                    throw new Error(result.error || 'Failed to delete quiz.');
                }
            } catch (error) {
                alert(`Error: ${error.message}`);
            } finally {
                modal.style.display = 'none';
                quizIdToDelete = null;
            }
        }
    });

    // --- Publish/Unpublish Results Logic ---
    quizTableBody.addEventListener('click', async function(e) {
        if (e.target && (e.target.classList.contains('btn-publish-results') || e.target.classList.contains('btn-unpublish-results'))) {
            const isPublishing = e.target.classList.contains('btn-publish-results');
            const quizId = e.target.dataset.quizId;
            const action = isPublishing ? 'publish' : 'unpublish';
            const confirmMsg = isPublishing 
                ? 'Are you sure you want to publish the results for this exam? Students will be able to view their scores and answers immediately.'
                : 'Are you sure you want to unpublish the results? Students will no longer be able to view their scores and answers.';

            if (confirm(confirmMsg)) {
                try {
                    const response = await fetch(BASE_URL+'api/faculty/publish_results.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ quiz_id: quizId, action: action })
                    });
                    const result = await response.json();
                    if (result.success) {
                        // Reload the page to reflect all UI state changes (buttons, styling, text)
                        window.location.reload();
                    } else {
                        throw new Error(result.error || `Failed to ${action} results.`);
                    }
                } catch (error) {
                    alert(`Error: ${error.message}`);
                }
            }
        }
    });
});
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>
