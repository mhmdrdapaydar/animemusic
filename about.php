<?php
require_once __DIR__ . '/includes/headless.php';
am_no_cache();

// بررسی وضعیت تم کاربر
$isDarkMode = am_theme();

// داده‌های نمونه برای حامیان و تبادلات
$sponsors = [
    ["name" => "حامی وجود ندارد", "url" => ""],
];
$exchanges = [
    ["name" => "تبادل وجود ندارد", "url" => ""],
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="<?= $isDarkMode ? 'dark' : 'light' ?>">
<head>
  <meta charset="UTF-8">
  <title>درباره ما | انیمه موزیک</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn.min.css" rel="stylesheet">
  <style>
    :root {
      --primary-color: #00ff6a;
      --secondary-color: #00ffc3;
      --accent-color: #ff00aa;
      --yellow-accent: #ffcc00;
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
      padding-bottom: 90px;
      transition: background-color 0.3s, color 0.3s;
    }
    
    .header {
      background: var(--bg-card);
      text-align: center;
      padding: 12px 0 4px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      position: sticky;
      top: 0;
      z-index: 1000;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px 20px;
      border-bottom: 1px solid var(--border-color);
    }
    
    .header img {
      height: 48px;
      max-width: 160px;
      object-fit: contain;
    }
    
    .header-buttons {
      display: flex;
      align-items: center;
      gap: 10px;
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
    
    .container {
      max-width: 750px;
      margin: 0 auto;
      padding: 30px 20px;
    }
    
    h1 {
      text-align: center;
      color: var(--primary-color);
      font-size: 32px;
      margin-bottom: 30px;
    }
    
    p {
      font-size: 16px;
      text-align: justify;
      line-height: 1.8;
    }
    
    .team-section {
      margin-top: 40px;
    }
    
    .team-section h2 {
      font-size: 20px;
      color: var(--yellow-accent);
      margin-bottom: 10px;
    }
    
    .team-section ul {
      list-style: none;
      padding: 0;
    }
    
    .team-section li {
      padding: 10px;
      background: var(--bg-card);
      margin-bottom: 10px;
      border-radius: 10px;
      border: 1px solid var(--border-color);
    }
    
    .social-links {
      margin-top: 30px;
      display: flex;
      justify-content: center;
      gap: 20px;
    }
    
    .social-links a {
      color: var(--yellow-accent);
      font-size: 22px;
      text-decoration: none;
      transition: transform 0.3s;
    }
    
    .social-links a:hover {
      transform: translateY(-3px);
    }
    
    /* استایل جدید برای دکمه رزرو تبلیغات با تم زرد/طلایی */
    .ad-reservation {
      margin-top: 40px;
      text-align: center;
      padding: 25px;
      background: rgba(255, 204, 0, 0.1);
      border-radius: var(--border-radius);
      border: 1px solid rgba(255, 204, 0, 0.3);
      backdrop-filter: blur(5px);
    }
    
    .ad-reservation h3 {
      color: var(--yellow-accent);
      font-size: 20px;
      margin-bottom: 20px;
    }
    
    .reserve-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: linear-gradient(45deg, #ffcc00, #ffaa00);
      color: #121212;
      text-decoration: none;
      font-weight: bold;
      padding: 12px 30px;
      border-radius: 8px;
      font-size: 16px;
      transition: all 0.3s;
      box-shadow: 0 4px 10px rgba(255, 204, 0, 0.2);
      border: none;
      cursor: pointer;
    }
    
    .reserve-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 15px rgba(255, 204, 0, 0.3);
      background: linear-gradient(45deg, #ffaa00, #ffcc00);
    }
    
    .reserve-btn i {
      font-size: 18px;
    }
    
    /* استایل‌های جدید برای بخش‌های حامیان و تبادلات */
    .sponsors-section, .exchanges-section {
      margin-top: 40px;
      padding: 20px;
      background: var(--bg-card);
      border-radius: var(--border-radius);
      border: 1px solid var(--border-color);
    }
    
    .sponsors-section h2, .exchanges-section h2 {
      font-size: 20px;
      color: var(--secondary-color);
      margin-bottom: 20px;
      text-align: center;
    }
    
    .sponsors-list, .exchanges-list {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 15px;
    }
    
    .sponsor-item, .exchange-item {
      background: rgba(0, 255, 195, 0.1);
      padding: 15px;
      border-radius: 10px;
      border: 1px solid rgba(0, 255, 195, 0.3);
      text-align: center;
      transition: all 0.3s;
    }
    
    .exchange-item {
      background: rgba(255, 0, 170, 0.1);
      border: 1px solid rgba(255, 0, 170, 0.3);
    }
    
    .sponsor-item:hover, .exchange-item:hover {
      transform: translateY(-3px);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }
    
    .sponsor-link, .exchange-link {
      color: var(--text-primary);
      text-decoration: none;
      font-weight: bold;
      display: block;
      transition: color 0.3s;
    }
    
    .sponsor-link:hover, .exchange-link:hover {
      color: var(--secondary-color);
    }
    
    .exchange-link:hover {
      color: var(--accent-color);
    }
    
    .sponsor-link i, .exchange-link i {
      margin-left: 5px;
      font-size: 14px;
    }
    
    /* نوار پایین صفحه - مشابه کد قبلی */
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
      transition: 0.3s;
    }
    
    .navbar a.active {
      color: var(--primary-color);
    }
    
    .navbar a:hover {
      color: var(--primary-color);
      transform: translateY(-3px);
    }
  </style>
    <link rel="icon" type="image/x-icon" href="favicon.ico" />
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png" />
    <link rel="apple-touch-icon" href="apple-touch-icon.png" />
    <link rel="stylesheet" href="assets/site.css?v=3" />
</head>
<body>
  <div class="header">
    <img src="image.png" alt="لوگو رسانه">
    <div class="header-buttons">
      <button class="theme-toggle" id="themeToggle">
        <i class="bi <?= $isDarkMode ? 'bi-sun' : 'bi-moon' ?>"></i>
      </button>
    </div>
  </div>

  <div class="container">
    <h1>درباره ما</h1>
    <p>به <strong>انیمه موزیک</strong>، مرجع تخصصی موسیقی انیمه، خوش آمدید. ما مجموعه‌ای متشکل از توسعه‌دهندگان و علاقه‌مندان پراشتیاق هستیم که مأموریت خود را ایجاد یک پلتفرم برتر و یکپارچه برای ارائه غنی‌ترین مجموعه از تیتراژهای ژاپنی قرار داده‌ایم. تجربه شنیداری ناب و عمیق، هدف نهایی ما در خدمت‌رسانی به جامعه بزرگ هواداران این هنر است.</p>

<p><strong>تمایزهای پلتفرم ما</strong></p>

<p>سایت انیمه موزیک با بهره‌گیری از فناوری‌های روز، امکاناتی گسترده و کاربرمحور را در دو سطح دسترسی <strong>عمومی</strong> و <strong>اختصاصی (VIP)</strong> فراهم نموده است.</p>

<p><strong>امکانات همگانی</strong></p>
<p>دسترسی به هسته اصلی سرویس برای همه کاربران به صورت رایگان فراهم است. این امکانات شامل <strong>پخش آنلاین</strong> با کیفیت مطلوب، سیستم <strong>جستجو و فیلتر پیشرفته</strong> بر اساس عنوان انیمه و نام اثر، <strong>دسته‌بندی هوشمند</strong> بر اساس نوع تیتراژ (Opening و Ending)، بخش <strong>محبوب‌ترین‌ها</strong> بر اساس آمار بازدید کاربران، امکان تغییر <strong>تم دارک و لایت</strong> متناسب با ترجیح کاربر، امکان <strong>حمایت مالی</strong> از پلتفرم برای توسعه بیشتر و قابلیت <strong>جستجو بر اساس خواننده</strong> می‌باشد.</p>

<p><strong>امکانات اشتراک VIP</strong></p>
<p>ارتقاء به حساب VIP، دروازه‌ای به سوی تجربه‌ای جامع‌تر و بدون محدودیت است. کاربران ویژه از امکان <strong>دانلود موزیک</strong> با بالاترین کیفیت، ابزار مدیریت <strong>لیست پخش</strong> شخصی، <strong>نمایش متن اصلی و ترجمه</strong> همزمان با پخش آهنگ، <strong>حذف تمامی تبلیغات</strong>، داشتن <strong>صفحه پروفایل و سیستم اشتراک‌گذاری</strong> و دسترسی انحصاری به آرشیو <strong>ویدیوهای اوپنینگ، اندینگ و کنسرت‌ها</strong> بهره‌مند می‌شوند.</p>

<p><strong>تعهد ما</strong></p>
<p>تیم انیمه موزیک با تمرکز بر <strong>کیفیت محتوا</strong>، <strong>تجربه کاربری برتر</strong> و <strong>پشتیبانی پاسخگو</strong>، متعهد به توسعه مستقل این پلتفرم بر اساس بازخوردهای جامعه کاربری خود است. ما افتخار می‌کنیم که بخشی از سفر موزیکال شما هستیم.</p>

    <div class="team-section">
      <h2>برخی از عضو های فعال تیم</h2>
      <ul>
        <li>MHP</li>
      </ul>
    </div>

    <div class="social-links">
      <a href="https://t.me/Anime_Music_IRN" target="_blank"><i class="bi bi-telegram"></i></a>
      <a href="assets/Eror/work.html" target="_blank"><i class="bi bi-instagram"></i></a>
      <a href="assets/Eror/work.html" target="_blank"><i class="bi bi-camera-video-fill"></i></a>
    </div>
    
    <!-- بخش جدید رزرو تبلیغات با تم زرد/طلایی -->
    <div class="ad-reservation">
      <h3>فضای تبلیغاتی خود را در رسانه من رزرو کنید</h3>
      <a href="ads.php" class="reserve-btn">
        <i class="bi bi-megaphone-fill"></i>
        رزرو تبلیغات
      </a>
    </div>

    <!-- بخش جدید حامیان -->
    <div class="sponsors-section">
      <h2>حامیان ما</h2>
      <div class="sponsors-list">
        <?php foreach ($sponsors as $sponsor): ?>
          <div class="sponsor-item">
            <a href="<?= $sponsor['url'] ?>" class="sponsor-link" target="_blank">
              <i class="bi bi-link-45deg"></i>
              <?= $sponsor['name'] ?>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- بخش جدید تبادلات -->
    <div class="exchanges-section">
      <h2>تبادلات و همکاری‌ها</h2>
      <div class="exchanges-list">
        <?php foreach ($exchanges as $exchange): ?>
          <div class="exchange-item">
            <a href="<?= $exchange['url'] ?>" class="exchange-link" target="_blank">
              <i class="bi bi-arrow-left-right"></i>
              <?= $exchange['name'] ?>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="navbar">
    <a href="index.php" class="nav-item"><i class="bi bi-house-door-fill"></i>خانه</a>
    <a href="categories.php" class="nav-item"><i class="bi bi-grid-1x2-fill"></i>دسته‌ها</a>
    <a href="search/search.php" class="nav-item"><i class="bi bi-search"></i>جستجو</a>
    <a href="about.php" class="nav-item active"><i class="bi bi-info-circle"></i>درباره ما</a>
  </div>

  <script>
    // سیستم تغییر تم (مطابق با کد قبلی)
    const themeToggle = document.getElementById('themeToggle');
    const htmlElement = document.documentElement;
    
    themeToggle.addEventListener('click', () => {
      const isDark = htmlElement.getAttribute('data-theme') === 'dark';
      const newTheme = isDark ? 'light' : 'dark';
      
      htmlElement.setAttribute('data-theme', newTheme);
      themeToggle.innerHTML = `<i class="bi ${newTheme === 'dark' ? 'bi-sun' : 'bi-moon'}"></i>`;
      
      // ذخیره تنظیمات در کوکی به مدت 30 روز (مطابق با کد قبلی)
      document.cookie = `dark_mode=${newTheme === 'dark'}; max-age=${30 * 24 * 60 * 60}; path=/`;
    });
  </script>
</body>
</html>