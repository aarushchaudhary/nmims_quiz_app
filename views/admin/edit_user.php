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
                 s.name as student_name, s.sap_id as student_sap_id, s.roll_no, s.course_id, s.batch, s.graduation_year,
                 f.name as faculty_name, f.sap_id as faculty_sap_id, f.school_id as faculty_school_id, f.is_visiting as faculty_is_visiting,
                 p.name as placecom_name, p.department as placecom_department,
                 h.name as head_name, h.school_id as head_school_id,
                 a.name as admin_name
          FROM users u
          LEFT JOIN students s ON u.id = s.user_id
          LEFT JOIN faculties f ON u.id = f.user_id
          LEFT JOIN placement_officers p ON u.id = p.user_id
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
  $specializations = [];
  if ($user['role_id'] == 4) {
      $specializations = $pdo->query("SELECT id, name FROM specializations ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
  }
?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
  .select2-container .select2-selection--multiple { 
      min-height: 42px; 
      border: 1px solid #ced4da; 
  }
  .select2-container--default .select2-selection--multiple .select2-selection__choice {
      padding: 5px 10px;
      margin-top: 5px;
  }
</style>

<div class="form-container" style="max-width: 800px;">
    <h2>Edit User: <?php echo htmlspecialchars($full_name); ?></h2>
    
    <form id="edit-user-form" action="<?= get_base_url() ?>api/admin/update_user.php" method="POST">
        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
        <input type="hidden" name="role_id" value="<?php echo $user['role_id']; ?>">
        
        <div class="form-row">
            <div class="form-group"><label>Full Name</label><input type="text" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required></div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
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
                <div class="form-group"><label>SAP ID</label><input type="text" name="sap_id" value="<?php echo htmlspecialchars($user['student_sap_id'] ?? ''); ?>"></div>
                <div class="form-group"><label>Roll No.</label><input type="text" name="roll_no" value="<?php echo htmlspecialchars($user['roll_no'] ?? ''); ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Course</label><select name="course_id"><?php foreach($courses as $c) echo "<option value='{$c['id']}' ".($user['course_id']==$c['id']?'selected':'').">{$c['name']}</option>"; ?></select></div>
                <div class="form-group"><label>Graduation Year</label><input type="number" name="graduation_year" value="<?php echo htmlspecialchars($user['graduation_year'] ?? ''); ?>"></div>
            </div>
            <div class="form-group">
                <label for="specializations">Assign Specializations</label>
                <select multiple class="form-control" id="specializations" name="specialization_ids[]">
                    <?php foreach ($specializations as $spec): ?>
                        <option value="<?php echo $spec['id']; ?>"><?php echo htmlspecialchars($spec['name']); ?></option>
                    <?php endforeach; ?>
                </select>
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
            <h4>Placement Officer Details</h4>
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
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
const BASE_URL = '<?= get_base_url() ?>';
document.addEventListener('DOMContentLoaded', function() {
    const specializationsSelect = document.getElementById('specializations');
    if (specializationsSelect) {
        $('#specializations').select2({
            placeholder: "Select one or more specializations",
            allowClear: true
        });

        const userId = <?php echo $user_id; ?>;

        // Fetch and pre-select the student's current specializations
        fetch(BASE_URL + `api/admin/get_user_specializations.php?user_id=${userId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.specializations) {
                    const specializationIds = data.specializations.map(spec => spec.specialization_id);
                    $('#specializations').val(specializationIds).trigger('change');
                }
            })
            .catch(error => console.error('Error fetching user specializations:', error));
    }

});
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>