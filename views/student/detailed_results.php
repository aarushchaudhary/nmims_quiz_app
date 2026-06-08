<?php
  $pageTitle = 'Detailed Results';
  require_once '../../assets/templates/header.php';

  $attempt_id = isset($_GET['attempt_id']) ? filter_var($_GET['attempt_id'], FILTER_VALIDATE_INT) : null;
?>

<div class="manage-container detailed-results-container">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h2>Question Breakdown</h2>
        <a href="results.php?attempt_id=<?php echo htmlspecialchars($attempt_id); ?>">&larr; Back to Summary</a>
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

        if (results.length === 0) {
            breakdownContainer.innerHTML = '<p>No answered questions found for this attempt.</p>';
            return;
        }

        results.forEach((item, index) => {
            const card = document.createElement('div');
            card.className = 'question-review-card';
            
            let optionsHtml = '';
            if (item.question_type_id == 3) {
                optionsHtml = `<div style="background:#f9f9f9; padding: 10px; border: 1px solid #ccc; border-radius: 4px; margin-top: 10px; white-space: pre-wrap;"><strong>Your Answer:</strong><br/>${item.answer_text ? item.answer_text : '<em>No answer provided</em>'}</div>`;
            } else {
                item.options.forEach(option => {
                    const isSelected = item.student_selection.includes(option.id.toString());
                    const isCorrect = option.is_correct == 1;
                    
                    let classes = 'review-option';
                    if (isCorrect) {
                        classes += ' correct-answer';
                    }
                    if (isSelected) {
                        classes += ' selected';
                        if (!isCorrect) {
                            classes += ' incorrect-answer';
                        }
                    }
                    optionsHtml += `<div class="${classes}">${option.option_text}</div>`;
                });
            }

            card.innerHTML = `
                <h4>Question ${index + 1}: ${item.question_text}</h4>
                <div>${optionsHtml}</div>
            `;
            breakdownContainer.appendChild(card);
        });

    } catch (error) {
        breakdownContainer.innerHTML = `<p style="color:red;">Error: ${error.message}</p>`;
    }
});
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>
