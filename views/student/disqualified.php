<?php
  $pageTitle = 'Exam Disqualified';
  require_once '../../assets/templates/header.php';

  // --- Authorization Check ---
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4) {
      redirect('login.php');
      exit();
  }

  $attempt_id = isset($_GET['attempt_id']) ? filter_var($_GET['attempt_id'], FILTER_VALIDATE_INT) : null;
?>

<div class="lobby-container">
    <h2 style="color: #dc3545;">Exam Session Locked</h2>
    <p class="lobby-instructions">
        Your exam has been locked due to proctoring violations.
        Please wait for the faculty in charge to review your case. If approved, you will be able to resume your exam.
    </p>

    <div class="spinner"></div>

    <p class="status-text" id="status-text">Status: Disqualified. Awaiting faculty review...</p>
</div>

<script>
const BASE_URL = '<?= get_base_url() ?>';
document.addEventListener('DOMContentLoaded', function() {
    const attemptId = <?php echo json_encode($attempt_id); ?>;
    if (!attemptId) {
        document.getElementById('status-text').textContent = 'Error: No valid attempt ID found. Please contact your faculty.';
        return;
    }

    async function checkResumeStatus() {
        try {
            const response = await fetch(BASE_URL + `api/student/get_attempt_status.php?id=${attemptId}`);
            const data = await response.json();

            if (data.can_resume) {
                // If faculty re-enables, redirect back to the exam
                alert('The faculty has re-enabled your exam. You can now continue.');
                window.location.href = `exam.php?id=${data.quiz_id}`;
            }
        } catch (error) {
            console.error('Error checking status:', error);
        }
    }

    setInterval(checkResumeStatus, 5000); // Check every 5 seconds
});
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>
