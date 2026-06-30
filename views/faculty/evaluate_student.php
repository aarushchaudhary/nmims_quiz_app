<?php
  $pageTitle = 'Evaluate Student';
  
  if (isset($_GET['quiz_id'])) {
      $customBackButtonText = '&#8592; Back to Student List';
      $customBackButtonUrl = 'evaluate_descriptive.php?quiz_id=' . htmlspecialchars($_GET['quiz_id']);
  }

  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2 || !isset($_GET['attempt_id'])) {
      redirect('login.php');
      exit();
  }
  
  $attempt_id = filter_var($_GET['attempt_id'], FILTER_VALIDATE_INT);
  $quiz_id = filter_var($_GET['quiz_id'] ?? 0, FILTER_VALIDATE_INT);
  $faculty_id = $_SESSION['user_id'];

  // Fetch attempt, student, and quiz info
  $info_sql = "
      SELECT s.name as student_name, s.sap_id, qz.title as quiz_title
      FROM student_attempts att
      JOIN students s ON att.student_id = s.user_id
      JOIN quizzes qz ON att.quiz_id = qz.id
      WHERE att.id = :attempt_id AND qz.faculty_id = :faculty_id
  ";
  $info_stmt = $pdo->prepare($info_sql);
  $info_stmt->execute([':attempt_id' => $attempt_id, ':faculty_id' => $faculty_id]);
  $info = $info_stmt->fetch();

  if (!$info) {
      echo "<div class='manage-container'><p>Attempt not found or unauthorized.</p></div>";
      require_once '../../assets/templates/footer.php';
      exit();
  }

  // Fetch all descriptive answers for this attempt
  $sql = "SELECT 
            sa.id as answer_id,
            sa.answer_text,
            sa.score_awarded,
            q.question_text,
            q.points as max_points
          FROM student_answers sa
          JOIN questions q ON sa.question_id = q.id
          WHERE sa.attempt_id = :attempt_id 
            AND q.question_type_id = 3
          ORDER BY q.id";
  
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':attempt_id' => $attempt_id]);
  $answers = $stmt->fetchAll();
?>

<div class="manage-container">
    <div style="text-align: center; margin-bottom: 30px;">
        <h2>Evaluating: <?php echo htmlspecialchars($info['student_name']); ?></h2>
        <p style="color: #666; margin-top: 5px;"><strong>SAP ID:</strong> <?php echo htmlspecialchars($info['sap_id']); ?></p>
        <p style="color: #888; font-size: 0.9em; margin-top: 5px;">Quiz: <?php echo htmlspecialchars($info['quiz_title']); ?></p>
    </div>

    <?php if (empty($answers)): ?>
        <p style="text-align:center; padding: 30px; background: #f8f9fa; border-radius: 8px;">This student did not receive any descriptive questions.</p>
    <?php else: ?>
        <?php foreach ($answers as $index => $answer): ?>
            <div class="evaluation-card" style="margin-bottom: 25px; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background-color: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                <h4 style="margin-top: 0; color: #444; border-bottom: 1px solid #eee; padding-bottom: 10px;">Question <?php echo $index + 1; ?></h4>
                <p class="question-text-eval" style="font-size: 1.1em; color: #333; margin: 15px 0;"><strong><?php echo htmlspecialchars($answer['question_text']); ?></strong></p>
                <div class="answer-text" style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #e60000; margin-bottom: 20px; font-family: 'Courier New', Courier, monospace; white-space: pre-wrap;"><?php echo htmlspecialchars($answer['answer_text'] ?? 'No answer provided.'); ?></div>

                <form class="evaluation-form" data-answer-id="<?php echo $answer['answer_id']; ?>" style="display: flex; align-items: center; gap: 15px; background: #fafafa; padding: 15px; border-radius: 6px; border: 1px solid #eaeaea;">
                    <div class="form-group" style="margin: 0; display: flex; align-items: center; gap: 10px;">
                        <label for="score_<?php echo $answer['answer_id']; ?>" style="font-weight: bold; margin: 0;">Score:</label>
                        <?php 
                            $default_score = $answer['score_awarded'] ?? '';
                            if ($default_score === '' && trim($answer['answer_text'] ?? '') === '') {
                                $default_score = '0';
                            }
                        ?>
                        <input type="number" step="0.5" min="0" max="<?php echo $answer['max_points']; ?>" 
                               id="score_<?php echo $answer['answer_id']; ?>" name="score_awarded" 
                               class="input-field" value="<?php echo htmlspecialchars($default_score); ?>" required style="margin: 0; width: 80px; padding: 8px;">
                        <span style="color: #666; font-weight: bold;">/ <?php echo htmlspecialchars($answer['max_points']); ?></span>
                    </div>
                    <button type="submit" class="button-red" style="padding: 10px 20px; font-size: 0.9em; width: auto; margin: 0;">Save Score</button>
                    <span class="feedback-message" style="font-weight: bold;"></span>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
const BASE_URL = '<?= get_base_url() ?>';
document.querySelectorAll('.evaluation-form').forEach(form => {
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const answerId = this.dataset.answerId;
        const scoreInput = this.querySelector('input[name="score_awarded"]');
        const feedbackSpan = this.querySelector('.feedback-message');
        const submitBtn = this.querySelector('button[type="submit"]');
        
        const payload = {
            answer_id: answerId,
            score: scoreInput.value
        };

        const originalBtnText = submitBtn.textContent;
        submitBtn.textContent = 'Saving...';
        submitBtn.disabled = true;

        try {
            const response = await fetch(BASE_URL + 'api/faculty/save_evaluation.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await response.json();

            if (result.success) {
                feedbackSpan.textContent = 'Saved Successfully!';
                feedbackSpan.style.color = '#28a745';
            } else {
                throw new Error(result.error || 'Failed to save score.');
            }
        } catch (error) {
            feedbackSpan.textContent = `Error: ${error.message}`;
            feedbackSpan.style.color = '#dc3545';
        }
        
        submitBtn.textContent = originalBtnText;
        submitBtn.disabled = false;
        setTimeout(() => { feedbackSpan.textContent = ''; }, 3000);
    });
});
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>
