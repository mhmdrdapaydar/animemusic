<?php
session_start();
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/payments.php';
am_admin_guard();
am_payments_migrate();

$db = am_db_users();

$notice = '';

/* ==== پردازش اقدامات ==== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $tid = (int)($_POST['ticket_id'] ?? 0);

    switch ($action) {
        case 'reply':
            $body = trim($_POST['reply_message'] ?? '');
            if ($tid > 0 && $body !== '') {
                am_ticket_message_add($tid, 'admin', $body);
            }
            break;
        case 'close':
            if ($tid > 0) am_ticket_close($tid);
            break;
        case 'delete':
            if ($tid > 0) am_ticket_delete($tid);
            break;
        case 'delete_old':
            $count = am_tickets_delete_older_than_month();
            $notice = $count . ' تیکت قدیمی‌تر از یک ماه حذف شد.';
            break;
    }
    header("Location: tickets.php" . ($notice !== '' ? '?notice=' . urlencode($notice) : ''));
    exit;
}

if (isset($_GET['notice']) && $_GET['notice'] !== '') {
    $notice = (string)$_GET['notice'];
}

// فیلتر وضعیت
$filter = $_GET['status'] ?? 'all';
$allowedFilters = ['all', 'open', 'closed'];
if (!in_array($filter, $allowedFilters, true)) $filter = 'all';

$sql = "SELECT t.*, u.username, u.first_name, u.last_name,
               (SELECT COUNT(*) FROM ticket_messages tm WHERE tm.ticket_id = t.id) AS msg_count
        FROM tickets t LEFT JOIN users u ON u.id = t.user_id";
if ($filter !== 'all') {
    $sql .= " WHERE t.status = :status";
}
$sql .= " ORDER BY
    CASE t.status WHEN 'open' THEN 0 ELSE 1 END,
    t.updated_at DESC";

$stmt = $db->prepare($sql);
if ($filter !== 'all') $stmt->bindValue(':status', $filter);
$stmt->execute();
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$counts = [
    'all'    => (int)$db->query("SELECT COUNT(*) FROM tickets")->fetchColumn(),
    'open'   => (int)$db->query("SELECT COUNT(*) FROM tickets WHERE status='open'")->fetchColumn(),
    'closed' => (int)$db->query("SELECT COUNT(*) FROM tickets WHERE status='closed'")->fetchColumn(),
];

// مشاهده‌ی جزئیات تیکت
$selectedTicket = null;
$selectedMessages = [];
$viewId = (int)($_GET['view'] ?? 0);
if ($viewId > 0) {
    $stmt = $db->prepare("SELECT t.*, u.username, u.first_name, u.last_name FROM tickets t LEFT JOIN users u ON u.id = t.user_id WHERE t.id = ?");
    $stmt->execute([$viewId]);
    $selectedTicket = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($selectedTicket) {
        $selectedMessages = am_ticket_messages($viewId);
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <title>مدیریت تیکت‌ها</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/admin.css?v=4">
  <style>
    .filter-pills { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
    .pill { background: var(--surface); border: 1px solid var(--border); color: var(--dark); padding: 7px 16px; border-radius: 30px; cursor: pointer; font-size: 13px; font-weight: 600; transition: var(--transition); font-family: inherit; text-decoration: none; display: inline-block; }
    .pill:hover { border-color: var(--primary); color: var(--primary-dark); }
    .pill.active { background: var(--primary); border-color: var(--primary); color: #fff; }
    .ticket-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    .ticket-table th, .ticket-table td { padding: 12px 10px; border-bottom: 1px solid var(--border); text-align: right; vertical-align: top; }
    .ticket-table th { background: var(--light-gray); }
    .badge { padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }
    .b-open { background: #e3f6ea; color: #1e7e34; }
    .b-closed { background: #e3e7ec; color: #6c757d; }
    .btn-soft { border: 1px solid transparent; background: var(--light-gray); color: var(--dark); padding: 7px 12px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 12.5px; font-family: inherit; transition: var(--transition); text-decoration: none; display: inline-block; }
    .btn-acc { background: #e3f6ea; color: #1e7e34; border-color: #c8ecd3; }
    .btn-rej { background: #fdecec; color: #bd2130; border-color: #f5c6cb; }
    .btn-info { background: #e6f0ff; color: #0056c9; border-color: #c6e0ff; }
    .thread { margin-top: 20px; }
    .msg { padding: 12px 14px; border-radius: 12px; margin-bottom: 10px; max-width: 85%; }
    .msg.admin { background: rgba(0,123,255,.1); border: 1px solid rgba(0,123,255,.2); margin-right: auto; }
    .msg.user { background: rgba(40,167,69,.1); border: 1px solid rgba(40,167,69,.2); margin-left: auto; }
    .msg-meta { font-size: 11px; color: var(--gray); margin-bottom: 6px; }
    .msg-body { font-size: 14px; line-height: 1.7; }
    .warning-box { background: #fff4d6; color: #9a6b00; border: 1px solid #ffc107; border-radius: 10px; padding: 12px 16px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
  </style>
</head>
<body>
<div class="container">
  <header class="admin-header">
    <div class="brand"><i class="fas fa-life-ring"></i><span>مدیریت تیکت‌ها</span></div>
    <div class="header-actions">
      <a class="btn" href="admin.php"><i class="fas fa-arrow-right"></i> پنل اصلی</a>
      <a class="btn" href="logout.php"><i class="fas fa-sign-out-alt"></i> خروج</a>
    </div>
  </header>

  <?php if ($notice !== ''): ?>
    <div class="warning-box"><i class="fas fa-info-circle"></i> <?= htmlspecialchars($notice) ?></div>
  <?php endif; ?>

  <?php if ($selectedTicket): ?>
    <div class="card" style="padding:20px;">
      <h3 style="font-size:16px; color:var(--dark); margin-bottom:6px;">
        <i class="fas fa-ticket-alt" style="color:var(--primary);"></i> <?= htmlspecialchars($selectedTicket['subject']) ?>
        <span class="badge <?= $selectedTicket['status'] === 'open' ? 'b-open' : 'b-closed' ?>" style="margin-right:8px;">
          <?= $selectedTicket['status'] === 'open' ? 'باز' : 'بسته شده' ?>
        </span>
      </h3>
      <p style="font-size:12.5px; color:var(--gray); margin-bottom:16px;">
        از: <?= htmlspecialchars(($selectedTicket['username'] ?? '') . ' — ' . ($selectedTicket['first_name'] ?? '') . ' ' . ($selectedTicket['last_name'] ?? '')) ?>
        | ایجاد: <?= htmlspecialchars($selectedTicket['created_at']) ?>
      </p>

      <div class="thread">
        <?php foreach ($selectedMessages as $m): ?>
          <div class="msg <?= $m['sender'] === 'admin' ? 'admin' : 'user' ?>">
            <div class="msg-meta"><?= $m['sender'] === 'admin' ? 'Admin' : 'کاربر' ?> · <?= htmlspecialchars($m['created_at']) ?></div>
            <div class="msg-body"><?= nl2br(htmlspecialchars($m['body'])) ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ($selectedTicket['status'] === 'open'): ?>
        <form method="post" style="margin-top:16px;">
          <input type="hidden" name="action" value="reply">
          <input type="hidden" name="ticket_id" value="<?= (int)$selectedTicket['id'] ?>">
          <textarea name="reply_message" rows="3" class="form-control" style="width:100%;margin-bottom:10px;" required placeholder="پاسخ شما…"></textarea>
          <button type="submit" class="btn-soft btn-info"><i class="fas fa-reply"></i> ارسال پاسخ</button>
          <button type="submit" name="action" value="close" onclick="return confirm('بستن این تیکت؟')" class="btn-soft"><i class="fas fa-lock"></i> بستن تیکت</button>
          <button type="submit" name="action" value="delete" onclick="return confirm('حذف این تیکت؟')" class="btn-soft btn-rej"><i class="fas fa-trash"></i> حذف تیکت</button>
        </form>
      <?php else: ?>
        <form method="post" style="margin-top:16px;">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="ticket_id" value="<?= (int)$selectedTicket['id'] ?>">
          <button type="submit" class="btn-soft btn-rej" onclick="return confirm('حذف این تیکت؟')"><i class="fas fa-trash"></i> حذف تیکت</button>
        </form>
      <?php endif; ?>
      <a href="tickets.php" class="btn-soft" style="margin-top:12px;"><i class="fas fa-arrow-right"></i> بازگشت به فهرست</a>
    </div>
  <?php else: ?>
    <div class="card" style="padding:20px;">
      <h3 style="font-size:16px; color:var(--dark); margin-bottom:12px;"><i class="fas fa-life-ring" style="color:var(--primary);"></i> تیکت‌های پشتیبانی</h3>

      <div class="filter-pills">
        <a class="pill <?= $filter === 'all' ? 'active' : '' ?>" href="?status=all">همه (<?= $counts['all'] ?>)</a>
        <a class="pill <?= $filter === 'open' ? 'active' : '' ?>" href="?status=open">باز (<?= $counts['open'] ?>)</a>
        <a class="pill <?= $filter === 'closed' ? 'active' : '' ?>" href="?status=closed">بسته (<?= $counts['closed'] ?>)</a>

        <form method="post" style="margin-right:auto; display:inline;">
          <input type="hidden" name="action" value="delete_old">
          <button type="submit" class="btn-soft btn-rej" onclick="return confirm('حذف همه تیکت‌های قدیمی‌تر از یک ماه؟')">
            <i class="fas fa-broom"></i> حذف تیکت‌های قدیمی‌تر از یک ماه
          </button>
        </form>
      </div>

      <div class="table-wrap"><div class="scroll-x">
      <table class="ticket-table">
        <thead>
          <tr><th>ID</th><th>موضوع</th><th>کاربر</th><th>وضعیت</th><th>پیام‌ها</th><th>آخرین به‌روزرسانی</th><th>عملیات</th></tr>
        </thead>
        <tbody>
          <?php foreach ($tickets as $t): ?>
            <tr>
              <td><?= (int)$t['id'] ?></td>
              <td><?= htmlspecialchars($t['subject']) ?></td>
              <td><?= htmlspecialchars(($t['username'] ?? '') . ' (' . ($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? '') . ')') ?></td>
              <td><span class="badge <?= $t['status'] === 'open' ? 'b-open' : 'b-closed' ?>"><?= $t['status'] === 'open' ? 'باز' : 'بسته' ?></span></td>
              <td><?= (int)$t['msg_count'] ?></td>
              <td><?= htmlspecialchars($t['updated_at']) ?></td>
              <td style="white-space:nowrap;">
                <a href="tickets.php?view=<?= (int)$t['id'] ?>&status=<?= htmlspecialchars($filter) ?>" class="btn-soft btn-info"><i class="fas fa-eye"></i> مشاهده</a>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="ticket_id" value="<?= (int)$t['id'] ?>">
                  <button type="submit" class="btn-soft btn-rej" onclick="return confirm('حذف این تیکت؟')"><i class="fas fa-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($tickets)): ?>
            <tr><td colspan="7" style="text-align:center;color:#888;">تیکتی یافت نشد.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
      </div></div>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
