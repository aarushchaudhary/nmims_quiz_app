<?php
  $pageTitle = 'Demote Students';
  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
      redirect('login.php');
      exit();
  }

  // Fetch schools and courses for the dropdown filters
  $schools = $pdo->query("SELECT id, name FROM schools ORDER BY name ASC")->fetchAll();
  $courses = $pdo->query("SELECT id, school_id, name FROM courses ORDER BY name ASC")->fetchAll();
?>

<style>
.demote-container {
    display: flex;
    gap: 20px;
    margin-top: 20px;
}

.table-section {
    flex: 2;
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.side-panel {
    flex: 1;
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    max-height: 80vh;
    overflow-y: auto;
}

.filter-row {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.filter-row select, .filter-row input {
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 4px;
}

.student-table {
    width: 100%;
    border-collapse: collapse;
}

.student-table th, .student-table td {
    padding: 10px;
    border-bottom: 1px solid #ddd;
    text-align: left;
}

.selected-item {
    background: #fff;
    padding: 10px;
    margin-bottom: 10px;
    border-left: 4px solid #dc3545;
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.modal {
    display: none;
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    width: 600px;
    max-height: 80vh;
    overflow-y: auto;
}
</style>

<div class="manage-container" style="max-width: 1200px;">
    <h2>Demote Students</h2>
    <p style="color: #666; margin-bottom: 20px;">Select students to demote them by exactly 1 year. Their SAP IDs will remain identical.</p>

    <div class="filter-row">
        <select id="filter-school">
            <option value="">All Schools</option>
            <?php foreach($schools as $s): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
        </select>
        
        <select id="filter-course">
            <option value="">All Courses</option>
            <?php foreach($courses as $c): ?>
                <option value="<?= $c['id'] ?>" data-school="<?= $c['school_id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <input type="text" id="filter-search" placeholder="Search Name or SAP ID..." style="flex: 1;">
        <button id="btn-search" class="button-red" style="width: auto;">Search</button>
    </div>

    <div class="demote-container">
        <!-- Main Table Section -->
        <div class="table-section">
            <table class="student-table">
                <thead>
                    <tr>
                        <th style="width: 40px;"><input type="checkbox" id="check-all"></th>
                        <th>Name</th>
                        <th>SAP ID</th>
                        <th>Course</th>
                        <th>Batch / Year</th>
                    </tr>
                </thead>
                <tbody id="student-list">
                    <tr><td colspan="5" style="text-align: center;">Loading students...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Side Panel for Selected Students -->
        <div class="side-panel">
            <h3 style="margin-top: 0; border-bottom: 2px solid #ddd; padding-bottom: 10px;">Selected (<span id="selected-count">0</span>)</h3>
            <div id="selected-list" style="margin-bottom: 20px; min-height: 100px;">
                <p style="color: #888; text-align: center;">No students selected.</p>
            </div>
            <button id="btn-finalize" class="button-red" style="width: 100%; display: none;">Finalize List</button>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirm-modal" class="modal">
    <div class="modal-content">
        <h2>Confirm Demotion</h2>
        <p style="color: #dc3545; margin-bottom: 15px;">You are about to demote the following students by 1 year. Please cross-check the list before proceeding.</p>
        
        <div id="modal-student-list" style="max-height: 400px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; margin-bottom: 20px;">
            <!-- Populated via JS -->
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px;">
            <button id="btn-modal-cancel" class="button-secondary" style="width: auto;">Cancel</button>
            <button id="btn-modal-confirm" class="button-red" style="width: auto;">Confirm & Demote</button>
        </div>
    </div>
</div>

<script>
const BASE_URL = '<?= get_base_url() ?>';
let studentsData = [];
let selectedStudents = new Map(); // Maps user_id to student object

document.addEventListener('DOMContentLoaded', function() {
    const filterSchool = document.getElementById('filter-school');
    const filterCourse = document.getElementById('filter-course');
    const filterSearch = document.getElementById('filter-search');
    const btnSearch = document.getElementById('btn-search');
    
    // Dynamic course dropdown based on school selection
    filterSchool.addEventListener('change', function() {
        const schoolId = this.value;
        Array.from(filterCourse.options).forEach(opt => {
            if (opt.value === "") return;
            opt.style.display = (schoolId === "" || opt.dataset.school === schoolId) ? 'block' : 'none';
        });
        filterCourse.value = "";
    });

    function loadStudents() {
        const schoolId = filterSchool.value;
        const courseId = filterCourse.value;
        const search = filterSearch.value;

        fetch(`${BASE_URL}api/admin/get_students_for_demotion.php?school_id=${schoolId}&course_id=${courseId}&search=${encodeURIComponent(search)}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    studentsData = data.students;
                    renderTable();
                }
            });
    }

    function renderTable() {
        const tbody = document.getElementById('student-list');
        tbody.innerHTML = '';
        
        if (studentsData.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center;">No students found.</td></tr>';
            return;
        }

        studentsData.forEach(s => {
            const tr = document.createElement('tr');
            const isChecked = selectedStudents.has(s.user_id) ? 'checked' : '';
            tr.innerHTML = `
                <td><input type="checkbox" class="student-checkbox" value="${s.user_id}" ${isChecked}></td>
                <td>${s.name}</td>
                <td>${s.sap_id}</td>
                <td>${s.course_name}</td>
                <td>${s.batch} (Grad: ${s.graduation_year})</td>
            `;
            tbody.appendChild(tr);
        });

        // Attach event listeners to checkboxes
        document.querySelectorAll('.student-checkbox').forEach(cb => {
            cb.addEventListener('change', function() {
                const id = this.value;
                if (this.checked) {
                    const student = studentsData.find(st => st.user_id == id);
                    selectedStudents.set(id, student);
                } else {
                    selectedStudents.delete(id);
                }
                renderSidePanel();
            });
        });
        
        document.getElementById('check-all').checked = false;
    }

    document.getElementById('check-all').addEventListener('change', function() {
        const isChecked = this.checked;
        document.querySelectorAll('.student-checkbox').forEach(cb => {
            cb.checked = isChecked;
            const id = cb.value;
            if (isChecked) {
                const student = studentsData.find(st => st.user_id == id);
                selectedStudents.set(id, student);
            } else {
                selectedStudents.delete(id);
            }
        });
        renderSidePanel();
    });

    btnSearch.addEventListener('click', loadStudents);

    function renderSidePanel() {
        const listDiv = document.getElementById('selected-list');
        const countSpan = document.getElementById('selected-count');
        const finalizeBtn = document.getElementById('btn-finalize');
        
        countSpan.innerText = selectedStudents.size;
        
        if (selectedStudents.size === 0) {
            listDiv.innerHTML = '<p style="color: #888; text-align: center;">No students selected.</p>';
            finalizeBtn.style.display = 'none';
            return;
        }

        listDiv.innerHTML = '';
        selectedStudents.forEach(s => {
            const nextGradYear = parseInt(s.graduation_year) + 1;
            const div = document.createElement('div');
            div.className = 'selected-item';
            div.innerHTML = `
                <strong>${s.name}</strong> (${s.sap_id})<br>
                <small style="color: #555;">Current: ${s.batch} (Grad ${s.graduation_year})</small><br>
                <small style="color: #dc3545;">New Grad: ${nextGradYear}</small>
            `;
            listDiv.appendChild(div);
        });
        finalizeBtn.style.display = 'block';
    }

    // Modal Logic
    const modal = document.getElementById('confirm-modal');
    
    document.getElementById('btn-finalize').addEventListener('click', function() {
        const modalList = document.getElementById('modal-student-list');
        modalList.innerHTML = '';
        
        const table = document.createElement('table');
        table.className = 'student-table';
        table.innerHTML = `<thead><tr><th>SAP ID</th><th>Name</th><th>Current Grad</th><th>New Grad</th></tr></thead><tbody></tbody>`;
        
        selectedStudents.forEach(s => {
            const tr = document.createElement('tr');
            const nextGradYear = parseInt(s.graduation_year) + 1;
            tr.innerHTML = `
                <td>${s.sap_id}</td>
                <td>${s.name}</td>
                <td>${s.graduation_year}</td>
                <td style="color:#dc3545; font-weight:bold;">${nextGradYear}</td>
            `;
            table.querySelector('tbody').appendChild(tr);
        });
        
        modalList.appendChild(table);
        modal.style.display = 'flex';
    });

    document.getElementById('btn-modal-cancel').addEventListener('click', function() {
        modal.style.display = 'none';
    });

    document.getElementById('btn-modal-confirm').addEventListener('click', function() {
        const studentIds = Array.from(selectedStudents.keys());
        const btn = this;
        btn.disabled = true;
        btn.innerText = 'Processing...';

        fetch(BASE_URL + 'api/admin/demote_students.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ student_ids: studentIds })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                modal.style.display = 'none';
                selectedStudents.clear();
                renderSidePanel();
                loadStudents(); // reload list
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(err => {
            console.error(err);
            alert('An error occurred.');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerText = 'Confirm & Demote';
        });
    });

    // Initial load
    loadStudents();
});
</script>

<?php require_once '../../assets/templates/footer.php'; ?>
