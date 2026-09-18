<?php
require_once __DIR__ . '/includes/headless.php';

// تنظیمات (دامنه از فایل پیکربندی مرکزی خوانده می‌شود)
$base_url = defined('AM_SITE_URL') ? AM_SITE_URL : 'https://anime-music.ct.ws';
$per_page = 1000; // تعداد پست در هر صفحه سایت‌مپ

// اتصال به دیتابیس محتوا
try {
    $db = am_content_db();
} catch (PDOException $e) {
    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
    exit;
}

// دریافت تعداد کل محتواها
try {
    $stmt = $db->query("SELECT COUNT(*) FROM anime_contents");
    $total = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    $total = 0;
}

// اگر پارامتر page وجود داشت، خروجی سایتمپ صفحه مربوطه را تولید کن
if (isset($_GET['page'])) {
    $page = max(1, (int)$_GET['page']);
    $offset = ($page - 1) * $per_page;

    // دریافت شناسه‌ها و تاریخ ایجاد (بدون نیاز به ستون updated_at)
    try {
        $stmt = $db->prepare("SELECT id, created_at FROM anime_contents ORDER BY id LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $rows = [];
    }

    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

    foreach ($rows as $row) {
        $url = $base_url . '/content.php?id=' . $row['id'];
        echo '<url>';
        echo '<loc>' . htmlspecialchars($url, ENT_XML1, 'UTF-8') . '</loc>';

        // دریافت تاریخ خام
        $raw_date = !empty($row['created_at']) ? $row['created_at'] : '';

        // تبدیل به timestamp
        if (!empty($raw_date)) {
            if (is_numeric($raw_date)) {
                $timestamp = (int)$raw_date;
            } else {
                $timestamp = strtotime($raw_date);
            }
        } else {
            $timestamp = false;
        }

        // اگر تبدیل موفق بود، از آن استفاده کن وگرنه تاریخ فعلی
        if ($timestamp !== false && $timestamp > 0) {
            $lastmod = date('c', $timestamp); // فرمت ISO 8601
        } else {
            $lastmod = date('c');
        }

        echo '<lastmod>' . $lastmod . '</lastmod>';
        echo '</url>';
    }

    echo '</urlset>';
    exit;
}

// در غیر این صورت، سایت‌مپ ایندکس را تولید کن
$total_pages = ceil($total / $per_page);

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

for ($i = 1; $i <= $total_pages; $i++) {
    echo '<sitemap>';
    echo '<loc>' . $base_url . '/sitemap.php?page=' . $i . '</loc>';
    echo '<lastmod>' . date('c') . '</lastmod>';
    echo '</sitemap>';
}

echo '</sitemapindex>';
?>