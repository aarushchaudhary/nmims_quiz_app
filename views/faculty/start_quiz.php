<?php
  $pageTitle = 'Live Quiz Session';
  
  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  // --- Authorization & Input Check ---
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
      redirect('login.php');
      exit();
  }
  if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
      header('Location: manage_quizzes.php');
      exit();
  }

  $quiz_id = $_GET['id'];
  $faculty_id = $_SESSION['user_id'];

  // --- Fetch Quiz Details ---
  $sql = "SELECT q.title, es.name as status_name, 
                 q.config_easy_count, q.config_medium_count, q.config_hard_count
          FROM quizzes q 
          JOIN exam_statuses es ON q.status_id = es.id
          WHERE q.id = :quiz_id AND q.faculty_id = :faculty_id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':quiz_id' => $quiz_id, ':faculty_id' => $faculty_id]);
  $quiz = $stmt->fetch();

  if (!$quiz) {
      header('Location: manage_quizzes.php');
      exit();
  }

  // --- Verify Question Requirements ---
  $q_stmt = $pdo->prepare("SELECT difficulty_id, COUNT(*) as count FROM questions WHERE quiz_id = :quiz_id GROUP BY difficulty_id");
  $q_stmt->execute([':quiz_id' => $quiz_id]);
  $actual_counts = [];
  while ($row = $q_stmt->fetch()) {
      $actual_counts[$row['difficulty_id']] = $row['count'];
  }
  
  $actual_easy = $actual_counts[1] ?? 0;
  $actual_medium = $actual_counts[2] ?? 0;
  $actual_hard = $actual_counts[3] ?? 0;

  $meets_requirements = ($actual_easy >= $quiz['config_easy_count'] &&
                         $actual_medium >= $quiz['config_medium_count'] &&
                         $actual_hard >= $quiz['config_hard_count']);

  $missing_easy = max(0, $quiz['config_easy_count'] - $actual_easy);
  $missing_medium = max(0, $quiz['config_medium_count'] - $actual_medium);
  $missing_hard = max(0, $quiz['config_hard_count'] - $actual_hard);

  $missing_parts = [];
  if ($missing_easy > 0) $missing_parts[] = "$missing_easy Easy";
  if ($missing_medium > 0) $missing_parts[] = "$missing_medium Medium";
  if ($missing_hard > 0) $missing_parts[] = "$missing_hard Hard";
  $missing_text = implode(', ', $missing_parts);

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
      $course = 'N/A';
      $sections = 'N/A';
  }
?>

<div class="manage-container">

    <div class="quiz-header-card" style="background: #fff; padding: 25px; border-radius: 10px; border: 1px solid #e0e0e0; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); text-align: center;">
        <h2 style="margin-top: 0; margin-bottom: 10px; color: #333; font-size: 1.8em;"><?php echo htmlspecialchars($display_title); ?></h2>
        <?php if ($school !== 'N/A'): ?>
            <p style="margin: 0; color: #666; font-size: 1.05em;">
                <strong>School:</strong> <?php echo htmlspecialchars($school); ?> &nbsp;|&nbsp;
                <strong>Course:</strong> <?php echo htmlspecialchars($course); ?> &nbsp;|&nbsp;
                <strong>Sections:</strong> <?php echo htmlspecialchars($sections); ?>
            </p>
        <?php endif; ?>
    </div>
    
    <div id="message-area">
    <?php
    if (isset($_GET['success'])) { echo '<div class="message-box success-message">' . htmlspecialchars($_GET['success']) . '</div>'; }
    if (isset($_GET['error'])) { echo '<div class="message-box error-message">' . htmlspecialchars($_GET['error']) . '</div>'; }
    ?>
    </div>

    <div class="section-box control-panel" style="background: #fdfdfd; border: 1px solid #eaeaea; border-radius: 10px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 30px; text-align: center;">
        <h3 style="margin-top: 0; color: #444; font-size: 1.4em;">Live Exam Control</h3>
        <div style="margin-bottom: 25px;">
            <?php 
                $status_color = '#6c757d';
                if ($quiz['status_name'] == 'Lobby Open') $status_color = '#17a2b8';
                if ($quiz['status_name'] == 'In Progress') $status_color = '#28a745';
                if ($quiz['status_name'] == 'Completed') $status_color = '#20c997';
            ?>
            <span id="current-status-text" style="color: <?php echo $status_color; ?>; font-size: 1.2em; background: rgba(0,0,0,0.05); padding: 5px 12px; border-radius: 20px; font-weight: bold;"><?php echo htmlspecialchars($quiz['status_name']); ?></span>
        </div>
        
        <div class="button-group" id="control-buttons" style="display: flex; justify-content: center; flex-direction: column; align-items: center;">
            <?php if (!$meets_requirements && $quiz['status_name'] == 'Not Started'): ?>
                <p style="color: #555; font-size: 1.1em; margin: 0 0 15px 0;">
                    Please add <strong><?php echo $missing_text; ?></strong> more questions to start the exam.
                </p>
                <a href="view_quiz.php?id=<?php echo $quiz_id; ?>" class="button-red" style="text-decoration: none; padding: 10px 25px; display: inline-block;">Add Questions</a>
            <?php else: ?>
                <?php if ($quiz['status_name'] == 'Not Started'): ?>
                    <button data-new-status-id="2" class="btn-open-lobby" style="padding: 12px 30px; font-size: 1.1em;">Open Lobby</button>
                <?php elseif ($quiz['status_name'] == 'Lobby Open'): ?>
                    <button data-new-status-id="3" class="btn-start-exam" style="padding: 12px 30px; font-size: 1.1em; background-color: #28a745;">Start Exam Now</button>
                <?php elseif ($quiz['status_name'] == 'In Progress'): ?>
                    <button data-new-status-id="4" class="btn-end-exam" style="padding: 12px 30px; font-size: 1.1em;">End Exam Now</button>
                <?php else: ?>
                    <p style="font-weight: bold; font-size: 18px; color: #444; background: #f0f0f0; padding: 15px 30px; border-radius: 8px; display: inline-block; margin: 0;">This exam is completed.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="section-box">
        <h3>Real-Time Monitoring (<span id="student-count">0</span>)</h3>
        <table class="data-table">
            <thead><tr><th>Student Name</th><th>SAP ID</th><th>Status</th><th>Progress</th><th>Action</th></tr></thead>
            <tbody id="student-list-body">
                <tr><td colspan="5" style="text-align:center;">Loading student data...</td></tr>
            </tbody>
        </table>
        <div class="pagination-controls" id="pagination-controls"></div>
    </div>
</div>

<script>
const BASE_URL = '<?= get_base_url() ?>';
document.addEventListener('DOMContentLoaded', function() {
    const quizId = <?php echo json_encode($quiz_id); ?>;
    const studentListBody = document.getElementById('student-list-body');
    const studentCountSpan = document.getElementById('student-count');
    const paginationControlsDiv = document.getElementById('pagination-controls');
    const controlButtonsDiv = document.getElementById('control-buttons');
    const currentStatusText = document.getElementById('current-status-text');
    const messageArea = document.getElementById('message-area');
    let currentPage = 1;
    let currentLimit = 10; // New: Added currentLimit

    async function fetchMonitoringData(page = 1, limit = 10) { // Modified: Added limit parameter
        currentPage = page;
        currentLimit = limit; // New: Update currentLimit
        try {
            // Modified: Added limit to the fetch URL
            const response = await fetch(BASE_URL + `api/faculty/get_live_monitoring_data.php?id=${quizId}&page=${page}&limit=${limit}`);
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Failed to fetch data');
            
            const students = data.students;
            const quizStatus = data.quiz_status;
            
            studentCountSpan.textContent = data.pagination.total_students;
            studentListBody.innerHTML = '';

            if (students.length === 0 && data.pagination.total_students > 0) {
                 studentListBody.innerHTML = `<tr><td colspan="5" style="text-align:center;">No students on this page. <a href="#" data-page="1">Go to first page</a>.</td></tr>`;
            } else if (students.length === 0) {
                studentListBody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No students are assigned to this quiz.</td></tr>';
            } else {
                students.forEach(student => {
                    const statusClass = student.status.toLowerCase().replace(/ /g, '_');
                    let actionHtml = 'N/A';
                    
                    if (student.is_disqualified && quizStatus === 'In Progress') {
                        actionHtml = `<button class="button-red btn-reenable" data-attempt-id="${student.attempt_id}">Re-enable</button>`;
                    }

                    const row = `<tr>
                        <td>${escapeHTML(student.name)}</td>
                        <td>${escapeHTML(student.sap_id)}</td>
                        <td><span class="status-badge status-${statusClass}">${escapeHTML(student.status)}</span></td>
                        <td>${escapeHTML(student.progress)}</td>
                        <td class="action-buttons">${actionHtml}</td>
                    </tr>`;
                    studentListBody.insertAdjacentHTML('beforeend', row);
                });
            }
            
            renderPagination(data.pagination);

        } catch (error) {
            console.error('Error fetching monitoring data:', error);
            studentListBody.innerHTML = `<tr><td colspan="5" style="text-align:center; color:red;">Error: ${error.message}</td></tr>`;
        }
    }

    // **MODIFIED:** Function to render the full pagination controls with dropdown
    function renderPagination(pagination) {
        paginationControlsDiv.innerHTML = '';
        if (pagination.total_students === 0) return;

        let linksHtml = '';
        if (pagination.total_pages > 1) {
            for (let i = 1; i <= pagination.total_pages; i++) {
                linksHtml += `<a href="#" data-page="${i}" class="${i === pagination.current_page ? 'current-page' : ''}">${i}</a>`;
            }
        }

        const limitOptions = [10, 25, 50, 100];
        let limitHtml = `<select id="limit-selector" class="input-field" style="width:auto; padding: 5px;">`;
        limitOptions.forEach(opt => {
            limitHtml += `<option value="${opt}" ${opt == pagination.limit ? 'selected' : ''}>${opt}</option>`;
        });
        limitHtml += `</select>`;

        paginationControlsDiv.innerHTML = `
            <div class="items-per-page-form">
                <label for="limit-selector">Items per page:</label>
                ${limitHtml}
            </div>
            <div class="page-links">${linksHtml}</div>
        `;
    }

    // **MODIFIED:** Event listener now handles both page links and the dropdown
    paginationControlsDiv.addEventListener('change', function(e) {
        if (e.target.id === 'limit-selector') {
            fetchMonitoringData(1, parseInt(e.target.value)); // Go to page 1 with new limit
        }
    });
    paginationControlsDiv.addEventListener('click', function(e) {
        if (e.target.tagName === 'A' && e.target.dataset.page) {
            e.preventDefault();
            fetchMonitoringData(parseInt(e.target.dataset.page), currentLimit);
        }
    });

    function escapeHTML(str) {
        if (str === null || str === undefined) return '';
        return str.toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]);
    }

    studentListBody.addEventListener('click', async function(e) {
        const button = e.target.closest('button.btn-reenable');
        if (!button) return;
        
        if (confirm('Are you sure you want to re-enable this student?')) {
            const attemptId = button.dataset.attemptId;
            button.disabled = true;
            button.textContent = '...';
            try {
                const response = await fetch(BASE_URL+'api/faculty/reenable_student.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ attempt_id: attemptId })
                });
                const result = await response.json();
                if (!result.success) throw new Error(result.error);
                fetchMonitoringData(currentPage, currentLimit); // Modified: pass currentLimit
            } catch (error) {
                alert(`Action failed: ${error.message}`);
                fetchMonitoringData(currentPage, currentLimit); // Modified: pass currentLimit
            }
        }
    });
    
    controlButtonsDiv.addEventListener('click', async function(e) {
        if (e.target.tagName === 'BUTTON') {
            const newStatusId = e.target.dataset.newStatusId;
            const actionText = e.target.textContent;

            if (actionText === 'End Exam Now' && !confirm('Are you sure?')) return;

            e.target.disabled = true;
            e.target.textContent = 'Updating...';
            try {
                const response = await fetch(BASE_URL+'api/faculty/update_quiz_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ quiz_id: quizId, new_status_id: newStatusId })
                });
                const result = await response.json();
                if (result.success) {
                    currentStatusText.textContent = result.new_status_name;
                    updateControlButtons(result.new_status_name);
                    messageArea.innerHTML = `<div class="message-box success-message">${result.message}</div>`;
                } else {
                    throw new Error(result.error || 'API Error');
                }
            } catch (error) {
                alert('Failed to update status: ' + error.message);
                e.target.disabled = false;
                e.target.textContent = actionText;
            }
        }
    });

    function updateControlButtons(statusName) {
        let buttonsHtml = '';
        if (statusName === 'Not Started') {
            buttonsHtml = `<button data-new-status-id="2" class="btn-open-lobby">Open Lobby</button>`;
        } else if (statusName === 'Lobby Open') {
            buttonsHtml = `<button data-new-status-id="3" class="btn-start-exam">Start Exam Now</button>`;
        } else if (statusName === 'In Progress') {
            buttonsHtml = `<button data-new-status-id="4" class="btn-end-exam">End Exam Now</button>`;
        } else {
            buttonsHtml = `<p style="font-weight: bold; font-size: 18px;">This exam is completed.</p>`;
        }
        controlButtonsDiv.innerHTML = buttonsHtml;
    }

    // Modified: Pass currentLimit to setInterval and the initial fetch
    setInterval(() => fetchMonitoringData(currentPage, currentLimit), 5000);
    fetchMonitoringData(1, 10); // Initial fetch for page 1 with a limit of 10
});
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>