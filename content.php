<?php
require_once __DIR__ . '/includes/headless.php';
am_no_cache();

// آمار بازدید تبلیغات (جایگزین counter_ads.php) — با تفکیک زبان
am_log_visit(AM_VISITS_ADS_FILE, am_current_lang());

// اعتبارسنجی پارامتر ID
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    http_response_code(404);
    die("محتوا یافت نشد");
}

$id = (int)$_GET['id'];

// اتصال به دیتابیس‌ها با مدیریت خطا
try {
    $db_content = am_content_db();
    $db_content->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $db_users = am_users_db();
} catch (PDOException $e) {
    error_log("DB Error: " . $e->getMessage());
    die("خطا در اتصال به پایگاه داده");
}

// بررسی وضعیت تم کاربر
$isDarkMode = am_theme();

// بررسی وضعیت کاربر (VIP یا عادی) — موتور مرکزی انقضا اینجا هم اعمال می‌شود (رفع باگ)
$isVIP = false;
$userData = null;
$userId = null;
if (isset($_SESSION['user_id'])) {
    $userId = (int)$_SESSION['user_id'];
    // am_current_user وضعیت منقضی‌شده را خودکار به free برمی‌گرداند
    $userData = am_current_user();
    
    if ($userData && $userData['subscription_status'] === 'vip') {
        $isVIP = true;
    }
}

// تبلیغ مربوط به زبان جاری (برای کاربران غیر VIP)
$currentAd  = am_ad_for_lang(am_current_lang());
$adVideoUrl = am_lang_asset(isset($currentAd['video']) && $currentAd['video'] !== '' ? $currentAd['video'] : 'assets/ads/myad.mp4');
$adClickUrl = '/ad_click.php?lang=' . rawurlencode(am_current_lang());

// بررسی وضعیت علاقه‌مندی و پلی‌لیست‌ها
$isFavorite = false;
$userPlaylists = [];

if ($userId) {
    // بررسی آیا این محتوا در علاقه‌مندی‌های کاربر است
    $stmt = $db_users->prepare("SELECT * FROM user_favorites WHERE user_id = ? AND content_id = ?");
    $stmt->execute([$userId, $id]);
    $isFavorite = $stmt->fetch() !== false;
    
    // دریافت پلی‌لیست‌های کاربر
    $stmt = $db_users->prepare("SELECT * FROM playlists WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $userPlaylists = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// دریافت محتوا از دیتابیس
try {
    $stmt = $db_content->prepare("
        SELECT ac.*, asr.title_fa, asr.title_en, asr.description, asr.important_links, 
               mt.name as music_type, mt.id as music_type_id
        FROM anime_contents ac 
        JOIN anime_series asr ON ac.anime_id = asr.id 
        JOIN music_types mt ON ac.music_type_id = mt.id 
        WHERE ac.id = ?
    ");
    $stmt->execute([$id]);
    $content = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$content) {
        http_response_code(404);
        die("محتوا وجود ندارد");
    }
    
    // دریافت همه محتواهای این انیمه از همان نوع برای dropdown
    $stmt = $db_content->prepare("
        SELECT ac.id, ac.title, ac.music_file_url, ac.video_file_url, ac.image_url
        FROM anime_contents ac 
        WHERE ac.anime_id = ? AND ac.music_type_id = ?
        ORDER BY ac.season_number, ac.episode_number
    ");
    $stmt->execute([$content['anime_id'], $content['music_type_id']]);
    $relatedMusic = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // دریافت خوانندگان این محتوا
    $stmt = $db_content->prepare("
        SELECT s.* FROM singers s 
        JOIN content_singers cs ON s.id = cs.singer_id 
        WHERE cs.content_id = ?
    ");
    $stmt->execute([$id]);
    $singers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // دریافت محتواهای مرتبط (از همان انیمه) برای بخش پایین صفحه
    $stmt = $db_content->prepare("
        SELECT ac.*, mt.name as music_type 
        FROM anime_contents ac 
        JOIN music_types mt ON ac.music_type_id = mt.id 
        WHERE ac.anime_id = ? AND ac.id != ? 
        ORDER BY ac.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$content['anime_id'], $id]);
    $relatedContents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // افزایش تعداد بازدیدها
    $stmt = $db_content->prepare("UPDATE anime_contents SET view_count = view_count + 1 WHERE id = ?");
    $stmt->execute([$id]);
    
} catch (PDOException $e) {
    error_log("Query Error: " . $e->getMessage());
    die("خطا در دریافت اطلاعات");
}

// بستن اتصال به دیتابیس
$db_content = null;
$db_users = null;

// تنظیم متغیرها
$title = htmlspecialchars($content['title'], ENT_QUOTES, 'UTF-8');
$title_fa = htmlspecialchars($content['title_fa'], ENT_QUOTES, 'UTF-8');
$title_en = htmlspecialchars($content['title_en'], ENT_QUOTES, 'UTF-8');
$description = !empty($content['description']) ? nl2br(htmlspecialchars($content['description'], ENT_QUOTES, 'UTF-8')) : '';
$music_type = htmlspecialchars($content['music_type']);
$music_type_fa = $music_type;
switch (strtolower($music_type)) {
    case 'opening':
        $music_type_fa = 'اوپنینگ';
        break;
    case 'ending':
        $music_type_fa = 'اندینگ';
        break;
    case 'ost':
        $music_type_fa = 'موسیقی متن';
        break;
}
$image_url = htmlspecialchars($content['image_url'] ?? '/assets/image/placeholder.jpg');
$music_file = htmlspecialchars($content['music_file_url']);
$video_file = !empty($content['video_file_url']) ? htmlspecialchars($content['video_file_url']) : null;
$lyrics_text = !empty($content['lyrics_text']) ? nl2br(htmlspecialchars($content['lyrics_text'], ENT_QUOTES, 'UTF-8')) : null;
$lyrics_translation = !empty($content['lyrics_translation']) ? nl2br(htmlspecialchars($content['lyrics_translation'], ENT_QUOTES, 'UTF-8')) : null;
$season_number = !empty($content['season_number']) ? $content['season_number'] : null;
$episode_number = !empty($content['episode_number']) ? $content['episode_number'] : null;

// استخراج لینک‌های مهم
$important_links = [];
if (!empty($content['important_links'])) {
    $links = explode(',', $content['important_links']);
    foreach ($links as $link) {
        if (strpos($link, '=') !== false) {
            list($name, $url) = explode('=', $link, 2);
            $important_links[] = [
                'name' => trim($name),
                'url' => trim($url)
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?= am_e(am_lang()) ?>" dir="<?= am_e(am_lang_dir()) ?>" data-theme="<?= $isDarkMode ? 'dark' : 'light' ?>">
<head>
  <?= am_lang_base_tag() ?>
  <meta charset="UTF-8" />
  <?php
  // عنوان SEO چندزبانه: برای فارسی همان الگوی قبل، برای سایر زبان‌ها الگوی لاتین
  if (am_lang() === 'fa') {
      $page_title_text = "دانلود $music_type_fa $title_fa | $title_en | $title";
      $page_desc_text  = "دانلود $music_type_fa $title_fa با کیفیت بالا و لینک مستقیم. $title_en - $title";
  } else {
      $am_meta_type = strtolower($music_type);
      $am_meta_dl   = am_t('download');
      $page_title_text = "$am_meta_dl $am_meta_type $title_en | $title";
      $page_desc_text  = "$am_meta_dl $am_meta_type '$title_en' ($title) — anime song, direct link, high quality.";
  }
  ?>
  <title><?= am_e($page_title_text) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="<?= am_e($page_desc_text) ?>" />
  <?= am_hreflang_links('content.php?id=' . $id) ?>
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
      padding: 0;
      line-height: 1.6;
      transition: background-color 0.3s, color 0.3s;
    }

    .header {
      background: var(--bg-card);
      padding: 12px 20px;
      text-align: center;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
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
    
    .user-status {
      display: flex;
      align-items: center;
      gap: 10px;
      color: var(--text-secondary);
      font-size: 14px;
    }
    
    .vip-badge {
      background: linear-gradient(135deg, #ffd700, #ff9800);
      color: #000;
      padding: 4px 10px;
      border-radius: 12px;
      font-weight: bold;
      font-size: 12px;
    }
    
    h2 {
      text-align: center;
      margin-top: 20px;
      font-size: 1.8rem;
      color: var(--secondary-color);
      padding: 0 15px;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .container {
      max-width: 1000px;
      margin: 0 auto;
      padding: 15px;
      padding-bottom: 80px; /* اضافه کردن padding برای ناوبری پایین */
    }

    .card {
      background: var(--bg-card);
      border-radius: var(--border-radius);
      padding: 20px;
      margin-top: 20px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      border-left: 4px solid var(--primary-color);
      border: 1px solid var(--border-color);
    }

    .card h3 {
      color: var(--primary-color);
      margin-bottom: 12px;
      font-size: 1.4rem;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .card p, .card li {
      font-size: 1rem;
      color: var(--text-secondary);
      line-height: 1.8;
    }

    .center {
      text-align: center;
    }

    .media-container {
      position: relative;
      overflow: hidden;
      border-radius: var(--border-radius);
      margin: 20px auto;
      max-width: 100%;
      background: var(--bg-card);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
      border: 1px solid var(--border-color);
    }

    video, img, audio {
      display: block;
      width: 100%;
      max-width: 100%;
    }

    .video-poster {
      position: relative;
      cursor: pointer;
    }

    .video-poster img {
      width: 100%;
      height: auto;
    }

    .play-button {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 70px;
      height: 70px;
      background: rgba(0, 0, 0, 0.6);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 2;
      transition: all 0.3s;
    }

    .play-button::before {
      content: "";
      display: block;
      width: 0;
      height: 0;
      border-top: 18px solid transparent;
      border-left: 30px solid var(--primary-color);
      border-bottom: 18px solid transparent;
      margin-left: 8px;
    }

    .play-button:hover {
      transform: translate(-50%, -50%) scale(1.1);
      background: rgba(0, 0, 0, 0.8);
    }

    .button, .quality-button {
      background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
      color: #000;
      border: none;
      padding: 10px 18px;
      margin: 8px;
      border-radius: 30px;
      cursor: pointer;
      font-weight: bold;
      transition: all 0.3s ease;
      font-size: 1rem;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .button:hover, .quality-button:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
    }

    a.button-link {
      text-decoration: none;
      display: inline-block;
    }

    ul.links {
      list-style: none;
      padding: 0;
      margin-top: 15px;
    }

    ul.links li {
      margin-bottom: 12px;
      position: relative;
      padding-right: 20px;
    }

    ul.links li:before {
      content: "→";
      position: absolute;
      right: 0;
      color: var(--accent-color);
      font-size: 1.2rem;
    }

    ul.links a {
      color: var(--accent-color);
      text-decoration: none;
      transition: all 0.3s ease;
      font-weight: 500;
    }

    ul.links a:hover {
      color: var(--secondary-color);
      text-decoration: underline;
    }

    #main-content {
      <?php if (!$isVIP): ?>
        display: none;
      <?php else: ?>
        display: block;
      <?php endif; ?>
    }

    #ad-section {
      position: relative;
      margin: 20px auto;
      max-width: 800px;
      border-radius: var(--border-radius);
      overflow: hidden;
      background: var(--bg-card);
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
      border: 1px solid var(--border-color);
      <?php if ($isVIP): ?>
        display: none;
      <?php endif; ?>
    }

    #skip-btn, #unmute-btn, #ad-link {
      position: absolute;
      z-index: 10;
      font-size: 0.9rem;
      opacity: 0.9;
      font-weight: bold;
    }

    #skip-btn {
      top: 10px;
      right: 10px;
      padding: 8px 15px;
      background: rgba(0, 0, 0, 0.7);
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
    }

    #unmute-btn {
      top: 10px;
      left: 10px;
      padding: 8px 15px;
      background: rgba(0, 0, 0, 0.7);
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
    }

    #ad-link {
      position: absolute;
      bottom: 10px;
      right: 20px;
      background: var(--accent-color);
      color: white;
      padding: 8px 15px;
      border-radius: 25px;
      text-decoration: none;
      font-weight: bold;
      transition: all 0.3s ease;
    }

    #ad-link:hover {
      background: #ff8c00;
      transform: translateY(-2px);
    }

    .button-group {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 12px;
      margin: 20px 0;
    }

    .loading {
      display: none;
      text-align: center;
      padding: 25px;
      color: var(--primary-color);
      font-size: 1.2rem;
    }

    .quality-buttons {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 8px;
      margin: 15px 0;
    }

    .loading-placeholder {
      width: 100%;
      height: 200px;
      background: var(--bg-card);
      border-radius: var(--border-radius);
      animation: pulse 1.5s infinite ease-in-out;
      border: 1px solid var(--border-color);
    }
    
    @keyframes pulse {
      0%, 100% { opacity: 0.6; }
      50% { opacity: 0.3; }
    }
    
    /* استایل‌های جدید برای پخش‌کننده موزیک */
    .music-section {
      background: linear-gradient(135deg, var(--bg-card), var(--bg-primary));
      border-radius: 16px;
      padding: 25px;
      margin: 30px 0;
      position: relative;
      overflow: hidden;
      border: 1px solid var(--border-color);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }
    
    .music-cover {
      width: 250px;
      height: 250px;
      border-radius: 12px;
      margin: 0 auto 25px;
      overflow: hidden;
      border: 4px solid var(--primary-color);
      box-shadow: 0 0 30px rgba(0, 255, 106, 0.3);
    }
    
    .music-cover img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    .music-player {
      max-width: 600px;
      margin: 0 auto;
    }
    
    .player-controls {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 25px;
      margin-bottom: 25px;
    }
    
    .control-btn {
      background: transparent;
      border: none;
      color: var(--primary-color);
      font-size: 1.8rem;
      width: 65px;
      height: 65px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.3s ease;
      border: 2px solid var(--primary-color);
    }
    
    .control-btn:hover {
      background: rgba(0, 255, 106, 0.1);
      transform: scale(1.1);
    }
    
    .play-btn {
      background: var(--primary-color);
      color: #000;
      width: 80px;
      height: 80px;
      font-size: 2rem;
      box-shadow: 0 0 20px rgba(0, 255, 106, 0.5);
    }
    
    .play-btn:hover {
      background: var(--secondary-color);
      transform: scale(1.1);
    }
    
    .progress-container {
      margin: 30px 0;
    }
    
    .progress-bar {
      width: 100%;
      height: 10px;
      background: rgba(0, 0, 0, 0.1);
      border-radius: 5px;
      position: relative;
      cursor: pointer;
      direction: rtl;
    }
    
    .progress {
      height: 100%;
      background: linear-gradient(90deg, var(--secondary-color), var(--primary-color));
      border-radius: 5px;
      width: 0%;
      position: absolute;
      right: 0;
      top: 0;
      transition: width 0.1s linear;
    }
    
    .progress::after {
      content: '';
      position: absolute;
      left: -10px;
      top: 50%;
      transform: translateY(-50%);
      width: 20px;
      height: 20px;
      background: white;
      border-radius: 50%;
      box-shadow: 0 0 15px var(--primary-color);
    }
    
    .time-info {
      display: flex;
      justify-content: space-between;
      margin-top: 12px;
      color: var(--text-secondary);
      font-size: 1rem;
    }
    
    .volume-container {
      display: flex;
      align-items: center;
      gap: 15px;
      margin-top: 25px;
      max-width: 300px;
      margin: 25px auto 0;
    }
    
    .volume-slider {
      flex: 1;
      height: 6px;
      -webkit-appearance: none;
      background: rgba(0, 0, 0, 0.1);
      border-radius: 5px;
      outline: none;
    }
    
    .volume-slider::-webkit-slider-thumb {
      -webkit-appearance: none;
      width: 18px;
      height: 18px;
      border-radius: 50%;
      background: var(--secondary-color);
      cursor: pointer;
      box-shadow: 0 0 10px rgba(0, 255, 195, 0.5);
    }
    
    .additional-controls {
      display: flex;
      justify-content: center;
      gap: 15px;
      margin-top: 30px;
      flex-wrap: wrap;
    }
    
    .speed-control, .download-btn, .favorite-btn, .playlist-btn {
      background: rgba(0, 0, 0, 0.1);
      border: 1px solid var(--primary-color);
      border-radius: 30px;
      color: var(--text-primary);
      padding: 10px 20px;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 10px;
      transition: all 0.3s ease;
      font-size: 1rem;
    }
    
    .speed-control:hover, .download-btn:hover, .favorite-btn:hover, .playlist-btn:hover {
      background: rgba(0, 255, 106, 0.2);
      transform: translateY(-3px);
    }
    
    .favorite-btn.active {
      background: rgba(255, 0, 0, 0.2);
      border-color: #ff0000;
      color: #ff0000;
    }
    
    .vip-only {
      position: relative;
      overflow: hidden;
    }
    
    .vip-only::after {
      content: 'VIP';
      position: absolute;
      top: 8px;
      left: 8px;
      background: linear-gradient(135deg, #ffd700, #ff9800);
      color: #000;
      font-size: 11px;
      padding: 3px 8px;
      border-radius: 12px;
      font-weight: bold;
      z-index: 2;
    }
    
    .vip-overlay {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.85);
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      color: white;
      z-index: 5;
      padding: 20px;
      text-align: center;
      backdrop-filter: blur(5px);
    }
    
    .vip-overlay .button {
      margin-top: 15px;
    }
    
    .lyrics-section {
      margin: 30px 0;
    }
    
    .lyrics-container {
      display: grid;
      grid-template-columns: 1fr;
      gap: 20px;
      margin-top: 20px;
    }
    
    @media (min-width: 768px) {
      .lyrics-container {
        grid-template-columns: 1fr 1fr;
      }
    }
    
    .lyrics-box {
      background: var(--bg-card);
      border-radius: var(--border-radius);
      padding: 20px;
      border: 1px solid var(--border-color);
      position: relative;
    }
    
    .lyrics-box h4 {
      color: var(--primary-color);
      margin-top: 0;
      padding-bottom: 10px;
      border-bottom: 2px solid var(--primary-color);
    }
    
    .lyrics-text {
      white-space: pre-line;
      line-height: 1.8;
      max-height: 300px;
      overflow-y: auto;
    }
    
    /* محتوای ترجمه برای کاربران غیر VIP تار و غیرقابل خواندن باشد */
    .lyrics-box.non-vip .lyrics-text {
      filter: blur(8px);
      user-select: none;
      pointer-events: none;
      opacity: 0.5;
    }
    
    .related-section {
      margin: 40px 0;
    }
    
    .related-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
      gap: 15px;
      margin-top: 20px;
    }
    
    .related-item {
      background: var(--bg-card);
      border-radius: var(--border-radius);
      overflow: hidden;
      text-decoration: none;
      color: inherit;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      border: 1px solid var(--border-color);
    }
    
    .related-item:hover {
      transform: translateY(-4px);
      box-shadow: 0 4px 12px rgba(0, 255, 100, 0.2);
    }
    
    .related-item img {
      width: 100%;
      height: 120px;
      object-fit: cover;
    }
    
    .related-info {
      padding: 12px;
    }
    
    .related-info h4 {
      font-size: 14px;
      margin: 0 0 5px;
      color: var(--primary-color);
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      line-height: 1.4;
    }
    
    .related-info p {
      margin: 0;
      font-size: 12px;
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
    
    /* مودال پلی‌لیست */
    .modal {
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
    
    .modal-content {
      background: var(--bg-card);
      border-radius: var(--border-radius);
      padding: 25px;
      width: 90%;
      max-width: 500px;
      max-height: 80vh;
      overflow-y: auto;
      border: 2px solid var(--primary-color);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }
    
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      padding-bottom: 15px;
      border-bottom: 1px solid var(--border-color);
    }
    
    .modal-header h3 {
      margin: 0;
      color: var(--primary-color);
    }
    
    .close-modal {
      background: none;
      border: none;
      font-size: 24px;
      color: var(--text-secondary);
      cursor: pointer;
      transition: color 0.3s;
    }
    
    .close-modal:hover {
      color: var(--accent-color);
    }
    
    .playlist-list {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    
    .playlist-item {
      padding: 15px;
      border-radius: var(--border-radius);
      margin-bottom: 10px;
      background: var(--bg-primary);
      border: 1px solid var(--border-color);
      cursor: pointer;
      transition: all 0.3s ease;
    }
    
    .playlist-item:hover {
      background: rgba(0, 255, 106, 0.1);
      transform: translateX(-5px);
    }
    
    .playlist-item h4 {
      margin: 0 0 5px;
      color: var(--text-primary);
    }
    
    .playlist-item p {
      margin: 0;
      color: var(--text-secondary);
      font-size: 0.9rem;
    }
    
    .create-playlist-btn {
      background: var(--primary-color);
      color: #000;
      border: none;
      padding: 12px 20px;
      border-radius: 30px;
      cursor: pointer;
      font-weight: bold;
      margin-top: 15px;
      width: 100%;
      transition: all 0.3s ease;
    }
    
    .create-playlist-btn:hover {
      background: var(--secondary-color);
      transform: translateY(-2px);
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
      margin-bottom: -3px;
      transition: 0.3s;
    }
    
    .navbar a.active {
      color: var(--primary-color);
    }
    
    .navbar a:hover {
      color: var(--primary-color);
      transform: translateY(-3px);
    }

    @media (max-width: 768px) {
      .container {
        padding: 10px;
        padding-bottom: 70px; /* padding-bottom برای موبایل */
      }
      
      h2 {
        font-size: 1.6rem;
      }
      
      .button, .quality-button {
        padding: 9px 16px;
        font-size: 0.95rem;
      }
      
      .play-button {
        width: 60px;
        height: 60px;
      }
      
      .player-controls {
        gap: 15px;
      }
      
      .control-btn {
        width: 55px;
        height: 55px;
        font-size: 1.5rem;
      }
      
      .play-btn {
        width: 70px;
        height: 70px;
      }
      
      .additional-controls {
        flex-wrap: wrap;
      }
      
      .music-cover {
        width: 200px;
        height: 200px;
      }
      
      .lyrics-container {
        grid-template-columns: 1fr;
      }
    }
    
    @media (max-width: 480px) {
      .header {
        padding: 10px;
      }
      
      h2 {
        font-size: 1.4rem;
      }
      
      .music-cover {
        width: 180px;
        height: 180px;
      }
      
      .control-btn {
        width: 50px;
        height: 50px;
        font-size: 1.4rem;
      }
      
      .play-btn {
        width: 65px;
        height: 65px;
      }
      
      .related-grid {
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
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
    <img src="/image.png" alt="Logo" />
    <div>
      <button class="theme-toggle" id="themeToggle">
        <i class="bi <?= $isDarkMode ? 'bi-sun' : 'bi-moon' ?>"></i>
      </button>
      <div class="user-status">
        <?php if (isset($_SESSION['user_id'])): ?>
          <?php if ($isVIP): ?>
            <span class="vip-badge">VIP</span>
          <?php else: ?>
            <a href="<?= am_lang_url('vip.php') ?>" style="color: var(--secondary-color); text-decoration: none;"><?= am_te("upgrade_vip") ?></a>
          <?php endif; ?>
        <?php else: ?>
          <a href="<?= am_lang_url('login.php') ?>" style="color: var(--secondary-color); text-decoration: none;"><?= am_te("login_signup") ?></a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?= am_lang_switcher_flags('content.php?id=' . $id) ?>

  <h2><?= am_e(am_lang_content_title($title_en, $title_fa)) ?></h2>
  <?php if (!empty($title_fa) && am_lang() === 'fa'): ?>
    <h3 style="text-align: center; color: var(--text-secondary); margin-top: 5px;"><?= $title_fa ?></h3>
  <?php endif; ?>
  
  <div class="container">
    <?php if (!empty($description)): ?>
      <div class="card">
        <h3><i class="bi bi-journal-text"></i> <?= am_te("description_title") ?></h3>
        <p class="clamp"><?= $description ?></p>
      </div>
    <?php endif; ?>
    
    <!-- تبلیغ - فقط برای کاربران غیر VIP نمایش داده شود -->
    <?php if (!$isVIP): ?>
    <div id="ad-section">
      <video id="ad-video" src="<?= am_e($adVideoUrl) ?>" muted playsinline preload="metadata"></video>
      <a id="ad-link" href="<?= am_e($adClickUrl) ?>" target="_blank" rel="noopener noreferrer"><?= am_te('more_info') ?></a>
      <button id="skip-btn" class="button" disabled><?= am_te('skip_ad') ?> (10)</button>
      <button id="unmute-btn" class="button"><?= am_te('enable_sound') ?></button>
    </div>
    <?php endif; ?>
    
    <!-- محتوای اصلی -->
    <div id="main-content">
      <!-- dropdown برای انتخاب موزیک‌های دیگر از همین انیمه و نوع -->
      <?php if (count($relatedMusic) > 1): ?>
      <div class="card">
        <h3><i class="bi bi-list"></i> <?= am_te("choose_music") ?> — <?= $music_type ?></h3>
        <select id="music-selector" class="form-control">
          <?php foreach ($relatedMusic as $music): ?>
            <option value="<?= $music['id'] ?>" <?= $music['id'] == $id ? 'selected' : '' ?>>
              <?= htmlspecialchars($music['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      
      <div class="music-section">
        <div class="music-cover">
          <img src="<?= $image_url ?>" alt="کاور <?= $title ?>" loading="lazy">
        </div>
        
        <div class="music-player">
          <!-- پخش‌کننده صوتی - فقط صوت پخش می‌شود و هیچ ویدیویی بارگذاری نمی‌شود -->
          <audio id="music-audio" preload="metadata" controlslist="nodownload">
            <source src="<?= $music_file ?>">
            <?= am_te('audio_unsupported') ?>
          </audio>
          
          <div class="player-controls">
            <button class="control-btn" id="forward-btn">
              <i class="bi bi-skip-forward-fill"></i>
            </button>
            <button class="control-btn play-btn" id="play-btn">
              <i class="bi bi-play-fill"></i>
            </button>
            <button class="control-btn" id="rewind-btn">
              <i class="bi bi-skip-backward-fill"></i>
            </button>
          </div>
          
          <div class="progress-container">
            <div class="progress-bar" id="progress-bar">
              <div class="progress" id="progress"></div>
            </div>
            <div class="time-info">
              <span id="current-time">00:00</span>
              <span id="duration">00:00</span>
            </div>
          </div>
          
          <div class="volume-container">
            <i class="bi bi-volume-up-fill" style="color: var(--primary-color); font-size: 1.3rem;"></i>
            <input type="range" min="0" max="100" value="80" class="volume-slider" id="volume-slider">
          </div>
          
          <div class="additional-controls">
            <select class="speed-control" id="speed-control">
              <option value="0.75">0.75x</option>
              <option value="1" selected>1x</option>
              <option value="1.25">1.25x</option>
              <option value="1.5">1.5x</option>
              <option value="2">2x</option>
            </select>
            
            <?php if ($isVIP): ?>
              <a href="<?= $music_file ?>" download class="download-btn">
                <i class="bi bi-download"></i> <?= am_te("download_music") ?>
              </a>
            <?php else: ?>
              <div class="vip-only">
                <a href="<?= am_lang_url('vip.php') ?>" class="download-btn">
                  <i class="bi bi-download"></i> <?= am_te('download_vip') ?>
                </a>
              </div>
            <?php endif; ?>
            
            <!-- دکمه مورد علاقه -->
            <?php if ($isVIP): ?>
              <button class="favorite-btn <?= $isFavorite ? 'active' : '' ?>" id="favorite-btn">
                <i class="bi bi-heart<?= $isFavorite ? '-fill' : '' ?>"></i>
                <?= $isFavorite ? am_t("remove_favorite") : am_t("add_favorite") ?>
              </button>
            <?php else: ?>
              <div class="vip-only">
                <button class="favorite-btn" onclick="showVipMessage()">
                  <i class="bi bi-heart"></i> <?= am_te("add_favorite") ?> (VIP)
                </button>
              </div>
            <?php endif; ?>
            
            <!-- دکمه افزودن به پلی‌لیست -->
            <?php if ($isVIP): ?>
              <button class="playlist-btn" id="playlist-btn">
                <i class="bi bi-plus-circle"></i> <?= am_te("add_playlist") ?>
              </button>
            <?php else: ?>
              <div class="vip-only">
                <button class="playlist-btn" onclick="showVipMessage()">
                  <i class="bi bi-plus-circle"></i> <?= am_te("add_playlist") ?> (VIP)
                </button>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <!-- نمایش ویدیو (اگر موجود باشد) -->
      <?php if ($video_file): ?>
        <div class="card <?= !$isVIP ? 'vip-only' : '' ?>">
          <h3><i class="bi bi-play-btn-fill"></i> <?= am_te('video') ?></h3>
          <div class="media-container">
            <?php if (!$isVIP): ?>
              <div class="vip-overlay">
                <p><?= am_te('video_vip_msg') ?></p>
                <a href="<?= am_lang_url('vip.php') ?>" class="button"><?= am_te('buy_vip') ?></a>
              </div>
            <?php endif; ?>
            <!-- پخش‌کننده ویدیویی - فقط با درخواست کاربر (کلیک) لود می‌شود تا باند پخش موزیک اشغال نشود -->
            <video controls playsinline preload="none" poster="<?= $image_url ?>" style="<?= !$isVIP ? 'filter: blur(5px);' : '' ?>">
              <source src="<?= $video_file ?>" type="video/webm">
              <source src="<?= $video_file ?>" type="video/mp4">
              <?= am_te('video_unsupported') ?>
            </video>
          </div>
        </div>
      <?php endif; ?>
      
      <!-- اطلاعات تکمیلی -->
      <div class="card">
        <h3><i class="bi bi-info-circle"></i> <?= am_te('music_info') ?></h3>
        <p><strong><?= am_te('type_label') ?>:</strong> <?= $music_type ?></p>
        <?php if ($season_number): ?>
          <p><strong><?= am_te('season_label') ?>:</strong> <?= $season_number ?></p>
        <?php endif; ?>
        <?php if ($episode_number): ?>
          <p><strong><?= am_te('episode_label') ?>:</strong> <?= $episode_number ?></p>
        <?php endif; ?>
        <?php if (!empty($singers)): ?>
          <p><strong><?= am_te('singer') ?>:</strong> 
            <?php foreach ($singers as $index => $singer): ?>
              <?= htmlspecialchars($singer['name']) ?><?= $index < count($singers) - 1 ? '، ' : '' ?>
            <?php endforeach; ?>
          </p>
        <?php endif; ?>
        <p><strong><?= am_te('views_label') ?>:</strong> <?= number_format($content['view_count']) ?></p>
      </div>
      
      <!-- متن و ترجمه آهنگ -->
      <?php if ($lyrics_text || $lyrics_translation): ?>
        <div class="lyrics-section">
          <h3 style="text-align: center; color: var(--secondary-color);"><?= am_te("lyrics") ?></h3>
          <div class="lyrics-container">
            <?php if ($lyrics_text): ?>
              <div class="lyrics-box <?= !$isVIP ? 'non-vip' : '' ?>">
                <h4><?= am_te("original_lyrics") ?></h4>
                <div class="lyrics-text clamp"><?= $lyrics_text ?></div>
                <?php if (!$isVIP): ?>
                  <div class="vip-overlay">
                    <p><?= am_te('lyrics_vip_msg') ?></p>
                    <a href="<?= am_lang_url('vip.php') ?>" class="button"><?= am_te('buy_vip') ?></a>
                  </div>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            
            <?php if ($lyrics_translation): ?>
              <div class="lyrics-box <?= !$isVIP ? 'non-vip' : '' ?>">
                <h4><?= am_te("lyrics_translation") ?></h4>
                <div class="lyrics-text clamp"><?= $lyrics_translation ?></div>
                <?php if (!$isVIP): ?>
                  <div class="vip-overlay">
                    <p><?= am_te('lyrics_translation_vip') ?></p>
                    <a href="<?= am_lang_url('vip.php') ?>" class="button"><?= am_te('buy_vip') ?></a>
                  </div>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
      
      <!-- لینک‌های مهم -->
      <?php if (!empty($important_links)): ?>
        <div class="card">
          <h3><i class="bi bi-link-45deg"></i> <?= am_te('important_links') ?></h3>
          <ul class="links">
            <?php foreach ($important_links as $link): ?>
              <li><a href="<?= htmlspecialchars($link['url']) ?>" target="_blank"><?= htmlspecialchars($link['name']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      
      <!-- محتواهای مرتبط -->
      <?php if (!empty($relatedContents)): ?>
        <div class="related-section">
          <h3 style="text-align: center; color: var(--secondary-color);"><?= am_te('related') ?></h3>
          <div class="related-grid">
            <?php foreach ($relatedContents as $item): ?>
              <a href="<?= am_lang_url('content.php?id=' . $item['id']) ?>" class="related-item">
                <div class="badge"><?= htmlspecialchars($item['music_type']) ?></div>
                <img src="<?= htmlspecialchars($item['image_url'] ?? '/assets/image/placeholder.jpg') ?>" alt="<?= am_e($item['title']) ?>">
                <div class="related-info">
                  <h4><?= htmlspecialchars($item['title']) ?></h4>
                  <p><?= htmlspecialchars($item['music_type']) ?></p>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
      
      <!-- دکمه بازگشت -->
      <div>
      <center>
        <a href="<?= am_lang_url('index.php') ?>" class="button-link">
          <button class="button"><i class="bi bi-house-door"></i> <?= am_te('back_home') ?></button>
          </center>
        </a>
      </div>
    </div>
  </div>
  
  <!-- مودال پلی‌لیست -->
  <div class="modal" id="playlist-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3><?= am_te('add_playlist') ?></h3>
        <button class="close-modal">&times;</button>
      </div>
      <div id="playlist-container">
        <?php if (!empty($userPlaylists)): ?>
          <ul class="playlist-list">
            <?php foreach ($userPlaylists as $playlist): ?>
              <li class="playlist-item" data-id="<?= $playlist['id'] ?>">
                <h4><?= htmlspecialchars($playlist['name']) ?></h4>
                <?php if (!empty($playlist['description'])): ?>
                  <p><?= htmlspecialchars($playlist['description']) ?></p>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p><?= am_te('no_playlists') ?></p>
        <?php endif; ?>
        <button class="create-playlist-btn" id="create-playlist-btn">
          <i class="bi bi-plus-circle"></i> <?= am_te('create_playlist') ?>
        </button>
      </div>
    </div>
  </div>
  
  <div class="navbar">
    <a href="<?= am_lang_url('index.php') ?>" class="nav-item"><i class="bi bi-house-door-fill"></i><?= am_te('home') ?></a>
    <a href="<?= am_lang_url('categories.php') ?>" class="nav-item"><i class="bi bi-grid-1x2-fill"></i><?= am_te('categories') ?></a>
    <a href="<?= am_lang_url('search/search.php') ?>" class="nav-item"><i class="bi bi-search"></i><?= am_te('search') ?></a>
    <a href="<?= am_lang_url('about.php') ?>" class="nav-item"><i class="bi bi-info-circle"></i><?= am_te('about') ?></a>
  </div>
  
  <script>
    // --- سیستم تغییر تم ---
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
    
    // --- تبلیغ - فقط برای کاربران غیر VIP اجرا شود ---
    <?php if (!$isVIP): ?>
    const adVideo = document.getElementById("ad-video");
    const skipBtn = document.getElementById("skip-btn");
    const skipLabel = <?= json_encode(am_t('skip_ad')) ?>;
    const unmuteBtn = document.getElementById("unmute-btn");
    const adSection = document.getElementById("ad-section");
    const mainContent = document.getElementById("main-content");
    
    // لینک تبلیغ مستقیماً به ad_click.php اشاره می‌کند که کلیک را ثبت و سپس هدایت می‌کند.
    
    // مدیریت صدا
    unmuteBtn.onclick = () => {
      adVideo.muted = false;
      unmuteBtn.textContent = <?= json_encode(am_t('sound_on')) ?>;
      setTimeout(() => {
        unmuteBtn.style.opacity = "0.5";
      }, 2000);
    };
    
    // تایمر رد کردن تبلیغ
    let t = 10; // زمان تبلیغ 10 ثانیه
    const timer = setInterval(() => {
      skipBtn.textContent = `${skipLabel} (${t})`;
      if (--t < 0) {
        clearInterval(timer);
        skipBtn.disabled = false;
        skipBtn.textContent = skipLabel;
      }
    }, 1000);
    
    // مدیریت رد کردن تبلیغ
    skipBtn.onclick = skipAd;
    adVideo.addEventListener("ended", skipAd);
    
    function skipAd() {
      adVideo.pause();
      adSection.style.display = "none";
      mainContent.style.display = "block";
      clearInterval(timer); // توقف تایمر
    }
    
    // شروع پخش تبلیغ پس از لود صفحه
    window.addEventListener('DOMContentLoaded', () => {
      try {
        adVideo.play().catch(e => {
          console.log("پخش خودکار تبلیغ ممکن نیست:", e);
          skipAd();
        });
      } catch (e) {
        console.log("خطا در پخش تبلیغ:", e);
        skipAd();
      }
    });
    <?php else: ?>
    // برای کاربران VIP، محتوای اصلی مستقیماً نمایش داده می‌شود
    document.getElementById('main-content').style.display = 'block';
    <?php endif; ?>
    
    // --- مدیریت پخش‌کننده موزیک ---
    const audio = document.getElementById('music-audio');
    const playBtn = document.getElementById('play-btn');
    const progressBar = document.getElementById('progress-bar');
    const progress = document.getElementById('progress');
    const currentTimeEl = document.getElementById('current-time');
    const durationEl = document.getElementById('duration');
    const volumeSlider = document.getElementById('volume-slider');
    const speedControl = document.getElementById('speed-control');
    const rewindBtn = document.getElementById('rewind-btn');
    const forwardBtn = document.getElementById('forward-btn');
    
    // حالت پخش
    let isPlaying = false;

    // توقف هر ویدیوی فعال تا فقط «صوت موزیک» شنیده شود
    function stopAnyVideo() {
      document.querySelectorAll('video').forEach(function (v) {
        try { v.pause(); } catch (e) {}
      });
    }

    // پخش/توقف
    function togglePlay() {
      if (isPlaying) {
        audio.pause();
        playBtn.innerHTML = '<i class="bi bi-play-fill"></i>';
      } else {
        stopAnyVideo();
        audio.play();
        playBtn.innerHTML = '<i class="bi bi-pause-fill"></i>';
      }
      isPlaying = !isPlaying;
    }
    
    // فرمت زمان
    function formatTime(seconds) {
      const min = Math.floor(seconds / 60);
      const sec = Math.floor(seconds % 60);
      return `${min.toString().padStart(2, '0')}:${sec.toString().padStart(2, '0')}`;
    }
    
    // به‌روزرسانی پیشرفت
    function updateProgress() {
      if (isNaN(audio.duration)) return;
      
      const percent = (audio.currentTime / audio.duration) * 100;
      progress.style.width = `${percent}%`;
      currentTimeEl.textContent = formatTime(audio.currentTime);
      durationEl.textContent = formatTime(audio.duration);
    }
    
    // تنظیم پیشرفت بر اساس کلیک کاربر
    function setProgress(e) {
      const rect = progressBar.getBoundingClientRect();
      let position;
      
      if (e.type.includes('touch')) {
        position = (rect.right - e.touches[0].clientX) / rect.width;
      } else {
        position = (rect.right - e.clientX) / rect.width;
      }
      
      // محدود کردن موقعیت به بازه 0 تا 1
      position = Math.max(0, Math.min(1, position));
      audio.currentTime = position * audio.duration;
      updateProgress();
    }
    
    // تنظیم حجم صدا
    function setVolume() {
      audio.volume = volumeSlider.value / 100;
    }
    
    // تنظیم سرعت پخش
    function setSpeed() {
      audio.playbackRate = speedControl.value;
    }
    
    // توابع برای دکمه‌های عقب/جلو
    function rewind() {
      audio.currentTime = Math.max(0, audio.currentTime - 15);
    }
    
    function forward() {
      audio.currentTime = Math.min(audio.duration, audio.currentTime + 15);
    }
    
    // رویدادها
    playBtn.addEventListener('click', togglePlay);
    audio.addEventListener('timeupdate', updateProgress);
    
    // افزودن رویدادهای لمسی و ماوس
    progressBar.addEventListener('click', setProgress);
    progressBar.addEventListener('touchstart', function(e) {
      setProgress(e);
      progressBar.addEventListener('touchmove', setProgress);
    });
    
    // حذف شنونده حرکت هنگام رها کردن
    document.addEventListener('touchend', function() {
      progressBar.removeEventListener('touchmove', setProgress);
    });
    
    volumeSlider.addEventListener('input', setVolume);
    speedControl.addEventListener('change', setSpeed);
    rewindBtn.addEventListener('click', rewind);
    forwardBtn.addEventListener('click', forward);
    
    // تنظیم مدت زمان کل پس از بارگذاری
    audio.addEventListener('loadedmetadata', () => {
      durationEl.textContent = formatTime(audio.duration);
      updateProgress();
    });
    
    // بازنشانی پس از پایان پخش
    audio.addEventListener('ended', () => {
      isPlaying = false;
      playBtn.innerHTML = '<i class="bi bi-play-fill"></i>';
    });
    
    // به‌روزرسانی اولیه پیشرفت
    updateProgress();
    
    // مدیریت dropdown انتخاب موزیک
    <?php if (count($relatedMusic) > 1): ?>
    document.getElementById('music-selector').addEventListener('change', function() {
      window.location.href = <?= json_encode(am_lang_url('content.php')) ?> + '?id=' + this.value;
    });
    <?php endif; ?>
    
    // --- مدیریت علاقه‌مندی‌ها و پلی‌لیست‌ها ---
    <?php if ($isVIP && isset($_SESSION['user_id'])): ?>
    const favoriteBtn = document.getElementById('favorite-btn');
    const playlistBtn = document.getElementById('playlist-btn');
    const playlistModal = document.getElementById('playlist-modal');
    const closeModal = document.querySelector('.close-modal');
    const playlistItems = document.querySelectorAll('.playlist-item');
    const createPlaylistBtn = document.getElementById('create-playlist-btn');
    
    // مدیریت علاقه‌مندی‌ها
    favoriteBtn.addEventListener('click', function() {
      const isCurrentlyFavorite = this.classList.contains('active');
      const action = isCurrentlyFavorite ? 'remove' : 'add';
      
      fetch(<?= json_encode(am_lang_url('manage_favorite.php')) ?>, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `content_id=<?= $id ?>&action=${action}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          if (action === 'add') {
            this.classList.add('active');
            this.innerHTML = '<i class="bi bi-heart-fill"></i> ' + <?= json_encode(am_t('remove_fav_js')) ?>;
          } else {
            this.classList.remove('active');
            this.innerHTML = '<i class="bi bi-heart"></i> ' + <?= json_encode(am_t('add_fav_js')) ?>;
          }
        } else {
          alert(<?= json_encode(am_t('fav_update_error')) ?> + ': ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert(<?= json_encode(am_t('server_error')) ?>);
      });
    });
    
    // مدیریت پلی‌لیست‌ها
    playlistBtn.addEventListener('click', function() {
      playlistModal.style.display = 'flex';
    });
    
    closeModal.addEventListener('click', function() {
      playlistModal.style.display = 'none';
    });
    
    // بستن مودال با کلیک خارج از آن
    playlistModal.addEventListener('click', function(e) {
      if (e.target === playlistModal) {
        playlistModal.style.display = 'none';
      }
    });
    
    // افزودن به پلی‌لیست
    playlistItems.forEach(item => {
      item.addEventListener('click', function() {
        const playlistId = this.getAttribute('data-id');
        
        fetch(<?= json_encode(am_lang_url('add_to_playlist.php')) ?>, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `content_id=<?= $id ?>&playlist_id=${playlistId}`
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert(<?= json_encode(am_t('added_to_playlist_success')) ?>);
            playlistModal.style.display = 'none';
          } else {
            alert(<?= json_encode(am_t('add_to_playlist_error')) ?> + ': ' + data.message);
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert(<?= json_encode(am_t('server_error')) ?>);
        });
      });
    });
    
    // ایجاد پلی‌لیست جدید
    createPlaylistBtn.addEventListener('click', function() {
      const playlistName = prompt(<?= json_encode(am_t('enter_playlist_name')) ?>);
      if (playlistName && playlistName.trim() !== '') {
        fetch(<?= json_encode(am_lang_url('create_playlist.php')) ?>, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `name=${encodeURIComponent(playlistName.trim())}&content_id=<?= $id ?>`
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert(<?= json_encode(am_t('playlist_created_success')) ?>);
            playlistModal.style.display = 'none';
            // رفرش صفحه برای نمایش پلی‌لیست جدید
            location.reload();
          } else {
            alert(<?= json_encode(am_t('create_playlist_error')) ?> + ': ' + data.message);
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert(<?= json_encode(am_t('server_error')) ?>);
        });
      }
    });
    <?php endif; ?>
    
    // نمایش پیام VIP برای کاربران غیر VIP
    function showVipMessage() {
      alert(<?= json_encode(am_t('vip_feature_prompt')) ?>);
      window.location.href = <?= json_encode(am_lang_url('vip.php')) ?>;
    }
  </script>
</body>
</html>