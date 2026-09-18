<?php
require_once __DIR__ . '/../includes/headless.php';
am_no_cache();

// بررسی وضعیت تم کاربر
$isDarkMode = am_theme();

// اتصال به دیتابیس محتوا
$db_content = am_content_db();

// تابع نرمال‌سازی برای حل مشکلات رایج کاراکترهای فارسی
function normalizePersian($string) {
    $replacements = [
        'ي' => 'ی', // ي عربی
        'ك' => 'ک', // ك عربی
        'ٰ' => '', // حذف علامت تنوین
        'أ' => 'ا',
        'إ' => 'ا',
        'ة' => 'ه',
        'ۀ' => 'ه',
        'ؤ' => 'و',
        // حذف اعراب (کسره، ضمه، فتحه، تشدید) و دیگر علائم
        "\u{064D}" => '', // کسره
        "\u{064C}" => '', // ضمه
        "\u{064B}" => '', // فتحه
        "\u{0650}" => '', // کسره
        "\u{0651}" => '', // تشدید
    ];
    
    $normalized = strtr($string, $replacements);
    
    // حذف کاراکترهای کنترل و فاصله‌های اضافه
    $normalized = preg_replace('/\p{C}/u', '', $normalized);
    $normalized = preg_replace('/\s+/u', ' ', $normalized);
    $normalized = trim($normalized);
    
    return $normalized;
}

$search = $_GET['q'] ?? '';
$typeFilter = $_GET['type'] ?? '';
$singerFilter = $_GET['singer'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 12;
$results = [];
$totalResults = 0;
$singers = [];

// دریافت لیست خوانندگان برای فیلتر
$singerStmt = $db_content->prepare("SELECT id, name FROM singers ORDER BY name");
$singerStmt->execute();
$allSingers = $singerStmt->fetchAll(PDO::FETCH_ASSOC);

if ($search !== '') {
    // نرمال‌سازی عبارت جستجو
    $normalizedSearch = normalizePersian($search);
    
    // کوئری اصلی برای جستجو
    $query = "
        SELECT 
            ac.*, 
            asr.title_fa, 
            asr.title_en, 
            mt.name as music_type,
            GROUP_CONCAT(s.name, ', ') as singers
        FROM anime_contents ac 
        JOIN anime_series asr ON ac.anime_id = asr.id 
        JOIN music_types mt ON ac.music_type_id = mt.id
        LEFT JOIN content_singers cs ON ac.id = cs.content_id
        LEFT JOIN singers s ON cs.singer_id = s.id
        WHERE (
            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(ac.title,
                'ي', 'ی'),
                'ك', 'ک'),
                'ٰ', ''),
                'أ', 'ا'),
                'إ', 'ا'),
                'ة', 'ه'),
                'ۀ', 'ه'),
                'ؤ', 'و') LIKE :search
            OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(asr.title_fa,
                'ي', 'ی'),
                'ك', 'ک'),
                'ٰ', ''),
                'أ', 'ا'),
                'إ', 'ا'),
                'ة', 'ه'),
                'ۀ', 'ه'),
                'ؤ', 'و') LIKE :search
            OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(asr.title_en,
                'ي', 'ی'),
                'ك', 'ک'),
                'ٰ', ''),
                'أ', 'ا'),
                'إ', 'ا'),
                'ة', 'ه'),
                'ۀ', 'ه'),
                'ؤ', 'و') LIKE :search
            OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(s.name,
                'ي', 'ی'),
                'ك', 'ک'),
                'ٰ', ''),
                'أ', 'ا'),
                'إ', 'ا'),
                'ة', 'ه'),
                'ۀ', 'ه'),
                'ؤ', 'و') LIKE :search
        )
    ";
    
    $params = [':search' => "%$normalizedSearch%"];
    $countParams = [':search' => "%$normalizedSearch%"];

    if (!empty($typeFilter)) {
        $query .= " AND mt.name = :type";
        $params[':type'] = $typeFilter;
        $countParams[':type'] = $typeFilter;
    }
    
    if (!empty($singerFilter)) {
        $query .= " AND s.id = :singer";
        $params[':singer'] = $singerFilter;
        $countParams[':singer'] = $singerFilter;
    }

    $query .= " GROUP BY ac.id";
    
    // کوئری برای شمارش کل نتایج
    $countQuery = "SELECT COUNT(DISTINCT ac.id) FROM anime_contents ac 
                   JOIN anime_series asr ON ac.anime_id = asr.id 
                   JOIN music_types mt ON ac.music_type_id = mt.id
                   LEFT JOIN content_singers cs ON ac.id = cs.content_id
                   LEFT JOIN singers s ON cs.singer_id = s.id
                   WHERE (
                       REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(ac.title,
                           'ي', 'ی'),
                           'ك', 'ک'),
                           'ٰ', ''),
                           'أ', 'ا'),
                           'إ', 'ا'),
                           'ة', 'ه'),
                           'ۀ', 'ه'),
                           'ؤ', 'و') LIKE :search
                       OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(asr.title_fa,
                           'ي', 'ی'),
                           'ك', 'ک'),
                           'ٰ', ''),
                           'أ', 'ا'),
                           'إ', 'ا'),
                           'ة', 'ه'),
                           'ۀ', 'ه'),
                           'ؤ', 'و') LIKE :search
                       OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(asr.title_en,
                           'ي', 'ی'),
                           'ك', 'ک'),
                           'ٰ', ''),
                           'أ', 'ا'),
                           'إ', 'ا'),
                           'ة', 'ه'),
                           'ۀ', 'ه'),
                           'ؤ', 'و') LIKE :search
                       OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(s.name,
                           'ي', 'ی'),
                           'ك', 'ک'),
                           'ٰ', ''),
                           'أ', 'ا'),
                           'إ', 'ا'),
                           'ة', 'ه'),
                           'ۀ', 'ه'),
                           'ؤ', 'و') LIKE :search
                   )";
    
    if (!empty($typeFilter)) {
        $countQuery .= " AND mt.name = :type";
    }
    
    if (!empty($singerFilter)) {
        $countQuery .= " AND s.id = :singer";
    }

    // اجرای کوئری شمارش
    $countStmt = $db_content->prepare($countQuery);
    foreach ($countParams as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalResults = $countStmt->fetchColumn();

    // مرتب‌سازی و صفحه‌بندی
    $query .= " ORDER BY ac.view_count DESC, ac.created_at DESC LIMIT :limit OFFSET :offset";
    $params[':limit'] = $perPage;
    $params[':offset'] = ($page - 1) * $perPage;

    // اجرای کوئری اصلی
    $stmt = $db_content->prepare($query);
    
    // بایند کردن پارامترها با نوع صحیح
    foreach ($params as $key => $value) {
        $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $paramType);
    }
    
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// محاسبه تعداد صفحات
$totalPages = ceil($totalResults / $perPage);

// گزینه‌های نوع محتوا
$contentTypes = [
    '' => am_t('all_types'),
    'Opening' => am_t('opening_type'),
    'Ending' => am_t('ending_type'),
    'OST' => am_t('ost_type'),
    'Concert' => am_t('concert_type'),
    'Character Song' => am_t('char_song_type')
];
?>
<!DOCTYPE html>
<html lang="<?= am_e(am_lang()) ?>" dir="<?= am_e(am_lang_dir()) ?>" data-theme="<?= $isDarkMode ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <title><?= am_te('search') ?> | <?= am_te('site_name') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= am_lang_base_tag() ?>
    <?= am_hreflang_links('search/search.php') ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn.min.css" rel="stylesheet">
<style>
        :root {
            --primary-color: #00ff6a;
            --secondary-color: #00ffc3;
            --accent-color: #ff00aa;
            --bg-dark: #0a0a0a;
            --bg-light: #f5f5f5;
            --card-bg-dark: #1a1a1a;
            --card-bg-light: #ffffff;
            --text-dark: #eee;
            --text-light: #333;
            --text-gray-dark: #bbb;
            --text-gray-light: #777;
            --border-radius: 14px;
            --border-color-dark: #333;
            --border-color-light: #e0e0e0;
        }
        
        [data-theme="light"] {
            --bg-primary: var(--bg-light);
            --bg-card: var(--card-bg-light);
            --text-primary: var(--text-light);
            --text-secondary: var(--text-gray-light);
            --border-color: var(--border-color-light);
        }
        
        [data-theme="dark"] {
            --bg-primary: var(--bg-dark);
            --bg-card: var(--card-bg-dark);
            --text-primary: var(--text-dark);
            --text-secondary: var(--text-gray-dark);
            --border-color: var(--border-color-dark);
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            margin: 0;
            font-family: 'Vazirmatn', sans-serif;
            padding-bottom: 90px;
            transition: background-color 0.3s, color 0.3s;
            min-height: 100vh;
        }
        
        .header {
            background: var(--bg-card);
            text-align: center;
            padding: 12px 0 4px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .header img {
            height: 48px;
            max-width: 160px;
            object-fit: contain;
        }
        
        .header-buttons {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .theme-toggle {
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 24px;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: background-color 0.3s;
        }
        
        .theme-toggle:hover {
            background-color: rgba(0, 0, 0, 0.1);
        }
        
        .search-container {
            padding: 20px;
            background-color: var(--bg-card);
            margin: 15px;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-color);
        }
        
        .search-form {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        @media (min-width: 768px) {
            .search-form {
                grid-template-columns: 1fr auto auto auto;
            }
        }
        
        .search-input {
            padding: 12px 15px;
            border-radius: 30px;
            border: 1px solid var(--border-color);
            background-color: var(--bg-card);
            color: var(--text-primary);
            font-family: 'Vazirmatn', sans-serif;
            outline: none;
            transition: border-color 0.3s;
            width: 100%;
        }
        
        .search-input:focus {
            border-color: var(--primary-color);
        }
        
        .search-select {
            padding: 12px 15px;
            border-radius: 30px;
            border: 1px solid var(--border-color);
            background-color: var(--bg-card);
            color: var(--text-primary);
            font-family: 'Vazirmatn', sans-serif;
            outline: none;
            cursor: pointer;
            width: 100%;
        }
        
        .search-button {
            padding: 12px 25px;
            background: var(--primary-color);
            border: none;
            border-radius: 30px;
            color: #000;
            font-family: 'Vazirmatn', sans-serif;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
            width: 100%;
        }
        
        .search-button:hover {
            background: #00cc55;
        }
        
        .search-summary {
            text-align: center;
            color: var(--text-secondary);
            margin: 20px 0;
            padding: 0 15px;
        }
        
        .results {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 20px;
            padding: 20px;
            width: 100%;
        }
        
        .card-wrapper {
            position: relative;
            width: 100%;
        }
        
        .badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background-color: var(--primary-color);
            color: #000;
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: bold;
            z-index: 2;
        }
        
        .singer-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            background-color: var(--secondary-color);
            color: #000;
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: bold;
            z-index: 2;
        }
        
        .content-card {
            background-color: var(--bg-card);
            border-radius: var(--border-radius);
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
            border: 1px solid var(--border-color);
        }
        
        .content-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 255, 100, 0.2);
        }
        
        .card-image {
            width: 100%;
            height: 0;
            padding-bottom: 100%; /* نسبت تصویر مربعی */
            position: relative;
            overflow: hidden;
        }
        
        .card-image img {
            position: absolute;
            width: 100%;
            height: 100%;
            object-fit: cover;
            top: 0;
            left: 0;
        }
        
        .info {
            padding: 12px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .info h3 {
            font-size: 14px;
            margin: 0 0 5px;
            color: var(--primary-color);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.4;
            min-height: 40px;
        }
        
        .info p {
            margin: 0;
            font-size: 12px;
            color: var(--text-secondary);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin: 30px 0;
            flex-wrap: wrap;
            padding: 0 15px;
        }
        
        .pagination a, .pagination span {
            padding: 8px 12px;
            border-radius: 8px;
            text-decoration: none;
            color: var(--text-primary);
            background: var(--bg-card);
            transition: all 0.3s;
            font-size: 14px;
            border: 1px solid var(--border-color);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
        }
        
        .pagination a:hover {
            background: var(--primary-color);
            color: #000;
        }
        
        .pagination .current {
            background: var(--primary-color);
            color: #000;
            font-weight: bold;
        }
        
        .pagination .disabled {
            opacity: 0.5;
            pointer-events: none;
        }
        
        .no-results {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
            grid-column: 1 / -1;
            width: 100%;
        }
        
        .no-results i {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
            color: var(--text-secondary);
        }
        
        .navbar {
            position: fixed;
            bottom: 0;
            width: 100%;
            background-color: var(--bg-card);
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            text-align: center;
            padding: 12px 0 10px;
            border-top: 1px solid var(--border-color);
            z-index: 999;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .navbar a {
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 13px;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: 0.3s;
        }
        
        .navbar a i {
            font-size: 22px;
            margin-bottom: 2px;
            transition: 0.3s;
        }
        
        .navbar a.active {
            color: var(--primary-color);
        }
        
        .navbar a:hover {
            color: var(--primary-color);
            transform: translateY(-3px);
        }
        
        @media (max-width: 600px) {
            .results {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 15px;
                padding: 15px;
            }
            
            .search-container {
                margin: 10px;
                padding: 15px;
            }
            
            .search-form {
                gap: 10px;
            }
            
            .search-input, .search-select, .search-button {
                padding: 10px 12px;
                font-size: 14px;
            }
            
            .pagination {
                gap: 5px;
            }
            
            .pagination a, .pagination span {
                padding: 6px 10px;
                font-size: 12px;
                min-width: 35px;
            }
        }

        @media (max-width: 400px) {
            .results {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
                gap: 12px;
                padding: 12px;
            }
            
            .info h3 {
                font-size: 13px;
            }
            
            .info p {
                font-size: 11px;
            }
        }
    </style>
    <link rel="icon" type="image/x-icon" href="/favicon.ico" />
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png" />
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" />
    <link rel="stylesheet" href="/assets/site.css?v=4" />
    <script defer src="/assets/site.js?v=1"></script>
</head>
<body>
    <div class="header">
        <img src="/image.png" alt="لوگو رسانه">
        <div class="header-buttons">
            <button class="theme-toggle" id="themeToggle">
                <i class="bi <?= $isDarkMode ? 'bi-sun' : 'bi-moon' ?>"></i>
            </button>
        </div>
    </div>

    <?= am_lang_switcher_flags('search/search.php') ?>

    <div class="search-container">
        <form method="get" class="search-form">
            <input type="text" name="q" class="search-input" value="<?= htmlspecialchars($search) ?>" placeholder="<?= am_te('search_ph') ?>">
            
            <select name="type" class="search-select">
                <?php foreach ($contentTypes as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $typeFilter === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            
            <select name="singer" class="search-select">
                <option value=""><?= am_te('all_singers') ?></option>
                <?php foreach ($allSingers as $singer): ?>
                    <option value="<?= $singer['id'] ?>" <?= $singerFilter == $singer['id'] ? 'selected' : '' ?>><?= htmlspecialchars($singer['name']) ?></option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit" class="search-button">
                <i class="bi bi-search"></i> <?= am_te('search') ?>
            </button>
        </form>
    </div>

    <?php if ($search !== ''): ?>
        <div class="search-summary">
            <?php if ($totalResults > 0): ?>
                <p><?= am_te('showing') ?> <strong><?= ($page - 1) * $perPage + 1 ?> <?= am_te('to_word') ?> <?= min($page * $perPage, $totalResults) ?></strong> <?= am_te('of_word') ?> <strong><?= $totalResults ?></strong> <?= am_te('result_word') ?> <?= am_te('for_word') ?> "<strong><?= htmlspecialchars($search) ?></strong>"</p>
            <?php else: ?>
                <p><?= am_te('no_results_for') ?> "<strong><?= htmlspecialchars($search) ?></strong>"</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="results">
        <?php if ($search !== '' && empty($results)): ?>
            <div class="no-results">
                <i class="bi bi-search"></i>
                <h3><?= am_te('not_found') ?></h3>
                <p><?= am_te('try_other_search') ?></p>
            </div>
        <?php endif; ?>
        
        <?php foreach ($results as $item): ?>
            <div class="card-wrapper">
                <span class="badge"><?= htmlspecialchars($item['music_type']) ?></span>
                <?php if (!empty($item['singers'])): ?>
                    <span class="singer-badge" title="<?= htmlspecialchars($item['singers']) ?>">
                        <i class="bi bi-person-vocal"></i>
                    </span>
                <?php endif; ?>
                
                <a href="<?= am_lang_url('content.php?id=' . $item['id']) ?>" class="content-card">
                    <div class="card-image">
                        <img src="<?= htmlspecialchars($item['image_url'] ?? '/assets/image/placeholder.jpg') ?>" alt="<?= am_e($item['title']) ?>" loading="lazy">
                    </div>
                    <div class="info">
                        <h3><?= htmlspecialchars($item['title']) ?></h3>
                        <p><?= am_e(am_lang_content_title($item['title_en'], $item['title_fa'])) ?></p>
                        <?php if (!empty($item['singers'])): ?>
                            <p title="<?= htmlspecialchars($item['singers']) ?>">
                                <i class="bi bi-person"></i> 
                                <?= mb_strlen($item['singers']) > 20 ? mb_substr($item['singers'], 0, 20) . '...' : $item['singers'] ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalResults > 0): ?>
        <div class="pagination">
            <!-- دکمه صفحه قبل -->
            <?php if ($page > 1): ?>
                <a href="<?= am_lang_url('search/search.php?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">
                    <i class="bi bi-chevron-right"></i> قبلی
                </a>
            <?php else: ?>
                <span class="disabled"><i class="bi bi-chevron-right"></i> قبلی</span>
            <?php endif; ?>

            <!-- نمایش صفحات مجاور -->
            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            
            if ($startPage > 1) {
                echo '<a href="' . am_e(am_lang_url('search/search.php?' . http_build_query(array_merge($_GET, ['page' => 1])))) . '">1</a>';
                if ($startPage > 2) echo '<span>...</span>';
            }
            
            for ($i = $startPage; $i <= $endPage; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= am_lang_url('search/search.php?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor;
            
            if ($endPage < $totalPages) {
                if ($endPage < $totalPages - 1) echo '<span>...</span>';
                echo '<a href="' . am_e(am_lang_url('search/search.php?' . http_build_query(array_merge($_GET, ['page' => $totalPages])))) . '">' . $totalPages . '</a>';
            }
            ?>

            <!-- دکمه صفحه بعد -->
            <?php if ($page < $totalPages): ?>
                <a href="<?= am_lang_url('search/search.php?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">
                    بعدی <i class="bi bi-chevron-left"></i>
                </a>
            <?php else: ?>
                <span class="disabled">بعدی <i class="bi bi-chevron-left"></i></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="navbar">
        <a href="<?= am_lang_url('index.php') ?>" class="nav-item"><i class="bi bi-house-door-fill"></i> <?= am_te('home') ?></a>
        <a href="<?= am_lang_url('categories.php') ?>" class="nav-item"><i class="bi bi-grid-1x2-fill"></i> <?= am_te('categories') ?></a>
        <a href="<?= am_lang_url('search/search.php') ?>" class="nav-item active"><i class="bi bi-search"></i> <?= am_te('search') ?></a>
        <a href="<?= am_lang_url('about.php') ?>" class="nav-item"><i class="bi bi-info-circle"></i> <?= am_te('about') ?></a>
    </div>

    <script>
        // سیستم تغییر تم
        const themeToggle = document.getElementById('themeToggle');
        const htmlElement = document.documentElement;
        
        themeToggle.addEventListener('click', () => {
            const isDark = htmlElement.getAttribute('data-theme') === 'dark';
            const newTheme = isDark ? 'light' : 'dark';
            
            htmlElement.setAttribute('data-theme', newTheme);
            themeToggle.innerHTML = `<i class="bi ${newTheme === 'dark' ? 'bi-sun' : 'bi-moon'}"></i>`;
            
            // ذخیره تنظیمات در کوکی به مدت 30 روز
            document.cookie = `dark_mode=${newTheme === 'dark'}; max-age=${30 * 24 * 60 * 60}; path=/`;
        });
    </script>
</body>
</html>