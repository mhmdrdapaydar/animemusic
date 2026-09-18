<?php
/**
 * انیمه موزیک — سر صفحه (head) مشترک
 * پارامترها:
 *   $page_title : عنوان صفحه
 *   $extra_head : HTML اضافی داخل head (اختیاری)
 *
 * نکته: اگر این فایل به تنهایی include شود، خودش کتابخانه‌ها را نیز بارگذاری می‌کند.
 */
if (file_exists(__DIR__ . '/headless.php')) {
    require_once __DIR__ . '/headless.php';
}

if (!isset($page_title)) {
    $page_title = 'انیمه موزیک';
}
if (!isset($extra_head)) {
    $extra_head = '';
}
$am_theme = am_theme();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="<?= $am_theme ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= am_e($page_title) ?></title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn.min.css" rel="stylesheet">
    <?= $extra_head ?>
</head>
