<?php
  $pageTitle = 'Event Log Report';
  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  // --- Authorization Check (Allows any non-student role) ---
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] == 4) {
      redirect('login.php');
      exit();
  }

  // --- Get User's School (if School Head) ---
  $user_school_id = null;
  if ($_SESSION['role_id'] == 5) {
      $stmt = $pdo->prepare("SELECT school_id FROM heads WHERE user_id = ?");
      $stmt->execute([$_SESSION['user_id']]);
      $user_school_id = $stmt->fetchColumn();
  }

  // --- Fetch data for filter dropdowns ---
  if ($user_school_id) {
      $schools = $pdo->query("SELECT id, name FROM schools WHERE id = " . (int)$user_school_id)->fetchAll();
  } else {
      $schools = $pdo->query("SELECT id, name FROM schools ORDER BY name ASC")->fetchAll();
  }

  // --- Get Filter Values & Build Query to populate the quiz selector ---
  $search_query = $_GET['search'] ?? '';
  $school_filter = $user_school_id ? $user_school_id : ($_GET['school'] ?? '');
  $course_filter = $_GET['course'] ?? '';
  $date_filter = $_GET['date'] ?? '';

  $where_clauses = ['1=1']; // Start with a true condition as this is a shared report
  $params = [];

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

  // Fetch quizzes for the dropdown based on the filters applied
  $sql_quizzes = "SELECT q.id, q.title FROM quizzes q JOIN courses c ON q.course_id = c.id WHERE $where_sql ORDER BY q.created_at DESC";
  $quizzes_stmt = $pdo->prepare($sql_quizzes);
  $quizzes_stmt->execute($params);
  $quizzes = $quizzes_stmt->fetchAll();
  
  $preselected_quiz_id = isset($_GET['quiz_id']) ? filter_var($_GET['quiz_id'], FILTER_VALIDATE_INT) : null;
?>

<div class="manage-container">
    <h2>Exam Event Logs</h2>

    <div class="filter-form">
        <form method="GET" action="event_log_report.php">
            <div class="form-row">
                <div class="form-group" style="flex: 2;"><label for="search">Search by Quiz Title</label><input type="text" name="search" id="search" class="input-field" placeholder="Enter quiz title..." value="<?php echo htmlspecialchars($search_query); ?>"></div>
                <div class="form-group" style="flex: 1;"><label for="school">Filter by School</label><select name="school" id="school" class="input-field" <?php if($user_school_id) echo 'readonly style="pointer-events: none; background-color: #e9ecef;"'; ?>><?php if(!$user_school_id): ?><option value="">All Schools</option><?php endif; ?><?php foreach($schools as $school): ?><option value="<?php echo $school['id']; ?>" <?php if($school['id'] == $school_filter) echo 'selected'; ?>><?php echo htmlspecialchars($school['name']); ?></option><?php endforeach; ?></select></div>
                <div class="form-group" style="flex: 1;"><label for="course">Filter by Course</label><select name="course" id="course" class="input-field"><option value="">All Courses</option></select></div>
                <div class="form-group" style="flex: 1;"><label for="date">Filter by Date</label><input type="date" name="date" id="date" class="input-field" value="<?php echo htmlspecialchars($date_filter); ?>"></div>
                <div class="button-group"><button type="submit" class="button-red" style="width:auto;">Find Quizzes</button><a href="event_log_report.php" class="button-red" style="width:auto; background-color:#6c757d;">Clear</a></div>
            </div>
        </form>
    </div>
    <hr>
    <div class="report-header">
        <div class="form-group" style="margin-bottom:0; flex-grow: 1;">
            <label for="quiz_id_selector">Select a Quiz from Filtered Results:</label>
            <select id="quiz_id_selector" class="input-field" style="padding: 8px;">
                <option value="">-- Choose a Quiz to View Logs --</option>
                <?php foreach ($quizzes as $quiz): ?>
                    <option value="<?php echo $quiz['id']; ?>" <?php if ($quiz['id'] == $preselected_quiz_id) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($quiz['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <table class="data-table" id="log-table" style="display:none;">
        <thead><tr><th>Timestamp</th><th>Student Name</th><th>SAP ID</th><th>Event Type</th><th>Description</th><th>IP Address</th></tr></thead>
        <tbody id="log-table-body"></tbody>
    </table>
    <p id="log-placeholder">Use the filters to find a quiz, then select it from the dropdown to view its event log.</p>
</div>

<script>
const BASE_URL = '<?= get_base_url() ?>';
document.addEventListener('DOMContentLoaded', function() {
    // --- Cascading Dropdown Logic ---
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
                if (course.id == selectedCourseId) option.selected = true;
                courseSelect.add(option);
            });
        } catch (error) {
            console.error('Failed to load courses:', error);
            courseSelect.innerHTML = '<option value="">Error loading</option>';
        }
    }

    schoolSelect.addEventListener('change', function() { populateCourses(this.value); });
    if (schoolSelect.value) { populateCourses(schoolSelect.value, preselectedCourseId); }

    // --- Log Loading Logic ---
    const quizSelector = document.getElementById('quiz_id_selector');
    const logTable = document.getElementById('log-table');
    const placeholder = document.getElementById('log-placeholder');
    const tableBody = document.getElementById('log-table-body');

    async function loadLogs(quizId) {
        if (!quizId) {
            logTable.style.display = 'none';
            placeholder.style.display = 'block';
            return;
        }
        placeholder.textContent = 'Loading logs...';
        try {
            const response = await fetch(BASE_URL + `api/shared/get_event_logs.php?quiz_id=${quizId}`);
            const logs = await response.json();
            if (!response.ok) throw new Error(logs.error || 'Failed to fetch logs.');

            tableBody.innerHTML = '';
            if (logs.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No events have been logged for this quiz.</td></tr>';
            } else {
                logs.forEach(log => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${new Date(log.timestamp).toLocaleString()}</td>
                        <td>${log.student_name ?? 'N/A'}</td>
                        <td>${log.sap_id ?? 'N/A'}</td>
                        <td>${log.event_type}</td>
                        <td>${log.description}</td>
                        <td>${log.ip_address}</td>
                    `;
                    tableBody.appendChild(tr);
                });
            }
            placeholder.style.display = 'none';
            logTable.style.display = 'table';
        } catch (error) {
            console.error("Error loading event logs:", error);
            placeholder.textContent = `Error: ${error.message}`;
        }
    }

    quizSelector.addEventListener('change', function() { loadLogs(this.value); });
    if (quizSelector.value) { loadLogs(quizSelector.value); }
});
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>
