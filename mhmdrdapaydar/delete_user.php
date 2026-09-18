<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// بررسی وجود و صحت پارامتر ID
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    header("Location: admin.php?error=invalid_request");
    exit;
}

$user_id = (int)$_GET['id'];

try {
    // اتصال به دیتابیس کاربران
    $db_users = new PDO('sqlite:' . __DIR__ . '/../db/users.db');
    $db_users->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // شروع تراکنش
    $db_users->beginTransaction();

    // 1. دریافت اطلاعات کاربر برای پیام بازگشت
    $stmt = $db_users->prepare("SELECT username, first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("کاربر یافت نشد");
    }

    // 2. حذف لیست‌های پخش کاربر
    $db_users->prepare("DELETE FROM playlists WHERE user_id = ?")->execute([$user_id]);
    
    // 3. حذف علاقه‌مندی‌های کاربر
    $db_users->prepare("DELETE FROM user_favorites WHERE user_id = ?")->execute([$user_id]);
    
    // 4. حذف تنظیمات کاربر
    $db_users->prepare("DELETE FROM user_preferences WHERE user_id = ?")->execute([$user_id]);
    
    // 5. حذف کاربر
    $stmt = $db_users->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    
    $rowsDeleted = $stmt->rowCount();
    
    if ($rowsDeleted === 0) {
        throw new Exception("هیچ کاربری حذف نشد");
    }
    
    // تأیید تراکنش
    $db_users->commit();
    
    // پیام موفقیت
    $successMsg = "کاربر '" . $user['username'] . "' (" . $user['first_name'] . " " . $user['last_name'] . ") با موفقیت حذف شد";
    
    header("Location: admin.php?success=content_deleted&message=" . urlencode($successMsg));
    exit;

} catch (PDOException $e) {
    // در صورت خطا، بازگردانی تغییرات
    if (isset($db_users)) {
        $db_users->rollBack();
    }
    
    error_log("Delete user error: " . $e->getMessage());
    header("Location: admin.php?error=database_error&message=" . urlencode($e->getMessage()));
    exit;
} catch (Exception $e) {
    // در صورت خطا، بازگردانی تغییرات
    if (isset($db_users)) {
        $db_users->rollBack();
    }
    
    error_log("Delete user error: " . $e->getMessage());
    header("Location: admin.php?error=delete_failed&message=" . urlencode($e->getMessage()));
    exit;
}
?>