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
    $title = "همه " . htmlspecialchars($type) . " ها";
} 
// مرتب‌سازی بر اساس محبوبیت
elseif ($sort === 'popular') {
    $title = "محبوب‌ترین‌ها";
} 
// مرتب‌سازی بر اساس جدیدترین‌ها
else {
    $title = "جدیدترین‌ها";
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
<html lang="fa" dir="rtl" data-theme="<?= $isDarkMode ? 'dark' : 'light' ?>">
<head>
  <meta charset="UTF-8">
  <title><?= $title ?> | رسانه من</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    <link rel="icon" type="image/x-icon" href="favicon.ico" />
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png" />
    <link rel="apple-touch-icon" href="apple-touch-icon.png" />
    <link rel="stylesheet" href="assets/site.css?v=3" />
</head>
<body>
  <div class="header">
    <h1><?= $title ?></h1>
    <button class="theme-toggle" id="themeToggle">
        <i class="bi <?= $isDarkMode ? 'bi-sun' : 'bi-moon' ?>"></i>
    </button>
  </div>

  <div class="filters">
    <a href="?sort=latest" class="filter-btn <?= (!$type && $sort === 'latest') ? 'active' : '' ?>">
        جدیدترین‌ها
    </a>
    <a href="?sort=popular" class="filter-btn <?= (!$type && $sort === 'popular') ? 'active' : '' ?>">
        محبوب‌ترین‌ها
    </a>
    <a href="?type=Opening" class="filter-btn <?= $type === 'Opening' ? 'active' : '' ?>">
        اوپنینگ ها
    </a>
    <a href="?type=Ending" class="filter-btn <?= $type === 'Ending' ? 'active' : '' ?>">
        اندینگ ها
    </a>
    <a href="?type=OST" class="filter-btn <?= $type === 'OST' ? 'active' : '' ?>">
        موسیقی زمینه
    </a>
  </div>

  <?php if (empty($items)): ?>
    <div class="empty-state">
        <i class="bi bi-music-note-beamed"></i>
        <p>محتوا یافت نشد.</p>
    </div>
  <?php else: ?>
    <div class="content-grid">
      <?php foreach ($items as $item): ?>
        <a class="content-card" href="content.php?id=<?= $item['id'] ?>">
          <div class="image-container">
            <div class="content-badge"><?= htmlspecialchars($item['music_type']) ?></div>
            <img src="<?= htmlspecialchars($item['image_url'] ?? 'assets/image/placeholder.jpg') ?>" alt="کاور <?= htmlspecialchars($item['title']) ?>" loading="lazy">
          </div>
          <div class="info">
            <h2><?= htmlspecialchars($item['title']) ?></h2>
            <p><?= htmlspecialchars($item['title_fa']) ?></p>
            <div class="meta">
              <span>فصل <?= $item['season_number'] ?? 1 ?></span>
              <span><?= number_format($item['view_count'] ?? 0) ?> بازدید</span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="pagination">
      <!-- دکمه صفحه قبل -->
      <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">قبلی</a>
      <?php else: ?>
        <span class="disabled">قبلی</span>
      <?php endif; ?>

      <!-- نمایش صفحات مجاور -->
      <?php
      $startPage = max(1, $page - 2);
      $endPage = min($totalPages, $page + 2);
      
      if ($startPage > 1) {
          echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '">1</a>';
          if ($startPage > 2) echo '<span>...</span>';
      }
      
      for ($i = $startPage; $i <= $endPage; $i++): ?>
        <?php if ($i == $page): ?>
          <span class="current"><?= $i ?></span>
        <?php else: ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
        <?php endif; ?>
      <?php endfor;
      
      if ($endPage < $totalPages) {
          if ($endPage < $totalPages - 1) echo '<span>...</span>';
          echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $totalPages])) . '">' . $totalPages . '</a>';
      }
      ?>

      <!-- دکمه صفحه بعد -->
      <?php if ($page < $totalPages): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">بعدی</a>
      <?php else: ?>
        <span class="disabled">بعدی</span>
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