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
  $sql = "SELECT q.title, es.name as status_name 
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
?>

<div class="manage-container">
    <a href="manage_quizzes.php" style="text-decoration: none; color: #007bff; margin-bottom: 20px; display: inline-block;">&larr; Back to My Quizzes</a>
    <h2 style="text-align: center;"><?php echo htmlspecialchars($quiz['title']); ?></h2>
    
    <div id="message-area">
    <?php
    if (isset($_GET['success'])) { echo '<div class="message-box success-message">' . htmlspecialchars($_GET['success']) . '</div>'; }
    if (isset($_GET['error'])) { echo '<div class="message-box error-message">' . htmlspecialchars($_GET['error']) . '</div>'; }
    ?>
    </div>

    <div class="section-box control-panel">
        <h3>Live Exam Control</h3>
        <p>Current Status: <strong style="font-size: 18px;" id="current-status-text"><?php echo htmlspecialchars($quiz['status_name']); ?></strong></p>
        <div class="button-group" id="control-buttons">
            <?php if ($quiz['status_name'] == 'Not Started'): ?>
                <button data-new-status-id="2" class="btn-open-lobby">Open Lobby</button>
            <?php elseif ($quiz['status_name'] == 'Lobby Open'): ?>
                <button data-new-status-id="3" class="btn-start-exam">Start Exam Now</button>
            <?php elseif ($quiz['status_name'] == 'In Progress'): ?>
                <button data-new-status-id="4" class="btn-end-exam">End Exam Now</button>
            <?php else: ?>
                <p style="font-weight: bold; font-size: 18px;">This exam is completed.</p>
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