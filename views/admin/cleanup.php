<?php
  $pageTitle = 'Data Cleanup';
  require_once '../../assets/templates/header.php';

  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
      redirect('login.php');
      exit();
  }
?>

<div class="manage-container" style="max-width: 800px;">
    <h2 style="margin-bottom: 5px;">Data Cleanup</h2>
    
    <div style="text-align: center; color: #555; margin-bottom: 20px;">
        <p style="margin: 5px 0;">This tool permanently removes data from batches that graduated 3 or more years ago. It will safely delete:</p>
        <p style="margin: 5px 0;"><strong>Old Students</strong> &bull; <strong>Inactive Classes & Batches</strong> &bull; <strong>Expired Quizzes</strong> &bull; <strong>Past Quiz Attempts</strong></p>
        <p style="margin: 5px 0; font-size: 0.9em; color: #777;"><em>Active data, faculty accounts, and global structures are never deleted.</em></p>
    </div>

    <hr style="border: 0; border-top: 1px solid #e9ecef; margin: 0 0 20px 0;">

    <div id="cleanup-preview" style="text-align: center; padding-bottom: 20px;">
        <p>Loading preview...</p>
    </div>

    <div id="cleanup-actions" style="text-align: center; display: none;">
        <hr style="border: 0; border-top: 1px solid #e9ecef; margin: 0 0 20px 0;">
        <p style="color: #dc3545; font-weight: bold; margin-bottom: 20px;">Warning: This action is irreversible. All listed data will be permanently deleted.</p>
        <button id="btn-confirm-delete" class="button-red" style="padding: 10px 30px; font-size: 1.1em;">Delete 3+ Years Old Data</button>
        <a href="dashboard.php" class="button-secondary" style="padding: 10px 30px; font-size: 1.1em; text-decoration: none; margin-left: 10px;">Cancel</a>
    </div>
</div>

<script>
const BASE_URL = '<?= get_base_url() ?>';

document.addEventListener('DOMContentLoaded', async function() {
    const previewContainer = document.getElementById('cleanup-preview');
    const actionsContainer = document.getElementById('cleanup-actions');
    const confirmBtn = document.getElementById('btn-confirm-delete');

    try {
        const response = await fetch(BASE_URL + 'api/admin/cleanup_preview.php');
        if (!response.ok) throw new Error('Failed to fetch preview data.');
        const data = await response.json();

        if (data.success) {
            const counts = data.counts;
            const total = counts.students + counts.classes + counts.batches + counts.quizzes + counts.attempts;

            if (total === 0) {
                previewContainer.innerHTML = `<p>No old data found that meets the retention criteria (Graduation year <= ${data.retentionYearThreshold}).</p>`;
            } else {
                let html = `<p style="margin-bottom: 20px; font-size: 1.1em;">The following records are older than the retention period and will be deleted:</p>`;
                html += `<div style="display: flex; justify-content: center; gap: 20px; flex-wrap: wrap;">`;
                html += `<div class="section-box" style="width: 150px;"><h3>${counts.students}</h3><p>Students</p></div>`;
                html += `<div class="section-box" style="width: 150px;"><h3>${counts.classes}</h3><p>Classes</p></div>`;
                html += `<div class="section-box" style="width: 150px;"><h3>${counts.batches}</h3><p>Batches</p></div>`;
                html += `<div class="section-box" style="width: 150px;"><h3>${counts.quizzes}</h3><p>Quizzes</p></div>`;
                html += `<div class="section-box" style="width: 150px;"><h3>${counts.attempts}</h3><p>Quiz Attempts</p></div>`;
                html += `</div>`;
                
                previewContainer.innerHTML = html;
                actionsContainer.style.display = 'block';
            }
        } else {
            previewContainer.innerHTML = `<p style="color: red;">Error: ${data.error}</p>`;
        }
    } catch (error) {
        console.error("Error loading preview:", error);
        previewContainer.innerHTML = `<p style="color: red;">Failed to load preview data.</p>`;
    }

    confirmBtn.addEventListener('click', async function() {
        if (!confirm('Are you absolutely sure you want to permanently delete data from 3+ years ago?')) {
            return;
        }
        
        confirmBtn.disabled = true;
        confirmBtn.innerText = 'Deleting...';

        try {
            const response = await fetch(BASE_URL + 'api/admin/execute_cleanup.php', {
                method: 'POST'
            });
            const data = await response.json();

            if (data.success) {
                alert('Data deleted successfully.');
                window.location.reload();
            } else {
                alert('Error: ' + data.error);
                confirmBtn.disabled = false;
                confirmBtn.innerText = 'Confirm Deletion';
            }
        } catch (error) {
            console.error("Error executing cleanup:", error);
            alert('Failed to delete data. Check console for details.');
            confirmBtn.disabled = false;
            confirmBtn.innerText = 'Confirm Deletion';
        }
    });
});
</script>

<?php require_once '../../assets/templates/footer.php'; ?>
