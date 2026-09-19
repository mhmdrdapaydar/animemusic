<?php
require_once __DIR__ . '/includes/headless.php';

// پاک کردن تمامی متغیرهای session
$_SESSION = array();

// اگر کوکی session وجود دارد، آن را پاک کن
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// در نهایت session را نابود کنید
session_destroy();

// هدایت به صفحه اصلیِ زبان جاری (تا کاربر انگلیسی‌زبان به صفحه فارسی نپرد)
header("Location: " . am_lang_url("index.php"));
exit;
