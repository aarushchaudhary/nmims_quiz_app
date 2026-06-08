<?php
  $pageTitle = 'Evaluate Descriptive Answers';

  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  // --- Authorization & Input Check ---
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2 || !isset($_GET['quiz_id'])) {
      redirect('login.php');
      exit();
  }
  $quiz_id = filter_var($_GET['quiz_id'], FILTER_VALIDATE_INT);
  $faculty_id = $_SESSION['user_id'];

  // --- Fetch all descriptive answers for this quiz ---
  $sql = "SELECT 
            sa.id as answer_id,
            sa.answer_text,
            sa.score_awarded,
            q.question_text,
            q.points as max_points,
            s.name as student_name
          FROM student_answers sa
          JOIN questions q ON sa.question_id = q.id
          JOIN student_attempts att ON sa.attempt_id = att.id
          JOIN students s ON att.student_id = s.user_id
          JOIN quizzes quiz ON q.quiz_id = quiz.id
          WHERE q.quiz_id = :quiz_id 
            AND q.question_type_id = 3 -- 3 is for 'Descriptive'
            AND quiz.faculty_id = :faculty_id
          ORDER BY s.name, q.id";
  
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':quiz_id' => $quiz_id, ':faculty_id' => $faculty_id]);
  $answers = $stmt->fetchAll();
?>

<div class="manage-container">

    <h2>Evaluate Answers</h2>

    <?php if (empty($answers)): ?>
        <p style="text-align:center;">There are no descriptive answers to evaluate for this quiz.</p>
    <?php else: ?>
        <?php foreach ($answers as $answer): ?>
            <div class="evaluation-card">
                <p class="student-info"><?php echo htmlspecialchars($answer['student_name']); ?></p>
                <p class="question-text-eval"><strong>Question:</strong> <?php echo htmlspecialchars($answer['question_text']); ?></p>
                <div class="answer-text">
                    <?php echo nl2br(htmlspecialchars($answer['answer_text'] ?? '')); ?>
                </div>

                <form class="evaluation-form" data-answer-id="<?php echo $answer['answer_id']; ?>">
                    <div class="form-group">
                        <label for="score_<?php echo $answer['answer_id']; ?>">Score:</label>
                        <input type="number" step="0.5" min="0" max="<?php echo $answer['max_points']; ?>" 
                               id="score_<?php echo $answer['answer_id']; ?>" name="score_awarded" 
                               class="input-field" value="<?php echo htmlspecialchars($answer['score_awarded'] ?? ''); ?>" required>
                        <span> / <?php echo htmlspecialchars($answer['max_points']); ?></span>
                    </div>
                    <button type="submit" class="button-red">Save Score</button>
                    <span class="feedback-message"></span>
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
        
        const payload = {
            answer_id: answerId,
            score: scoreInput.value
        };

        try {
            const response = await fetch(BASE_URL + 'api/faculty/save_evaluation.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await response.json();

            if (result.success) {
                feedbackSpan.textContent = 'Saved!';
                feedbackSpan.className = 'feedback-message feedback-success';
            } else {
                throw new Error(result.error || 'Failed to save score.');
            }
        } catch (error) {
            feedbackSpan.textContent = `Error: ${error.message}`;
            feedbackSpan.className = 'feedback-message feedback-error';
        }
        
        setTimeout(() => { feedbackSpan.textContent = ''; }, 3000);
    });
});
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>
