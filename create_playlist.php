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
if (!isset($_POST['name']) || empty(trim($_POST['name']))) {
    echo json_encode(['success' => false, 'message' => am_t('playlist_name_required')]);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$playlistName = trim($_POST['name']);
$contentId = isset($_POST['content_id']) ? (int)$_POST['content_id'] : null;

$db = am_users_db();

// بررسی تکراری نبودن نام پلی‌لیست برای کاربر
try {
    $stmt = $db->prepare("SELECT * FROM playlists WHERE user_id = ? AND name = ?");
    $stmt->execute([$userId, $playlistName]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => am_t('playlist_name_exists')]);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => am_t('server_error')]);
    exit;
}

// اگر content_id ارسال شده، بررسی وجود محتوا
if ($contentId) {
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
}

// ایجاد پلی‌لیست جدید
try {
    $db->beginTransaction();
    
    // ایجاد پلی‌لیست
    $stmt = $db->prepare("INSERT INTO playlists (user_id, name, description) VALUES (?, ?, ?)");
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $stmt->execute([$userId, $playlistName, $description]);
    
    $playlistId = $db->lastInsertId();
    
    // اگر content_id ارسال شده، افزودن به پلی‌لیست
    if ($contentId) {
        $stmt = $db->prepare("INSERT INTO playlist_contents (playlist_id, content_id, sort_order) VALUES (?, ?, 0)");
        $stmt->execute([$playlistId, $contentId]);
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $contentId ? am_t('playlist_created_success') : am_t('playlist_created'),
        'playlist_id' => $playlistId
    ]);
    
} catch (PDOException $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => am_t('create_playlist_error')]);
}
?>
