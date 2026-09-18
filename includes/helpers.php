<?php
/**
 * انیمه موزیک — توابع کمکی مشترک
 */

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}
if (file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
}

/** اتصال به دیتابیس محتوا (نام کوتاه) */
function am_content_db() {
    return am_db_content();
}

/** اتصال به دیتابیس کاربران (نام کوتاه) */
function am_users_db() {
    return am_db_users();
}

/**
 * افزودن نسخه‌بندی به فایل‌های استاتیک برای شکستن کش مرورگر
 */
function am_asset($path) {
    static $cache = [];
    if (isset($cache[$path])) {
        return $cache[$path];
    }
    $full = defined('AM_ROOT') ? AM_ROOT . '/' . ltrim($path, '/') : __DIR__ . '/../' . ltrim($path, '/');
    $result = file_exists($full) ? $path . '?v=' . filemtime($full) : $path;
    $cache[$path] = $result;
    return $result;
}

/** تبدیل اعداد انگلیسی به فارسی */
function am_fa_num($str) {
    return str_replace(['0','1','2','3','4','5','6','7','8','9'], ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], (string)$str);
}

/** خروجی امن متن در HTML */
function am_e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/**
 * تبدیل تاریخ میلادی به شمسی (الگوریتم استاندارد jdf)
 *
 * @param string|int $time  تاریخ مجاز برای strtotime یا timestamp
 * @param string $format    'Y/m/d' یا 'Y-m-d' یا 'Y/m/d H:i'
 */
function am_jdate($format = 'Y/m/d', $time = null) {
    $ts = $time === null ? time() : (is_numeric($time) ? (int)$time : strtotime($time));
    $ts = (int)$ts;
    if ($ts <= 0) {
        return '';
    }

    // استخراج میلادی
    $gy = (int)date('Y', $ts);
    $gm = (int)date('n', $ts);
    $gd = (int)date('j', $ts);

    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];

    $jy = -1595 + (33 * ((int)($days / 12053)));
    $days %= 12053;
    $jy += 4 * ((int)($days / 1461));
    $days %= 1461;

    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }

    $jm = $jd = 0;
    if ($days < 186) {
        $jm = 1 + (int)($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }

    $jH = date('H', $ts);
    $ji = date('i', $ts);
    $js = date('s', $ts);

    $map = [
        'Y' => (string)$jy,
        'm' => str_pad((string)$jm, 2, '0', STR_PAD_LEFT),
        'n' => (string)$jm,
        'd' => str_pad((string)$jd, 2, '0', STR_PAD_LEFT),
        'j' => (string)$jd,
        'H' => $jH,
        'i' => $ji,
        's' => $js,
    ];

    $out = $format;
    foreach ($map as $token => $value) {
        $out = str_replace($token, $value, $out);
    }
    return am_fa_num($out);
}

/** نمایش ساده و کوتاه تاریخ شمسی برای اعتبار اشتراک */
function am_format_date($date) {
    if (empty($date)) {
        return '';
    }
    return am_jdate('Y/m/d', $date);
}

/** آیا کاربر جاری لاگین کرده است؟ */
function am_logged_in() {
    return isset($_SESSION['user_id']);
}

/** وضعیت تم (تیره/روشن) کاربر از کوکی */
function am_theme() {
    return (isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true');
}

/** تنظیم هدرهای ضد کش */
function am_no_cache() {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: 0");
}

/** دریافت/افزایش آمار بازدید (ذخیره در فایل JSON) */
function am_log_visit($file) {
    $data = ['total' => 0, 'daily' => [], 'weekly' => [], 'monthly' => []];
    if (file_exists($file)) {
        $decoded = json_decode((string)file_get_contents($file), true);
        if (is_array($decoded)) {
            $data = array_merge($data, $decoded);
        }
    }
    $today  = date('Y-m-d');
    $week   = date('Y-W');
    $month  = date('Y-m');

    $data['total'] = (int)($data['total'] ?? 0) + 1;
    $data['daily'][$today]   = (int)($data['daily'][$today] ?? 0) + 1;
    $data['weekly'][$week]   = (int)($data['weekly'][$week] ?? 0) + 1;
    $data['monthly'][$month] = (int)($data['monthly'][$month] ?? 0) + 1;

    @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE));
}
