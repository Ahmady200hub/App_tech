<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$pageTitle  = 'Notifications';
$activePage = 'notifications';
$db   = getDB();
$uid  = currentUser()['id'];

// Mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    if (csrf_verify()) {
        $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$uid]);
    }
}

// Mark single as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_one'])) {
    if (csrf_verify()) {
        $nid = (int)$_POST['nid'];
        $db->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([$nid,$uid]);
    }
}

$notifs = $db->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50");
$notifs->execute([$uid]);
$notifications = $notifs->fetchAll();
$unreadCount   = count(array_filter($notifications, fn($n) => !$n['is_read']));

include __DIR__ . '/../includes/header.php';
?>
<div class="page-fade">
<div class="pg-header-row">
  <div>
    <div class="pg-eyebrow">Inbox</div>
    <div class="pg-title">Notifications</div>
    <div class="pg-sub"><?= $unreadCount ?> unread notification<?= $unreadCount !== 1 ? 's' : '' ?></div>
  </div>
  <?php if ($unreadCount > 0): ?>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
    <input type="hidden" name="mark_read" value="1"/>
    <button type="submit" class="btn btn-sec">✓ Mark All Read</button>
  </form>
  <?php endif; ?>
</div>

<?php if (empty($notifications)): ?>
<div class="card">
  <div class="card-body" style="text-align:center;padding:3rem;color:var(--t3)">
    <div style="font-size:48px;margin-bottom:12px">🔔</div>
    <div style="font-size:15px;font-weight:600">No notifications yet</div>
  </div>
</div>
<?php else: ?>
<?php foreach ($notifications as $n): ?>
<div class="card" style="margin-bottom:10px;<?= !$n['is_read'] ? 'border-color:rgba(0,229,195,.25);' : '' ?>">
  <div class="card-body" style="display:flex;align-items:flex-start;gap:12px;padding:1rem 1.25rem">
    <div style="font-size:22px;margin-top:2px"><?= !$n['is_read'] ? '🔔' : '🔕' ?></div>
    <div style="flex:1">
      <div style="font-size:14px;line-height:1.6;<?= !$n['is_read'] ? 'font-weight:600;color:var(--t1)' : 'color:var(--t2)' ?>"><?= e($n['message']) ?></div>
      <div style="font-size:11px;color:var(--t3);margin-top:4px"><?= date('d M Y H:i', strtotime($n['created_at'])) ?></div>
    </div>
    <?php if (!$n['is_read']): ?>
    <form method="POST" style="flex-shrink:0">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"/>
      <input type="hidden" name="mark_one" value="1"/>
      <input type="hidden" name="nid" value="<?= $n['id'] ?>"/>
      <button type="submit" class="btn btn-ghost btn-xs">Mark read</button>
    </form>
    <?php else: ?>
    <span class="badge b-green" style="flex-shrink:0">Read</span>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
