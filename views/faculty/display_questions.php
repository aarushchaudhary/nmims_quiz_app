<?php
  $pageTitle = 'Display Questions';
  $customBackButtonText = '&#8592; Back to Quizzes';
  $customBackButtonUrl = 'manage_quizzes.php';
  
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
  
  // --- Fetch Quiz Data ---
  $stmt = $pdo->prepare("SELECT title, start_time, end_time, duration_minutes FROM quizzes WHERE id = :quiz_id");
  $stmt->execute([':quiz_id' => $quiz_id]);
  $quiz = $stmt->fetch();
  if (!$quiz) {
      header('Location: manage_quizzes.php');
      exit();
  }

  // --- Fetch Questions ---
  $questions_stmt = $pdo->prepare("
      SELECT q.id, q.question_text, qt.name as type_name, qd.level as difficulty, q.points as marks
      FROM questions q 
      JOIN question_types qt ON q.question_type_id = qt.id
      JOIN question_difficulties qd ON q.difficulty_id = qd.id
      WHERE q.quiz_id = :quiz_id 
      ORDER BY qt.id ASC, q.id ASC
  ");
  $questions_stmt->execute([':quiz_id' => $quiz_id]);
  $questions = $questions_stmt->fetchAll();

  // Group questions by type for neat subheadings
  $questions_by_type = [];
  foreach ($questions as $q) {
      $questions_by_type[$q['type_name']][] = $q;
  }

  // Fetch all options
  $options_stmt = $pdo->prepare("
      SELECT o.question_id, o.id, o.option_text, o.is_correct 
      FROM options o 
      JOIN questions q ON o.question_id = q.id 
      WHERE q.quiz_id = :quiz_id 
      ORDER BY o.id ASC
  ");
  $options_stmt->execute([':quiz_id' => $quiz_id]);
  $all_options = $options_stmt->fetchAll();
  
  $options_by_q = [];
  foreach ($all_options as $opt) {
      $options_by_q[$opt['question_id']][] = $opt;
  }
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

    <div style="text-align: center; margin-bottom: 30px; padding-top: 15px;">
        <h2 style="margin-bottom: 8px; color: #333; font-size: 1.8em;"><?php echo htmlspecialchars($quizName); ?></h2>
        <?php if ($courseInfo): ?>
            <h4 style="margin-top: 0; margin-bottom: 6px; color: #555; font-weight: 600;"><?php echo htmlspecialchars($courseInfo); ?></h4>
        <?php endif; ?>
        <?php if ($sectionInfo): ?>
            <p style="margin-top: 0; color: #777; font-size: 0.95em;"><?php echo htmlspecialchars($sectionInfo); ?></p>
        <?php endif; ?>
    </div>

    <div class="section-box">
        <h3 style="border-bottom: 2px solid #e60000; padding-bottom: 10px; margin-bottom: 25px; color: #333;">Quiz Questions</h3>
        
        <?php if (empty($questions)): ?>
            <p style="text-align: center; color: #777; padding: 20px;">No questions found for this quiz.</p>
        <?php else: ?>
            <?php 
                $serial = 1; 
                foreach (['MCQ', 'Multiple Answer', 'Descriptive'] as $type): 
                    if (!isset($questions_by_type[$type])) continue;
            ?>
                <div style="margin-bottom: 30px;">
                    <h4 style="background-color: #f8f9fa; padding: 10px 15px; border-left: 4px solid #333; margin-bottom: 20px; color: #444; border-radius: 4px;">
                        <?php echo $type; ?> Questions
                    </h4>
                    
                    <?php foreach ($questions_by_type[$type] as $q): ?>
                        <div style="margin-bottom: 25px; padding: 15px; border: 1px solid #e9ecef; border-radius: 6px; background-color: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                                <div style="font-weight: 600; font-size: 1.05em; color: #333; flex: 1;">
                                    <span style="color: #e60000; margin-right: 8px;">Q<?php echo $serial++; ?>.</span> 
                                    <?php echo nl2br(htmlspecialchars($q['question_text'])); ?>
                                </div>
                                <div style="font-size: 0.85em; color: #666; background: #f1f3f5; padding: 4px 8px; border-radius: 4px; margin-left: 15px; white-space: nowrap;">
                                    <?php echo htmlspecialchars($q['difficulty']); ?> | <?php echo $q['marks']; ?> Marks
                                </div>
                            </div>
                            
                            <?php if ($type !== 'Descriptive' && isset($options_by_q[$q['id']])): ?>
                                <div style="margin-left: 30px;">
                                    <?php 
                                        $alpha = 'A';
                                        foreach ($options_by_q[$q['id']] as $opt): 
                                    ?>
                                        <div style="margin-bottom: 8px; padding: 8px 12px; border-radius: 4px; background-color: <?php echo $opt['is_correct'] ? '#d4edda' : '#f8f9fa'; ?>; border: 1px solid <?php echo $opt['is_correct'] ? '#c3e6cb' : '#e9ecef'; ?>;">
                                            <span style="font-weight: bold; margin-right: 10px; color: <?php echo $opt['is_correct'] ? '#155724' : '#555'; ?>;"><?php echo $alpha++; ?>.</span>
                                            <span style="color: <?php echo $opt['is_correct'] ? '#155724' : '#333'; ?>;">
                                                <?php echo htmlspecialchars($opt['option_text']); ?>
                                            </span>
                                            <?php if ($opt['is_correct']): ?>
                                                <span style="float: right; color: #28a745; font-size: 0.85em; font-weight: bold;">✓ Correct</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../assets/templates/footer.php'; ?>
