<?php
  $pageTitle = 'View & Manage Questions';
  
  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  // --- Authorization & Input Check ---
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2 || !isset($_GET['quiz_id'])) {
      redirect('login.php');
      exit();
  }
  $quiz_id = filter_var($_GET['quiz_id'], FILTER_VALIDATE_INT);
  
  // Fetch questions for this quiz
  $questions_sql = "SELECT q.id, q.question_text, qt.name as type_name, qd.level as difficulty_level FROM questions q JOIN question_types qt ON q.question_type_id = qt.id JOIN question_difficulties qd ON q.difficulty_id = qd.id WHERE quiz_id = :quiz_id ORDER BY q.id DESC";
  $questions_stmt = $pdo->prepare($questions_sql);
  $questions_stmt->execute([':quiz_id' => $quiz_id]);
  $questions = $questions_stmt->fetchAll();
?>

<!-- Confirmation Modal for Deletion -->
<div class="confirm-modal-overlay" id="delete-confirm-modal">
    <div class="confirm-modal">
        <h3>Confirm Deletion</h3>
        <p>Are you sure you want to permanently delete this question and all its options? This cannot be undone.</p>
        <div class="button-group">
            <button class="btn-cancel" id="cancel-delete-btn">Cancel</button>
            <button class="btn-confirm-delete" id="confirm-delete-btn">Delete Question</button>
        </div>
    </div>
</div>

<div class="manage-container">
    <a href="view_quiz.php?id=<?php echo $quiz_id; ?>" style="text-decoration: none; color: #007bff; margin-bottom: 20px; display: inline-block;">&larr; Back to Add Questions</a>
    <h2>Existing Questions (<span id="question-count"><?php echo count($questions); ?></span>)</h2>

    <table class="data-table">
        <thead>
            <tr>
                <th>Question Text</th>
                <th>Type</th>
                <th>Difficulty</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="questions-table-body">
            <?php if (empty($questions)): ?>
                <tr id="no-questions-row"><td colspan="4" style="text-align:center;">No questions have been added yet.</td></tr>
            <?php else: ?>
                <?php foreach($questions as $q): ?>
                <tr id="question-row-<?php echo $q['id']; ?>">
                    <td><?php echo substr(htmlspecialchars($q['question_text']), 0, 70) . (strlen($q['question_text']) > 70 ? '...' : ''); ?></td>
                    <td><?php echo htmlspecialchars($q['type_name']); ?></td>
                    <td><?php echo htmlspecialchars($q['difficulty_level']); ?></td>
                    <td class="action-buttons" style="flex-direction: row;">
                        <a href="edit_question.php?id=<?php echo $q['id']; ?>" class="btn-edit" style="padding: 5px 10px; font-size: 12px;">Edit</a>
                        <button class="btn-delete-question" data-question-id="<?php echo $q['id']; ?>" style="background-color:#dc3545; color:white; border:none; padding: 5px 10px; font-size: 12px; border-radius: 6px; cursor:pointer;">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
const BASE_URL = '<?= get_base_url() ?>';
// This script is identical to the previous version and handles the delete confirmation
document.addEventListener('DOMContentLoaded', function() {
    const deleteModal = document.getElementById('delete-confirm-modal');
    const cancelDeleteBtn = document.getElementById('cancel-delete-btn');
    const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
    const questionsTableBody = document.getElementById('questions-table-body');
    const questionCountSpan = document.getElementById('question-count');
    let questionIdToDelete = null;

    questionsTableBody.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('btn-delete-question')) {
            questionIdToDelete = e.target.dataset.questionId;
            deleteModal.style.display = 'flex';
        }
    });

    cancelDeleteBtn.addEventListener('click', () => {
        deleteModal.style.display = 'none';
        questionIdToDelete = null;
    });

    confirmDeleteBtn.addEventListener('click', async () => {
        if (questionIdToDelete) {
            try {
                const response = await fetch(BASE_URL + 'api/faculty/delete_question.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ question_id: questionIdToDelete })
                });
                const result = await response.json();
                if (result.success) {
                    document.getElementById(`question-row-${questionIdToDelete}`).remove();
                    questionCountSpan.textContent = parseInt(questionCountSpan.textContent) - 1;
                } else {
                    throw new Error(result.error || 'Failed to delete question.');
                }
            } catch (error) {
                alert(`Error: ${error.message}`);
            } finally {
                deleteModal.style.display = 'none';
                questionIdToDelete = null;
            }
        }
    });
});
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>
