<?php
session_start();
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/payments.php';
am_admin_guard();
am_payments_migrate();

$db = am_db_users();

/* ==== پردازش اقدامات ==== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pid = (int)($_POST['payment_id'] ?? 0);
    if ($pid > 0) {
        if ($action === 'approve') {
            am_payment_set_status($pid, 'approved');
        } elseif ($action === 'reject') {
            am_payment_set_status($pid, 'rejected');
        }
    }
    header("Location: payments.php");
    exit;
}

// فیلتر وضعیت
$filter = $_GET['status'] ?? 'all';
$allowedFilters = ['all', 'pending', 'approved', 'rejected'];
if (!in_array($filter, $allowedFilters, true)) $filter = 'all';

// اتصال پرداخت‌ها به نام کاربری
$sql = "SELECT p.*, u.username, u.first_name, u.last_name, u.subscription_status
        FROM payments p LEFT JOIN users u ON u.id = p.user_id";
if ($filter !== 'all') {
    $sql .= " WHERE p.status = :status";
}
$sql .= " ORDER BY
    CASE p.status WHEN 'pending' THEN 0 ELSE 1 END,
    p.id DESC";

$stmt = $db->prepare($sql);
if ($filter !== 'all') $stmt->bindValue(':status', $filter);
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$counts = [
    'all'      => (int)$db->query("SELECT COUNT(*) FROM payments")->fetchColumn(),
    'pending'  => (int)$db->query("SELECT COUNT(*) FROM payments WHERE status='pending'")->fetchColumn(),
    'approved' => (int)$db->query("SELECT COUNT(*) FROM payments WHERE status='approved'")->fetchColumn(),
    'rejected' => (int)$db->query("SELECT COUNT(*) FROM payments WHERE status='rejected'")->fetchColumn(),
];

$methodNames = ['card' => 'کارت به کارت', 'crypto' => 'ارز دیجیتال', 'telegram' => 'تلگرام', 'rubika' => 'روبیکا'];
$statusNames = ['pending' => 'در انتظار بررسی', 'approved' => 'تأیید شده', 'rejected' => 'رد شده'];
$statusClass = ['pending' => 'st-pending', 'approved' => 'st-accepted', 'rejected' => 'st-rejected'];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <title>مدیریت پرداخت‌ها</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/admin.css?v=4">
  <style>
    .filter-pills { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
    .pill { background: var(--surface); border: 1px solid var(--border); color: var(--dark); padding: 7px 16px; border-radius: 30px; cursor: pointer; font-size: 13px; font-weight: 600; transition: var(--transition); font-family: inherit; text-decoration: none; display: inline-block; }
    .pill:hover { border-color: var(--primary); color: var(--primary-dark); }
    .pill.active { background: var(--primary); border-color: var(--primary); color: #fff; }
    .pay-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; margin-top: 16px; }
    .pay-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px; box-shadow: var(--shadow); border-right: 4px solid var(--primary); }
    .pay-card h3 { font-size: 15px; color: var(--dark); margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
    .pay-meta { font-size: 12.5px; color: var(--gray); margin-bottom: 10px; }
    .pay-detail { font-size: 13.5px; margin-bottom: 8px; line-height: 1.8; }
    .status-pill { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }
    .st-pending { background: #fff4d6; color: #9a6b00; border: 1px solid #ffc107; }
    .st-accepted { background: #e3f6ea; color: #1e7e34; border: 1px solid #28a745; }
    .st-rejected { background: #fdecec; color: #bd2130; border: 1px solid #dc3545; }
    .pay-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px; }
    .btn-soft { border: 1px solid transparent; background: var(--light-gray); color: var(--dark); padding: 8px 14px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; font-family: inherit; flex: 1; min-width: 100px; transition: var(--transition); }
    .btn-acc { background: #e3f6ea; color: #1e7e34; border-color: #c8ecd3; }
    .btn-acc:hover { background: #d2efdb; }
    .btn-rej { background: #fdecec; color: #bd2130; border-color: #f5c6cb; }
    .btn-rej:hover { background: #f9d1d5; }
    .receipt-img { max-width: 100%; border-radius: 10px; margin-top: 10px; border: 1px solid var(--border); }
    .amount-big { font-size: 20px; font-weight: 800; direction: ltr; display: inline-block; color: var(--primary-dark); }
    @media (max-width: 640px) { .pay-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
<div class="container">
  <header class="admin-header">
    <div class="brand"><i class="fas fa-credit-card"></i><span>مدیریت پرداخت‌ها</span></div>
    <div class="header-actions">
      <a class="btn" href="admin.php"><i class="fas fa-arrow-right"></i> پنل اصلی</a>
      <a class="btn" href="logout.php"><i class="fas fa-sign-out-alt"></i> خروج</a>
    </div>
  </header>

  <div class="filter-pills">
    <a class="pill <?= $filter === 'all' ? 'active' : '' ?>" href="?status=all">همه (<?= $counts['all'] ?>)</a>
    <a class="pill <?= $filter === 'pending' ? 'active' : '' ?>" href="?status=pending">در انتظار (<?= $counts['pending'] ?>)</a>
    <a class="pill <?= $filter === 'approved' ? 'active' : '' ?>" href="?status=approved">تأیید شده (<?= $counts['approved'] ?>)</a>
    <a class="pill <?= $filter === 'rejected' ? 'active' : '' ?>" href="?status=rejected">رد شده (<?= $counts['rejected'] ?>)</a>
  </div>

  <div class="pay-grid">
    <?php foreach ($payments as $p): ?>
      <div class="pay-card">
        <h3><i class="fas fa-receipt"></i> پرداخت #<?= (int)$p['id'] ?></h3>
        <div class="pay-meta">
          کاربر: <strong><?= htmlspecialchars(($p['username'] ?? '') . ' — ' . ($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')) ?></strong>
          <br>تاریخ: <?= htmlspecialchars($p['created_at']) ?>
        </div>
        <div class="pay-detail">
          <strong>پلن:</strong> <?= htmlspecialchars($p['plan']) ?>
          &nbsp;|&nbsp; <strong>روش:</strong> <?= htmlspecialchars($methodNames[$p['method']] ?? $p['method']) ?>
          &nbsp;|&nbsp; <strong>مبلغ نمایشی:</strong> <?= htmlspecialchars($p['price_label'] ?? '—') ?>
        </div>
        <?php if (!empty($p['crypto_amount'])): ?>
          <div class="pay-detail"><strong>مبلغ یکتای کریپتو:</strong> <span class="amount-big"><?= htmlspecialchars($p['crypto_amount']) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($p['tx_hash'])): ?>
          <div class="pay-detail"><strong>هش تراکنش:</strong> <span dir="ltr"><?= htmlspecialchars($p['tx_hash']) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($p['receipt_path']) && file_exists($p['receipt_path'])): ?>
          <a href="receipt.php?id=<?= (int)$p['id'] ?>" target="_blank" rel="noopener" style="color:var(--secondary);font-size:12.5px;">
            <i class="fas fa-image"></i> مشاهده‌ی رسید
          </a>
        <?php endif; ?>

        <span class="status-pill <?= $statusClass[$p['status']] ?? 'st-pending' ?>"><?= htmlspecialchars($statusNames[$p['status']] ?? $p['status']) ?></span>

        <?php if ($p['status'] === 'pending'): ?>
          <div class="pay-actions">
            <form method="post" style="display:contents;">
              <input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>">
              <button type="submit" name="action" value="approve" class="btn-soft btn-acc" onclick="return confirm('تأیید و فعال‌سازی این پرداخت؟')"><i class="fas fa-check"></i> تأیید + فعال‌سازی VIP</button>
              <button type="submit" name="action" value="reject" class="btn-soft btn-rej" onclick="return confirm('رد این پرداخت؟')"><i class="fas fa-times"></i> رد پرداخت</button>
            </form>
          </div>
        <?php else: ?>
          <div class="pay-meta" style="margin-top:8px;">آخرین به‌روزرسانی: <?= htmlspecialchars($p['updated_at']) ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <?php if (empty($payments)): ?>
      <p style="color:#888; text-align:center; padding:30px;">هیچ پرداختی یافت نشد.</p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
