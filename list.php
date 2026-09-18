<?php
require_once __DIR__ . '/includes/headless.php';

// اتصال به پایگاه داده محتوا
$db_content = am_content_db();

// دریافت پارامترهای فیلتر و صفحه‌بندی
$type = $_GET['type'] ?? null;
$sort = $_GET['sort'] ?? 'latest';
$page = max(1, intval($_GET['page'] ?? 1)); // صفحه جاری (حداقل 1)
$perPage = 12; // تعداد آیتم‌ها در هر صفحه

// آماده‌سازی کوئری پایه
$query = "
    SELECT ac.*, asr.title_fa, asr.title_en, mt.name as music_type 
    FROM anime_contents ac 
    JOIN anime_series asr ON ac.anime_id = asr.id 
    JOIN music_types mt ON ac.music_type_id = mt.id
";

$countQuery = "
    SELECT COUNT(*) 
    FROM anime_contents ac 
    JOIN anime_series asr ON ac.anime_id = asr.id 
    JOIN music_types mt ON ac.music_type_id = mt.id
";

$params = [];
$conditions = [];

// فیلتر بر اساس نوع محتوا
if ($type) {
    $conditions[] = "mt.name = ?";
    $params[] = $type;
    $typeLabel = htmlspecialchars($type);
    $title = (am_lang() === 'fa')
        ? "همه " . $typeLabel . " ها"
        : "All " . $typeLabel . "s";
} 
// مرتب‌سازی بر اساس محبوبیت
elseif ($sort === 'popular') {
    $title = am_t('popular');
} 
// مرتب‌سازی بر اساس جدیدترین‌ها
else {
    $title = am_t('latest');
}

// اضافه کردن شرایط به کوئری
if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
    $countQuery .= " WHERE " . implode(" AND ", $conditions);
}

// تعیین مرتب‌سازی
if ($sort === 'popular') {
    $query .= " ORDER BY ac.view_count DESC";
} else {
    $query .= " ORDER BY ac.created_at DESC";
}

// اضافه کردن محدودیت صفحه‌بندی
$query .= " LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = ($page - 1) * $perPage;

// اجرای کوئری محتوا
$stmt = $db_content->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// محاسبه تعداد کل آیتم‌ها برای صفحه‌بندی
$countStmt = $db_content->prepare($countQuery);
$countStmt->execute(array_slice($params, 0, -2));
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

// بررسی وضعیت تم کاربر
$isDarkMode = am_theme();
?>
<!DOCTYPE html>
<html lang="<?= am_e(am_lang()) ?>" dir="<?= am_e(am_lang_dir()) ?>" data-theme="<?= $isDarkMode ? 'dark' : 'light' ?>">
<head>
  <meta charset="UTF-8">
  <title><?= am_e($title) ?> | <?= am_te('site_name') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?= am_lang_base_tag() ?>
  <?= am_hreflang_links('list.php') ?>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn.min.css" rel="stylesheet" />
<style>
    :root {
        --primary-color: #00ff6a;
        --secondary-color: #00ffc3;
        --accent-color: #ff00aa;
        --bg-dark: #0a0a0a;
        --bg-light: #f5f5f5;
        --card-bg-dark: #1a1a1a;
        --card-bg-light: #ffffff;
        --text-dark: #eee;
        --text-light: #333;
        --text-gray-dark: #bbb;
        --text-gray-light: #777;
        --border-radius: 14px;
        --border-color-dark: #333;
        --border-color-light: #e0e0e0;
    }
    
    [data-theme="light"] {
        --bg-primary: var(--bg-light);
        --bg-card: var(--card-bg-light);
        --text-primary: var(--text-light);
        --text-secondary: var(--text-gray-light);
        --border-color: var(--border-color-light);
    }
    
    [data-theme="dark"] {
        --bg-primary: var(--bg-dark);
        --bg-card: var(--card-bg-dark);
        --text-primary: var(--text-dark);
        --text-secondary: var(--text-gray-dark);
        --border-color: var(--border-color-dark);
    }
    
    body {
      background-color: var(--bg-primary);
      color: var(--text-primary);
      font-family: 'Vazirmatn', sans-serif;
      margin: 0;
      padding: 20px;
      transition: background-color 0.3s, color 0.3s;
    }
    
    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .header h1 {
        color: var(--primary-color);
        margin: 0;
        font-size: 28px;
    }
    
    .theme-toggle {
        background: none;
        border: none;
        color: var(--text-primary);
        font-size: 24px;
        cursor: pointer;
        padding: 5px;
        border-radius: 50%;
        transition: background-color 0.3s;
    }
    
    .theme-toggle:hover {
        background-color: rgba(0, 0, 0, 0.1);
    }
    
    .filters {
        display: flex;
        gap: 15px;
        margin-bottom: 25px;
        flex-wrap: wrap;
    }
    
    .filter-btn {
        padding: 10px 20px;
        border-radius: 30px;
        border: 1px solid var(--border-color);
        background: var(--bg-card);
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.3s;
        font-family: 'Vazirmatn', sans-serif;
    }
    
    .filter-btn.active {
        background: var(--primary-color);
        color: #000;
        border-color: var(--primary-color);
    }
    
    .filter-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    .content-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }
    
    .content-card {
        background: var(--bg-card);
        border-radius: var(--border-radius);
        overflow: hidden;
        text-decoration: none;
        color: inherit;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        border: 1px solid var(--border-color);
    }
    
    .content-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0, 255, 100, 0.2);
    }
    
    .image-container {
        position: relative;
        width: 100%;
        padding-top: 56.25%; /* نسبت 16:9 */
        overflow: hidden;
    }
    
    .content-card img {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: filter 0.3s;
    }
    
    .content-card:hover img {
        filter: brightness(1.1);
    }
    
    .content-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        background: var(--accent-color);
        color: white;
        font-size: 12px;
        padding: 4px 10px;
        border-radius: 12px;
        font-weight: bold;
        z-index: 2;
    }
    
    .info {
        padding: 15px;
    }
    
    .info h2 {
        margin: 0 0 8px;
        font-size: 16px;
        color: var(--primary-color);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        line-height: 1.4;
    }
    
    .info p {
        margin: 0;
        font-size: 14px;
        color: var(--text-secondary);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .meta {
        display: flex;
        justify-content: space-between;
        margin-top: 10px;
        font-size: 12px;
        color: var(--text-secondary);
    }
    
    .pagination {
        display: flex;
        justify-content: center;
        gap: 8px;
        margin: 30px 0;
        flex-wrap: wrap;
    }
    
    .pagination a, .pagination span {
        padding: 8px 16px;
        border-radius: 8px;
        text-decoration: none;
        color: var(--text-primary);
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        transition: all 0.3s;
    }
    
    .pagination a:hover {
        background: var(--primary-color);
        color: #000;
        border-color: var(--primary-color);
    }
    
    .pagination .current {
        background: var(--primary-color);
        color: #000;
        font-weight: bold;
        border-color: var(--primary-color);
    }
    
    .pagination .disabled {
        opacity: 0.5;
        pointer-events: none;
    }
    
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--text-secondary);
    }
    
    .empty-state i {
        font-size: 48px;
        margin-bottom: 15px;
        display: block;
        color: var(--text-secondary);
    }
    
    @media (max-width: 768px) {
        .content-grid {
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 15px;
        }
        
        .header h1 {
            font-size: 22px;
        }
        
        .filters {
            gap: 10px;
        }
        
        .filter-btn {
            padding: 8px 16px;
            font-size: 14px;
        }
        
        .info {
            padding: 12px;
        }
        
        .info h2 {
            font-size: 14px;
        }
        
        .info p {
            font-size: 12px;
        }
    }
  </style>
    <link rel="icon" type="image/x-icon" href="/favicon.ico" />
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png" />
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" />
    <link rel="stylesheet" href="/assets/site.css?v=4" />
    <script defer src="/assets/site.js?v=1"></script>
</head>
<body>
  <div class="header">
    <h1><?= am_e($title) ?></h1>
    <button class="theme-toggle" id="themeToggle">
        <i class="bi <?= $isDarkMode ? 'bi-sun' : 'bi-moon' ?>"></i>
    </button>
  </div>
  <?= am_lang_switcher_flags('list.php') ?>


  <div class="filters">
    <a href="<?= am_lang_url('list.php?sort=latest') ?>" class="filter-btn <?= (!$type && $sort === 'latest') ? 'active' : '' ?>">
        <?= am_te('latest') ?>
    </a>
    <a href="<?= am_lang_url('list.php?sort=popular') ?>" class="filter-btn <?= (!$type && $sort === 'popular') ? 'active' : '' ?>">
        <?= am_te('popular') ?>
    </a>
    <a href="<?= am_lang_url('list.php?type=Opening') ?>" class="filter-btn <?= $type === 'Opening' ? 'active' : '' ?>">
        <?= am_te('openings') ?>
    </a>
    <a href="<?= am_lang_url('list.php?type=Ending') ?>" class="filter-btn <?= $type === 'Ending' ? 'active' : '' ?>">
        <?= am_te('endings') ?>
    </a>
    <a href="<?= am_lang_url('list.php?type=OST') ?>" class="filter-btn <?= $type === 'OST' ? 'active' : '' ?>">
        <?= am_te('osts') ?>
    </a>
  </div>

  <?php if (empty($items)): ?>
    <div class="empty-state">
        <i class="bi bi-music-note-beamed"></i>
        <p><?= am_te('not_found') ?></p>
    </div>
  <?php else: ?>
    <div class="content-grid">
      <?php foreach ($items as $item): ?>
        <a class="content-card" href="<?= am_lang_url('content.php?id=' . $item['id']) ?>">
          <div class="image-container">
            <div class="content-badge"><?= htmlspecialchars($item['music_type']) ?></div>
            <img src="<?= htmlspecialchars($item['image_url'] ?? '/assets/image/placeholder.jpg') ?>" alt="<?= am_e($item['title']) ?>" loading="lazy">
          </div>
          <div class="info">
            <h2><?= htmlspecialchars($item['title']) ?></h2>
            <p><?= am_e(am_lang_content_title($item['title_en'], $item['title_fa'])) ?></p>
            <div class="meta">
              <span><?= am_te('season') ?> <?= $item['season_number'] ?? 1 ?></span>
              <span><?= number_format($item['view_count'] ?? 0) ?> <?= am_te('views') ?></span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="pagination">
      <!-- دکمه صفحه قبل -->
      <?php if ($page > 1): ?>
        <a href="<?= am_lang_url('list.php?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>"><?= am_te('prev_page') ?></a>
      <?php else: ?>
        <span class="disabled"><?= am_te('prev_page') ?></span>
      <?php endif; ?>

      <!-- نمایش صفحات مجاور -->
      <?php
      $startPage = max(1, $page - 2);
      $endPage = min($totalPages, $page + 2);
      
      if ($startPage > 1) {
          echo '<a href="' . am_e(am_lang_url('list.php?' . http_build_query(array_merge($_GET, ['page' => 1])))) . '">1</a>';
          if ($startPage > 2) echo '<span>...</span>';
      }
      
      for ($i = $startPage; $i <= $endPage; $i++): ?>
        <?php if ($i == $page): ?>
          <span class="current"><?= $i ?></span>
        <?php else: ?>
          <a href="<?= am_lang_url('list.php?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>"><?= $i ?></a>
        <?php endif; ?>
      <?php endfor;
      
      if ($endPage < $totalPages) {
          if ($endPage < $totalPages - 1) echo '<span>...</span>';
          echo '<a href="' . am_e(am_lang_url('list.php?' . http_build_query(array_merge($_GET, ['page' => $totalPages])))) . '">' . $totalPages . '</a>';
      }
      ?>

      <!-- دکمه صفحه بعد -->
      <?php if ($page < $totalPages): ?>
        <a href="<?= am_lang_url('list.php?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>"><?= am_te('next_page') ?></a>
      <?php else: ?>
        <span class="disabled"><?= am_te('next_page') ?></span>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <script>
    // سیستم تغییر تم
    const themeToggle = document.getElementById('themeToggle');
    const htmlElement = document.documentElement;
    
    themeToggle.addEventListener('click', () => {
        const isDark = htmlElement.getAttribute('data-theme') === 'dark';
        const newTheme = isDark ? 'light' : 'dark';
        
        htmlElement.setAttribute('data-theme', newTheme);
        themeToggle.innerHTML = `<i class="bi ${newTheme === 'dark' ? 'bi-sun' : 'bi-moon'}"></i>`;
        
        // ذخیره تنظیمات در کوکی به مدت 30 روز
        document.cookie = `dark_mode=${newTheme === 'dark'}; max-age=${30 * 24 * 60 * 60}; path=/`;
    });
  </script>
</body>
</html>