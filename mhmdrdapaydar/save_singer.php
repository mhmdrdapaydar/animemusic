<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// بررسی روش درخواست
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin.php?error=invalid_request_method");
    exit;
}

// اعتبارسنجی فیلدهای اجباری
$required_fields = ['name'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        header("Location: admin.php?error=missing_required_field&field=" . urlencode($field));
        exit;
    }
}

try {
    // اتصال به دیتابیس محتوا
    $db_content = new PDO('sqlite:../db/content.db');
    $db_content->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // شروع تراکنش
    $db_content->beginTransaction();

    // ذخیره خواننده جدید
    $stmt = $db_content->prepare("
        INSERT INTO singers (name, bio, image_url)
        VALUES (?, ?, ?)
    ");
    
    $stmt->execute([
        $_POST['name'],
        $_POST['bio'] ?? null,
        $_POST['image_url'] ?? null
    ]);
    
    // تأیید تراکنش
    $db_content->commit();
    
    // بازگشت با پیام موفقیت
    header("Location: admin.php?success=singer_added");
    exit;

} catch (PDOException $e) {
    // در صورت خطا، بازگردانی تغییرات
    if (isset($db_content)) {
        $db_content->rollBack();
    }
    
    error_log("Save singer error: " . $e->getMessage());
    header("Location: admin.php?error=database_error&message=" . urlencode($e->getMessage()));
    exit;
} catch (Exception $e) {
    // در صورت خطا، بازگردانی تغییرات
    if (isset($db_content)) {
        $db_content->rollBack();
    }
    
    error_log("Save singer error: " . $e->getMessage());
    header("Location: admin.php?error=operation_failed&message=" . urlencode($e->getMessage()));
    exit;
}
?>