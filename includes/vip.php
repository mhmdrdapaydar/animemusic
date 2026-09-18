<?php
/**
 * انیمه موزیک — موتور مرکزی وضعیت VIP
 *
 * *** اصلاح باگ اصلی ***
 * پیش‌تر وضعیت VIP فقط در صفحه‌هایی مثل index/profile/login بررسی و تمدید
 * می‌شد؛ نتیجه این بود که اگر اشتراک کاربر تمام می‌شد و کاربر وارد صفحه‌ای مثل
 * content.php یا playlist.php می‌شد، وضعیت vip در دیتابیس می‌ماند و کاربر
 * همچنان «ویژه» دیده می‌شد.
 *
 * حالا تابع am_refresh_vip() در هر صفحه‌ای که به وضعیت کاربر نیاز دارد صدا زده
 * می‌شود و اگر تاریخ پایان گذشته باشد، SUBSCRIPTION_STATUS را به free برمی‌گرداند.
 */

if (file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
}

/**
 * مقایسه‌ی صحیح تاریخ انقضای اشتراک (بر اساس «تاریخ» نه ساعت روز).
 * مقادیر: 1 = هنوز معتبر، 0 = امروز تمام می‌شود (تا پایان امروز معتبر)، -1 = منقضی شده
 */
function am_subscription_days_left($endDate) {
    if (empty($endDate)) {
        return -1;
    }
    $end = new DateTime($endDate);
    $today = new DateTime('today'); // ساعت ۰۰:۰۰ امروز
    if ($end < $today) {
        return -1; // منقضی
    }
    $diff = (int)$today->diff($end)->format('%r%a');
    return $diff < 0 ? -1 : $diff;
}

/**
 * اعلام اینکه کاربر باید از دید سیستم VIP حساب شود یا نه.
 * vip بدون تاریخ پایان = ویژهٔ نامحدود (توسط ادمین)
 * vip با تاریخ پایان = فقط اگر امروز <= تاریخ پایان
 */
function am_is_vip(array $user, $autoRefresh = true) {
    $status = $user['subscription_status'] ?? 'free';

    if ($status !== 'vip') {
        return false;
    }

    $end = $user['subscription_end_date'] ?? null;

    // VIP نامحدود (ادمین تاریخ نگذاشته)
    if (empty($end)) {
        return true;
    }

    $left = am_subscription_days_left($end);
    if ($left >= 0) {
        return true;
    }

    // منقضی شده — اگر اجازه داشته باشیم، وضعیت را در دیتابیس اصلاح می‌کنیم
    if ($autoRefresh) {
        am_demote_expired_vip((int)($user['id'] ?? 0));
    }
    return false;
}

/**
 * کاربر منقضی‌شده را از VIP به free برمی‌گرداند و session را به‌روز می‌کند.
 */
function am_demote_expired_vip($userId) {
    if (!$userId) {
        return;
    }
    try {
        $db = am_db_users();
        $stmt = $db->prepare("UPDATE users SET subscription_status = 'free' WHERE id = ? AND subscription_status = 'vip'");
        $stmt->execute([$userId]);
    } catch (PDOException $e) {
        error_log('[VIP] demote error: ' . $e->getMessage());
    }
    // به‌روزرسانی session برای ماندگاری فوری تغییر
    if (isset($_SESSION['subscription_status'])) {
        $_SESSION['subscription_status'] = 'free';
    }
}

/**
 * دریافت کاربر جاری (لاگین‌کرده) به همراه اعمال منطق انقضا.
 * اگر کاربر vip منقضی داشته باشد، همین‌جا به free تبدیل می‌شود.
 * خروجی: آرایه کاربر یا null
 */
function am_current_user() {
    static $cached = null;
    static $loaded = false;
    if ($loaded) {
        return $cached;
    }
    $loaded = true;

    if (!isset($_SESSION['user_id'])) {
        return $cached;
    }
    try {
        $db = am_db_users();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([(int)$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return $cached;
        }
        // اصلاح خودکار وضعیت در صورت انقضا
        $user['subscription_status'] = am_is_vip($user) ? 'vip' : 'free';
        // هماهنگ‌سازی session
        $_SESSION['subscription_status'] = $user['subscription_status'];
        $cached = $user;
    } catch (PDOException $e) {
        error_log('[VIP] current user error: ' . $e->getMessage());
    }
    return $cached;
}
