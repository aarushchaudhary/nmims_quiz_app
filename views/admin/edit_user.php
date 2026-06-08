<?php
  $pageTitle = 'Edit User';
  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  // --- Authorization & Input Check ---
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1 || !isset($_GET['id'])) {
      redirect('login.php');
      exit();
  }
  $user_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

  // --- Fetch all user data ---
  $sql = "SELECT u.id, u.email, u.role_id, 
                 s.name as student_name, s.sap_id as student_sap_id, s.course_id, s.batch, s.graduation_year,
                 f.name as faculty_name, f.sap_id as faculty_sap_id, f.school_id as faculty_school_id, f.is_visiting as faculty_is_visiting,
                 p.name as placecom_name, p.department as placecom_department,
                 h.name as head_name, h.school_id as head_school_id,
                 a.name as admin_name
          FROM users u
          LEFT JOIN students s ON u.id = s.user_id
          LEFT JOIN faculties f ON u.id = f.user_id
          LEFT JOIN placecom_officers p ON u.id = p.user_id
          LEFT JOIN heads h ON u.id = h.user_id
          LEFT JOIN admins a ON u.id = a.user_id
          WHERE u.id = ?";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$user_id]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
      header('Location: user_management.php?error=user_not_found');
      exit();
  }
  
  // Determine the full name from the correct table
  $full_name = $user['student_name'] ?? $user['faculty_name'] ?? $user['placecom_name'] ?? $user['head_name'] ?? $user['admin_name'] ?? ''; 

  $courses = $pdo->query("SELECT id, name FROM courses")->fetchAll();
  $schools = $pdo->query("SELECT id, name FROM schools")->fetchAll();
  
  // --- Fetch specializations for the form ---
  $courses_data = $pdo->query("SELECT c.code, c.name as course_name, c.duration_years, s.name as school_name 
                               FROM courses c 
                               JOIN schools s ON c.school_id = s.id")->fetchAll(PDO::FETCH_ASSOC);
?>


<div class="form-container" style="max-width: 800px;">
    <h2>Edit User: <?php echo htmlspecialchars($full_name); ?></h2>
    
    <form id="edit-user-form" action="<?= get_base_url() ?>api/admin/update_user.php" method="POST">
        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
        <input type="hidden" name="role_id" value="<?php echo $user['role_id']; ?>">
        
        <div class="form-row">
            <div class="form-group"><label>Full Name</label><input type="text" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required></div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" <?php if ($user['role_id'] == 4) echo 'pattern=".*nmims\.in$" title="Student email must end with nmims.in"'; ?> required>
            </div>
        </div>
        <div class="form-group">
            <label>New Password (leave blank to keep current password)</label>
            <input type="password" name="password">
        </div>
        <hr>

        <?php if ($user['role_id'] == 4): ?>
        <div class="student-fields">
            <h4>Student Details</h4>
            <div class="form-row">
                <div class="form-group">
                    <label>SAP ID (11 Digits)</label>
                    <input type="text" name="sap_id" id="sap_id" minlength="11" maxlength="11" pattern="\d{11}" title="Must be exactly 11 digits" value="<?php echo htmlspecialchars($user['student_sap_id'] ?? ''); ?>" required>
                    <div id="sap-preview" style="margin-top: 8px; font-size: 0.9em; display: none;"></div>
                    <small style="color: #666; font-size: 0.8em; margin-top: 5px; display: block;">Note: School, Course, and Batch will be automatically assigned based on SAP ID.</small>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($user['role_id'] == 2): ?>
        <div class="faculty-fields">
            <h4>Faculty Details</h4>
            <div class="form-row">
                <div class="form-group"><label>SAP ID</label><input type="text" name="faculty_sap_id" value="<?php echo htmlspecialchars($user['faculty_sap_id'] ?? ''); ?>"></div>
                <div class="form-group"><label>School</label>
                    <select name="department">
                        <option value="">-- Select --</option>
                        <?php foreach($schools as $school): ?>
                        <option value="<?php echo $school['id']; ?>" <?php echo ($user['faculty_school_id'] == $school['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($school['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex-direction: row; align-items: center; gap: 10px;">
                    <input type="checkbox" id="is_visiting" name="is_visiting" value="1" style="width: auto; height: 18px;" <?php echo (!empty($user['faculty_is_visiting'])) ? 'checked' : ''; ?>>
                    <label for="is_visiting" style="margin: 0;">Visiting Faculty</label>
                </div>
            </div>
        </div>
        <?php elseif ($user['role_id'] == 3): ?>
        <div class="placecom-fields">
            <h4>Placecom Officer Details</h4>
            <div class="form-row">
                <div class="form-group"><label>Department</label><input type="text" name="department" value="<?php echo htmlspecialchars($user['placecom_department'] ?? ''); ?>"></div>
            </div>
        </div>
        <?php elseif ($user['role_id'] == 5): ?>
        <div class="head-fields">
            <h4>School Head Details</h4>
            <div class="form-row">
                <div class="form-group"><label>School</label>
                    <select name="department">
                        <option value="">-- Select --</option>
                        <?php foreach($schools as $school): ?>
                        <option value="<?php echo $school['id']; ?>" <?php echo ($user['head_school_id'] == $school['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($school['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="form-group" style="text-align: center; margin-top: 30px;">
            <button type="submit" class="button-red" style="width: auto; padding: 12px 40px;">Save Changes</button>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script>
const BASE_URL = '<?= get_base_url() ?>';
const coursesData = <?php echo json_encode($courses_data ?? []); ?>;
const coursesMap = {};
coursesData.forEach(c => {
    coursesMap[c.code] = c;
});

document.addEventListener('DOMContentLoaded', function() {
    const sapInput = document.getElementById('sap_id');
    const sapPreview = document.getElementById('sap-preview');
    
    function updateSapPreview() {
        if (!sapInput || !sapPreview) return;
        const val = sapInput.value.trim();
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
    }
    
    if (sapInput) {
        sapInput.addEventListener('input', updateSapPreview);
        // Trigger on load
        updateSapPreview();
    }
});
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>