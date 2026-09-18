<?php
require_once __DIR__ . '/includes/headless.php';

// اتصال به دیتابیس محتوا
$db_content = am_content_db();

// بررسی وضعیت تم کاربر
$isDarkMode = am_theme();

// گرفتن انواع موزیک به همراه تعداد آثار
$musicTypes = $db_content->query("
    SELECT mt.name, COUNT(ac.id) as total 
    FROM music_types mt 
    LEFT JOIN anime_contents ac ON mt.id = ac.music_type_id 
    GROUP BY mt.name 
    ORDER BY total DESC
")->fetchAll(PDO::FETCH_ASSOC);

// گرفتن انیمه‌ها به همراه تعداد آثار
$animeSeries = $db_content->query("
    SELECT asr.id, asr.title_fa, asr.title_en, COUNT(ac.id) as total 
    FROM anime_series asr 
    LEFT JOIN anime_contents ac ON asr.id = ac.anime_id 
    GROUP BY asr.id, asr.title_fa, asr.title_en 
    ORDER BY total DESC
")->fetchAll(PDO::FETCH_ASSOC);

// گرفتن خوانندگان به همراه تعداد آثار
$singers = $db_content->query("
    SELECT s.id, s.name, COUNT(cs.content_id) as total 
    FROM singers s 
    LEFT JOIN content_singers cs ON s.id = cs.singer_id 
    GROUP BY s.id, s.name 
    ORDER BY total DESC
")->fetchAll(PDO::FETCH_ASSOC);

// اگر نوع موزیک انتخاب شده باشد، آثارش را بیاور
$contents = [];
$currentFilter = '';
$filterType = '';
$filterValue = '';

if (isset($_GET['type'])) {
    $type = $_GET['type'];
    $currentFilter = $type;
    $filterType = 'نوع موزیک';
    
    $stmt = $db_content->prepare("
        SELECT ac.*, asr.title_fa, asr.title_en, mt.name as music_type 
        FROM anime_contents ac 
        JOIN anime_series asr ON ac.anime_id = asr.id 
        JOIN music_types mt ON ac.music_type_id = mt.id 
        WHERE mt.name = ? 
        ORDER BY ac.created_at DESC
    ");
    $stmt->execute([$type]);
    $contents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} 
// اگر انیمه انتخاب شده باشد، آثارش را بیاور
elseif (isset($_GET['anime'])) {
    $animeId = $_GET['anime'];
    $currentFilter = $animeId;
    $filterType = 'انیمه';
    
    // دریافت نام انیمه
    $stmt = $db_content->prepare("SELECT title_fa, title_en FROM anime_series WHERE id = ?");
    $stmt->execute([$animeId]);
    $anime = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($anime) {
        $currentFilter = $anime['title_fa'] . ' (' . $anime['title_en'] . ')';
        
        $stmt = $db_content->prepare("
            SELECT ac.*, asr.title_fa, asr.title_en, mt.name as music_type 
            FROM anime_contents ac 
            JOIN anime_series asr ON ac.anime_id = asr.id 
            JOIN music_types mt ON ac.music_type_id = mt.id 
            WHERE ac.anime_id = ? 
            ORDER BY ac.season_number, ac.episode_number, mt.name
        ");
        $stmt->execute([$animeId]);
        $contents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} 
// اگر خواننده انتخاب شده باشد، آثارش را بیاور
elseif (isset($_GET['singer'])) {
    $singerId = $_GET['singer'];
    $currentFilter = $singerId;
    $filterType = 'خواننده';
    
    // دریافت نام خواننده
    $stmt = $db_content->prepare("SELECT name FROM singers WHERE id = ?");
    $stmt->execute([$singerId]);
    $singer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($singer) {
        $currentFilter = $singer['name'];
        
        $stmt = $db_content->prepare("
            SELECT ac.*, asr.title_fa, asr.title_en, mt.name as music_type 
            FROM anime_contents ac 
            JOIN anime_series asr ON ac.anime_id = asr.id 
            JOIN music_types mt ON ac.music_type_id = mt.id 
            JOIN content_singers cs ON ac.id = cs.content_id 
            WHERE cs.singer_id = ? 
            ORDER BY ac.created_at DESC
        ");
        $stmt->execute([$singerId]);
        $contents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="<?= $isDarkMode ? 'dark' : 'light' ?>">
<head>
  <meta charset="UTF-8">
  <title>دسته‌بندی‌ها | انیمه موزیک</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
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
      padding-bottom: 80px;
      transition: background-color 0.3s, color 0.3s;
    }
    
    .header {
        background: var(--bg-card);
        text-align: center;
        padding: 12px 20px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        position: sticky;
        top: 0;
        z-index: 1000;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid var(--border-color);
    }
    
    .header img {
        height: 48px;
        max-width: 160px;
        object-fit: contain;
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
    
    h1, h2 {
      color: var(--secondary-color);
      text-align: center;
      padding: 0 16px;
    }
    
    .category-list {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 15px;
      padding: 20px;
      max-width: 1200px;
      margin: 20px auto;
    }
    
    .category-card {
      background: var(--bg-card);
      border: 1px solid var(--border-color);
      border-radius: var(--border-radius);
      padding: 16px;
      text-align: center;
      transition: all 0.3s ease;
      cursor: pointer;
      text-decoration: none;
      color: var(--primary-color);
      font-weight: bold;
      position: relative;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      font-size: 15px;
    }
    
    .category-card:hover {
      background: var(--primary-color);
      color: #000;
      transform: translateY(-4px) scale(1.03);
      box-shadow: 0 4px 12px rgba(0, 255, 100, 0.2);
    }
    
    .category-card .count {
      display: block;
      margin-top: 8px;
      font-size: 13px;
      color: var(--text-secondary);
    }
    
    .category-card:hover .count {
      color: #000;
    }

    .content-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 15px;
      padding: 20px;
      max-width: 1200px;
      margin: 0 auto;
    }
    
    .content-card {
      background-color: var(--bg-card);
      border-radius: var(--border-radius);
      overflow: hidden;
      text-decoration: none;
      color: inherit;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      position: relative;
      border: 1px solid var(--border-color);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .content-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 4px 12px rgba(0, 255, 100, 0.2);
    }
    
    .content-card img {
      width: 100%;
      height: 120px;
      object-fit: cover;
    }
    
    .content-card .info {
      padding: 12px;
    }
    
    .content-card h3 {
      font-size: 15px;
      margin: 0 0 6px;
      color: var(--primary-color);
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      line-height: 1.4;
    }
    
    .content-card p {
      margin: 0;
      font-size: 13px;
      color: var(--text-secondary);
    }
    
    .badge {
      position: absolute;
      top: 8px;
      left: 8px;
      background-color: var(--accent-color);
      color: white;
      font-size: 11px;
      padding: 3px 8px;
      border-radius: 12px;
      font-weight: bold;
      z-index: 2;
    }
    
    .back-button {
      display: block;
      margin: 20px auto;
      padding: 10px 20px;
      background-color: var(--secondary-color);
      color: #000;
      border: none;
      border-radius: 30px;
      text-decoration: none;
      text-align: center;
      font-weight: bold;
      max-width: 200px;
      transition: all 0.3s;
    }
    
    .back-button:hover {
      background-color: var(--primary-color);
      transform: translateY(-2px);
    }
    
    .navbar {
      position: fixed;
      bottom: 0;
      width: 100%;
      background-color: var(--bg-card);
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      text-align: center;
      padding: 12px 0 10px;
      border-top: 1px solid var(--border-color);
      z-index: 999;
      box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
    }
    
    .navbar a {
      text-decoration: none;
      color: var(--text-secondary);
      font-size: 13px;
      display: flex;
      flex-direction: column;
      align-items: center;
      transition: 0.3s;
    }
    
    .navbar a i {
      font-size: 22px;
      margin-bottom: 2px;
    }
    
    .navbar a.active {
      color: var(--primary-color);
    }
    
    .navbar a:hover {
      color: var(--primary-color);
      transform: translateY(-3px);
    }
    
    .section-title {
      text-align: center;
      margin: 30px 0 20px;
      padding: 0 16px;
      color: var(--secondary-color);
      font-size: 20px;
      border-bottom: 2px solid var(--secondary-color);
      padding-bottom: 10px;
      max-width: 1200px;
      margin-left: auto;
      margin-right: auto;
    }
    
    /* بهینه‌سازی برای دستگاه‌های موبایل */
    @media (max-width: 600px) {
      .category-list {
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px;
        padding: 16px;
      }
      
      .content-grid {
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px;
        padding: 16px;
      }
      
      .category-card {
        padding: 12px;
        font-size: 14px;
      }
      
      .content-card img {
        height: 100px;
      }
      
      .content-card .info {
        padding: 10px;
      }
      
      .content-card h3 {
        font-size: 14px;
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
    <img src="image.png" alt="لوگو رسانه" />
    <button class="theme-toggle" id="themeToggle">
        <i class="bi <?= $isDarkMode ? 'bi-sun' : 'bi-moon' ?>"></i>
    </button>
  </div>

  <h1>دسته‌بندی‌ها</h1>

  <?php if (empty($contents)): ?>
    <div class="section-title">انواع موزیک</div>
    <div class="category-list">
      <?php foreach ($musicTypes as $type): ?>
        <a href="?type=<?= urlencode($type['name']) ?>" class="category-card">
          <?= htmlspecialchars($type['name']) ?>
          <span class="count">(<?= $type['total'] ?> مورد)</span>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="section-title">انیمه‌ها</div>
    <div class="category-list">
      <?php foreach ($animeSeries as $anime): ?>
        <a href="?anime=<?= $anime['id'] ?>" class="category-card">
          <?= htmlspecialchars($anime['title_fa']) ?>
          <span class="count">(<?= $anime['total'] ?> مورد)</span>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="section-title">خوانندگان</div>
    <div class="category-list">
      <?php foreach ($singers as $singer): ?>
        <a href="?singer=<?= $singer['id'] ?>" class="category-card">
          <?= htmlspecialchars($singer['name']) ?>
          <span class="count">(<?= $singer['total'] ?> مورد)</span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <h2>آثار <?= $filterType ?>: <?= htmlspecialchars($currentFilter) ?></h2>
    
    <a href="categories.php" class="back-button">بازگشت به دسته‌بندی‌ها</a>
    
    <div class="content-grid">
      <?php foreach ($contents as $item): ?>
        <a href="content.php?id=<?= $item['id'] ?>" class="content-card">
          <div class="badge"><?= htmlspecialchars($item['music_type']) ?></div>
          <img src="<?= htmlspecialchars($item['image_url'] ?? 'assets/image/placeholder.jpg') ?>" alt="کاور <?= htmlspecialchars($item['title']) ?>">
          <div class="info">
            <h3><?= htmlspecialchars($item['title']) ?></h3>
            <p><?= htmlspecialchars($item['title_fa']) ?></p>
            <?php if ($item['season_number']): ?>
              <p>فصل <?= $item['season_number'] ?></p>
            <?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="navbar">
    <a href="index.php" class="nav-item"><i class="bi bi-house-door-fill"></i>خانه</a>
    <a href="categories.php" class="nav-item active"><i class="bi bi-grid-1x2-fill"></i>دسته‌ها</a>
    <a href="search/search.php" class="nav-item"><i class="bi bi-search"></i>جستجو</a>
    <a href="about.php" class="nav-item"><i class="bi bi-info-circle"></i>درباره ما</a>
  </div>

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