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

$anime_id = (int)$_GET['id'];

try {
    // اتصال به دیتابیس محتوا
    $db_content = new PDO('sqlite:../db/content.db');
    $db_content->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // شروع تراکنش
    $db_content->beginTransaction();

    // 1. دریافت اطلاعات انیمه برای پیام بازگشت
    $stmt = $db_content->prepare("SELECT title_fa FROM anime_series WHERE id = ?");
    $stmt->execute([$anime_id]);
    $anime = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$anime) {
        throw new Exception("انیمه یافت نشد");
    }

    // 2. حذف موزیک‌های مرتبط
    $db_content->prepare("DELETE FROM anime_contents WHERE anime_id = ?")->execute([$anime_id]);
    
    // 3. حذف انیمه
    $stmt = $db_content->prepare("DELETE FROM anime_series WHERE id = ?");
    $stmt->execute([$anime_id]);
    
    $rowsDeleted = $stmt->rowCount();
    
    if ($rowsDeleted === 0) {
        throw new Exception("هیچ انیمه‌ای حذف نشد");
    }
    
    // تأیید تراکنش
    $db_content->commit();
    
    // پیام موفقیت
    $successMsg = "انیمه '" . $anime['title_fa'] . "' با موفقیت حذف شد";
    
    header("Location: admin.php?success=content_deleted&message=" . urlencode($successMsg));
    exit;

} catch (PDOException $e) {
    // در صورت خطا، بازگردانی تغییرات
    if (isset($db_content)) {
        $db_content->rollBack();
    }
    
    error_log("Delete anime error: " . $e->getMessage());
    header("Location: admin.php?error=database_error&message=" . urlencode($e->getMessage()));
    exit;
} catch (Exception $e) {
    // در صورت خطا، بازگردانی تغییرات
    if (isset($db_content)) {
        $db_content->rollBack();
    }
    
    error_log("Delete anime error: " . $e->getMessage());
    header("Location: admin.php?error=delete_failed&message=" . urlencode($e->getMessage()));
    exit;
}
?>