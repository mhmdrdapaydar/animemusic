<?php
require_once __DIR__ . '/includes/headless.php';

// اگر کاربر لاگین نکرده باشد، به صفحه ورود ریدایرکت شود
am_require_login();

// بررسی وضعیت تم کاربر
$isDarkMode = am_theme();

// دریافت کاربر جاری + اصلاح خودکار VIP منقضی (رفع باگ)
$user = am_current_user();

$message = '';
$message_type = '';

// تابع تبدیل تاریخ میلادی به شمسی (الگوریتم استاندارد)
function jdate($format, $timestamp = '') {
    return am_jdate($format, $timestamp);
}
?>

<!DOCTYPE html>
<html lang="<?= am_e(am_lang()) ?>" dir="<?= am_e(am_lang_dir()) ?>" data-theme="<?= $isDarkMode ? 'dark' : 'light' ?>">
<head>
  <?= am_lang_base_tag() ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= am_te('buy_vip_title') ?> | <?= am_te('site_name') ?></title>
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
        
        .vip-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }
        
        @media (min-width: 768px) {
            .vip-container {
                grid-template-columns: 2fr 1fr;
            }
        }
        
        .plans-section {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: 0 4px 20px var(--shadow-color);
            border: 1px solid var(--border-color);
            transition: all var(--transition-speed);
        }
        
        .plans-section:hover {
            box-shadow: 0 8px 25px var(--shadow-color);
        }
        
        .user-status {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: 0 4px 20px var(--shadow-color);
            border: 1px solid var(--border-color);
            height: fit-content;
            transition: all var(--transition-speed);
        }
        
        .user-status:hover {
            box-shadow: 0 8px 25px var(--shadow-color);
        }
        
        .section-title {
            color: var(--primary-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
            font-size: 22px;
            font-weight: 700;
        }
        
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 25px;
        }
        
        .plan-card {
            background: linear-gradient(135deg, var(--bg-card), var(--bg-primary));
            border-radius: var(--border-radius);
            padding: 25px;
            text-align: center;
            border: 2px solid var(--border-color);
            transition: all var(--transition-speed);
            position: relative;
            overflow: hidden;
        }
        
        .plan-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary-color);
            box-shadow: 0 10px 25px rgba(0, 255, 106, 0.15);
        }
        
        .plan-card.popular {
            border-color: var(--accent-color);
            transform: scale(1.05);
        }
        
        .plan-card.popular:hover {
            transform: scale(1.05) translateY(-5px);
        }
        
        .plan-card.popular::before {
            content: 'پیشنهاد ویژه';
            position: absolute;
            top: 15px;
            left: -25px;
            background: var(--accent-color);
            color: white;
            padding: 5px 30px;
            font-size: 12px;
            font-weight: bold;
            transform: rotate(-45deg);
        }
        
        .plan-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 15px;
            color: var(--primary-color);
        }
        
        .plan-price {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
            color: var(--text-primary);
        }
        
        .plan-period {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 20px;
        }
        
        .plan-features {
            list-style: none;
            padding: 0;
            margin: 20px 0;
            text-align: right;
        }
        
        .plan-features li {
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            display: flex;
            align-items: center;
        }
        
        .plan-features li:last-child {
            border-bottom: none;
        }
        
        .plan-features li i {
            color: var(--primary-color);
            margin-left: 8px;
            font-size: 18px;
        }
        
        .plan-button {
            width: 100%;
            padding: 12px;
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .plan-button:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 255, 106, 0.4);
        }
        
        .user-status-content {
            text-align: center;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: bold;
            margin-bottom: 20px;
            transition: all var(--transition-speed);
        }
        
        .status-free {
            background: rgba(108, 117, 125, 0.2);
            color: #6c757d;
        }
        
        .status-vip {
            background: linear-gradient(135deg, #ffd700, #ff9800);
            color: #000;
        }
        
        .status-info {
            margin-top: 20px;
            padding: 15px;
            background: rgba(0, 170, 111, 0.1);
            border-radius: var(--border-radius);
            color: var(--text-primary);
            border-right: 3px solid var(--primary-color);
        }
        
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeIn 0.5s ease;
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }
        
        .alert-error {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }
        
        .benefits-list {
            margin-top: 30px;
        }
        
        .benefit-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 15px;
            background: rgba(0, 255, 106, 0.05);
            border-radius: var(--border-radius);
            border-right: 4px solid var(--primary-color);
            transition: all var(--transition-speed);
        }
        
        .benefit-item:hover {
            transform: translateX(-5px);
            background: rgba(0, 255, 106, 0.1);
        }
        
        .benefit-icon {
            font-size: 24px;
            color: var(--primary-color);
            margin-left: 15px;
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
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 768px) {
            .vip-container {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                padding: 15px;
            }
            
            .plans-grid {
                grid-template-columns: 1fr;
            }
            
            .plan-card.popular {
                transform: scale(1);
            }
            
            .plan-card.popular:hover {
                transform: translateY(-5px);
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
        <img src="/image.png" alt="Logo">
        <button class="theme-toggle" id="themeToggle">
            <i class="bi <?= $isDarkMode ? 'bi-sun' : 'bi-moon' ?>"></i>
        </button>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>">
                <i class="bi <?= $message_type == 'success' ? 'bi-check-circle' : 'bi-exclamation-circle' ?>"></i> 
                <?= $message ?>
            </div>
        <?php endif; ?>
        
        <div class="vip-container">
            <div class="plans-section">
                <h2 class="section-title"><?= am_te('vip_plans') ?></h2>
                <p><?= am_te('vip_plans_sub') ?></p>
                
                <div class="plans-grid">
                    <div class="plan-card">
                        <div class="plan-name"><?= am_te('monthly_plan') ?></div>
                        <div class="plan-price">۲۹,۰۰۰ تومان</div>
                        <div class="plan-period"><?= am_te('one_month_sub') ?></div>
                        
                        <ul class="plan-features">
                            <li><i class="bi bi-check"></i> <?= am_te('access_all_music') ?></li>
                            <li><i class="bi bi-check"></i> <?= am_te('download_music_feat') ?></li>
                            <li><i class="bi bi-check"></i> <?= am_te('view_lyrics_feat') ?></li>
                            <li><i class="bi bi-check"></i> <?= am_te('watch_anime_videos') ?></li>
                            <li><i class="bi bi-check"></i> <?= am_te('no_ads') ?></li>
                            <li><i class="bi bi-check"></i> <?= am_te('unlimited_playlists') ?></li>
                            <li><i class="bi bi-check"></i> <?= am_te('add_to_favorites_feat') ?></li>
                            <li><i class="bi bi-check"></i> <?= am_te('pro_player_playlist') ?></li>
                            <li><i class="bi bi-check"></i> <?= am_te('public_private_playlists') ?></li>
                        </ul>
                        
                        <a href="<?= am_lang_url('getvip.php') ?>?plan=monthly" class="plan-button">
                            <i class="bi bi-cart"></i> <?= am_te('buy_plan') ?>
                        </a>
                    </div>
                    
                    <div class="plan-card popular">
                        <div class="plan-name"><?= am_te('quarterly_plan') ?></div>
                        <div class="plan-price">۷۹,۰۰۰ تومان</div>
                        <div class="plan-period"><?= am_te('three_month_sub') ?></div>
                        
                        <ul class="plan-features">
                            <li><i class="bi bi-check"></i> <?= am_te('all_monthly_features') ?></li>
                            <li><i class="bi bi-check"></i> <?= am_te('save_15') ?></li>
                            <li><i class="bi bi-check"></i> <?= am_te('priority_support') ?></li>
                        </ul>
                        
                        <a href="<?= am_lang_url('getvip.php') ?>?plan=3months" class="plan-button">
                            <i class="bi bi-cart"></i> <?= am_te('buy_plan') ?>
                        </a>
                    </div>
                    
                    <div class="plan-card">
                        <div class="plan-name"><?= am_te('yearly_plan') ?></div>
                        <div class="plan-price">۲۵۹,۰۰۰ تومان</div>
                        <div class="plan-period"><?= am_te('one_year_sub') ?></div>
                        
                        <ul class="plan-features">
                            <li><i class="bi bi-check"></i> <?= am_te('all_quarterly_features') ?></li>
                            <li><i class="bi bi-check"></i> <?= am_te('save_30') ?></li>
                            <li><i class="bi bi-check"></i> <?= am_te('phone_support') ?></li>
                        </ul>
                        
                        <a href="<?= am_lang_url('getvip.php') ?>?plan=yearly" class="plan-button">
                            <i class="bi bi-cart"></i> <?= am_te('buy_plan') ?>
                        </a>
                    </div>
                </div>
                
                <div class="benefits-list">
                    <h3 class="section-title"><?= am_te('vip_benefits') ?></h3>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="bi bi-download"></i>
                        </div>
                        <div>
                            <h4><?= am_te('unlimited_downloads') ?></h4>
                            <p><?= am_te('unlimited_downloads_desc') ?></p>
                        </div>
                    </div>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="bi bi-music-note-beamed"></i>
                        </div>
                        <div>
                            <h4><?= am_te('lyrics_trans_title') ?></h4>
                            <p><?= am_te('lyrics_trans_desc') ?></p>
                        </div>
                    </div>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="bi bi-play-btn"></i>
                        </div>
                        <div>
                            <h4><?= am_te('anime_videos_title') ?></h4>
                            <p><?= am_te('anime_videos_desc') ?></p>
                        </div>
                    </div>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="bi-broadcast"></i>
                        </div>
                        <div>
                            <h4><?= am_te('dedicated_playlist_title') ?></h4>
                            <p><?= am_te('dedicated_playlist_desc') ?></p>
                        </div>
                    </div>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="bi-suit-heart"></i>
                        </div>
                        <div>
                            <h4><?= am_te('favorites_title') ?></h4>
                            <p><?= am_te('favorites_desc') ?></p>
                        </div>
                    </div>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="bi bi-x-circle"></i>
                        </div>
                        <div>
                            <h4><?= am_te('ad_free_title') ?></h4>
                            <p><?= am_te('ad_free_desc') ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="user-status">
                <div class="user-status-content">
                    <h3 class="section-title"><?= am_te('subscription_status') ?></h3>
                    
                    <div class="status-badge <?= $user['subscription_status'] === 'vip' ? 'status-vip' : 'status-free' ?>">
                        <?= $user['subscription_status'] === 'vip' ? 'VIP' : am_te('regular_label') ?>
                    </div>
                    
                    <?php if ($user['subscription_status'] === 'vip' && $user['subscription_end_date']): ?>
                        <p><?= am_te('vip_active_until') ?> 
                            <strong><?= jdate('Y/m/d', $user['subscription_end_date']) ?></strong>
                        </p>
                        <p class="status-info">
                            <i class="bi bi-info-circle"></i>
                            <?= am_te('vip_active_info') ?>
                        </p>
                    <?php else: ?>
                        <p><?= am_te('regular_sub_now') ?></p>
                        <p class="status-info">
                            <i class="bi bi-info-circle"></i>
                            <?= am_te('upgrade_to_vip_info') ?>
                        </p>
                    <?php endif; ?>
                    
                    <div style="margin-top: 20px;">
                        <a href="<?= am_lang_url('profile.php') ?>" class="plan-button" style="display: block; text-align: center; text-decoration: none;">
                            <i class="bi bi-person"></i> <?= am_te('user_panel_link') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="navbar">
        <a href="<?= am_lang_url('index.php') ?>"><i class="bi bi-house"></i> <?= am_te('home') ?></a>
        <a href="<?= am_lang_url('categories.php') ?>"><i class="bi bi-grid-1x2-fill"></i> <?= am_te('categories') ?></a>
        <a href="<?= am_lang_url('vip.php')  class="active""><i class="bi bi-star"></i> VIP</a>
        <a href="<?= am_lang_url('profile.php') "><i class="bi bi-person"></i> <?= am_te('profile') ?></a>
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