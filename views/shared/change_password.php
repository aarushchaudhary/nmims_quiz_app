<?php
  $pageTitle = 'Change Password';
  $isChangePasswordPage = true;
  require_once '../../assets/templates/header.php';
  
  if (!isset($_SESSION['user_id'])) {
      redirect('login.php');
      exit();
  }
  
  $is_forced = !empty($_SESSION['force_password_change']);
?>

<div class="form-container" style="max-width: 500px; margin-top: 50px;">
    <?php if ($is_forced): ?>
        <h2>Security Update Required</h2>
        <p style="text-align: center; color: #555; margin-bottom: 20px;">
            For your security, you must change your password before you can access the system.
        </p>
    <?php else: ?>
        <h2>Change Password</h2>
        <p style="text-align: center; color: #555; margin-bottom: 20px;">
            Update your account password.
        </p>
    <?php endif; ?>

    <div id="password-message-box" class="message-box error-message" style="display: none;"></div>

    <form id="change-password-form">
        <!-- Always require Current Password unless it's a forced change -->
        <?php if (!$is_forced): ?>
        <div class="form-group">
            <label for="current_password">Current Password</label>
            <input type="password" id="current_password" name="current_password" required>
        </div>
        <?php endif; ?>

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
const isForced = <?= $is_forced ? 'true' : 'false' ?>;

document.getElementById('change-password-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    let currentPassword = '';
    
    if (!isForced) {
        currentPassword = document.getElementById('current_password').value;
    }

    const msgBox = document.getElementById('password-message-box');

    if (newPassword !== confirmPassword) {
        msgBox.className = 'message-box error-message';
        msgBox.innerText = "New passwords do not match.";
        msgBox.style.display = 'block';
        return;
    }

    const btn = this.querySelector('button');
    btn.disabled = true;
    btn.innerText = 'Updating...';

    try {
        const response = await fetch(BASE_URL + 'api/shared/change_password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                current_password: currentPassword,
                new_password: newPassword, 
                confirm_password: confirmPassword,
                is_forced: isForced
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            msgBox.className = 'message-box success-message';
            msgBox.innerText = 'Password updated successfully! Redirecting to Home...';
            msgBox.style.display = 'block';
            setTimeout(() => {
                window.location.href = BASE_URL + 'index.php';
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

<?php if ($is_forced): ?>
<style>
    .header-buttons { display: none !important; }
</style>
<?php endif; ?>

<?php require_once '../../assets/templates/footer.php'; ?>
