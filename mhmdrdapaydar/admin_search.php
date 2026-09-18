<?php
session_start();
require_once __DIR__ . '/includes/admin_auth.php';
am_admin_guard();

$page_title = 'جستجو و مدیریت پیشرفته - پنل مدیریت انیمه موزیک';
$error = '';

try {
    $db_content = new PDO('sqlite:' . __DIR__ . '/../db/content.db');
    $db_content->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db_users = new PDO('sqlite:' . __DIR__ . '/../db/users.db');
    $db_users->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $musicTypes = $db_content->query("SELECT * FROM music_types")->fetchAll(PDO::FETCH_ASSOC);
    $animeList = $db_content->query("SELECT id, title_fa FROM anime_series ORDER BY title_fa")->fetchAll(PDO::FETCH_ASSOC);
    $singersList = $db_content->query("SELECT id, name FROM singers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

    $searchType = $_GET['type'] ?? 'anime';
    if (!in_array($searchType, ['anime', 'music', 'singer', 'user'], true)) $searchType = 'anime';

    $searchKeyword = trim($_GET['keyword'] ?? '');
    $animeFilter = $_GET['anime_filter'] ?? '';
    $musicTypeFilter = $_GET['music_type_filter'] ?? '';
    $singerFilter = $_GET['singer_filter'] ?? '';
    $statusFilter = $_GET['status_filter'] ?? '';

    // «عادی» در فرم یعنی مقدار آزاد 'free' در دیتابیس
    $dbStatus = $statusFilter === 'normal' ? 'free' : $statusFilter;

    $hasFilter = !empty($searchKeyword) || !empty($animeFilter) || !empty($musicTypeFilter) || !empty($singerFilter) || !empty($statusFilter);

    // صفحه‌بندی نتایج
    $perPage = 15;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $perPage;

    $results = [];
    $totalResults = 0;

    if ($hasFilter) {
        $where = [];
        $params = [];

        switch ($searchType) {
            case 'anime':
                if ($searchKeyword !== '') {
                    $where[] = "(title_fa LIKE ? OR title_en LIKE ? OR description LIKE ?)";
                    array_push($params, "%$searchKeyword%", "%$searchKeyword%", "%$searchKeyword%");
                }
                $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
                $countSql = "SELECT COUNT(*) FROM anime_series $whereSql";
                $dataSql = "SELECT * FROM anime_series $whereSql ORDER BY id DESC LIMIT $perPage OFFSET $offset";
                break;

            case 'music':
                if ($searchKeyword !== '') {
                    $where[] = "(ac.title LIKE ? OR asr.title_fa LIKE ? OR asr.title_en LIKE ?)";
                    array_push($params, "%$searchKeyword%", "%$searchKeyword%", "%$searchKeyword%");
                }
                if ($animeFilter !== '') { $where[] = "ac.anime_id = ?"; $params[] = (int)$animeFilter; }
                if ($musicTypeFilter !== '') { $where[] = "ac.music_type_id = ?"; $params[] = (int)$musicTypeFilter; }
                if ($singerFilter !== '') { $where[] = "ac.id IN (SELECT content_id FROM content_singers WHERE singer_id = ?)"; $params[] = (int)$singerFilter; }
                $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
                $join = "FROM anime_contents ac JOIN anime_series asr ON ac.anime_id = asr.id JOIN music_types mt ON ac.music_type_id = mt.id";
                $countSql = "SELECT COUNT(*) $join $whereSql";
                $dataSql = "SELECT ac.*, asr.title_fa AS anime_title, mt.name AS music_type_name $join $whereSql ORDER BY ac.id DESC LIMIT $perPage OFFSET $offset";
                break;

            case 'singer':
                if ($searchKeyword !== '') {
                    $where[] = "(name LIKE ? OR bio LIKE ?)";
                    array_push($params, "%$searchKeyword%", "%$searchKeyword%");
                }
                $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
                $countSql = "SELECT COUNT(*) FROM singers $whereSql";
                $dataSql = "SELECT s.*, (SELECT COUNT(*) FROM content_singers cs WHERE cs.singer_id = s.id) AS cnt FROM singers s $whereSql ORDER BY s.name LIMIT $perPage OFFSET $offset";
                break;

            case 'user':
                if ($searchKeyword !== '') {
                    $where[] = "(username LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
                    array_push($params, "%$searchKeyword%", "%$searchKeyword%", "%$searchKeyword%", "%$searchKeyword%");
                }
                if ($dbStatus !== '') { $where[] = "subscription_status = ?"; $params[] = $dbStatus; }
                $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
                $countSql = "SELECT COUNT(*) FROM users $whereSql";
                $dataSql = "SELECT * FROM users $whereSql ORDER BY created_at DESC LIMIT $perPage OFFSET $offset";
                break;
        }

        $searchDb = ($searchType === 'user') ? $db_users : $db_content;

        try {
            $stmt = $searchDb->prepare($countSql);
            $stmt->execute($params);
            $totalResults = (int)$stmt->fetchColumn();

            $stmt = $searchDb->prepare($dataSql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = 'خطا در جستجو: ' . $e->getMessage();
        }
    }

    $totalPages = (int)ceil($totalResults / $perPage);

    // ساخت لینک صفحه‌بندی با حفظ فیلترها
    function am_preserve_page($params, $page) {
        $q = array_merge($params, ['page' => $page]);
        return 'admin_search.php?' . http_build_query($q);
    }

    $pagerParams = $_GET;
    unset($pagerParams['page']);

    function am_build_pager2($pagerParams, $page, $totalPages) {
        if ($totalPages <= 1) return '';
        $mk = function ($p) use ($pagerParams) {
            return am_preserve_page($pagerParams, $p);
        };
        $h = '<div class="pagination">';
        $h .= $page > 1 ? '<a href="' . $mk($page - 1) . '"><i class="fas fa-chevron-right"></i></a>'
                        : '<span class="page-num page-disabled"><i class="fas fa-chevron-right"></i></span>';
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        if ($start > 1) { $h .= '<a href="' . $mk(1) . '">1</a>'; if ($start > 2) $h .= '<span class="page-gap">…</span>'; }
        for ($i = $start; $i <= $end; $i++) {
            $h .= $i == $page ? '<strong class="page-cur">' . $i . '</strong>' : '<a href="' . $mk($i) . '">' . $i . '</a>';
        }
        if ($end < $totalPages) { if ($end < $totalPages - 1) $h .= '<span class="page-gap">…</span>'; $h .= '<a href="' . $mk($totalPages) . '">' . $totalPages . '</a>'; }
        $h .= $page < $totalPages ? '<a href="' . $mk($page + 1) . '"><i class="fas fa-chevron-left"></i></a>'
                                 : '<span class="page-num page-disabled"><i class="fas fa-chevron-left"></i></span>';
        $h .= '</div>';
        return $h;
    }

    $pager = am_build_pager2($pagerParams, $page, $totalPages);
    $rangeInfo = 'نمایش ' . min($offset + 1, $totalResults) . ' تا ' . min($offset + $perPage, $totalResults) . ' از ' . number_format($totalResults) . ' نتیجه';

} catch (PDOException $e) {
    $error = 'خطا در اتصال به پایگاه داده: ' . $e->getMessage();
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
    <div class="brand"><i class="fas fa-search"></i><span>جستجو و مدیریت پیشرفته</span></div>
    <div class="header-actions">
      <a class="btn" href="admin.php"><i class="fas fa-arrow-right"></i> پنل اصلی</a>
      <a class="btn" href="logout.php"><i class="fas fa-sign-out-alt"></i> خروج</a>
    </div>
  </header>

  <?php if ($error): ?>
    <div class="message error-message"><i class="fas fa-exclamation-circle"></i><div><?= htmlspecialchars($error) ?></div></div>
  <?php endif; ?>

  <div class="card" style="padding:20px; margin-bottom:16px;">
    <form method="get" action="admin_search.php">
      <div class="form-grid">
        <div class="form-group">
          <label>نوع محتوا</label>
          <select name="type" class="form-control">
            <option value="anime" <?= $searchType == 'anime' ? 'selected' : '' ?>>انیمه‌ها</option>
            <option value="music" <?= $searchType == 'music' ? 'selected' : '' ?>>موزیک‌ها</option>
            <option value="singer" <?= $searchType == 'singer' ? 'selected' : '' ?>>خوانندگان</option>
            <option value="user" <?= $searchType == 'user' ? 'selected' : '' ?>>کاربران</option>
          </select>
        </div>

        <div class="form-group">
          <label>کلمه کلیدی</label>
          <input type="text" name="keyword" class="form-control" value="<?= htmlspecialchars($searchKeyword) ?>" placeholder="عبارت مورد نظر…">
        </div>

        <?php if ($searchType == 'music'): ?>
          <div class="form-group">
            <label>فیلتر انیمه</label>
            <select name="anime_filter" class="form-control">
              <option value="">همه انیمه‌ها</option>
              <?php foreach ($animeList as $a): ?>
                <option value="<?= $a['id'] ?>" <?= (string)$animeFilter === (string)$a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['title_fa']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>فیلتر نوع موزیک</label>
            <select name="music_type_filter" class="form-control">
              <option value="">همه انواع</option>
              <?php foreach ($musicTypes as $t): ?>
                <option value="<?= $t['id'] ?>" <?= (string)$musicTypeFilter === (string)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>فیلتر خواننده</label>
            <select name="singer_filter" class="form-control">
              <option value="">همه خوانندگان</option>
              <?php foreach ($singersList as $s): ?>
                <option value="<?= $s['id'] ?>" <?= (string)$singerFilter === (string)$s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <?php if ($searchType == 'user'): ?>
          <div class="form-group">
            <label>فیلتر وضعیت</label>
            <select name="status_filter" class="form-control">
              <option value="">همه وضعیت‌ها</option>
              <option value="vip" <?= $statusFilter === 'vip' ? 'selected' : '' ?>>VIP</option>
              <option value="normal" <?= $statusFilter === 'normal' ? 'selected' : '' ?>>عادی</option>
            </select>
          </div>
        <?php endif; ?>
      </div>

      <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
        <div class="page-info" style="margin:0;">
          <?php if ($totalResults > 0): ?><i class="fas fa-check-circle" style="color:var(--success)"></i> <?= number_format($totalResults) ?> نتیجه
          <?php elseif ($hasFilter): ?><i class="fas fa-info-circle"></i> نتیجه‌ای یافت نشد
          <?php else: ?><i class="fas fa-search"></i> معیارهای جستجو را وارد کنید<?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> اجرای جستجو</button>
      </div>
    </form>
  </div>

  <?php if (!empty($results)): ?>
  <div class="card" style="padding:20px;">
    <h3 class="section-title"><i class="fas fa-list"></i> نتایج جستجو</h3>

    <?php if ($searchType == 'anime'): ?>
    <div class="table-wrap"><div class="scroll-x"><table class="data-table">
      <thead><tr><th>ID</th><th>عنوان فارسی</th><th>عنوان انگلیسی</th><th>تاریخ</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($results as $a): ?>
        <tr>
          <td><?= $a['id'] ?></td>
          <td><?= htmlspecialchars($a['title_fa']) ?></td>
          <td><?= htmlspecialchars($a['title_en']) ?></td>
          <td><?= date('Y/m/d', strtotime($a['created_at'])) ?></td>
          <td style="white-space:nowrap;">
            <a href="edit_anime.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline btn-edit"><i class="fas fa-edit"></i></a>
            <a href="delete_anime.php?id=<?= $a['id'] ?>" onclick="return confirm('حذف انیمه و موزیک‌های آن؟')" class="btn btn-sm btn-outline btn-delete"><i class="fas fa-trash"></i></a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div></div>

    <?php elseif ($searchType == 'music'): ?>
    <div class="table-wrap"><div class="scroll-x"><table class="data-table">
      <thead><tr><th>ID</th><th>عنوان</th><th>انیمه</th><th>نوع</th><th>فصل/قسمت</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($results as $m): ?>
        <tr>
          <td><?= $m['id'] ?></td>
          <td><?= htmlspecialchars($m['title']) ?></td>
          <td><?= htmlspecialchars($m['anime_title']) ?></td>
          <td><span class="badge badge-secondary"><?= htmlspecialchars($m['music_type_name']) ?></span></td>
          <td><?= $m['season_number'] ? 'فصل ' . $m['season_number'] : '-' ?><?= $m['episode_number'] ? ' / قسمت ' . $m['episode_number'] : '' ?></td>
          <td style="white-space:nowrap;">
            <a href="../content.php?id=<?= $m['id'] ?>" target="_blank" class="btn btn-sm btn-outline btn-secondary"><i class="fas fa-eye"></i></a>
            <a href="edit_music.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline btn-edit"><i class="fas fa-edit"></i></a>
            <a href="delete_music.php?id=<?= $m['id'] ?>" onclick="return confirm('حذف این موزیک؟')" class="btn btn-sm btn-outline btn-delete"><i class="fas fa-trash"></i></a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div></div>

    <?php elseif ($searchType == 'singer'): ?>
    <div class="table-wrap"><div class="scroll-x"><table class="data-table">
      <thead><tr><th>ID</th><th>نام</th><th>تعداد آثار</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($results as $s): ?>
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
      </tbody>
    </table></div></div>

    <?php else: ?>
    <div class="table-wrap"><div class="scroll-x"><table class="data-table">
      <thead><tr><th>ID</th><th>نام کاربری</th><th>نام کامل</th><th>وضعیت</th><th>اعتبار</th><th>عضویت</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($results as $u): ?>
        <tr>
          <td><?= $u['id'] ?></td>
          <td><?= htmlspecialchars($u['username']) ?></td>
          <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
          <td><span class="badge <?= $u['subscription_status'] === 'vip' ? 'badge-warning' : 'badge-secondary' ?>"><?= $u['subscription_status'] === 'vip' ? 'VIP' : 'عادی' ?></span></td>
          <td><?php if ($u['subscription_status'] === 'vip' && !empty($u['subscription_end_date'])): ?><?= date('Y/m/d', strtotime($u['subscription_end_date'])) ?>
              <?php elseif ($u['subscription_status'] === 'vip'): ?><span class="badge badge-success">نامحدود</span><?php else: ?>—<?php endif; ?></td>
          <td><?= date('Y/m/d', strtotime($u['created_at'])) ?></td>
          <td style="white-space:nowrap;">
            <a href="edit_user.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline btn-edit"><i class="fas fa-edit"></i></a>
            <a href="delete_user.php?id=<?= $u['id'] ?>" onclick="return confirm('حذف این کاربر؟')" class="btn btn-sm btn-outline btn-delete"><i class="fas fa-trash"></i></a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div></div>
    <?php endif; ?>

    <div class="page-info"><?= $rangeInfo ?></div>
    <?= $pager ?>
  </div>

  <?php elseif ($hasFilter): ?>
    <div class="empty-state"><i class="fas fa-search"></i><h3>نتیجه‌ای یافت نشد</h3><p>معیارهای جستجو را تغییر دهید.</p></div>
  <?php else: ?>
    <div class="empty-state"><i class="fas fa-filter"></i><h3>جستجوی پیشرفته</h3><p>برای دیدن نتایج، فیلترها را وارد و جستجو را اجرا کنید.</p></div>
  <?php endif; ?>

  <footer><p>پنل مدیریت انیمه موزیک © <?= date('Y') ?></p></footer>
</div>
</body>
</html>
