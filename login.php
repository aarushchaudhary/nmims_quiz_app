<?php
  $pageTitle = 'Login';
  require_once 'assets/templates/header.php';
?>
<div class="form-container login-form">
  <h2>User Login</h2>
  
  <div id="message-box" class="message-box error-message" style="display: none;"></div>

  <form id="login-form">
    <div class="form-group">
      <label for="email">Email Address</label>
      <input type="email" id="email" name="email" placeholder="example@nmims.edu" required>
    </div>
    <div class="form-group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>
    </div>
    <button type="submit" class="button-red">Login</button>
  </form>
</div>

<script src="<?= get_asset_url('assets/js/script.js') ?>" defer></script>

<?php
  require_once 'assets/templates/footer.php';
?>
