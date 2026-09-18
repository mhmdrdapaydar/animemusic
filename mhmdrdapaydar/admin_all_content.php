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

    $totalItems = $counts[$type];
    $totalPages = (int)ceil($totalItems / $perPage);
    $items = [];

    switch ($type) {
        case 'anime':
            $stmt = $db_content->prepare("SELECT * FROM anime_series ORDER BY id DESC LIMIT :l OFFSET :o");
            break;
        case 'music':
            $stmt = $db_content->prepare("
                SELECT ac.*, asr.title_fa AS anime_title, mt.name AS music_type_name
                FROM anime_contents ac
                JOIN anime_series asr ON ac.anime_id = asr.id
                JOIN music_types mt ON ac.music_type_id = mt.id
                ORDER BY ac.id DESC LIMIT :l OFFSET :o
            ");
            break;
        case 'singer':
            $stmt = $db_content->prepare("
                SELECT s.*, (SELECT COUNT(*) FROM content_singers cs WHERE cs.singer_id = s.id) AS cnt
                FROM singers s ORDER BY s.name LIMIT :l OFFSET :o
            ");
            break;
        case 'user':
            $stmt = $db_users->prepare("SELECT * FROM users ORDER BY created_at DESC LIMIT :l OFFSET :o");
            break;
    }
    $stmt->bindValue(':l', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $base = '?type=' . urlencode($type) . '&page=';
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
    $rangeInfo = $totalItems > 0
        ? 'نمایش ' . min($offset + 1, $totalItems) . ' تا ' . min($offset + $perPage, $totalItems) . ' از ' . number_format($totalItems) . ' مورد'
        : 'موردی ثبت نشده است';

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

    <?php if (empty($items)): ?>
      <div class="empty-state">
        <i class="fas fa-inbox"></i>
        <h3>محتوایی وجود ندارد</h3>
        <p>هنوز هیچ موردی ثبت نشده است.</p>
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
