<?php
session_start();
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/payments.php';
am_admin_guard();

$saved = false;
$config = am_pay_config();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config = [
        'card_number'   => trim((string)($_POST['card_number'] ?? '')),
        'card_holder'   => trim((string)($_POST['card_holder'] ?? '')),
        'telegram_id'   => trim((string)($_POST['telegram_id'] ?? '')),
        'rubika_id'     => trim((string)($_POST['rubika_id'] ?? '')),
        'crypto_coin'   => trim((string)($_POST['crypto_coin'] ?? '')),
        'crypto_wallet' => trim((string)($_POST['crypto_wallet'] ?? '')),
    ];
    am_pay_config_save($config);
    $saved = true;
} else {
    $config = am_pay_config();
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <title>تنظیمات پرداخت</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/admin.css?v=4">
</head>
<body>
<div class="container">
  <header class="admin-header">
    <div class="brand"><i class="fas fa-cog"></i><span>تنظیمات پرداخت</span></div>
    <div class="header-actions">
      <a class="btn" href="admin.php"><i class="fas fa-arrow-right"></i> پنل اصلی</a>
      <a class="btn" href="logout.php"><i class="fas fa-sign-out-alt"></i> خروج</a>
    </div>
  </header>

  <div class="card" style="padding:20px;">
    <h3 style="font-size:16px; color:var(--dark); margin-bottom:6px;"><i class="fas fa-credit-card" style="color:var(--primary);"></i> تنظیم روش‌های پرداخت</h3>
    <p style="font-size:13px; color:var(--gray); margin-bottom:18px;">
      شماره کارت برای پرداخت کارت‌به‌کارت کاربران ایرانی، آیدی تلگرام/روبیکا برای پرداخت دستی، و ارز دیجیتال برای کاربران خارجی.
    </p>
    <?php if ($saved): ?>
      <div style="background:#e3f6ea;color:#1e7e34;padding:10px;border-radius:8px;margin-bottom:14px;"><i class="fas fa-check"></i> تنظیمات پرداخت ذخیره شد.</div>
    <?php endif; ?>
    <form method="POST">
      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px;">
        <div>
          <label style="display:block;font-size:13px;margin-bottom:6px;font-weight:600;">شماره کارت (کارت‌به‌کارت)</label>
          <input type="text" name="card_number" class="form-control" dir="ltr" placeholder="6037-XXXX-XXXX-XXXX"
                 value="<?= htmlspecialchars($config['card_number'] ?? '') ?>" style="width:100%;">
        </div>
        <div>
          <label style="display:block;font-size:13px;margin-bottom:6px;font-weight:600;">نام دارنده کارت</label>
          <input type="text" name="card_holder" class="form-control"
                 value="<?= htmlspecialchars($config['card_holder'] ?? '') ?>" style="width:100%;">
        </div>
        <div>
          <label style="display:block;font-size:13px;margin-bottom:6px;font-weight:600;">آیدی تلگرام (بدون @)</label>
          <input type="text" name="telegram_id" class="form-control" dir="ltr" placeholder="I_MHP_I"
                 value="<?= htmlspecialchars($config['telegram_id'] ?? '') ?>" style="width:100%;">
        </div>
        <div>
          <label style="display:block;font-size:13px;margin-bottom:6px;font-weight:600;">آیدی روبیکا (بدون @)</label>
          <input type="text" name="rubika_id" class="form-control" dir="ltr" placeholder="I_MHP_I"
                 value="<?= htmlspecialchars($config['rubika_id'] ?? '') ?>" style="width:100%;">
        </div>
        <div>
          <label style="display:block;font-size:13px;margin-bottom:6px;font-weight:600;">ارز دیجیتال (نمایش)</label>
          <input type="text" name="crypto_coin" class="form-control" dir="ltr" placeholder="USDT (TRC20)"
                 value="<?= htmlspecialchars($config['crypto_coin'] ?? '') ?>" style="width:100%;">
        </div>
        <div>
          <label style="display:block;font-size:13px;margin-bottom:6px;font-weight:600;">آدرس کیف پول کریپتو</label>
          <input type="text" name="crypto_wallet" class="form-control" dir="ltr" placeholder="TXXXXXXXX..."
                 value="<?= htmlspecialchars($config['crypto_wallet'] ?? '') ?>" style="width:100%;">
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="margin-top:16px;"><i class="fas fa-save"></i> ذخیره تنظیمات</button>
    </form>
  </div>
</div>
</body>
</html>
