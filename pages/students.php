<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin','lecturer');
$pageTitle  = 'Students';
$activePage = 'students';
$db   = getDB();
$user = currentUser();
$flashMsg = ''; $flashType = 'success';

// ── Handle Add Student (admin only) ─────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_student']) && $user['role']==='admin') {
    if (!csrf_verify()) { $flashMsg='Security token error.'; $flashType='error'; }
    else {
        $name      = sanitize($_POST['full_name']   ?? '');
        $email     = strtolower(trim($_POST['email'] ?? ''));
        $pw        = $_POST['password']  ?? '';
        $sid       = sanitize($_POST['student_id']  ?? '');
        $modId     = (int)($_POST['module_id'] ?? 0);
        $errors    = [];
        if (!$name)  $errors[] = 'Name required.';
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
        if (strlen($pw) < 8) $errors[] = 'Password must be at least 8 characters.';
        if (!$sid)   $errors[] = 'Student ID required.';
        // Check duplicate email
        $dup = $db->prepare("SELECT id FROM users WHERE email=?"); $dup->execute([$email]);
        if ($dup->fetch()) $errors[] = 'Email already registered.';

        if ($errors) { $flashMsg = implode(' ',$errors); $flashType='error'; }
        else {
            $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost'=>BCRYPT_COST]);
            $db->prepare("INSERT INTO users (full_name,email,password,role,student_id,avatar_color) VALUES (?,?,?,'student',?,?)")
               ->execute([$name,$email,$hash,$sid, rand(0,5)]);
            $newId = $db->lastInsertId();
            if ($modId) {
                $db->prepare("INSERT IGNORE INTO enrollments (student_id,module_id) VALUES (?,?)")->execute([$newId,$modId]);
            }
            $flashMsg = "Student {$name} added successfully.";
        }
    }
}

// ── Handle Delete (admin only) ───────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_student']) && $user['role']==='admin') {
    if (!csrf_verify()) { $flashMsg='Security error.'; $flashType='error'; }
    else {
        $delId = (int)($_POST['student_id_del'] ?? 0);
        $db->prepare("UPDATE users SET is_active=0 WHERE id=? AND role='student'")->execute([$delId]);
        $flashMsg = 'Student deactivated.'; $flashType = 'warning';
    }
}

// ── Fetch all students with attendance % ─────────────────────
$students = $db->query("
    SELECT u.id, u.full_name, u.student_id, u.email, u.avatar_color, u.is_active,
           GROUP_CONCAT(DISTINCT m.code ORDER BY m.code SEPARATOR ', ') AS modules,
           COUNT(DISTINCT a.id) AS attended,
           COUNT(DISTINCT s.id) AS total_sessions,
           ROUND(COUNT(DISTINCT CASE WHEN a.status IN ('present','late') THEN a.id END)/NULLIF(COUNT(DISTINCT s.id),0)*100,1) AS pct
    FROM users u
    LEFT JOIN enrollments e  ON e.student_id=u.id
    LEFT JOIN modules m      ON m.id=e.module_id
    LEFT JOIN sessions s     ON s.module_id=m.id
    LEFT JOIN attendance a   ON a.session_id=s.id AND a.student_id=u.id
    WHERE u.role='student'
    GROUP BY u.id
    ORDER BY u.full_name
")->fetchAll();

$allModules = $db->query("SELECT id,code,name FROM modules WHERE is_active=1 ORDER BY code")->fetchAll();
$avCols = ['av-c0','av-c1','av-c2','av-c3','av-c4','av-c5'];

include __DIR__ . '/../includes/header.php';
?>
<div class="page-fade">
<div class="pg-header-row">
  <div>
    <div class="pg-eyebrow">Registry</div>
    <div class="pg-title">Students</div>
    <div class="pg-sub">All enrolled students across all modules.</div>
  </div>
  <?php if ($user['role']==='admin'): ?>
  <button class="btn btn-prim" onclick="openModal('add-modal')">+ Add Student</button>
  <?php endif; ?>
</div>

<div class="filter-row">
  <input class="search-input" placeholder="🔍  Search name, ID, email…" oninput="filterTable(this,'stu-table')"/>
  <button class="chip active" data-filter onclick="filterByModule(this,'all','stu-table')">All</button>
  <?php foreach ($allModules as $m): ?>
  <button class="chip" data-filter onclick="filterByModule(this,'<?= e($m['code']) ?>','stu-table')"><?= e($m['code']) ?></button>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="tbl-wrap">
    <table id="stu-table">
      <thead>
        <tr>
          <th>Student</th><th>ID</th><th>Email</th><th>Modules</th>
          <th>Sessions</th><th>Attendance</th><th>Status</th>
          <?php if ($user['role']==='admin'): ?><th>Actions</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $s):
          $pct = $s['pct'] ?? 0;
          $col = pctColor((float)$pct);
        ?>
        <tr data-mod="<?= e($s['modules']) ?>">
          <td>
            <div class="flex-c gap-6">
              <div class="ac-av <?= $avCols[$s['avatar_color']%count($avCols)] ?>" style="width:28px;height:28px;font-size:11px;flex-shrink:0"><?= initials($s['full_name']) ?></div>
              <span style="font-weight:600"><?= e($s['full_name']) ?></span>
              <?php if (!$s['is_active']): ?><span class="badge b-red" style="margin-left:4px">Inactive</span><?php endif; ?>
            </div>
          </td>
          <td style="color:var(--t3)"><?= e($s['student_id']) ?></td>
          <td style="color:var(--t2);font-size:12px"><?= e($s['email']) ?></td>
          <td><span class="badge b-blue"><?= e($s['modules'] ?: '—') ?></span></td>
          <td style="color:var(--t2)"><?= (int)$s['attended'] ?>/<?= (int)$s['total_sessions'] ?></td>
          <td>
            <div class="prog-wrap">
              <div class="prog-track" style="min-width:70px">
                <div class="prog-fill" data-pct="<?= $pct ?>" style="width:0;background:<?= $col ?>"></div>
              </div>
              <span class="prog-pct" style="color:<?= $col ?>"><?= $pct>0?$pct.'%':'—' ?></span>
            </div>
          </td>
          <td><?= $pct>0 ? statusBadge((float)$pct) : '<span class="badge b-blue">No data</span>' ?></td>
          <?php if ($user['role']==='admin'): ?>
          <td>
            <div class="flex-c gap-6">
              <a href="<?= BASE_URL ?>/pages/student_detail.php?id=<?= $s['id'] ?>" class="btn btn-ghost btn-xs">View</a>
              <?php if ($s['is_active']): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
                <input type="hidden" name="delete_student" value="1"/>
                <input type="hidden" name="student_id_del" value="<?= $s['id'] ?>"/>
                <button type="button" class="btn btn-danger btn-xs" onclick="if(confirm('Deactivate <?= e(addslashes($s['full_name'])) ?>?')) this.closest('form').submit()">Deactivate</button>
              </form>
              <?php endif; ?>
            </div>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<!-- ADD STUDENT MODAL -->
<?php if ($user['role']==='admin'): ?>
<div id="add-modal" class="modal-overlay">
  <div class="modal">
    <div class="modal-head">
      <div><div class="modal-title">Add Student</div><div class="modal-sub">Enroll a new student into the portal</div></div>
      <button class="modal-close" onclick="closeModal('add-modal')">✕</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
      <input type="hidden" name="add_student" value="1"/>
      <div class="form-group"><label>Full Name</label><input class="form-control" name="full_name" placeholder="e.g. Chidi Okonkwo" required/></div>
      <div class="form-group"><label>Student ID</label><input class="form-control" name="student_id" placeholder="e.g. APT-2026-045" required/></div>
      <div class="form-group"><label>Email</label><input class="form-control" type="email" name="email" placeholder="student@aptech.edu.ng" required/></div>
      <div class="form-group"><label>Password</label><input class="form-control" type="password" name="password" placeholder="Min 8 characters" required/></div>
      <div class="form-group">
        <label>Enroll in Module</label>
        <select class="form-control" name="module_id">
          <option value="">— Optional —</option>
          <?php foreach ($allModules as $m): ?>
          <option value="<?= $m['id'] ?>"><?= e($m['code']) ?> · <?= e($m['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="flex-c gap-8 mt-2">
        <button type="submit" class="btn btn-prim">Add Student</button>
        <button type="button" class="btn btn-ghost" onclick="closeModal('add-modal')">Cancel</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
