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
    
    // دریافت اطلاعات خواننده
    $stmt = $db_content->prepare("SELECT * FROM singers WHERE id = ?");
    $stmt->execute([$singer_id]);
    $singer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$singer) {
        header("Location: admin.php?error=content_not_found");
        exit;
    }
    
    // اگر فرم ارسال شده باشد
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // اعتبارسنجی فیلدهای اجباری
        if (empty($_POST['name'])) {
            $error = "فیلد نام اجباری است";
        }
        
        if (!isset($error)) {
            // به‌روزرسانی اطلاعات خواننده
            $stmt = $db_content->prepare("
                UPDATE singers 
                SET name = ?, bio = ?, image_url = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $_POST['name'],
                $_POST['bio'] ?? null,
                $_POST['image_url'] ?? null,
                $singer_id
            ]);
            
            header("Location: admin.php?success=content_updated");
            exit;
        }
    }
    
} catch (PDOException $e) {
    error_log("Edit singer error: " . $e->getMessage());
    header("Location: admin.php?error=database_error&message=" . urlencode($e->getMessage()));
    exit;
}

// نمایش فرم ویرایش
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ویرایش خواننده</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn.min.css" rel="stylesheet" />
  <style>
    /* استایل‌ها مشابه edit_anime.php */
  </style>
</head>
<body>
  <div class="container">
    <h1><i class="fas fa-edit"></i> ویرایش خواننده</h1>
    
    <?php if (isset($error)): ?>
      <div class="error"><?= $error ?></div>
    <?php endif; ?>
    
    <form method="post">
      <div class="form-group">
        <label>نام خواننده:</label>
        <input type="text" name="name" value="<?= htmlspecialchars($singer['name']) ?>" required>
      </div>
      
      <div class="form-group">
        <label>لینک تصویر (اختیاری):</label>
        <input type="text" name="image_url" value="<?= htmlspecialchars($singer['image_url'] ?? '') ?>">
      </div>
      
      <div class="form-group">
        <label>بیوگرافی (اختیاری):</label>
        <textarea name="bio" rows="4"><?= htmlspecialchars($singer['bio'] ?? '') ?></textarea>
      </div>
      
      <button type="submit">ذخیره تغییرات</button>
      <a href="admin.php" style="margin-right: 15px;">بازگشت</a>
    </form>
  </div>
</body>
</html>