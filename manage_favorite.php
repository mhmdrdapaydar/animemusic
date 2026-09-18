<?php
require_once __DIR__ . '/includes/headless.php';
header('Content-Type: application/json');

// بررسی اینکه کاربر لاگین کرده است
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'لطفاً ابتدا وارد حساب کاربری خود شوید']);
    exit;
}

// علاقه‌مندی‌ها فقط برای کاربران VIP (باید در همه‌جا یکسان اجرا شود)
$user = am_current_user();
if (!$user || $user['subscription_status'] !== 'vip') {
    echo json_encode(['success' => false, 'message' => 'مدیریت علاقه‌مندی‌ها مخصوص کاربران VIP است']);
    exit;
}

// بررسی پارامترهای ورودی
if (!isset($_POST['content_id']) || !isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'پارامترهای ورودی ناقص است']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$contentId = (int)$_POST['content_id'];
$action = $_POST['action'];

$db = am_users_db();

// بررسی وجود محتوا در دیتابیس محتوا
try {
    $contentDb = am_content_db();
    
    $stmt = $contentDb->prepare("SELECT id FROM anime_contents WHERE id = ?");
    $stmt->execute([$contentId]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'محتوا یافت نشد']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطا در بررسی محتوا']);
    exit;
}

// مدیریت علاقه‌مندی
try {
    if ($action === 'add') {
        // بررسی وجود قبلی در علاقه‌مندی‌ها
        $stmt = $db->prepare("SELECT * FROM user_favorites WHERE user_id = ? AND content_id = ?");
        $stmt->execute([$userId, $contentId]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'این محتوا قبلاً به علاقه‌مندی‌ها اضافه شده است']);
            exit;
        }
        
        // افزودن به علاقه‌مندی‌ها
        $stmt = $db->prepare("INSERT INTO user_favorites (user_id, content_id) VALUES (?, ?)");
        $stmt->execute([$userId, $contentId]);
        
        echo json_encode(['success' => true, 'message' => 'محتوا به علاقه‌مندی‌ها اضافه شد']);
        
    } elseif ($action === 'remove') {
        // حذف از علاقه‌مندی‌ها
        $stmt = $db->prepare("DELETE FROM user_favorites WHERE user_id = ? AND content_id = ?");
        $stmt->execute([$userId, $contentId]);
        
        echo json_encode(['success' => true, 'message' => 'محتوا از علاقه‌مندی‌ها حذف شد']);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'عمل نامعتبر']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطا در انجام عملیات: ' . $e->getMessage()]);
}
?>