<?php
  $pageTitle = 'Detailed Results';
  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';
  require_once '../../config/authz.php';

  // SECURITY FIX #1: Role and login check.
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4) {
      redirect('login.php');
      exit();
  }

  $attempt_id = isset($_GET['attempt_id']) ? filter_var($_GET['attempt_id'], FILTER_VALIDATE_INT) : false;
  if ($attempt_id === false || !$attempt_id) {
      header('Location: dashboard.php?error=invalid_attempt');
      exit();
  }

  $student_user_id = (int) $_SESSION['user_id'];

  // SECURITY FIX #1: Ownership + publication check (same pattern as results.php).
  $stmt = $pdo->prepare(
      'SELECT sa.id, q.show_results_immediately, q.descriptive_published
       FROM student_attempts sa
       JOIN quizzes q ON sa.quiz_id = q.id
       WHERE sa.id = ? AND sa.student_id = ?'
  );
  $stmt->execute([$attempt_id, $student_user_id]);
  $guard = $stmt->fetch();

  if (!$guard) {
      header('Location: dashboard.php?error=not_found');
      exit();
  }

  if (!$guard['show_results_immediately']) {
      header('Location: dashboard.php?error=results_not_published');
      exit();
  }

  $descriptive_published = (bool) $guard['descriptive_published'];
?>

<div class="manage-container detailed-results-container">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h2>Question Breakdown</h2>

    </div>
    <div id="results-breakdown">
        <!-- Detailed results will be loaded here by JavaScript -->
        <p>Loading detailed report...</p>
    </div>
</div>

<script>
const BASE_URL = '<?= get_base_url() ?>';
document.addEventListener('DOMContentLoaded', async function() {
    const attemptId = <?php echo json_encode($attempt_id); ?>;
    const breakdownContainer = document.getElementById('results-breakdown');

    if (!attemptId) {
        breakdownContainer.innerHTML = '<p style="color:red;">Error: No attempt ID specified.</p>';
        return;
    }

    try {
        const response = await fetch(BASE_URL + `api/student/get_detailed_results.php?attempt_id=${attemptId}`);
        if (!response.ok) throw new Error('Failed to load report data.');

        const results = await response.json();
        breakdownContainer.innerHTML = ''; // Clear loading message

        // API returns results_pending when publication gate is not met server-side.
        if (results.results_pending) {
            breakdownContainer.innerHTML = '<p>Results have not been published yet. Please check back later.</p>';
            return;
        }

        if (!Array.isArray(results) || results.length === 0) {
            breakdownContainer.innerHTML = '<p>No answered questions found for this attempt.</p>';
            return;
        }

        const descriptivePublished = <?php echo json_encode($descriptive_published); ?>;

        // Verdict config: is_correct values → label + colour
        const VERDICTS = {
            '1':    { label: 'Correct',           color: '#28a745' },
            '2':    { label: 'Partially Correct',  color: '#fd7e14' },
            '0':    { label: 'Incorrect',          color: '#dc3545' },
            '3':    { label: 'Pending Evaluation', color: '#17a2b8' },
            'null': { label: 'Not Answered',       color: '#6c757d' },
        };

        let hasDescriptive = false;
        let displayIndex = 1;

        results.forEach((item) => {
            if (item.question_type_id == 3 && !descriptivePublished) {
                hasDescriptive = true;
                return; // Skip displaying descriptive questions until published
            }

            const verdictKey = item.is_correct === null ? 'null' : String(item.is_correct);
            const verdict = VERDICTS[verdictKey] || VERDICTS['null'];

            // Marks display
            const maxPts = item.points != null ? item.points : '?';
            let marksHtml;
            if (item.score_awarded !== null && item.score_awarded !== undefined) {
                marksHtml = `<strong style="color:${verdict.color};">${item.score_awarded}</strong> / ${maxPts}`;
            } else {
                marksHtml = `— / ${maxPts}`;
            }

            // Options (MCQ / MSQ)
            let answersHtml = '';
            if (item.question_type_id == 3) {
                // Descriptive
                const text = item.answer_text && item.answer_text.trim() !== ''
                    ? item.answer_text
                    : '<em style="color:#999;">No answer provided.</em>';
                answersHtml = `<div style="background:#f8f9fa; border-left:4px solid #adb5bd; padding:12px 16px; border-radius:4px; white-space:pre-wrap; font-family:monospace; font-size:14px;">${text}</div>`;
            } else {
                item.options.forEach(option => {
                    const isSelected = item.student_selection.map(String).includes(String(option.id));
                    const isCorrect  = option.is_correct == 1;

                    let bg = '#fff', border = '#dee2e6', icon = '';
                    if (isCorrect && isSelected)  { bg = '#d4edda'; border = '#28a745'; icon = '✓ '; }
                    else if (isCorrect)            { bg = '#d4edda'; border = '#28a745'; icon = '✓ '; }
                    else if (isSelected && !isCorrect) { bg = '#f8d7da'; border = '#dc3545'; icon = '✗ '; }

                    answersHtml += `<div style="padding:10px 14px; margin-bottom:6px; border-radius:6px; border:1px solid ${border}; background:${bg}; font-size:14px;">
                        <span style="font-weight:bold;">${icon}</span>${option.option_text}
                        ${isCorrect ? '<span style="float:right; font-size:12px; color:#28a745; font-weight:bold;">Correct Answer</span>' : ''}
                        ${isSelected && !isCorrect ? '<span style="float:right; font-size:12px; color:#dc3545; font-weight:bold;">Your Answer</span>' : ''}
                        ${isSelected && isCorrect ? '<span style="float:right; font-size:12px; color:#28a745; font-weight:bold;">Your Answer ✓</span>' : ''}
                    </div>`;
                });
            }

            const card = document.createElement('div');
            card.style = 'background:#fff; border:1px solid #e0e0e0; border-radius:10px; margin-bottom:20px; overflow:hidden; box-shadow:0 2px 6px rgba(0,0,0,0.05);';
            card.innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 20px; border-bottom:1px solid #f0f0f0; background:#fafafa;">
                    <span style="font-weight:700; font-size:15px; color:#333;">Q${displayIndex}. ${item.question_text}</span>
                    <div style="display:flex; align-items:center; gap:12px; flex-shrink:0; margin-left:16px;">
                        <span style="font-size:13px; font-weight:bold; padding:4px 12px; border-radius:20px; background:${verdict.color}; color:#fff; white-space:nowrap;">${verdict.label}</span>
                        <span style="font-size:13px; color:#555; white-space:nowrap;">Marks: ${marksHtml}</span>
                    </div>
                </div>
                <div style="padding:16px 20px;">
                    ${answersHtml}
                </div>
            `;
            breakdownContainer.appendChild(card);
            displayIndex++;
        });

        if (hasDescriptive) {
            const msg = document.createElement('div');
            msg.style = 'background-color:#e2f3f5; color:#0c5460; padding:15px; border-radius:5px; margin-bottom:20px; border:1px solid #b8daff; text-align:center;';
            msg.innerHTML = '<strong>Note:</strong> Descriptive questions are pending evaluation and will appear here once published.';
            breakdownContainer.prepend(msg);
        }

    } catch (error) {
        breakdownContainer.innerHTML = `<p style="color:red;">Error: ${error.message}</p>`;
    }
});
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>
