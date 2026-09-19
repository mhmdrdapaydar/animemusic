<?php
/**
 * انیمه موزیک — محافظ ورود برای صفحات کاربری
 * صفحاتی که نیاز به ورود دارند این فایل را include می‌کنند.
 */

if (file_exists(__DIR__ . '/helpers.php')) {
    require_once __DIR__ . '/helpers.php';
}
if (file_exists(__DIR__ . '/i18n.php')) {
    require_once __DIR__ . '/i18n.php';
}
if (file_exists(__DIR__ . '/vip.php')) {
    require_once __DIR__ . '/vip.php';
}

function am_require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . am_lang_url('login.php'));
        exit;
    }
}

function am_require_vip() {
    am_require_login();
    $user = am_current_user();
    if (!$user || $user['subscription_status'] !== 'vip') {
        header('Location: ' . am_lang_url('vip.php'));
        exit;
    }
    return $user;
}
