<?php
  $pageTitle = 'Change Password Required';
  require_once '../../assets/templates/header.php';
  
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4) {
      redirect('login.php');
      exit();
  }
  
  if (empty($_SESSION['force_password_change'])) {
      // If they somehow got here but don't need to change password, redirect to dashboard
      header('Location: ' . get_base_url() . 'views/student/dashboard.php');
      exit();
  }
?>

<div class="form-container" style="max-width: 500px;">
    <h2>Security Update Required</h2>
    <p style="text-align: center; color: #555; margin-bottom: 20px;">
        For your security, you must change your password from your default SAP ID before you can access the system.
    </p>

    <div id="password-message-box" class="message-box error-message" style="display: none;"></div>

    <form id="change-password-form">
        <div class="form-group">
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" required minlength="6">
        </div>
        
        <div class="form-group">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
        </div>

        <button type="submit" class="button-red" style="width: 100%;">Update Password</button>
    </form>
</div>

<script>
const BASE_URL = '<?= get_base_url() ?>';

document.getElementById('change-password-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const msgBox = document.getElementById('password-message-box');

    if (newPassword !== confirmPassword) {
        msgBox.innerText = "Passwords do not match.";
        msgBox.style.display = 'block';
        return;
    }

    const btn = this.querySelector('button');
    btn.disabled = true;
    btn.innerText = 'Updating...';

    try {
        const response = await fetch(BASE_URL + 'api/student/change_password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ new_password: newPassword, confirm_password: confirmPassword })
        });
        
        const data = await response.json();
        
        if (data.success) {
            msgBox.className = 'message-box success-message';
            msgBox.innerText = 'Password updated successfully! Redirecting...';
            msgBox.style.display = 'block';
            setTimeout(() => {
                window.location.href = BASE_URL + 'views/student/dashboard.php';
            }, 1500);
        } else {
            msgBox.className = 'message-box error-message';
            msgBox.innerText = data.error;
            msgBox.style.display = 'block';
            btn.disabled = false;
            btn.innerText = 'Update Password';
        }
    } catch (err) {
        msgBox.className = 'message-box error-message';
        msgBox.innerText = 'An error occurred. Please try again.';
        msgBox.style.display = 'block';
        btn.disabled = false;
        btn.innerText = 'Update Password';
    }
});
</script>

<?php
  // Hide header navigation buttons for this specific page using CSS since header is already loaded
?>
<style>
    .header-buttons { display: none !important; }
</style>

<?php require_once '../../assets/templates/footer.php'; ?>
