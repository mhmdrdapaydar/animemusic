<?php
/**
 * انیمه موزیک — کپچای ریاضی ساده و امن (بدون وابستگی به GD)
 *
 * در هر بار نمایش فرم، یک سؤال ریاضی تصادفی تولید و پاسخ آن در session ذخیره
 * می‌شود. پاسخ تنها یک‌بار مصرف است و بعد از بررسی (موفق یا ناموفق) پاک می‌شود.
 * برای جلوگیری از خواندن خودکار ربات‌ها، اعداد در نمایش به ارقام محلی تبدیل
 * و با کمی جابه‌جایی بصری (CSS) نمایش داده می‌شوند.
 */

if (!defined('AM_CAPTCHA_LOADED')) {
    define('AM_CAPTCHA_LOADED', 1);

    /** تولید یک سؤال جدید کپچا و ذخیره در session */
    function am_captcha_generate() {
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        $op = (random_int(0, 1) === 1) ? '+' : '-';
        if ($op === '-' && $a < $b) {
            $tmp = $a; $a = $b; $b = $tmp;
        }
        $answer = ($op === '+') ? ($a + $b) : ($a - $b);
        $_SESSION['am_captcha'] = [
            'q' => $a . ' ' . $op . ' ' . $b,
            'a' => (string)$answer,
            't' => time(),
        ];
        return $_SESSION['am_captcha'];
    }

    /**
     * اطمینان از وجود یک سؤال فعال (در بارگذاری معمولی) یا ساخت سؤال جدید
     * وقتی کاربر دکمه «تعویض» را می‌زند.
     */
    function am_captcha_start() {
        if (!isset($_SESSION['am_captcha']) || (($_GET['new_captcha'] ?? '') === '1')) {
            am_captcha_generate();
        }
    }

    /** سؤال کپچای ذخیره‌شده به شکل خام (ارقام انگلیسی) */
    function am_captcha_question() {
        if (!isset($_SESSION['am_captcha'])) {
            am_captcha_generate();
        }
        return $_SESSION['am_captcha']['q'];
    }

    /** نمایش سؤال با ارقام محلی زبان جاری (برای HTML) */
    function am_captcha_render() {
        $q = am_captcha_question();
        $lang = function_exists('am_current_lang') ? am_current_lang() : 'fa';
        if ($lang === 'fa' || $lang === 'ar') {
            $q = function_exists('am_fa_num') ? am_fa_num($q) : $q;
        }
        $parts = explode(' ', $q);
        $op = $parts[1] ?? '+';
        return '<span class="captcha-num">' . am_e($parts[0] ?? '') . '</span>'
             . '<span class="captcha-op">' . am_e($op) . '</span>'
             . '<span class="captcha-num">' . am_e($parts[2] ?? '') . '</span>'
             . '<span class="captcha-eq">= ?</span>';
    }

    /** بررسی پاسخ کاربر (یک‌بارمصرف) */
    function am_captcha_check($input) {
        if (!isset($_SESSION['am_captcha'])) {
            return false;
        }
        // نرمال‌سازی ارقام فارسی/عربی به انگلیسی
        $input = str_replace(
            ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'],
            ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'],
            trim((string)$input)
        );
        $stored = $_SESSION['am_captcha'];
        unset($_SESSION['am_captcha']); // یک‌بارمصرف
        return ($input === (string)$stored['a']);
    }
}
