<?php
/**
 * انیمه موزیک — احراز هویت پنل مدیریت (مشترک)
 * از طریق include در همه صفحات ادمین استفاده می‌شود.
 */

require_once __DIR__ . '/config.php';

/**
 * بررسی لاگین بودن ادمین؛ در غیر این صورت به صفحه ورود می‌رود.
 */
function am_admin_guard() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: login.php');
        exit;
    }
}

/**
 * اعتبارسنجی نام کاربری و رمز عبور ادمین.
 */
function am_admin_credentials_valid($username, $password) {
    if (!hash_equals(AM_ADMIN_USER, (string)$username)) {
        return false;
    }
    return password_verify((string)$password, AM_ADMIN_HASH);
}

/**
 * محافظ ضد حدس رمز: شمارش تلاش‌های ناموفق اخیر.
 */
function am_admin_login_attempts_left() {
    $max = 5;
    $win = 900; // ثانیه
    $now = time();
    $attempts = $_SESSION['admin_login_attempts'] ?? [];
    $attempts = array_filter($attempts, function ($t) use ($now, $win) {
        return ($now - (int)$t) < $win;
    });
    return max(0, $max - count($attempts));
}

function am_admin_record_attempt() {
    $_SESSION['admin_login_attempts'][] = time();
}
