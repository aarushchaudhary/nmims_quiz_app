<?php
  $pageTitle = 'Edit Question'; 
  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  // --- Authorization & Input Check ---
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2 || !isset($_GET['id'])) {
      redirect('login.php');
      exit();
  }
  $question_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
  if (!$question_id) {
      header('Location: manage_quizzes.php?error=not_found');
      exit();
  }

  $faculty_id = (int) $_SESSION['user_id'];

  // SECURITY FIX #7: Scope by faculty_id via JOIN on quizzes.
  $stmt_q = $pdo->prepare(
      'SELECT q.* FROM questions q
       JOIN quizzes qz ON q.quiz_id = qz.id
       WHERE q.id = ? AND qz.faculty_id = ?'
  );
  $stmt_q->execute([$question_id, $faculty_id]);
  $question = $stmt_q->fetch();

  if (!$question) {
      header('Location: manage_quizzes.php?error=not_found');
      exit();
  }

  $stmt_o = $pdo->prepare('SELECT * FROM options WHERE question_id = ? ORDER BY id ASC');
  $stmt_o->execute([$question_id]);
  $options = $stmt_o->fetchAll();

  // Fetch data for dropdowns
  $question_types = $pdo->query("SELECT id, name FROM question_types")->fetchAll();
  $difficulties = $pdo->query("SELECT id, level FROM question_difficulties")->fetchAll();
?>

<div class="form-container" style="max-width: 800px;">
    <h2>Edit Question</h2>
    <form action="<?= get_base_url() ?>api/faculty/update_question.php" method="POST" class="manual-add-form">
        <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
        <input type="hidden" name="quiz_id" value="<?php echo $question['quiz_id']; ?>">
        
        <div class="form-group">
            <label for="question_text">Question Text</label>
            <textarea id="question_text" name="question_text" required><?php echo htmlspecialchars($question['question_text']); ?></textarea>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="question_type_id">Question Type</label>
                <select id="question_type_id" name="question_type_id" required>
                    <?php foreach($question_types as $type): ?>
                    <option value="<?php echo $type['id']; ?>" <?php if($type['id'] == $question['question_type_id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($type['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="difficulty_id">Difficulty</label>
                <select id="difficulty_id" name="difficulty_id" required>
                    <?php foreach($difficulties as $diff): ?>
                    <option value="<?php echo $diff['id']; ?>" <?php if($diff['id'] == $question['difficulty_id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($diff['level']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="points">Points</label>
                <input type="number" id="points" name="points" class="input-field" min="0" step="0.5" value="<?php echo htmlspecialchars($question['points']); ?>" required>
            </div>
        </div>

        <div id="options-section">
            <div class="options-container">
                <p style="margin-top:0; font-weight:bold; text-align:center;">Options & Correct Answer</p>
                <?php for ($i = 0; $i < 4; $i++): ?>
                <div class="option-item">
                    <label>Option <?php echo $i + 1; ?>:</label>
                    <input type="text" name="options[]" class="input-field" value="<?php echo htmlspecialchars($options[$i]['option_text'] ?? ''); ?>">
                    <input type="hidden" name="option_ids[]" value="<?php echo htmlspecialchars($options[$i]['id'] ?? ''); ?>">
                    <div class="correct-answer-group">
                       <input type="checkbox" name="correct_answers[]" value="<?php echo $i; ?>" <?php if(isset($options[$i]) && $options[$i]['is_correct']) echo 'checked'; ?>>
                       <label>Correct</label>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <div class="form-group" style="text-align: center; margin-top: 30px;">
            <button type="submit" class="button-red" style="width: auto; padding: 12px 40px;">Save Changes</button>
            <a href="question_view.php?quiz_id=<?php echo $question['quiz_id']; ?>" style="display:inline-block; margin-left:15px; color:#555;">Cancel</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const questionTypeSelect = document.getElementById('question_type_id');
    const optionsSection = document.getElementById('options-section');
    const checkboxes = document.querySelectorAll('input[name="correct_answers[]"]');

    function handleQuestionTypeChange() {
        const typeName = questionTypeSelect.options[questionTypeSelect.selectedIndex].text.trim();
        
        // Hide options if Descriptive
        if (typeName === 'Descriptive') {
            optionsSection.style.display = 'none';
        } else {
            optionsSection.style.display = 'block';
        }

        // If changed to MCQ, enforce single selection immediately
        if (typeName === 'MCQ') {
            let checkedCount = 0;
            checkboxes.forEach(function(cb) {
                if (cb.checked) {
                    checkedCount++;
                    if (checkedCount > 1) {
                        cb.checked = false;
                    }
                }
            });
        }
    }

    // Enforce single selection for MCQ when a checkbox is clicked
    checkboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const typeName = questionTypeSelect.options[questionTypeSelect.selectedIndex].text.trim();
            if (typeName === 'MCQ' && this.checked) {
                checkboxes.forEach(function(cb) {
                    if (cb !== checkbox) cb.checked = false;
                });
            }
        });
    });

    questionTypeSelect.addEventListener('change', handleQuestionTypeChange);

    // Run on initial load
    handleQuestionTypeChange();
});
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>
