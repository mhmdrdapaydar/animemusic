<?php
/**
 * انیمه موزیک — موتور مرکزی وضعیت VIP
 *
 * *** رفع باگ اصلی ***
 * پیش‌تر بررسی انقضای اشتراک فقط در چند صفحه انجام می‌شد؛ به همین دلیل اگر
 * اشتراک کاربر تمام می‌شد و او وارد content.php یا playlist.php می‌شد،
 * در دیتابیس هنوز vip بود و امکانات ویژه فعال می‌ماند.
 *
 * حالا هر جایی که «وضعیت کاربر» خوانده شود، تابع am_is_vip() تاریخ پایان را
 * بررسی می‌کند و در صورت گذشته بودن، همان لحظه رکورد را به free برمی‌گرداند.
 */

if (file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
}

/**
 * مقایسه صحیح تاریخ انقضای اشتراک (بر اساس «تاریخ» نه ساعت روز).
 * خروجی:
 *   >= 0 : هنوز معتبر است (0 یعنی تا پایان امروز اعتبار دارد)
 *   -1    : منقضی شده
 */
function am_subscription_days_left($endDate) {
    if (empty($endDate)) {
        return -1;
    }
    try {
        $end = new DateTime($endDate);
    } catch (Exception $e) {
        return -1;
    }
    $today = new DateTime('today');
    if ($end < $today) {
        return -1;
    }
    $diff = (int)$today->diff($end)->format('%r%a');
    return $diff < 0 ? -1 : $diff;
}

/**
 * تشخیص صحیح VIP بودن یک کاربر.
 * - بدون تاریخ پایان = VIP نامحدود (توسط ادمین)
 * - با تاریخ پایان = فقط تا روز مشخص
 * - در صورت انقضا، فوراً رکورد را به free برمی‌گرداند (حالت سخت‌گیرانه)
 */
function am_is_vip(array $user, $autoRefresh = true) {
    $status = $user['subscription_status'] ?? 'free';

    if ($status !== 'vip') {
        return false;
    }

    $end = $user['subscription_end_date'] ?? null;

    // VIP نامحدود
    if ($end === null || $end === '') {
        return true;
    }

    $left = am_subscription_days_left($end);
    if ($left >= 0) {
        return true;
    }

    // منقضی — اصلاح فوری در دیتابیس (تا دیگر هیچ صفحه‌ای کاربر را VIP نبیند)
    if ($autoRefresh) {
        am_demote_expired_vip((int)($user['id'] ?? 0));
    }
    return false;
}

/**
 * تغییر وضعیت یک کاربر منقضی از vip به free.
 * شرط subscription_status='vip' در UPDATE مانع تداخل‌های همزمان می‌شود.
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
    // هماهنگ‌سازی session
    if (isset($_SESSION['subscription_status'])) {
        $_SESSION['subscription_status'] = 'free';
    }
}

/**
 * دریافت کاربر جاری (واردشده) به همراه اجرای منطق انقضا.
 * به خاطر حافظه‌ی استاتیک، در هر درخواست فقط یک بار کاربر خوانده می‌شود.
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
        // اصلاح خودکار (و دائمی) در صورت انقضا
        $user['subscription_status'] = am_is_vip($user) ? 'vip' : 'free';
        $_SESSION['subscription_status'] = $user['subscription_status'];
        $cached = $user;
    } catch (PDOException $e) {
        error_log('[VIP] current user error: ' . $e->getMessage());
    }
    return $cached;
}
