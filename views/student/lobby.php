<?php
  $pageTitle = 'Exam Lobby';
  
  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  // --- Authorization & Input Check ---
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4) {
      redirect('login.php');
      exit();
  }
  if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
      header('Location: dashboard.php?error=invalid_quiz');
      exit();
  }

  $quiz_id = $_GET['id'];
  $student_user_id = $_SESSION['user_id'];

  // --- Fetch Quiz Details ---
  $stmt_quiz = $pdo->prepare("SELECT title FROM quizzes WHERE id = ?");
  $stmt_quiz->execute([$quiz_id]);
  $quiz = $stmt_quiz->fetch();

  if (!$quiz) {
      header('Location: dashboard.php?error=quiz_not_found');
      exit();
  }

  // Register student in the lobby
  $lobby_sql = "INSERT IGNORE INTO quiz_lobby (quiz_id, student_id) VALUES (?, ?)";
  $stmt_lobby = $pdo->prepare($lobby_sql);
  $stmt_lobby->execute([$quiz_id, $student_user_id]);
?>

<div class="lobby-container">
    <h2>Exam Lobby</h2>
    <p class="quiz-title"><?php echo htmlspecialchars($quiz['title']); ?></p>

    <!-- NEW: Instructions Box -->
    <div class="lobby-instructions-box">
        <h3>Please Read Before Starting</h3>
        <ul>
            <li>Once the exam starts, a timer will begin. The exam will submit automatically when time runs out.</li>
            <li>The exam page will enter **fullscreen mode**. Exiting fullscreen is a violation.</li>
            <li>**Do not switch tabs or minimize the browser.** Leaving the exam page more than once will result in disqualification.</li>
            <li>Ensure you have a stable network connection.</li>
        </ul>
    </div>

    <div class="spinner"></div>

    <p class="status-text" id="status-text">Waiting for faculty to start the exam...</p>
</div>

<script>
const BASE_URL = '<?= get_base_url() ?>';
document.addEventListener('DOMContentLoaded', function() {
    const quizId = <?php echo json_encode($quiz_id); ?>;
    const statusText = document.getElementById('status-text');

    async function checkQuizStatus() {
        try {
            const response = await fetch(BASE_URL + `api/shared/get_quiz_status.php?id=${quizId}`);
            if (!response.ok) throw new Error('Network response was not ok');
            const data = await response.json();

            if (data.status === 'In Progress') {
                window.location.href = `exam.php?id=${quizId}`;
            } else if (data.status) {
                statusText.textContent = `Status: ${data.status}. Waiting...`;
            }
        } catch (error) {
            console.error('Error checking quiz status:', error);
            statusText.textContent = 'Connection error. Retrying...';
        }
    }

    const statusInterval = setInterval(checkQuizStatus, 5000);
    checkQuizStatus();
});
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>
