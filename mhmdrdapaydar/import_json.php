<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

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
                    
                    // پردازش داده‌های JSON
                    foreach ($data as $anime_item) {
                        if (!isset($anime_item['name'])) continue;
                        
                        // تشخیص زبان و ترجمه عنوان
                        $title_en = $anime_item['name'];
                        $title_fa = $translator->translateWithDetection($title_en, 'fa');
                        
                        // بررسی وجود انیمه در دیتابیس
                        $stmt = $db_content->prepare("SELECT id FROM anime_series WHERE title_en = ?");
                        $stmt->execute([$title_en]);
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
                        }
                        
                        // پردازش فصل‌ها
                        if (isset($anime_item['seasons']) && is_array($anime_item['seasons'])) {
                            foreach ($anime_item['seasons'] as $season_index => $season) {
                                $imported_seasons++;
                                
                                // پردازش تم‌ها (موزیک‌ها)
                                if (isset($season['themes']) && is_array($season['themes'])) {
                                    foreach ($season['themes'] as $theme) {
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
                                                $stmt = $db_content->prepare("SELECT id FROM singers WHERE name = ?");
                                                $stmt->execute([$artist_name]);
                                                $singer_id = $stmt->fetchColumn();
                                                
                                                if (!$singer_id) {
                                                    $stmt = $db_content->prepare("INSERT INTO singers (name) VALUES (?)");
                                                    $stmt->execute([$artist_name]);
                                                    $singer_id = $db_content->lastInsertId();
                                                    $imported_singers++;
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
                                        
                                        // اضافه کردن موزیک به دیتابیس
                                        $stmt = $db_content->prepare("
                                            INSERT INTO anime_contents 
                                            (anime_id, music_type_id, title, music_file_url, video_file_url, image_url, season_number, episode_number)
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                        ");
                                        
                                        // تعیین شماره فصل
                                        $season_number = $season_index + 1;
                                        
                                        // تعیین شماره اپیزود بر اساس episodes
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
                                        
                                        $stmt->execute([
                                            $anime_id,
                                            $music_type_id,
                                            $theme['title'],
                                            $best_video ? $best_video['url'] : '',
                                            $best_video ? $best_video['url'] : '',
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
                                تعداد انیمه‌های اضافه شده: $imported_anime<br>
                                تعداد فصل‌های پردازش شده: $imported_seasons<br>
                                تعداد موزیک‌های اضافه شده: $imported_music<br>
                                تعداد خوانندگان اضافه شده: $imported_singers";
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
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    
    :root {
      --primary: #00aa6f;
      --primary-dark: #007d52;
      --secondary: #4361ee;
      --light: #f8f9fa;
      --dark: #212529;
      --gray: #6c757d;
      --light-gray: #e9ecef;
      --danger: #dc3545;
      --success: #28a745;
      --warning: #ffc107;
      --info: #17a2b8;
      --border-radius: 10px;
      --box-shadow: 0 5px 15px rgba(0,0,0,0.08);
      --transition: all 0.3s ease;
    }
    
    body {
      font-family: 'Vazirmatn', 'Segoe UI', Tahoma, sans-serif;
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      color: var(--dark);
      direction: rtl;
      min-height: 100vh;
      padding: 20px;
      line-height: 1.6;
    }
    
    .container {
      max-width: 1000px;
      margin: 0 auto;
      background: white;
      border-radius: var(--border-radius);
      box-shadow: var(--box-shadow);
      padding: 30px;
    }
    
    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
      padding-bottom: 20px;
      border-bottom: 1px solid var(--light-gray);
    }
    
    .logo {
      display: flex;
      align-items: center;
      gap: 15px;
    }
    
    .logo i {
      font-size: 28px;
      color: var(--primary);
    }
    
    .logo h1 {
      font-size: 24px;
      color: var(--dark);
    }
    
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 10px 20px;
      border-radius: var(--border-radius);
      border: none;
      cursor: pointer;
      font-weight: 500;
      transition: var(--transition);
      font-family: inherit;
      font-size: 15px;
      text-decoration: none;
    }
    
    .btn i {
      font-size: 16px;
    }
    
    .btn-primary {
      background: var(--primary);
      color: white;
    }
    
    .btn-primary:hover {
      background: var(--primary-dark);
      transform: translateY(-2px);
      box-shadow: 0 4px 10px rgba(0, 170, 111, 0.3);
    }
    
    .btn-secondary {
      background: var(--secondary);
      color: white;
    }
    
    .btn-secondary:hover {
      background: #3651d8;
      transform: translateY(-2px);
    }
    
    .btn-danger {
      background: var(--danger);
      color: white;
    }
    
    .btn-danger:hover {
      background: #bd2130;
      transform: translateY(-2px);
      box-shadow: 0 4px 10px rgba(220, 53, 69, 0.3);
    }
    
    .upload-area {
      border: 2px dashed var(--primary);
      border-radius: var(--border-radius);
      padding: 40px;
      text-align: center;
      margin-bottom: 30px;
      background: rgba(0, 170, 111, 0.05);
      transition: var(--transition);
    }
    
    .upload-area:hover {
      background: rgba(0, 170, 111, 0.1);
    }
    
    .upload-icon {
      font-size: 48px;
      color: var(--primary);
      margin-bottom: 15px;
    }
    
    .upload-text {
      margin-bottom: 20px;
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    .form-control {
      width: 100%;
      padding: 12px 15px;
      border: 1px solid #ced4da;
      border-radius: var(--border-radius);
      font-family: inherit;
      font-size: 15px;
      transition: var(--transition);
    }
    
    .form-control:focus {
      border-color: var(--primary);
      outline: none;
      box-shadow: 0 0 0 3px rgba(0, 170, 111, 0.2);
    }
    
    .message {
      padding: 15px;
      border-radius: var(--border-radius);
      margin-bottom: 25px;
      display: flex;
      align-items: center;
      gap: 15px;
    }
    
    .success-message {
      background: rgba(40, 167, 69, 0.1);
      color: var(--success);
      border-left: 4px solid var(--success);
    }
    
    .error-message {
      background: rgba(220, 53, 69, 0.1);
      color: var(--danger);
      border-left: 4px solid var(--danger);
    }
    
    .info-message {
      background: rgba(23, 162, 184, 0.1);
      color: var(--info);
      border-left: 4px solid var(--info);
    }
    
    .message i {
      font-size: 22px;
    }
    
    .instructions {
      background: var(--light);
      border-radius: var(--border-radius);
      padding: 20px;
      margin-top: 30px;
    }
    
    .instructions h3 {
      margin-bottom: 15px;
      color: var(--primary);
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .instructions ul {
      padding-right: 20px;
      margin-bottom: 15px;
    }
    
    .instructions li {
      margin-bottom: 8px;
    }
    
    .footer {
      text-align: center;
      padding: 20px;
      color: var(--gray);
      font-size: 14px;
      margin-top: 30px;
      border-top: 1px solid var(--light-gray);
    }
    
    @media (max-width: 768px) {
      .container {
        padding: 20px;
      }
      
      .upload-area {
        padding: 20px;
      }
      
      .header {
        flex-direction: column;
        gap: 20px;
        text-align: center;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="logo">
        <i class="fas fa-file-import"></i>
        <h1>واردات داده از فایل JSON</h1>
      </div>
      <div>
        <a href="admin.php" class="btn btn-secondary">
          <i class="fas fa-arrow-right"></i>
          بازگشت به پنل مدیریت
        </a>
      </div>
    </div>

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