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

$singer_id = (int)$_GET['id'];

try {
    // اتصال به دیتابیس محتوا
    $db_content = new PDO('sqlite:' . __DIR__ . '/../db/content.db');
    $db_content->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // شروع تراکنش
    $db_content->beginTransaction();

    // 1. دریافت اطلاعات خواننده برای پیام بازگشت
    $stmt = $db_content->prepare("SELECT name FROM singers WHERE id = ?");
    $stmt->execute([$singer_id]);
    $singer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$singer) {
        throw new Exception("خواننده یافت نشد");
    }

    // 2. حذف ارتباط با موزیک‌ها
    $db_content->prepare("DELETE FROM content_singers WHERE singer_id = ?")->execute([$singer_id]);
    
    // 3. حذف خواننده
    $stmt = $db_content->prepare("DELETE FROM singers WHERE id = ?");
    $stmt->execute([$singer_id]);
    
    $rowsDeleted = $stmt->rowCount();
    
    if ($rowsDeleted === 0) {
        throw new Exception("هیچ خواننده‌ای حذف نشد");
    }
    
    // تأیید تراکنش
    $db_content->commit();
    
    // پیام موفقیت
    $successMsg = "خواننده '" . $singer['name'] . "' با موفقیت حذف شد";
    
    header("Location: admin.php?success=content_deleted&message=" . urlencode($successMsg));
    exit;

} catch (PDOException $e) {
    // در صورت خطا، بازگردانی تغییرات
    if (isset($db_content)) {
        $db_content->rollBack();
    }
    
    error_log("Delete singer error: " . $e->getMessage());
    header("Location: admin.php?error=database_error&message=" . urlencode($e->getMessage()));
    exit;
} catch (Exception $e) {
    // در صورت خطا، بازگردانی تغییرات
    if (isset($db_content)) {
        $db_content->rollBack();
    }
    
    error_log("Delete singer error: " . $e->getMessage());
    header("Location: admin.php?error=delete_failed&message=" . urlencode($e->getMessage()));
    exit;
}
?>