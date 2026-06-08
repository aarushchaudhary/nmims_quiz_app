<?php
  $pageTitle = 'Manage Roles';
  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  // --- Authorization Check ---
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
      redirect('login.php');
      exit();
  }

  // Fetch all roles
  $roles = $pdo->query("SELECT * FROM roles ORDER BY name ASC")->fetchAll();
?>

<div class="manage-container">

    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h2>All User Roles (<?php echo count($roles); ?>)</h2>
    </div>

    <?php
    if (isset($_GET['success'])) { echo '<div class="message-box success-message">' . htmlspecialchars($_GET['success']) . '</div>'; }
    if (isset($_GET['error'])) { echo '<div class="message-box error-message">' . htmlspecialchars($_GET['error']) . '</div>'; }
    ?>
    
    <div class="section-box">
        <h3>Add New Role</h3>
        <form action="<?= get_base_url() ?>api/admin/add_role.php" method="POST" style="display:flex; gap: 15px;">
            <input type="text" name="role_name" class="input-field" placeholder="Enter new role name (e.g., observer)" required style="flex-grow:1;">
            <button type="submit" class="button-red" style="width:auto;">Add Role</button>
        </form>
    </div>

    <table class="data-table">
        <thead>
            <tr><th>Role Name</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($roles as $role): ?>
                <tr>
                    <td><?php echo htmlspecialchars(ucfirst($role['name'])); ?></td>
                    <td class="action-buttons" style="flex-direction:row;">
                        <?php if (!in_array($role['name'], ['admin', 'faculty', 'student', 'placecom', 'director', 'school head'])): // Prevent deleting core roles ?>
                        <a href="<?= get_base_url() ?>api/admin/delete_role.php?id=<?php echo $role['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this role?')" style="background-color:#dc3545; color:white; border:none; padding: 8px 12px; font-size: 14px; border-radius: 6px; cursor:pointer;">Delete</a>
                        <?php else: ?>
                            <span style="color:#999;">Core Role</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
  require_once '../../assets/templates/footer.php';
?>
