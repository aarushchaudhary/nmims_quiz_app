<?php
  $pageTitle = 'Add New User';

  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  // --- Authorization Check ---
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
      redirect('login.php');
      exit();
  }

  // --- Fetch initial data for the form's dropdown menus ---
  // FIXED: Changed 'role_name' to 'name' to match your database schema
  $roles = $pdo->query("SELECT id, name FROM roles WHERE name NOT IN ('Admin', 'Super Admin')")->fetchAll();
  $schools = $pdo->query("SELECT id, name FROM schools ORDER BY name ASC")->fetchAll();
  
  $courses_data = $pdo->query("SELECT c.code, c.name as course_name, c.duration_years, s.name as school_name 
                               FROM courses c 
                               JOIN schools s ON c.school_id = s.id")->fetchAll(PDO::FETCH_ASSOC);
?>



<div class="form-container" style="max-width: 800px;">
    <h2>Create New User Account</h2>
    <form action="<?= get_base_url() ?>api/admin/add_user.php" method="POST" id="add-user-form">
        
        <div class="form-group">
            <label for="role_id">User Role</label>
            <select id="role_id" name="role_id" required>
                <option value="" disabled selected>-- Select a Role --</option>
                <?php foreach ($roles as $role): ?>
                
                <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars(ucfirst($role['name'])); ?></option>
                
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group"><label for="full_name">Full Name</label><input type="text" id="full_name" name="full_name" required></div>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="example@nmims.edu" required>
            </div>
        </div>
        <div class="form-group" id="password-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <hr>

        <div id="role-specific-fields">
            <div class="student-fields" style="display:none;">
                <h4>Student Details</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>SAP ID (11 Digits)</label>
                        <input type="text" name="sap_id" id="sap_id" minlength="11" maxlength="11" pattern="\d{11}" title="Must be exactly 11 digits">
                        <div id="sap-preview" style="margin-top: 8px; font-size: 0.9em; display: none;"></div>
                        <small style="color: #666; font-size: 0.8em; margin-top: 5px; display: block;">Note: SAP ID will be used as the default password. School, Course, and Batch will be assigned automatically based on SAP ID.</small>
                    </div>
                </div>
            </div>

            <div class="staff-fields" style="display:none;">
                <h4>Staff Details</h4>
                
                <div class="form-row" id="visiting-faculty-group" style="display:none;">
                    <div class="form-group" style="display: flex; flex-direction: row; align-items: center; gap: 10px;">
                        <input type="checkbox" id="is_visiting" name="is_visiting" style="width: auto; height: 18px;">
                        <label for="is_visiting" style="margin: 0;">Visiting Faculty (Allows non-NMIMS email)</label>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" id="staff-sap-id-group"><label>SAP ID</label><input type="text" id="staff_sap_id" name="staff_sap_id"></div>
                    <div class="form-group" id="staff-school-group"><label>School / Department</label><select id="staff_school_id" name="staff_school_id"><option value="">-- Select --</option><?php foreach($schools as $school): ?><option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option><?php endforeach; ?></select></div>
                </div>
            </div>
        </div>

        <div class="form-group" style="text-align: center; margin-top: 30px;">
            <button type="submit" class="button-red" style="width: auto; padding: 12px 40px;">Create User</button>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
const BASE_URL = '<?= get_base_url() ?>';
const coursesData = <?php echo json_encode($courses_data); ?>;
const coursesMap = {};
coursesData.forEach(c => {
    coursesMap[c.code] = c;
});

document.addEventListener('DOMContentLoaded', function() {

    const sapInput = document.getElementById('sap_id');
    const sapPreview = document.getElementById('sap-preview');
    if (sapInput) {
        sapInput.addEventListener('input', function() {
            const val = this.value.trim();
            if (val.length >= 8) {
                const courseCode = val.substring(0, 4);
                const yearStr = val.substring(4, 8);
                const startYear = 2000 + parseInt(yearStr.substring(0, 2), 10);
                
                const course = coursesMap[courseCode];
                if (course) {
                    const endYear = startYear + parseInt(course.duration_years, 10);
                    sapPreview.innerHTML = `<strong>School:</strong> ${course.school_name} <br> <strong>Course:</strong> ${course.course_name} <br> <strong>Batch:</strong> ${startYear}-${endYear}`;
                    sapPreview.style.color = '#28a745';
                    sapPreview.style.display = 'block';
                } else {
                    sapPreview.innerHTML = `Invalid Course Code (${courseCode})`;
                    sapPreview.style.color = '#dc3545';
                    sapPreview.style.display = 'block';
                }
            } else {
                sapPreview.style.display = 'none';
            }
        });
    }

    const roleSelect = document.getElementById('role_id');
    const studentFields = document.querySelector('.student-fields');
    const staffFields = document.querySelector('.staff-fields');
    
    roleSelect.addEventListener('change', function() {
        const roleText = this.options[this.selectedIndex].text.toLowerCase();
        
        studentFields.style.display = 'none';
        staffFields.style.display = 'none';

        if (roleText === 'student') {
            studentFields.style.display = 'block';
            document.getElementById('password-group').style.display = 'none';
            document.getElementById('password').removeAttribute('required');
            document.getElementById('sap_id').setAttribute('required', 'required');
            document.getElementById('email').setAttribute('pattern', '.*nmims\\.in$');
            document.getElementById('email').setAttribute('title', 'Student email must end with nmims.in');
        } else {
            document.getElementById('password-group').style.display = 'block';
            document.getElementById('password').setAttribute('required', 'required');
            document.getElementById('sap_id').removeAttribute('required');
            document.getElementById('email').removeAttribute('pattern');
            document.getElementById('email').removeAttribute('title');
            
            if (roleText === 'faculty' || roleText.includes('head')) { 
                staffFields.style.display = 'block';
                
                // Toggle SAP ID and Visiting Checkbox
                if (roleText.includes('head')) {
                    document.getElementById('staff-sap-id-group').style.display = 'none';
                    document.getElementById('visiting-faculty-group').style.display = 'none';
                    document.getElementById('staff-school-group').style.display = 'flex';
                } else {
                    document.getElementById('staff-sap-id-group').style.display = 'flex';
                    document.getElementById('staff-school-group').style.display = 'flex';
                    document.getElementById('visiting-faculty-group').style.display = 'flex';
                }
            }
        }
    });

    const schoolSelect = document.getElementById('student_school_id');
    const courseSelect = document.getElementById('course_id');

    schoolSelect.addEventListener('change', async function() {
        const schoolId = this.value;
        courseSelect.innerHTML = '<option value="">Loading...</option>';
        courseSelect.disabled = true;

        if (!schoolId) {
            courseSelect.innerHTML = '<option value="">-- Select School First --</option>';
            return;
        }

        try {
            const response = await fetch(BASE_URL + `api/shared/get_courses_by_school.php?school_id=${schoolId}`);
            const courses = await response.json();
            
            courseSelect.innerHTML = '<option value="" disabled selected>-- Select a Course --</option>';
            courses.forEach(course => {
                courseSelect.add(new Option(course.name, course.id));
            });
            courseSelect.disabled = false;
        } catch (error) {
            console.error('Failed to load courses:', error);
            courseSelect.innerHTML = '<option value="">Error loading</option>';
        }
    });
});
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>