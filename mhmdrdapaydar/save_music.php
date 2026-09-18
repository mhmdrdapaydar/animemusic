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
$required_fields = ['anime_id', 'music_type_id', 'title', 'music_file_url'];
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

    // ذخیره موزیک جدید
    $stmt = $db_content->prepare("
        INSERT INTO anime_contents 
        (anime_id, music_type_id, season_number, episode_number, title, 
         music_file_url, video_file_url, image_url, duration, 
         lyrics_text, lyrics_translation)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $_POST['anime_id'],
        $_POST['music_type_id'],
        $_POST['season_number'] ?? 1,
        $_POST['episode_number'] ?? null,
        $_POST['title'],
        $_POST['music_file_url'],
        $_POST['video_file_url'] ?? null,
        $_POST['image_url'] ?? null, // این فیلد اضافه شد
        $_POST['duration'] ?? null,
        $_POST['lyrics_text'] ?? null,
        $_POST['lyrics_translation'] ?? null
    ]);
    
    $content_id = $db_content->lastInsertId();
    
    // افزودن خوانندگان اگر مشخص شده باشند
    if (!empty($_POST['singer_ids'])) {
        $singer_ids = explode(',', $_POST['singer_ids']);
        foreach ($singer_ids as $singer_id) {
            $singer_id = trim($singer_id);
            if (is_numeric($singer_id)) {
                $stmt = $db_content->prepare("
                    INSERT INTO content_singers (content_id, singer_id)
                    VALUES (?, ?)
                ");
                $stmt->execute([$content_id, $singer_id]);
            }
        }
    }
    
    // تأیید تراکنش
    $db_content->commit();
    
    // بازگشت با پیام موفقیت
    header("Location: admin.php?success=music_added");
    exit;

} catch (PDOException $e) {
    // در صورت خطا، بازگردانی تغییرات
    if (isset($db_content)) {
        $db_content->rollBack();
    }
    
    error_log("Save music error: " . $e->getMessage());
    header("Location: admin.php?error=database_error&message=" . urlencode($e->getMessage()));
    exit;
} catch (Exception $e) {
    // در صورت خطا، بازگردانی تغییرات
    if (isset($db_content)) {
        $db_content->rollBack();
    }
    
    error_log("Save music error: " . $e->getMessage());
    header("Location: admin.php?error=operation_failed&message=" . urlencode($e->getMessage()));
    exit;
}
?>