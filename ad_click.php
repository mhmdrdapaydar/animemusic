<?php
/**
 * انیمه موزیک — ثبت کلیک روی تبلیغ و هدایت به مقصد
 *
 * پارامترها:
 *   lang=xx      زبان تبلیغ (اختیاری — پیش‌فرض: زبان جاری)
 *   slot=display|download    جایگاه تبلیغ (پیش‌فرض: display)
 *   beacon=1     حالت AJAX: بدون ریدایرکت، JSON برمی‌گرداند (برای کلیک تبلیغات کدی)
 *   gate=1       صدور کوکی فعال‌سازی دانلود (پس از کلیک کاربر روی تبلیغ دروازه دانلود)
 *   cid=N        شناسه محتوا (موزیک) — برای باز شدن قفل دانلودِ فقط همان موزیک
 *
 * جلوگیری از شمارش دوباره: کلیک تکراری یک نشست برای همان زبان/جایگاه
 * در پنجره‌ی ۹۰ ثانیه‌ای فقط یک‌بار شمرده می‌شود (مهم وقتی هم لینک هدایت
 * و هم بیکن جاوااسکریپت فراخوانی می‌شوند).
 */
require_once __DIR__ . '/includes/headless.php';

// تعیین زبان (از پارامتر معتبر، وگرنه زبان جاری)
$lang = am_current_lang();
if (isset($_GET['lang'])) {
    $reqLang = (string)$_GET['lang'];
    $langs   = am_languages();
    if (isset($langs[$reqLang])) {
        $lang = $reqLang;
    }
}

// جایگاه تبلیغ
$slot = isset($_GET['slot']) && $_GET['slot'] === 'download' ? 'download' : 'display';

$beacon = isset($_GET['beacon']) && (string)$_GET['beacon'] !== '0';
$gate   = isset($_GET['gate'])   && (string)$_GET['gate']   !== '0';

// ثبت کلیک در آمار (تفکیک‌شده بر اساس زبان و جایگاه) — با قفل و بدون ریست
$dedupeKey = $lang . '|' . $slot;
$last = isset($_SESSION['am_click_dedupe'][$dedupeKey]) ? (int)$_SESSION['am_click_dedupe'][$dedupeKey] : 0;
if (time() - $last > 90) {
    am_ad_click_log($lang, $slot);
    $_SESSION['am_click_dedupe'][$dedupeKey] = time();
}

// فعال‌سازی دانلود (دروازه دانلود: کاربر روی تبلیغِ همان موزیک کلیک کرده است)
if ($gate) {
    $cid = isset($_GET['cid']) ? (int)$_GET['cid'] : 0;
    am_gate_issue(isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null, $cid);
}

if ($beacon) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['ok' => true, 'logged' => ($last === 0 || time() - $last > 90), 'gate' => $gate ? 1 : 0]);
    exit;
}

// مقصد تبلیغ: از تنظیمات جایگاهِ همان زبان (اول url، بعد aparat_url، وگرنه خانه)
$adCfg = am_ad_slot($lang, $slot);
$url = '';
if (isset($adCfg['normal']['url']) && trim((string)$adCfg['normal']['url']) !== '') {
    $url = trim((string)$adCfg['normal']['url']);
} elseif (isset($adCfg['normal']['aparat_url']) && trim((string)$adCfg['normal']['aparat_url']) !== '') {
    $url = trim((string)$adCfg['normal']['aparat_url']);
}
$url = am_ad_target_url($url);

header('Location: ' . $url);
exit;
