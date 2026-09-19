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

/** افزایش شمارنده‌های یک مجموعه آمار (total/daily/weekly/monthly) */
function am_visit_incr(&$data, $today, $week, $month) {
    $data['total'] = (int)($data['total'] ?? 0) + 1;
    $data['daily'][$today]   = (int)($data['daily'][$today] ?? 0) + 1;
    $data['weekly'][$week]   = (int)($data['weekly'][$week] ?? 0) + 1;
    $data['monthly'][$month] = (int)($data['monthly'][$month] ?? 0) + 1;
}

/** دریافت/افزایش آمار بازدید (ذخیره در فایل JSON) — با قابلیت تفکیک زبان */
function am_log_visit($file, $lang = null) {
    $data = ['total' => 0, 'daily' => [], 'weekly' => [], 'monthly' => [], 'langs' => []];
    if (file_exists($file)) {
        $decoded = json_decode((string)file_get_contents($file), true);
        if (is_array($decoded)) {
            $data = array_merge($data, $decoded);
        }
    }
    if (!isset($data['langs']) || !is_array($data['langs'])) {
        $data['langs'] = [];
    }

    $today  = date('Y-m-d');
    $week   = date('Y-W');
    $month  = date('Y-m');

    am_visit_incr($data, $today, $week, $month);

    if ($lang !== null && $lang !== '') {
        $lang = (string)$lang;
        if (!isset($data['langs'][$lang]) || !is_array($data['langs'][$lang])) {
            $data['langs'][$lang] = ['total' => 0, 'daily' => [], 'weekly' => [], 'monthly' => []];
        }
        am_visit_incr($data['langs'][$lang], $today, $week, $month);
    }

    @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE));
}

/** خواندن تنظیمات تبلیغ (پیکربندی هر زبان) از فایل JSON */
function am_ad_config() {
    static $config = null;
    if ($config === null) {
        $file = defined('AM_AD_CONFIG_FILE')
            ? AM_AD_CONFIG_FILE
            : (defined('AM_ROOT') ? AM_ROOT . '/ad_config.json' : __DIR__ . '/../ad_config.json');
        $config = [];
        if (file_exists($file)) {
            $decoded = json_decode((string)file_get_contents($file), true);
            if (is_array($decoded)) {
                $config = $decoded;
            }
        }
    }
    return $config;
}

/** ذخیره تنظیمات تبلیغ در فایل JSON */
function am_ad_config_save($config) {
    $file = defined('AM_AD_CONFIG_FILE')
        ? AM_AD_CONFIG_FILE
        : (defined('AM_ROOT') ? AM_ROOT . '/ad_config.json' : __DIR__ . '/../ad_config.json');
    if (!is_array($config)) $config = [];
    return @file_put_contents($file, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/** تبلیغ مربوط به یک زبان (با بازگشت به انگلیسی و سپس فارسی) */
function am_ad_for_lang($lang = null) {
    if ($lang === null || $lang === '') {
        $lang = 'fa';
    }
    $lang = (string)$lang;
    $config = am_ad_config();
    if (isset($config[$lang]) && is_array($config[$lang])) {
        return $config[$lang];
    }
    if (isset($config['fa']) && is_array($config['fa'])) {
        return $config['fa'];
    }
    return ['url' => '', 'aparat_url' => '', 'video' => 'assets/ads/myad.mp4'];
}

/** ثبت کلیک روی تبلیغ (برای آمار کلیک) — با تفکیک زبان */
function am_ad_click_log($lang = 'fa') {
    $file = defined('AM_AD_CLICKS_FILE')
        ? AM_AD_CLICKS_FILE
        : (defined('AM_ROOT') ? AM_ROOT . '/ad_clicks.json' : __DIR__ . '/../ad_clicks.json');
    $data = ['total' => 0, 'daily' => [], 'weekly' => [], 'monthly' => [], 'langs' => []];
    if (file_exists($file)) {
        $decoded = json_decode((string)file_get_contents($file), true);
        if (is_array($decoded)) {
            $data = array_merge($data, $decoded);
        }
    }
    if (!isset($data['langs']) || !is_array($data['langs'])) {
        $data['langs'] = [];
    }

    $today = date('Y-m-d');
    $week  = date('Y-W');
    $month = date('Y-m');

    am_visit_incr($data, $today, $week, $month);

    $lang = (string)$lang;
    if (!isset($data['langs'][$lang]) || !is_array($data['langs'][$lang])) {
        $data['langs'][$lang] = ['total' => 0, 'daily' => [], 'weekly' => [], 'monthly' => []];
    }
    am_visit_incr($data['langs'][$lang], $today, $week, $month);

    @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE));
}

/**
 * صفر کردن شمارش کلیک‌های تبلیغ یک زبان.
 * وقتی ادمین لینک تصویر یا لینک مقصد تبلیغ زبانی را تغییر می‌دهد،
 * شمارش کلیک آن زبان باید از صفر شروع شود.
 * @return bool
 */
function am_ad_reset_clicks($lang) {
    $lang = (string)$lang;
    if ($lang === '') return false;
    $file = defined('AM_AD_CLICKS_FILE')
        ? AM_AD_CLICKS_FILE
        : (defined('AM_ROOT') ? AM_ROOT . '/ad_clicks.json' : __DIR__ . '/../ad_clicks.json');
    $data = ['total' => 0, 'daily' => [], 'weekly' => [], 'monthly' => [], 'langs' => []];
    if (file_exists($file)) {
        $decoded = json_decode((string)file_get_contents($file), true);
        if (is_array($decoded)) {
            $data = array_merge($data, $decoded);
        }
    }
    if (!isset($data['langs']) || !is_array($data['langs'])) {
        $data['langs'] = [];
    }
    // مقدار قبلی زبان را برای کسر از مجموع سراسری به‌خاطر بسپار
    $old = isset($data['langs'][$lang]) && is_array($data['langs'][$lang])
        ? $data['langs'][$lang]
        : ['total' => 0, 'daily' => [], 'weekly' => [], 'monthly' => []];

    // صفر کردن شمارنده‌ی زبان (کلیک‌های قبلی حذف می‌شوند)
    $data['langs'][$lang] = ['total' => 0, 'daily' => [], 'weekly' => [], 'monthly' => []];

    // کسر سهم زبانِ صفرشده از مجموع سراسری (بدون از دست دادن آمار تاریخیِ بدون زبان)
    $data['total'] = max(0, (int)($data['total'] ?? 0) - (int)($old['total'] ?? 0));
    $sub = function (array $set, array $remove) {
        foreach ($remove as $d => $v) {
            $remaining = (int)($set[$d] ?? 0) - (int)$v;
            if ($remaining <= 0) {
                unset($set[$d]);
            } else {
                $set[$d] = $remaining;
            }
        }
        return $set;
    };
    $data['daily']   = $sub(is_array($data['daily'] ?? null) ? $data['daily'] : [], is_array($old['daily'] ?? null) ? $old['daily'] : []);
    $data['weekly']  = $sub(is_array($data['weekly'] ?? null) ? $data['weekly'] : [], is_array($old['weekly'] ?? null) ? $old['weekly'] : []);
    $data['monthly'] = $sub(is_array($data['monthly'] ?? null) ? $data['monthly'] : [], is_array($old['monthly'] ?? null) ? $old['monthly'] : []);

    return (bool)@file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE));
}

/**
 * آمار کلیک‌های تبلیغ یک زبان مشخص (total + روند روزانه/هفتگی/ماهانه).
 * اگر زبانی داده نشود، جمع کل همه‌ی زبان‌ها برمی‌گردد.
 */
function am_ad_clicks_stat($lang = null) {
    $file = defined('AM_AD_CLICKS_FILE')
        ? AM_AD_CLICKS_FILE
        : (defined('AM_ROOT') ? AM_ROOT . '/ad_clicks.json' : __DIR__ . '/../ad_clicks.json');
    $data = ['total' => 0, 'daily' => [], 'weekly' => [], 'monthly' => [], 'langs' => []];
    if (file_exists($file)) {
        $decoded = json_decode((string)file_get_contents($file), true);
        if (is_array($decoded)) {
            $data = array_merge($data, $decoded);
        }
    }
    if ($lang !== null && $lang !== '') {
        $lang = (string)$lang;
        return isset($data['langs'][$lang]) && is_array($data['langs'][$lang])
            ? $data['langs'][$lang]
            : ['total' => 0, 'daily' => [], 'weekly' => [], 'monthly' => []];
    }
    return [
        'total'   => (int)($data['total'] ?? 0),
        'daily'   => $data['daily']   ?? [],
        'weekly'  => $data['weekly']  ?? [],
        'monthly' => $data['monthly'] ?? [],
    ];
}
