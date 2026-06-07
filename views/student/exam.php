<?php
  $pageTitle = 'Taking Exam';
  // This flag tells the header to hide Home/Logout/Back buttons.
  $isExamPage = true; 
  
  require_once '../../assets/templates/header.php';

  // --- Authorization & Input Check ---
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4) {
      redirect('login.php');
      exit();
  }
  $quiz_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
?>

<!-- Warning Overlay (proctoring) -->
<div id="warning-overlay" style="display:none;">
    <div class="warning-box">
        <h2>Warning!</h2>
        <p>You have left the exam window. This action has been logged.<br>Please remain on this page to avoid disqualification.</p>
        <p>You have <span id="warning-count" style="font-weight:bold;">1 of 2</span> warnings.</p>
    </div>
</div>

<!-- Main Exam Interface (hidden until loaded) -->
<div id="exam-container" class="exam-wrapper" style="display:none;" data-quiz-id="<?php echo htmlspecialchars($quiz_id); ?>">
    
    <!-- LEFT PANEL: Question & Controls -->
    <div class="exam-left-panel">
        <!-- Question Header -->
        <div class="exam-question-header">
            <div>
                <span class="exam-question-label" id="question-label">Question 1:</span>
                <span id="question-marking-scheme" style="margin-left: 15px; font-size: 14px; color: #555; background-color: #fff; padding: 4px 8px; border-radius: 4px; border: 1px solid #ccc; display: inline-block;">Marks: +1.0</span>
            </div>
            <div style="display: flex; align-items: center; gap: 15px;">
                <button id="calculator-btn" style="display: none; background: #e0e0e0; border: 1px solid #ccc; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-weight: bold;">🖩 Calculator</button>
                <span class="timer" id="timer">Time Left: 00:00</span>
            </div>
        </div>

        <!-- Question Body -->
        <div class="exam-question-body">
            <p class="question-text" id="question-text">Loading question...</p>
            <div class="options-grid" id="options-grid"></div>
        </div>

        <!-- Action Buttons Row -->
        <div class="exam-action-buttons">
            <button class="exam-btn exam-btn-save" id="save-next-btn">SAVE & NEXT</button>
            <button class="exam-btn exam-btn-save-mark" id="save-mark-btn">SAVE & MARK FOR REVIEW</button>
            <button class="exam-btn exam-btn-clear" id="clear-btn">CLEAR RESPONSE</button>
            <button class="exam-btn exam-btn-mark" id="mark-next-btn">MARK FOR REVIEW & NEXT</button>
        </div>

        <!-- Bottom Navigation Bar -->
        <div class="exam-nav-bar">
            <div class="exam-nav-left">
                <button class="exam-nav-btn" id="back-btn">&lt;&lt; BACK</button>
                <button class="exam-nav-btn" id="next-btn">NEXT &gt;&gt;</button>
            </div>
            <button class="exam-nav-btn exam-submit-btn" id="submit-btn">SUBMIT</button>
        </div>
    </div>

    <!-- RIGHT PANEL: Question Palette -->
    <div class="exam-right-panel">
        <!-- Status Legend -->
        <div class="palette-legend">
            <div class="legend-row">
                <span class="legend-indicator legend-not-visited" id="count-not-visited">0</span>
                <span class="legend-text">Not Visited</span>
            </div>
            <div class="legend-row">
                <span class="legend-indicator legend-not-answered" id="count-not-answered">0</span>
                <span class="legend-text">Not Answered</span>
            </div>
            <div class="legend-row">
                <span class="legend-indicator legend-answered" id="count-answered">0</span>
                <span class="legend-text">Answered</span>
            </div>
            <div class="legend-row">
                <span class="legend-indicator legend-marked" id="count-marked">0</span>
                <span class="legend-text">Marked for Review</span>
            </div>
            <div class="legend-row">
                <span class="legend-indicator legend-answered-marked" id="count-answered-marked">0</span>
                <span class="legend-text">Answered & Marked for Review</span>
            </div>
        </div>

        <!-- Question Number Grid -->
        <div class="palette-grid" id="palette-grid">
            <!-- Buttons injected by JS -->
        </div>
    </div>
</div>

<!-- Submit Confirmation Modal -->
<div id="submit-modal" class="confirm-modal-overlay">
    <div class="confirm-modal">
        <h3>Submit Exam?</h3>
        <div id="submit-summary" style="text-align:left; margin-bottom:20px; line-height:1.8;"></div>
        <p>Are you sure you want to submit? You cannot change your answers after submission.</p>
        <div class="button-group">
            <button class="btn-cancel" id="submit-cancel-btn">Go Back</button>
            <button class="btn-confirm-delete" id="submit-confirm-btn" style="background-color:#28a745;">Yes, Submit</button>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loading-overlay">
    <div class="spinner"></div>
    <p style="text-align:center; font-weight:bold; font-size: 22px;">Preparing your exam...</p>
    <p id="click-prompt" style="margin-top: 15px; font-size: 18px;"></p>
</div>

<!-- Basic Calculator -->
<div id="calculator-ui" style="display: none; position: fixed; top: 100px; right: 300px; width: 250px; background: #fff; border: 1px solid #ccc; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); z-index: 1000;">
    <div id="calc-header" style="background: #007bff; color: white; padding: 10px; border-top-left-radius: 8px; border-top-right-radius: 8px; font-weight: bold; cursor: move; display: flex; justify-content: space-between;">
        <span>Calculator</span>
        <span id="calc-close" style="cursor: pointer;">&times;</span>
    </div>
    <div style="padding: 15px;">
        <input type="text" id="calc-display" disabled style="width: 100%; height: 40px; text-align: right; margin-bottom: 10px; font-size: 18px; border: 1px solid #ccc; background: #f9f9f9;">
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px;">
            <button class="calc-btn" id="calc-clear" style="background: #dc3545; color: white;">C</button>
            <button class="calc-btn" id="calc-sqrt">√</button>
            <button class="calc-btn" id="calc-percent">%</button>
            <button class="calc-btn calc-op" data-val="/">÷</button>

            <button class="calc-btn" data-val="7">7</button>
            <button class="calc-btn" data-val="8">8</button>
            <button class="calc-btn" data-val="9">9</button>
            <button class="calc-btn calc-op" data-val="*">×</button>
            
            <button class="calc-btn" data-val="4">4</button>
            <button class="calc-btn" data-val="5">5</button>
            <button class="calc-btn" data-val="6">6</button>
            <button class="calc-btn calc-op" data-val="-">-</button>
            
            <button class="calc-btn" data-val="1">1</button>
            <button class="calc-btn" data-val="2">2</button>
            <button class="calc-btn" data-val="3">3</button>
            <button class="calc-btn calc-op" data-val="+">+</button>
            
            <button class="calc-btn" id="calc-neg">+/-</button>
            <button class="calc-btn" data-val="0">0</button>
            <button class="calc-btn" data-val=".">.</button>
            <button class="calc-btn" id="calc-equals" style="background: #28a745; color: white;">=</button>
        </div>
    </div>
</div>

<style>
.calc-btn {
    padding: 10px 0;
    font-size: 16px;
    border: 1px solid #ddd;
    background: #f1f1f1;
    border-radius: 4px;
    cursor: pointer;
}
.calc-btn:hover { background: #e2e2e2; }
.calc-op { background: #ffc107; }
.calc-op:hover { background: #e0a800; }
</style>

<script src="<?= get_asset_url('assets/js/script.js') ?>" defer></script>

<?php
  // The footer is intentionally omitted on the exam page.
?>
