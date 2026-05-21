<?php
  $pageTitle = 'Taking Exam';
  // **CRITICAL FIX:** This flag tells the header to hide the buttons.
  $isExamPage = true; 
  
  require_once '../../assets/templates/header.php';

  // --- Authorization & Input Check ---
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4) {
      redirect('login.php');
      exit();
  }
  $quiz_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
?>

<div id="warning-overlay" style="display:none;">
    <div class="warning-box">
        <h2>Warning!</h2>
        <p>You have left the exam window. This action has been logged.<br>Please remain on this page to avoid disqualification.</p>
        <p>You have <span id="warning-count" style="font-weight:bold;">1 of 2</span> warnings.</p>
    </div>
</div>

<div id="exam-container" class="exam-container" style="display:none;" data-quiz-id="<?php echo htmlspecialchars($quiz_id); ?>">
    <div class="exam-header">
        <div class="question-counter" id="question-counter">Question 1 of N</div>
        <div class="timer" id="timer">Time Left: 00:00</div>
    </div>
    <div class="question-area">
        <p class="question-text" id="question-text">Loading question...</p>
        <div class="options-grid" id="options-grid">
            </div>
    </div>
    <div class="exam-footer">
        <button class="button-red btn-next" id="next-btn" disabled>Next Question</button>
    </div>
</div>

<div id="loading-overlay">
    <div class="spinner"></div>
    <p style="text-align:center; font-weight:bold; font-size: 22px;">Preparing your exam...</p>
    <p id="click-prompt" style="margin-top: 15px; font-size: 18px;"></p>
</div>

<script src="<?= get_asset_url('assets/js/script.js') ?>" defer></script>

<?php
  // The footer is intentionally omitted on the exam page.
?>
