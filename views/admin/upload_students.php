<?php
  $pageTitle = 'Upload Students';
  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  // --- Authorization Check ---
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
      redirect('login.php');
      exit();
  }
?>


<div class="upload-container">
    <div class="upload-card">
        <h2>Bulk Upload Students</h2>
        <p class="upload-subtitle">Quickly onboard multiple students by uploading an Excel file.</p>

        <?php
        if (isset($_GET['success'])) { echo '<div class="message-box success-message">✅ ' . htmlspecialchars($_GET['message']) . '</div>'; }
        if (isset($_GET['error'])) { echo '<div class="message-box error-message">❌ ' . htmlspecialchars($_GET['message']) . '</div>'; }
        
        if (isset($_SESSION['upload_errors']) && !empty($_SESSION['upload_errors'])) {
            echo '<div class="message-box warning-message"><h4>⚠️ Upload Warnings:</h4><ul>';
            foreach ($_SESSION['upload_errors'] as $error) {
                echo '<li>' . htmlspecialchars($error) . '</li>';
            }
            echo '</ul></div>';
            unset($_SESSION['upload_errors']); // Clear errors after displaying
        }
        ?>

        <div class="format-guide">
            <h4>📋 Required Excel Format</h4>
            <p style="margin-bottom: 8px;">The file <strong>must</strong> be an `.xlsx` file and contain the following headers in this exact order:</p>
            <p style="text-align: center; margin: 15px 0;"><code>Name, Email, SAP ID</code></p>
            <p style="margin-top: 8px; font-size: 13px;">
                <strong>Note:</strong> Passwords, Schools, Courses, and Batches are automatically generated based on the student's 11-digit SAP ID. Emails must end with <code>nmims.in</code>.
            </p>
        </div>

        <form action="<?= get_base_url() ?>api/admin/upload_students.php" method="POST" enctype="multipart/form-data">
            <div class="file-drop-area" id="drop-area">
                <div class="file-drop-icon">📄</div>
                <div class="file-drop-text">Drag & Drop your Excel file here</div>
                <div class="file-drop-subtext">or click to browse from your computer</div>
                <input type="file" id="student_file" name="student_file" class="file-input" accept=".xlsx, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                <div class="file-name-display" id="file-name"></div>
            </div>
            
            <button type="submit" class="btn-upload">Upload and Create Students</button>
            
            <a href="<?= get_asset_url('assets/templates/student_template.xlsx') ?>" class="template-link" download>
                📥 Download Excel Template
            </a>
        </form>
    </div>
</div>

<script>
    const fileInput = document.getElementById('student_file');
    const dropArea = document.getElementById('drop-area');
    const fileNameDisplay = document.getElementById('file-name');
    const dropText = document.querySelector('.file-drop-text');
    const dropSubtext = document.querySelector('.file-drop-subtext');

    fileInput.addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            fileNameDisplay.innerHTML = `✓ Selected: <span>${this.files[0].name}</span>`;
            fileNameDisplay.style.display = 'flex';
            dropText.style.display = 'none';
            dropSubtext.style.display = 'none';
            dropArea.style.borderColor = '#10b981';
            dropArea.style.backgroundColor = '#ecfdf5';
        } else {
            fileNameDisplay.style.display = 'none';
            dropText.style.display = 'block';
            dropSubtext.style.display = 'block';
            dropArea.style.borderColor = '#d1d5db';
            dropArea.style.backgroundColor = '#f9fafb';
        }
    });

    // Drag and drop visual cues
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults (e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.addEventListener(eventName, () => dropArea.classList.add('dragover'), false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, () => dropArea.classList.remove('dragover'), false);
    });
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>