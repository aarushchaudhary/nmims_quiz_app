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
  $sql = "SELECT q.id, q.title, q.start_time, c.name as course_name, es.name as status_name
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

    <div class="filter-form">
        <form method="GET" action="manage_quizzes.php">
            <div class="form-row">
                <div class="form-group" style="flex: 2;">
                    <label for="search">Search by Title</label>
                    <input type="text" name="search" id="search" class="input-field" placeholder="Enter quiz title..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label for="school">Filter by School</label>
                    <select name="school" id="school" class="input-field">
                        <option value="">All Schools</option>
                        <?php foreach($schools as $school): ?>
                        <option value="<?php echo $school['id']; ?>" <?php if($school['id'] == $school_filter) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($school['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label for="course">Filter by Course</label>
                    <select name="course" id="course" class="input-field">
                        <option value="">All Courses</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label for="date">Filter by Date</label>
                    <input type="date" name="date" id="date" class="input-field" value="<?php echo htmlspecialchars($date_filter); ?>">
                </div>
                <div class="button-group">
                    <button type="submit" class="button-red" style="width:auto;">Filter</button>
                    <a href="manage_quizzes.php" class="button-red" style="width:auto; background-color:#6c757d;">Clear</a>
                </div>
            </div>
        </form>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Quiz ID</th><th>Title</th><th>Course</th><th>Start Time</th><th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody id="quiz-table-body">
            <?php if (empty($quizzes)): ?>
                <tr><td colspan="6" style="text-align:center;">No quizzes found matching your criteria.</td></tr>
            <?php else: ?>
                <?php foreach ($quizzes as $quiz): ?>
                    <tr id="quiz-row-<?php echo $quiz['id']; ?>">
                        <td><?php echo htmlspecialchars($quiz['id']); ?></td>
                        <td><?php echo htmlspecialchars($quiz['title']); ?></td>
                        <td><?php echo htmlspecialchars($quiz['course_name']); ?></td>
                        <td><?php echo date('M j, Y, g:i A', strtotime($quiz['start_time'])); ?></td>
                        <td>
                            <?php $status_class = strtolower(str_replace(' ', '_', $quiz['status_name'])); ?>
                            <span class="status-badge status-<?php echo htmlspecialchars($status_class); ?>"><?php echo htmlspecialchars($quiz['status_name']); ?></span>
                        </td>
                        <td class="action-buttons">
                            <a href="view_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn-manage">Manage Questions</a>
                            <a href="start_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn-start-quiz">Start Quiz</a>
                            <a href="edit_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn-edit">Edit Details</a>
                            <a href="reports.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn-reports">View Reports</a>
                            <a href="../shared/event_log_report.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn-logs">View Logs</a>
                            <button class="btn-delete-quiz" data-quiz-id="<?php echo $quiz['id']; ?>">Delete Quiz</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

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
        if (e.target && e.target.classList.contains('btn-delete-quiz')) {
            quizIdToDelete = e.target.dataset.quizId;
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
});
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>
