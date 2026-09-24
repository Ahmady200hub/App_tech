<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin', 'lecturer');
$pageTitle  = 'Alerts';
$activePage = 'alerts';
$db        = getDB();
$user      = currentUser();
$flashMsg  = ''; $flashType = 'success';
$threshold = (int)ATTENDANCE_THRESHOLD;

// ── Send single notification ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_alert'])) {
    if (!csrf_verify()) { $flashMsg = 'Security error.'; $flashType = 'error'; }
    else {
        $uid = (int)($_POST['uid'] ?? 0);
        $msg = sanitize($_POST['msg'] ?? '');
        if ($uid && $msg) {
            $db->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")
               ->execute([$uid, $msg]);
            $flashMsg = 'Alert sent successfully.';
        }
    }
}

// ── Send bulk notifications ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_all'])) {
    if (!csrf_verify()) { $flashMsg = 'Security error.'; $flashType = 'error'; }
    else {
        $bulkStmt = $db->prepare("
            SELECT sub.uid, sub.mod_name
            FROM (
                SELECT
                    u.id AS uid,
                    m.name AS mod_name,
                    ROUND(
                        COALESCE(SUM(CASE WHEN a.status IN ('present','late') THEN 1 ELSE 0 END), 0)
                        / NULLIF(COUNT(s.id), 0) * 100
                    , 1) AS pct,
                    COUNT(s.id) AS total_sessions
                FROM enrollments e
                INNER JOIN users   u ON u.id = e.student_id
                INNER JOIN modules m ON m.id = e.module_id
                INNER JOIN sessions s ON s.module_id = m.id
                LEFT  JOIN attendance a ON a.session_id = s.id AND a.student_id = u.id
                WHERE u.is_active = 1
                GROUP BY u.id, m.id
            ) AS sub
            WHERE sub.total_sessions > 0
              AND sub.pct < :threshold
        ");
        $bulkStmt->execute([':threshold' => $threshold]);
        $rows = $bulkStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) $rows = [];

        $ins = $db->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        foreach ($rows as $r) {
            $ins->execute([
                $r['uid'],
                "⚠ Your attendance has dropped below {$threshold}% in {$r['mod_name']}. Please attend upcoming sessions to avoid being barred from exams."
            ]);
        }
        $flashMsg = count($rows) . ' alert(s) sent to at-risk students.';
    }
}

// ── Fetch at-risk list ────────────────────────────────────────
$atRiskStmt = $db->prepare("
    SELECT *
    FROM (
        SELECT
            u.id,
            u.full_name,
            u.student_id,
            u.avatar_color,
            u.email,
            m.code  AS mod_code,
            m.name  AS mod_name,
            COUNT(s.id) AS total_sessions,
            COALESCE(SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END), 0) AS absences,
            ROUND(
                COALESCE(SUM(CASE WHEN a.status IN ('present','late') THEN 1 ELSE 0 END), 0)
                / NULLIF(COUNT(s.id), 0) * 100
            , 1) AS pct
        FROM enrollments e
        INNER JOIN users   u ON u.id = e.student_id
        INNER JOIN modules m ON m.id = e.module_id
        INNER JOIN sessions s ON s.module_id = m.id
        LEFT  JOIN attendance a ON a.session_id = s.id AND a.student_id = u.id
        WHERE u.is_active = 1
        GROUP BY u.id, u.full_name, u.student_id, u.avatar_color, u.email, m.id, m.code, m.name
    ) AS sub
    WHERE sub.total_sessions > 0
      AND sub.pct < :threshold
    ORDER BY sub.pct ASC
");
$atRiskStmt->execute([':threshold' => $threshold]);
$risks = $atRiskStmt->fetchAll(PDO::FETCH_ASSOC);
if (!is_array($risks)) $risks = [];

include __DIR__ . '/../includes/header.php';
?>
<div class="page-fade">

<div class="pg-header-row">
  <div>
    <div class="pg-eyebrow">Intervention System</div>
    <div class="pg-title">Alerts</div>
    <div class="pg-sub">Students below the <?= $threshold ?>% attendance threshold — take action now.</div>
  </div>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
    <input type="hidden" name="send_all" value="1"/>
    <button type="submit" class="btn btn-prim"
      onclick="return confirm('Send alert to all <?= count($risks) ?> at-risk students?')">
      📨 Notify All (<?= count($risks) ?>)
    </button>
  </form>
</div>

<?php if (empty($risks)): ?>
<div class="card">
  <div class="card-body" style="text-align:center;padding:3rem;color:var(--t3)">
    <div style="font-size:40px;margin-bottom:10px">✅</div>
    <div style="font-weight:600">No students are currently at risk.</div>
    <div style="font-size:13px;margin-top:6px">All attendance is above <?= $threshold ?>%.</div>
  </div>
</div>
<?php else: ?>
<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>Student</th>
          <th>Module</th>
          <th>Attendance</th>
          <th>Absences</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($risks as $r): ?>
        <tr>
          <td>
            <div class="flex-c gap-6">
              <div class="ac-av av-c<?= (int)$r['avatar_color'] % 6 ?>" style="width:28px;height:28px;font-size:11px;flex-shrink:0">
                <?= e(initials($r['full_name'])) ?>
              </div>
              <div>
                <div style="font-weight:700;font-size:13px"><?= e($r['full_name']) ?></div>
                <div style="font-size:11px;color:var(--t3)"><?= e($r['student_id']) ?></div>
              </div>
            </div>
          </td>
          <td>
            <span class="badge b-blue"><?= e($r['mod_code']) ?></span>
            <span style="margin-left:6px;font-size:13px"><?= e($r['mod_name']) ?></span>
          </td>
          <td>
            <span class="badge <?= (float)$r['pct'] < 65 ? 'b-red' : 'b-amber' ?>"><?= $r['pct'] ?>%</span>
          </td>
          <td style="color:var(--red);font-weight:600"><?= (int)$r['absences'] ?> sessions</td>
          <td>
            <form method="POST" class="flex-c gap-6">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
              <input type="hidden" name="send_alert" value="1"/>
              <input type="hidden" name="uid" value="<?= (int)$r['id'] ?>"/>
              <input type="hidden" name="msg"
                value="⚠ Your attendance in <?= e(addslashes($r['mod_name'])) ?> is <?= $r['pct'] ?>% — below the required <?= $threshold ?>%. Please attend upcoming sessions."/>
              <button type="submit" class="btn btn-ghost btn-xs">Send Alert</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
