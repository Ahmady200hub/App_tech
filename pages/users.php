<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
$pageTitle  = 'Manage Users';
$activePage = 'users';
$db   = getDB();
$flashMsg=''; $flashType='success';

// Add user
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_user'])) {
    if (!csrf_verify()) { $flashMsg='Security error.'; $flashType='error'; }
    else {
        $name  = sanitize($_POST['full_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $pw    = $_POST['password'] ?? '';
        $role  = in_array($_POST['role']??'', ['admin','lecturer','student']) ? $_POST['role'] : 'student';
        $sid   = $role==='student' ? sanitize($_POST['student_id']??'') : null;
        if (!$name || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($pw)<8) {
            $flashMsg='Name, valid email and password (8+ chars) required.'; $flashType='error';
        } else {
            $dup=$db->prepare("SELECT id FROM users WHERE email=?"); $dup->execute([$email]);
            if ($dup->fetch()) { $flashMsg='Email already registered.'; $flashType='error'; }
            else {
                $hash=password_hash($pw,PASSWORD_BCRYPT,['cost'=>BCRYPT_COST]);
                $db->prepare("INSERT INTO users (full_name,email,password,role,student_id) VALUES (?,?,?,?,?)")->execute([$name,$email,$hash,$role,$sid]);
                $flashMsg="User $name ($role) created.";
            }
        }
    }
}

// Toggle active
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['toggle_user'])) {
    if (!csrf_verify()) { $flashMsg='Security error.'; $flashType='error'; }
    else {
        $uid=(int)$_POST['uid']; $active=(int)$_POST['active'];
        $db->prepare("UPDATE users SET is_active=? WHERE id=?")->execute([$active,$uid]);
        $flashMsg='User status updated.';
    }
}

// Reset password
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reset_pw'])) {
    if (!csrf_verify()) { $flashMsg='Security error.'; $flashType='error'; }
    else {
        $uid=(int)$_POST['uid']; $pw=$_POST['new_pw']??'';
        if(strlen($pw)<8){$flashMsg='Password must be 8+ characters.';$flashType='error';}
        else {
            $hash=password_hash($pw,PASSWORD_BCRYPT,['cost'=>BCRYPT_COST]);
            $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash,$uid]);
            $flashMsg='Password reset successfully.';
        }
    }
}

$users = $db->query("SELECT * FROM users ORDER BY role, full_name")->fetchAll();
$roleColors = ['admin'=>'b-purple','lecturer'=>'b-blue','student'=>'b-cyan'];
$avCols = ['av-c0','av-c1','av-c2','av-c3','av-c4','av-c5'];

include __DIR__ . '/../includes/header.php';
?>
<div class="page-fade">
<div class="pg-header-row">
  <div><div class="pg-eyebrow">Administration</div><div class="pg-title">Manage Users</div><div class="pg-sub">All accounts — add, deactivate, or reset passwords.</div></div>
  <button class="btn btn-prim" onclick="openModal('add-user-modal')">+ Add User</button>
</div>

<div class="card"><div class="tbl-wrap"><table>
  <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Student ID</th><th>Last Login</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
    <?php foreach ($users as $u): ?>
    <tr>
      <td><div class="flex-c gap-6"><div class="su-av <?= $avCols[$u['avatar_color']%6] ?>" style="width:28px;height:28px;font-size:11px"><?= initials($u['full_name']) ?></div><span style="font-weight:600"><?= e($u['full_name']) ?></span></div></td>
      <td style="color:var(--t2);font-size:12px"><?= e($u['email']) ?></td>
      <td><span class="badge <?= $roleColors[$u['role']] ?>"><?= ucfirst($u['role']) ?></span></td>
      <td style="color:var(--t3)"><?= e($u['student_id']??'—') ?></td>
      <td style="font-size:12px;color:var(--t3)"><?= $u['last_login'] ? date('d M Y H:i',strtotime($u['last_login'])) : 'Never' ?></td>
      <td><span class="badge <?= $u['is_active']?'b-green':'b-red' ?>"><?= $u['is_active']?'Active':'Inactive' ?></span></td>
      <td>
        <div class="flex-c gap-6">
          <form method="POST" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
            <input type="hidden" name="toggle_user" value="1"/>
            <input type="hidden" name="uid" value="<?= $u['id'] ?>"/>
            <input type="hidden" name="active" value="<?= $u['is_active']?0:1 ?>"/>
            <button type="submit" class="btn btn-ghost btn-xs"><?= $u['is_active']?'Deactivate':'Activate' ?></button>
          </form>
          <button class="btn btn-sec btn-xs" onclick="openReset(<?= $u['id'] ?>, '<?= e(addslashes($u['full_name'])) ?>')">Reset PW</button>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table></div></div>
</div>

<!-- ADD USER MODAL -->
<div id="add-user-modal" class="modal-overlay">
  <div class="modal">
    <div class="modal-head"><div><div class="modal-title">Add User</div><div class="modal-sub">Create a new account</div></div><button class="modal-close" onclick="closeModal('add-user-modal')">✕</button></div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
      <input type="hidden" name="add_user" value="1"/>
      <div class="form-group"><label>Full Name</label><input class="form-control" name="full_name" required/></div>
      <div class="form-group"><label>Email</label><input class="form-control" type="email" name="email" required/></div>
      <div class="form-group"><label>Password (min 8 chars)</label><input class="form-control" type="password" name="password" required/></div>
      <div class="form-group"><label>Role</label><select class="form-control" name="role" onchange="document.getElementById('sid-row').style.display=this.value==='student'?'block':'none'"><option value="student">Student</option><option value="lecturer">Lecturer</option><option value="admin">Admin</option></select></div>
      <div class="form-group" id="sid-row"><label>Student ID</label><input class="form-control" name="student_id" placeholder="e.g. APT-2026-050"/></div>
      <div class="flex-c gap-8 mt-2"><button type="submit" class="btn btn-prim">Create User</button><button type="button" class="btn btn-ghost" onclick="closeModal('add-user-modal')">Cancel</button></div>
    </form>
  </div>
</div>

<!-- RESET PW MODAL -->
<div id="reset-modal" class="modal-overlay">
  <div class="modal">
    <div class="modal-head"><div><div class="modal-title">Reset Password</div><div class="modal-sub" id="reset-name"></div></div><button class="modal-close" onclick="closeModal('reset-modal')">✕</button></div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
      <input type="hidden" name="reset_pw" value="1"/>
      <input type="hidden" name="uid" id="reset-uid"/>
      <div class="form-group"><label>New Password (min 8 chars)</label><input class="form-control" type="password" name="new_pw" required/></div>
      <div class="flex-c gap-8 mt-2"><button type="submit" class="btn btn-prim">Reset Password</button><button type="button" class="btn btn-ghost" onclick="closeModal('reset-modal')">Cancel</button></div>
    </form>
  </div>
</div>

<script>
function openReset(uid, name) {
  document.getElementById('reset-uid').value = uid;
  document.getElementById('reset-name').textContent = 'For: ' + name;
  openModal('reset-modal');
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
