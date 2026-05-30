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
  $specializations = $pdo->query("SELECT id, name FROM specializations ORDER BY name ASC")->fetchAll();
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
  .select2-container .select2-selection--multiple { 
      min-height: 42px; 
      border: 1px solid #ced4da; 
      padding-top: 5px;
  }
</style>

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
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <hr>

        <div id="role-specific-fields">
            <div class="student-fields" style="display:none;">
                <h4>Student Details</h4>
                <div class="form-row">
                    <div class="form-group"><label>SAP ID</label><input type="text" name="sap_id"></div>
                    <div class="form-group"><label>Roll No.</label><input type="text" name="roll_no"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="student_school_id">School</label>
                        <select id="student_school_id" name="school_id">
                            <option value="">-- Select a School --</option>
                            <?php foreach($schools as $school): ?>
                            <option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="course_id">Course</label>
                        <select id="course_id" name="course_id" disabled><option value="">-- Select School First --</option></select>
                    </div>
                </div>
                 <div class="form-row">
                    <div class="form-group">
                        <label for="grad_year">Graduation Year</label>
                        <input type="number" id="grad_year" name="graduation_year" placeholder="e.g., <?php echo date('Y') + 4; ?>">
                    </div>
                     <div class="form-group">
                        <label for="batch">Batch</label>
                        <input type="text" id="batch" name="batch" placeholder="e.g., 2024-2028">
                    </div>
                </div>
                <div class="form-group">
                    <label for="specializations">Assign Specializations (Optional)</label>
                    <select multiple id="specializations" name="specialization_ids[]" style="width: 100%;">
                        <?php foreach ($specializations as $spec): ?>
                            <option value="<?php echo $spec['id']; ?>"><?php echo htmlspecialchars($spec['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="staff-fields" style="display:none;">
                <h4>Staff Details</h4>
                
                <div class="form-row" id="visiting-faculty-group" style="display:none;">
                    <div class="form-group" style="flex-direction: row; align-items: center; gap: 10px;">
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
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
const BASE_URL = '<?= get_base_url() ?>';
document.addEventListener('DOMContentLoaded', function() {
    $('#specializations').select2({
        placeholder: "Select one or more specializations",
        allowClear: true
    });

    const roleSelect = document.getElementById('role_id');
    const studentFields = document.querySelector('.student-fields');
    const staffFields = document.querySelector('.staff-fields');
    
    roleSelect.addEventListener('change', function() {
        const roleText = this.options[this.selectedIndex].text.toLowerCase();
        
        studentFields.style.display = 'none';
        staffFields.style.display = 'none';

        if (roleText === 'student') {
            studentFields.style.display = 'block';
        } else if (roleText === 'faculty' || roleText.includes('head')) { 
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