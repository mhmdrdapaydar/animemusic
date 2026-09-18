<?php
require_once __DIR__ . '/includes/headless.php';

// تنها کاربران واردشده می‌توانند این صفحه را ببینند
am_require_login();

// دریافت کاربر جاری؛ وضعیت VIP در صورت انقضا همین‌جا اصلاح می‌شود (رفع باگ)
$user = am_current_user();

$isDarkMode = am_theme();
$db_users   = am_users_db();
$db_content = am_content_db();

// بررسی وضعیت VIP کاربر (موتور مرکزی همیشه حرف آخر را می‌زند)
$isVIP = ($user && $user['subscription_status'] === 'vip');

// دریافت لیست پخش کاربر (فقط برای کاربران VIP)
$user_playlists = [];
if ($isVIP) {
    $playlists = $db_users->prepare("
        SELECT p.*, COUNT(pc.content_id) as content_count 
        FROM playlists p 
        LEFT JOIN playlist_contents pc ON p.id = pc.playlist_id 
        WHERE p.user_id = ? 
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ");
    $playlists->execute([$_SESSION['user_id']]);
    $user_playlists = $playlists->fetchAll(PDO::FETCH_ASSOC);
}

// دریافت محتواهای مورد علاقه کاربر (فقط برای کاربران VIP)
$user_favorites = [];
if ($isVIP) {
    try {
        $favorites = $db_users->prepare("
            SELECT uf.content_id, uf.added_at 
            FROM user_favorites uf 
            WHERE uf.user_id = ? 
            ORDER BY uf.added_at DESC 
            LIMIT 10
        ");
        $favorites->execute([$_SESSION['user_id']]);
        $favorite_ids = $favorites->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($favorite_ids)) {
            $placeholders = implode(',', array_fill(0, count($favorite_ids), '?'));
            $content_ids = array_column($favorite_ids, 'content_id');
            
            $content_stmt = $db_content->prepare("
                SELECT ac.*, asr.title_fa, mt.name as music_type 
                FROM anime_contents ac 
                JOIN anime_series asr ON ac.anime_id = asr.id 
                JOIN music_types mt ON ac.music_type_id = mt.id 
                WHERE ac.id IN ($placeholders)
            ");
            $content_stmt->execute($content_ids);
            $user_favorites = $content_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $user_favorites = [];
        error_log("Error fetching favorites: " . $e->getMessage());
    }
}

// پردازش به‌روزرسانی پروفایل
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        
        if (empty($firstName) || empty($lastName)) {
            $message = 'لطفا نام و نام خانوادگی را وارد کنید';
            $message_type = 'error';
        } else {
            $stmt = $db_users->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
            if ($stmt->execute([$firstName, $lastName, $email, $_SESSION['user_id']])) {
                $_SESSION['first_name'] = $firstName;
                $_SESSION['last_name'] = $lastName;
                $message = 'پروفایل با موفقیت به‌روزرسانی شد';
                $message_type = 'success';
            } else {
                $message = 'خطا در به‌روزرسانی پروفایل';
                $message_type = 'error';
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $message = 'لطفا تمام فیلدهای رمز عبور را پر کنید';
            $message_type = 'error';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'رمز عبور جدید و تکرار آن مطابقت ندارند';
            $message_type = 'error';
        } elseif (strlen($newPassword) < 6) {
            $message = 'رمز عبور جدید باید حداقل 6 کاراکتر باشد';
            $message_type = 'error';
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $message = 'رمز عبور فعلی اشتباه است';
            $message_type = 'error';
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db_users->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashedPassword, $_SESSION['user_id']])) {
                $message = 'رمز عبور با موفقیت تغییر کرد';
                $message_type = 'success';
            } else {
                $message = 'خطا در تغییر رمز عبور';
                $message_type = 'error';
            }
        }
    } elseif (isset($_POST['create_playlist']) && $isVIP) {
        $playlistName = trim($_POST['playlist_name']);
        $playlistDescription = trim($_POST['playlist_description'] ?? '');
        $isPublic = isset($_POST['is_public']) ? 1 : 0;
        
        if (empty($playlistName)) {
            $message = 'لطفا نام لیست پخش را وارد کنید';
            $message_type = 'error';
        } else {
            $stmt = $db_users->prepare("INSERT INTO playlists (user_id, name, description, is_public) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$_SESSION['user_id'], $playlistName, $playlistDescription, $isPublic])) {
                $message = 'لیست پخش با موفقیت ایجاد شد';
                $message_type = 'success';
                // رفرش صفحه برای نمایش لیست جدید
                header("Location: profile.php");
                exit;
            } else {
                $message = 'خطا در ایجاد لیست پخش';
                $message_type = 'error';
            }
        }
    } elseif (isset($_POST['delete_playlist']) && $isVIP) {
        $playlistId = (int)$_POST['playlist_id'];
        
        // بررسی مالکیت پلی‌لیست
        $stmt = $db_users->prepare("SELECT user_id FROM playlists WHERE id = ?");
        $stmt->execute([$playlistId]);
        $playlist = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$playlist || $playlist['user_id'] != $_SESSION['user_id']) {
            $message = 'شما اجازه حذف این لیست پخش را ندارید';
            $message_type = 'error';
        } else {
            // حذف محتواهای پلی‌لیست
            $stmt = $db_users->prepare("DELETE FROM playlist_contents WHERE playlist_id = ?");
            $stmt->execute([$playlistId]);
            
            // حذف خود پلی‌لیست
            $stmt = $db_users->prepare("DELETE FROM playlists WHERE id = ?");
            if ($stmt->execute([$playlistId])) {
                $message = 'لیست پخش با موفقیت حذف شد';
                $message_type = 'success';
                // رفرش صفحه برای به‌روزرسانی لیست
                header("Location: profile.php");
                exit;
            } else {
                $message = 'خطا در حذف لیست پخش';
                $message_type = 'error';
            }
        }
    } elseif (isset($_POST['toggle_playlist_visibility']) && $isVIP) {
        $playlistId = (int)$_POST['playlist_id'];
        
        // بررسی مالکیت پلی‌لیست
        $stmt = $db_users->prepare("SELECT user_id, is_public FROM playlists WHERE id = ?");
        $stmt->execute([$playlistId]);
        $playlist = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$playlist || $playlist['user_id'] != $_SESSION['user_id']) {
            $message = 'شما اجازه تغییر این لیست پخش را ندارید';
            $message_type = 'error';
        } else {
            // تغییر وضعیت عمومی/خصوصی
            $newVisibility = $playlist['is_public'] ? 0 : 1;
            $stmt = $db_users->prepare("UPDATE playlists SET is_public = ? WHERE id = ?");
            if ($stmt->execute([$newVisibility, $playlistId])) {
                $message = 'وضعیت لیست پخش با موفقیت تغییر کرد';
                $message_type = 'success';
                // رفرش صفحه برای به‌روزرسانی لیست
                header("Location: profile.php");
                exit;
            } else {
                $message = 'خطا در تغییر وضعیت لیست پخش';
                $message_type = 'error';
            }
        }
    }
}

// تابع تبدیل تاریخ (نمایش شمسی)
function format_date($date) {
    return am_format_date($date);
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="<?= $isDarkMode ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل کاربری - رسانه من</title>
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
            --transition-speed: 0.3s;
        }
        
        [data-theme="light"] {
            --bg-primary: var(--bg-light);
            --bg-card: var(--card-bg-light);
            --text-primary: var(--text-light);
            --text-secondary: var(--text-gray-light);
            --border-color: var(--border-color-light);
            --shadow-color: rgba(0, 0, 0, 0.1);
        }
        
        [data-theme="dark"] {
            --bg-primary: var(--bg-dark);
            --bg-card: var(--card-bg-dark);
            --text-primary: var(--text-dark);
            --text-secondary: var(--text-gray-dark);
            --border-color: var(--border-color-dark);
            --shadow-color: rgba(0, 0, 0, 0.3);
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0;
            padding: 0;
            transition: background-color var(--transition-speed), color var(--transition-speed);
            padding-bottom: 90px;
        }
        
        .header {
            background: var(--bg-card);
            padding: 15px 20px;
            text-align: center;
            box-shadow: 0 4px 12px var(--shadow-color);
            margin-bottom: 30px;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header img {
            height: 50px;
        }
        
        .theme-toggle {
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 24px;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: background-color var(--transition-speed);
        }
        
        .theme-toggle:hover {
            background-color: rgba(0, 0, 0, 0.1);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .profile-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }
        
        @media (min-width: 992px) {
            .profile-container {
                grid-template-columns: 1fr 2fr;
            }
        }
        
        .sidebar {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: 0 4px 20px var(--shadow-color);
            border: 1px solid var(--border-color);
            height: fit-content;
            transition: all var(--transition-speed);
        }
        
        .sidebar:hover {
            box-shadow: 0 8px 25px var(--shadow-color);
        }
        
        .main-content {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: 0 4px 20px var(--shadow-color);
            border: 1px solid var(--border-color);
            transition: all var(--transition-speed);
        }
        
        .main-content:hover {
            box-shadow: 0 8px 25px var(--shadow-color);
        }
        
        .section-title {
            color: var(--primary-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
            font-size: 22px;
            font-weight: 700;
        }
        
        .user-info {
            text-align: center;
            margin-bottom: 25px;
            position: relative;
        }
        
        .user-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 40px;
            color: #000;
            font-weight: bold;
            transition: transform var(--transition-speed);
        }
        
        .user-avatar:hover {
            transform: scale(1.05);
        }
        
        .user-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .user-status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            transition: all var(--transition-speed);
        }
        
        .status-free {
            background: rgba(108, 117, 125, 0.2);
            color: #6c757d;
        }
        
        .status-vip {
            background: linear-gradient(135deg, #ffd700, #ff9800);
            color: #000;
        }
        
        .subscription-expired {
            margin-top: 10px;
            padding: 8px 12px;
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border-radius: var(--border-radius);
            font-size: 13px;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }
        
        .sidebar-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-nav li {
            margin-bottom: 10px;
        }
        
        .sidebar-nav a {
            display: block;
            padding: 12px 15px;
            background: rgba(0, 255, 106, 0.1);
            border-radius: var(--border-radius);
            color: var(--text-primary);
            text-decoration: none;
            transition: all var(--transition-speed);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .sidebar-nav a:hover {
            background: rgba(0, 255, 106, 0.2);
            transform: translateX(-5px);
        }
        
        .sidebar-nav a.active {
            background: var(--primary-color);
            color: #000;
        }
        
        .sidebar-nav a.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: rgba(108, 117, 125, 0.1);
        }
        
        .sidebar-nav a.disabled:hover {
            background: rgba(108, 117, 125, 0.1);
            transform: none;
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            background-color: var(--bg-card);
            color: var(--text-primary);
            font-family: 'Vazirmatn', sans-serif;
            font-size: 16px;
            transition: all var(--transition-speed);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 255, 106, 0.2);
        }
        
        .btn {
            padding: 12px 25px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: #000;
            border: none;
            border-radius: var(--border-radius);
            font-family: 'Vazirmatn', sans-serif;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all var(--transition-speed);
            box-shadow: 0 4px 12px rgba(0, 255, 106, 0.3);
        }
        
        .btn:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 255, 106, 0.4);
        }
        
        .btn-secondary {
            background: rgba(108, 117, 125, 0.2);
            color: var(--text-primary);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.2);
        }
        
        .btn-secondary:hover {
            background: rgba(108, 117, 125, 0.3);
        }
        
        .btn-danger {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.2);
        }
        
        .btn-danger:hover {
            background: rgba(220, 53, 69, 0.3);
        }
        
        .btn-disabled {
            background: rgba(108, 117, 125, 0.1);
            color: var(--text-secondary);
            cursor: not-allowed;
            box-shadow: none;
        }
        
        .btn-disabled:hover {
            background: rgba(108, 117, 125, 0.1);
            transform: none;
            box-shadow: none;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .content-card {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: 0 2px 10px var(--shadow-color);
            transition: all var(--transition-speed);
            border: 1px solid var(--border-color);
            position: relative;
        }
        
        .content-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px var(--shadow-color);
        }
        
        .content-card a {
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .content-card img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            transition: transform var(--transition-speed);
        }
        
        .content-card:hover img {
            transform: scale(1.05);
        }
        
        .content-info {
            padding: 12px;
        }
        
        .content-title {
            font-size: 14px;
            margin: 0 0 5px;
            color: var(--primary-color);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.4;
        }
        
        .content-type {
            font-size: 12px;
            color: var(--text-secondary);
            margin: 0;
        }
        
        .playlist-actions {
            position: absolute;
            top: 10px;
            left: 10px;
            display: flex;
            gap: 5px;
        }
        
        .playlist-action-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 14px;
            transition: all var(--transition-speed);
        }
        
        .playlist-action-btn:hover {
            background: rgba(0, 0, 0, 0.9);
            transform: scale(1.1);
        }
        
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeIn 0.5s ease;
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }
        
        .alert-error {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }
        
        .tab-content {
            display: none;
            animation: fadeIn 0.5s ease;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .password-toggle {
            position: absolute;
            left: 15px;
            top: 38px;
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 18px;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 25px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
            border: 2px solid var(--primary-color);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .modal-header h3 {
            margin: 0;
            color: var(--primary-color);
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            color: var(--text-secondary);
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .close-modal:hover {
            color: var(--accent-color);
        }
        
        .vip-only-message {
            text-align: center;
            padding: 40px 0;
            color: var(--text-secondary);
        }
        
        .vip-only-message i {
            font-size: 64px;
            display: block;
            margin-bottom: 20px;
            color: #ffd700;
        }
        
        .vip-only-message h3 {
            color: #ffd700;
            margin-bottom: 15px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
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
            box-shadow: 0 -2px 10px var(--shadow-color);
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
        }
        
        .navbar a.active {
            color: var(--primary-color);
        }
        
        .navbar a:hover {
            color: var(--primary-color);
            transform: translateY(-3px);
        }
        
        @media (max-width: 768px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                padding: 15px;
            }
            
            .content-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 15px;
            }
            
            .sidebar, .main-content {
                padding: 20px;
            }
            
            .section-title {
                font-size: 20px;
            }
            
            .user-avatar {
                width: 80px;
                height: 80px;
                font-size: 30px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="image.png" alt="لوگو رسانه من">
        <button class="theme-toggle" id="themeToggle">
            <i class="bi <?= $isDarkMode ? 'bi-sun' : 'bi-moon' ?>"></i>
        </button>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?>">
                <i class="bi <?= $message_type === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle' ?>"></i> 
                <?= $message ?>
            </div>
        <?php endif; ?>
        
        <div class="profile-container">
            <div class="sidebar">
                <div class="user-info">
                    <div class="user-avatar">
                        <?= substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1) ?>
                    </div>
                    <div class="user-name"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                    <div class="user-status <?= $user['subscription_status'] === 'vip' ? 'status-vip' : 'status-free' ?>">
                        <?= $user['subscription_status'] === 'vip' ? 'VIP' : 'عادی' ?>
                    </div>
                    <?php if ($user['subscription_status'] === 'vip' && $user['subscription_end_date']): ?>
                        <p style="margin-top: 10px; font-size: 14px; color: var(--text-secondary);">
                            اعتبار تا: <?= format_date($user['subscription_end_date']) ?>
                        </p>
                    <?php endif; ?>
                </div>
                
                <ul class="sidebar-nav">
                    <li><a href="#" class="active" onclick="switchTab('profile-tab')"><i class="bi bi-person"></i> پروفایل</a></li>
                    <?php if ($isVIP): ?>
                        <li><a href="#" onclick="switchTab('playlists-tab')"><i class="bi bi-music-note-list"></i> لیست‌های پخش</a></li>
                        <li><a href="#" onclick="switchTab('favorites-tab')"><i class="bi bi-heart"></i> مورد علاقه‌ها</a></li>
                    <?php else: ?>
                        <li><a href="vip.php" class="disabled"><i class="bi bi-music-note-list"></i> لیست‌های پخش (VIP)</a></li>
                        <li><a href="vip.php" class="disabled"><i class="bi bi-heart"></i> مورد علاقه‌ها (VIP)</a></li>
                    <?php endif; ?>
                    <li><a href="#" onclick="switchTab('security-tab')"><i class="bi bi-shield-lock"></i> امنیت</a></li>
                    <li><a href="vip.php"><i class="bi bi-star"></i> ارتقاء به VIP</a></li>
                    <li><a href="logout.php"><i class="bi bi-box-arrow-left"></i> خروج</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <!-- تب پروفایل -->
                <div id="profile-tab" class="tab-content active">
                    <h2 class="section-title">اطلاعات پروفایل</h2>
                    
                    <form method="post">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="form-group">
                            <label for="first_name">نام</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" 
                                   value="<?= htmlspecialchars($user['first_name']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">نام خانوادگی</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" 
                                   value="<?= htmlspecialchars($user['last_name']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="username">نام کاربری</label>
                            <input type="text" id="username" class="form-control" 
                                   value="<?= htmlspecialchars($user['username']) ?>" disabled>
                            <small style="color: var(--text-secondary);">نام کاربری قابل تغییر نیست</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">ایمیل</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                        </div>
                        
                        <button type="submit" class="btn">
                            <i class="bi bi-check-lg"></i> ذخیره تغییرات
                        </button>
                    </form>
                </div>
                
                <!-- تب لیست‌های پخش -->
                <div id="playlists-tab" class="tab-content">
                    <?php if ($isVIP): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h2 class="section-title">لیست‌های پخش من</h2>
                            <button class="btn" onclick="showCreatePlaylistModal()">
                                <i class="bi bi-plus-lg"></i> ایجاد لیست جدید
                            </button>
                        </div>
                        
                        <?php if (!empty($user_playlists)): ?>
                            <div class="content-grid">
                                <?php foreach ($user_playlists as $playlist): ?>
                                    <div class="content-card">
                                        <div class="playlist-actions">
                                            <!-- دکمه تغییر وضعیت عمومی/خصوصی -->
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="toggle_playlist_visibility" value="1">
                                                <input type="hidden" name="playlist_id" value="<?= $playlist['id'] ?>">
                                                <button type="submit" class="playlist-action-btn" title="<?= $playlist['is_public'] ? 'تبدیل به خصوصی' : 'تبدیل به عمومی' ?>">
                                                    <i class="bi <?= $playlist['is_public'] ? 'bi-lock' : 'bi-globe' ?>"></i>
                                                </button>
                                            </form>
                                            
                                            <!-- دکمه حذف لیست پخش -->
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="delete_playlist" value="1">
                                                <input type="hidden" name="playlist_id" value="<?= $playlist['id'] ?>">
                                                <button type="submit" class="playlist-action-btn" onclick="return confirm('آیا از حذف این لیست پخش مطمئن هستید؟')" title="حذف لیست پخش">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                        <a href="playlist.php?id=<?= $playlist['id'] ?>">
                                            <div style="background: linear-gradient(135deg, <?= $playlist['is_public'] ? '#00ff6a' : '#6c757d' ?>, <?= $playlist['is_public'] ? '#00ffc3' : '#adb5bd' ?>); height: 120px; display: flex; align-items: center; justify-content: center;">
                                                <i class="bi bi-music-note-list" style="font-size: 48px; color: #000;"></i>
                                            </div>
                                            <div class="content-info">
                                                <h3 class="content-title"><?= htmlspecialchars($playlist['name']) ?></h3>
                                                <p class="content-type"><?= $playlist['content_count'] ?> موزیک</p>
                                                <p class="content-type">
                                                    <i class="bi <?= $playlist['is_public'] ? 'bi-globe' : 'bi-lock' ?>"></i>
                                                    <?= $playlist['is_public'] ? 'عمومی' : 'خصوصی' ?>
                                                </p>
                                                <?php if (!empty($playlist['description'])): ?>
                                                    <p class="content-type"><?= htmlspecialchars($playlist['description']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p style="text-align: center; color: var(--text-secondary); padding: 40px 0;">
                                <i class="bi bi-music-note-list" style="font-size: 48px; display: block; margin-bottom: 15px;"></i>
                                هنوز لیست پخشی ایجاد نکرده‌اید.
                            </p>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="vip-only-message">
                            <i class="bi bi-star-fill"></i>
                            <h3>این بخش فقط برای کاربران VIP در دسترس است</h3>
                            <p>برای دسترسی به لیست‌های پخش، اشتراک VIP تهیه کنید.</p>
                            <a href="vip.php" class="btn" style="margin-top: 20px;">
                                <i class="bi bi-star"></i> ارتقاء به VIP
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- تب مورد علاقه‌ها -->
                <div id="favorites-tab" class="tab-content">
                    <?php if ($isVIP): ?>
                        <h2 class="section-title">موزیک‌های مورد علاقه</h2>
                        
                        <?php if (!empty($user_favorites)): ?>
                            <div class="content-grid">
                                <?php foreach ($user_favorites as $favorite): ?>
                                    <a href="content.php?id=<?= $favorite['id'] ?>" class="content-card">
                                        <img src="<?= htmlspecialchars($favorite['image_url'] ?? 'assets/image/placeholder.jpg') ?>" 
                                             alt="<?= htmlspecialchars($favorite['title']) ?>">
                                        <div class="content-info">
                                            <h3 class="content-title"><?= htmlspecialchars($favorite['title']) ?></h3>
                                            <p class="content-type"><?= htmlspecialchars($favorite['title_fa']) ?></p>
                                            <p class="content-type"><?= htmlspecialchars($favorite['music_type']) ?></p>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p style="text-align: center; color: var(--text-secondary); padding: 40px 0;">
                                <i class="bi bi-heart" style="font-size: 48px; display: block; margin-bottom: 15px;"></i>
                                هنوز موزیکی به مورد علاقه‌ها اضافه نکرده‌اید.
                            </p>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="vip-only-message">
                            <i class="bi bi-star-fill"></i>
                            <h3>این بخش فقط برای کاربران VIP در دسترس است</h3>
                            <p>برای دسترسی به مورد علاقه‌ها، اشترак VIP تهیه کنید.</p>
                            <a href="vip.php" class="btn" style="margin-top: 20px;">
                                <i class="bi bi-star"></i> ارتقاء به VIP
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- تب امنیت -->
                <div id="security-tab" class="tab-content">
                    <h2 class="section-title">تغییر رمز عبور</h2>
                    
                    <form method="post">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="form-group">
                            <label for="current_password">رمز عبور فعلی</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                            <button type="button" class="password-toggle" id="currentPasswordToggle">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">رمز عبور جدید</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                            <button type="button" class="password-toggle" id="newPasswordToggle">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">تکرار رمز عبور جدید</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                            <button type="button" class="password-toggle" id="confirmPasswordToggle">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        
                        <button type="submit" class="btn">
                            <i class="bi bi-key"></i> تغییر رمز عبور
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- مودال ایجاد لیست پخش جدید -->
    <?php if ($isVIP): ?>
    <div class="modal" id="create-playlist-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>ایجاد لیست پخش جدید</h3>
                <button class="close-modal" onclick="closeCreatePlaylistModal()">&times;</button>
            </div>
            <form method="post">
                <input type="hidden" name="create_playlist" value="1">
                
                <div class="form-group">
                    <label for="playlist_name">نام لیست پخش</label>
                    <input type="text" id="playlist_name" name="playlist_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="playlist_description">توضیحات (اختیاری)</label>
                    <textarea id="playlist_description" name="playlist_description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_public" value="1"> لیست پخش عمومی باشد
                    </label>
                    <small style="color: var(--text-secondary); display: block; margin-top: 5px;">
                        اگر این گزینه را انتخاب کنید، دیگران می‌توانند لیست پخش شما را ببینند.
                    </small>
                </div>
                
                <button type="submit" class="btn">
                    <i class="bi bi-check-lg"></i> ایجاد لیست پخش
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="navbar">
        <a href="index.php"><i class="bi bi-house"></i> خانه</a>
        <a href="categories.php"><i class="bi bi-grid-1x2-fill"></i> دسته‌ها</a>
        <a href="vip.php"><i class="bi bi-star"></i> VIP</a>
        <a href="profile.php" class="active"><i class="bi bi-person"></i> پروفایل</a>
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
        
        function switchTab(tabId) {
            // مخفی کردن همه تب‌ها
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // نمایش تب انتخاب شده
            document.getElementById(tabId).classList.add('active');
            
            // به‌روزرسانی لینک فعال در نوار کناری
            document.querySelectorAll('.sidebar-nav a').forEach(link => {
                link.classList.remove('active');
            });
            event.currentTarget.classList.add('active');
        }
        
        // نمایش/مخفی کردن رمز عبور
        function setupPasswordToggle(toggleId, inputId) {
            const toggle = document.getElementById(toggleId);
            const input = document.getElementById(inputId);
            
            if (toggle && input) {
                toggle.addEventListener('click', () => {
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    
                    // تغییر آیکون
                    toggle.innerHTML = type === 'password' ? 
                        '<i class="bi bi-eye"></i>' : 
                        '<i class="bi bi-eye-slash"></i>';
                });
            }
        }
        
        // راه‌اندازی نمایش/مخفی کردن رمز عبور برای همه فیلدها
        setupPasswordToggle('currentPasswordToggle', 'current_password');
        setupPasswordToggle('newPasswordToggle', 'new_password');
        setupPasswordToggle('confirmPasswordToggle', 'confirm_password');
        
        // مدیریت مودال ایجاد لیست پخش
        function showCreatePlaylistModal() {
            document.getElementById('create-playlist-modal').style.display = 'flex';
        }
        
        function closeCreatePlaylistModal() {
            document.getElementById('create-playlist-modal').style.display = 'none';
        }
        
        // بستن مودال با کلیک خارج از آن
        document.getElementById('create-playlist-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCreatePlaylistModal();
            }
        });
    </script>
</body>
</html>