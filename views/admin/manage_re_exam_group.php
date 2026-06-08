<?php
  $pageTitle = 'Manage Re Exam Group Students';
  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
      redirect('login.php');
      exit();
  }

  $group_id = $_GET['id'] ?? null;
  if (!$group_id) {
      header('Location: re_exam_groups.php');
      exit();
  }

  // Fetch group details
  $stmt = $pdo->prepare("SELECT * FROM re_exam_groups WHERE id = ?");
  $stmt->execute([$group_id]);
  $group = $stmt->fetch();

  if (!$group) {
      header('Location: re_exam_groups.php?error=' . urlencode("Group not found."));
      exit();
  }

  // Fetch students enrolled
  $stmt = $pdo->prepare("
      SELECT s.sap_id, s.name, s.user_id 
      FROM re_exam_group_students gs 
      JOIN students s ON gs.student_id = s.user_id 
      WHERE gs.group_id = ?
      ORDER BY s.sap_id ASC
  ");
  $stmt->execute([$group_id]);
  $students = $stmt->fetchAll();
?>

<div class="manage-container">
    <a href="re_exam_groups.php" style="text-decoration: none; color: #007bff; margin-bottom: 20px; display: inline-block;">&larr; Back to Re Exam Groups</a>
    
    <h2>Manage Students for: <?php echo htmlspecialchars($group['name']); ?></h2>
    <p style="color: #d9534f; font-weight: bold;">Expires At: <?php echo date('Y-m-d H:i', strtotime($group['expires_at'])); ?></p>

    <?php
    if (isset($_GET['success'])) { echo '<div class="message-box success-message">' . htmlspecialchars($_GET['success']) . '</div>'; }
    if (isset($_GET['error'])) { echo '<div class="message-box error-message">' . htmlspecialchars($_GET['error']) . '</div>'; }
    
    if (isset($_SESSION['upload_errors']) && !empty($_SESSION['upload_errors'])) {
        echo '<div class="message-box error-message"><ul>';
        foreach ($_SESSION['upload_errors'] as $err) {
            echo '<li>' . htmlspecialchars($err) . '</li>';
        }
        echo '</ul></div>';
        unset($_SESSION['upload_errors']);
    }
    ?>
    
    <div style="display:flex; gap: 20px; margin-top: 20px;">
        <!-- Form for comma separated SAP IDs -->
        <div class="section-box" style="flex: 1;">
            <h3>Add Students Manually</h3>
            <p style="font-size: 14px; color: #555; margin-bottom: 15px;">Enter comma-separated SAP IDs to add students directly.</p>
            <form action="<?= get_base_url() ?>api/admin/add_re_exam_students.php" method="POST">
                <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                <textarea name="sap_ids" class="input-field" placeholder="e.g., 70481234567, 70481234568" rows="4" style="width: 100%; box-sizing: border-box; resize: vertical;"></textarea>
                <button type="submit" class="button-red" style="margin-top: 15px;">Add Students</button>
            </form>
        </div>

        <!-- Form for Excel Upload -->
        <div class="section-box" style="flex: 1;">
            <h3>Upload XLSX File</h3>
            <p style="font-size: 14px; color: #555; margin-bottom: 15px;">Upload an XLSX file containing a single column of SAP IDs (no header needed).</p>
            <form action="<?= get_base_url() ?>api/admin/add_re_exam_students.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                <div class="file-upload-wrapper" style="margin-bottom: 15px;">
                    <input type="file" name="sap_file" id="sap_file" accept=".xlsx" class="input-field" required style="width: 100%; box-sizing: border-box; padding: 10px;">
                </div>
                <button type="submit" class="button-red">Upload and Add</button>
            </form>
        </div>
    </div>

    <table class="data-table" style="margin-top: 20px;">
        <thead>
            <tr>
                <th>SAP ID</th>
                <th>Student Name</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($students as $student): ?>
                <tr>
                    <td><?php echo htmlspecialchars($student['sap_id']); ?></td>
                    <td><?php echo htmlspecialchars($student['name']); ?></td>
                    <td class="action-buttons" style="flex-direction:row;">
                        <a href="<?= get_base_url() ?>api/admin/remove_re_exam_student.php?group_id=<?php echo $group['id']; ?>&student_id=<?php echo $student['user_id']; ?>" class="btn-delete" onclick="return confirm('Remove this student from the group?')" style="background-color:#dc3545; color:white; border:none; padding: 6px 10px; font-size: 12px; border-radius: 4px; cursor:pointer; text-decoration:none;">Remove</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
  require_once '../../assets/templates/footer.php';
?>
