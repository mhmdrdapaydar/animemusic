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

$music_id = (int)$_GET['id'];

try {
    // اتصال به دیتابیس محتوا
    $db_content = new PDO('sqlite:../db/content.db');
    $db_content->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // شروع تراکنش
    $db_content->beginTransaction();

    // 1. دریافت اطلاعات موزیک برای پیام بازگشت
    $stmt = $db_content->prepare("
        SELECT ac.title, asr.title_fa 
        FROM anime_contents ac 
        JOIN anime_series asr ON ac.anime_id = asr.id 
        WHERE ac.id = ?
    ");
    $stmt->execute([$music_id]);
    $music = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$music) {
        throw new Exception("موزیک یافت نشد");
    }

    // 2. حذف ارتباط با خوانندگان
    $db_content->prepare("DELETE FROM content_singers WHERE content_id = ?")->execute([$music_id]);
    
    // 3. حذف موزیک
    $stmt = $db_content->prepare("DELETE FROM anime_contents WHERE id = ?");
    $stmt->execute([$music_id]);
    
    $rowsDeleted = $stmt->rowCount();
    
    if ($rowsDeleted === 0) {
        throw new Exception("هیچ موزیکی حذف نشد");
    }
    
    // تأیید تراکنش
    $db_content->commit();
    
    // پیام موفقیت
    $successMsg = "موزیک '" . $music['title'] . "' از انیمه '" . $music['title_fa'] . "' با موفقیت حذف شد";
    
    header("Location: admin.php?success=content_deleted&message=" . urlencode($successMsg));
    exit;

} catch (PDOException $e) {
    // در صورت خطا، بازگردانی تغییرات
    if (isset($db_content)) {
        $db_content->rollBack();
    }
    
    error_log("Delete music error: " . $e->getMessage());
    header("Location: admin.php?error=database_error&message=" . urlencode($e->getMessage()));
    exit;
} catch (Exception $e) {
    // در صورت خطا، بازگردانی تغییرات
    if (isset($db_content)) {
        $db_content->rollBack();
    }
    
    error_log("Delete music error: " . $e->getMessage());
    header("Location: admin.php?error=delete_failed&message=" . urlencode($e->getMessage()));
    exit;
}
?>