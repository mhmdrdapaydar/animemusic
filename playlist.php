<?php
require_once __DIR__ . '/includes/headless.php';

// بررسی پارامتر ID
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    header("Location: profile.php");
    exit;
}

$playlistId = (int)$_GET['id'];

// بررسی وضعیت تم کاربر
$isDarkMode = am_theme();

// اتصال به دیتابیس‌ها
$db_users   = am_users_db();
$db_content = am_content_db();

// بررسی وضعیت VIP کاربر — موتور مرکزی انقضا (رفع باگ)
$isVIP = false;
if (isset($_SESSION['user_id'])) {
    $userData = am_current_user();
    if ($userData && $userData['subscription_status'] === 'vip') {
        $isVIP = true;
    }
}

// دریافت اطلاعات پلی‌لیست
$stmt = $db_users->prepare("
    SELECT p.*, u.username, u.first_name, u.last_name 
    FROM playlists p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.id = ?
");
$stmt->execute([$playlistId]);
$playlist = $stmt->fetch(PDO::FETCH_ASSOC);

// بررسی وجود پلی‌لیست و دسترسی کاربر
if (!$playlist) {
    header("Location: profile.php");
    exit;
}

// بررسی دسترسی (اگر پلی‌لیست خصوصی است و کاربر لاگین نکرده یا مالک نیست)
if (!$playlist['is_public']) {
    if (!isset($_SESSION['user_id']) || $playlist['user_id'] != $_SESSION['user_id']) {
        header("Location: login.php");
        exit;
    }
}

// دریافت محتواهای پلی‌لیست
$stmt = $db_users->prepare("
    SELECT pc.content_id, pc.added_at, pc.sort_order 
    FROM playlist_contents pc 
    WHERE pc.playlist_id = ? 
    ORDER BY pc.sort_order, pc.added_at
");
$stmt->execute([$playlistId]);
$playlistContents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// دریافت اطلاعات کامل محتواها
$playlistItems = [];
$musicFiles = [];
if (!empty($playlistContents)) {
    $contentIds = array_column($playlistContents, 'content_id');
    $placeholders = implode(',', array_fill(0, count($contentIds), '?'));
    
    $stmt = $db_content->prepare("
        SELECT ac.*, asr.title_fa, asr.title_en, mt.name as music_type 
        FROM anime_contents ac 
        JOIN anime_series asr ON ac.anime_id = asr.id 
        JOIN music_types mt ON ac.music_type_id = mt.id 
        WHERE ac.id IN ($placeholders)
    ");
    $stmt->execute($contentIds);
    $contents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ترکیب اطلاعات پلی‌لیست و محتوا
    foreach ($playlistContents as $pc) {
        foreach ($contents as $content) {
            if ($content['id'] == $pc['content_id']) {
                $playlistItems[] = array_merge($content, [
                    'added_at' => $pc['added_at'],
                    'sort_order' => $pc['sort_order']
                ]);
                $musicFiles[] = [
                    'id' => $content['id'],
                    'title' => $content['title'],
                    'file' => $content['music_file_url'],
                    'image' => $content['image_url'] ?? 'assets/image/placeholder.jpg'
                ];
                break;
            }
        }
    }
}

// پردازش حذف محتوا از پلی‌لیست
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['remove_from_playlist'])) {
        $contentId = (int)$_POST['content_id'];
        
        // بررسی مالکیت پلی‌لیست
        if (isset($_SESSION['user_id']) && $playlist['user_id'] == $_SESSION['user_id']) {
            $stmt = $db_users->prepare("DELETE FROM playlist_contents WHERE playlist_id = ? AND content_id = ?");
            $stmt->execute([$playlistId, $contentId]);
            
            // رفرش صفحه
            header("Location: playlist.php?id=" . $playlistId);
            exit;
        }
    }
}

// تابع تبدیل تاریخ (شمسی)
function format_date($date) {
    return am_format_date($date);
}

// تولید لینک اشتراک‌گذاری
$shareUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
?>

<!DOCTYPE html>
<html lang="<?= am_e(am_lang()) ?>" dir="<?= am_e(am_lang_dir()) ?>" data-theme="<?= $isDarkMode ? 'dark' : 'light' ?>">
<head>
  <?= am_lang_base_tag() ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($playlist['name']) ?> | <?= am_te('site_name') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn.min.css" rel="stylesheet">
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
            --transition-speed: 0.3s;
        }
        
        [data-theme="light"] {
            --bg-primary: var(--bg-light);
            --bg-card: var(--card-bg-light);
            --text-primary: var(--text-light);
            --text-secondary: var(--text-gray-light);
            --border-color: var(--border-color-light);
            --shadow-color: rgba(0, 0, 0, 0.1);
        }
        
        [data-theme="dark"] {
            --bg-primary: var(--bg-dark);
            --bg-card: var(--card-bg-dark);
            --text-primary: var(--text-dark);
            --text-secondary: var(--text-gray-dark);
            --border-color: var(--border-color-dark);
            --shadow-color: rgba(0, 0, 0, 0.3);
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0;
            padding: 0;
            transition: background-color var(--transition-speed), color var(--transition-speed);
            padding-bottom: 90px;
        }
        
        .header {
            background: var(--bg-card);
            padding: 15px 20px;
            text-align: center;
            box-shadow: 0 4px 12px var(--shadow-color);
            margin-bottom: 30px;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header img {
            height: 50px;
        }
        
        .theme-toggle {
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 24px;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: background-color var(--transition-speed);
        }
        
        .theme-toggle:hover {
            background-color: rgba(0, 0, 0, 0.1);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .playlist-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: var(--border-radius);
            padding: 30px;
            margin-bottom: 30px;
            color: #000;
            text-align: center;
            box-shadow: 0 8px 25px var(--shadow-color);
            position: relative;
        }
        
        .playlist-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .playlist-meta {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 15px;
            font-size: 14px;
            flex-wrap: wrap;
        }
        
        .playlist-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .playlist-owner {
            margin-top: 15px;
            font-size: 16px;
        }
        
        .playlist-description {
            margin-top: 15px;
            font-size: 16px;
            line-height: 1.6;
        }
        
        .share-buttons {
            position: absolute;
            top: 15px;
            left: 15px;
            display: flex;
            gap: 10px;
        }
        
        .share-btn {
            background: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all var(--transition-speed);
        }
        
        .share-btn:hover {
            background: rgba(0, 0, 0, 0.9);
            transform: scale(1.1);
        }
        
        .share-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
        }
        
        .share-modal-content {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 25px;
            width: 90%;
            max-width: 500px;
            border: 2px solid var(--primary-color);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .share-url {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 10px 15px;
            margin: 15px 0;
            word-break: break-all;
            font-size: 14px;
            direction: ltr;
            text-align: left;
        }
        
        .player-section {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px var(--shadow-color);
            border: 1px solid var(--border-color);
        }
        
        .player-controls {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .control-buttons-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 15px;
            width: 100%;
        }
        
        .control-btn {
            background: transparent;
            border: none;
            color: var(--primary-color);
            font-size: 1.5rem;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all var(--transition-speed);
            border: 2px solid var(--primary-color);
        }
        
        .control-btn:hover {
            background: rgba(0, 255, 106, 0.1);
            transform: scale(1.1);
        }
        
        .play-btn {
            background: var(--primary-color);
            color: #000;
            width: 60px;
            height: 60px;
            font-size: 1.8rem;
            box-shadow: 0 0 20px rgba(0, 255, 106, 0.5);
        }
        
        .play-btn:hover {
            background: var(--secondary-color);
        }
        
        .player-info {
            width: 100%;
            min-width: 200px;
            text-align: center;
        }
        
        .now-playing {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--primary-color);
        }
        
        .player-progress {
            width: 100%;
            height: 6px;
            background: rgba(0, 0, 0, 0.1);
            border-radius: 3px;
            margin: 10px 0;
            cursor: pointer;
            direction: ltr;
        }
        
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--secondary-color), var(--primary-color));
            border-radius: 3px;
            width: 0%;
            transition: width 0.1s linear;
        }
        
        .player-options {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-top: 15px;
        }
        
        .option-btn {
            background: rgba(0, 0, 0, 0.1);
            border: 1px solid var(--primary-color);
            border-radius: 20px;
            color: var(--text-primary);
            padding: 8px 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all var(--transition-speed);
            font-size: 14px;
        }
        
        .option-btn.active {
            background: rgba(0, 255, 106, 0.2);
        }
        
        .option-btn:hover {
            background: rgba(0, 255, 106, 0.2);
        }
        
        .vip-only {
            position: relative;
            opacity: 0.7;
        }
        
        .vip-only::after {
            content: 'VIP';
            position: absolute;
            top: -8px;
            left: -8px;
            background: linear-gradient(135deg, #ffd700, #ff9800);
            color: #000;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: bold;
            z-index: 2;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .content-card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: 0 2px 10px var(--shadow-color);
            transition: all var(--transition-speed);
            border: 1px solid var(--border-color);
            position: relative;
        }
        
        .content-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px var(--shadow-color);
        }
        
        .content-card a {
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .content-card img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            transition: transform var(--transition-speed);
        }
        
        .content-card:hover img {
            transform: scale(1.05);
        }
        
        .content-info {
            padding: 12px;
        }
        
        .content-title {
            font-size: 14px;
            margin: 0 0 5px;
            color: var(--primary-color);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.4;
        }
        
        .content-type {
            font-size: 12px;
            color: var(--text-secondary);
            margin: 0;
        }
        
        .remove-btn {
            position: absolute;
            top: 10px;
            left: 10px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: rgba(220, 53, 69, 0.8);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 14px;
            transition: all var(--transition-speed);
        }
        
        .remove-btn:hover {
            background: rgba(220, 53, 69, 1);
            transform: scale(1.1);
        }
        
        .empty-state {
            text-align: center;
            color: var(--text-secondary);
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 64px;
            display: block;
            margin-bottom: 20px;
            color: var(--text-secondary);
        }
        
        .empty-state p {
            font-size: 18px;
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 12px 25px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: #000;
            border: none;
            border-radius: var(--border-radius);
            font-family: 'Vazirmatn', sans-serif;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all var(--transition-speed);
            box-shadow: 0 4px 12px rgba(0, 255, 106, 0.3);
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 255, 106, 0.4);
        }
        
        .btn-secondary {
            background: rgba(108, 117, 125, 0.2);
            color: var(--text-primary);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.2);
        }
        
        .btn-secondary:hover {
            background: rgba(108, 117, 125, 0.3);
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
            box-shadow: 0 -2px 10px var(--shadow-color);
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
        
        .vip-message {
            text-align: center;
            padding: 20px;
            background: var(--bg-card);
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            box-shadow: 0 4px 20px var(--shadow-color);
            border: 1px solid var(--border-color);
        }
        
        .vip-message i {
            font-size: 48px;
            color: #ffd700;
            display: block;
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
            
            .playlist-header {
                padding: 20px;
            }
            
            .playlist-title {
                font-size: 24px;
            }
            
            .playlist-meta {
                flex-direction: column;
                gap: 10px;
            }
            
            .player-controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .player-info {
                min-width: auto;
            }
        }
    </style>
    <link rel="icon" type="image/x-icon" href="favicon.ico" />
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png" />
    <link rel="apple-touch-icon" href="apple-touch-icon.png" />
    <link rel="stylesheet" href="assets/site.css?v=4" />
    <script defer src="assets/site.js?v=1"></script>
</head>
<body>
    <div class="header">
        <img src="image.png" alt="لوگو رسانه من">
        <button class="theme-toggle" id="themeToggle">
            <i class="bi <?= $isDarkMode ? 'bi-sun' : 'bi-moon' ?>"></i>
        </button>
    </div>
    
    <div class="container">
        <div class="playlist-header">
            <?php if ($playlist['is_public']): ?>
            <div class="share-buttons">
                <button class="share-btn" onclick="showShareModal()">
                    <i class="bi bi-share"></i>
                </button>
            </div>
            <?php endif; ?>
            
            <h1 class="playlist-title"><?= htmlspecialchars($playlist['name']) ?></h1>
            
            <?php if (!empty($playlist['description'])): ?>
                <p class="playlist-description"><?= htmlspecialchars($playlist['description']) ?></p>
            <?php endif; ?>
            
            <div class="playlist-meta">
                <span class="playlist-meta-item">
                    <i class="bi bi-music-note-list"></i>
                    <?= count($playlistItems) ?> موزیک
                </span>
                <span class="playlist-meta-item">
                    <i class="bi <?= $playlist['is_public'] ? 'bi-globe' : 'bi-lock' ?>"></i>
                    <?= $playlist['is_public'] ? 'عمومی' : 'خصوصی' ?>
                </span>
                <span class="playlist-meta-item">
                    <i class="bi bi-calendar"></i>
                    ایجاد شده در: <?= format_date($playlist['created_at']) ?>
                </span>
            </div>
            
            <div class="playlist-owner">
                ساخته شده توسط: <?= htmlspecialchars($playlist['first_name'] . ' ' . $playlist['last_name']) ?>
            </div>
        </div>
        
        <?php if (!empty($playlistItems)): ?>
            <?php if ($isVIP): ?>
            <!-- پخش کننده پلی‌لیست فقط برای کاربران VIP -->
            <div class="player-section">
                <!-- پخش‌کننده صوتی - فقط صوت دریافت می‌شود -->
                <audio id="playlist-audio" preload="metadata" style="display: none;"></audio>
                
                <div class="player-controls">
                    <div class="control-buttons-row">
                        <button class="control-btn" id="prev-btn">
                            <i class="bi bi-skip-backward-fill"></i>
                        </button>
                        <button class="control-btn play-btn" id="play-btn">
                            <i class="bi bi-play-fill"></i>
                        </button>
                        <button class="control-btn" id="next-btn">
                            <i class="bi bi-skip-forward-fill"></i>
                        </button>
                    </div>
                    
                    <div class="player-info">
                        <div class="now-playing" id="now-playing">
                            پخش کننده آماده است
                        </div>
                        <div class="player-progress" id="progress-bar">
                            <div class="progress-bar" id="progress"></div>
                        </div>
                    </div>
                </div>
                
                <div class="player-options">
                    <button class="option-btn" id="loop-btn">
                        <i class="bi bi-arrow-repeat"></i> حلقه بی‌پایان
                    </button>
                    <button class="option-btn" id="shuffle-btn">
                        <i class="bi bi-shuffle"></i> پخش تصادفی
                    </button>
                </div>
            </div>
            <?php else: ?>
            <!-- پیام برای کاربران غیر VIP -->
            <div class="vip-message">
                <i class="bi bi-star-fill"></i>
                <h3>این پخش کننده فقط برای کاربران ویژه (VIP) در دسترس است</h3>
                <p>برای دسترسی به امکانات پیشرفته پخش موزیک، لطفاً اشتراک VIP تهیه کنید.</p>
                <a href="vip.php" class="btn">
                    <i class="bi bi-star"></i> ارتقاء به VIP
                </a>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (!empty($playlistItems)): ?>
            <div class="content-grid">
                <?php foreach ($playlistItems as $index => $item): ?>
                    <div class="content-card" data-id="<?= $item['id'] ?>" data-file="<?= htmlspecialchars($item['music_file_url']) ?>" data-title="<?= htmlspecialchars($item['title']) ?>">
                        <?php if (isset($_SESSION['user_id']) && $playlist['user_id'] == $_SESSION['user_id']): ?>
                            <form method="post" class="remove-form">
                                <input type="hidden" name="remove_from_playlist" value="1">
                                <input type="hidden" name="content_id" value="<?= $item['id'] ?>">
                                <button type="submit" class="remove-btn" onclick="return confirm('آیا از حذف این موزیک از لیست پخش مطمئن هستید؟')">
                                    <i class="bi bi-x"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                        <a href="content.php?id=<?= $item['id'] ?>">
                            <img src="<?= htmlspecialchars($item['image_url'] ?? 'assets/image/placeholder.jpg') ?>" 
                                 alt="<?= htmlspecialchars($item['title']) ?>">
                            <div class="content-info">
                                <h3 class="content-title"><?= htmlspecialchars($item['title']) ?></h3>
                                <p class="content-type"><?= htmlspecialchars($item['title_fa']) ?></p>
                                <p class="content-type"><?= htmlspecialchars($item['music_type']) ?></p>
                                <p class="content-type">
                                    <i class="bi bi-calendar"></i>
                                    افزوده شده در: <?= format_date($item['added_at']) ?>
                                </p>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-music-note-list"></i>
                <p>این لیست پخش خالی است.</p>
                <a href="categories.php" class="btn">
                    <i class="bi bi-plus-lg"></i> افزودن موزیک
                </a>
            </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="profile.php" class="btn-secondary">
                <i class="bi bi-arrow-right"></i> بازگشت به پروفایل
            </a>
        </div>
    </div>
    
    <!-- مودال اشتراک‌گذاری -->
    <div class="share-modal" id="share-modal">
        <div class="share-modal-content">
            <h3 style="text-align: center; color: var(--primary-color); margin-bottom: 20px;">اشتراک‌گذاری لیست پخش</h3>
            <p>از لینک زیر برای اشتراک‌گذاری این لیست پخش استفاده کنید:</p>
            <div class="share-url" id="share-url"><?= $shareUrl ?></div>
            <button class="btn" onclick="copyShareUrl()">
                <i class="bi bi-clipboard"></i> کپی لینک
            </button>
            <button class="btn-secondary" style="margin-right: 10px;" onclick="closeShareModal()">
                بستن
            </button>
        </div>
    </div>
    
    <div class="navbar">
        <a href="index.php"><i class="bi bi-house"></i> خانه</a>
        <a href="categories.php"><i class="bi bi-grid-1x2-fill"></i> دسته‌ها</a>
        <a href="vip.php"><i class="bi bi-star"></i> VIP</a>
        <a href="profile.php" class="active"><i class="bi bi-person"></i> پروفایل</a>
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
        
        // مدیریت مودال اشتراک‌گذاری
        function showShareModal() {
            document.getElementById('share-modal').style.display = 'flex';
        }
        
        function closeShareModal() {
            document.getElementById('share-modal').style.display = 'none';
        }
        
        function copyShareUrl() {
            const shareUrl = document.getElementById('share-url');
            navigator.clipboard.writeText(shareUrl.textContent).then(() => {
                alert('لینک با موفقیت کپی شد!');
            }).catch(err => {
                console.error('خطا در کپی لینک:', err);
            });
        }
        
        // بستن مودال با کلیک خارج از آن
        document.getElementById('share-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeShareModal();
            }
        });
        
        // سیستم پخش پلی‌لیست - فقط فایل‌های صوتی
        <?php if (!empty($playlistItems) && $isVIP): ?>
        const playlistAudio = document.getElementById('playlist-audio');
        const playBtn = document.getElementById('play-btn');
        const prevBtn = document.getElementById('prev-btn');
        const nextBtn = document.getElementById('next-btn');
        const loopBtn = document.getElementById('loop-btn');
        const shuffleBtn = document.getElementById('shuffle-btn');
        const nowPlaying = document.getElementById('now-playing');
        const progressBar = document.getElementById('progress-bar');
        const progress = document.getElementById('progress');
        
        let currentTrackIndex = 0;
        let isPlaying = false;
        let isLooping = false;
        let isShuffling = false;
        
        // لیست موزیک‌ها
        const musicList = <?= json_encode($musicFiles) ?>;
        
        // رویدادهای دکمه‌ها
        playBtn.addEventListener('click', togglePlay);
        prevBtn.addEventListener('click', playPrevious);
        nextBtn.addEventListener('click', playNext);
        loopBtn.addEventListener('click', toggleLoop);
        shuffleBtn.addEventListener('click', toggleShuffle);
        
        // رویدادهای المان صوتی
        playlistAudio.addEventListener('loadedmetadata', () => {
            nowPlaying.textContent = `در حال پخش: ${musicList[currentTrackIndex].title}`;
        });
        
        playlistAudio.addEventListener('timeupdate', updateProgress);
        playlistAudio.addEventListener('ended', playNext);
        playlistAudio.addEventListener('play', () => {
            playBtn.innerHTML = '<i class="bi bi-pause-fill"></i>';
            isPlaying = true;
            
            // هایلایت کردن کارت فعال
            document.querySelectorAll('.content-card').forEach(card => {
                card.style.borderColor = 'var(--border-color)';
            });
            document.querySelectorAll('.content-card')[currentTrackIndex].style.borderColor = 'var(--primary-color)';
        });
        
        playlistAudio.addEventListener('pause', () => {
            playBtn.innerHTML = '<i class="bi bi-play-fill"></i>';
            isPlaying = false;
        });
        
        // رویدادهای کلیک روی کارت‌های موزیک
        document.querySelectorAll('.content-card').forEach((card, index) => {
            card.addEventListener('click', (e) => {
                // اگر روی لینک یا دکمه حذف کلیک شده، کاری نکن
                if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON' || e.target.closest('a') || e.target.closest('button')) {
                    return;
                }
                
                // پخش موزیک انتخاب شده
                playTrack(index);
            });
        });
        
        // توابع پخش
        function togglePlay() {
            if (isPlaying) {
                pauseAudio();
            } else {
                playAudio();
            }
        }
        
        function playAudio() {
            if (musicList.length > 0) {
                if (!playlistAudio.src) {
                    playTrack(0);
                } else {
                    playlistAudio.play();
                }
            }
        }
        
        function pauseAudio() {
            playlistAudio.pause();
        }
        
        function playTrack(index) {
            currentTrackIndex = index;
            const track = musicList[index];
            
            // تنظیم منبع صوتی - فقط صوت دریافت می‌شود
            playlistAudio.src = track.file;
            playlistAudio.load();
            
            // شروع پخش
            playlistAudio.play();
        }
        
        function playPrevious() {
            if (musicList.length === 0) return;
            
            let newIndex = currentTrackIndex - 1;
            if (newIndex < 0) {
                newIndex = musicList.length - 1;
            }
            
            playTrack(newIndex);
        }
        
        function playNext() {
            if (musicList.length === 0) return;
            
            let newIndex = currentTrackIndex + 1;
            if (newIndex >= musicList.length) {
                if (isLooping) {
                    newIndex = 0; // برگشت به ابتدا در حالت حلقه
                } else {
                    pauseAudio();
                    return;
                }
            }
            
            playTrack(newIndex);
        }
        
        function toggleLoop() {
            isLooping = !isLooping;
            loopBtn.classList.toggle('active', isLooping);
            
            if (isLooping) {
                loopBtn.innerHTML = '<i class="bi bi-arrow-repeat"></i> حلقه فعال';
            } else {
                loopBtn.innerHTML = '<i class="bi bi-arrow-repeat"></i> حلقه بی‌پایان';
            }
        }
        
        function toggleShuffle() {
            isShuffling = !isShuffling;
            shuffleBtn.classList.toggle('active', isShuffling);
            
            if (isShuffling) {
                shuffleBtn.innerHTML = '<i class="bi bi-shuffle"></i> تصادفی فعال';
                // شافل کردن لیست
                for (let i = musicList.length - 1; i > 0; i--) {
                    const j = Math.floor(Math.random() * (i + 1));
                    [musicList[i], musicList[j]] = [musicList[j], musicList[i]];
                }
            } else {
                shuffleBtn.innerHTML = '<i class="bi bi-shuffle"></i> پخش تصادفی';
                // بازگرداندن لیست به حالت اولیه
                // این قسمت نیاز به پیاده سازی ذخیره ترتیب اصلی دارد
            }
        }
        
        function updateProgress() {
            if (playlistAudio.duration) {
                const percent = (playlistAudio.currentTime / playlistAudio.duration) * 100;
                progress.style.width = `${percent}%`;
            }
        }
        
        // کلیک روی progress bar برای رفتن به زمان خاص
        progressBar.addEventListener('click', (e) => {
            if (playlistAudio.duration) {
                const rect = progressBar.getBoundingClientRect();
                const percent = (e.clientX - rect.left) / rect.width;
                playlistAudio.currentTime = percent * playlistAudio.duration;
                updateProgress();
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>