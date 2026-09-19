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

// پاکسازی رسیدهای تأیید/رد شده‌ی قدیمی‌تر از یک هفته — حداکثر یک‌بار در روز
if (function_exists('am_payments_purge_resolved_old')) {
    $purgeFlag = defined('AM_ROOT') ? AM_ROOT . '/uploads/receipts/.purge_ts' : null;
    $shouldPurge = true;
    if ($purgeFlag && file_exists($purgeFlag)) {
        $last = (int)@file_get_contents($purgeFlag);
        if ($last > 0 && (time() - $last) < 86400) {
            $shouldPurge = false;
        }
    }
    if ($shouldPurge) {
        am_payments_purge_resolved_old(7);
        if ($purgeFlag) {
            $dir = dirname($purgeFlag);
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            @file_put_contents($purgeFlag, (string)time());
        }
    }
}
