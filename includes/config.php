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

// منطقه زمانی تهران — ملاک محاسبه انقضای اشتراک VIP همین است.
// اگر host ساعتِ دیگری داشته باشد، این تنظیم حساب تاریخ را یکسان می‌کند
// تا کاربر «امروز» زودتر یا دیرتر از انقضا، VIP نماند.
date_default_timezone_set('Asia/Tehran');

// حالت سخت‌گیرانه VIP:
// true  => هر جایی وضعیت کاربر خوانده شود، اگر تاریخ پایان گذشته باشد فوراً free می‌شود
// false => فقط نمایش تغییر می‌کند ولی دیتابیس دست نمی‌خورد (پیشنهاد نمی‌شود)
define('AM_STRICT_VIP', true);

// آدرس پایه سایت (برای سایت‌مپ و لینک‌های اشتراک‌گذاری)
define('AM_SITE_URL', 'https://anime-music.ct.ws');
