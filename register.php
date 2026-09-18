<?php
require_once __DIR__ . '/includes/headless.php';

// بررسی وضعیت تم کاربر
$isDarkMode = am_theme();

// اتصال به دیتابیس کاربران
$db_users = am_users_db();

// سقف ثبت‌نام اختیاری برای جلوگیری از ثبت انبوه ربات‌ها (قابل حذف توسط صاحب سایت)
define('AM_MAX_USERS', 10000);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $email = trim($_POST['email']);

    // اعتبارسنجی
    if (empty($firstName) || empty($lastName) || empty($username) || empty($password)) {
        $error = 'لطفا تمام فیلدهای اجباری را پر کنید';
    } elseif ($password !== $confirmPassword) {
        $error = 'رمز عبور و تکرار آن مطابقت ندارند';
    } elseif (strlen($password) < 6) {
        $error = 'رمز عبور باید حداقل 6 کاراکتر باشد';
    } else {
        // بررسی تکراری نبودن نام کاربری
        $stmt = $db_users->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->fetch()) {
            $error = 'نام کاربری قبلا انتخاب شده است';
        } else {
            // کنترل حد نصاب کاربران
            $userCount = (int)$db_users->query("SELECT COUNT(*) FROM users")->fetchColumn();
            if ($userCount >= AM_MAX_USERS) {
                $error = 'ظرفیت ثبت‌نام تکمیل شده است. لطفاً با پشتیبانی تماس بگیرید.';
            } else {
                // ثبت کاربر جدید
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db_users->prepare("
                    INSERT INTO users (first_name, last_name, username, password, email, subscription_status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'free', datetime('now'))
                ");
                
                if ($stmt->execute([$firstName, $lastName, $username, $hashedPassword, $email])) {
                    // هدایت به صفحه login.php پس از ثبت‌نام موفق
                    header("Location: login.php");
                    exit();
                } else {
                    $error = 'خطا در ثبت‌نام. لطفا دوباره تلاش کنید.';
                }
            }
        }
    }
}

// اگر کاربر قبلا لاگین کرده، به صفحه اصلی ریدایرکت شود
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="<?= $isDarkMode ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ثبت‌نام - رسانه من</title>
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
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: background-color var(--transition-speed), color var(--transition-speed);
            padding: 20px;
            position: relative;
        }
        
        .header {
            position: absolute;
            top: 20px;
            left: 20px;
            right: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
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
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .theme-toggle:hover {
            background-color: rgba(0, 0, 0, 0.1);
        }
        
        .auth-container {
            width: 100%;
            max-width: 420px;
            margin: 60px 0 40px;
        }
        
        .auth-card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 35px 30px;
            box-shadow: 0 10px 30px var(--shadow-color);
            border: 1px solid var(--border-color);
            transition: all var(--transition-speed);
        }
        
        .auth-card:hover {
            box-shadow: 0 15px 40px var(--shadow-color);
            transform: translateY(-5px);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo img {
            height: 70px;
            transition: transform var(--transition-speed);
        }
        
        .logo img:hover {
            transform: scale(1.05);
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .auth-header h1 {
            color: var(--primary-color);
            margin-bottom: 12px;
            font-size: 26px;
            font-weight: 700;
        }
        
        .auth-header p {
            color: var(--text-secondary);
            font-size: 15px;
            line-height: 1.6;
        }
        
        .form-group {
            margin-bottom: 22px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: var(--text-primary);
            font-weight: 500;
            font-size: 15px;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            background-color: var(--bg-card);
            color: var(--text-primary);
            font-family: 'Vazirmatn', sans-serif;
            font-size: 16px;
            transition: all var(--transition-speed);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 255, 106, 0.2);
            transform: translateY(-2px);
        }
        
        .btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: #000;
            border: none;
            border-radius: var(--border-radius);
            font-family: 'Vazirmatn', sans-serif;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all var(--transition-speed);
            margin-top: 10px;
            box-shadow: 0 4px 12px rgba(0, 255, 106, 0.3);
        }
        
        .btn:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 255, 106, 0.4);
        }
        
        .btn:active {
            transform: translateY(-1px);
        }
        
        .auth-footer {
            text-align: center;
            margin-top: 25px;
            color: var(--text-secondary);
            font-size: 15px;
        }
        
        .auth-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: color var(--transition-speed);
            position: relative;
        }
        
        .auth-footer a:hover {
            color: var(--secondary-color);
        }
        
        .auth-footer a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            transform: scaleX(0);
            transform-origin: right;
            transition: transform var(--transition-speed);
        }
        
        .auth-footer a:hover::after {
            transform: scaleX(1);
            transform-origin: left;
        }
        
        .alert {
            padding: 14px 18px;
            border-radius: var(--border-radius);
            margin-bottom: 22px;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeIn 0.5s ease;
        }
        
        .alert-error {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }
        
        .password-toggle {
            position: absolute;
            left: 15px;
            top: 45px;
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 18px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 480px) {
            .auth-container {
                max-width: 100%;
                padding: 0 15px;
            }
            
            .auth-card {
                padding: 25px 20px;
            }
            
            .auth-header h1 {
                font-size: 22px;
            }
            
            .header {
                left: 15px;
                right: 15px;
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
        <div></div> <!-- عنصر خالی برای تراز کردن دکمه در سمت راست -->
        <button class="theme-toggle" id="themeToggle">
            <i class="bi <?= $isDarkMode ? 'bi-sun' : 'bi-moon' ?>"></i>
        </button>
    </div>
    
    <div class="auth-container">
        <div class="logo">
            <img src="image.png" alt="لوگو رسانه من">
        </div>
        
        <div class="auth-card">
            <div class="auth-header">
                <h1>ایجاد حساب کاربری</h1>
                <p>لطفا اطلاعات خود را وارد کنید</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="bi bi-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> <?= $success ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="register.php">
                <div class="form-group">
                    <label for="first_name">نام</label>
                    <input type="text" id="first_name" name="first_name" class="form-control" required 
                           value="<?= isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : '' ?>"
                           placeholder="نام خود را وارد کنید">
                </div>
                
                <div class="form-group">
                    <label for="last_name">نام خانوادگی</label>
                    <input type="text" id="last_name" name="last_name" class="form-control" required 
                           value="<?= isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : '' ?>"
                           placeholder="نام خانوادگی خود را وارد کنید">
                </div>
                
                <div class="form-group">
                    <label for="username">نام کاربری</label>
                    <input type="text" id="username" name="username" class="form-control" required 
                           value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                           placeholder="نام کاربری خود را وارد کنید">
                </div>
                
                <div class="form-group">
                    <label for="email">ایمیل (اختیاری)</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                           placeholder="ایمیل خود را وارد کنید">
                </div>
                
                <div class="form-group">
                    <label for="password">رمز عبور</label>
                    <input type="password" id="password" name="password" class="form-control" required
                           placeholder="رمز عبور خود را وارد کنید">
                    <button type="button" class="password-toggle" data-target="password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">تکرار رمز عبور</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required
                           placeholder="تکرار رمز عبور را وارد کنید">
                    <button type="button" class="password-toggle" data-target="confirm_password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                
                <button type="submit" class="btn">
                    <i class="bi bi-person-plus"></i> ثبت‌نام
                </button>
            </form>
            
            <div class="auth-footer">
                قبلا حساب دارید؟ <a href="login.php">وارد شوید</a>
            </div>
        </div>
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
        
        // نمایش/مخفی کردن رمز عبور
        document.querySelectorAll('.password-toggle').forEach(toggle => {
            toggle.addEventListener('click', () => {
                const targetId = toggle.getAttribute('data-target');
                const passwordInput = document.getElementById(targetId);
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                
                passwordInput.setAttribute('type', type);
                
                // تغییر آیکون
                toggle.innerHTML = type === 'password' ? 
                    '<i class="bi bi-eye"></i>' : 
                    '<i class="bi bi-eye-slash"></i>';
            });
        });
    </script>
</body>
</html>