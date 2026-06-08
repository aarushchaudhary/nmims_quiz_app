<?php
  $pageTitle = 'Manage Electives';
  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
      redirect('login.php');
      exit();
  }

  // Fetch electives with student count
  $electives = $pdo->query("
      SELECT e.*, COUNT(es.student_id) as student_count 
      FROM electives e 
      LEFT JOIN elective_students es ON e.id = es.elective_id 
      GROUP BY e.id 
      ORDER BY e.name ASC
  ")->fetchAll();
?>

<div class="manage-container">

    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h2>All Electives (<?php echo count($electives); ?>)</h2>
    </div>

    <?php
    if (isset($_GET['success'])) { echo '<div class="message-box success-message">' . htmlspecialchars($_GET['success']) . '</div>'; }
    if (isset($_GET['error'])) { echo '<div class="message-box error-message">' . htmlspecialchars($_GET['error']) . '</div>'; }
    ?>
    
    <div class="section-box">
        <h3>Add New Elective</h3>
        <form action="<?= get_base_url() ?>api/admin/add_elective.php" method="POST" style="display:flex; gap: 15px;">
            <input type="text" name="elective_name" class="input-field" placeholder="Enter elective name (e.g., Advanced AI)" required style="flex: 1;">
            <button type="submit" class="button-red" style="width:auto;">Create Elective</button>
        </form>
    </div>

    <table class="data-table" style="margin-top: 20px;">
        <thead>
            <tr>
                <th>Elective Name</th>
                <th>Students Enrolled</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($electives as $elective): ?>
                <tr>
                    <td><?php echo htmlspecialchars($elective['name']); ?></td>
                    <td><?php echo $elective['student_count']; ?></td>
                    <td class="action-buttons" style="flex-direction:row;">
                        <a href="manage_elective.php?id=<?php echo $elective['id']; ?>" class="btn-edit" style="background-color:#007bff; color:white; border:none; padding: 8px 12px; font-size: 14px; border-radius: 6px; cursor:pointer; text-decoration:none; margin-right: 5px;">Manage Students</a>
                        <a href="<?= get_base_url() ?>api/admin/delete_elective.php?id=<?php echo $elective['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this elective?')" style="background-color:#dc3545; color:white; border:none; padding: 8px 12px; font-size: 14px; border-radius: 6px; cursor:pointer; text-decoration:none;">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
  require_once '../../assets/templates/footer.php';
?>
