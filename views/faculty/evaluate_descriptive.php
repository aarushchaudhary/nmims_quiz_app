<?php
  $pageTitle = 'Evaluate Answers';

  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  // --- Authorization & Input Check ---
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2 || !isset($_GET['quiz_id'])) {
      redirect('login.php');
      exit();
  }
  $quiz_id = filter_var($_GET['quiz_id'], FILTER_VALIDATE_INT);
  $faculty_id = $_SESSION['user_id'];

  // --- Fetch Quiz Details ---
  $stmt_quiz = $pdo->prepare("SELECT title FROM quizzes WHERE id = ? AND faculty_id = ?");
  $stmt_quiz->execute([$quiz_id, $faculty_id]);
  $quiz = $stmt_quiz->fetch();
  if (!$quiz) {
      echo "<div class='manage-container'><p>Quiz not found or unauthorized.</p></div>";
      require_once '../../assets/templates/footer.php';
      exit();
  }

  // --- Fetch all students who attempted the quiz and calculate their evaluation status ---
  // A student is "Pending" if they have ANY descriptive answers with score_awarded IS NULL.
  $sql = "
    SELECT 
        s.user_id,
        s.sap_id,
        s.name as student_name,
        att.id as attempt_id,
        (
            SELECT COUNT(*) 
            FROM student_answers sa 
            JOIN questions q ON sa.question_id = q.id 
            WHERE sa.attempt_id = att.id 
              AND q.question_type_id = 3 
              AND sa.score_awarded IS NULL
        ) as pending_evaluations
    FROM student_attempts att
    JOIN students s ON att.student_id = s.user_id
    WHERE att.quiz_id = :quiz_id
    ORDER BY s.name ASC
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':quiz_id' => $quiz_id]);
  $students = $stmt->fetchAll();
?>

<div class="manage-container">
    <h2 style="text-align: center; margin-bottom: 20px;">Evaluate Answers</h2>
    <h4 style="text-align: center; color: #555; margin-bottom: 30px;"><?php echo htmlspecialchars($quiz['title']); ?></h4>

    <div class="filter-form" style="display: flex; gap: 15px; margin-bottom: 20px; align-items: center;">
        <div style="flex-grow: 1;">
            <input type="text" id="searchInput" class="input-field" style="margin-bottom: 0;" placeholder="Search by Student Name or SAP ID...">
        </div>
        <div>
            <select id="statusFilter" class="input-field" style="margin-bottom: 0; min-width: 200px;">
                <option value="all">All Status</option>
                <option value="pending">Pending Evaluation</option>
                <option value="evaluated">Evaluated</option>
            </select>
        </div>
    </div>

    <?php if (empty($students)): ?>
        <p style="text-align:center;">No students have attempted this quiz yet.</p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="data-table" id="studentsTable" style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                <thead>
                    <tr style="background-color: #f8f9fa; border-bottom: 2px solid #ddd;">
                        <th style="padding: 12px; text-align: left;">SAP ID</th>
                        <th style="padding: 12px; text-align: left;">Name</th>
                        <th style="padding: 12px; text-align: center;">Evaluation Status</th>
                        <th style="padding: 12px; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): 
                        $is_pending = ($student['pending_evaluations'] > 0);
                        $status_text = $is_pending ? 'Pending Evaluation' : 'Evaluated';
                        $status_bg = $is_pending ? '#fff3cd' : '#d4edda';
                        $status_color = $is_pending ? '#856404' : '#155724';
                        $status_border = $is_pending ? '#ffeeba' : '#c3e6cb';
                    ?>
                        <tr class="student-row" 
                            data-name="<?php echo strtolower(htmlspecialchars($student['student_name'])); ?>" 
                            data-sap="<?php echo strtolower(htmlspecialchars($student['sap_id'])); ?>" 
                            data-status="<?php echo $is_pending ? 'pending' : 'evaluated'; ?>"
                            style="border-bottom: 1px solid #eee;">
                            
                            <td style="padding: 12px;"><?php echo htmlspecialchars($student['sap_id']); ?></td>
                            <td style="padding: 12px; font-weight: bold;"><?php echo htmlspecialchars($student['student_name']); ?></td>
                            <td style="padding: 12px; text-align: center;">
                                <span style="background-color: <?php echo $status_bg; ?>; color: <?php echo $status_color; ?>; border: 1px solid <?php echo $status_border; ?>; padding: 4px 10px; border-radius: 12px; font-size: 0.85em; font-weight: bold;">
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                            <td style="padding: 12px; text-align: center;">
                                <a href="evaluate_student.php?attempt_id=<?php echo $student['attempt_id']; ?>&quiz_id=<?php echo $quiz_id; ?>" class="button-red" style="padding: 8px 16px; font-size: 0.9em; text-decoration: none; display: inline-block; width: auto; border-radius: 4px;">Evaluate</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p id="noResultsMsg" style="text-align:center; display:none; margin-top: 20px; color: #777;">No students match your filter criteria.</p>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const studentRows = document.querySelectorAll('.student-row');
    const noResultsMsg = document.getElementById('noResultsMsg');

    function filterStudents() {
        if (!searchInput || !statusFilter) return;
        
        const searchTerm = searchInput.value.toLowerCase();
        const statusValue = statusFilter.value;
        let visibleCount = 0;

        studentRows.forEach(row => {
            const name = row.getAttribute('data-name');
            const sap = row.getAttribute('data-sap');
            const status = row.getAttribute('data-status');

            const matchesSearch = name.includes(searchTerm) || sap.includes(searchTerm);
            const matchesStatus = (statusValue === 'all') || (status === statusValue);

            if (matchesSearch && matchesStatus) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (visibleCount === 0 && studentRows.length > 0) {
            noResultsMsg.style.display = 'block';
        } else {
            noResultsMsg.style.display = 'none';
        }
    }

    if (searchInput) searchInput.addEventListener('input', filterStudents);
    if (statusFilter) statusFilter.addEventListener('change', filterStudents);
});
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>
