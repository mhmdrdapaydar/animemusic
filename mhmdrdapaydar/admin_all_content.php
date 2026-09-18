<?php
session_start();
require_once __DIR__ . '/includes/admin_auth.php';
am_admin_guard();

$page_title = 'مدیریت همه محتوا - پنل مدیریت انیمه موزیک';
$error = '';

try {
    $db_content = new PDO('sqlite:' . __DIR__ . '/../db/content.db');
    $db_content->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db_users = new PDO('sqlite:' . __DIR__ . '/../db/users.db');
    $db_users->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // شمارش‌ها (یک بار)
    $counts = [
        'anime'  => (int)$db_content->query("SELECT COUNT(*) FROM anime_series")->fetchColumn(),
        'music'  => (int)$db_content->query("SELECT COUNT(*) FROM anime_contents")->fetchColumn(),
        'singer' => (int)$db_content->query("SELECT COUNT(*) FROM singers")->fetchColumn(),
        'user'   => (int)$db_users->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    ];

    $type = $_GET['type'] ?? 'anime';
    if (!in_array($type, ['anime', 'music', 'singer', 'user'], true)) {
        $type = 'anime';
    }

    $perPage = 15;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $perPage;

    // جستجو داخل بخش جاری
    $q = trim($_GET['q'] ?? '');

    $totalItems = $counts[$type];
    $items = [];

    switch ($type) {
        case 'anime':
            if ($q !== '') {
                $like = '%' . $q . '%';
                $stmt = $db_content->prepare("SELECT * FROM anime_series WHERE title_fa LIKE :q OR title_en LIKE :q OR description LIKE :q ORDER BY id DESC LIMIT :l OFFSET :o");
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
                    SELECT ac.*, asr.title_fa AS anime_title, mt.name AS music_type_name
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
                $stmt = $db_content->prepare("
                    SELECT ac.*, asr.title_fa AS anime_title, mt.name AS music_type_name
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
                $stmt = $db_content->prepare("
                    SELECT s.*, (SELECT COUNT(*) FROM content_singers cs WHERE cs.singer_id = s.id) AS cnt
                    FROM singers s ORDER BY s.name LIMIT :l OFFSET :o
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
                $stmt = $db_users->prepare("SELECT * FROM users ORDER BY created_at DESC LIMIT :l OFFSET :o");
                $stmt->bindValue(':l', $perPage, PDO::PARAM_INT);
                $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;
    }

    $totalPages = (int)ceil($totalItems / $perPage);
    $base = '?type=' . urlencode($type) . ($q !== '' ? '&q=' . urlencode($q) : '') . '&page=';
    $typeNames = ['anime' => 'انیمه‌ها', 'music' => 'موزیک‌ها', 'singer' => 'خوانندگان', 'user' => 'کاربران'];

    // ساخت صفحه‌بندی با پنجره محدود (نه همه شماره‌ها)
    function am_build_pager($base, $page, $totalPages) {
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

    $pager = am_build_pager($base, $page, $totalPages);
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
    <div class="brand"><i class="fas fa-database"></i><span>مدیریت همه محتواها</span></div>
    <div class="header-actions">
      <a class="btn" href="admin.php"><i class="fas fa-arrow-right"></i> پنل اصلی</a>
      <a class="btn" href="logout.php"><i class="fas fa-sign-out-alt"></i> خروج</a>
    </div>
  </header>

  <?php if ($error): ?>
    <div class="message error-message"><i class="fas fa-exclamation-circle"></i><div><?= htmlspecialchars($error) ?></div></div>
  <?php endif; ?>

  <div class="tabs">
    <?php foreach (['anime' => 'film', 'music' => 'music', 'singer' => 'microphone', 'user' => 'users'] as $k => $icon): ?>
      <a class="tab <?= $type === $k ? 'active' : '' ?>" href="?type=<?= $k ?>">
        <i class="fas fa-<?= $icon ?>"></i><?= $typeNames[$k] ?> (<?= number_format($counts[$k]) ?>)
      </a>
    <?php endforeach; ?>
  </div>

  <div class="card" style="padding:20px;">
    <h3 class="section-title"><i class="fas fa-list"></i> لیست <?= $typeNames[$type] ?></h3>

    <form method="get" action="admin_all_content.php" class="search-inline">
      <input type="hidden" name="type" value="<?= $type ?>">
      <input type="text" name="q" class="form-control" placeholder="جستجو در <?= $typeNames[$type] ?>…" value="<?= htmlspecialchars($q) ?>">
      <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> جستجو</button>
      <?php if ($q !== ''): ?><a href="admin_all_content.php?type=<?= $type ?>" class="btn btn-outline btn-delete"><i class="fas fa-times"></i> حذف</a><?php endif; ?>
    </form>

    <?php if (empty($items)): ?>
      <div class="empty-state">
        <i class="<?= $q !== '' ? 'fas fa-search' : 'fas fa-inbox' ?>"></i>
        <h3><?= $q !== '' ? 'نتیجه‌ای یافت نشد' : 'محتوایی وجود ندارد' ?></h3>
        <p><?= $q !== '' ? 'عبارت دیگری را امتحان کنید.' : 'هنوز هیچ موردی ثبت نشده است.' ?></p>
      </div>
    <?php else: ?>

      <?php if ($type === 'anime'): ?>
      <div class="table-wrap"><div class="scroll-x"><table class="data-table">
        <thead><tr><th>ID</th><th>عنوان فارسی</th><th>عنوان انگلیسی</th><th>تاریخ</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($items as $a): ?>
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

      <?php elseif ($type === 'music'): ?>
      <div class="table-wrap"><div class="scroll-x"><table class="data-table">
        <thead><tr><th>ID</th><th>عنوان</th><th>انیمه</th><th>نوع</th><th>فصل/قسمت</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($items as $m): ?>
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

      <?php elseif ($type === 'singer'): ?>
      <div class="table-wrap"><div class="scroll-x"><table class="data-table">
        <thead><tr><th>ID</th><th>نام</th><th>تعداد آثار</th><th>عملیات</th></tr></thead>
        <tbody>
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
        </tbody>
      </table></div></div>

      <?php else: ?>
      <div class="table-wrap"><div class="scroll-x"><table class="data-table">
        <thead><tr><th>ID</th><th>نام کاربری</th><th>نام کامل</th><th>وضعیت</th><th>اعتبار</th><th>عضویت</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($items as $u): ?>
          <tr>
            <td><?= $u['id'] ?></td>
            <td><?= htmlspecialchars($u['username']) ?></td>
            <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
            <td><span class="badge <?= $u['subscription_status'] === 'vip' ? 'badge-warning' : 'badge-secondary' ?>"><?= $u['subscription_status'] === 'vip' ? 'VIP' : 'عادی' ?></span></td>
            <td>
              <?php if ($u['subscription_status'] === 'vip' && !empty($u['subscription_end_date'])): ?>
                <?= date('Y/m/d', strtotime($u['subscription_end_date'])) ?>
              <?php elseif ($u['subscription_status'] === 'vip'): ?><span class="badge badge-success">نامحدود</span>
              <?php else: ?>—<?php endif; ?>
            </td>
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
    <?php endif; ?>
  </div>

  <footer><p>پنل مدیریت انیمه موزیک © <?= date('Y') ?></p></footer>
</div>
</body>
</html>
