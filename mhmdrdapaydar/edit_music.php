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
    $db_content = new PDO('sqlite:' . __DIR__ . '/../db/content.db');
    $db_content->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // دریافت اطلاعات موزیک
    $stmt = $db_content->prepare("
        SELECT ac.*, asr.title_fa, mt.name as music_type 
        FROM anime_contents ac 
        JOIN anime_series asr ON ac.anime_id = asr.id 
        JOIN music_types mt ON ac.music_type_id = mt.id 
        WHERE ac.id = ?
    ");
    $stmt->execute([$music_id]);
    $music = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$music) {
        header("Location: admin.php?error=content_not_found");
        exit;
    }
    
    // دریافت خوانندگان این موزیک
    $stmt = $db_content->prepare("
        SELECT s.id, s.name 
        FROM singers s 
        JOIN content_singers cs ON s.id = cs.singer_id 
        WHERE cs.content_id = ?
    ");
    $stmt->execute([$music_id]);
    $singers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $singer_ids = array_map(function($s) { return $s['id']; }, $singers);
    
    // دریافت لیست انیمه‌ها و انواع موزیک
    $anime_list = $db_content->query("SELECT id, title_fa, title_en FROM anime_series ORDER BY title_fa")->fetchAll(PDO::FETCH_ASSOC);
    $music_types = $db_content->query("SELECT * FROM music_types")->fetchAll(PDO::FETCH_ASSOC);
    
    // اگر فرم ارسال شده باشد
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // اعتبارسنجی فیلدهای اجباری
        $required_fields = ['anime_id', 'music_type_id', 'title', 'music_file_url'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $error = "فیلد اجباری پر نشده است: " . $field;
                break;
            }
        }
        
        if (!isset($error)) {
            $db_content->beginTransaction();
            
            // به‌روزرسانی اطلاعات موزیک
            $stmt = $db_content->prepare("
                UPDATE anime_contents 
                SET anime_id = ?, music_type_id = ?, season_number = ?, episode_number = ?, 
                    title = ?, music_file_url = ?, video_file_url = ?, image_url = ?, 
                    duration = ?, lyrics_text = ?, lyrics_translation = ?
                WHERE id = ?
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
                $_POST['lyrics_translation'] ?? null,
                $music_id
            ]);
            
            // حذف خوانندگان قبلی و افزودن جدید
            $db_content->prepare("DELETE FROM content_singers WHERE content_id = ?")->execute([$music_id]);
            
            if (!empty($_POST['singer_ids'])) {
                $new_singer_ids = explode(',', $_POST['singer_ids']);
                foreach ($new_singer_ids as $singer_id) {
                    $singer_id = trim($singer_id);
                    if (is_numeric($singer_id)) {
                        $stmt = $db_content->prepare("
                            INSERT INTO content_singers (content_id, singer_id)
                            VALUES (?, ?)
                        ");
                        $stmt->execute([$music_id, $singer_id]);
                    }
                }
            }
            
            $db_content->commit();
            
            header("Location: admin.php?success=content_updated");
            exit;
        }
    }
    
} catch (PDOException $e) {
    if (isset($db_content)) {
        $db_content->rollBack();
    }
    
    error_log("Edit music error: " . $e->getMessage());
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
  <title>ویرایش موزیک</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/admin.css?v=4">
</head>
<body>
<div class="container">
  <header class="admin-header">
    <div class="brand"><i class="fas fa-edit"></i><span>ویرایش موزیک</span></div>
    <div class="header-actions"><a class="btn" href="admin.php"><i class="fas fa-arrow-right"></i> بازگشت</a></div>
  </header>

  <div class="card" style="padding:20px;">
    <?php if (isset($error)): ?>
      <div class="message error-message"><i class="fas fa-exclamation-circle"></i><div><?= htmlspecialchars($error) ?></div></div>
    <?php endif; ?>

    <form method="post">
      <div class="form-grid">
        <div class="form-group"><label>انتخاب انیمه</label>
          <select name="anime_id" class="form-control" required>
            <option value="">-- انتخاب انیمه --</option>
            <?php foreach ($anime_list as $anime): ?>
              <option value="<?= $anime['id'] ?>" <?= $anime['id'] == $music['anime_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($anime['title_fa']) ?> (<?= htmlspecialchars($anime['title_en']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>نوع موزیک</label>
          <select name="music_type_id" class="form-control" required>
            <option value="">-- انتخاب نوع --</option>
            <?php foreach ($music_types as $type): ?>
              <option value="<?= $type['id'] ?>" <?= $type['id'] == $music['music_type_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($type['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>فصل</label><input type="number" name="season_number" class="form-control" value="<?= $music['season_number'] ?>" min="1"></div>
        <div class="form-group"><label>قسمت</label><input type="number" name="episode_number" class="form-control" value="<?= $music['episode_number'] ?>" min="1"></div>
        <div class="form-group"><label>عنوان موزیک</label><input type="text" name="title" class="form-control" value="<?= htmlspecialchars($music['title']) ?>" required></div>
        <div class="form-group"><label>لینک فایل موزیک</label><input type="text" name="music_file_url" class="form-control" value="<?= htmlspecialchars($music['music_file_url']) ?>" required></div>
        <div class="form-group"><label>لینک فایل ویدیو (اختیاری)</label><input type="text" name="video_file_url" class="form-control" value="<?= htmlspecialchars($music['video_file_url'] ?? '') ?>"></div>
        <div class="form-group"><label>لینک تصویر (اختیاری)</label><input type="text" name="image_url" class="form-control" value="<?= htmlspecialchars($music['image_url'] ?? '') ?>"></div>
        <div class="form-group"><label>مدت زمان (ثانیه)</label><input type="number" name="duration" class="form-control" value="<?= $music['duration'] ?>" min="0"></div>
        <div class="form-group"><label>خوانندگان (ID جدا با کاما)</label><input type="text" name="singer_ids" class="form-control" value="<?= implode(',', $singer_ids) ?>" placeholder="مثال: 1,5,8"></div>
      </div>
      <div class="form-group"><label>متن آهنگ (اختیاری)</label><textarea name="lyrics_text" class="form-control" rows="4"><?= htmlspecialchars($music['lyrics_text'] ?? '') ?></textarea></div>
      <div class="form-group"><label>ترجمه فارسی (اختیاری)</label><textarea name="lyrics_translation" class="form-control" rows="4"><?= htmlspecialchars($music['lyrics_translation'] ?? '') ?></textarea></div>
      <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> ذخیره تغییرات</button>
    </form>
  </div>
</div>
</body>
</html>