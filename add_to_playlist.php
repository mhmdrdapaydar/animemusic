<?php
require_once __DIR__ . '/includes/headless.php';
header('Content-Type: application/json');

// بررسی اینکه کاربر لاگین کرده است
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => am_t('login_required')]);
    exit;
}

// لیست‌های پخش فقط برای کاربران VIP
$user = am_current_user();
if (!$user || $user['subscription_status'] !== 'vip') {
    echo json_encode(['success' => false, 'message' => am_t('playlists_vip_only')]);
    exit;
}

// بررسی پارامترهای ورودی
if (!isset($_POST['content_id']) || !isset($_POST['playlist_id'])) {
    echo json_encode(['success' => false, 'message' => am_t('missing_params')]);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$contentId = (int)$_POST['content_id'];
$playlistId = (int)$_POST['playlist_id'];

$db = am_users_db();

// بررسی وجود پلی‌لیست و مالکیت کاربر
try {
    $stmt = $db->prepare("SELECT * FROM playlists WHERE id = ? AND user_id = ?");
    $stmt->execute([$playlistId, $userId]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => am_t('playlist_not_found_or_access')]);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => am_t('server_error')]);
    exit;
}

// بررسی وجود محتوا در دیتابیس محتوا
try {
    $contentDb = am_content_db();
    
    $stmt = $contentDb->prepare("SELECT id FROM anime_contents WHERE id = ?");
    $stmt->execute([$contentId]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => am_t('not_found')]);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => am_t('server_error')]);
    exit;
}

// بررسی وجود قبلی محتوا در پلی‌لیست
try {
    $stmt = $db->prepare("SELECT * FROM playlist_contents WHERE playlist_id = ? AND content_id = ?");
    $stmt->execute([$playlistId, $contentId]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => am_t('content_already_in_playlist')]);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => am_t('server_error')]);
    exit;
}

// افزودن محتوا به پلی‌لیست
try {
    // دریافت آخرین ترتیب برای قرار دادن محتوا در انتها
    $stmt = $db->prepare("SELECT MAX(sort_order) as max_order FROM playlist_contents WHERE playlist_id = ?");
    $stmt->execute([$playlistId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $sortOrder = $result['max_order'] !== null ? $result['max_order'] + 1 : 0;
    
    $stmt = $db->prepare("INSERT INTO playlist_contents (playlist_id, content_id, sort_order) VALUES (?, ?, ?)");
    $stmt->execute([$playlistId, $contentId, $sortOrder]);
    
    echo json_encode(['success' => true, 'message' => am_t('added_to_playlist_success')]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => am_t('add_to_playlist_error')]);
}
?>
