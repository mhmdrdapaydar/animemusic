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
    $db_content = new PDO('sqlite:' . __DIR__ . '/../db/content.db');
    $db_content->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // دریافت اطلاعات انیمه
    $stmt = $db_content->prepare("SELECT * FROM anime_series WHERE id = ?");
    $stmt->execute([$anime_id]);
    $anime = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$anime) {
        header("Location: admin.php?error=content_not_found");
        exit;
    }
    
    // اگر فرم ارسال شده باشد
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // اعتبارسنجی فیلدهای اجباری
        $required_fields = ['title_fa', 'title_en', 'poster_image_url'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $error = "فیلد اجباری پر نشده است: " . $field;
                break;
            }
        }
        
        if (!isset($error)) {
            // به‌روزرسانی اطلاعات انیمه
            $stmt = $db_content->prepare("
                UPDATE anime_series 
                SET title_fa = ?, title_en = ?, description = ?, 
                    important_links = ?, poster_image_url = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $_POST['title_fa'],
                $_POST['title_en'],
                $_POST['description'] ?? null,
                $_POST['important_links'] ?? null,
                $_POST['poster_image_url'],
                $anime_id
            ]);
            
            header("Location: admin.php?success=content_updated");
            exit;
        }
    }
    
} catch (PDOException $e) {
    error_log("Edit anime error: " . $e->getMessage());
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
  <title>ویرایش انیمه</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/admin.css?v=4">
</head>
<body>
<div class="container">
  <header class="admin-header">
    <div class="brand"><i class="fas fa-edit"></i><span>ویرایش انیمه</span></div>
    <div class="header-actions"><a class="btn" href="admin.php"><i class="fas fa-arrow-right"></i> بازگشت</a></div>
  </header>

  <div class="card" style="padding:20px;">
    <?php if (isset($error)): ?>
      <div class="message error-message"><i class="fas fa-exclamation-circle"></i><div><?= htmlspecialchars($error) ?></div></div>
    <?php endif; ?>

    <form method="post">
      <div class="form-grid">
        <div class="form-group"><label>عنوان فارسی</label><input type="text" name="title_fa" class="form-control" value="<?= htmlspecialchars($anime['title_fa']) ?>" required></div>
        <div class="form-group"><label>عنوان انگلیسی</label><input type="text" name="title_en" class="form-control" value="<?= htmlspecialchars($anime['title_en']) ?>" required></div>
        <div class="form-group"><label>لینک تصویر پست</label><input type="text" name="poster_image_url" class="form-control" value="<?= htmlspecialchars($anime['poster_image_url']) ?>" required></div>
      </div>
      <div class="form-group"><label>توضیحات</label><textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($anime['description'] ?? '') ?></textarea></div>
      <div class="form-group"><label>لینک‌های مهم (فرمت: نام=لینک, نام۲=لینک۲)</label><textarea name="important_links" class="form-control" rows="2"><?= htmlspecialchars($anime['important_links'] ?? '') ?></textarea></div>
      <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> ذخیره تغییرات</button>
    </form>
  </div>
</div>
</body>
</html>