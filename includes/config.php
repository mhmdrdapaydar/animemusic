<?php
/**
 * انیمه موزیک — فایل پیکربندی مرکزی
 * نسخه بهبودیافته: تمام مسیرها و تنظیمات مهم سایت در یک جا
 */

// مسیر ریشه پروژه
define('AM_ROOT', __DIR__ . '/..');

// مسیر پایگاه‌های داده
define('AM_DB_PATH', AM_ROOT . '/db');
define('AM_DB_USERS', AM_DB_PATH . '/users.db');
define('AM_DB_CONTENT', AM_DB_PATH . '/content.db');

// اطلاعات فایل‌های آمار بازدید
define('AM_VISITS_FILE', AM_ROOT . '/visits.json');
define('AM_VISITS_ADS_FILE', AM_ROOT . '/visits_ads.json');
define('AM_ADS_FILE', AM_ROOT . '/ads.json');

// تاریخ نمایش داده نمی‌شود مگر آنکه سیستم ساعت درست تنظیم باشد؛
// پیش‌فرض منطقه زمانی تهران
date_default_timezone_set('Asia/Tehran');

// آدرس پایه سایت (برای سایت‌مپ و لینک‌های اشتراک‌گذاری)
define('AM_SITE_URL', 'https://anime-music.ct.ws');
