<?php
/**
 * انیمه موزیک — بوت‌استرپ سبک برای هر صفحه (بدون تولید HTML)
 * ترتیب صحیح:
 *   1) session
 *   2) config + helpers (شامل db و vip)
 * صفحه‌هایی که خروجی HTML هم می‌خواهند از includes/head.php استفاده کنند.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vip.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/payments.php';
require_once __DIR__ . '/captcha.php';

// مهاجرت خودکار جدول‌های پرداخت و تیکت (اجرا در هر بارگذاری)
if (function_exists('am_payments_migrate')) {
    am_payments_migrate();
}
