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

  $sql_quizzes = "SELECT q.id, q.title, q.start_time FROM quizzes q JOIN courses c ON q.course_id = c.id WHERE $where_sql ORDER BY q.created_at DESC";
  $quizzes_stmt = $pdo->prepare($sql_quizzes);
  $quizzes_stmt->execute($params);
  $quizzes = $quizzes_stmt->fetchAll();
  
  $preselected_quiz_id = isset($_GET['quiz_id']) ? filter_var($_GET['quiz_id'], FILTER_VALIDATE_INT) : null;
  $preselected_quiz_title = '';
  $start_time_str = '';
  $end_time_str = '';
  if ($preselected_quiz_id) {
      $stmt_title = $pdo->prepare("SELECT title, start_time, end_time FROM quizzes WHERE id = ?");
      $stmt_title->execute([$preselected_quiz_id]);
      $quiz_data = $stmt_title->fetch();
      if ($quiz_data) {
          $preselected_quiz_title = $quiz_data['title'];
          $start_time_str = date('g:i A', strtotime($quiz_data['start_time']));
          $end_time_str = date('g:i A', strtotime($quiz_data['end_time']));
      }
  }
?>

<div class="manage-container" style="position: relative;">
    <?php if ($preselected_quiz_id && $preselected_quiz_title): 
        $parts = explode(' - ', $preselected_quiz_title);
        if (count($parts) >= 4) {
            $quizName = trim($parts[2]);
            $courseInfo = trim($parts[0]) . " | " . trim($parts[1]);
            $dateInfo = trim($parts[3]);
            $sectionInfo = isset($parts[4]) ? trim($parts[4]) : '';
        } else {
            $quizName = $preselected_quiz_title;
            $courseInfo = '';
            $dateInfo = '';
            $sectionInfo = '';
        }
    ?>
        <?php if ($dateInfo): ?>
        <div style="position: absolute; top: 25px; left: 30px; text-align: left; font-size: 0.9em; color: #555; background: #f8f9fa; padding: 10px 15px; border-radius: 8px; border: 1px solid #e9ecef;">
            <div style="font-weight: bold; margin-bottom: 5px; color: #333; font-size: 1.1em;"><?php echo htmlspecialchars($dateInfo); ?></div>
            <div style="margin-bottom: 3px;"><strong>Start:</strong> <?php echo $start_time_str; ?></div>
            <div><strong>End:</strong> <?php echo $end_time_str; ?></div>
        </div>
        <?php endif; ?>

        <div style="text-align: center; margin-bottom: 25px; padding-top: 15px;">
            <h2 style="margin-bottom: 8px; color: #333; font-size: 1.8em;"><?php echo htmlspecialchars($quizName); ?></h2>
            <?php if ($courseInfo): ?>
                <h4 style="margin-top: 0; margin-bottom: 6px; color: #555; font-weight: 600;"><?php echo htmlspecialchars($courseInfo); ?></h4>
            <?php endif; ?>
            <?php if ($sectionInfo): ?>
                <p style="margin-top: 0; color: #777; font-size: 0.95em;"><?php echo htmlspecialchars($sectionInfo); ?></p>
            <?php endif; ?>
        </div>
        
        <select id="quiz_id_selector" style="display:none;">
            <option value="<?php echo $preselected_quiz_id; ?>" selected></option>
        </select>
        
        <div style="display: flex; align-items: center; justify-content: center; gap: 12px; flex-wrap: wrap; margin-bottom: 30px;">
            <a href="#" id="analysis-btn" class="button-red" style="display:none; width:auto; padding: 10px 20px; background-color: #6f42c1; margin-bottom: 0;">Item Analysis</a>
            <a href="#" id="export-btn" class="button-red" style="display:none; width:auto; padding: 10px 20px; background-color: #17a2b8; margin-bottom: 0;">Export to Excel</a>
        </div>
    <?php else: ?>
        <h2 style="text-align: center; margin-bottom: 25px;">View Quiz Results</h2>

        <!-- Section 1: Filters -->
        <div class="section-box" style="margin-bottom: 25px; padding: 20px 25px;">
            <h4 style="margin: 0 0 15px 0; color: #555; font-size: 0.95em; text-transform: uppercase; letter-spacing: 0.5px;">Search & Filter Quizzes</h4>
            <form method="GET" action="reports.php">
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
                            <option value="<?php echo $school['id']; ?>" <?php if($school['id'] == $school_filter) echo 'selected'; ?>><?php echo htmlspecialchars($school['name']); ?></option>
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
                    <a href="reports.php" class="button-red" style="width: auto; padding: 10px 24px; background-color: #6c757d; margin-bottom: 0; text-decoration: none;">Clear</a>
                </div>
            </form>
        </div>

        <!-- Hidden select to keep JS compatibility -->
        <select id="quiz_id_selector" style="display:none;">
            <option value="">-- Choose a Quiz --</option>
            <?php foreach ($quizzes as $quiz): ?>
                <option value="<?php echo $quiz['id']; ?>" <?php if ($quiz['id'] == $preselected_quiz_id) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($quiz['title']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Section 2: Quiz List + Action Buttons -->
        <div class="section-box" style="margin-bottom: 25px; padding: 20px 25px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                <h4 style="margin: 0; color: #555; font-size: 0.95em; text-transform: uppercase; letter-spacing: 0.5px;">Select a Quiz</h4>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="#" id="analysis-btn" class="button-red" style="display:none; width:auto; padding: 8px 16px; background-color: #6f42c1; margin-bottom: 0; font-size: 0.85em;">Item Analysis</a>
                    <a href="#" id="evaluate-btn" class="button-red" style="display:none; width:auto; padding: 8px 16px; background-color: #ffc107; color: #333; margin-bottom: 0; font-size: 0.85em;">Evaluate Answers</a>
                    <a href="#" id="export-btn" class="button-red" style="display:none; width:auto; padding: 8px 16px; background-color: #17a2b8; margin-bottom: 0; font-size: 0.85em;">Export to Excel</a>
                    <button id="publish-results-btn" class="button-red" style="display:none; width:auto; padding: 8px 16px; background-color: #28a745; color: white; border: none; cursor: pointer; margin-bottom: 0; font-size: 0.85em;">Publish Results</button>
                </div>
            </div>
            <?php if (empty($quizzes)): ?>
                <p style="text-align: center; color: #999; padding: 20px 0;">No quizzes found. Try adjusting your filters.</p>
            <?php else: ?>
                <div style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 6px;">
                    <?php foreach ($quizzes as $index => $quiz): 
                        $parts = explode(' - ', $quiz['title']);
                        $display_name = count($parts) >= 3 ? trim($parts[2]) : $quiz['title'];
                        $display_meta = count($parts) >= 2 ? trim($parts[0]) . ' | ' . trim($parts[1]) : '';
                        $display_date = !empty($quiz['start_time']) ? date('M j, Y', strtotime($quiz['start_time'])) : '';
                        $is_selected = ($quiz['id'] == $preselected_quiz_id);
                    ?>
                        <a href="reports.php?quiz_id=<?php echo $quiz['id']; ?>" class="quiz-list-item" data-quiz-id="<?php echo $quiz['id']; ?>" 
                             style="display: flex; align-items: center; padding: 12px 15px; cursor: pointer; transition: background 0.15s; border-bottom: 1px solid #eee; text-decoration: none; color: inherit; <?php echo $is_selected ? 'background-color: #e8f0fe; border-left: 4px solid #e60000;' : 'border-left: 4px solid transparent;'; ?>">
                            <div style="width: 35px; font-weight: bold; color: #999; font-size: 0.9em; flex-shrink: 0;"><?php echo $index + 1; ?>.</div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($display_name); ?></div>
                                <?php if ($display_meta): ?>
                                    <div style="font-size: 0.85em; color: #777; margin-top: 3px;"><?php echo htmlspecialchars($display_meta); ?></div>
                                <?php endif; ?>
                            </div>
                            <?php if ($display_date): ?>
                                <div style="font-size: 0.85em; color: #888; white-space: nowrap; margin-left: 15px;"><?php echo $display_date; ?></div>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Section 3: Report Content -->
    <div id="report-content" style="display:none;">
        <div class="report-summary-grid">
            <div class="summary-card"><p class="card-title">Total Attempts</p><p class="card-value" id="summary-attempts">0</p></div>
            <div class="summary-card"><p class="card-title">Average Score</p><p class="card-value" id="summary-avg-score">0.00</p></div>
            <div class="summary-card"><p class="card-title">Disqualified</p><p class="card-value" id="summary-disqualified">0</p></div>
        </div>
        <div style="overflow-x: auto;">
            <table class="data-table results-table" style="width: 100%;">
                <thead><tr><th>Student Name</th><th>SAP ID</th><th>Score</th><th>Time Taken</th><th>Status</th></tr></thead>
                <tbody id="results-table-body"></tbody>
            </table>
        </div>
    </div>
    <p id="report-placeholder" style="display:none;"></p>
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
    if (schoolSelect) {
        schoolSelect.addEventListener('change', function() { populateCourses(this.value); });
        if (schoolSelect.value) { populateCourses(schoolSelect.value, preselectedCourseId); }
    }

    // --- Report Loading Logic ---
    const quizSelector = document.getElementById('quiz_id_selector');
    const reportContent = document.getElementById('report-content');
    const placeholder = document.getElementById('report-placeholder');
    const exportBtn = document.getElementById('export-btn');
    const analysisBtn = document.getElementById('analysis-btn');
    
    async function loadReport(quizId) {
        if (!quizId) {
            reportContent.style.display = 'none';
            [exportBtn, analysisBtn].forEach(btn => btn.style.display = 'none');
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
            analysisBtn.href = `item_analysis.php?quiz_id=${quizId}`;
            [exportBtn, analysisBtn].forEach(btn => btn.style.display = 'inline-block');
        } catch (error) {
            console.error("Error loading report:", error);
            placeholder.textContent = `Error: ${error.message}`;
        }
    }
    // Also keep select change handler for preselected quiz
    quizSelector.addEventListener('change', function() { 
        if (this.value) {
            window.location.href = 'reports.php?quiz_id=' + this.value;
        }
    });
    if (quizSelector.value) { loadReport(quizSelector.value); }


});
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>
