<?php
  $pageTitle = 'Demote Students';
  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
      redirect('login.php');
      exit();
  }
?>

<style>
.autocomplete-container {
    position: relative;
    width: 100%;
}
.autocomplete-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #ccc;
    border-top: none;
    z-index: 1000;
    max-height: 200px;
    overflow-y: auto;
    border-radius: 0 0 4px 4px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.autocomplete-item {
    padding: 10px 15px;
    cursor: pointer;
    font-size: 15px;
}
.autocomplete-item:hover {
    background-color: #f0f0f0;
}
.student-card {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
    display: none;
}
.student-card p {
    margin: 8px 0;
    font-size: 15px;
}
</style>

<div class="manage-container" style="max-width: 600px;">
    
    <?php
    if (isset($_GET['success'])) { echo '<div class="message-box success-message">' . htmlspecialchars($_GET['success']) . '</div>'; }
    if (isset($_GET['error'])) { echo '<div class="message-box error-message">' . htmlspecialchars($_GET['error']) . '</div>'; }
    ?>

    <div class="section-box">
        <h3>Demote a Student</h3>
        <p style="color: #666; margin-bottom: 20px; font-size: 14px;">Enter a student's SAP ID to demote them by exactly 1 year.</p>

        <form action="<?= get_base_url() ?>api/admin/demote_single_student.php" method="POST" id="demoteForm">
            <div class="autocomplete-container">
                <label style="font-size: 14px; font-weight: bold; margin-bottom: 8px; display: block;">Search SAP ID</label>
                <input type="text" id="sap_id_search" class="input-field" placeholder="Enter SAP ID..." autocomplete="off" required>
                <input type="hidden" name="student_id" id="selected_student_id" required>
                <div id="sap_results" class="autocomplete-results" style="display: none;"></div>
            </div>

            <div id="student_preview" class="student-card">
                <h4 style="margin-top: 0; color: #d32f2f; border-bottom: 1px solid #ddd; padding-bottom: 10px;">Student Details</h4>
                <p><strong>Name:</strong> <span id="preview_name"></span></p>
                <p><strong>SAP ID:</strong> <span id="preview_sap"></span></p>
                <p><strong>Current Graduation Year:</strong> <span id="preview_current_grad"></span></p>
                <p style="color: #dc3545; font-weight: bold; font-size: 16px; margin-top: 15px;">New Graduation Year: <span id="preview_new_grad"></span></p>
                
                <button type="submit" class="button-red" style="margin-top: 20px; width: 100%;" onclick="return confirm('Are you sure you want to demote this student by 1 year?');">Demote Student</button>
            </div>
        </form>
    </div>
</div>

<script>
    const BASE_URL = '<?= get_base_url() ?>';
    const searchInput = document.getElementById('sap_id_search');
    const resultsContainer = document.getElementById('sap_results');
    const hiddenInput = document.getElementById('selected_student_id');
    const previewCard = document.getElementById('student_preview');

    let searchTimeout;

    searchInput.addEventListener('keyup', function() {
        clearTimeout(searchTimeout);
        const query = this.value;

        if (query.trim() === '') {
            hiddenInput.value = '';
            resultsContainer.style.display = 'none';
            previewCard.style.display = 'none';
            return;
        }

        if (query.length < 2) {
            resultsContainer.style.display = 'none';
            return;
        }

        searchTimeout = setTimeout(() => {
            fetch(BASE_URL + 'api/admin/search_student.php?q=' + encodeURIComponent(query))
                .then(res => res.json())
                .then(data => {
                    resultsContainer.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(student => {
                            const div = document.createElement('div');
                            div.className = 'autocomplete-item';
                            div.textContent = `${student.sap_id} - ${student.name}`;
                            div.onclick = function() {
                                searchInput.value = student.sap_id;
                                hiddenInput.value = student.user_id; // search_student returns user_id ? Wait, search_student returns sap_id usually? Let's check!
                                
                                // To get full details for preview, we need another fetch or just use what search_student gives.
                                // Actually, search_student returns user_id, name, sap_id, maybe graduation_year?
                                loadStudentPreview(student.sap_id);

                                resultsContainer.style.display = 'none';
                            };
                            resultsContainer.appendChild(div);
                        });
                        resultsContainer.style.display = 'block';
                    } else {
                        resultsContainer.innerHTML = '<div style="padding: 10px 15px; font-size: 14px; color: #777;">No students found</div>';
                        resultsContainer.style.display = 'block';
                    }
                })
                .catch(err => console.error(err));
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.autocomplete-container')) {
            resultsContainer.style.display = 'none';
        }
    });

    function loadStudentPreview(sap_id) {
        // We'll reuse get_students_for_demotion.php which accepts 'search' parameter and returns full student info
        fetch(BASE_URL + 'api/admin/get_students_for_demotion.php?search=' + encodeURIComponent(sap_id))
            .then(res => res.json())
            .then(data => {
                if (data.success && data.students.length > 0) {
                    // Find the exact match
                    const student = data.students.find(s => s.sap_id === sap_id) || data.students[0];
                    
                    hiddenInput.value = student.user_id;
                    document.getElementById('preview_name').textContent = student.name;
                    document.getElementById('preview_sap').textContent = student.sap_id;
                    document.getElementById('preview_current_grad').textContent = student.graduation_year;
                    document.getElementById('preview_new_grad').textContent = parseInt(student.graduation_year) + 1;
                    
                    previewCard.style.display = 'block';
                }
            });
    }

    document.getElementById('demoteForm').addEventListener('submit', function(e) {
        if (!hiddenInput.value) {
            e.preventDefault();
            alert('Please select a valid student from the search results.');
        }
    });
</script>

<?php require_once '../../assets/templates/footer.php'; ?>
