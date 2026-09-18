<?php
require_once __DIR__ . '/includes/headless.php';

// اگر کاربر لاگین نکرده باشد، به صفحه ورود ریدایرکت شود
am_require_login();

// بررسی وضعیت تم کاربر
$isDarkMode = am_theme();

// دریافت کاربر جاری + اصلاح خودکار VIP منقضی
$user = am_current_user();

// دریافت اطلاعات پلن از URL
$plan = $_GET['plan'] ?? '';
$plan_name = '';
$plan_price = '';
$plan_period = '';

switch($plan) {
    case 'monthly':
        $plan_name = 'ماهیانه';
        $plan_price = '۲۹,۰۰۰ تومان';
        $plan_period = 'یک ماه اشتراک';
        break;
    case '3months':
        $plan_name = 'سه ماهه';
        $plan_price = '۷۹,۰۰۰ تومان';
        $plan_period = 'سه ماه اشتراک';
        break;
    case 'yearly':
        $plan_name = 'سالانه';
        $plan_price = '۲۵۹,۰۰۰ تومان';
        $plan_period = 'یک سال اشتراک';
        break;
    default:
        header("Location: vip.php");
        exit;
}

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
    <title><?= am_te('complete_purchase') ?> | <?= am_te('site_name') ?></title>
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
        
        .plan-summary {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: 0 4px 20px var(--shadow-color);
            border: 1px solid var(--border-color);
            margin-bottom: 30px;
            text-align: center;
            transition: all var(--transition-speed);
        }
        
        .plan-summary:hover {
            box-shadow: 0 8px 25px var(--shadow-color);
        }
        
        .plan-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
            color: var(--primary-color);
        }
        
        .plan-price {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--text-primary);
        }
        
        .plan-period {
            font-size: 16px;
            color: var(--text-secondary);
            margin-bottom: 20px;
        }
        
        .payment-methods {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: 0 4px 20px var(--shadow-color);
            border: 1px solid var(--border-color);
            transition: all var(--transition-speed);
        }
        
        .payment-methods:hover {
            box-shadow: 0 8px 25px var(--shadow-color);
        }
        
        .section-title {
            color: var(--primary-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
            font-size: 22px;
            font-weight: 700;
            text-align: center;
        }
        
        .payment-options {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin-top: 25px;
        }
        
        @media (min-width: 768px) {
            .payment-options {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        .payment-option {
            background: linear-gradient(135deg, var(--bg-card), var(--bg-primary));
            border-radius: var(--border-radius);
            padding: 25px;
            text-align: center;
            border: 2px solid var(--border-color);
            transition: all var(--transition-speed);
            cursor: pointer;
        }
        
        .payment-option:hover {
            transform: translateY(-5px);
            border-color: var(--primary-color);
            box-shadow: 0 10px 25px rgba(0, 255, 106, 0.15);
        }
        
        .payment-icon {
            font-size: 48px;
            margin-bottom: 15px;
            color: var(--primary-color);
        }
        
        .payment-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
            color: var(--text-primary);
        }
        
        .payment-description {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .payment-instructions {
            margin-top: 30px;
            padding: 20px;
            background: rgba(0, 170, 111, 0.1);
            border-radius: var(--border-radius);
            border-right: 3px solid var(--primary-color);
            display: none;
            animation: fadeIn 0.5s ease;
        }
        
        .instruction-title {
            font-weight: bold;
            margin-bottom: 15px;
            color: var(--primary-color);
            font-size: 18px;
        }
        
        .instruction-text {
            margin-bottom: 15px;
            line-height: 1.8;
        }
        
        .contact-link {
            display: inline-block;
            background: var(--primary-color);
            color: #000;
            padding: 10px 20px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: bold;
            margin: 10px 5px;
            transition: all var(--transition-speed);
        }
        
        .contact-link:hover {
            background: var(--secondary-color);
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
            .payment-options {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                padding: 15px;
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
        <div class="plan-summary">
            <h2>پلن انتخابی شما</h2>
            <div class="plan-name"><?= $plan_name ?></div>
            <div class="plan-price"><?= $plan_price ?></div>
            <div class="plan-period"><?= $plan_period ?></div>
            <p style="margin-top: 15px; color: var(--text-secondary);">لطفاً روش پرداخت خود را انتخاب کنید</p>
        </div>
        
        <div class="payment-methods">
            <h2 class="section-title">روش پرداخت خود را انتخاب کنید و در صورت وجود حتما کد تخفیف را برای ادمین ارسال کنید</h2>
            
            <div class="payment-options">
                <div class="payment-option" onclick="showTelegramInstructions()">
                    <div class="payment-icon">
                        <i class="bi bi-telegram"></i>
                    </div>
                    <div class="payment-name">پرداخت از طریق تلگرام</div>
                    <div class="payment-description">
                        برای پرداخت از طریق تلگرام، لطفاً با ادمین در ارتباط باشید
                    </div>
                </div>
                
                <div class="payment-option" onclick="showRubikaInstructions()">
                    <div class="payment-icon">
                        <i class="bi bi-chat-dots"></i>
                    </div>
                    <div class="payment-name">پرداخت از طریق روبیکا</div>
                    <div class="payment-description">
                        برای پرداخت از طریق روبیکا، لطفاً به آیدی زیر مراجعه کنید
                    </div>
                </div>
            </div>
            
            <div id="telegramInstructions" class="payment-instructions">
                <div class="instruction-title">راهنمای پرداخت از طریق تلگرام</div>
                <div class="instruction-text">
                    لطفاً با فیلترشکن به ادمین پیام دهید:
                </div>
                <a href="http://t.me/I_MHP_I" target="_blank" class="contact-link">
                    <i class="bi bi-telegram"></i> ادمین تلگرام
                </a>
                <div class="instruction-text" style="margin-top: 20px;">
                    اگر ریپورت هستید به ربات زیر پیام دهید:
                </div>
                <a href="http://t.me/Anime_music_irn_bot" target="_blank" class="contact-link">
                    <i class="bi bi-robot"></i> ربات پشتیبانی
                </a>
            </div>
            
            <div id="rubikaInstructions" class="payment-instructions">
                <div class="instruction-title">راهنمای پرداخت از طریق روبیکا</div>
                <div class="instruction-text">
                    لطفاً وارد آیدی زیر در روبیکا شوید:
                </div>
                <a href="https://rubika.ir/I_MHP_I" target="_blank" class="contact-link">
                    <i class="bi bi-chat-dots"></i> I_MHP_I@
                </a>
                <div class="instruction-text" style="margin-top: 20px;">
                    پس از ورود به این آیدی، اطلاعات پلن انتخابی خود را ارسال کرده و منتظر راهنمایی ادمین باشید.
                </div>
            </div>
        </div>
    </div>
    
    <div class="navbar">
        <a href="index.php"><i class="bi bi-house"></i> خانه</a>
        <a href="categories.php"><i class="bi bi-grid-1x2-fill"></i> دسته‌ها</a>
        <a href="vip.php"><i class="bi bi-star"></i> VIP</a>
        <a href="profile.php"><i class="bi bi-person"></i> پروفایل</a>
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
        
        // نمایش دستورات تلگرام
        function showTelegramInstructions() {
            // مخفی کردن تمام دستورات
            document.getElementById('telegramInstructions').style.display = 'none';
            document.getElementById('rubikaInstructions').style.display = 'none';
            
            // نمایش دستورات تلگرام
            document.getElementById('telegramInstructions').style.display = 'block';
        }
        
        // نمایش دستورات روبیکا
        function showRubikaInstructions() {
            // مخفی کردن تمام دستورات
            document.getElementById('telegramInstructions').style.display = 'none';
            document.getElementById('rubikaInstructions').style.display = 'none';
            
            // نمایش دستورات روبیکا
            document.getElementById('rubikaInstructions').style.display = 'block';
        }
    </script>
</body>
</html>