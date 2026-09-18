<?php
$dataFile = __DIR__ . '/visits_ads.json';

// خواندن داده‌های موجود
$data = [];
if (file_exists($dataFile)) {
    $json = file_get_contents($dataFile);
    $data = json_decode($json, true) ?: [];
}

// تاریخ‌های مورد نیاز
$today = date('Y-m-d');
$thisWeek = date('Y-W');
$thisMonth = date('Y-m');

// مقداردهی اولیه اگر وجود نداشته باشد
if (!isset($data['total'])) $data['total'] = 0;
if (!isset($data['daily'])) $data['daily'] = [];
if (!isset($data['weekly'])) $data['weekly'] = [];
if (!isset($data['monthly'])) $data['monthly'] = [];

// افزایش آمار
$data['total']++;
$data['daily'][$today] = ($data['daily'][$today] ?? 0) + 1;
$data['weekly'][$thisWeek] = ($data['weekly'][$thisWeek] ?? 0) + 1;
$data['monthly'][$thisMonth] = ($data['monthly'][$thisMonth] ?? 0) + 1;

// ذخیره داده‌ها
file_put_contents($dataFile, json_encode($data));
?>