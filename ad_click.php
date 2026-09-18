<?php
/**
 * انیمه موزیک — ثبت کلیک روی تبلیغ و هدایت به مقصد
 * آدرس: /ad_click.php?lang=xx (اختیاری)
 * کلیک روی دکمه «ادامه مطلب» تبلیغ از content.php به اینجا می‌آید.
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

// ثبت کلیک در آمار (تفکیک‌شده بر اساس زبان)
am_ad_click_log($lang);

// مقصد تبلیغ: اول url، بعد aparat_url، وگرنه خانه
$ad  = am_ad_for_lang($lang);
$url = '';
if (isset($ad['url']) && trim((string)$ad['url']) !== '') {
    $url = trim((string)$ad['url']);
} elseif (isset($ad['aparat_url']) && trim((string)$ad['aparat_url']) !== '') {
    $url = trim((string)$ad['aparat_url']);
}

if ($url === '' || $url === '#') {
    $url = defined('AM_SITE_URL') ? rtrim(AM_SITE_URL, '/') . '/' : '/';
}

// اطمینان از داشتن پروتکل
if (!preg_match('#^[a-z][a-z0-9+.\-]*://#i', $url)) {
    $url = (strpos($url, '/') === 0 ? (defined('AM_SITE_URL') ? rtrim(AM_SITE_URL, '/') . $url : '') : 'http://' . ltrim($url, '/'));
}

header('Location: ' . $url);
exit;
