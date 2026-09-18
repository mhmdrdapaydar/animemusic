<?php
session_start();
require_once __DIR__ . '/includes/admin_auth.php';
am_admin_guard();

// اتصال به دیتابیس
try {
    $db_content = new PDO('sqlite:' . __DIR__ . '/../db/content.db');
    $db_content->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("خطا در اتصال به پایگاه داده: " . $e->getMessage());
}

$message = '';
$message_type = '';

// کلاس ترجمه گوگل
class GoogleTranslator {
    public function detectLanguage($text) {
        try {
            $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=auto&tl=en&dt=t&q=" . urlencode($text);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            if ($response) {
                $data = json_decode($response, true);
                if (isset($data[2])) {
                    return $data[2]; // زبان تشخیص داده شده
                }
            }
        } catch (Exception $e) {
            error_log("خطا در تشخیص زبان: " . $e->getMessage());
        }
        return null;
    }
    
    public function translate($text, $targetLang = 'fa', $sourceLang = 'auto') {
        try {
            $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl={$sourceLang}&tl={$targetLang}&dt=t&q=" . urlencode($text);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            if ($response) {
                $data = json_decode($response, true);
                $translatedText = '';
                if (isset($data[0]) && is_array($data[0])) {
                    foreach ($data[0] as $segment) {
                        if (isset($segment[0])) {
                            $translatedText .= $segment[0];
                        }
                    }
                }
                return $translatedText;
            }
        } catch (Exception $e) {
            error_log("خطا در ترجمه: " . $e->getMessage());
        }
        return $text; // در صورت خطا، متن اصلی بازگردانده شود
    }
    
    public function translateWithDetection($text, $targetLang = 'fa') {
        $detectedLang = $this->detectLanguage($text);
        if ($detectedLang && $detectedLang !== $targetLang) {
            return $this->translate($text, $targetLang, $detectedLang);
        }
        return $text;
    }
}

// پردازش آپلود فایل JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['json_file'])) {
    if ($_FILES['json_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['json_file']['tmp_name'];
        $file_name = $_FILES['json_file']['name'];
        
        // بررسی پسوند فایل
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if ($file_extension !== 'json') {
            $message = 'فایل باید با پسوند json باشد.';
            $message_type = 'error';
        } else {
            // خواندن و decode کردن فایل JSON
            $json_content = file_get_contents($file_tmp_path);
            $data = json_decode($json_content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $message = 'فایل JSON معتبر نیست: ' . json_last_error_msg();
                $message_type = 'error';
            } else {
                try {
                    $translator = new GoogleTranslator();
                    $db_content->beginTransaction();
                    
                    $imported_anime = 0;
                    $imported_music = 0;
                    $imported_singers = 0;
                    $imported_seasons = 0;
                    $skipped_anime = 0;
                    $skipped_music = 0;
                    $skipped_singers = 0;

                    // پردازش داده‌های JSON
                    foreach ($data as $anime_item) {
                        if (!isset($anime_item['name'])) continue;

                        // تشخیص زبان و ترجمه عنوان
                        $title_en = trim($anime_item['name']);
                        if ($title_en === '') continue;

                        $title_fa = $translator->translateWithDetection($title_en, 'fa');

                        // بررسی وجود انیمه در دیتابیس (بدون حساسیت به بزرگی/کوچکی حروف)
                        $stmt = $db_content->prepare("SELECT id FROM anime_series WHERE title_en = ? COLLATE NOCASE OR title_fa = ? COLLATE NOCASE");
                        $stmt->execute([$title_en, $title_fa]);
                        $anime_id = $stmt->fetchColumn();

                        // اگر انیمه وجود ندارد، آن را اضافه کن
                        if (!$anime_id) {
                            $stmt = $db_content->prepare("
                                INSERT INTO anime_series (title_fa, title_en, poster_image_url, description)
                                VALUES (?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $title_fa,
                                $title_en,
                                $anime_item['main_image_url'] ?? '',
                                '' // بخش توضیحات خالی
                            ]);
                            $anime_id = $db_content->lastInsertId();
                            $imported_anime++;
                        } else {
                            $skipped_anime++;
                        }

                        // پردازش فصل‌ها
                        if (isset($anime_item['seasons']) && is_array($anime_item['seasons'])) {
                            foreach ($anime_item['seasons'] as $season_index => $season) {
                                $imported_seasons++;

                                // پردازش تم‌ها (موزیک‌ها)
                                if (isset($season['themes']) && is_array($season['themes'])) {
                                    foreach ($season['themes'] as $theme) {
                                        // عنوان موزیک اجباری است
                                        if (empty($theme['title'])) continue;
                                        $theme_title = trim($theme['title']);

                                        // تعیین نوع موزیک بر اساس theme type
                                        $music_type_id = 1; // پیش‌فرض
                                        if ($theme['type'] === 'OP') {
                                            $music_type_id = 1; // Opening
                                        } elseif ($theme['type'] === 'ED') {
                                            $music_type_id = 2; // Ending
                                        } elseif ($theme['type'] === 'IN') {
                                            $music_type_id = 3; // Insert Song
                                        }

                                        // پیدا کردن خواننده یا ایجاد آن
                                        $singer_ids = [];
                                        if (!empty($theme['artist'])) {
                                            $artist_names = explode(',', $theme['artist']);
                                            foreach ($artist_names as $artist_name) {
                                                $artist_name = trim($artist_name);
                                                if ($artist_name === '') continue;

                                                $stmt = $db_content->prepare("SELECT id FROM singers WHERE name = ? COLLATE NOCASE");
                                                $stmt->execute([$artist_name]);
                                                $singer_id = $stmt->fetchColumn();

                                                if (!$singer_id) {
                                                    $stmt = $db_content->prepare("INSERT INTO singers (name) VALUES (?)");
                                                    $stmt->execute([$artist_name]);
                                                    $singer_id = $db_content->lastInsertId();
                                                    $imported_singers++;
                                                } else {
                                                    $skipped_singers++;
                                                }

                                                $singer_ids[] = $singer_id;
                                            }
                                        }

                                        // پیدا کردن بهترین ویدیو (اولین ویدیو با بالاترین رزولوشن)
                                        $best_video = null;
                                        if (isset($theme['videos']) && is_array($theme['videos'])) {
                                            foreach ($theme['videos'] as $video) {
                                                if (!$best_video || 
                                                   ($video['resolution'] > $best_video['resolution']) ||
                                                   (isset($video['nc']) && $video['nc'] === false)) {
                                                    $best_video = $video;
                                                }
                                            }
                                        }

                                        // تعیین شماره فصل و اپیزود
                                        $season_number = $season_index + 1;

                                        $episode_number = 1;
                                        if (isset($theme['episodes'])) {
                                            $episodes = $theme['episodes'];
                                            if (strpos($episodes, '-') !== false) {
                                                $episode_parts = explode('-', $episodes);
                                                $episode_number = intval($episode_parts[0]);
                                            } else {
                                                $episode_number = intval($episodes);
                                            }
                                        }

                                        $video_url = $best_video ? $best_video['url'] : '';

                                        // --- جلوگیری از محتوای تکراری (مهم‌ترین بخش) ---
                                        // اثر انگشت ۱: همان انیمه + نوع + عنوان + فصل + قسمت
                                        $exists = false;
                                        $stmt = $db_content->prepare("
                                            SELECT id FROM anime_contents
                                            WHERE anime_id = ? AND music_type_id = ?
                                              AND title = ? COLLATE NOCASE
                                              AND season_number = ?
                                              AND COALESCE(episode_number, 0) = ?
                                            LIMIT 1
                                        ");
                                        $stmt->execute([$anime_id, $music_type_id, $theme_title, $season_number, (int)$episode_number]);
                                        if ($stmt->fetchColumn()) {
                                            $exists = true;
                                        }

                                        // اثر انگشت ۲: لینک فایل یکتا (اگر لینک‌ها برابر باشند یعنی همان موزیک است)
                                        if (!$exists && $video_url !== '') {
                                            $stmt = $db_content->prepare("
                                                SELECT id FROM anime_contents
                                                WHERE music_file_url = ? OR video_file_url = ?
                                                LIMIT 1
                                            ");
                                            $stmt->execute([$video_url, $video_url]);
                                            if ($stmt->fetchColumn()) {
                                                $exists = true;
                                            }
                                        }

                                        // اگر این موزیک از قبل وارد شده، از افزودن دوباره صرف‌نظر کن
                                        if ($exists) {
                                            $skipped_music++;
                                            continue;
                                        }

                                        // اضافه کردن موزیک به دیتابیس
                                        $stmt = $db_content->prepare("
                                            INSERT INTO anime_contents 
                                            (anime_id, music_type_id, title, music_file_url, video_file_url, image_url, season_number, episode_number)
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                        ");

                                        $stmt->execute([
                                            $anime_id,
                                            $music_type_id,
                                            $theme_title,
                                            $video_url,
                                            $video_url,
                                            $season['image_url'] ?? $season['large_image_url'] ?? $anime_item['main_image_url'] ?? '',
                                            $season_number,
                                            $episode_number
                                        ]);

                                        $content_id = $db_content->lastInsertId();
                                        $imported_music++;

                                        // اتصال خوانندگان به موزیک
                                        foreach ($singer_ids as $singer_id) {
                                            $stmt = $db_content->prepare("
                                                INSERT INTO content_singers (content_id, singer_id)
                                                VALUES (?, ?)
                                            ");
                                            $stmt->execute([$content_id, $singer_id]);
                                        }
                                    }
                                }
                            }
                        }
                    }

                    $db_content->commit();

                    $message = "واردات با موفقیت انجام شد.<br>
                                تعداد انیمه‌های اضافه شده: $imported_anime (تکراری: $skipped_anime)<br>
                                تعداد فصل‌های پردازش شده: $imported_seasons<br>
                                تعداد موزیک‌های اضافه شده: $imported_music (تکراری: $skipped_music)<br>
                                تعداد خوانندگان اضافه شده: $imported_singers (تکراری: $skipped_singers)";
                    $message_type = 'success';
                    
                } catch (Exception $e) {
                    $db_content->rollBack();
                    $message = 'خطا در پردازش داده‌ها: ' . $e->getMessage();
                    $message_type = 'error';
                    error_log("Import JSON Error: " . $e->getMessage());
                }
            }
        }
    } else {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'حجم فایل بیشتر از حد مجاز است.',
            UPLOAD_ERR_FORM_SIZE => 'حجم فایل بیشتر از حد مجاز فرم است.',
            UPLOAD_ERR_PARTIAL => 'فایل به صورت ناقص آپلود شده است.',
            UPLOAD_ERR_NO_FILE => 'هیچ فایلی انتخاب نشده است.',
            UPLOAD_ERR_NO_TMP_DIR => 'پوشه موقت وجود ندارد.',
            UPLOAD_ERR_CANT_WRITE => 'خطا در نوشتن فایل روی دیسک.',
            UPLOAD_ERR_EXTENSION => 'آپلود فایل توسط افزونه PHP متوقف شد.'
        ];
        
        $error_code = $_FILES['json_file']['error'];
        $message = isset($error_messages[$error_code]) ? $error_messages[$error_code] : 'خطای ناشناخته در آپلود فایل.';
        $message_type = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>واردات JSON - پنل مدیریت انیمه موزیک</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/admin.css?v=4">
  <style>
    .upload-area { border: 2px dashed var(--primary); border-radius: var(--radius); padding: 26px; text-align: center; background: var(--surface); transition: var(--transition); cursor: pointer; margin-bottom: 14px; }
    .upload-area:hover { background: var(--primary-soft); }
    .upload-icon i { font-size: 44px; color: var(--primary); margin-bottom: 10px; }
    .upload-text h3 { font-size: 16px; margin: 8px 0 4px; color: var(--dark); }
    .upload-text p { color: var(--gray); font-size: 13px; }
    .upload-area .form-group { max-width: 420px; margin: 14px auto 0; }
    .instructions { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px; margin-top: 18px; box-shadow: var(--shadow); }
    .instructions h3 { font-size: 15px; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; color: var(--primary); }
    .instructions p, .instructions li { color: var(--gray); font-size: 13.5px; line-height: 1.9; }
    .instructions ul { padding-right: 20px; }
    footer { text-align: center; color: var(--gray); font-size: 13px; margin-top: 20px; line-height: 1.8; }
  </style>
</head>
<body>
<div class="container">
    <header class="admin-header">
      <div class="brand"><i class="fas fa-file-import"></i><span>واردات داده از فایل JSON</span></div>
      <div class="header-actions">
        <a class="btn" href="admin.php"><i class="fas fa-arrow-right"></i> پنل اصلی</a>
        <a class="btn" href="logout.php"><i class="fas fa-sign-out-alt"></i> خروج</a>
      </div>
    </header>

    <?php if (!empty($message)): ?>
      <div class="message <?= $message_type === 'success' ? 'success-message' : 'error-message' ?>">
        <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
        <div><?= $message ?></div>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <div class="upload-area">
        <div class="upload-icon">
          <i class="fas fa-cloud-upload-alt"></i>
        </div>
        <div class="upload-text">
          <h3>فایل JSON خود را اینجا رها کنید یا برای انتخاب کلیک کنید</h3>
          <p>فرمت مجاز: json (حداکثر حجم: 10MB)</p>
        </div>
        <div class="form-group">
          <input type="file" name="json_file" accept=".json" class="form-control" required>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%;">
        <i class="fas fa-upload"></i>
        آپلود و پردازش فایل
      </button>
    </form>

    <div class="instructions">
      <h3><i class="fas fa-info-circle"></i> راهنمای فرمت JSON</h3>
      <p>فایل JSON باید دارای ساختار زیر باشد:</p>
      <ul>
        <li>آرایه‌ای از آبجکت‌های انیمه</li>
        <li>هر انیمه دارای نام، آدرس و آدرس تصویر اصلی است</li>
        <li>هر انیمه می‌تواند چندین فصل داشته باشد</li>
        <li>هر فصل می‌تواند چندین تم (موزیک) داشته باشد</li>
        <li>هر تم دارای نوع (OP/ED/IN)، عنوان، هنرمند و ویدیوها است</li>
      </ul>
      <p>سیستم به طور خودکار زبان عنوان را تشخیص داده و به فارسی ترجمه می‌کند.</p>
      <h3 style="margin-top:14px;"><i class="fas fa-shield-alt"></i> محافظت در برابر تکراری‌ها</h3>
      <p>
        حتی اگر فایل JSON شامل محتوای قدیمی و جدید با هم باشد، موزیک‌هایی که از قبل در سایت
        وجود دارند (بر اساس انیمه + نوع + عنوان + فصل/قسمت یا بر اساس لینک فایل یکسان)
        دوباره اضافه نمی‌شوند. در پایان، تعداد موارد «تکراریِ رد شده» در پیام نتیجه نمایش داده می‌شود.
      </p>
      <p style="margin-top:8px;">
        اگر از واردات‌های قبلی موزیک تکراری در سایت باقی مانده، می‌توانید با یک کلیک آن‌ها را پاک کنید:
        <a href="clean_duplicates.php" style="color:var(--danger); font-weight:700;">
          <i class="fas fa-broom"></i> پاکسازی تکراری‌های قبلی
        </a>
      </p>
    </div>

    <div class="footer">
      <p>پنل مدیریت انیمه موزیک | نسخه ۳.۰</p>
      <p>کلیه حقوق برای این پلتفرم محفوظ است © <?= date('Y') ?></p>
    </div>
  </div>

  <script>
    // بهبود UX برای آپلود فایل
    const fileInput = document.querySelector('input[type="file"]');
    const uploadArea = document.querySelector('.upload-area');
    
    fileInput.addEventListener('change', function() {
      if (this.files && this.files[0]) {
        const fileName = this.files[0].name;
        uploadArea.querySelector('h3').textContent = `فایل انتخاب شده: ${fileName}`;
      }
    });
    
    uploadArea.addEventListener('dragover', function(e) {
      e.preventDefault();
      this.style.background = 'rgba(0, 170, 111, 0.15)';
      this.style.borderColor = 'var(--primary-dark)';
    });
    
    uploadArea.addEventListener('dragleave', function() {
      this.style.background = 'rgba(0, 170, 111, 0.05)';
      this.style.borderColor = 'var(--primary)';
    });
    
    uploadArea.addEventListener('drop', function(e) {
      e.preventDefault();
      this.style.background = 'rgba(0, 170, 111, 0.05)';
      this.style.borderColor = 'var(--primary)';
      
      if (e.dataTransfer.files && e.dataTransfer.files[0]) {
        fileInput.files = e.dataTransfer.files;
        const fileName = e.dataTransfer.files[0].name;
        uploadArea.querySelector('h3').textContent = `فایل انتخاب شده: ${fileName}`;
      }
    });
  </script>
</body>
</html>