<?php
session_start();
require_once __DIR__ . '/includes/admin_auth.php';

// اگر قبلاً وارد شده، به داشبورد برو
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin.php");
    exit;
}

$error = "";
$attemptsLeft = am_admin_login_attempts_left();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($attemptsLeft <= 0) {
        $error = "تلاش بیش از حد. لطفاً ۱۵ دقیقه دیگر دوباره امتحان کنید.";
    } else {
        $user = $_POST['username'] ?? '';
        $pass = $_POST['password'] ?? '';

        if (am_admin_credentials_valid($user, $pass)) {
            // ورود موفق — پاک‌سازی تلاش‌های ناموفق
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $user;
            unset($_SESSION['admin_login_attempts']);
            session_regenerate_id(true);
            header("Location: admin.php");
            exit;
        } else {
            am_admin_record_attempt();
            $attemptsLeft = am_admin_login_attempts_left();
            $error = "نام کاربری یا رمز عبور اشتباه است.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ورود به پنل مدیریت | انیمه موزیک</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn.min.css" rel="stylesheet" />
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    :root {
      --primary: #00aa6f;
      --primary-dark: #007d52;
      --dark: #212529;
      --gray: #6c757d;
      --danger: #dc3545;
      --border-radius: 12px;
      --box-shadow: 0 8px 30px rgba(0,0,0,0.12);
    }

    body {
      font-family: 'Vazirmatn', 'Segoe UI', Tahoma, sans-serif;
      background: linear-gradient(135deg, #00aa6f 0%, #007d52 50%, #004d33 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      direction: rtl;
    }

    .login-card {
      background: #fff;
      border-radius: 18px;
      box-shadow: var(--box-shadow);
      width: 100%;
      max-width: 400px;
      padding: 40px 30px;
    }

    .login-logo {
      text-align: center;
      margin-bottom: 28px;
    }

    .login-logo i {
      font-size: 46px;
      color: var(--primary);
      margin-bottom: 10px;
    }

    .login-logo h1 {
      font-size: 22px;
      color: var(--dark);
    }

    .login-logo p {
      color: var(--gray);
      font-size: 14px;
      margin-top: 6px;
    }

    .form-group {
      margin-bottom: 18px;
    }

    .form-group label {
      display: block;
      margin-bottom: 7px;
      font-weight: 600;
      font-size: 14px;
      color: var(--dark);
    }

    .input-wrap {
      position: relative;
    }

    .input-wrap i {
      position: absolute;
      right: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: #adb5bd;
    }

    .form-control {
      width: 100%;
      padding: 12px 42px 12px 14px;
      border: 1px solid #ced4da;
      border-radius: var(--border-radius);
      font-family: inherit;
      font-size: 15px;
      transition: all .2s ease;
    }

    .form-control:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(0, 170, 111, .2);
    }

    .btn {
      width: 100%;
      padding: 13px;
      background: var(--primary);
      color: #fff;
      border: none;
      border-radius: var(--border-radius);
      font-family: inherit;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: background .2s ease, transform .2s ease;
    }

    .btn:hover {
      background: var(--primary-dark);
      transform: translateY(-2px);
    }

    .alert {
      padding: 12px 14px;
      border-radius: var(--border-radius);
      font-size: 14px;
      margin-bottom: 18px;
      background: rgba(220, 53, 69, 0.08);
      color: var(--danger);
      border: 1px solid rgba(220, 53, 69, 0.2);
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .hint {
      text-align: center;
      color: var(--gray);
      font-size: 12.5px;
      margin-top: 20px;
    }

    .back-link {
      display: block;
      text-align: center;
      color: var(--gray);
      font-size: 13px;
      margin-top: 14px;
      text-decoration: none;
    }

    .back-link:hover {
      color: var(--primary);
    }
  </style>
</head>
<body>
  <div class="login-card">
    <div class="login-logo">
      <i class="fas fa-music"></i>
      <h1>پنل مدیریت انیمه موزیک</h1>
      <p>برای ادامه وارد حساب مدیریت شوید</p>
    </div>

    <?php if ($error): ?>
      <div class="alert">
        <i class="fas fa-exclamation-circle"></i>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>

    <form method="post" action="" id="loginForm">
      <div class="form-group">
        <label for="username">نام کاربری</label>
        <div class="input-wrap">
          <i class="fas fa-user"></i>
          <input type="text" id="username" name="username" class="form-control" required autocomplete="username">
        </div>
      </div>

      <div class="form-group">
        <label for="password">رمز عبور</label>
        <div class="input-wrap">
          <i class="fas fa-lock"></i>
          <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
        </div>
      </div>

      <button type="submit" class="btn">
        <i class="fas fa-sign-in-alt"></i>
        ورود به پنل
      </button>
    </form>

    <div class="hint">
      توجه: برای امنیت، اطلاعات ورود را در پوشه
      <code>mhmdrdapaydar/includes/config.php</code>
      تغییر دهید.
    </div>

    <a href="../index.php" class="back-link">
      <i class="fas fa-arrow-right"></i> بازگشت به سایت
    </a>
  </div>
</body>
</html>
