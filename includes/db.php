<?php
/**
 * انیمه موزیک — اتصال مرکزی به پایگاه‌های داده
 * عملکرد: فقط یک بار PDO ساخته می‌شود و در کل صفحه استفاده می‌گردد (سرعت بهتر)
 */

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

/**
 * اتصال (یا استفاده از اتصال قبلی) به پایگاه داده کاربران
 */
function am_db_users() {
    static $db = null;
    if ($db === null) {
        $path = defined('AM_DB_USERS') ? AM_DB_USERS : __DIR__ . '/../db/users.db';
        $db = new PDO('sqlite:' . $path);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        // فعال کردن WAL برای خواندن همزمان سریع‌تر
        $db->exec('PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 5000;');
    }
    return $db;
}

/**
 * اتصال (یا استفاده از اتصال قبلی) به پایگاه داده محتوا
 */
function am_db_content() {
    static $db = null;
    if ($db === null) {
        $path = defined('AM_DB_CONTENT') ? AM_DB_CONTENT : __DIR__ . '/../db/content.db';
        $db = new PDO('sqlite:' . $path);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec('PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 5000;');
    }
    return $db;
}
