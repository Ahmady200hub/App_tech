<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$pageTitle  = 'My Profile';
$activePage = 'profile';
$db   = getDB();
$user = currentUser();
$uid  = $user['id'];
$flashMsg = ''; $flashType = 'success';

// Update profile name
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!csrf_verify()) { $flashMsg = 'Security error.'; $flashType = 'error'; }
    else {
        $name = sanitize($_POST['full_name'] ?? '');
        if (strlen($name) < 3) { $flashMsg = 'Name too short.'; $flashType = 'error'; }
        else {
            $db->prepare("UPDATE users SET full_name=? WHERE id=?")->execute([$name, $uid]);
            $_SESSION['user_name'] = $name;
            $flashMsg = 'Profile updated successfully.';
        }
    }
}

// Change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_pw'])) {
    if (!csrf_verify()) { $flashMsg = 'Security error.'; $flashType = 'error'; }
    else {
        $current = $_POST['current_pw'] ?? '';
        $new     = $_POST['new_pw']     ?? '';
        $confirm = $_POST['confirm_pw'] ?? '';

        $row = $db->prepare("SELECT password FROM users WHERE id=?");
        $row->execute([$uid]);
        $hash = $row->fetchColumn();

        if (!password_verify($current, $hash)) {
            $flashMsg = 'Current password is incorrect.'; $flashType = 'error';
        } elseif (strlen($new) < 8) {
            $flashMsg = 'New password must be at least 8 characters.'; $flashType = 'error';
        } elseif ($new !== $confirm) {
            $flashMsg = 'New passwords do not match.'; $flashType = 'error';
        } else {
            $newHash = password_hash($new, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$newHash, $uid]);
            $flashMsg = 'Password changed successfully.';
        }
    }
}

// Fetch fresh data
$profile = $db->prepare("SELECT * FROM users WHERE id=?");
$profile->execute([$uid]);
$profile = $profile->fetch();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-fade">
<div class="pg-header">
  <div class="pg-eyebrow">Account</div>
  <div class="pg-title">My Profile</div>
  <div class="pg-sub">Manage your account information and security settings.</div>
</div>

<div class="grid2">

<!-- Profile Info -->
<div class="card">
  <div class="card-head"><span class="card-title">👤 Profile Information</span></div>
  <div class="card-body">
    <div style="display:flex;align-items:center;gap:16px;margin-bottom:1.5rem;padding-bottom:1.5rem;border-bottom:1px solid var(--border)">
      <div class="su-av av-c<?= (int)$profile['avatar_color'] ?>" style="width:60px;height:60px;font-size:22px;border-radius:16px">
        <?= initials($profile['full_name']) ?>
      </div>
      <div>
        <div style="font-family:'Syne',sans-serif;font-size:18px;font-weight:800"><?= e($profile['full_name']) ?></div>
        <div style="font-size:13px;color:var(--t2)"><?= e($profile['email']) ?></div>
        <div style="margin-top:4px"><span class="badge b-cyan"><?= ucfirst($profile['role']) ?></span>
        <?php if ($profile['student_id']): ?>
          <span class="badge b-blue" style="margin-left:4px"><?= e($profile['student_id']) ?></span>
        <?php endif; ?>
        </div>
      </div>
    </div>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
      <input type="hidden" name="update_profile" value="1"/>
      <div class="form-group">
        <label>Full Name</label>
        <input class="form-control" name="full_name" value="<?= e($profile['full_name']) ?>" required/>
      </div>
      <div class="form-group">
        <label>Email Address</label>
        <input class="form-control" value="<?= e($profile['email']) ?>" disabled style="opacity:.5;cursor:not-allowed"/>
        <div class="form-hint">Email cannot be changed. Contact admin if needed.</div>
      </div>
      <div class="form-group">
        <label>Role</label>
        <input class="form-control" value="<?= ucfirst($profile['role']) ?>" disabled style="opacity:.5;cursor:not-allowed"/>
      </div>
      <?php if ($profile['last_login']): ?>
      <div style="font-size:12px;color:var(--t3);margin-bottom:1rem">
        Last login: <?= date('d M Y H:i', strtotime($profile['last_login'])) ?>
      </div>
      <?php endif; ?>
      <button type="submit" class="btn btn-prim">Save Changes</button>
    </form>
  </div>
</div>

<!-- Change Password -->
<div class="card">
  <div class="card-head"><span class="card-title">🔒 Change Password</span></div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
      <input type="hidden" name="change_pw" value="1"/>
      <div class="form-group">
        <label>Current Password</label>
        <input class="form-control" type="password" name="current_pw" required autocomplete="current-password"/>
      </div>
      <div class="form-group">
        <label>New Password</label>
        <input class="form-control" type="password" name="new_pw" required autocomplete="new-password"/>
        <div class="form-hint">Minimum 8 characters.</div>
      </div>
      <div class="form-group">
        <label>Confirm New Password</label>
        <input class="form-control" type="password" name="confirm_pw" required autocomplete="new-password"/>
      </div>
      <button type="submit" class="btn btn-prim">Change Password</button>
    </form>
  </div>
</div>

</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
