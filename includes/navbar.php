<?php
/**
 * انیمه موزیک — نوار ناوبری پایین (کامپوننت مشترک)
 * پارامترها (اختیاری):
 *   $nav_active : نام آیتم فعال
 *   $nav_base   : پیشوند مسیرها برای صفحات داخل زیرپوشه ها (مثلا '../')
 */
if (!isset($nav_base)) {
    $nav_base = '';
}
if (!isset($nav_active)) {
    $nav_active = '';
}
$nav_items = [
    'index'      => ['خانه', $nav_base . 'index.php', 'bi-house-door-fill'],
    'categories' => ['دسته‌ها', $nav_base . 'categories.php', 'bi-grid-1x2-fill'],
    'search'     => ['جستجو', $nav_base . 'search/search.php', 'bi-search'],
    'about'      => ['درباره ما', $nav_base . 'about.php', 'bi-info-circle'],
];
if (!array_key_exists($nav_active, $nav_items)) {
    $nav_active = '';
}
?>
<div class="navbar">
    <?php foreach ($nav_items as $key => $item): ?>
        <a href="<?= am_e($item[1]) ?>" class="nav-item <?= $key === $nav_active ? 'active' : '' ?>">
            <i class="bi <?= $item[2] ?>"></i><?= am_e($item[0]) ?>
        </a>
    <?php endforeach; ?>
</div>
