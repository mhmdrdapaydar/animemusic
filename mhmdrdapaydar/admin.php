<?php
session_start();
require_once __DIR__ . '/includes/admin_auth.php';
am_admin_guard();

$page_title = 'پنل مدیریت انیمه موزیک';
$error = '';

try {
    $db_content = new PDO('sqlite:' . __DIR__ . '/../db/content.db');
    $db_content->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db_users = new PDO('sqlite:' . __DIR__ . '/../db/users.db');
    $db_users->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- آمار کلی ---
    $animeCount  = (int)$db_content->query("SELECT COUNT(*) FROM anime_series")->fetchColumn();
    $musicCount  = (int)$db_content->query("SELECT COUNT(*) FROM anime_contents")->fetchColumn();
    $singerCount = (int)$db_content->query("SELECT COUNT(*) FROM singers")->fetchColumn();
    $userCount   = (int)$db_users->query("SELECT COUNT(*) FROM users")->fetchColumn();

    // --- همگام‌سازی VIP های منقضی (منطقه زمانی site توسط config ست شده) ---
    $expiredVipCount = (int)$db_users->query("
        SELECT COUNT(*) FROM users
        WHERE subscription_status = 'vip'
          AND subscription_end_date IS NOT NULL AND subscription_end_date <> ''
          AND subscription_end_date < date('now')
    ")->fetchColumn();

    $db_users->exec("
        UPDATE users SET subscription_status = 'free'
        WHERE subscription_status = 'vip'
          AND subscription_end_date IS NOT NULL AND subscription_end_date <> ''
          AND subscription_end_date < date('now')
    ");

    $vipCount = (int)$db_users->query("
        SELECT COUNT(*) FROM users
        WHERE subscription_status = 'vip'
          AND (subscription_end_date IS NULL OR subscription_end_date = '' OR subscription_end_date >= date('now'))
    ")->fetchColumn();

    // --- فاکتورهای سیستمی ---
    $dbSize = 0;
    if (file_exists(__DIR__ . '/../db/content.db')) $dbSize += filesize(__DIR__ . '/../db/content.db');
    if (file_exists(__DIR__ . '/../db/users.db'))   $dbSize += filesize(__DIR__ . '/../db/users.db');

    // --- داده‌های مورد نیاز فرم‌ها ---
    $musicTypes = $db_content->query("SELECT * FROM music_types ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    // همه انیمه‌ها برای dropdown انتخاب در فرم افزودن موزیک
    $allAnime = $db_content->query("SELECT id, title_fa, title_en FROM anime_series ORDER BY title_fa")->fetchAll(PDO::FETCH_ASSOC);

    // --- صفحه‌بندی (رفع مشکل «همه‌چیز یکجا بارگذاری شود») ---
    $perPage = 10;
    $validTabs = ['anime', 'music', 'singer', 'user', 'system'];
    $activeTab = $_GET['tab'] ?? 'anime';
    if (!in_array($activeTab, $validTabs, true)) $activeTab = 'anime';

    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $perPage;

    $tabTitles = ['anime' => 'انیمه‌ها', 'music' => 'موزیک‌ها', 'singer' => 'خوانندگان', 'user' => 'کاربران'];

    $totalItems = 0;
    $items = [];

    // جستجو داخل هر تب (برای پیدا کردن سریع محتوا وقتی حجم بالاست)
    $q = trim($_GET['q'] ?? '');

    switch ($activeTab) {
        case 'anime':
            if ($q !== '') {
                $like = '%' . $q . '%';
                $stmt = $db_content->prepare("
                    SELECT * FROM anime_series
                    WHERE title_fa LIKE :q OR title_en LIKE :q OR description LIKE :q
                    ORDER BY id DESC LIMIT :l OFFSET :o
                ");
                $stmt->bindValue(':q', $like);
                $stmt->bindValue(':l', $perPage, PDO::PARAM_INT);
                $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $st = $db_content->prepare("SELECT COUNT(*) FROM anime_series WHERE title_fa LIKE :q OR title_en LIKE :q OR description LIKE :q");
                $st->bindValue(':q', $like);
                $st->execute();
                $totalItems = (int)$st->fetchColumn();
            } else {
                $totalItems = $animeCount;
                $stmt = $db_content->prepare("SELECT * FROM anime_series ORDER BY id DESC LIMIT :l OFFSET :o");
                $stmt->bindValue(':l', $perPage, PDO::PARAM_INT);
                $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'music':
            $musicJoins = "
                FROM anime_contents ac
                JOIN anime_series asr ON ac.anime_id = asr.id
                JOIN music_types mt ON ac.music_type_id = mt.id
            ";
            if ($q !== '') {
                $like = '%' . $q . '%';
                $stmt = $db_content->prepare("
                    SELECT ac.*, asr.title_fa AS anime_title, mt.name AS music_type
                    $musicJoins
                    WHERE ac.title LIKE :q OR asr.title_fa LIKE :q OR asr.title_en LIKE :q OR mt.name LIKE :q
                    ORDER BY ac.id DESC LIMIT :l OFFSET :o
                ");
                $stmt->bindValue(':q', $like);
                $stmt->bindValue(':l', $perPage, PDO::PARAM_INT);
                $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $st = $db_content->prepare("
                    SELECT COUNT(*) $musicJoins
                    WHERE ac.title LIKE :q OR asr.title_fa LIKE :q OR asr.title_en LIKE :q OR mt.name LIKE :q
                ");
                $st->bindValue(':q', $like);
                $st->execute();
                $totalItems = (int)$st->fetchColumn();
            } else {
                $totalItems = $musicCount;
                $stmt = $db_content->prepare("
                    SELECT ac.*, asr.title_fa AS anime_title, mt.name AS music_type
                    $musicJoins
                    ORDER BY ac.id DESC LIMIT :l OFFSET :o
                ");
                $stmt->bindValue(':l', $perPage, PDO::PARAM_INT);
                $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'singer':
            if ($q !== '') {
                $like = '%' . $q . '%';
                $stmt = $db_content->prepare("
                    SELECT s.*, (SELECT COUNT(*) FROM content_singers cs WHERE cs.singer_id = s.id) AS cnt
                    FROM singers s
                    WHERE s.name LIKE :q OR s.bio LIKE :q
                    ORDER BY s.name LIMIT :l OFFSET :o
                ");
                $stmt->bindValue(':q', $like);
                $stmt->bindValue(':l', $perPage, PDO::PARAM_INT);
                $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $st = $db_content->prepare("SELECT COUNT(*) FROM singers WHERE name LIKE :q OR bio LIKE :q");
                $st->bindValue(':q', $like);
                $st->execute();
                $totalItems = (int)$st->fetchColumn();
            } else {
                $totalItems = $singerCount;
                $stmt = $db_content->prepare("
                    SELECT s.*, (SELECT COUNT(*) FROM content_singers cs WHERE cs.singer_id = s.id) AS cnt
                    FROM singers s
                    ORDER BY s.name LIMIT :l OFFSET :o
                ");
                $stmt->bindValue(':l', $perPage, PDO::PARAM_INT);
                $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'user':
            if ($q !== '') {
                $like = '%' . $q . '%';
                $stmt = $db_users->prepare("
                    SELECT * FROM users
                    WHERE username LIKE :q OR first_name LIKE :q OR last_name LIKE :q OR email LIKE :q
                    ORDER BY created_at DESC LIMIT :l OFFSET :o
                ");
                $stmt->bindValue(':q', $like);
                $stmt->bindValue(':l', $perPage, PDO::PARAM_INT);
                $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $st = $db_users->prepare("SELECT COUNT(*) FROM users WHERE username LIKE :q OR first_name LIKE :q OR last_name LIKE :q OR email LIKE :q");
                $st->bindValue(':q', $like);
                $st->execute();
                $totalItems = (int)$st->fetchColumn();
            } else {
                $totalItems = $userCount;
                $stmt = $db_users->prepare("SELECT * FROM users ORDER BY created_at DESC LIMIT :l OFFSET :o");
                $stmt->bindValue(':l', $perPage, PDO::PARAM_INT);
                $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;
    }

    $totalPages = (int)ceil($totalItems / $perPage);
    $pagerUrl = '?tab=' . urlencode($activeTab) . ($q !== '' ? '&q=' . urlencode($q) : '') . '&page=';

    // تولید لینک‌های صفحه‌بندی
    function admin_pager($base, $page, $totalPages) {
        if ($totalPages <= 1) return '';
        $h = '<div class="pagination">';
        // قبلی
        if ($page > 1) {
            $h .= '<a href="' . $base . ($page - 1) . '" aria-label="قبلی"><i class="fas fa-chevron-right"></i></a>';
        } else {
            $h .= '<span class="page-num page-disabled"><i class="fas fa-chevron-right"></i></span>';
        }
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        if ($start > 1) {
            $h .= '<a href="' . $base . '1">1</a>';
            if ($start > 2) $h .= '<span class="page-gap">…</span>';
        }
        for ($i = $start; $i <= $end; $i++) {
            if ($i == $page) {
                $h .= '<strong class="page-cur">' . $i . '</strong>';
            } else {
                $h .= '<a href="' . $base . $i . '">' . $i . '</a>';
            }
        }
        if ($end < $totalPages) {
            if ($end < $totalPages - 1) $h .= '<span class="page-gap">…</span>';
            $h .= '<a href="' . $base . $totalPages . '">' . $totalPages . '</a>';
        }
        // بعدی
        if ($page < $totalPages) {
            $h .= '<a href="' . $base . ($page + 1) . '" aria-label="بعدی"><i class="fas fa-chevron-left"></i></a>';
        } else {
            $h .= '<span class="page-num page-disabled"><i class="fas fa-chevron-left"></i></span>';
        }
        $h .= '</div>';
        return $h;
    }

    $pagerHtml = admin_pager($pagerUrl, $page, $totalPages);
    if ($q !== '') {
        $rangeInfo = $totalItems > 0
            ? number_format($totalItems) . ' نتیجه برای &laquo;' . htmlspecialchars($q) . '&raquo; — نمایش ' . min($offset + 1, $totalItems) . ' تا ' . min($offset + $perPage, $totalItems)
            : 'نتیجه‌ای برای &laquo;' . htmlspecialchars($q) . '&raquo; یافت نشد';
    } else {
        $rangeInfo = $totalItems > 0
            ? 'نمایش ' . min($offset + 1, $totalItems) . ' تا ' . min($offset + $perPage, $totalItems) . ' از ' . number_format($totalItems) . ' مورد'
            : 'موردی ثبت نشده است';
    }

} catch (PDOException $e) {
    $error = 'خطا در اتصال به پایگاه داده: ' . $e->getMessage();
}

// پیام‌های موفقیت/خطا از ریدایرکت‌های صفحات عملیاتی
$flashSuccess = '';
$flashError = '';
$successMessages = [
    '1' => 'عملیات با موفقیت انجام شد',
    'anime_added' => 'انیمه جدید با موفقیت اضافه شد',
    'music_added' => 'موزیک جدید با موفقیت اضافه شد',
    'singer_added' => 'خواننده جدید با موفقیت اضافه شد',
    'content_updated' => 'محتوا با موفقیت به‌روزرسانی شد',
    'content_deleted' => 'محتوا با موفقیت حذف شد',
];
if (isset($_GET['success'])) {
    $key = $_GET['success'];
    $flashSuccess = $successMessages[$key] ?? (isset($_GET['message']) ? urldecode($_GET['message']) : 'عملیات موفقیت‌آمیز بود');
}
$errorMessages = [
    'invalid_request' => 'درخواست نامعتبر',
    'invalid_request_method' => 'متد درخواست نامعتبر',
    'database_error' => 'خطای پایگاه داده',
    'content_not_found' => 'محتوا یافت نشد',
    'missing_required_field' => 'فیلد اجباری پر نشده است' . (isset($_GET['field']) ? ': ' . $_GET['field'] : ''),
    'operation_failed' => 'عملیات ناموفق بود',
    'delete_failed' => 'حذف ناموفق بود',
];
if (isset($_GET['error'])) {
    $key = $_GET['error'];
    $flashError = $errorMessages[$key] ?? 'خطا در انجام عملیات';
    if (!empty($_GET['message'])) {
        $flashError .= ' — ' . urldecode($_GET['message']);
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
    <div class="brand">
      <i class="fas fa-music"></i>
      <span>پنل مدیریت انیمه موزیک</span>
    </div>
    <div class="header-actions">
      <a class="btn" href="../index.php"><i class="fas fa-globe"></i> مشاهده سایت</a>
      <a class="btn" href="logout.php"><i class="fas fa-sign-out-alt"></i> خروج</a>
    </div>
  </header>

  <?php if ($error): ?>
    <div class="message error-message"><i class="fas fa-exclamation-circle"></i><div><?= htmlspecialchars($error) ?></div></div>
  <?php endif; ?>

  <?php if ($flashSuccess): ?>
    <div class="message success-message"><i class="fas fa-check-circle"></i><div><?= htmlspecialchars($flashSuccess) ?></div></div>
  <?php endif; ?>
  <?php if ($flashError): ?>
    <div class="message error-message"><i class="fas fa-exclamation-circle"></i><div><?= htmlspecialchars($flashError) ?></div></div>
  <?php endif; ?>

  <div class="stats-grid">
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-database"></i></div><div class="stat-body"><div class="stat-value"><?= round($dbSize/1024/1024, 2) ?> MB</div><div class="stat-title">حجم دیتابیس</div></div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-film"></i></div><div class="stat-body"><div class="stat-value"><?= number_format($animeCount) ?></div><div class="stat-title">انیمه‌ها</div></div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-music"></i></div><div class="stat-body"><div class="stat-value"><?= number_format($musicCount) ?></div><div class="stat-title">موزیک‌ها</div></div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-microphone"></i></div><div class="stat-body"><div class="stat-value"><?= number_format($singerCount) ?></div><div class="stat-title">خوانندگان</div></div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-users"></i></div><div class="stat-body"><div class="stat-value"><?= number_format($userCount) ?></div><div class="stat-title">کاربران</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="color:#d4a400;background:rgba(255,193,7,.15)"><i class="fas fa-crown"></i></div><div class="stat-body"><div class="stat-value" style="color:#c89100"><?= number_format($vipCount) ?></div><div class="stat-title">VIP فعال</div></div></div>
    <?php if ($expiredVipCount > 0): ?>
    <div class="stat-card" style="border-color:rgba(220,53,69,.35)">
      <div class="stat-icon" style="color:#dc3545;background:rgba(220,53,69,.12)"><i class="fas fa-hourglass-end"></i></div>
      <div class="stat-body"><div class="stat-value" style="color:#dc3545"><?= number_format($expiredVipCount) ?></div><div class="stat-title">VIP منقضی (آزاد شدند)</div></div>
    </div>
    <?php endif; ?>
  </div>

  <div class="tabs">
    <div class="tab <?= $activeTab === 'anime' ? 'active' : '' ?>" onclick="switchTab('anime', this)"><i class="fas fa-film"></i>انیمه‌ها</div>
    <div class="tab <?= $activeTab === 'music' ? 'active' : '' ?>" onclick="switchTab('music', this)"><i class="fas fa-music"></i>موزیک‌ها</div>
    <div class="tab <?= $activeTab === 'singer' ? 'active' : '' ?>" onclick="switchTab('singer', this)"><i class="fas fa-microphone"></i>خوانندگان</div>
    <div class="tab <?= $activeTab === 'user' ? 'active' : '' ?>" onclick="switchTab('user', this)"><i class="fas fa-users"></i>کاربران</div>
    <div class="tab <?= $activeTab === 'system' ? 'active' : '' ?>" onclick="switchTab('system', this)"><i class="fas fa-cog"></i>سیستم</div>
  </div>

  <!-- ============ تب انیمه‌ها ============ -->
  <div id="content-anime" class="tab-content <?= $activeTab === 'anime' ? 'active' : '' ?>">
    <div class="card" style="padding:20px;">
      <h3 class="section-title"><i class="fas fa-plus-circle"></i> افزودن انیمه جدید</h3>
      <form method="post" action="save_anime.php">
        <div class="form-grid">
          <div class="form-group"><label>عنوان فارسی</label><input type="text" name="title_fa" class="form-control" required></div>
          <div class="form-group"><label>عنوان انگلیسی</label><input type="text" name="title_en" class="form-control" required></div>
          <div class="form-group"><label>لینک تصویر پست</label><input type="text" name="poster_image_url" class="form-control" required></div>
        </div>
        <div class="form-group"><label>توضیحات</label><textarea name="description" class="form-control" rows="3"></textarea></div>
        <div class="form-group"><label>لینک‌های مهم (مثال: IMDb=https://imdb.com, MyAnimeList=https://myanimelist.net)</label><textarea name="important_links" class="form-control" rows="2"></textarea></div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> ذخیره انیمه</button>
      </form>

      <h3 class="section-title"><i class="fas fa-list"></i> لیست انیمه‌ها <span class="badge badge-primary"><?= number_format($animeCount) ?></span></h3>
      <?php if ($activeTab === 'anime'): ?>
      <form method="get" action="admin.php" class="search-inline">
        <input type="hidden" name="tab" value="anime">
        <input type="text" name="q" class="form-control" placeholder="جستجو در انیمه‌ها (عنوان فارسی/انگلیسی یا توضیحات)…" value="<?= htmlspecialchars($q) ?>">
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> جستجو</button>
        <?php if ($q !== ''): ?><a href="admin.php?tab=anime" class="btn btn-outline btn-delete"><i class="fas fa-times"></i> حذف</a><?php endif; ?>
      </form>
      <div class="table-wrap"><div class="scroll-x">
        <table class="data-table">
          <thead><tr><th>ID</th><th>عنوان فارسی</th><th>عنوان انگلیسی</th><th>تاریخ</th><th>عملیات</th></tr></thead>
          <tbody>
          <?php if (empty($items)): ?>
            <tr><td colspan="5" style="text-align:center;color:#888;">موردی یافت نشد</td></tr>
          <?php else: ?>
            <?php foreach ($items as $a): ?>
              <tr>
                <td><?= $a['id'] ?></td>
                <td><?= htmlspecialchars($a['title_fa']) ?></td>
                <td><?= htmlspecialchars($a['title_en']) ?></td>
                <td><?= date('Y/m/d', strtotime($a['created_at'])) ?></td>
                <td style="white-space:nowrap;">
                  <a href="edit_anime.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline btn-edit"><i class="fas fa-edit"></i></a>
                  <a href="delete_anime.php?id=<?= $a['id'] ?>" onclick="return confirm('حذف انیمه و همه موزیک‌هایش؟')" class="btn btn-sm btn-outline btn-delete"><i class="fas fa-trash"></i></a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div></div>
      <div class="page-info"><?= $rangeInfo ?></div>
      <?= $pagerHtml ?>
      <?php else: ?>
        <p style="color:#888;text-align:center;">برای دیدن لیست انیمه‌ها، روی تب «انیمه‌ها» کلیک کنید.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- ============ تب موزیک‌ها ============ -->
  <div id="content-music" class="tab-content <?= $activeTab === 'music' ? 'active' : '' ?>">
    <div class="card" style="padding:20px;">
      <h3 class="section-title"><i class="fas fa-plus-circle"></i> افزودن موزیک جدید</h3>
      <form method="post" action="save_music.php">
        <div class="form-grid">
          <div class="form-group">
            <label>انتخاب انیمه</label>
            <select name="anime_id" class="form-control" required>
              <option value="">— انتخاب انیمه —</option>
              <?php foreach ($allAnime as $a): ?>
                <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['title_fa']) ?> (<?= htmlspecialchars($a['title_en']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>نوع موزیک</label>
            <select name="music_type_id" class="form-control" required>
              <option value="">— انتخاب نوع —</option>
              <?php foreach ($musicTypes as $t): ?>
                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>فصل</label><input type="number" name="season_number" class="form-control" min="1" value="1"></div>
          <div class="form-group"><label>قسمت</label><input type="number" name="episode_number" class="form-control" min="1"></div>
        </div>
        <div class="form-grid">
          <div class="form-group"><label>عنوان موزیک</label><input type="text" name="title" class="form-control" required></div>
          <div class="form-group"><label>لینک فایل موزیک</label><input type="text" name="music_file_url" class="form-control" required></div>
          <div class="form-group"><label>لینک فایل ویدیو (اختیاری)</label><input type="text" name="video_file_url" class="form-control"></div>
          <div class="form-group"><label>لینک تصویر (اختیاری)</label><input type="text" name="image_url" class="form-control"></div>
        </div>
        <div class="form-grid">
          <div class="form-group"><label>مدت زمان (ثانیه)</label><input type="number" name="duration" class="form-control" min="0"></div>
          <div class="form-group"><label>خوانندگان (ID با کاما)</label><input type="text" name="singer_ids" class="form-control" placeholder="مثال: 1,5,8"></div>
        </div>
        <div class="form-group"><label>متن آهنگ (اختیاری)</label><textarea name="lyrics_text" class="form-control" rows="3"></textarea></div>
        <div class="form-group"><label>ترجمه فارسی (اختیاری)</label><textarea name="lyrics_translation" class="form-control" rows="3"></textarea></div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> ذخیره موزیک</button>
      </form>

      <h3 class="section-title"><i class="fas fa-list"></i> لیست موزیک‌ها <span class="badge badge-primary"><?= number_format($musicCount) ?></span></h3>
      <?php if ($activeTab === 'music'): ?>
      <form method="get" action="admin.php" class="search-inline">
        <input type="hidden" name="tab" value="music">
        <input type="text" name="q" class="form-control" placeholder="جستجو در موزیک‌ها (عنوان، انیمه، نوع)…" value="<?= htmlspecialchars($q) ?>">
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> جستجو</button>
        <?php if ($q !== ''): ?><a href="admin.php?tab=music" class="btn btn-outline btn-delete"><i class="fas fa-times"></i> حذف</a><?php endif; ?>
      </form>
      <div class="table-wrap"><div class="scroll-x">
        <table class="data-table">
          <thead><tr><th>ID</th><th>عنوان</th><th>انیمه</th><th>نوع</th><th>فصل/قسمت</th><th>عملیات</th></tr></thead>
          <tbody>
          <?php if (empty($items)): ?>
            <tr><td colspan="6" style="text-align:center;color:#888;">موردی یافت نشد</td></tr>
          <?php else: ?>
            <?php foreach ($items as $m): ?>
              <tr>
                <td><?= $m['id'] ?></td>
                <td><?= htmlspecialchars($m['title']) ?></td>
                <td><?= htmlspecialchars($m['anime_title']) ?></td>
                <td><span class="badge badge-secondary"><?= htmlspecialchars($m['music_type']) ?></span></td>
                <td><?= $m['season_number'] ? 'فصل ' . $m['season_number'] : '-' ?><?= $m['episode_number'] ? ' / قسمت ' . $m['episode_number'] : '' ?></td>
                <td style="white-space:nowrap;">
                  <a href="edit_music.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline btn-edit"><i class="fas fa-edit"></i></a>
                  <a href="delete_music.php?id=<?= $m['id'] ?>" onclick="return confirm('حذف این موزیک؟')" class="btn btn-sm btn-outline btn-delete"><i class="fas fa-trash"></i></a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div></div>
      <div class="page-info"><?= $rangeInfo ?></div>
      <?= $pagerHtml ?>
      <?php else: ?>
        <p style="color:#888;text-align:center;">برای دیدن لیست موزیک‌ها، روی تب «موزیک‌ها» کلیک کنید.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- ============ تب خوانندگان ============ -->
  <div id="content-singer" class="tab-content <?= $activeTab === 'singer' ? 'active' : '' ?>">
    <div class="card" style="padding:20px;">
      <h3 class="section-title"><i class="fas fa-plus-circle"></i> افزودن خواننده جدید</h3>
      <form method="post" action="save_singer.php">
        <div class="form-grid">
          <div class="form-group"><label>نام خواننده</label><input type="text" name="name" class="form-control" required></div>
          <div class="form-group"><label>لینک تصویر (اختیاری)</label><input type="text" name="image_url" class="form-control"></div>
        </div>
        <div class="form-group"><label>بیوگرافی (اختیاری)</label><textarea name="bio" class="form-control" rows="3"></textarea></div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> ذخیره خواننده</button>
      </form>

      <h3 class="section-title"><i class="fas fa-list"></i> لیست خوانندگان <span class="badge badge-primary"><?= number_format($singerCount) ?></span></h3>
      <?php if ($activeTab === 'singer'): ?>
      <form method="get" action="admin.php" class="search-inline">
        <input type="hidden" name="tab" value="singer">
        <input type="text" name="q" class="form-control" placeholder="جستجو در خوانندگان (نام یا بیوگرافی)…" value="<?= htmlspecialchars($q) ?>">
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> جستجو</button>
        <?php if ($q !== ''): ?><a href="admin.php?tab=singer" class="btn btn-outline btn-delete"><i class="fas fa-times"></i> حذف</a><?php endif; ?>
      </form>
      <div class="table-wrap"><div class="scroll-x">
        <table class="data-table">
          <thead><tr><th>ID</th><th>نام</th><th>تعداد آثار</th><th>عملیات</th></tr></thead>
          <tbody>
          <?php if (empty($items)): ?>
            <tr><td colspan="4" style="text-align:center;color:#888;">موردی یافت نشد</td></tr>
          <?php else: ?>
            <?php foreach ($items as $s): ?>
              <tr>
                <td><?= $s['id'] ?></td>
                <td><?= htmlspecialchars($s['name']) ?></td>
                <td><span class="badge badge-success"><?= (int)$s['cnt'] ?> اثر</span></td>
                <td style="white-space:nowrap;">
                  <a href="edit_singer.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline btn-edit"><i class="fas fa-edit"></i></a>
                  <a href="delete_singer.php?id=<?= $s['id'] ?>" onclick="return confirm('حذف این خواننده؟')" class="btn btn-sm btn-outline btn-delete"><i class="fas fa-trash"></i></a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div></div>
      <div class="page-info"><?= $rangeInfo ?></div>
      <?= $pagerHtml ?>
      <?php else: ?>
        <p style="color:#888;text-align:center;">برای دیدن لیست خوانندگان، روی تب «خوانندگان» کلیک کنید.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- ============ تب کاربران ============ -->
  <div id="content-user" class="tab-content <?= $activeTab === 'user' ? 'active' : '' ?>">
    <div class="card" style="padding:20px;">
      <h3 class="section-title"><i class="fas fa-users"></i> مدیریت کاربران <span class="badge badge-primary"><?= number_format($userCount) ?></span></h3>
      <?php if ($activeTab === 'user'): ?>
      <form method="get" action="admin.php" class="search-inline">
        <input type="hidden" name="tab" value="user">
        <input type="text" name="q" class="form-control" placeholder="جستجو در کاربران (نام کاربری، نام، ایمیل)…" value="<?= htmlspecialchars($q) ?>">
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> جستجو</button>
        <?php if ($q !== ''): ?><a href="admin.php?tab=user" class="btn btn-outline btn-delete"><i class="fas fa-times"></i> حذف</a><?php endif; ?>
      </form>
      <div class="table-wrap"><div class="scroll-x">
        <table class="data-table">
          <thead><tr><th>ID</th><th>نام کاربری</th><th>نام کامل</th><th>وضعیت</th><th>اعتبار</th><th>تاریخ عضویت</th><th>عملیات</th></tr></thead>
          <tbody>
          <?php if (empty($items)): ?>
            <tr><td colspan="7" style="text-align:center;color:#888;">کاربری یافت نشد</td></tr>
          <?php else: ?>
            <?php foreach ($items as $u): ?>
              <tr>
                <td><?= $u['id'] ?></td>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                <td><span class="badge <?= $u['subscription_status'] === 'vip' ? 'badge-warning' : 'badge-secondary' ?>"><?= $u['subscription_status'] === 'vip' ? 'VIP' : 'عادی' ?></span></td>
                <td>
                  <?php if ($u['subscription_status'] === 'vip' && !empty($u['subscription_end_date'])): ?>
                    <?= date('Y/m/d', strtotime($u['subscription_end_date'])) ?>
                  <?php elseif ($u['subscription_status'] === 'vip'): ?>
                    <span class="badge badge-success">نامحدود</span>
                  <?php else: ?>—<?php endif; ?>
                </td>
                <td><?= date('Y/m/d', strtotime($u['created_at'])) ?></td>
                <td style="white-space:nowrap;">
                  <a href="edit_user.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline btn-edit"><i class="fas fa-edit"></i></a>
                  <a href="delete_user.php?id=<?= $u['id'] ?>" onclick="return confirm('حذف این کاربر؟')" class="btn btn-sm btn-outline btn-delete"><i class="fas fa-trash"></i></a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div></div>
      <div class="page-info"><?= $rangeInfo ?></div>
      <?= $pagerHtml ?>
      <?php else: ?>
        <p style="color:#888;text-align:center;">برای دیدن لیست کاربران، روی تب «کاربران» کلیک کنید.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- ============ تب سیستم ============ -->
  <div id="content-system" class="tab-content <?= $activeTab === 'system' ? 'active' : '' ?>">
    <h3 class="section-title"><i class="fas fa-tools"></i> ابزارها</h3>
    <div class="system-grid">
      <div class="system-card"><h3><i class="fas fa-download" style="color:var(--primary)"></i> پشتیبان‌گیری</h3><p>دانلود نسخه پشتیبان از دیتابیس‌ها</p><a href="backup_database.php" class="btn btn-primary btn-block"><i class="fas fa-download"></i> دانلود پشتیبان</a></div>
      <div class="system-card"><h3><i class="fas fa-folder-open" style="color:var(--secondary)"></i> مدیریت فایل‌ها</h3><p>مرور و مدیریت فایل‌های سایت</p><a href="file_manager.php" class="btn btn-secondary btn-block"><i class="fas fa-folder-open"></i> مدیریت فایل‌ها</a></div>
      <div class="system-card"><h3><i class="fas fa-database" style="color:var(--info)"></i> همه محتوا</h3><p>مشاهده کامل و صفحه‌بندی‌شده محتوا</p><a href="admin_all_content.php" class="btn btn-light btn-block"><i class="fas fa-database"></i> مشاهده همه محتوا</a></div>
      <div class="system-card"><h3><i class="fas fa-search" style="color:var(--secondary)"></i> جستجوی پیشرفته</h3><p>جستجو و فیلتر در دیتابیس</p><a href="admin_search.php" class="btn btn-light btn-block"><i class="fas fa-search"></i> جستجو</a></div>
      <div class="system-card"><h3><i class="fas fa-upload" style="color:var(--warning)"></i> واردات JSON</h3><p>ایمپورت محتوا از فایل JSON (تکراری‌ها رد می‌شوند)</p><a href="import_json.php" class="btn btn-light btn-block"><i class="fas fa-upload"></i> واردات JSON</a></div>
      <div class="system-card"><h3><i class="fas fa-broom" style="color:var(--danger)"></i> پاکسازی تکراری‌ها</h3><p>حذف موزیک‌های دوباره واردشده از قبل</p><a href="clean_duplicates.php" class="btn btn-light btn-block"><i class="fas fa-broom"></i> پاکسازی تکراری‌ها</a></div>
      <div class="system-card"><h3><i class="fas fa-chart-pie" style="color:var(--success)"></i> آمار بازدید</h3><p>آمار بازدید صفحات سایت</p><a href="admin_stats.php" class="btn btn-light btn-block"><i class="fas fa-chart-pie"></i> آمار سایت</a></div>
      <div class="system-card"><h3><i class="fas fa-ad" style="color:var(--info)"></i> آمار تبلیغات</h3><p>آمار بازدید بخش تبلیغات</p><a href="admin_ads.php" class="btn btn-light btn-block"><i class="fas fa-ad"></i> آمار تبلیغات</a></div>
      <div class="system-card"><h3><i class="fas fa-bullhorn" style="color:var(--danger)"></i> رزرو تبلیغات</h3><p>وضعیت رزروهای تبلیغاتی</p><a href="adscheck.php" class="btn btn-light btn-block"><i class="fas fa-bullhorn"></i> رزرو تبلیغات</a></div>
    </div>
  </div>

  <footer>
    <p>پنل مدیریت انیمه موزیک | نسخه ۴.۱</p>
    <p>کلیه حقوق برای این پلتفرم محفوظ است © <?= date('Y') ?></p>
  </footer>
</div>

<script>
function switchTab(id, el) {
  // فعال/غیرفعال کردن ظاهر تب‌ها
  document.querySelectorAll('.tabs .tab').forEach(function (t) { t.classList.remove('active'); });
  document.querySelectorAll('.tab-content').forEach(function (c) { c.classList.remove('active'); });
  var content = document.getElementById('content-' + id);
  if (content) content.classList.add('active');
  // فعال کردن دکمه تب کلیک‌شده
  if (el) el.classList.add('active');
  // حفظ تب جاری و پاک کردن صفحه‌بندی و جستجوی قبلی
  var url = new URL(window.location.href);
  url.searchParams.set('tab', id);
  url.searchParams.delete('q');
  url.searchParams.delete('page');
  history.replaceState(null, '', url);
  window.location.href = url.toString();
}
</script>
</body>
</html>
