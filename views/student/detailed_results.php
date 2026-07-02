<?php
  $pageTitle = 'Detailed Results';
  require_once '../../assets/templates/header.php';

  $attempt_id = isset($_GET['attempt_id']) ? filter_var($_GET['attempt_id'], FILTER_VALIDATE_INT) : null;

  require_once '../../config/database.php';
  $stmt = $pdo->prepare("SELECT q.descriptive_published FROM student_attempts sa JOIN quizzes q ON sa.quiz_id = q.id WHERE sa.id = ?");
  $stmt->execute([$attempt_id]);
  $descriptive_published = $stmt->fetchColumn() ? true : false;
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

        if (results.length === 0) {
            breakdownContainer.innerHTML = '<p>No answered questions found for this attempt.</p>';
            return;
        }

        const descriptivePublished = <?php echo json_encode($descriptive_published); ?>;

        let hasDescriptive = false;
        let displayIndex = 1;

        results.forEach((item, index) => {
            if (item.question_type_id == 3 && !descriptivePublished) {
                hasDescriptive = true;
                return; // Skip displaying descriptive questions
            }

            const card = document.createElement('div');
            card.className = 'question-review-card';
            
            let optionsHtml = '';
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

            card.innerHTML = `
                <h4>Question ${displayIndex}: ${item.question_text}</h4>
                <div>${optionsHtml}</div>
            `;
            breakdownContainer.appendChild(card);
            displayIndex++;
        });

        if (hasDescriptive) {
            const msg = document.createElement('div');
            msg.style = 'background-color: #e2f3f5; color: #0c5460; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #b8daff; text-align: center;';
            msg.innerHTML = '<strong>Note:</strong> Descriptive questions have been omitted. The rest of the questions will be evaluated and the result will be released later.';
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
