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
  <link rel="stylesheet" href="assets/admin.css?v=4">
</head>
<body>
<div class="container">
  <header class="admin-header">
    <div class="brand"><i class="fas fa-edit"></i><span>ویرایش خواننده</span></div>
    <div class="header-actions"><a class="btn" href="admin.php"><i class="fas fa-arrow-right"></i> بازگشت</a></div>
  </header>

  <div class="card" style="padding:20px;">
    <?php if (isset($error)): ?>
      <div class="message error-message"><i class="fas fa-exclamation-circle"></i><div><?= htmlspecialchars($error) ?></div></div>
    <?php endif; ?>

    <form method="post">
      <div class="form-grid">
        <div class="form-group"><label>نام خواننده</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($singer['name']) ?>" required></div>
        <div class="form-group"><label>لینک تصویر (اختیاری)</label><input type="text" name="image_url" class="form-control" value="<?= htmlspecialchars($singer['image_url'] ?? '') ?>"></div>
      </div>
      <div class="form-group"><label>بیوگرافی (اختیاری)</label><textarea name="bio" class="form-control" rows="4"><?= htmlspecialchars($singer['bio'] ?? '') ?></textarea></div>
      <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> ذخیره تغییرات</button>
    </form>
  </div>
</div>
</body>
</html>