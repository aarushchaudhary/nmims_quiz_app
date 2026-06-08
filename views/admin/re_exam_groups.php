<?php
  $pageTitle = 'Manage Re Exam Groups';
  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
      redirect('login.php');
      exit();
  }

  // Fetch groups with student count
  $groups = $pdo->query("
      SELECT g.*, COUNT(gs.student_id) as student_count 
      FROM re_exam_groups g 
      LEFT JOIN re_exam_group_students gs ON g.id = gs.group_id 
      GROUP BY g.id 
      ORDER BY g.name ASC
  ")->fetchAll();
?>

<div class="manage-container">
    <a href="exam_groups.php" style="text-decoration: none; color: #007bff; margin-bottom: 20px; display: inline-block;">&larr; Back to Exam Groups</a>
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h2>All Re Exam Groups (<?php echo count($groups); ?>)</h2>
    </div>

    <?php
    if (isset($_GET['success'])) { echo '<div class="message-box success-message">' . htmlspecialchars($_GET['success']) . '</div>'; }
    if (isset($_GET['error'])) { echo '<div class="message-box error-message">' . htmlspecialchars($_GET['error']) . '</div>'; }
    ?>
    
    <div class="section-box">
        <h3>Add New Re Exam Group</h3>
        <form action="<?= get_base_url() ?>api/admin/add_re_exam_group.php" method="POST" style="display:flex; gap: 15px;">
            <input type="text" name="group_name" class="input-field" placeholder="Enter group name (e.g., Midterm Makeup)" required style="flex: 2;">
            <input type="datetime-local" name="expires_at" class="input-field" required style="flex: 1;" title="Expiration Time">
            <button type="submit" class="button-red" style="width:auto;">Create Group</button>
        </form>
    </div>

    <table class="data-table" style="margin-top: 20px;">
        <thead>
            <tr>
                <th>Group Name</th>
                <th>Expires At</th>
                <th>Students Enrolled</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($groups as $group): ?>
                <tr>
                    <td><?php echo htmlspecialchars($group['name']); ?></td>
                    <td><?php echo date('Y-m-d H:i', strtotime($group['expires_at'])); ?></td>
                    <td><?php echo $group['student_count']; ?></td>
                    <td class="action-buttons" style="flex-direction:row;">
                        <a href="manage_re_exam_group.php?id=<?php echo $group['id']; ?>" class="btn-edit" style="background-color:#007bff; color:white; border:none; padding: 8px 12px; font-size: 14px; border-radius: 6px; cursor:pointer; text-decoration:none; margin-right: 5px;">Manage Students</a>
                        <a href="<?= get_base_url() ?>api/admin/delete_re_exam_group.php?id=<?php echo $group['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this group?')" style="background-color:#dc3545; color:white; border:none; padding: 8px 12px; font-size: 14px; border-radius: 6px; cursor:pointer; text-decoration:none;">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
  require_once '../../assets/templates/footer.php';
?>
