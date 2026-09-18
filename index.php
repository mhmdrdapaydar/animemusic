<?php
require_once __DIR__ . '/includes/headless.php';
am_no_cache();

// آمار بازدید (جایگزین counter.php — یک فایل مشترک)
am_log_visit(AM_VISITS_FILE);

// اتصال به دیتابیس محتوا (و کاربران به صورت خودکار با helper)
$db_content = am_content_db();
$db_users   = am_users_db();

// اصلاح خودکار وضعیت VIP کاربر در صورت انقضا (رفع باگ)
am_current_user();

// نام کوتاه برای افزودن نسخه به فایل‌های استاتیک
function asset($path) {
    return am_asset($path);
}

// دریافت محبوب‌ترین محتواها
function getPopularContents($db, $limit = 10) {
    $stmt = $db->prepare("
        SELECT ac.*, asr.title_fa, asr.title_en, mt.name as music_type 
        FROM anime_contents ac 
        JOIN anime_series asr ON ac.anime_id = asr.id 
        JOIN music_types mt ON ac.music_type_id = mt.id 
        ORDER BY ac.view_count DESC 
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// دریافت جدیدترین محتواها
function getLatestContents($db, $limit = 10) {
    $stmt = $db->prepare("
        SELECT ac.*, asr.title_fa, asr.title_en, mt.name as music_type 
        FROM anime_contents ac 
        JOIN anime_series asr ON ac.anime_id = asr.id 
        JOIN music_types mt ON ac.music_type_id = mt.id 
        ORDER BY ac.created_at DESC 
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// دریافت محتوا بر اساس نوع
function getContentsByType($db, $type, $limit = 10) {
    $stmt = $db->prepare("
        SELECT ac.*, asr.title_fa, asr.title_en, mt.name as music_type 
        FROM anime_contents ac 
        JOIN anime_series asr ON ac.anime_id = asr.id 
        JOIN music_types mt ON ac.music_type_id = mt.id 
        WHERE mt.name = :type 
        ORDER BY ac.created_at DESC 
        LIMIT :limit
    ");
    $stmt->bindValue(':type', $type, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// دریافت داده‌ها
$popularContents = getPopularContents($db_content, 10);
$latestContents = getLatestContents($db_content, 10);
$openings = getContentsByType($db_content, 'Opening', 10);
$endings = getContentsByType($db_content, 'Ending', 10);
$osts = getContentsByType($db_content, 'OST', 10);

// اسلایدهای ویژه
$specialSlides = [];
$imageFiles = glob(__DIR__ . "/assets/image/image*.png");
$imageFiles = array_slice($imageFiles, 0, 10); // محدود کردن به 10 تصویر

foreach ($imageFiles as $imagePath) {
    $baseName = basename($imagePath, '.png');
    $num = preg_replace('/[^0-9]/', '', $baseName);
    $linkPath = __DIR__ . "/assets/image/image{$num}.txt";
    
    $url = file_exists($linkPath) ? trim(file_get_contents($linkPath)) : '#';
    $specialSlides[] = [
        "img" => "assets/image/image{$num}.png",
        "url" => $url,
        "version" => filemtime($imagePath)
    ];
}

// نسخه لوگو
$logoVersion = file_exists(__DIR__ . '/image.png') ? filemtime(__DIR__ . '/image.png') : time();

// بررسی وضعیت تم کاربر
$isDarkMode = false;
if (isset($_COOKIE['dark_mode'])) {
    $isDarkMode = $_COOKIE['dark_mode'] === 'true';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="<?= $isDarkMode ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8" />
    <title>خانه | انیمه موزیک</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
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
            --image-width: 165px;
            --image-height: 180px;
            --image-ratio: calc(180 / 165);
            --border-radius: 14px;
        }
        
        [data-theme="light"] {
            --bg-primary: var(--bg-light);
            --bg-card: var(--card-bg-light);
            --text-primary: var(--text-light);
            --text-secondary: var(--text-gray-light);
            --border-color: #e0e0e0;
        }
        
        [data-theme="dark"] {
            --bg-primary: var(--bg-dark);
            --bg-card: var(--card-bg-dark);
            --text-primary: var(--text-dark);
            --text-secondary: var(--text-gray-dark);
            --border-color: #333;
        }
        
        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            margin: 0;
            font-family: 'Vazirmatn', sans-serif;
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
            transition: transform 0.3s;
        }
        
        .header img:hover {
            transform: scale(1.05);
        }
        
        .header-buttons {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .theme-toggle, .profile-button {
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 24px;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .theme-toggle:hover, .profile-button:hover {
            background-color: rgba(0, 0, 0, 0.1);
        }
        
        .search-container {
            margin: 15px 20px;
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border-radius: 30px;
            border: 1px solid var(--border-color);
            background-color: var(--bg-card);
            color: var(--text-primary);
            font-family: 'Vazirmatn', sans-serif;
            box-sizing: border-box;
            transition: all 0.3s;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 255, 106, 0.2);
        }
        
        .search-button {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 20px;
            cursor: pointer;
        }
        
        .slider-container {
            max-width: 720px;
            height: 220px;
            margin: 16px auto 25px;
            overflow: hidden;
            border-radius: var(--border-radius);
            position: relative;
            background-color: var(--bg-card);
            box-shadow: 0 4px 15px rgba(0, 255, 100, 0.1);
        }
        
        .slider-wrapper {
            position: relative;
            width: 100%;
            height: 100%;
        }
        
        .slider-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 0.5s ease-in-out;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .slider-slide.active {
            opacity: 1;
            z-index: 1;
        }
        
        .slider-slide a {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100%;
        }
        
        .slider-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            background-color: var(--bg-card);
        }
        
        .slider-nav button {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.4);
            border: none;
            color: white;
            font-size: 22px;
            padding: 8px 12px;
            border-radius: 50%;
            cursor: pointer;
            z-index: 10;
            transition: all 0.3s;
        }
        
        .slider-nav button:hover {
            background: rgba(0, 0, 0, 0.7);
        }
        
        .slider-nav .prev {
            left: 10px;
        }
        
        .slider-nav .next {
            right: 10px;
        }
        
        .slider-dots {
            position: absolute;
            bottom: 10px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10;
            text-align: center;
        }
        
        .slider-dots span {
            display: inline-block;
            width: 10px;
            height: 10px;
            background: #555;
            border-radius: 50%;
            margin: 0 3px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .slider-dots .active {
            background: var(--primary-color);
            transform: scale(1.2);
        }
        
        .section {
            margin: 18px 16px 24px;
        }
        
        .section h2 {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--secondary-color);
            font-size: 18px;
            margin-bottom: 15px;
            padding: 12px 16px;
            background-color: var(--bg-card);
            border-right: 4px solid var(--secondary-color);
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .section h2 span {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .section h2 a {
            font-size: 13px;
            background: var(--primary-color);
            color: #000;
            padding: 6px 16px;
            border-radius: 30px;
            text-decoration: none;
            transition: 0.3s;
            font-weight: bold;
        }
        
        .section h2 a:hover {
            background: #00cc55;
            transform: translateY(-2px);
        }
        
        .horizontal-scroll {
            display: flex;
            overflow-x: auto;
            gap: 16px;
            padding: 10px 6px 14px;
            scroll-snap-type: x mandatory;
            scrollbar-width: thin;
            scrollbar-color: #444 #222;
        }
        
        .horizontal-scroll::-webkit-scrollbar {
            height: 6px;
        }
        
        .horizontal-scroll::-webkit-scrollbar-thumb {
            background: #444;
            border-radius: 8px;
        }
        
        .horizontal-scroll::-webkit-scrollbar-track {
            background: #222;
        }
        
        .content-card {
            width: var(--image-width);
            background: var(--bg-card);
            border-radius: var(--border-radius);
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            scroll-snap-align: start;
            position: relative;
            flex-shrink: 0;
            border: 1px solid var(--border-color);
        }
        
        .content-card:hover {
            transform: scale(1.04);
            box-shadow: 0 4px 12px rgba(0, 255, 100, 0.2);
        }
        
        .image-container {
            position: relative;
            width: 100%;
            padding-top: calc(var(--image-ratio) * 100%);
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
        
        .content-card .info {
            padding: 12px;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }
        
        .content-card h3 {
            font-size: 14px;
            margin: 0 0 5px;
            color: var(--primary-color);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.4;
        }
        
        .content-card p {
            margin: 0;
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .content-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            background: var(--accent-color);
            color: white;
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: bold;
            z-index: 2;
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
            transition: 0.3s;
        }
        
        .navbar a.active {
            color: var(--primary-color);
        }
        
        .navbar a:hover {
            color: var(--primary-color);
            transform: translateY(-3px);
        }
        
        .loading-placeholder {
            width: var(--image-width);
            height: var(--image-height);
            background: var(--bg-card);
            border-radius: var(--border-radius);
            animation: pulse 1.5s infinite ease-in-out;
            flex-shrink: 0;
            border: 1px solid var(--border-color);
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 0.6; }
            50% { opacity: 0.3; }
        }
        
        /* بهینه‌سازی برای دستگاه‌های موبایل */
        @media (max-width: 600px) {
            :root {
                --image-width: 140px;
                --image-height: 200px;
            }
            
            .slider-container {
                height: 200px;
                margin: 14px auto 20px;
            }
            
            .section {
                margin: 16px 14px 22px;
            }
            
            .section h2 {
                font-size: 17px;
                padding: 10px 14px;
                margin-bottom: 13px;
            }
            
            .content-card h3 {
                font-size: 13px;
            }
            
            .content-card p {
                font-size: 11px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="<?= asset('image.png') ?>" alt="لوگو رسانه" />
        <div class="header-buttons">
            <a href="profile.php" class="profile-button">
                <i class="bi bi-person"></i>
            </a>
            <button class="theme-toggle" id="themeToggle">
                <i class="bi <?= $isDarkMode ? 'bi-sun' : 'bi-moon' ?>"></i>
            </button>
        </div>
    </div>

    <div class="search-container">
        <form action="search/search.php" method="GET">
            <input type="text" name="q" class="search-input" placeholder="جستجوی انیمه، موزیک، خواننده..." />
            <button type="submit" class="search-button">
                <i class="bi bi-search"></i>
            </button>
        </form>
    </div>

    <?php if (!empty($specialSlides)): ?>
    <div class="slider-container" aria-label="اسلایدر محتوا ویژه">
        <div class="slider-wrapper">
            <?php foreach ($specialSlides as $index => $slide): ?>
                <div class="slider-slide <?= $index === 0 ? 'active' : '' ?>" data-index="<?= $index ?>">
                    <a href="<?= htmlspecialchars($slide['url']) ?>" target="_blank" rel="noopener noreferrer">
                        <img src="<?= asset($slide['img']) ?>" alt="ویژه" loading="lazy" />
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="slider-nav">
            <button class="prev" aria-label="اسلاید قبلی" onclick="moveSlide(-1)">&#10094;</button>
            <button class="next" aria-label="اسلاید بعدی" onclick="moveSlide(1)">&#10095;</button>
        </div>
        <div class="slider-dots" id="sliderDots" role="tablist"></div>
    </div>
    <script>
        let currentSlide = 0;
        const slides = document.querySelectorAll('.slider-slide');
        const dotsContainer = document.getElementById('sliderDots');
        let autoSlideInterval;

        function renderDots() {
            dotsContainer.innerHTML = '';
            slides.forEach((slide, idx) => {
                const dot = document.createElement('span');
                dot.setAttribute('role', 'tab');
                dot.setAttribute('tabindex', '0');
                dot.onclick = () => moveToSlide(idx);
                dot.onkeydown = (e) => { if(e.key === 'Enter' || e.key === ' ') moveToSlide(idx); };
                if (idx === currentSlide) dot.classList.add('active');
                dotsContainer.appendChild(dot);
            });
        }

        function moveToSlide(index) {
            slides[currentSlide].classList.remove('active');
            currentSlide = (index + slides.length) % slides.length;
            slides[currentSlide].classList.add('active');
            renderDots();
            resetAutoSlide();
        }

        function moveSlide(n) {
            moveToSlide(currentSlide + n);
        }

        function startAutoSlide() {
            autoSlideInterval = setInterval(() => moveSlide(1), 5000);
        }

        function resetAutoSlide() {
            clearInterval(autoSlideInterval);
            startAutoSlide();
        }

        // توقف اسلایدشو هنگام تعامل کاربر
        const sliderContainer = document.querySelector('.slider-container');
        sliderContainer.addEventListener('mouseenter', () => clearInterval(autoSlideInterval));
        sliderContainer.addEventListener('mouseleave', startAutoSlide);
        sliderContainer.addEventListener('touchstart', () => clearInterval(autoSlideInterval));
        sliderContainer.addEventListener('touchend', startAutoSlide);

        // Initialize
        renderDots();
        startAutoSlide();

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowRight') {
                moveSlide(1);
            } else if (e.key === 'ArrowLeft') {
                moveSlide(-1);
            }
        });
    </script>
    <?php endif; ?>

    <div class="section">
        <h2><span>🔥 محبوب‌ترین‌ها</span> <a href="list.php?sort=popular">مشاهده همه</a></h2>
        <div class="horizontal-scroll">
            <?php if (empty($popularContents)): ?>
                <?php for ($i = 0; $i < 5; $i++): ?>
                    <div class="loading-placeholder"></div>
                <?php endfor; ?>
            <?php else: ?>
                <?php foreach ($popularContents as $item): ?>
                    <a href="content.php?id=<?= $item['id'] ?>" class="content-card">
                        <div class="content-badge"><?= htmlspecialchars($item['music_type']) ?></div>
                        <div class="image-container">
                            <img src="<?= asset($item['image_url'] ?? 'assets/image/placeholder.jpg') ?>" alt="کاور <?= htmlspecialchars($item['title']) ?>" loading="lazy" />
                        </div>
                        <div class="info">
                            <h3><?= htmlspecialchars($item['title']) ?></h3>
                            <p><?= htmlspecialchars($item['title_fa']) ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="section">
        <h2><span>🆕 جدیدترین‌ها</span> <a href="list.php?sort=latest">مشاهده همه</a></h2>
        <div class="horizontal-scroll">
            <?php if (empty($latestContents)): ?>
                <?php for ($i = 0; $i < 5; $i++): ?>
                    <div class="loading-placeholder"></div>
                <?php endfor; ?>
            <?php else: ?>
                <?php foreach ($latestContents as $item): ?>
                    <a href="content.php?id=<?= $item['id'] ?>" class="content-card">
                        <div class="content-badge"><?= htmlspecialchars($item['music_type']) ?></div>
                        <div class="image-container">
                            <img src="<?= asset($item['image_url'] ?? 'assets/image/placeholder.jpg') ?>" alt="کاور <?= htmlspecialchars($item['title']) ?>" loading="lazy" />
                        </div>
                        <div class="info">
                            <h3><?= htmlspecialchars($item['title']) ?></h3>
                            <p><?= htmlspecialchars($item['title_fa']) ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="section">
        <h2><span>🎵 اوپنینگ ها</span> <a href="list.php?type=opening">مشاهده همه</a></h2>
        <div class="horizontal-scroll">
            <?php if (empty($openings)): ?>
                <?php for ($i = 0; $i < 5; $i++): ?>
                    <div class="loading-placeholder"></div>
                <?php endfor; ?>
            <?php else: ?>
                <?php foreach ($openings as $item): ?>
                    <a href="content.php?id=<?= $item['id'] ?>" class="content-card">
                        <div class="image-container">
                            <img src="<?= asset($item['image_url'] ?? 'assets/image/placeholder.jpg') ?>" alt="کاور <?= htmlspecialchars($item['title']) ?>" loading="lazy" />
                        </div>
                        <div class="info">
                            <h3><?= htmlspecialchars($item['title']) ?></h3>
                            <p><?= htmlspecialchars($item['title_fa']) ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="section">
        <h2><span>🎶 اندینگ ها</span> <a href="list.php?type=ending">مشاهده همه</a></h2>
        <div class="horizontal-scroll">
            <?php if (empty($endings)): ?>
                <?php for ($i = 0; $i < 5; $i++): ?>
                    <div class="loading-placeholder"></div>
                <?php endfor; ?>
            <?php else: ?>
                <?php foreach ($endings as $item): ?>
                    <a href="content.php?id=<?= $item['id'] ?>" class="content-card">
                        <div class="image-container">
                            <img src="<?= asset($item['image_url'] ?? 'assets/image/placeholder.jpg') ?>" alt="کاور <?= htmlspecialchars($item['title']) ?>" loading="lazy" />
                        </div>
                        <div class="info">
                            <h3><?= htmlspecialchars($item['title']) ?></h3>
                            <p><?= htmlspecialchars($item['title_fa']) ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="section">
        <h2><span>🎼 موسیقی زمینه (OST)</span> <a href="list.php?type=ost">مشاهده همه</a></h2>
        <div class="horizontal-scroll">
            <?php if (empty($osts)): ?>
                <?php for ($i = 0; $i < 5; $i++): ?>
                    <div class="loading-placeholder"></div>
                <?php endfor; ?>
            <?php else: ?>
                <?php foreach ($osts as $item): ?>
                    <a href="content.php?id=<?= $item['id'] ?>" class="content-card">
                        <div class="image-container">
                            <img src="<?= asset($item['image_url'] ?? 'assets/image/placeholder.jpg') ?>" alt="کاور <?= htmlspecialchars($item['title']) ?>" loading="lazy" />
                        </div>
                        <div class="info">
                            <h3><?= htmlspecialchars($item['title']) ?></h3>
                            <p><?= htmlspecialchars($item['title_fa']) ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="navbar">
        <a href="index.php" class="nav-item active"><i class="bi bi-house-door-fill"></i>خانه</a>
        <a href="categories.php" class="nav-item"><i class="bi bi-grid-1x2-fill"></i>دسته‌ها</a>
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