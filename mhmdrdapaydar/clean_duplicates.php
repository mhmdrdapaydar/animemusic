<?php
/**
 * انیمه موزیک — پاکسازی محتوای تکراری (موزیک‌های دوباره واردشده)
 *
 * موزیک‌های تکراری را بر اساس لینک فایل (music_file_url) گروه‌بندی می‌کند.
 * برای هر گروه قدیمی‌ترین ردیف (کمترین id) نگه داشته می‌شود، مجموع بازدیدها روی آن
 * جمع و پیوند خوانندگان یکی می‌شود و بقیه ردیف‌ها حذف می‌شوند.
 *
 * امکانات:
 *  - نمایش تک‌به‌تک گروه‌های تکراری (کدام ردیف نگه داشته / کدام حذف می‌شود)
 *  - صفحه‌بندی گروه‌ها
 *  - حذف انتخابی گروه‌ها با چک‌باکس + پاکسازی کامل یکجا
 */

session_start();
require_once __DIR__ . '/includes/admin_auth.php';
am_admin_guard();

$page_title = 'پاکسازی محتوای تکراری - پنل مدیریت انیمه موزیک';
$message = '';
$message_type = '';

/**
 * اتصال به دیتابیس محتوا (همان الگوی admin)
 */
function am_clean_db() {
    $db = new PDO('sqlite:' . __DIR__ . '/../db/content.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $db;
}

/**
 * لیست لینک‌های فایل تکراری (مرتب برای صفحه‌بندی پایدار).
 */
function am_clean_dup_urls(PDO $db) {
    $st = $db->query("
        SELECT music_file_url
        FROM anime_contents
        WHERE music_file_url IS NOT NULL AND music_file_url <> ''
        GROUP BY music_file_url
        HAVING COUNT(*) > 1
        ORDER BY music_file_url
    ");
    return $st->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * ردیف‌های یک گروه تکراری به همراه اطلاعات کامل برای نمایش.
 */
function am_clean_group_rows(PDO $db, $url) {
    $st = $db->prepare("
        SELECT ac.*,
               a.title_fa  AS anime_title,
               a.title_en  AS anime_title_en,
               mt.name     AS type_name,
               (SELECT GROUP_CONCAT(s.name, '، ')
                  FROM content_singers cs JOIN singers s ON s.id = cs.singer_id
                 WHERE cs.content_id = ac.id) AS singers_list
        FROM anime_contents ac
        LEFT JOIN anime_series a ON a.id = ac.anime_id
        LEFT JOIN music_types mt ON mt.id = ac.music_type_id
        WHERE ac.music_file_url = ?
        ORDER BY ac.id ASC
    ");
    $st->execute([$url]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * پردازش (ادغام و حذف) یک گروه تکراری.
 * @return int ردیف‌هایی که حذف شدند
 */
function am_clean_process_group(PDO $db, $url) {
    $rows = am_clean_group_rows($db, $url);
    if (count($rows) < 2) return 0;

    $keep = $rows[0];
    $keepId = (int)$keep['id'];
    $keepViews = (int)$keep['view_count'];
    $removeIds = [];
    foreach (array_slice($rows, 1) as $dup) {
        $keepViews += (int)$dup['view_count'];
        $removeIds[] = (int)$dup['id'];
    }

    // جمع بازدیدها روی ردیف نگه‌داشته‌شده
    $db->prepare("UPDATE anime_contents SET view_count = ? WHERE id = ?")->execute([$keepViews, $keepId]);

    // ادغام پیوند خوانندگان
    $countLinks = $db->prepare("SELECT COUNT(*) FROM content_singers WHERE content_id = ? AND singer_id = ?");
    $addLink    = $db->prepare("INSERT INTO content_singers (content_id, singer_id) VALUES (?, ?)");
    $delLinks   = $db->prepare("DELETE FROM content_singers WHERE content_id = ?");
    $delRow     = $db->prepare("DELETE FROM anime_contents WHERE id = ?");

    foreach ($removeIds as $remId) {
        $links = $db->query("SELECT singer_id FROM content_singers WHERE content_id = " . (int)$remId)->fetchAll(PDO::FETCH_COLUMN);
        foreach ($links as $sid) {
            $countLinks->execute([$keepId, (int)$sid]);
            if ((int)$countLinks->fetchColumn() === 0) {
                $addLink->execute([$keepId, (int)$sid]);
            }
        }
        $delLinks->execute([$remId]);
        $delRow->execute([$remId]);
    }
    return count($removeIds);
}

/**
 * ساخت صفحه‌بندی با پنجره محدود (هماهنگ با پنل).
 */
function am_clean_pager($base, $page, $totalPages) {
    if ($totalPages <= 1) return '';
    $h = '<div class="pagination">';
    $h .= $page > 1
        ? '<a href="' . $base . ($page - 1) . '"><i class="fas fa-chevron-right"></i></a>'
        : '<span class="page-num page-disabled"><i class="fas fa-chevron-right"></i></span>';
    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);
    if ($start > 1) {
        $h .= '<a href="' . $base . '1">1</a>';
        if ($start > 2) $h .= '<span class="page-gap">…</span>';
    }
    for ($i = $start; $i <= $end; $i++) {
        $h .= $i == $page
            ? '<strong class="page-cur">' . $i . '</strong>'
            : '<a href="' . $base . $i . '">' . $i . '</a>';
    }
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) $h .= '<span class="page-gap">…</span>';
        $h .= '<a href="' . $base . $totalPages . '">' . $totalPages . '</a>';
    }
    $h .= $page < $totalPages
        ? '<a href="' . $base . ($page + 1) . '"><i class="fas fa-chevron-left"></i></a>'
        : '<span class="page-num page-disabled"><i class="fas fa-chevron-left"></i></span>';
    $h .= '</div>';
    return $h;
}

/* ------------------------------------------------------------------------- *
 *  اجرای پاکسازی (POST)
 *  - action=clean_all        : پاکسازی کامل همه گروه‌ها
 *  - action=delete_selected  : پاکسازی فقط گروه‌های انتخاب‌شده (indices[])
 * ------------------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'clean_all' && isset($_POST['confirm'])) {
        $db = am_clean_db();
        $db->exec('PRAGMA foreign_keys = ON');
        try {
            $db->beginTransaction();
            $dups = am_clean_dup_urls($db);
            $groups = count($dups);
            $removed = 0;
            foreach ($dups as $url) {
                $removed += am_clean_process_group($db, $url);
            }
            $orphans = $db->exec("DELETE FROM content_singers WHERE content_id NOT IN (SELECT id FROM anime_contents)");
            $db->commit();
            $orphanInfo = ($orphans !== false && $orphans > 0) ? " — همچنین $orphans پیوند خواننده‌ی یتیم پاکسازی شد" : '';
            $message = "پاکسازی کامل انجام شد: <b>$groups</b> گروه تکراری، <b>$removed</b> ردیف اضافی حذف شد (بازدیدها و خوانندگان ادغام شدند)$orphanInfo.";
            $message_type = 'success';
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $message = 'خطا در پاکسازی: ' . $e->getMessage();
            $message_type = 'error';
        }
    } elseif ($action === 'delete_selected' && !empty($_POST['indices']) && is_array($_POST['indices'])) {
        $db = am_clean_db();
        $db->exec('PRAGMA foreign_keys = ON');
        $dups = am_clean_dup_urls($db);
        $chosen = [];
        foreach ($_POST['indices'] as $idx) {
            $idx = (int)$idx;
            if (isset($dups[$idx - 1])) $chosen[] = $dups[$idx - 1];
        }
        if (empty($chosen)) {
            $message = 'هیچ گروه معتبری انتخاب نشده است.';
            $message_type = 'error';
        } else {
            try {
                $db->beginTransaction();
                $removed = 0;
                foreach ($chosen as $url) {
                    $removed += am_clean_process_group($db, $url);
                }
                $db->commit();
                $message = "حذف انتخابی انجام شد: <b>" . count($chosen) . "</b> گروه انتخاب‌شده، <b>$removed</b> ردیف اضافی حذف شد.";
                $message_type = 'success';
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $message = 'خطا در حذف انتخابی: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }

    // PRG: پس از پاکسازی برای جلوگیری از ارسال دوباره، ریدایرکت می‌کنیم
    header('Location: clean_duplicates.php?' . ($message_type === 'success' ? 'done=1' : 'done=0'));
    exit;
}

if (isset($_GET['done'])) {
    $message = $_GET['done'] === '1'
        ? 'پاکسازی با موفقیت انجام شد.'
        : 'خطا در پاکسازی.';
    $message_type = $_GET['done'] === '1' ? 'success' : 'error';
}

/* ------------------------------------------------------------------------- *
 *  خواندن وضعیت برای نمایش
 * ------------------------------------------------------------------------- */
$db = am_clean_db();
$dupUrls = am_clean_dup_urls($db);
$totalGroups = count($dupUrls);
$totalRemove = 0;
foreach ($dupUrls as $u) {
    $totalRemove += max(0, count(am_clean_group_rows($db, $u)) - 1);
}

$perGroup = 10; // گروه در هر صفحه
$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages = (int)ceil($totalGroups / $perGroup);
if ($page > $totalPages) $page = max(1, $totalPages);
$offset = ($page - 1) * $perGroup;

$pageUrls = array_slice($dupUrls, $offset, $perGroup);
$groupsData = [];
$groupIndex = $offset; // شماره‌ی سراسری گروه (از ۱)
foreach ($pageUrls as $url) {
    $groupIndex++;
    $groupsData[] = [
        'index' => $groupIndex,
        'url'   => $url,
        'rows'  => am_clean_group_rows($db, $url),
    ];
}
$base = '?page=';
$pager = am_clean_pager($base, $page, $totalPages);
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
  <style>
    .dup-group { border: 1px solid var(--border); border-radius: 12px; padding: 14px 16px; margin-bottom: 14px; background: var(--surface); box-shadow: var(--shadow); }
    .dup-group .g-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
    .dup-group .g-title { font-weight: 700; font-size: 14px; display: flex; align-items: center; gap: 8px; }
    .dup-group .g-url { direction: ltr; display: inline-block; font-family: monospace; font-size: 12px; color: var(--gray); word-break: break-all; margin-top: 4px; }
    .dup-row { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 9px; margin-top: 8px; flex-wrap: wrap; border: 1px solid transparent; }
    .dup-row.keep { background: var(--success-soft, #e8f7ee); border-color: var(--success); }
    .dup-row.remove { background: var(--danger-soft); border-color: var(--danger); }
    .dup-row .r-main { flex: 1 1 320px; min-width: 280px; }
    .dup-row .r-title { font-weight: 600; font-size: 13.5px; }
    .dup-row .r-meta { font-size: 12px; color: var(--gray); margin-top: 3px; line-height: 1.7; }
    .dup-row .r-tags { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; margin-top: 4px; }
    .group-actions { display: flex; align-items: center; gap: 12px; margin-top: 14px; border-top: 1px dashed var(--border); padding-top: 12px; flex-wrap: wrap; }
    .select-all-row { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--gray); }
    .keep-badge, .remove-badge { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 20px; white-space: nowrap; }
    .keep-badge { background: var(--success); color: #fff; }
    .remove-badge { background: var(--danger); color: #fff; }
    .g-check { width: 18px; height: 18px; accent-color: var(--danger); cursor: pointer; }
    .url-badge { display:inline-block; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    @media (max-width: 640px) { .dup-row { align-items: flex-start; } }
  </style>
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
      <?php if ($totalGroups > 0): ?>
        در حال حاضر <b style="color:var(--danger);"><?= number_format($totalGroups) ?></b> گروه موزیک تکراری
        (مجموعاً <b><?= number_format($totalRemove) ?></b> ردیف اضافی) وجود دارد.
      <?php else: ?>
        هیچ موزیک تکراری‌ای پیدا نشد — دیتابیس تمیز است. ✅
      <?php endif; ?>
    </p>

    <?php if ($totalGroups > 0): ?>
      <div class="message error-message" style="margin-top:10px;">
        <i class="fas fa-shield-alt"></i>
        <div>
          در هر گروه، ردیف <b>سبز (نگه‌داشته می‌شود)</b> قدیمی‌ترین است و ردیف‌های <b>قرمز (حذف می‌شود)</b>
          با آن ادغام می‌شوند (بازدیدها جمع و خوانندگان یکی می‌شوند). می‌توانید گروه‌ها را تیک بزنید و
          فقط موارد دلخواه را حذف کنید یا همه را یکجا پاکسازی کنید.
        </div>
      </div>

      <form method="post" id="clean-form">
        <input type="hidden" name="action" id="clean-action" value="delete_selected">
        <div class="group-actions">
          <button type="submit" class="btn btn-danger"><i class="fas fa-broom"></i> حذف گروه‌های انتخاب‌شده</button>
          <a href="backup_database.php" class="btn btn-light"><i class="fas fa-download"></i> اول پشتیبان بگیر</a>
          <label class="select-all-row">
            <input type="checkbox" id="select-all" class="g-check">
            انتخاب همه گروه‌های این صفحه
          </label>
        </div>

        <div style="margin-top:14px;">
          <?php foreach ($groupsData as $g): ?>
            <?php $keepId = $g['rows'][0]['id'] ?? null; ?>
            <div class="dup-group">
              <div class="g-head">
                <div class="g-title">
                  <input type="checkbox" name="indices[]" value="<?= (int)$g['index'] ?>" class="g-check group-check">
                  <span class="badge badge-danger">گروه <?= (int)$g['index'] ?></span>
                  <span style="color:var(--dark);"><?= htmlspecialchars($g['rows'][0]['title'] ?? '') ?></span>
                  <span class="badge badge-secondary"><?= htmlspecialchars($g['rows'][0]['type_name'] ?? '—') ?></span>
                  <span style="font-size:12px;color:var(--gray);"><?= htmlspecialchars($g['rows'][0]['anime_title'] ?: ($g['rows'][0]['anime_title_en'] ?: '')) ?></span>
                </div>
                <span style="font-size:12px;color:var(--gray);"><?= count($g['rows']) ?> ردیف — ۱ نگه‌داشته + <?= count($g['rows']) - 1 ?> حذف</span>
              </div>
              <div class="g-url" title="<?= htmlspecialchars($g['url']) ?>"><?= htmlspecialchars($g['url']) ?></div>

              <?php foreach ($g['rows'] as $pos => $r): ?>
                <?php $isKeep = ((int)$r['id'] === (int)$keepId); ?>
                <div class="dup-row <?= $isKeep ? 'keep' : 'remove' ?>">
                  <span class="<?= $isKeep ? 'keep-badge' : 'remove-badge' ?>"><?= $isKeep ? 'نگه‌داشته می‌شود' : 'حذف می‌شود' ?></span>
                  <div class="r-main">
                    <div class="r-title">
                      #<?= $r['id'] ?> — <?= htmlspecialchars($r['title']) ?>
                      <span class="badge badge-secondary"><?= htmlspecialchars($r['type_name'] ?? '?') ?></span>
                    </div>
                    <div class="r-meta">
                      انیمه: <?= htmlspecialchars($r['anime_title'] ?: ($r['anime_title_en'] ?: '—')) ?>
                      <?php if (!empty($r['season_number']) || !empty($r['episode_number'])): ?>
                        | فصل <?= $r['season_number'] ?> / قسمت <?= $r['episode_number'] ?>
                      <?php endif; ?>
                      | بازدید: <?= number_format((int)$r['view_count']) ?>
                    </div>
                    <div class="r-tags">
                      <?php if (!empty($r['singers_list'])): ?>
                        <span class="badge badge-primary"><i class="fas fa-microphone"></i> <?= htmlspecialchars($r['singers_list']) ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div style="text-align:left;">
                    <a href="../content.php?id=<?= $r['id'] ?>" target="_blank" class="btn btn-sm btn-outline btn-secondary" title="مشاهده"><i class="fas fa-eye"></i></a>
                    <a href="edit_music.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline btn-edit" title="ویرایش"><i class="fas fa-edit"></i></a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>

          <?php if (empty($groupsData)): ?>
            <div class="empty-state"><i class="fas fa-check-circle"></i><h3>این صفحه خالی است</h3><p>به صفحه قبلی برگردید.</p></div>
          <?php endif; ?>
        </div>

        <div class="group-actions">
          <button type="submit" class="btn btn-danger"><i class="fas fa-broom"></i> حذف گروه‌های انتخاب‌شده</button>
          <button type="button" class="btn btn-light" id="clean-all-btn"><i class="fas fa-trash-alt"></i> پاکسازی کامل همه گروه‌ها</button>
        </div>
      </form>

      <div class="page-info">
        <?= number_format($totalGroups) ?> گروه تکراری — نمایش <?= min($offset + 1, $totalGroups) ?> تا <?= min($offset + $perGroup, $totalGroups) ?>
      </div>
      <?= $pager ?>
    <?php else: ?>
      <a href="import_json.php" class="btn btn-primary"><i class="fas fa-upload"></i> رفتن به واردات JSON</a>
    <?php endif; ?>
  </div>

  <footer><p>پنل مدیریت انیمه موزیک © <?= date('Y') ?></p></footer>
</div>

<script>
(function () {
  var selectAll = document.getElementById('select-all');
  if (selectAll) {
    selectAll.addEventListener('change', function () {
      document.querySelectorAll('.group-check').forEach(function (cb) { cb.checked = selectAll.checked; });
    });
  }
  var cleanAllBtn = document.getElementById('clean-all-btn');
  if (cleanAllBtn) {
    cleanAllBtn.addEventListener('click', function () {
      var checked = document.querySelectorAll('.group-check:checked').length;
      if (!confirm('آیا از پاکسازی کامل «همه گروه‌ها» مطمئن هستید؟ این عمل قابل بازگشت نیست.')) return;
      var form = document.getElementById('clean-form');
      form.action.value = 'clean_all';
      var confirmInput = document.createElement('input');
      confirmInput.type = 'hidden';
      confirmInput.name = 'confirm';
      confirmInput.value = '1';
      form.appendChild(confirmInput);
      form.submit();
    });
  }
})();
</script>
</body>
</html>
