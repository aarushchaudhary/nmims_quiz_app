<?php
  $pageTitle = 'System Event Logs';

  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  // --- Authorization Check for Admin ---
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
      redirect('login.php');
      exit();
  }

  // --- Fetch data for filter dropdowns ---
  $schools = $pdo->query("SELECT id, name FROM schools ORDER BY name ASC")->fetchAll();
  $users_sql = "SELECT u.id, COALESCE(s.name, f.name, p.name, a.name, h.name) as full_name, u.username 
                FROM users u 
                LEFT JOIN students s ON u.id = s.user_id 
                LEFT JOIN faculties f ON u.id = f.user_id 
                LEFT JOIN placement_officers p ON u.id = p.user_id 
                LEFT JOIN admins a ON u.id = a.user_id 
                LEFT JOIN heads h ON u.id = h.user_id 
                ORDER BY full_name ASC";
  $users = $pdo->query($users_sql)->fetchAll();
  
  // --- Build the dynamic SQL query for the logs ---
  $sql = "SELECT 
            el.timestamp, el.event_type, el.description, el.ip_address,
            q.title as quiz_title,
            COALESCE(s.name, f.name, p.name, a.name, h.name) as user_name,
            COALESCE(s.sap_id, f.sap_id, p.sap_id) as sap_id
          FROM event_logs el
          LEFT JOIN users u ON el.user_id = u.id
          LEFT JOIN students s ON u.id = s.user_id
          LEFT JOIN faculties f ON u.id = f.user_id
          LEFT JOIN placement_officers p ON u.id = p.user_id
          LEFT JOIN admins a ON u.id = a.user_id
          LEFT JOIN heads h ON u.id = h.user_id
          LEFT JOIN student_attempts sa ON el.attempt_id = sa.id
          LEFT JOIN quizzes q ON sa.quiz_id = q.id
          LEFT JOIN courses c ON q.course_id = c.id";
  
  $where_clauses = [];
  $params = [];

  // Get all filter values from the URL
  $school_filter = $_GET['school'] ?? '';
  $course_filter = $_GET['course'] ?? '';
  $user_filter = $_GET['user_filter'] ?? '';
  $search_query = $_GET['search_query'] ?? '';
  $date_filter = $_GET['date'] ?? ''; // **NEW:** Get date filter

  if (!empty($school_filter)) {
      $where_clauses[] = "c.school_id = ?";
      $params[] = $school_filter;
  }
  if (!empty($course_filter)) {
      $where_clauses[] = "q.course_id = ?";
      $params[] = $course_filter;
  }
  if (!empty($user_filter)) {
      $where_clauses[] = "el.user_id = ?";
      $params[] = $user_filter;
  }
  if (!empty($search_query)) {
      $search_term = '%' . $search_query . '%';
      $where_clauses[] = "(el.description LIKE ? OR el.event_type LIKE ? OR COALESCE(s.sap_id, f.sap_id, p.sap_id) LIKE ?)";
      array_push($params, $search_term, $search_term, $search_term);
  }
  if (!empty($date_filter)) { // **NEW:** Add date filter to query
      $where_clauses[] = "DATE(el.timestamp) = ?";
      $params[] = $date_filter;
  }

  if (!empty($where_clauses)) {
      $sql .= " WHERE " . implode(' AND ', $where_clauses);
  }
  $sql .= " ORDER BY el.timestamp DESC";
  
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $logs = $stmt->fetchAll();
?>

<div class="manage-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>System Event Logs</h2>
        <a href="dashboard.php" class="button-red" style="width:auto; background-color:#6c757d;">&larr; Back to Dashboard</a>
    </div>

    <div class="section-box filter-form">
        <form method="GET" action="system_logs.php">
            <div class="form-row">
                <div class="form-group">
                    <label for="school">Filter by School</label>
                    <select id="school" name="school" class="input-field">
                        <option value="">All Schools</option>
                        <?php foreach ($schools as $school): ?>
                            <option value="<?php echo $school['id']; ?>" <?php if($school['id'] == $school_filter) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($school['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="course">Filter by Course</label>
                    <select id="course" name="course" class="input-field">
                        <option value="">All Courses</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date">Filter by Date</label>
                    <input type="date" name="date" id="date" class="input-field" value="<?php echo htmlspecialchars($date_filter); ?>">
                </div>
            </div>
            <div class="form-row" style="margin-top: 15px;">
                 <div class="form-group">
                    <label for="user_filter">Filter by User</label>
                    <select id="user_filter" name="user_filter" class="input-field">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php if($user['id'] == $user_filter) echo 'selected'; ?>>
                                <?php echo htmlspecialchars(($user['full_name'] ?? 'N/A') . ' (' . $user['username'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <div class="form-group">
                    <label for="search_query">Search Logs / SAP ID</label>
                    <input type="text" id="search_query" name="search_query" class="input-field" placeholder="e.g., Violation, 705..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                <div class="button-group">
                    <button type="submit" class="button-red" style="width:auto;">Filter Logs</button>
                    <a href="system_logs.php" class="button-red" style="width:auto; background-color:#6c757d;">Clear Filters</a>
                </div>
            </div>
        </form>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>User</th>
                <th>SAP ID</th>
                <th>Quiz</th>
                <th>Event Type</th>
                <th>Description</th>
                <th>IP Address</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="7" style="text-align:center;">No system events found matching your criteria.</td></tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo date('M j, Y, g:i:s A', strtotime($log['timestamp'])); ?></td>
                        <td><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></td>
                        <td><?php echo htmlspecialchars($log['sap_id'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($log['quiz_title'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($log['event_type']); ?></td>
                        <td><?php echo htmlspecialchars($log['description']); ?></td>
                        <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
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
    if (schoolSelect.value) {
        populateCourses(schoolSelect.value, preselectedCourseId);
    }
});
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>
