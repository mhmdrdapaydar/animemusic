<?php
/**
 * انیمه موزیک — پاکسازی محتوای تکراری (موزیک‌های دوباره واردشده)
 *
 * موزیک‌های تکراری را بر اساس لینک فایل (music_file_url) گروه‌بندی می‌کند،
 * برای هر گروه قدیمی‌ترین ردیف را نگه می‌دارد، مجموع بازدیدها را روی آن جمع
 * می‌کند، پیوند خوانندگان را یکی می‌کند و بقیه ردیف‌ها را حذف می‌کند.
 *
 * صرفاً با درخواست POST و تأیید ادمین اجرا می‌شود.
 */

session_start();
require_once __DIR__ . '/includes/admin_auth.php';
am_admin_guard();

$page_title = 'پاکسازی محتوای تکراری - پنل مدیریت انیمه موزیک';
$message = '';
$message_type = '';
$duplicateGroups = 0;
$removedRows = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    // برای دیتابیس‌ها اتصال `db.php` مرکزی را استفاده نمی‌کنیم تا کنترل تراکنش دست خودمان باشد
    $db_content = new PDO('sqlite:' . __DIR__ . '/../db/content.db');
    $db_content->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db_content->exec('PRAGMA foreign_keys = ON');

    try {
        $db_content->beginTransaction();

        // یافتن لینک‌های تکراری
        $stmt = $db_content->query("
            SELECT music_file_url
            FROM anime_contents
            WHERE music_file_url IS NOT NULL AND music_file_url <> ''
            GROUP BY music_file_url
            HAVING COUNT(*) > 1
        ");
        $dupUrls = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $duplicateGroups = count($dupUrls);

        $findRows = $db_content->prepare("SELECT id, view_count FROM anime_contents WHERE music_file_url = ? ORDER BY id ASC");
        $sumViews = $db_content->prepare("UPDATE anime_contents SET view_count = ? WHERE id = ?");
        $deleteRow  = $db_content->prepare("DELETE FROM anime_contents WHERE id = ?");
        $deleteLinks = $db_content->prepare("DELETE FROM content_singers WHERE content_id = ?");
        $countLinks = $db_content->prepare("SELECT COUNT(*) FROM content_singers WHERE content_id = ? AND singer_id = ?");
        $addLink    = $db_content->prepare("INSERT INTO content_singers (content_id, singer_id) VALUES (?, ?)");

        foreach ($dupUrls as $url) {
            $findRows->execute([$url]);
            $rows = $findRows->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) < 2) continue;

            // قدیمی‌ترین ردیف (کمترین id) را نگه می‌داریم
            $keep = $rows[0];
            $keepId = (int)$keep['id'];
            $keepViews = (int)$keep['view_count'];
            $removeIds = [];

            foreach (array_slice($rows, 1) as $dup) {
                $keepViews += (int)$dup['view_count'];
                $removeIds[] = (int)$dup['id'];
            }

            // جمع بازدیدها روی ردیف نگه‌داشته‌شده
            $sumViews->execute([$keepViews, $keepId]);

            // یکی‌کردن پیوند خوانندگان: خواننده‌های ردیف‌های حذف‌شونده اگر در ردیف اصلی نبودند اضافه می‌شوند
            foreach ($removeIds as $remId) {
                $links = $db_content->query("SELECT singer_id FROM content_singers WHERE content_id = " . (int)$remId)->fetchAll(PDO::FETCH_COLUMN);
                foreach ($links as $sid) {
                    $countLinks->execute([$keepId, $sid]);
                    if ((int)$countLinks->fetchColumn() === 0) {
                        $addLink->execute([$keepId, $sid]);
                    }
                }
                // حذف پیوندهای ردیف تکراری و خود ردیف
                $deleteLinks->execute([$remId]);
                $deleteRow->execute([$remId]);
                $removedRows++;
            }
        }

        // حذف پیوندهای orphan (content_singers بدون موزیک والد) — از واردات/حذف‌های قدیمی
        $orphanLinks = $db_content->exec("
            DELETE FROM content_singers
            WHERE content_id NOT IN (SELECT id FROM anime_contents)
        ");

        $db_content->commit();
        $orphanInfo = ($orphanLinks !== false && $orphanLinks > 0)
            ? "<br>همچنین <b>$orphanLinks</b> پیوند خواننده‌ی یتیم (بدون موزیک) پاکسازی شد."
            : '';
        $message = "پاکسازی با موفقیت انجام شد: <b>$duplicateGroups</b> گروه تکراری پیدا و <b>$removedRows</b> ردیف اضافی حذف شد (بازدیدها و خوانندگان ادغام شدند)." . $orphanInfo;
        $message_type = 'success';
        $duplicateGroups = 0;
    } catch (Exception $e) {
        if ($db_content->inTransaction()) {
            $db_content->rollBack();
        }
        $message = 'خطا در پاکسازی: ' . $e->getMessage();
        $message_type = 'error';
    }
} else {
    // شمارش فقط برای نمایش وضعیت فعلی (بدون تغییر)
    try {
        $db_content = new PDO('sqlite:' . __DIR__ . '/../db/content.db');
        $db_content->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $duplicateGroups = (int)$db_content->query("
            SELECT COUNT(*) FROM (
                SELECT music_file_url
                FROM anime_contents
                WHERE music_file_url IS NOT NULL AND music_file_url <> ''
                GROUP BY music_file_url
                HAVING COUNT(*) > 1
            )
        ")->fetchColumn();

        $removedRows = (int)$db_content->query("
            SELECT SUM(c) FROM (
                SELECT COUNT(*) - 1 AS c
                FROM anime_contents
                WHERE music_file_url IS NOT NULL AND music_file_url <> ''
                GROUP BY music_file_url
                HAVING COUNT(*) > 1
            )
        ")->fetchColumn();
    } catch (PDOException $e) {
        $message = 'خطا در خواندن وضعیت دیتابیس: ' . $e->getMessage();
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title) ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/admin.css?v=4">
</head>
<body>
<div class="container">
  <header class="admin-header">
    <div class="brand"><i class="fas fa-broom"></i><span>پاکسازی محتوای تکراری</span></div>
    <div class="header-actions">
      <a class="btn" href="admin.php"><i class="fas fa-arrow-right"></i> پنل اصلی</a>
      <a class="btn" href="logout.php"><i class="fas fa-sign-out-alt"></i> خروج</a>
    </div>
  </header>

  <?php if ($message): ?>
    <div class="message <?= $message_type === 'success' ? 'success-message' : 'error-message' ?>">
      <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
      <div><?= $message ?></div>
    </div>
  <?php endif; ?>

  <div class="card" style="padding:20px;">
    <h3 class="section-title"><i class="fas fa-clone"></i> وضعیت فعلی</h3>
    <p style="color:var(--gray);">
      <?php if ($duplicateGroups > 0): ?>
        در حال حاضر <b style="color:var(--danger);"><?= number_format($duplicateGroups) ?></b> گروه موزیک تکراری
        (مجموعاً <b><?= number_format($removedRows) ?></b> ردیف اضافی) در دیتابیس وجود دارد.
      <?php else: ?>
        هیچ موزیک تکراری‌ای پیدا نشد — دیتابیس تمیز است. ✅
      <?php endif; ?>
    </p>

    <?php if ($duplicateGroups > 0): ?>
      <div class="message error-message" style="margin-top:10px;">
        <i class="fas fa-shield-alt"></i>
        <div>
          این عمل فقط ردیف‌های تکراری (بر اساس لینک یکسان فایل) را حذف می‌کند؛
          قدیمی‌ترین ردیف هر گروه نگه داشته می‌شود، تعداد بازدیدها جمع و خوانندگان ادغام می‌شوند.
          پیش از اجرا توصیه می‌شود از دیتابیس پشتیبان بگیرید.
        </div>
      </div>
      <form method="post" style="margin-top:14px;"
            onsubmit="return confirm('آیا از حذف <?= number_format($removedRows) ?> ردیف موزیک تکراری مطمئن هستید؟ این عمل قابل بازگشت نیست.');">
        <button type="submit" name="confirm" value="1" class="btn btn-danger"><i class="fas fa-broom"></i> پاکسازی موارد تکراری</button>
        <a href="backup_database.php" class="btn btn-light" style="margin-right:8px;"><i class="fas fa-download"></i> اول پشتیبان بگیر</a>
      </form>
    <?php else: ?>
      <a href="import_json.php" class="btn btn-primary"><i class="fas fa-upload"></i> رفتن به واردات JSON</a>
    <?php endif; ?>
  </div>

  <footer><p>پنل مدیریت انیمه موزیک © <?= date('Y') ?></p></footer>
</div>
</body>
</html>
