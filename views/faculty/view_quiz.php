<?php
  $pageTitle = 'Add Quiz Questions';
  $customProceedButtonText = 'Proceed &#8594;';
  $customProceedButtonUrl = 'manage_quizzes.php';
  
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
  $stmt = $pdo->prepare("SELECT title, start_time, end_time, config_easy_count, config_medium_count, config_hard_count, show_results_immediately FROM quizzes WHERE id = :quiz_id");
  $stmt->execute([':quiz_id' => $quiz_id]);
  $quiz = $stmt->fetch();
  if (!$quiz) {
      header('Location: manage_quizzes.php');
      exit();
  }
  $question_types = $pdo->query("SELECT id, name FROM question_types")->fetchAll();
  $difficulties = $pdo->query("SELECT id, level FROM question_difficulties")->fetchAll();

  // --- Fetch Current Question Counts Grouped By Difficulty & Type ---
  $count_stmt = $pdo->prepare("
    SELECT qd.level as diff_level, qt.name as type_name, COUNT(q.id) as current_count 
    FROM questions q 
    JOIN question_difficulties qd ON q.difficulty_id = qd.id 
    JOIN question_types qt ON q.question_type_id = qt.id
    WHERE q.quiz_id = :quiz_id 
    GROUP BY qd.id, qt.id
  ");
  $count_stmt->execute([':quiz_id' => $quiz_id]);
  $type_diff_counts = $count_stmt->fetchAll(PDO::FETCH_ASSOC);

  // Initialize count structure
  $stats = [
      'Easy' => ['total' => 0, 'MCQ' => 0, 'MSQ' => 0, 'Descriptive' => 0],
      'Medium' => ['total' => 0, 'MCQ' => 0, 'MSQ' => 0, 'Descriptive' => 0],
      'Hard' => ['total' => 0, 'MCQ' => 0, 'MSQ' => 0, 'Descriptive' => 0]
  ];

  foreach ($type_diff_counts as $row) {
      $diff = $row['diff_level'];
      $type = $row['type_name'];
      $count = $row['current_count'];
      if (isset($stats[$diff])) {
          $stats[$diff][$type] = $count;
          $stats[$diff]['total'] += $count;
      }
  }

  $total_added = $stats['Easy']['total'] + $stats['Medium']['total'] + $stats['Hard']['total'];
?>

<div class="manage-container" style="position: relative;">

    <?php
        $parts = explode(' - ', $quiz['title']);
        if (count($parts) >= 4) {
            $quizName = trim($parts[2]);
            $courseInfo = trim($parts[0]) . " | " . trim($parts[1]);
            $dateInfo = trim($parts[3]);
            $sectionInfo = isset($parts[4]) ? trim($parts[4]) : '';
        } else {
            $quizName = $quiz['title'];
            $courseInfo = '';
            $dateInfo = '';
            $sectionInfo = '';
        }
        $start_time_str = date('g:i A', strtotime($quiz['start_time']));
        $end_time_str = date('g:i A', strtotime($quiz['end_time']));
    ?>
    
    <?php if ($dateInfo): ?>
    <div style="position: absolute; top: 25px; left: 30px; text-align: left; font-size: 0.9em; color: #555; background: #f8f9fa; padding: 10px 15px; border-radius: 8px; border: 1px solid #e9ecef;">
        <div style="font-weight: bold; margin-bottom: 5px; color: #333; font-size: 1.1em;"><?php echo htmlspecialchars($dateInfo); ?></div>
        <div style="margin-bottom: 3px;"><strong>Start:</strong> <?php echo $start_time_str; ?></div>
        <div><strong>End:</strong> <?php echo $end_time_str; ?></div>
    </div>
    <?php endif; ?>

    <div style="text-align: center; margin-bottom: 25px; padding-top: 15px;">
        <h2 style="margin-bottom: 8px; color: #333; font-size: 1.8em;"><?php echo htmlspecialchars($quizName); ?></h2>
        <?php if ($courseInfo): ?>
            <h4 style="margin-top: 0; margin-bottom: 6px; color: #555; font-weight: 600;"><?php echo htmlspecialchars($courseInfo); ?></h4>
        <?php endif; ?>
        <?php if ($sectionInfo): ?>
            <p style="margin-top: 0; color: #777; font-size: 0.95em;"><?php echo htmlspecialchars($sectionInfo); ?></p>
        <?php endif; ?>
    </div>
    
    <?php
    if (isset($_GET['success'])) { echo '<div class="message-box success-message">' . htmlspecialchars($_GET['success']) . '</div>'; }
    if (isset($_GET['error'])) { echo '<div class="message-box error-message">' . htmlspecialchars($_GET['error']) . '</div>'; }
    ?>

    <div style="text-align:center; margin: 20px 0;">
        <a href="question_view.php?quiz_id=<?php echo $quiz_id; ?>" class="button-red" style="width: auto; padding: 12px 30px; background-color: #17a2b8;">View & Manage Existing Questions</a>
    </div>

    <div class="section-box" style="background-color: #f8f9fa; border: 1px solid #dee2e6; margin-bottom: 20px; border-radius: 8px; box-shadow: inset 0 1px 3px rgba(0,0,0,0.05); overflow: hidden;">
        <div style="display: flex;">
            
            <!-- Easy Column -->
            <div style="flex: 1; display: flex; flex-direction: column;">
                <div style="padding: 15px; text-align: center;">
                    <h4 style="margin-top: 0; margin-bottom: 5px; color: #555; text-transform: uppercase; font-size: 0.9em;">Easy Questions</h4>
                    <div style="font-size: 1.5em; font-weight: bold; color: <?php echo ($stats['Easy']['total'] >= $quiz['config_easy_count']) ? '#28a745' : '#e60000'; ?>;">
                        <?php echo $stats['Easy']['total']; ?> <span style="font-size: 0.6em; color: #888;">/ <?php echo $quiz['config_easy_count']; ?></span>
                    </div>
                </div>
                <div style="display: flex; margin: 0 15px 15px 15px; border: 1px solid #ddd; border-radius: 6px; background-color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
                    <div style="flex: 1; text-align: center; padding: 10px 0;">
                        <div style="color: #777; font-size: 0.75em; text-transform: uppercase;">MCQ</div>
                        <strong style="font-size: 1.1em; color: #333;"><?php echo $stats['Easy']['MCQ']; ?></strong>
                    </div>
                    <div style="flex: 1; text-align: center; padding: 10px 0; border-left: 1px solid #ddd;">
                        <div style="color: #777; font-size: 0.75em; text-transform: uppercase;">MSQ</div>
                        <strong style="font-size: 1.1em; color: #333;"><?php echo $stats['Easy']['MSQ']; ?></strong>
                    </div>
                    <div style="flex: 1; text-align: center; padding: 10px 0; border-left: 1px solid #ddd;">
                        <div style="color: #777; font-size: 0.75em; text-transform: uppercase;">Desc</div>
                        <strong style="font-size: 1.1em; color: #333;"><?php echo $stats['Easy']['Descriptive']; ?></strong>
                    </div>
                </div>
            </div>
            
            <!-- Medium Column -->
            <div style="flex: 1; display: flex; flex-direction: column; border-left: 1px solid #e2e6ea;">
                <div style="padding: 15px; text-align: center;">
                    <h4 style="margin-top: 0; margin-bottom: 5px; color: #555; text-transform: uppercase; font-size: 0.9em;">Medium Questions</h4>
                    <div style="font-size: 1.5em; font-weight: bold; color: <?php echo ($stats['Medium']['total'] >= $quiz['config_medium_count']) ? '#28a745' : '#e60000'; ?>;">
                        <?php echo $stats['Medium']['total']; ?> <span style="font-size: 0.6em; color: #888;">/ <?php echo $quiz['config_medium_count']; ?></span>
                    </div>
                </div>
                <div style="display: flex; margin: 0 15px 15px 15px; border: 1px solid #ddd; border-radius: 6px; background-color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
                    <div style="flex: 1; text-align: center; padding: 10px 0;">
                        <div style="color: #777; font-size: 0.75em; text-transform: uppercase;">MCQ</div>
                        <strong style="font-size: 1.1em; color: #333;"><?php echo $stats['Medium']['MCQ']; ?></strong>
                    </div>
                    <div style="flex: 1; text-align: center; padding: 10px 0; border-left: 1px solid #ddd;">
                        <div style="color: #777; font-size: 0.75em; text-transform: uppercase;">MSQ</div>
                        <strong style="font-size: 1.1em; color: #333;"><?php echo $stats['Medium']['MSQ']; ?></strong>
                    </div>
                    <div style="flex: 1; text-align: center; padding: 10px 0; border-left: 1px solid #ddd;">
                        <div style="color: #777; font-size: 0.75em; text-transform: uppercase;">Desc</div>
                        <strong style="font-size: 1.1em; color: #333;"><?php echo $stats['Medium']['Descriptive']; ?></strong>
                    </div>
                </div>
            </div>
            
            <!-- Hard Column -->
            <div style="flex: 1; display: flex; flex-direction: column; border-left: 1px solid #e2e6ea;">
                <div style="padding: 15px; text-align: center;">
                    <h4 style="margin-top: 0; margin-bottom: 5px; color: #555; text-transform: uppercase; font-size: 0.9em;">Hard Questions</h4>
                    <div style="font-size: 1.5em; font-weight: bold; color: <?php echo ($stats['Hard']['total'] >= $quiz['config_hard_count']) ? '#28a745' : '#e60000'; ?>;">
                        <?php echo $stats['Hard']['total']; ?> <span style="font-size: 0.6em; color: #888;">/ <?php echo $quiz['config_hard_count']; ?></span>
                    </div>
                </div>
                <div style="display: flex; margin: 0 15px 15px 15px; border: 1px solid #ddd; border-radius: 6px; background-color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
                    <div style="flex: 1; text-align: center; padding: 10px 0;">
                        <div style="color: #777; font-size: 0.75em; text-transform: uppercase;">MCQ</div>
                        <strong style="font-size: 1.1em; color: #333;"><?php echo $stats['Hard']['MCQ']; ?></strong>
                    </div>
                    <div style="flex: 1; text-align: center; padding: 10px 0; border-left: 1px solid #ddd;">
                        <div style="color: #777; font-size: 0.75em; text-transform: uppercase;">MSQ</div>
                        <strong style="font-size: 1.1em; color: #333;"><?php echo $stats['Hard']['MSQ']; ?></strong>
                    </div>
                    <div style="flex: 1; text-align: center; padding: 10px 0; border-left: 1px solid #ddd;">
                        <div style="color: #777; font-size: 0.75em; text-transform: uppercase;">Desc</div>
                        <strong style="font-size: 1.1em; color: #333;"><?php echo $stats['Hard']['Descriptive']; ?></strong>
                    </div>
                </div>
            </div>
            
        </div>
    </div>

    <div style="text-align: center; margin-bottom: 20px;">
        <div class="toggle-group" style="display: inline-flex; border-radius: 8px; overflow: hidden; border: 1px solid #ccc;">
            <button id="toggle-manual-btn" type="button" style="padding: 10px 20px; background-color: #e60000; color: white; border: none; cursor: pointer; font-weight: bold;">Manual Entry</button>
            <button id="toggle-excel-btn" type="button" style="padding: 10px 20px; background-color: #f8f9fa; color: #333; border: none; cursor: pointer; font-weight: bold; border-left: 1px solid #ccc;">Excel Upload</button>
        </div>
    </div>

    <div class="section-box" id="manual-add-section">
        <h3>Add Question <?php echo $total_added + 1; ?> Manually</h3>
        
        <?php if ($quiz['show_results_immediately'] == 1): ?>
        <div style="background-color: #fff3cd; color: #856404; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 0.9em; border: 1px solid #ffeeba;">
            <strong>Note:</strong> Since this quiz is set to show results instantly, any descriptive questions added will be evaluated later. You must select the correct answer for MCQ and MSQ questions.
        </div>
        <?php endif; ?>

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

    <div class="section-box" id="excel-add-section" style="display: none;">
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

    // Form submission validation
    const form = document.querySelector('.manual-add-form');
    form.addEventListener('submit', function(e) {
        const selectedType = questionTypeSelect.value;
        // 1 for MCQ, 2 for MSQ
        if (selectedType === '1' || selectedType === '2') {
            const checkedOptions = optionsSection.querySelectorAll('input[type="radio"]:checked, input[type="checkbox"]:checked');
            if (checkedOptions.length === 0) {
                e.preventDefault();
                alert('Please select the correct answer(s) before adding the question.');
            }
        }
    });
    // Toggle logic for manual vs excel upload
    const manualBtn = document.getElementById('toggle-manual-btn');
    const excelBtn = document.getElementById('toggle-excel-btn');
    const manualSection = document.getElementById('manual-add-section');
    const excelSection = document.getElementById('excel-add-section');

    function showManual() {
        manualSection.style.display = 'block';
        excelSection.style.display = 'none';
        manualBtn.style.backgroundColor = '#e60000';
        manualBtn.style.color = 'white';
        excelBtn.style.backgroundColor = '#f8f9fa';
        excelBtn.style.color = '#333';
    }

    function showExcel() {
        manualSection.style.display = 'none';
        excelSection.style.display = 'block';
        excelBtn.style.backgroundColor = '#e60000';
        excelBtn.style.color = 'white';
        manualBtn.style.backgroundColor = '#f8f9fa';
        manualBtn.style.color = '#333';
    }

    manualBtn.addEventListener('click', showManual);
    excelBtn.addEventListener('click', showExcel);
});
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>
