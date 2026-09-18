<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// اتصال به دیتابیس‌ها
try {
    // اتصال به دیتابیس محتوا
    $db_content = new PDO('sqlite:../db/content.db');
    $db_content->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // اتصال به دیتابیس کاربران
    $db_users = new PDO('sqlite:../db/users.db');
    $db_users->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // دریافت آمار از دیتابیس محتوا
    $animeCount = $db_content->query("SELECT COUNT(*) FROM anime_series")->fetchColumn();
    $musicCount = $db_content->query("SELECT COUNT(*) FROM anime_contents")->fetchColumn();
    $singerCount = $db_content->query("SELECT COUNT(*) FROM singers")->fetchColumn();
    
    // دریافت آمار از دیتابیس کاربران
    $userCount = $db_users->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $vipCount = $db_users->query("SELECT COUNT(*) FROM users WHERE subscription_status = 'vip'")->fetchColumn();
    
    // دریافت آخرین انیمه‌ها
    $latestAnime = $db_content->query("SELECT * FROM anime_series ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    
    // دریافت آخرین موزیک‌ها
    $latestMusic = $db_content->query("
        SELECT ac.*, asr.title_fa, mt.name as music_type 
        FROM anime_contents ac 
        JOIN anime_series asr ON ac.anime_id = asr.id 
        JOIN music_types mt ON ac.music_type_id = mt.id 
        ORDER BY ac.id DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // دریافت انواع موزیک
    $musicTypes = $db_content->query("SELECT * FROM music_types")->fetchAll(PDO::FETCH_ASSOC);
    
    // محاسبه آمار سیستمی
    $dbSize = file_exists('../db/content.db') ? filesize('../db/content.db') : 0;
    $dbSize += file_exists('../db/users.db') ? filesize('../db/users.db') : 0;
    $memoryUsage = memory_get_usage(true);
    
} catch (PDOException $e) {
    $error = "خطا در اتصال به پایگاه داده: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>پنل مدیریت انیمه موزیک</title>
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
      max-width: 1600px;
      margin: 0 auto;
    }
    
    /* Header Styles */
    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
      padding: 20px;
      background: white;
      border-radius: var(--border-radius);
      box-shadow: var(--box-shadow);
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
    
    .user-actions {
      display: flex;
      gap: 15px;
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
    
    .btn-danger {
      background: var(--danger);
      color: white;
    }
    
    .btn-danger:hover {
      background: #bd2130;
      transform: translateY(-2px);
      box-shadow: 0 4px 10px rgba(220, 53, 69, 0.3);
    }
    
    .btn-secondary {
      background: var(--secondary);
      color: white;
    }
    
    .btn-secondary:hover {
      background: #3651d8;
      transform: translateY(-2px);
    }
    
    /* Stats Section */
    .stats-container {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    
    .stat-card {
      background: white;
      border-radius: var(--border-radius);
      padding: 20px;
      display: flex;
      flex-direction: column;
      box-shadow: var(--box-shadow);
      transition: var(--transition);
    }
    
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }
    
    .stat-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
    }
    
    .stat-icon {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
    }
    
    .icon-db {
      background: rgba(67, 97, 238, 0.1);
      color: var(--secondary);
    }
    
    .icon-mem {
      background: rgba(40, 167, 69, 0.1);
      color: var(--success);
    }
    
    .icon-anime {
      background: rgba(255, 193, 7, 0.1);
      color: var(--warning);
    }
    
    .icon-music {
      background: rgba(220, 53, 69, 0.1);
      color: var(--danger);
    }
    
    .icon-singer {
      background: rgba(23, 162, 184, 0.1);
      color: var(--info);
    }
    
    .icon-user {
      background: rgba(111, 66, 193, 0.1);
      color: #6f42c1;
    }
    
    .stat-value {
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 5px;
      color: var(--dark);
    }
    
    .stat-title {
      color: var(--gray);
      font-size: 14px;
    }
    
    /* Tabs */
    .tabs {
      display: flex;
      gap: 10px;
      margin-bottom: 30px;
      background: white;
      padding: 10px;
      border-radius: var(--border-radius);
      box-shadow: var(--box-shadow);
      flex-wrap: wrap;
    }
    
    .tab {
      padding: 12px 25px;
      cursor: pointer;
      border-radius: var(--border-radius);
      transition: var(--transition);
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .tab:hover {
      background: var(--light-gray);
    }
    
    .tab.active {
      background: var(--primary);
      color: white;
    }
    
    .tab-content {
      display: none;
      background: white;
      border-radius: var(--border-radius);
      padding: 30px;
      box-shadow: var(--box-shadow);
      margin-bottom: 30px;
    }
    
    .tab-content.active {
      display: block;
      animation: fadeIn 0.5s ease;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    /* Form Styles */
    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 20px;
      margin-bottom: 25px;
    }
    
    .form-group {
      margin-bottom: 15px;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: var(--dark);
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
    
    textarea.form-control {
      min-height: 100px;
      resize: vertical;
    }
    
    .form-section-title {
      font-size: 20px;
      margin: 30px 0 20px;
      padding-bottom: 10px;
      border-bottom: 2px solid var(--light-gray);
      color: var(--primary);
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    /* Content List */
    .content-list {
      margin-top: 30px;
    }
    
    .section-title {
      font-size: 20px;
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 2px solid var(--light-gray);
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .content-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 20px;
    }
    
    .content-card {
      background: white;
      border-radius: var(--border-radius);
      overflow: hidden;
      box-shadow: var(--box-shadow);
      transition: var(--transition);
    }
    
    .content-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    
    .card-header {
      padding: 15px;
      background: var(--primary);
      color: white;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .card-body {
      padding: 20px;
    }
    
    .card-title {
      font-size: 18px;
      margin-bottom: 10px;
      color: var(--dark);
    }
    
    .card-meta {
      color: var(--gray);
      font-size: 14px;
      margin-bottom: 15px;
      display: flex;
      flex-direction: column;
      gap: 5px;
    }
    
    .card-actions {
      display: flex;
      gap: 10px;
      border-top: 1px solid var(--light-gray);
      padding-top: 15px;
      margin-top: 15px;
    }
    
    .btn-sm {
      padding: 8px 15px;
      font-size: 14px;
    }
    
    .btn-outline {
      background: transparent;
      border: 1px solid currentColor;
    }
    
    .btn-edit {
      color: var(--secondary);
      border-color: var(--secondary);
    }
    
    .btn-edit:hover {
      background: var(--secondary);
      color: white;
    }
    
    .btn-delete {
      color: var(--danger);
      border-color: var(--danger);
    }
    
    .btn-delete:hover {
      background: var(--danger);
      color: white;
    }
    
    /* Messages */
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
    
    .message i {
      font-size: 22px;
    }
    
    /* Table Styles */
    .data-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
      background: white;
      border-radius: var(--border-radius);
      overflow: hidden;
      box-shadow: var(--box-shadow);
    }
    
    .data-table th {
      background: var(--primary);
      color: white;
      padding: 15px;
      text-align: right;
      font-weight: 600;
    }
    
    .data-table td {
      padding: 12px 15px;
      border-bottom: 1px solid var(--light-gray);
    }
    
    .data-table tr:last-child td {
      border-bottom: none;
    }
    
    .data-table tr:hover {
      background: rgba(0, 170, 111, 0.03);
    }
    
    .badge {
      padding: 5px 10px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 500;
    }
    
    .badge-primary {
      background: rgba(0, 170, 111, 0.1);
      color: var(--primary);
    }
    
    .badge-secondary {
      background: rgba(67, 97, 238, 0.1);
      color: var(--secondary);
    }
    
    .badge-success {
      background: rgba(40, 167, 69, 0.1);
      color: var(--success);
    }
    
    /* Footer */
    footer {
      text-align: center;
      padding: 20px;
      color: var(--gray);
      font-size: 14px;
      margin-top: 30px;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
      .form-grid {
        grid-template-columns: 1fr;
      }
      
      .content-grid {
        grid-template-columns: 1fr;
      }
      
      .tabs {
        flex-direction: column;
      }
      
      header {
        flex-direction: column;
        gap: 20px;
        text-align: center;
      }
      
      .user-actions {
        width: 100%;
        justify-content: center;
      }
      
      .stats-container {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <header>
      <div class="logo">
        <i class="fas fa-music"></i>
        <h1>پنل مدیریت انیمه موزیک</h1>
      </div>
      <div class="user-actions">
        <a href="logout.php" class="btn btn-danger">
          <i class="fas fa-sign-out-alt"></i>
          خروج از پنل
        </a>
      </div>
    </header>

    <div class="stats-container">
      <div class="stat-card">
        <div class="stat-header">
          <div>
            <div class="stat-value"><?= round($dbSize/1024/1024, 2) ?> MB</div>
            <div class="stat-title">حجم دیتابیس</div>
          </div>
          <div class="stat-icon icon-db">
            <i class="fas fa-database"></i>
          </div>
        </div>
      </div>
      
      <div class="stat-card">
        <div class="stat-header">
          <div>
            <div class="stat-value"><?= round($memoryUsage/1024/1024, 2) ?> MB</div>
            <div class="stat-title">مصرف حافظه</div>
          </div>
          <div class="stat-icon icon-mem">
            <i class="fas fa-memory"></i>
          </div>
        </div>
      </div>
      
      <div class="stat-card">
        <div class="stat-header">
          <div>
            <div class="stat-value"><?= $animeCount ?></div>
            <div class="stat-title">تعداد انیمه‌ها</div>
          </div>
          <div class="stat-icon icon-anime">
            <i class="fas fa-film"></i>
          </div>
        </div>
      </div>
      
      <div class="stat-card">
        <div class="stat-header">
          <div>
            <div class="stat-value"><?= $musicCount ?></div>
            <div class="stat-title">تعداد موزیک‌ها</div>
          </div>
          <div class="stat-icon icon-music">
            <i class="fas fa-music"></i>
          </div>
        </div>
      </div>
      
      <div class="stat-card">
        <div class="stat-header">
          <div>
            <div class="stat-value"><?= $singerCount ?></div>
            <div class="stat-title">تعداد خوانندگان</div>
          </div>
          <div class="stat-icon icon-singer">
            <i class="fas fa-microphone"></i>
          </div>
        </div>
      </div>
      
      <div class="stat-card">
        <div class="stat-header">
          <div>
            <div class="stat-value"><?= $userCount ?></div>
            <div class="stat-title">تعداد کاربران</div>
          </div>
          <div class="stat-icon icon-user">
            <i class="fas fa-users"></i>
          </div>
        </div>
      </div>
    </div>

    <?php
    // نمایش پیام‌های موفقیت
    if (isset($_GET['success'])) {
        $successMessages = [
            '1' => 'عملیات با موفقیت انجام شد',
            'anime_added' => 'انیمه جدید با موفقیت اضافه شد',
            'music_added' => 'موزیک جدید با موفقیت اضافه شد',
            'singer_added' => 'خواننده جدید با موفقیت اضافه شد',
            'content_updated' => 'محتوا با موفقیت بروزرسانی شد',
            'content_deleted' => 'محتوا با موفقیت حذف شد'
        ];
        
        $message = $_GET['success'];
        $displayMessage = '';
        if (isset($successMessages[$message])) {
            $displayMessage = $successMessages[$message];
        } elseif (isset($_GET['message'])) {
            $displayMessage = htmlspecialchars(urldecode($_GET['message']));
        } else {
            $displayMessage = 'عملیات موفقیت آمیز بود';
        }
        
        echo '<div class="message success-message">
                <i class="fas fa-check-circle"></i>
                <div>'.$displayMessage.'</div>
              </div>';
    }
    
    // نمایش پیام‌های خطا
    if (isset($_GET['error'])) {
        $errorMessages = [
            'invalid_request' => 'درخواست نامعتبر',
            'invalid_request_method' => 'متد درخواست نامعتبر',
            'database_error' => 'خطای پایگاه داده',
            'content_not_found' => 'محتوا یافت نشد',
            'missing_required_field' => 'فیلد اجباری پر نشده است: ' . ($_GET['field'] ?? ''),
            'operation_failed' => 'عملیات ناموفق بود',
            'delete_failed' => isset($_GET['message']) ? htmlspecialchars(urldecode($_GET['message'])) : 'حذف ناموفق بود'
        ];
        
        $error = $_GET['error'];
        $message = $_GET['message'] ?? '';
        
        $displayMessage = '';
        $details = '';
        
        if (isset($errorMessages[$error])) {
            $displayMessage = $errorMessages[$error];
        } else {
            $displayMessage = 'خطا در انجام عملیات';
        }
        
        if (!empty($message)) {
            $details = '<div style="font-size:14px; margin-top:8px;">جزئیات: ' . htmlspecialchars(urldecode($message)) . '</div>';
        }
        
        echo '<div class="message error-message">
                <i class="fas fa-exclamation-circle"></i>
                <div>'.$displayMessage.$details.'</div>
              </div>';
    }
    ?>

    <div class="tabs">
      <div class="tab active" onclick="switchTab('anime-content')">
        <i class="fas fa-film"></i>
        مدیریت انیمه‌ها
      </div>
      <div class="tab" onclick="switchTab('music-content')">
        <i class="fas fa-music"></i>
        مدیریت موزیک‌ها
      </div>
      <div class="tab" onclick="switchTab('singer-content')">
        <i class="fas fa-microphone"></i>
        مدیریت خوانندگان
      </div>
      <div class="tab" onclick="switchTab('user-content')">
        <i class="fas fa-users"></i>
        مدیریت کاربران
      </div>
      <div class="tab" onclick="switchTab('system-content')">
        <i class="fas fa-cog"></i>
        مدیریت سیستم
      </div>
    </div>

    <!-- تب مدیریت انیمه‌ها -->
    <div id="anime-content" class="tab-content active">
      <h2 class="form-section-title">
        <i class="fas fa-plus-circle"></i>
        افزودن انیمه جدید
      </h2>

      <form method="post" action="save_anime.php">
        <div class="form-grid">
          <div class="form-group">
            <label>عنوان فارسی:</label>
            <input type="text" name="title_fa" class="form-control" required>
          </div>
          
          <div class="form-group">
            <label>عنوان انگلیسی:</label>
            <input type="text" name="title_en" class="form-control" required>
          </div>
          
          <div class="form-group">
            <label>لینک تصویر پست:</label>
            <input type="text" name="poster_image_url" class="form-control" required>
          </div>
        </div>
        
        <div class="form-group">
          <label>توضیحات:</label>
          <textarea name="description" class="form-control" rows="4"></textarea>
        </div>
        
        <div class="form-group">
          <label>لینک‌های مهم (فرمت: نام=لینک, نام۲=لینک۲):</label>
          <textarea name="important_links" class="form-control" rows="2" placeholder="مثال: IMDb=https://imdb.com, MyAnimeList=https://myanimelist.net"></textarea>
        </div>
        
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save"></i>
          ذخیره انیمه
        </button>
      </form>

      <div class="content-list">
        <h3 class="section-title">
          <i class="fas fa-list"></i>
          آخرین انیمه‌ها
        </h3>
        
        <div class="content-grid">
          <?php if (!empty($latestAnime)): ?>
            <?php foreach ($latestAnime as $anime): ?>
              <div class="content-card">
                <div class="card-header">
                  <div><?= htmlspecialchars($anime['title_fa']) ?></div>
                  <span class="badge badge-primary">ID: <?= $anime['id'] ?></span>
                </div>
                <div class="card-body">
                  <div class="card-title"><?= htmlspecialchars($anime['title_en']) ?></div>
                  <div class="card-meta">
                    <span><i class="far fa-calendar"></i> <?= date('Y/m/d', strtotime($anime['created_at'])) ?></span>
                  </div>
                  <div class="card-actions">
                    <a href="edit_anime.php?id=<?= $anime['id'] ?>" class="btn btn-sm btn-outline btn-edit">
                      <i class="fas fa-edit"></i>
                      ویرایش
                    </a>
                    <a href="delete_anime.php?id=<?= $anime['id'] ?>" onclick="return confirm('آیا مطمئن هستید؟ این عمل غیرقابل بازگشت است')" class="btn btn-sm btn-outline btn-delete">
                      <i class="fas fa-trash"></i>
                      حذف
                    </a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p>هنوز انیمه‌ای ثبت نشده است</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- تب مدیریت موزیک‌ها -->
    <div id="music-content" class="tab-content">
      <h2 class="form-section-title">
        <i class="fas fa-plus-circle"></i>
        افزودن موزیک جدید
      </h2>

      <form method="post" action="save_music.php" enctype="multipart/form-data">
        <div class="form-grid">
          <div class="form-group">
            <label>انتخاب انیمه:</label>
            <select name="anime_id" class="form-control" required>
              <option value="">-- انتخاب انیمه --</option>
              <?php foreach ($latestAnime as $anime): ?>
                <option value="<?= $anime['id'] ?>"><?= htmlspecialchars($anime['title_fa']) ?> (<?= htmlspecialchars($anime['title_en']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="form-group">
            <label>نوع موزیک:</label>
            <select name="music_type_id" class="form-control" required>
              <option value="">-- انتخاب نوع --</option>
              <?php foreach ($musicTypes as $type): ?>
                <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="form-group">
            <label>فصل:</label>
            <input type="number" name="season_number" class="form-control" min="1" value="1">
          </div>
          
          <div class="form-group">
            <label>قسمت:</label>
            <input type="number" name="episode_number" class="form-control" min="1">
          </div>
        </div>
        
        <div class="form-grid">
          <div class="form-group">
            <label>عنوان موزیک:</label>
            <input type="text" name="title" class="form-control" required>
          </div>
          
          <div class="form-group">
            <label>لینک فایل موزیک:</label>
            <input type="text" name="music_file_url" class="form-control" required>
          </div>
          
          <div class="form-group">
            <label>لینک فایل ویدیو (اختیاری):</label>
            <input type="text" name="video_file_url" class="form-control">
          </div>
          
          <div class="form-group">
            <label>لینک تصویر (اختیاری):</label>
            <input type="text" name="image_url" class="form-control">
          </div>
        </div>
        
        <div class="form-grid">
          <div class="form-group">
            <label>مدت زمان (ثانیه):</label>
            <input type="number" name="duration" class="form-control" min="0">
          </div>
          
          <div class="form-group">
            <label>خوانندگان (ID جدا با کاما):</label>
            <input type="text" name="singer_ids" class="form-control" placeholder="مثال: 1,5,8">
          </div>
        </div>
        
        <div class="form-group">
          <label>متن آهنگ (اختیاری):</label>
          <textarea name="lyrics_text" class="form-control" rows="4"></textarea>
        </div>
        
        <div class="form-group">
          <label>ترجمه فارسی (اختیاری):</label>
          <textarea name="lyrics_translation" class="form-control" rows="4"></textarea>
        </div>
        
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save"></i>
          ذخیره موزیک
        </button>
      </form>

      <div class="content-list">
        <h3 class="section-title">
          <i class="fas fa-list"></i>
          آخرین موزیک‌ها
        </h3>
        
        <?php if (!empty($latestMusic)): ?>
          <div class="table-container">
            <table class="data-table">
              <thead>
                <tr>
                  <th>عنوان</th>
                  <th>انیمه</th>
                  <th>نوع</th>
                  <th>فصل</th>
                  <th>عملیات</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($latestMusic as $music): ?>
                  <tr>
                    <td><?= htmlspecialchars($music['title']) ?></td>
                    <td><?= htmlspecialchars($music['title_fa']) ?></td>
                    <td><span class="badge badge-secondary"><?= $music['music_type'] ?></span></td>
                    <td><?= $music['season_number'] ? 'فصل ' . $music['season_number'] : '-' ?></td>
                    <td>
                      <a href="edit_music.php?id=<?= $music['id'] ?>" class="btn btn-sm btn-outline btn-edit">
                        <i class="fas fa-edit"></i>
                        ویرایش
                      </a>
                      <a href="delete_music.php?id=<?= $music['id'] ?>" onclick="return confirm('آیا مطمئن هستید؟')" class="btn btn-sm btn-outline btn-delete">
                        <i class="fas fa-trash"></i>
                        حذف
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p>هنوز موزیکی ثبت نشده است</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- تب مدیریت خوانندگان -->
    <div id="singer-content" class="tab-content">
      <h2 class="form-section-title">
        <i class="fas fa-plus-circle"></i>
        افزودن خواننده جدید
      </h2>

      <form method="post" action="save_singer.php">
        <div class="form-grid">
          <div class="form-group">
            <label>نام خواننده:</label>
            <input type="text" name="name" class="form-control" required>
          </div>
          
          <div class="form-group">
            <label>لینک تصویر (اختیاری):</label>
            <input type="text" name="image_url" class="form-control">
          </div>
        </div>
        
        <div class="form-group">
          <label>بیوگرافی (اختیاری):</label>
          <textarea name="bio" class="form-control" rows="4"></textarea>
        </div>
        
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save"></i>
          ذخیره خواننده
        </button>
      </form>

      <div class="content-list">
        <h3 class="section-title">
          <i class="fas fa-list"></i>
          لیست خوانندگان
        </h3>
        
        <?php
        try {
          $singers = $db_content->query("SELECT * FROM singers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
          $singers = [];
        }
        ?>
        
        <?php if (!empty($singers)): ?>
          <div class="table-container">
            <table class="data-table">
              <thead>
                <tr>
                  <th>نام</th>
                  <th>تعداد آثار</th>
                  <th>عملیات</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($singers as $singer): 
                  // شمارش تعداد آثار هر خواننده
                  $stmt = $db_content->prepare("SELECT COUNT(*) FROM content_singers WHERE singer_id = ?");
                  $stmt->execute([$singer['id']]);
                  $musicCount = $stmt->fetchColumn();
                ?>
                  <tr>
                    <td><?= htmlspecialchars($singer['name']) ?></td>
                    <td><span class="badge badge-success"><?= $musicCount ?> اثر</span></td>
                    <td>
                      <a href="edit_singer.php?id=<?= $singer['id'] ?>" class="btn btn-sm btn-outline btn-edit">
                        <i class="fas fa-edit"></i>
                        ویرایش
                      </a>
                      <a href="delete_singer.php?id=<?= $singer['id'] ?>" onclick="return confirm('آیا مطمئن هستید؟')" class="btn btn-sm btn-outline btn-delete">
                        <i class="fas fa-trash"></i>
                        حذف
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p>هنوز خواننده‌ای ثبت نشده است</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- تب مدیریت کاربران -->
    <div id="user-content" class="tab-content">
      <h2 class="form-section-title">
        <i class="fas fa-users"></i>
        مدیریت کاربران
      </h2>
      
      <?php
      try {
        $users = $db_users->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
      } catch (PDOException $e) {
        $users = [];
      }
      ?>
      
      <?php if (!empty($users)): ?>
        <div class="table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th>نام کاربری</th>
                <th>نام کامل</th>
                <th>وضعیت</th>
                <th>تاریخ عضویت</th>
                <th>عملیات</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $user): ?>
                <tr>
                  <td><?= htmlspecialchars($user['username']) ?></td>
                  <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                  <td>
                    <span class="badge <?= $user['subscription_status'] === 'vip' ? 'badge-primary' : 'badge-secondary' ?>">
                      <?= $user['subscription_status'] === 'vip' ? 'VIP' : 'عادی' ?>
                    </span>
                  </td>
                  <td><?= date('Y/m/d', strtotime($user['created_at'])) ?></td>
                  <td>
                    <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-outline btn-edit">
                      <i class="fas fa-edit"></i>
                      ویرایش
                    </a>
                    <a href="delete_user.php?id=<?= $user['id'] ?>" onclick="return confirm('آیا مطمئن هستید؟')" class="btn btn-sm btn-outline btn-delete">
                      <i class="fas fa-trash"></i>
                      حذف
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p>هنوز کاربری ثبت نشده است</p>
      <?php endif; ?>
    </div>

    <!-- تب مدیریت سیستم -->
    <div id="system-content" class="tab-content">
      <h2 class="form-section-title">
        <i class="fas fa-cog"></i>
        مدیریت سیستم
      </h2>
      
      <div class="form-grid">
        <div class="stat-card">
          <div class="stat-header">
            <h3>پشتیبان‌گیری از دیتابیس</h3>
          </div>
          <div class="stat-actions" style="margin-top: 20px;">
            <a href="backup_database.php" class="btn btn-primary">
              <i class="fas fa-download"></i>
              دانلود فایل پشتیبان
            </a>
          </div>
        </div>
        
        <div class="stat-card">
          <div class="stat-header">
            <h3>مدیریت فایل‌ها</h3>
          </div>
          <div class="stat-actions" style="margin-top: 20px;">
            <a href="file_manager.php" class="btn btn-primary">
              <i class="fas fa-folder-open"></i>
              مدیریت فایل‌ها
            </a>
          </div>
        </div>
      </div>
      
      <div class="form-section-title">
        <i class="fas fa-chart-line"></i>
        آمار و گزارشات
      </div>
      
      <div class="form-grid">
        <a href="admin_stats.php" class="btn btn-secondary">
          <i class="fas fa-chart-pie"></i>
          آمار بازدید صفحه سایت
        </a>
        <a href="admin_ads.php" class="btn btn-secondary">
          <i class="fas fa-ad"></i>
          آمار بازدید تبلیغات سایت
        </a>
        <a href="import_json.php" class="btn btn-secondary">
          <i class="fas fa-upload"></i>
          واردات json
        </a>
        <a href="admin_search.php" class="btn btn-secondary">
          <i class="fas fa-search"></i>
          جستجو در دیتابیس
        </a>
        <a href="admin_all_content.php" class="btn btn-secondary">
          <i class="fas fa-database"></i>
          مشاهده همه محتوا دیتابیس
        </a>
        <a href="adscheck.php" class="btn btn-secondary">
          <i class="fas fa-chart-pie"></i>
          وضعیت رزرو تبلیغات
        </a>
      </div>
    </div>
    
    <footer>
      <p>پنل مدیریت انیمه موزیک | نسخه ۳.۰</p>
      <p>کلیه حقوق برای این پلتفرم محفوظ است © <?= date('Y') ?></p>
    </footer>
  </div>
  
  <script>
    function switchTab(tabId) {
      // مخفی کردن همه تب‌ها
      document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
      });
      
      // غیرفعال کردن همه دکمه‌های تب
      document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
      });
      
      // نمایش تب انتخاب شده
      document.getElementById(tabId).classList.add('active');
      
      // فعال کردن دکمه تب مربوطه
      event.currentTarget.classList.add('active');
      
      // اسکرول به بالای تب
      document.getElementById(tabId).scrollIntoView({behavior: 'smooth'});
    }
  </script>
</body>
</html>