<?php
require_once __DIR__ . '/includes/headless.php';

// اگر کاربر لاگین نکرده باشد، به صفحه ورود ریدایرکت شود
am_require_login();

// بررسی وضعیت تم کاربر
$isDarkMode = am_theme();

// دریافت کاربر جاری + اصلاح خودکار VIP منقضی
$user = am_current_user();

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
    <title><?= am_te('free_vip_request') ?> | <?= am_te('site_name') ?></title>
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
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .welcome-section {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: 0 4px 20px var(--shadow-color);
            border: 1px solid var(--border-color);
            margin-bottom: 30px;
            text-align: center;
            transition: all var(--transition-speed);
        }
        
        .welcome-section:hover {
            box-shadow: 0 8px 25px var(--shadow-color);
        }
        
        .welcome-icon {
            font-size: 64px;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        
        .welcome-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 15px;
            color: var(--primary-color);
        }
        
        .welcome-text {
            font-size: 16px;
            line-height: 1.8;
            color: var(--text-primary);
            margin-bottom: 20px;
        }
        
        .requirements-section {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: 0 4px 20px var(--shadow-color);
            border: 1px solid var(--border-color);
            margin-bottom: 30px;
            transition: all var(--transition-speed);
        }
        
        .requirements-section:hover {
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
        
        .requirements-list {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }
        
        .requirements-list li {
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            display: flex;
            align-items: center;
            font-size: 15px;
        }
        
        .requirements-list li:last-child {
            border-bottom: none;
        }
        
        .requirements-list li i {
            color: var(--primary-color);
            margin-left: 12px;
            font-size: 20px;
        }
        
        .contact-methods {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: 0 4px 20px var(--shadow-color);
            border: 1px solid var(--border-color);
            transition: all var(--transition-speed);
        }
        
        .contact-methods:hover {
            box-shadow: 0 8px 25px var(--shadow-color);
        }
        
        .contact-options {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin-top: 25px;
        }
        
        @media (min-width: 768px) {
            .contact-options {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        .contact-option {
            background: linear-gradient(135deg, var(--bg-card), var(--bg-primary));
            border-radius: var(--border-radius);
            padding: 25px;
            text-align: center;
            border: 2px solid var(--border-color);
            transition: all var(--transition-speed);
        }
        
        .contact-option:hover {
            transform: translateY(-5px);
            border-color: var(--primary-color);
            box-shadow: 0 10px 25px rgba(0, 255, 106, 0.15);
        }
        
        .contact-icon {
            font-size: 48px;
            margin-bottom: 15px;
            color: var(--primary-color);
        }
        
        .contact-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
            color: var(--text-primary);
        }
        
        .contact-description {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .contact-link {
            display: inline-block;
            background: var(--primary-color);
            color: #000;
            padding: 12px 25px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: bold;
            margin: 10px 5px;
            transition: all var(--transition-speed);
            width: 90%;
        }
        
        .contact-link:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 255, 106, 0.3);
        }
        
        .info-box {
            margin-top: 30px;
            padding: 20px;
            background: rgba(255, 193, 7, 0.1);
            border-radius: var(--border-radius);
            border-right: 3px solid #ffc107;
            text-align: center;
        }
        
        .info-title {
            font-weight: bold;
            margin-bottom: 10px;
            color: #ffc107;
            font-size: 18px;
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
            .contact-options {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                padding: 15px;
            }
            
            .welcome-icon {
                font-size: 48px;
            }
            
            .welcome-title {
                font-size: 24px;
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
        <img src="/image.png" alt="Logo">
        <button class="theme-toggle" id="themeToggle">
            <i class="bi <?= $isDarkMode ? 'bi-sun' : 'bi-moon' ?>"></i>
        </button>
    </div>
    
    <div class="container">
        <div class="welcome-section">
            <div class="welcome-icon">
                <i class="bi bi-heart-fill"></i>
            </div>
            <h1 class="welcome-title"><?= am_te('free_vip_request') ?></h1>
            <p class="welcome-text">
                <?= am_te('free_vip_welcome') ?>
            </p>
            <p class="welcome-text">
                <?= am_te('free_vip_belief') ?>
            </p>
        </div>
        
        <div class="requirements-section">
            <h2 class="section-title"><?= am_te('free_vip_requirements') ?></h2>
            <p style="margin-bottom: 20px; color: var(--text-secondary);">
                <?= am_te('free_vip_req_intro') ?>
            </p>
            
            <ul class="requirements-list">
                <li>
                    <i class="bi bi-check-circle"></i>
                    <span><?= am_te('free_vip_req_1') ?></span>
                </li>
                <li>
                    <i class="bi bi-check-circle"></i>
                    <span><?= am_te('free_vip_req_2') ?></span>
                </li>
                <li>
                    <i class="bi bi-check-circle"></i>
                    <span><?= am_te('free_vip_req_3') ?></span>
                </li>
                <li>
                    <i class="bi bi-check-circle"></i>
                    <span><?= am_te('free_vip_req_4') ?></span>
                </li>
                <li>
                    <i class="bi bi-check-circle"></i>
                    <span><?= am_te('free_vip_req_5') ?></span>
                </li>
            </ul>
            
            <div class="info-box">
                <div class="info-title"><?= am_te('important_notice') ?></div>
                <p><p><?= am_te('free_vip_notice') ?></p></p>
            </div>
        </div>
        
        <div class="contact-methods">
            <h2 class="section-title"><?= am_te('send_request') ?></h2>
            <p style="text-align: center; margin-bottom: 20px; color: var(--text-secondary);">
                <?= am_te('send_request_intro') ?>
            </p>
            
            <div class="contact-options">
                <div class="contact-option">
                    <div class="contact-icon">
                        <i class="bi bi-telegram"></i>
                    </div>
                    <div class="contact-name"><?= am_te('send_via_telegram') ?></div>
                    <div class="contact-description">
                        <?= am_te('send_via_telegram_desc') ?>
                    </div>
                    <a href="http://t.me/I_MHP_I" target="_blank" class="contact-link">
                        <i class="bi bi-telegram"></i> <?= am_te('admin_telegram') ?>
                    </a>
                    <div style="margin-top: 15px; font-size: 13px; color: var(--text-secondary);">
                        <?= am_te('if_reported_msg_bot') ?>
                    </div>
                    <a href="http://t.me/Anime_music_irn_bot" target="_blank" class="contact-link">
                        <i class="bi bi-robot"></i> <?= am_te('support_bot') ?>
                    </a>
                </div>
                
                <div class="contact-option">
                    <div class="contact-icon">
                        <i class="bi bi-chat-dots"></i>
                    </div>
                    <div class="contact-name"><?= am_te('send_via_rubika') ?></div>
                    <div class="contact-description">
                        <?= am_te('send_via_rubika_desc') ?>
                    </div>
                    <a href="https://rubika.ir/I_MHP_I" target="_blank" class="contact-link">
                        <i class="bi bi-chat-dots"></i> I_MHP_I@
                    </a>
                    <div style="margin-top: 15px; font-size: 13px; color: var(--text-secondary);">
                        <?= am_te('explain_after_entry') ?>
                    </div>
                </div>
            </div>
            
            <div class="info-box" style="margin-top: 25px;">
                <div class="info-title"><?= am_te('guidance') ?></div>
                <p><?= am_te('guidance_intro') ?></p>
                <ul style="text-align: right; margin: 10px 0; padding-right: 20px;">
                    <li><?= am_te('guidance_1') ?></li>
                    <li><?= am_te('guidance_2') ?></li>
                    <li><?= am_te('guidance_3') ?></li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="navbar">
        <a href="<?= am_lang_url('index.php') ?>"><i class="bi bi-house"></i> <?= am_te('home') ?></a>
        <a href="<?= am_lang_url('categories.php') ?>"><i class="bi bi-grid-1x2-fill"></i> <?= am_te('categories') ?></a>
        <a href="<?= am_lang_url('vip.php') ?>"><i class="bi bi-star"></i> VIP</a>
        <a href="<?= am_lang_url('profile.php') ?>"><i class="bi bi-person"></i> <?= am_te('profile') ?></a>
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