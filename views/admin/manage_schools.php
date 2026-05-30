<?php
  $pageTitle = 'Manage Schools';
  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  // --- Authorization Check ---
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
      redirect('login.php');
      exit();
  }

  // Fetch all schools
  $schools = $pdo->query("SELECT * FROM schools ORDER BY name ASC")->fetchAll();
?>

<div class="manage-container">
    <a href="dashboard.php" style="text-decoration: none; color: #007bff; margin-bottom: 20px; display: inline-block;">&larr; Back to Dashboard</a>
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h2>All Schools (<?php echo count($schools); ?>)</h2>
    </div>

    <?php
    if (isset($_GET['success'])) { echo '<div class="message-box success-message">' . htmlspecialchars($_GET['success']) . '</div>'; }
    if (isset($_GET['error'])) { echo '<div class="message-box error-message">' . htmlspecialchars($_GET['error']) . '</div>'; }
    ?>
    
    <div class="section-box">
        <h3>Add New School</h3>
        <form action="<?= get_base_url() ?>api/admin/add_school.php" method="POST" style="display:flex; gap: 15px;">
            <input type="text" name="school_name" class="input-field" placeholder="Enter new school name" required style="flex-grow:1;">
            <button type="submit" class="button-red" style="width:auto;">Add School</button>
        </form>
    </div>

    <table class="data-table">
        <thead>
            <tr><th>School Name</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($schools as $school): ?>
                <tr>
                    <td><?php echo htmlspecialchars($school['name']); ?></td>
                    <td class="action-buttons" style="flex-direction:row;">
                        <a href="<?= get_base_url() ?>api/admin/delete_school.php?id=<?php echo $school['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this school? This might affect existing courses and students.')" style="background-color:#dc3545; color:white; border:none; padding: 8px 12px; font-size: 14px; border-radius: 6px; cursor:pointer;">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
  require_once '../../assets/templates/footer.php';
?>
