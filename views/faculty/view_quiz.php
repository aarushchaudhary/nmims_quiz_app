<?php
  $pageTitle = 'Add Quiz Questions';
  
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
  
  // --- Fetch Quiz & Form Data ---
  $stmt = $pdo->prepare("SELECT title FROM quizzes WHERE id = :quiz_id");
  $stmt->execute([':quiz_id' => $quiz_id]);
  $quiz = $stmt->fetch();
  if (!$quiz) {
      header('Location: manage_quizzes.php');
      exit();
  }
  $question_types = $pdo->query("SELECT id, name FROM question_types")->fetchAll();
  $difficulties = $pdo->query("SELECT id, level FROM question_difficulties")->fetchAll();
?>

<div class="manage-container">

    <h2 style="text-align: center;"><?php echo htmlspecialchars($quiz['title']); ?></h2>
    
    <?php
    if (isset($_GET['success'])) { echo '<div class="message-box success-message">' . htmlspecialchars($_GET['success']) . '</div>'; }
    if (isset($_GET['error'])) { echo '<div class="message-box error-message">' . htmlspecialchars($_GET['error']) . '</div>'; }
    ?>

    <div style="text-align:center; margin: 20px 0;">
        <a href="question_view.php?quiz_id=<?php echo $quiz_id; ?>" class="button-red" style="width: auto; padding: 12px 30px; background-color: #17a2b8;">View & Manage Existing Questions</a>
    </div>

    <div class="section-box">
        <h3>Add Question Manually</h3>
        <form action="<?= get_base_url() ?>api/faculty/add_manual_question.php" method="POST" class="manual-add-form">
            <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">
            
            <div class="form-group">
                <label for="question_text">Question Text</label>
                <textarea id="question_text" name="question_text" required></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group"><label for="question_type_id">Question Type</label><select id="question_type_id" name="question_type_id" required><option value="" disabled selected>-- Select Type --</option><?php foreach($question_types as $type): ?><option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label for="difficulty_id">Difficulty</label><select id="difficulty_id" name="difficulty_id" required><option value="" disabled selected>-- Select Difficulty --</option><?php foreach($difficulties as $diff): ?><option value="<?php echo $diff['id']; ?>"><?php echo htmlspecialchars($diff['level']); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label for="points">Points / Marks</label><input type="number" id="points" name="points" class="input-field" min="0" step="0.5" value="1.0" required></div>
            </div>

            <div id="options-section" style="display: none;">
                <div class="options-container">
                    <p style="margin-top:0; font-weight:bold; text-align:center;">Options & Correct Answer</p>
                    <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="option-item">
                        <label>Option <?php echo $i + 1; ?>:</label>
                        <input type="text" name="options[]" class="input-field">
                        <div class="correct-answer-group">
                           <input type="checkbox" name="correct_answers[]" value="<?php echo $i; ?>">
                           <label>Correct</label>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="form-group" style="text-align: center; margin-top: 30px;">
                <button type="submit" class="button-red" style="width: auto; padding: 12px 40px;">Add Question</button>
            </div>
        </form>
    </div>

    <div class="section-box">
        <h3>Upload Questions via Excel</h3>
        <form action="<?= get_base_url() ?>api/faculty/upload_questions.php" method="POST" enctype="multipart/form-data" class="upload-form">
            <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">
            <div class="form-group">
                <label for="question_file">Select Excel File (.xlsx)</label>
                <input type="file" id="question_file" name="question_file" accept=".xlsx, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
            </div>
            <div class="form-group" style="text-align: center; margin-top: 20px;">
                <button type="submit" class="button-red" style="width: auto; padding: 12px 40px;">Upload File</button>
            </div>
            <p style="text-align:center; margin-top:15px; font-size: 0.9em;">
                Need a template? <a href="<?= get_asset_url('assets/templates/question_template.xlsx') ?>" download>Download Excel Template</a>
            </p>
        </form>
    </div>
</div>

<script>
// The JavaScript for the manual add form remains the same
document.addEventListener('DOMContentLoaded', function() {
    const questionTypeSelect = document.getElementById('question_type_id');
    const optionsSection = document.getElementById('options-section');
    
    function toggleOptions() {
        const selectedType = questionTypeSelect.value;
        const correctInputs = optionsSection.querySelectorAll('input[type="radio"], input[type="checkbox"]');
        
        if (selectedType === '3' || selectedType === '') {
            optionsSection.style.display = 'none';
        } else {
            optionsSection.style.display = 'block';
        }

        correctInputs.forEach(input => {
            if (selectedType === '1') {
                input.type = 'radio';
                input.name = 'correct_answer_single'; 
            } else {
                input.type = 'checkbox';
                input.name = 'correct_answers[]';
            }
        });
    }

    questionTypeSelect.addEventListener('change', toggleOptions);
    toggleOptions();
});
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>
