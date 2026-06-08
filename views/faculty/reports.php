<?php
  $pageTitle = 'Quiz Reports';

  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  // --- Authorization Check ---
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
      redirect('login.php');
      exit();
  }
  $faculty_id = $_SESSION['user_id'];

  // --- Fetch data for filter dropdowns ---
  $schools = $pdo->query("SELECT id, name FROM schools ORDER BY name ASC")->fetchAll();

  // --- Get Filter Values & Build Query to populate the quiz selector ---
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

  $sql_quizzes = "SELECT q.id, q.title FROM quizzes q JOIN courses c ON q.course_id = c.id WHERE $where_sql ORDER BY q.created_at DESC";
  $quizzes_stmt = $pdo->prepare($sql_quizzes);
  $quizzes_stmt->execute($params);
  $quizzes = $quizzes_stmt->fetchAll();
  
  $preselected_quiz_id = isset($_GET['quiz_id']) ? filter_var($_GET['quiz_id'], FILTER_VALIDATE_INT) : null;
?>

<div class="manage-container">
    <h2>View Quiz Results</h2>

    <div class="filter-form">
        <form method="GET" action="reports.php">
            <div class="form-row">
                <div class="form-group" style="flex: 2;"><label for="search">Search by Title</label><input type="text" name="search" id="search" class="input-field" placeholder="Enter quiz title..." value="<?php echo htmlspecialchars($search_query); ?>"></div>
                <div class="form-group" style="flex: 1;"><label for="school">Filter by School</label><select name="school" id="school" class="input-field"><option value="">All Schools</option><?php foreach($schools as $school): ?><option value="<?php echo $school['id']; ?>" <?php if($school['id'] == $school_filter) echo 'selected'; ?>><?php echo htmlspecialchars($school['name']); ?></option><?php endforeach; ?></select></div>
                <div class="form-group" style="flex: 1;"><label for="course">Filter by Course</label><select name="course" id="course" class="input-field"><option value="">All Courses</option></select></div>
                <div class="form-group" style="flex: 1;"><label for="date">Filter by Date</label><input type="date" name="date" id="date" class="input-field" value="<?php echo htmlspecialchars($date_filter); ?>"></div>
                <div class="button-group"><button type="submit" class="button-red" style="width:auto;">Find Quizzes</button><a href="reports.php" class="button-red" style="width:auto; background-color:#6c757d;">Clear</a></div>
            </div>
        </form>
    </div>
    <hr>
    <div class="report-header">
        <div class="form-group" style="margin-bottom:0; flex-grow: 1;">
            <label for="quiz_id_selector">Select a Quiz from Filtered Results:</label>
            <select id="quiz_id_selector" class="input-field" style="padding: 8px;">
                <option value="">-- Choose a Quiz --</option>
                <?php foreach ($quizzes as $quiz): ?>
                    <option value="<?php echo $quiz['id']; ?>" <?php if ($quiz['id'] == $preselected_quiz_id) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($quiz['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            <a href="#" id="analysis-btn" class="button-red" style="display:none; width:auto; padding: 10px 20px; background-color: #6f42c1;">Item Analysis</a>
            <a href="#" id="evaluate-btn" class="button-red" style="display:none; width:auto; padding: 10px 20px; background-color: #ffc107; color: #333;">Evaluate Answers</a>
            <a href="#" id="export-btn" class="button-red" style="display:none; width:auto; padding: 10px 20px; background-color: #17a2b8;">Export to Excel</a>
            <button id="publish-results-btn" class="button-red" style="display:none; width:auto; padding: 10px 20px; background-color: #28a745; color: white; border: none; cursor: pointer;">Publish Results</button>
        </div>
    </div>

    <div id="report-content" style="display:none;">
        <div class="report-summary-grid">
            <div class="summary-card"><p class="card-title">Total Attempts</p><p class="card-value" id="summary-attempts">0</p></div>
            <div class="summary-card"><p class="card-title">Average Score</p><p class="card-value" id="summary-avg-score">0.00</p></div>
            <div class="summary-card"><p class="card-title">Disqualified</p><p class="card-value" id="summary-disqualified">0</p></div>
        </div>
        <table class="data-table results-table">
            <thead><tr><th>Student Name</th><th>SAP ID</th><th>Score</th><th>Time Taken</th><th>Status</th></tr></thead>
            <tbody id="results-table-body"></tbody>
        </table>
    </div>
    <p id="report-placeholder">Use the filters to find a quiz, then select it from the dropdown to view the report.</p>
</div>

<script>
const BASE_URL = '<?= get_base_url() ?>';
document.addEventListener('DOMContentLoaded', function() {
    // --- Cascading Dropdown Logic ---
    const schoolSelect = document.getElementById('school');
    const courseSelect = document.getElementById('course');
    const preselectedCourseId = <?php echo json_encode($course_filter); ?>;
    async function populateCourses(schoolId, selectedCourseId = null) {
        if (!schoolId) { courseSelect.innerHTML = '<option value="">All Courses</option>'; return; }
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
        } catch (error) { courseSelect.innerHTML = '<option value="">Error loading</option>'; }
    }
    schoolSelect.addEventListener('change', function() { populateCourses(this.value); });
    if (schoolSelect.value) { populateCourses(schoolSelect.value, preselectedCourseId); }

    // --- Report Loading Logic ---
    const quizSelector = document.getElementById('quiz_id_selector');
    const reportContent = document.getElementById('report-content');
    const placeholder = document.getElementById('report-placeholder');
    const exportBtn = document.getElementById('export-btn');
    const evaluateBtn = document.getElementById('evaluate-btn');
    const analysisBtn = document.getElementById('analysis-btn');
    const publishBtn = document.getElementById('publish-results-btn');
    
    async function loadReport(quizId) {
        if (!quizId) {
            reportContent.style.display = 'none';
            [exportBtn, evaluateBtn, analysisBtn, publishBtn].forEach(btn => btn.style.display = 'none');
            placeholder.textContent = 'Please select a quiz to view the report.';
            placeholder.style.display = 'block';
            return;
        }
        placeholder.textContent = 'Loading report...';
        try {
            const response = await fetch(BASE_URL + `api/faculty/get_quiz_results.php?quiz_id=${quizId}`);
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Failed to fetch results.');
            
            document.getElementById('summary-attempts').textContent = data.summary.total_attempts;
            document.getElementById('summary-avg-score').textContent = data.summary.average_score;
            document.getElementById('summary-disqualified').textContent = data.summary.disqualified_count;
            const tableBody = document.getElementById('results-table-body');
            tableBody.innerHTML = '';
            if (data.details.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No attempts found for this quiz.</td></tr>';
            } else {
                data.details.forEach(row => {
                    const tr = document.createElement('tr');
                    if (row.is_disqualified) tr.classList.add('disqualified');
                    let timeTaken = 'N/A';
                    if (row.started_at && row.submitted_at) {
                        const diffSeconds = Math.round((new Date(row.submitted_at.replace(' ', 'T')+'Z') - new Date(row.started_at.replace(' ', 'T')+'Z')) / 1000);
                        timeTaken = `${Math.floor(diffSeconds / 60)}m ${diffSeconds % 60}s`;
                    }
                    tr.innerHTML = `<td>${row.student_name ?? 'N/A'}</td><td>${row.sap_id ?? 'N/A'}</td><td>${parseFloat(row.total_score).toFixed(2)}</td><td>${timeTaken}</td><td>${row.is_disqualified ? 'Disqualified' : 'Completed'}</td>`;
                    tableBody.appendChild(tr);
                });
            }
            placeholder.style.display = 'none';
            reportContent.style.display = 'block';
            exportBtn.href = `${BASE_URL}api/faculty/export_results.php?quiz_id=${quizId}`;
            evaluateBtn.href = `evaluate_descriptive.php?quiz_id=${quizId}`;
            analysisBtn.href = `item_analysis.php?quiz_id=${quizId}`;
            [exportBtn, evaluateBtn, analysisBtn].forEach(btn => btn.style.display = 'inline-block');
            
            if (data.summary.is_published) {
                publishBtn.style.display = 'inline-block';
                publishBtn.style.backgroundColor = '#6c757d';
                publishBtn.textContent = 'Results Published';
                publishBtn.disabled = true;
                publishBtn.style.cursor = 'default';
            } else if (data.summary.total_attempts > 0) {
                publishBtn.style.display = 'inline-block';
                publishBtn.style.backgroundColor = '#28a745';
                publishBtn.textContent = 'Publish Results';
                publishBtn.disabled = false;
                publishBtn.style.cursor = 'pointer';
            } else {
                publishBtn.style.display = 'none';
            }
        } catch (error) {
            console.error("Error loading report:", error);
            placeholder.textContent = `Error: ${error.message}`;
        }
    }
    quizSelector.addEventListener('change', function() { loadReport(this.value); });
    if (quizSelector.value) { loadReport(quizSelector.value); }

    publishBtn.addEventListener('click', async function(e) {
        e.preventDefault();
        const quizId = quizSelector.value;
        if (!quizId) return;
        
        if (confirm('Are you sure you want to publish the results for this exam? Students will be able to view their scores and answers immediately.')) {
            try {
                publishBtn.disabled = true;
                publishBtn.textContent = 'Publishing...';
                const response = await fetch(BASE_URL + 'api/faculty/publish_results.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ quiz_id: quizId })
                });
                const result = await response.json();
                if (result.success) {
                    alert('Results published successfully!');
                    publishBtn.style.backgroundColor = '#6c757d';
                    publishBtn.textContent = 'Results Published';
                    publishBtn.style.cursor = 'default';
                } else {
                    throw new Error(result.error || 'Failed to publish results.');
                }
            } catch (error) {
                alert(`Error: ${error.message}`);
                publishBtn.disabled = false;
                publishBtn.textContent = 'Publish Results';
            }
        }
    });
});
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>
