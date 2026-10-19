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

/* ============================================================
 *  هسته امن خواندن/نوشتن فایل‌های JSON آمار
 *  — رفع باگ ریست شدن ناگهانی آمار (race condition)
 *  قواعد:
 *   1) نوشتن همیشه اتمیک است (tmp + rename) تا خواننده هرگز فایل نیمه‌کاره نبیند.
 *   2) هر چرخه «خواندن/تغییر/نوشتن» با قفل انحصاری (flock) انجام می‌شود.
 *   3) قبل از هر نوشتن، نسخه پشتیبان (.bak) ساخته می‌شود.
 *   4) اگر فایلی خراب شد، از .bak بازیابی می‌شود و فایل خراب قرنطینه می‌شود؛
 *      هرگز داده سالم با مقادیر صفر بازنویسی نمی‌شود.
 * ============================================================ */

/** نوشتن خام و اتمیک JSON (tmp + rename) — بدون قفل */
function am_json_write_raw($file, $data) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }
    $tmp = $file . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(3));
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
        @unlink($tmp);
        return false;
    }
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        return false;
    }
    @chmod($file, 0664);
    return true;
}

/** قرنطینه فایل خراب (نگه‌داشتن شواهد، بدون حذف) */
function am_json_quarantine($file) {
    if (is_file($file)) {
        @rename($file, $file . '.corrupt-' . date('YmdHis') . '-' . bin2hex(random_bytes(2)));
    }
}

/**
 * خواندن JSON با بازیابی خودکار:
 * اگر فایل اصلی خالی/خراب بود، از .bak بازیابی و ترمیم می‌شود.
 */
function am_json_read($file, $default = []) {
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        if ($raw !== false && trim($raw) !== '') {
            $data = json_decode($raw, true);
            if (is_array($data)) {
                return $data;
            }
        }
        // فایل خراب یا خالیِ غیرمنتظره → تلاش برای بازیابی از پشتیبان
        $bak = $file . '.bak';
        if (is_file($bak)) {
            $braw = @file_get_contents($bak);
            $bdata = json_decode((string)$braw, true);
            if (is_array($bdata)) {
                am_json_quarantine($file);
                am_json_write_raw($file, $bdata); // ترمیم فایل اصلی از پشتیبان
                return $bdata;
            }
        }
        // پشتیبان هم نبود → فقط قرنطینه؛ هرگز بی‌صدا صفر نمی‌شود
        if (trim((string)$raw) !== '') {
            am_json_quarantine($file);
        }
        return $default;
    }
    return $default;
}

/**
 * نوشتن امن JSON با قفل انحصاری + ساخت نسخه پشتیبان.
 */
function am_json_write($file, $data) {
    $lockPath = $file . '.lock';
    $lock = @fopen($lockPath, 'c');
    if ($lock === false) {
        return am_json_write_raw($file, $data);
    }
    $ok = false;
    if (@flock($lock, LOCK_EX)) {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json !== false) {
            // پشتیبان از داده جدید (اگر نوشتن اصلی وسط کار قطع شد، بازیابی بدون از دست رفتن باشد)
            @file_put_contents($file . '.bak', $json, LOCK_EX);
            $ok = am_json_write_raw($file, $data);
        }
        @flock($lock, LOCK_UN);
    }
    @fclose($lock);
    return $ok;
}

/**
 * به‌روزرسانی اتمیک فایل آمار (خواندن/تغییر/نوشتن زیر یک قفل انحصاری).
 * $mutator یک callable(&$data) است که داده را درجا تغییر می‌دهد.
 */
function am_stats_update($file, callable $mutator) {
    $lockPath = $file . '.lock';
    $lock = @fopen($lockPath, 'c');
    if ($lock === false) {
        // بدون قفل هم حداقل خواندن/نوشتن اتمیک داریم
        $data = am_json_read($file, []);
        $mutator($data);
        am_stats_prune($data);
        return am_json_write_raw($file, $data);
    }
    $result = false;
    if (@flock($lock, LOCK_EX)) {
        $data = null;
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            if ($raw !== false && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                } else {
                    // فایل خراب: قرنطینه + بازیابی از پشتیبان
                    am_json_quarantine($file);
                    $bak = $file . '.bak';
                    if (is_file($bak)) {
                        $bdata = json_decode((string)@file_get_contents($bak), true);
                        if (is_array($bdata)) {
                            $data = $bdata;
                        }
                    }
                }
            } else {
                // فایل خالی (مثلاً قطع برق وسط نوشتن قدیمی) → بازیابی از پشتیبان
                $bak = $file . '.bak';
                if (is_file($bak)) {
                    $bdata = json_decode((string)@file_get_contents($bak), true);
                    if (is_array($bdata)) {
                        $data = $bdata;
                    }
                }
            }
        }
        if (!is_array($data)) {
            $data = [];
        }
        $mutator($data);
        am_stats_prune($data);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($json !== false) {
            @file_put_contents($file . '.bak', $json, LOCK_EX);
            $tmp = $file . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(3));
            if (@file_put_contents($tmp, $json, LOCK_EX) !== false && @rename($tmp, $file)) {
                @chmod($file, 0664);
                $result = true;
            } else {
                @unlink($tmp);
            }
        }
        @flock($lock, LOCK_UN);
    }
    @fclose($lock);
    return $result;
}

/** هرس کلیدهای قدیمی daily/weekly/monthly (نگه‌داشتن پنجره‌های متعارف) */
function am_stats_prune(array &$data) {
    $pruneSet = function (&$set, $keep) {
        if (!is_array($set) || count($set) <= $keep) {
            return;
        }
        ksort($set, SORT_STRING);
        $set = array_slice($set, -$keep, null, true);
    };
    $pruneGroup = function (&$g) use ($pruneSet) {
        if (!is_array($g)) {
            return;
        }
        $pruneSet($g['daily'], 120);
        $pruneSet($g['weekly'], 30);
        $pruneSet($g['monthly'], 24);
    };
    $pruneGroup($data);
    if (isset($data['langs']) && is_array($data['langs'])) {
        foreach ($data['langs'] as &$lg) {
            if (!is_array($lg)) continue;
            $pruneGroup($lg);
            if (isset($lg['slots']) && is_array($lg['slots'])) {
                foreach ($lg['slots'] as &$sg) {
                    if (!is_array($sg)) continue;
                    if (isset($sg['clicks']) || isset($sg['views'])) {
                        // ساختار جدید: گروه‌های کلیک/بازدید
                        $pruneGroup($sg['clicks']);
                        $pruneGroup($sg['views']);
                    } else {
                        // ساختار قدیمی: کلیک‌ها در خودِ سطح
                        $pruneGroup($sg);
                    }
                }
                unset($sg);
            }
        }
        unset($lg);
    }
}

/** دریافت/افزایش آمار بازدید (ذخیره در فایل JSON) — با قابلیت تفکیک زبان */
function am_log_visit($file, $lang = null) {
    am_stats_update($file, function (&$data) use ($lang) {
        if (!isset($data['langs']) || !is_array($data['langs'])) {
            $data['langs'] = [];
        }
        $today = date('Y-m-d');
        $week  = date('Y-W');
        $month = date('Y-m');
        am_visit_incr($data, $today, $week, $month);
        if ($lang !== null && $lang !== '') {
            $lang = (string)$lang;
            if (!isset($data['langs'][$lang]) || !is_array($data['langs'][$lang])) {
                $data['langs'][$lang] = ['total' => 0, 'daily' => [], 'weekly' => [], 'monthly' => []];
            }
            am_visit_incr($data['langs'][$lang], $today, $week, $month);
        }
    });
}

/** خواندن تنظیمات تبلیغ (پیکربندی هر زبان) از فایل JSON */
function am_ad_config() {
    static $config = null;
    if ($config === null) {
        $file = defined('AM_AD_CONFIG_FILE')
            ? AM_AD_CONFIG_FILE
            : (defined('AM_ROOT') ? AM_ROOT . '/ad_config.json' : __DIR__ . '/../ad_config.json');
        $config = am_json_read($file, []);
    }
    return $config;
}

/** ذخیره تنظیمات تبلیغ در فایل JSON */
function am_ad_config_save($config) {
    $file = defined('AM_AD_CONFIG_FILE')
        ? AM_AD_CONFIG_FILE
        : (defined('AM_ROOT') ? AM_ROOT . '/ad_config.json' : __DIR__ . '/../ad_config.json');
    if (!is_array($config)) $config = [];
    return am_json_write($file, $config);
}

/* ============================================================
 *  سیستم جایگاه‌های تبلیغ (۲ جایگاه × ۲ نوع برای هر زبان)
 *  جایگاه‌ها: display (نمایش تبلیغ) و download (دروازه دانلود)
 *  نوع‌ها:     normal (تبلیغ معمولی: تصویر/ویدیو + لینک) و code (کد تبلیغ)
 * ============================================================ */

/** نام جایگاه‌های معتبر تبلیغ */
function am_ad_slots() {
    return ['display', 'download'];
}

/**
 * نرمال‌سازی تنظیمات یک جایگاه تبلیغ برای یک زبان.
 * خروجی: ['type' => 'normal'|'code', 'normal' => [...], 'code' => '...']
 * سازگاری با پیکربندی قدیمی (url/aparat_url/video/image در ریشه) حفظ می‌شود.
 */
function am_ad_slot($lang, $slot) {
    $slot = in_array($slot, ['display', 'download'], true) ? $slot : 'display';
    $lang = (string)($lang !== null && $lang !== '' ? $lang : 'fa');
    $config = am_ad_config();

    $defaultNormal = ['url' => '', 'aparat_url' => '', 'video' => 'assets/ads/myad.mp4', 'image' => ''];

    $pick = function ($src) use ($defaultNormal) {
        $normal = $defaultNormal;
        if (isset($src['normal']) && is_array($src['normal'])) {
            foreach (array_keys($defaultNormal) as $k) {
                if (isset($src['normal'][$k])) {
                    $normal[$k] = (string)$src['normal'][$k];
                }
            }
        } else {
            // سازگاری قدیمی: فیلدها در خودِ سطح
            foreach (array_keys($defaultNormal) as $k) {
                if (isset($src[$k])) {
                    $normal[$k] = (string)$src[$k];
                }
            }
        }
        $type = 'normal';
        if (isset($src['type']) && in_array($src['type'], ['normal', 'code'], true)) {
            $type = $src['type'];
        }
        $code = isset($src['code']) ? (string)$src['code'] : '';
        return ['type' => $type, 'normal' => $normal, 'code' => $code];
    };

    // 1) پیکربندی صریح همین زبان + همین جایگاه
    if (isset($config[$lang]['slots'][$slot]) && is_array($config[$lang]['slots'][$slot])) {
        return $pick($config[$lang]['slots'][$slot]);
    }
    // 2) فیلدهای قدیمی همین زبان (پیکربندی قبل از سیستم جایگاه‌ها)
    if (isset($config[$lang]) && is_array($config[$lang])) {
        $legacy = $pick($config[$lang]);
        if ($legacy['normal']['url'] !== '' || $legacy['normal']['video'] !== '' || $legacy['normal']['image'] !== '') {
            return $legacy;
        }
    }
    // 3) جایگاه display همان زبان (برای download)
    if ($slot === 'download' && isset($config[$lang]['slots']['display']) && is_array($config[$lang]['slots']['display'])) {
        return $pick($config[$lang]['slots']['display']);
    }
    // 4) زبان fa
    if ($lang !== 'fa' && isset($config['fa']) && is_array($config['fa'])) {
        if (isset($config['fa']['slots'][$slot]) && is_array($config['fa']['slots'][$slot])) {
            return $pick($config['fa']['slots'][$slot]);
        }
        $legacy = $pick($config['fa']);
        if ($legacy['normal']['url'] !== '' || $legacy['normal']['video'] !== '' || $legacy['normal']['image'] !== '') {
            return $legacy;
        }
    }
    return $pick($defaultNormal);
}

/** نوع تبلیغ یک جایگاه: 'normal' یا 'code' */
function am_ad_slot_type($lang, $slot) {
    return am_ad_slot($lang, $slot)['type'];
}

/** کد تبلیغ (نوع code) یک جایگاه — خروجی خام؛ فقط ادمین آن را وارد می‌کند */
function am_ad_slot_code($lang, $slot) {
    return am_ad_slot($lang, $slot)['code'];
}

/** فیلدهای تبلیغ معمولی (نوع normal) یک جایگاه */
function am_ad_slot_normal($lang, $slot) {
    return am_ad_slot($lang, $slot)['normal'];
}

/** تبلیغ مربوط به یک زبان (سازگاری با کدهای قدیمی — همان جایگاه نمایش، نوع معمولی) */
function am_ad_for_lang($lang = null) {
    if ($lang === null || $lang === '') {
        $lang = 'fa';
    }
    $slot = am_ad_slot($lang, 'display');
    return $slot['normal'];
}

/** مسیر مطلق یا نسبی یک URL تبلیغ */
function am_ad_target_url($url) {
    $url = trim((string)$url);
    if ($url === '' || $url === '#') {
        return defined('AM_SITE_URL') ? rtrim(AM_SITE_URL, '/') . '/' : '/';
    }
    if (!preg_match('#^[a-z][a-z0-9+.\-]*://#i', $url)) {
        $url = (strpos($url, '/') === 0 && defined('AM_SITE_URL')) ? rtrim(AM_SITE_URL, '/') . $url : 'http://' . ltrim($url, '/');
    }
    return $url;
}

/** آدرس شمارنده کلیک تبلیغ برای یک جایگاه/زبان */
function am_ad_click_url($lang, $slot = 'display', $extra = []) {
    $params = array_merge(['lang' => (string)$lang, 'slot' => (string)$slot], $extra);
    return '/ad_click.php?' . http_build_query($params);
}

/* ============================================================
 *  دروازه دانلود با مشاهده تبلیغ (Gate)
 *  کاربر باید روی تبلیغ کلیک کند و به سایت برگردد؛ سپس کوکی امضاشده
 *  صادر و دانلود برای ۳۰ دقیقه فعال می‌شود.
 * ============================================================ */

/** مدت اعتبار دانلود پس از کلیک روی تبلیغ (ثانیه) — ۲۴ ساعت برای همان یک موزیک */
function am_gate_ttl() {
    return 86400;
}

function am_gate_secret() {
    $key = defined('AM_GATE_KEY') && AM_GATE_KEY !== '' ? AM_GATE_KEY : 'anime-music-gate-v1';
    return hash('sha256', $key . '|' . (defined('AM_SITE_URL') ? AM_SITE_URL : ''));
}

/** نام کوکی دروازه — برای هر موزیک جدا (قفل فقط برای همان محتوا باز می‌شود) */
function am_gate_cookie($contentId) {
    return 'am_dl_gate_' . (int)$contentId;
}

/** صدور کوکی امضاشده فعال‌سازی دانلود — فقط برای همان محتوا، به مدت ۲۴ ساعت */
function am_gate_issue($userId = null, $contentId = null) {
    $cid = (int)$contentId;
    $uid = $userId !== null && $userId !== '' ? 'u' . (int)$userId : 'guest';
    $exp = time() + am_gate_ttl();
    $sig = hash_hmac('sha256', $uid . '|' . $exp . '|' . $cid, am_gate_secret());
    $token = $exp . '.' . $uid . '.' . $sig;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    setcookie(am_gate_cookie($cid), $token, [
        'expires'  => $exp,
        'path'     => '/',
        'samesite' => 'Lax',
        'httponly' => true,
        'secure'   => $secure,
    ]);
    $_COOKIE[am_gate_cookie($cid)] = $token;
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['am_gate'][$cid] = ['exp' => $exp];
    }
    return true;
}

/**
 * آیا دانلودِ همین محتوا (پس از کلیک روی تبلیغ) فعال است؟
 * قفل برای هر موزیک جدا باز می‌شود و ۲۴ ساعت اعتبار دارد.
 */
function am_gate_valid($userId = null, $contentId = null) {
    $cid = (int)$contentId;
    if (session_status() === PHP_SESSION_ACTIVE
        && isset($_SESSION['am_gate'][$cid])
        && (int)($_SESSION['am_gate'][$cid]['exp'] ?? 0) >= time()) {
        return true;
    }
    $token = (string)($_COOKIE[am_gate_cookie($cid)] ?? '');
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return false;
    }
    [$exp, $uid, $sig] = $parts;
    if (!ctype_digit($exp) || (int)$exp < time()) {
        return false;
    }
    $expect = hash_hmac('sha256', $uid . '|' . $exp . '|' . $cid, am_gate_secret());
    return hash_equals($expect, (string)$sig);
}

/* ============================================================
 *  آمار تفکیکی جایگاه‌های تبلیغ (بازدید / کلیک / فعال‌سازی دانلود)
 *  برای هر زبان و هر جایگاه (display|download) جدا:
 *   - «پنجره» (clicks/views/unlocks): از زمان آخرین بروزرسانی همان جایگاه صفر می‌شود
 *   - «مادام‌العمر» (lifetime): هرگز صفر نمی‌شود
 *  بروزرسانی یک جایگاه، آمار جایگاه دیگر را صفر نمی‌کند.
 * ============================================================ */

/** ساختار خالی آمار یک جایگاه */
function am_ad_slot_stats_blank($since = null) {
    return [
        'since'    => $since !== null && $since !== '' ? (string)$since : date('Y-m-d H:i:s'),
        'clicks'   => ['total' => 0, 'daily' => [], 'weekly' => [], 'monthly' => []],
        'views'    => ['total' => 0, 'daily' => [], 'weekly' => [], 'monthly' => []],
        'unlocks'  => 0,
        'lifetime' => ['clicks' => 0, 'views' => 0, 'unlocks' => 0],
    ];
}

/** نرمال‌سازی آمار یک جایگاه (سازگاری با ساختار قدیمی کلیک‌ها) */
function am_ad_slot_stats_normalize($entry) {
    if (!is_array($entry)) {
        return am_ad_slot_stats_blank();
    }
    if (isset($entry['clicks']) || isset($entry['views']) || isset($entry['lifetime'])) {
        $blank = am_ad_slot_stats_blank(isset($entry['since']) ? $entry['since'] : null);
        $out = [
            'since'   => (string)($entry['since'] ?? $blank['since']),
            'clicks'  => array_merge($blank['clicks'], is_array($entry['clicks'] ?? null) ? $entry['clicks'] : []),
            'views'   => array_merge($blank['views'], is_array($entry['views'] ?? null) ? $entry['views'] : []),
            'unlocks' => (int)($entry['unlocks'] ?? 0),
            'lifetime'=> array_merge($blank['lifetime'], is_array($entry['lifetime'] ?? null) ? $entry['lifetime'] : []),
        ];
        foreach (['clicks', 'views'] as $g) {
            $out[$g]['total'] = (int)($out[$g]['total'] ?? 0);
            foreach (['daily', 'weekly', 'monthly'] as $k) {
                if (!is_array($out[$g][$k])) $out[$g][$k] = [];
            }
        }
        foreach (['clicks', 'views', 'unlocks'] as $k) {
            $out['lifetime'][$k] = (int)($out['lifetime'][$k] ?? 0);
        }
        return $out;
    }
    // ساختار قدیمی: total/daily/... در خودِ سطح = کلیک‌ها
    $out = am_ad_slot_stats_blank();
    $out['clicks'] = [
        'total'   => (int)($entry['total'] ?? 0),
        'daily'   => is_array($entry['daily'] ?? null) ? $entry['daily'] : [],
        'weekly'  => is_array($entry['weekly'] ?? null) ? $entry['weekly'] : [],
        'monthly' => is_array($entry['monthly'] ?? null) ? $entry['monthly'] : [],
    ];
    $out['lifetime']['clicks'] = (int)($entry['total'] ?? 0);
    return $out;
}

/** آمار خام یک جایگاه (خواندن از فایل کلیک‌ها) — خروجی نرمال‌شده */
function am_ad_slot_stats_raw($lang, $slot) {
    $lang = (string)$lang;
    $slot = in_array($slot, ['display', 'download'], true) ? $slot : 'display';
    $file = defined('AM_AD_CLICKS_FILE')
        ? AM_AD_CLICKS_FILE
        : (defined('AM_ROOT') ? AM_ROOT . '/ad_clicks.json' : __DIR__ . '/../ad_clicks.json');
    $data = am_json_read($file, []);
    $entry = $data['langs'][$lang]['slots'][$slot] ?? null;
    return am_ad_slot_stats_normalize($entry);
}

/**
 * آمار کامل یک جایگاه برای نمایش: since + clicks + views + unlocks + lifetime
 * + نرخ کلیک (CTR) و میانگین روزانه از زمان بروزرسانی
 */
function am_ad_slot_stats($lang, $slot) {
    $s = am_ad_slot_stats_raw($lang, $slot);
    $views = (int)$s['views']['total'];
    $clicks = (int)$s['clicks']['total'];
    $sinceTs = strtotime($s['since']) ?: time();
    $days = max(1, (int)ceil((time() - $sinceTs) / 86400));
    $s['ctr'] = $views > 0 ? round(100 * $clicks / $views, 1) : 0.0;
    $s['days_since'] = $days;
    $s['views_per_day'] = round($views / $days, 1);
    $s['clicks_per_day'] = round($clicks / $days, 1);
    return $s;
}

/** افزایش یک شمارنده گروه آمار (total/daily/weekly/monthly) */
function am_stats_group_incr(&$g, $today, $week, $month) {
    if (!is_array($g)) $g = ['total' => 0, 'daily' => [], 'weekly' => [], 'monthly' => []];
    am_visit_incr($g, $today, $week, $month);
}

/** ثبت «بازدید» (Impression) یک جایگاه تبلیغ — تفکیک زبان و جایگاه */
function am_ad_view_log($lang, $slot) {
    $lang = (string)$lang;
    $slot = in_array($slot, ['display', 'download'], true) ? $slot : 'display';
    am_stats_update(
        defined('AM_AD_CLICKS_FILE') ? AM_AD_CLICKS_FILE : (defined('AM_ROOT') ? AM_ROOT . '/ad_clicks.json' : __DIR__ . '/../ad_clicks.json'),
        function (&$data) use ($lang, $slot) {
            if (!isset($data['langs'][$lang]) || !is_array($data['langs'][$lang])) {
                $data['langs'][$lang] = ['total' => 0, 'daily' => [], 'weekly' => [], 'monthly' => [], 'slots' => []];
            }
            if (!isset($data['langs'][$lang]['slots'][$slot])) {
                $data['langs'][$lang]['slots'][$slot] = [];
            }
            $data['langs'][$lang]['slots'][$slot] = am_ad_slot_stats_normalize($data['langs'][$lang]['slots'][$slot]);
            $today = date('Y-m-d');
            $week  = date('Y-W');
            $month = date('Y-m');
            am_stats_group_incr($data['langs'][$lang]['slots'][$slot]['views'], $today, $week, $month);
            $data['langs'][$lang]['slots'][$slot]['lifetime']['views'] =
                (int)($data['langs'][$lang]['slots'][$slot]['lifetime']['views'] ?? 0) + 1;
        }
    );
}

/** ثبت «فعال‌سازی دانلود» (باز شدن قفل دانلود پس از تبلیغ) — برای جایگاه دانلود */
function am_ad_unlock_log($lang) {
    $lang = (string)$lang;
    am_stats_update(
        defined('AM_AD_CLICKS_FILE') ? AM_AD_CLICKS_FILE : (defined('AM_ROOT') ? AM_ROOT . '/ad_clicks.json' : __DIR__ . '/../ad_clicks.json'),
        function (&$data) use ($lang) {
            if (!isset($data['langs'][$lang]) || !is_array($data['langs'][$lang])) {
                $data['langs'][$lang] = ['total' => 0, 'daily' => [], 'weekly' => [], 'monthly' => [], 'slots' => []];
            }
            if (!isset($data['langs'][$lang]['slots']['download'])) {
                $data['langs'][$lang]['slots']['download'] = [];
            }
            $data['langs'][$lang]['slots']['download'] = am_ad_slot_stats_normalize($data['langs'][$lang]['slots']['download']);
            $data['langs'][$lang]['slots']['download']['unlocks'] =
                (int)($data['langs'][$lang]['slots']['download']['unlocks'] ?? 0) + 1;
            $data['langs'][$lang]['slots']['download']['lifetime']['unlocks'] =
                (int)($data['langs'][$lang]['slots']['download']['lifetime']['unlocks'] ?? 0) + 1;
        }
    );
}

/** ثبت کلیک روی تبلیغ — تفکیک زبان و جایگاه + آمار مادام‌العمر */
function am_ad_click_log($lang = 'fa', $slot = null) {
    $lang = (string)$lang;
    $slot = ($slot !== null && in_array($slot, ['display', 'download'], true)) ? $slot : null;
    am_stats_update(
        defined('AM_AD_CLICKS_FILE') ? AM_AD_CLICKS_FILE : (defined('AM_ROOT') ? AM_ROOT . '/ad_clicks.json' : __DIR__ . '/../ad_clicks.json'),
        function (&$data) use ($lang, $slot) {
            if (!isset($data['langs']) || !is_array($data['langs'])) {
                $data['langs'] = [];
            }
            $today = date('Y-m-d');
            $week  = date('Y-W');
            $month = date('Y-m');
            am_visit_incr($data, $today, $week, $month);
            if (!isset($data['langs'][$lang]) || !is_array($data['langs'][$lang])) {
                $data['langs'][$lang] = ['total' => 0, 'daily' => [], 'weekly' => [], 'monthly' => [], 'slots' => []];
            }
            am_visit_incr($data['langs'][$lang], $today, $week, $month);
            if ($slot !== null) {
                if (!isset($data['langs'][$lang]['slots'][$slot])) {
                    $data['langs'][$lang]['slots'][$slot] = [];
                }
                $data['langs'][$lang]['slots'][$slot] = am_ad_slot_stats_normalize($data['langs'][$lang]['slots'][$slot]);
                am_stats_group_incr($data['langs'][$lang]['slots'][$slot]['clicks'], $today, $week, $month);
                $data['langs'][$lang]['slots'][$slot]['lifetime']['clicks'] =
                    (int)($data['langs'][$lang]['slots'][$slot]['lifetime']['clicks'] ?? 0) + 1;
            }
        }
    );
}

/**
 * صفر کردن آمار «پنجره» یک جایگاه مشخص (مثلاً فقط جایگاه نمایش).
 * وقتی ادمین تنظیمات همان جایگاه را تغییر می‌دهد؛ جایگاه دیگر دست‌نخورده می‌ماند.
 * آمار مادام‌العمر حفظ می‌شود.
 */
function am_ad_reset_slot($lang, $slot) {
    $lang = (string)$lang;
    $slot = in_array($slot, ['display', 'download'], true) ? $slot : null;
    if ($lang === '' || $slot === null) return false;
    return am_stats_update(
        defined('AM_AD_CLICKS_FILE') ? AM_AD_CLICKS_FILE : (defined('AM_ROOT') ? AM_ROOT . '/ad_clicks.json' : __DIR__ . '/../ad_clicks.json'),
        function (&$data) use ($lang, $slot) {
            if (!isset($data['langs']) || !is_array($data['langs'])) {
                $data['langs'] = [];
            }
            if (!isset($data['langs'][$lang]) || !is_array($data['langs'][$lang])) {
                $data['langs'][$lang] = ['total' => 0, 'daily' => [], 'weekly' => [], 'monthly' => [], 'slots' => []];
            }
            $old = am_ad_slot_stats_normalize($data['langs'][$lang]['slots'][$slot] ?? null);

            // کسر سهم کلیک‌های این جایگاه از مجموع سراسری/زبان (بدون از دست رفتن آمار بدون جایگاه)
            $data['total'] = max(0, (int)($data['total'] ?? 0) - (int)$old['clicks']['total']);
            $sub = function (array $set, array $remove) {
                foreach ($remove as $d => $v) {
                    if (!is_int($v) && !is_numeric($v)) continue;
                    $remaining = (int)($set[$d] ?? 0) - (int)$v;
                    if ($remaining <= 0) {
                        unset($set[$d]);
                    } else {
                        $set[$d] = $remaining;
                    }
                }
                return $set;
            };
            $data['daily']   = $sub(is_array($data['daily'] ?? null) ? $data['daily'] : [], is_array($old['clicks']['daily'] ?? null) ? $old['clicks']['daily'] : []);
            $data['weekly']  = $sub(is_array($data['weekly'] ?? null) ? $data['weekly'] : [], is_array($old['clicks']['weekly'] ?? null) ? $old['clicks']['weekly'] : []);
            $data['monthly'] = $sub(is_array($data['monthly'] ?? null) ? $data['monthly'] : [], is_array($old['clicks']['monthly'] ?? null) ? $old['clicks']['monthly'] : []);

            $lg = &$data['langs'][$lang];
            $lg['total'] = max(0, (int)($lg['total'] ?? 0) - (int)$old['clicks']['total']);
            $lg['daily']   = $sub(is_array($lg['daily'] ?? null) ? $lg['daily'] : [], is_array($old['clicks']['daily'] ?? null) ? $old['clicks']['daily'] : []);
            $lg['weekly']  = $sub(is_array($lg['weekly'] ?? null) ? $lg['weekly'] : [], is_array($old['clicks']['weekly'] ?? null) ? $old['clicks']['weekly'] : []);
            $lg['monthly'] = $sub(is_array($lg['monthly'] ?? null) ? $lg['monthly'] : [], is_array($old['clicks']['monthly'] ?? null) ? $old['clicks']['monthly'] : []);

            // پنجره جدید؛ مادام‌العمر حفظ می‌شود
            $fresh = am_ad_slot_stats_blank();
            $fresh['lifetime'] = $old['lifetime'];
            $lg['slots'][$slot] = $fresh;
        }
    );
}

/**
 * (سازگاری با کد قدیمی) صفر کردن همه جایگاه‌های یک زبان.
 * توصیه: به‌جای این، am_ad_reset_slot را برای هر جایگاه جدا صدا بزنید.
 */
function am_ad_reset_clicks($lang) {
    $lang = (string)$lang;
    if ($lang === '') return false;
    $ok = am_ad_reset_slot($lang, 'display');
    $ok = am_ad_reset_slot($lang, 'download') && $ok;
    return $ok;
}

/**
 * آمار کلیک‌های تبلیغ یک زبان (total + روند + تفکیک جایگاه‌ها با آمار کامل).
 * اگر زبانی داده نشود، جمع کل همه‌ی زبان‌ها برمی‌گردد.
 */
function am_ad_clicks_stat($lang = null) {
    $file = defined('AM_AD_CLICKS_FILE')
        ? AM_AD_CLICKS_FILE
        : (defined('AM_ROOT') ? AM_ROOT . '/ad_clicks.json' : __DIR__ . '/../ad_clicks.json');
    $data = am_json_read($file, []);
    if ($lang !== null && $lang !== '') {
        $lang = (string)$lang;
        $g = isset($data['langs'][$lang]) && is_array($data['langs'][$lang])
            ? $data['langs'][$lang]
            : ['total' => 0, 'daily' => [], 'weekly' => [], 'monthly' => []];
        return [
            'total'   => (int)($g['total'] ?? 0),
            'daily'   => $g['daily']   ?? [],
            'weekly'  => $g['weekly']  ?? [],
            'monthly' => $g['monthly'] ?? [],
            'slots'   => [
                'display'  => am_ad_slot_stats_normalize($g['slots']['display'] ?? null),
                'download' => am_ad_slot_stats_normalize($g['slots']['download'] ?? null),
            ],
        ];
    }
    $langs = [];
    foreach (($data['langs'] ?? []) as $code => $g) {
        if (!is_array($g)) continue;
        $langs[$code] = [
            'total'   => (int)($g['total'] ?? 0),
            'daily'   => $g['daily']   ?? [],
            'weekly'  => $g['weekly']  ?? [],
            'monthly' => $g['monthly'] ?? [],
            'slots'   => [
                'display'  => am_ad_slot_stats_normalize($g['slots']['display'] ?? null),
                'download' => am_ad_slot_stats_normalize($g['slots']['download'] ?? null),
            ],
        ];
    }
    return [
        'total'   => (int)($data['total'] ?? 0),
        'daily'   => $data['daily']   ?? [],
        'weekly'  => $data['weekly']  ?? [],
        'monthly' => $data['monthly'] ?? [],
        'langs'   => $langs,
    ];
}

/** آمار بازدید از یک فایل شمارنده (visits.json / visits_ads.json) */
function am_visits_stat($file) {
    $data = am_json_read($file, []);
    return [
        'total'   => (int)($data['total'] ?? 0),
        'daily'   => $data['daily']   ?? [],
        'weekly'  => $data['weekly']  ?? [],
        'monthly' => $data['monthly'] ?? [],
        'langs'   => $data['langs']   ?? [],
    ];
}
