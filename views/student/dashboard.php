<?php
  $pageTitle = 'Student Dashboard'; 
  
  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  // --- Authorization Check ---
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4) {
      redirect('login.php');
      exit();
  }
  
  $student_user_id = $_SESSION['user_id'];
  $studentName = isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Student';

  // --- Fetch the Student's details ---
  $stmt_student = $pdo->prepare("SELECT course_id, graduation_year, sap_id FROM students WHERE user_id = ?");
  $stmt_student->execute([$student_user_id]);
  $student_info = $stmt_student->fetch();
  
  $student_course_id = $student_info['course_id'] ?? null;
  $student_grad_year = $student_info['graduation_year'] ?? null;
  $student_sap_id = $student_info['sap_id'] ?? null;
  $quizzes = [];

  // --- Fetch Available Quizzes for the Student ---
  // Base SQL query
  $sql = "SELECT DISTINCT q.id, q.title, q.start_time, es.name as status_name
          FROM quizzes q
          JOIN exam_statuses es ON q.status_id = es.id
          LEFT JOIN quiz_manual_students qms ON q.id = qms.quiz_id AND qms.student_id = :student_user_id_1
          LEFT JOIN quiz_classes qc ON q.id = qc.quiz_id
          LEFT JOIN classes c ON qc.class_id = c.id
          LEFT JOIN quiz_batches qb ON q.id = qb.quiz_id
          LEFT JOIN batches b ON qb.batch_id = b.id
          LEFT JOIN classes bc ON b.class_id = bc.id
          LEFT JOIN quiz_electives qe ON q.id = qe.quiz_id
          LEFT JOIN elective_students es_stu ON qe.elective_id = es_stu.elective_id AND es_stu.student_id = :student_user_id_2
          LEFT JOIN quiz_re_exam_groups qrg ON q.id = qrg.quiz_id
          LEFT JOIN re_exam_group_students regs ON qrg.group_id = regs.group_id AND regs.student_id = :student_user_id_3
          JOIN students s ON s.user_id = :student_user_id_4
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
          AND (
            (NOW() BETWEEN q.start_time AND q.end_time AND es.name != 'Completed')
            OR
            (es.name IN ('Lobby Open', 'In Progress'))
          )
          ORDER BY q.start_time ASC";
  
  $params = [
      ':student_user_id_1' => $student_user_id,
      ':student_user_id_2' => $student_user_id,
      ':student_user_id_3' => $student_user_id,
      ':student_user_id_4' => $student_user_id
  ];
  
  $stmt_quizzes = $pdo->prepare($sql);
  
  // Execute with the combined parameters
  $stmt_quizzes->execute($params);
  
  $quizzes = $stmt_quizzes->fetchAll();
  
  // --- NEW: Fetch Published Results ---
      $sql_published = "SELECT q.id as quiz_id, q.title, sa.id as attempt_id, sa.total_score, sa.submitted_at 
                        FROM student_attempts sa
                        JOIN quizzes q ON sa.quiz_id = q.id
                        WHERE sa.student_id = :student_user_id 
                        AND sa.submitted_at IS NOT NULL 
                        AND q.show_results_immediately = 1
                        ORDER BY sa.submitted_at DESC";
      $stmt_published = $pdo->prepare($sql_published);
      $stmt_published->execute([':student_user_id' => $student_user_id]);
      $published_results = $stmt_published->fetchAll();
?>

<div class="manage-container">
    <h2 style="margin-bottom: 10px;">Welcome, <?php echo $studentName; ?>!</h2>
    <p style="text-align:center; color: #555; margin-top:0;">The quizzes listed below are currently available for you to join.</p>

    <table class="data-table">
        <thead>
            <tr>
                <th>Quiz Title</th>
                <th>Starts At</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($quizzes)): ?>
                <tr>
                    <td colspan="4" style="text-align:center; padding: 20px;">There are no active quizzes available for your course and batch at this moment.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($quizzes as $quiz): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($quiz['title']); ?></td>
                        <td><?php echo date('M j, Y, g:i A', strtotime($quiz['start_time'])); ?></td>
                        <td>
                            <?php $status_class = strtolower(str_replace(' ', '_', $quiz['status_name'])); ?>
                            <span class="status-badge status-<?php echo htmlspecialchars($status_class); ?>">
                                <?php echo htmlspecialchars($quiz['status_name']); ?>
                            </span>
                        </td>
                        <td class="action-buttons">
                            <?php
                                if ($quiz['status_name'] != 'Not Started') {
                                    echo '<a href="lobby.php?id=' . $quiz['id'] . '" class="btn-manage" style="background-color: #28a745;">Join Exam</a>';
                                } else {
                                    echo '<span style="color: #6c757d;">Waiting for faculty...</span>';
                                }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <h2 style="margin-top: 40px; margin-bottom: 10px;">Your Published Results</h2>
    <p style="text-align:center; color: #555; margin-top:0;">These are your past exams where the faculty has released the results.</p>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>Quiz Title</th>
                <th>Date Taken</th>
                <th>Score</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($published_results)): ?>
                <tr>
                    <td colspan="4" style="text-align:center; padding: 20px;">No published results available yet.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($published_results as $result): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($result['title']); ?></td>
                        <td><?php echo date('M j, Y, g:i A', strtotime($result['submitted_at'])); ?></td>
                        <td style="font-weight: bold; color: #28a745;"><?php echo htmlspecialchars(number_format($result['total_score'], 2)); ?></td>
                        <td class="action-buttons">
                            <a href="results.php?attempt_id=<?php echo $result['attempt_id']; ?>" class="btn-manage" style="background-color: #17a2b8;">View Results</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
  require_once '../../assets/templates/footer.php';
?>