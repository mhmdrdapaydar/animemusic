<?php
require_once __DIR__ . '/includes/headless.php';

// دامنه از پیکربندی مرکزی
$base_url = defined('AM_SITE_URL') ? rtrim(AM_SITE_URL, '/') : 'https://anime-music.ct.ws';
$per_page = 1000;

// صفحات ایستای اصلی (آدرس داخلی بدون زبان)
$static_pages = array('index.php', 'categories.php', 'list.php', 'about.php');

try {
    $db = am_db_content();
} catch (PDOException $e) {
    $db = null;
}

// این صفحه می‌تواند در ۳ حالت عمل کند:
//   1) بدون پارامتر → فهرست سایت‌مپ‌ها (sitemapindex)
//   2) ?page=N      → سایت‌مپ فارسی (URLهای فعلی، بدون تغییر نسبت به قبل)
//   3) ?lang=xx&page=N → سایت‌مپ آن زبان (آدرس‌های زیرپوشه /xx/...)
$outLang = isset($_GET['lang']) ? (string)$_GET['lang'] : 'fa';

// اعتبارسنجی زبان
$lang_meta = am_languages();
if (!isset($lang_meta[$outLang])) {
    $outLang = 'fa';
}

// ===== حالت 1: ایندکس سایت‌مپ =====
if (!isset($_GET['page']) && !isset($_GET['lang'])) {
    if ($db) {
        $total = (int)$db->query("SELECT COUNT(*) FROM anime_contents")->fetchColumn();
    } else {
        $total = 0;
    }
    $total_pages = max(1, ceil($total / $per_page));

    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

    // فارسی: آدرس‌های فعلی (بدون تغییر نسبت به قبل — برای حفظ ایندکس گوگل)
    for ($i = 1; $i <= $total_pages; $i++) {
        echo '<sitemap>';
        echo '<loc>' . htmlspecialchars($base_url . '/sitemap.php?page=' . $i, ENT_XML1, 'UTF-8') . '</loc>';
        echo '<lastmod>' . date('c') . '</lastmod>';
        echo '</sitemap>';
    }

    // زبان‌های دیگر: هر زبان سایت‌مپ‌های جداگانه خودش را دارد
    foreach ($lang_meta as $code => $meta) {
        if ($code === 'fa') continue;
        for ($i = 1; $i <= $total_pages; $i++) {
            echo '<sitemap>';
            echo '<loc>' . htmlspecialchars($base_url . '/sitemap.php?lang=' . $code . '&page=' . $i, ENT_XML1, 'UTF-8') . '</loc>';
            echo '<lastmod>' . date('c') . '</lastmod>';
            echo '</sitemap>';
        }
    }

    echo '</sitemapindex>';
    exit;
}

// ===== حالت 2 و 3: سایت‌مپ صفحه‌بندی‌شده برای یک زبان =====
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

if ($db) {
    $stmt = $db->prepare("SELECT id, created_at FROM anime_contents ORDER BY id LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $rows = array();
}

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

// صفحات ایستا (فقط صفحه اول هر زبان)
if ($page === 1) {
    foreach ($static_pages as $p) {
        echo '<url>';
        echo '<loc>' . htmlspecialchars(am_abs_url($p, $outLang), ENT_XML1, 'UTF-8') . '</loc>';
        echo '<changefreq>weekly</changefreq>';
        echo '<priority>0.8</priority>';
        echo '</url>';
    }
}

// صفحات محتوا برای زبان انتخابی
foreach ($rows as $row) {
    $url = am_abs_url('content.php?id=' . $row['id'], $outLang);
    echo '<url>';
    echo '<loc>' . htmlspecialchars($url, ENT_XML1, 'UTF-8') . '</loc>';

    $raw_date = !empty($row['created_at']) ? $row['created_at'] : '';
    if (!empty($raw_date)) {
        $timestamp = is_numeric($raw_date) ? (int)$raw_date : strtotime($raw_date);
    } else {
        $timestamp = false;
    }
    $lastmod = ($timestamp !== false && $timestamp > 0) ? date('c', $timestamp) : date('c');

    echo '<lastmod>' . $lastmod . '</lastmod>';
    echo '</url>';
}

echo '</urlset>';
exit;
