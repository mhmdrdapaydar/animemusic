<?php
session_start();
// پاک کردن تمامی متغیرهای session
$_SESSION = array();

// اگر می‌خواهید session را کاملاً نابود کنید، cookie session را نیز پاک کنید
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// در نهایت session را نابود کنید
session_destroy();

// هدایت به صفحه اصلی
header("Location: index.php");
exit;
?>