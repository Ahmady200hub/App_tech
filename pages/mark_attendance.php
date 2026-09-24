<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin','lecturer');
$pageTitle  = 'Mark Attendance';
$activePage = 'mark';
$db   = getDB();
$user = currentUser();
$flashMsg = ''; $flashType = 'success';

// ── Handle POST save ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    if (!csrf_verify()) { $flashMsg = 'Security token error.'; $flashType = 'error'; }
    else {
        $moduleId    = (int)($_POST['module_id'] ?? 0);
        $sessionDate = $_POST['session_date'] ?? date('Y-m-d');
        $sessionTime = $_POST['session_time'] ?? 'morning';
        $attendances = $_POST['attendance'] ?? [];

        if (!$moduleId || empty($attendances)) {
            $flashMsg = 'Please select a module and mark attendance.'; $flashType = 'error';
        } else {
            try {
                $db->beginTransaction();

                // Upsert session
                $sStmt = $db->prepare("
                    INSERT INTO sessions (module_id, session_date, session_time, marked_by)
                    VALUES (?,?,?,?)
                    ON DUPLICATE KEY UPDATE marked_by=VALUES(marked_by), id=LAST_INSERT_ID(id)
                ");
                $sStmt->execute([$moduleId, $sessionDate, $sessionTime, $user['id']]);
                $sessionId = (int)$db->lastInsertId();

                // Upsert each attendance record
                $aStmt = $db->prepare("
                    INSERT INTO attendance (session_id, student_id, status)
                    VALUES (?,?,?)
                    ON DUPLICATE KEY UPDATE status=VALUES(status), marked_at=NOW()
                ");
                foreach ($attendances as $studentId => $status) {
                    $allowed = ['P'=>'present','A'=>'absent','L'=>'late'];
                    $status  = $allowed[$status] ?? 'absent';
                    $aStmt->execute([$sessionId, (int)$studentId, $status]);
                }

                // Auto-create notifications for newly absent students
                foreach ($attendances as $studentId => $status) {
                    if ($status === 'A') {
                        // Check overall %
                        $pctStmt = $db->prepare("
                            SELECT ROUND(SUM(a2.status IN ('present','late'))/NULLIF(COUNT(a2.id),0)*100,1) AS pct
                            FROM enrollments e
                            JOIN sessions s2 ON s2.module_id=e.module_id
                            LEFT JOIN attendance a2 ON a2.session_id=s2.id AND a2.student_id=e.student_id
                            WHERE e.student_id=? AND e.module_id=?
                        ");
                        $pctStmt->execute([(int)$studentId, $moduleId]);
                        $pct = (float)$pctStmt->fetchColumn();
                        if ($pct > 0 && $pct < ATTENDANCE_THRESHOLD) {
                            $modName = $db->prepare("SELECT name FROM modules WHERE id=?");
                            $modName->execute([$moduleId]);
                            $mn = $modName->fetchColumn();
                            $db->prepare("INSERT INTO notifications (user_id,message) VALUES (?,?)")
                               ->execute([(int)$studentId, "⚠ Your attendance in $mn has dropped to {$pct}%. Minimum required: ".ATTENDANCE_THRESHOLD."%."]);
                        }
                    }
                }

                $db->commit();
                $flashMsg = 'Attendance saved successfully for ' . count($attendances) . ' students.';
            } catch (Exception $ex) {
                $db->rollBack();
                $flashMsg = 'Error saving: ' . $ex->getMessage(); $flashType = 'error';
            }
        }
    }
}

// ── Load modules for dropdown ────────────────────────────────
if ($user['role'] === 'admin') {
    $modStmt = $db->query("SELECT id,code,name FROM modules WHERE is_active=1 ORDER BY code");
} else {
    $modStmt = $db->prepare("SELECT id,code,name FROM modules WHERE is_active=1 AND lecturer_id=? ORDER BY code");
    $modStmt->execute([$user['id']]);
}
$allModules = $modStmt->fetchAll();

// ── Load students for selected module ────────────────────────
$selModId    = (int)($_GET['module_id'] ?? $_POST['module_id'] ?? 0);
$selDate     = $_GET['session_date'] ?? date('Y-m-d');
$selTime     = $_GET['session_time'] ?? 'morning';
$students    = [];
$existingAtt = [];

if ($selModId) {
    $stStmt = $db->prepare("
        SELECT u.id, u.full_name, u.student_id, u.avatar_color
        FROM enrollments e
        JOIN users u ON u.id=e.student_id
        WHERE e.module_id=? AND u.is_active=1
        ORDER BY u.full_name
    ");
    $stStmt->execute([$selModId]);
    $students = $stStmt->fetchAll();

    // Load any existing attendance for this session
    $eStmt = $db->prepare("
        SELECT a.student_id, a.status
        FROM attendance a
        JOIN sessions s ON s.id=a.session_id
        WHERE s.module_id=? AND s.session_date=? AND s.session_time=?
    ");
    $eStmt->execute([$selModId, $selDate, $selTime]);
    foreach ($eStmt->fetchAll() as $row) {
        $existingAtt[$row['student_id']] = strtoupper($row['status'][0]); // 'present'->'P'
    }
}

$avCols = ['av-c0','av-c1','av-c2','av-c3','av-c4','av-c5'];
$statusMap = ['present'=>'P','absent'=>'A','late'=>'L'];

include __DIR__ . '/../includes/header.php';
?>
<div class="page-fade">
<div class="pg-header">
  <div class="pg-eyebrow">Lecturer Interface</div>
  <div class="pg-title">Mark Attendance</div>
  <div class="pg-sub">Select a module and session, then record each student's status.</div>
</div>

<!-- FILTER FORM -->
<form method="GET" action="" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:1.5rem;align-items:flex-end">
  <div style="flex:1;min-width:200px">
    <label style="font-size:11px;font-weight:700;color:var(--t3);letter-spacing:1px;text-transform:uppercase;margin-bottom:6px;display:block">Module</label>
    <select name="module_id" class="form-control" onchange="this.form.submit()">
      <option value="">── Select Module ──</option>
      <?php foreach ($allModules as $m): ?>
      <option value="<?= $m['id'] ?>" <?= $selModId===$m['id']?'selected':'' ?>><?= e($m['code']) ?> · <?= e($m['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label style="font-size:11px;font-weight:700;color:var(--t3);letter-spacing:1px;text-transform:uppercase;margin-bottom:6px;display:block">Date</label>
    <input type="date" name="session_date" class="form-control" value="<?= e($selDate) ?>" onchange="this.form.submit()"/>
  </div>
  <div>
    <label style="font-size:11px;font-weight:700;color:var(--t3);letter-spacing:1px;text-transform:uppercase;margin-bottom:6px;display:block">Session</label>
    <select name="session_time" class="form-control" onchange="this.form.submit()">
      <option value="morning"   <?= $selTime==='morning'?'selected':'' ?>>Morning</option>
      <option value="afternoon" <?= $selTime==='afternoon'?'selected':'' ?>>Afternoon</option>
      <option value="evening"   <?= $selTime==='evening'?'selected':'' ?>>Evening</option>
    </select>
  </div>
</form>

<?php if (!$selModId): ?>
<div style="text-align:center;padding:4rem;color:var(--t3)">
  <div style="font-size:48px;margin-bottom:12px">📋</div>
  <div style="font-size:15px;font-weight:600">Select a module above to load the student list</div>
</div>
<?php elseif (empty($students)): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:3rem;color:var(--t3)">No students enrolled in this module yet.</div></div>
<?php else: ?>

<!-- ATTENDANCE FORM -->
<form method="POST" action="" id="att-form">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
  <input type="hidden" name="save_attendance" value="1"/>
  <input type="hidden" name="module_id"    value="<?= $selModId ?>"/>
  <input type="hidden" name="session_date" value="<?= e($selDate) ?>"/>
  <input type="hidden" name="session_time" value="<?= e($selTime) ?>"/>

  <div class="flex-c gap-8" style="margin-bottom:1rem;flex-wrap:wrap">
    <span class="badge b-blue"   id="cnt-total"><?= count($students) ?> students</span>
    <span class="badge b-green"  id="cnt-present">0 present</span>
    <span class="badge b-red"    id="cnt-absent">0 absent</span>
    <span class="badge b-amber"  id="cnt-late">0 late</span>
    <div class="flex-c gap-8 ml-auto">
      <button type="button" class="btn btn-sec btn-sm" onclick="markAll('P')">All Present</button>
      <button type="button" class="btn btn-sec btn-sm" onclick="markAll('A')">All Absent</button>
    </div>
  </div>

  <div class="att-grid">
    <?php foreach ($students as $i => $s):
      $existing = $existingAtt[$s['id']] ?? 'P';
    ?>
    <div class="att-card">
      <div class="ac-av <?= $avCols[$s['avatar_color'] % count($avCols)] ?>"><?= initials($s['full_name']) ?></div>
      <div class="ac-info">
        <div class="ac-name"><?= e($s['full_name']) ?></div>
        <div class="ac-id"><?= e($s['student_id']) ?></div>
      </div>
      <input type="hidden" name="attendance[<?= $s['id'] ?>]" id="att-<?= $s['id'] ?>" value="<?= $existing ?>" data-sid="<?= $s['id'] ?>"/>
      <div class="att-pills">
        <button type="button" class="att-pill <?= $existing==='P'?'p-on':'' ?>" id="pb-P-<?= $s['id'] ?>" onclick="setAtt(<?= $s['id'] ?>,'P')">P</button>
        <button type="button" class="att-pill <?= $existing==='L'?'l-on':'' ?>" id="pb-L-<?= $s['id'] ?>" onclick="setAtt(<?= $s['id'] ?>,'L')">L</button>
        <button type="button" class="att-pill <?= $existing==='A'?'a-on':'' ?>" id="pb-A-<?= $s['id'] ?>" onclick="setAtt(<?= $s['id'] ?>,'A')">A</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="flex-c gap-8" style="margin-top:1.5rem;flex-wrap:wrap">
    <button type="submit" class="btn btn-prim">💾 Save Attendance</button>
    <button type="button" class="btn btn-ghost" onclick="markAll('P')">Reset All to Present</button>
  </div>
</form>
<script>document.addEventListener('DOMContentLoaded', updateAttCounts);</script>
<?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
