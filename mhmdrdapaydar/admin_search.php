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
    
    // دریافت انواع موزیک
    $musicTypes = $db_content->query("SELECT * FROM music_types")->fetchAll(PDO::FETCH_ASSOC);
    
    // دریافت لیست انیمه‌ها
    $animeList = $db_content->query("SELECT id, title_fa FROM anime_series ORDER BY title_fa")->fetchAll(PDO::FETCH_ASSOC);
    
    // دریافت لیست خوانندگان
    $singersList = $db_content->query("SELECT id, name FROM singers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    
    // پردازش پارامترهای جستجو
    $searchType = $_GET['type'] ?? 'anime';
    $searchKeyword = $_GET['keyword'] ?? '';
    $animeFilter = $_GET['anime_filter'] ?? '';
    $musicTypeFilter = $_GET['music_type_filter'] ?? '';
    $singerFilter = $_GET['singer_filter'] ?? '';
    $statusFilter = $_GET['status_filter'] ?? '';
    
    $results = [];
    $totalResults = 0;
    
    // اجرای جستجو بر اساس نوع
    if (!empty($searchKeyword) || !empty($animeFilter) || !empty($musicTypeFilter) || !empty($singerFilter) || !empty($statusFilter)) {
        switch ($searchType) {
            case 'anime':
                $query = "SELECT * FROM anime_series WHERE 1=1";
                $params = [];
                
                if (!empty($searchKeyword)) {
                    $query .= " AND (title_fa LIKE ? OR title_en LIKE ? OR description LIKE ?)";
                    $params[] = "%$searchKeyword%";
                    $params[] = "%$searchKeyword%";
                    $params[] = "%$searchKeyword%";
                }
                
                $query .= " ORDER BY id DESC";
                
                $stmt = $db_content->prepare($query);
                $stmt->execute($params);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $totalResults = count($results);
                break;
                
            case 'music':
                $query = "
                    SELECT ac.*, asr.title_fa as anime_title, mt.name as music_type_name 
                    FROM anime_contents ac 
                    JOIN anime_series asr ON ac.anime_id = asr.id 
                    JOIN music_types mt ON ac.music_type_id = mt.id 
                    WHERE 1=1
                ";
                $params = [];
                
                if (!empty($searchKeyword)) {
                    $query .= " AND (ac.title LIKE ? OR asr.title_fa LIKE ? OR asr.title_en LIKE ?)";
                    $params[] = "%$searchKeyword%";
                    $params[] = "%$searchKeyword%";
                    $params[] = "%$searchKeyword%";
                }
                
                if (!empty($animeFilter)) {
                    $query .= " AND ac.anime_id = ?";
                    $params[] = $animeFilter;
                }
                
                if (!empty($musicTypeFilter)) {
                    $query .= " AND ac.music_type_id = ?";
                    $params[] = $musicTypeFilter;
                }
                
                // فیلتر خواننده
                if (!empty($singerFilter)) {
                    $query .= " AND ac.id IN (SELECT content_id FROM content_singers WHERE singer_id = ?)";
                    $params[] = $singerFilter;
                }
                
                $query .= " ORDER BY ac.id DESC";
                
                $stmt = $db_content->prepare($query);
                $stmt->execute($params);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $totalResults = count($results);
                break;
                
            case 'singer':
                $query = "SELECT * FROM singers WHERE 1=1";
                $params = [];
                
                if (!empty($searchKeyword)) {
                    $query .= " AND (name LIKE ? OR bio LIKE ?)";
                    $params[] = "%$searchKeyword%";
                    $params[] = "%$searchKeyword%";
                }
                
                $query .= " ORDER BY name";
                
                $stmt = $db_content->prepare($query);
                $stmt->execute($params);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $totalResults = count($results);
                break;
                
            case 'user':
                $query = "SELECT * FROM users WHERE 1=1";
                $params = [];
                
                if (!empty($searchKeyword)) {
                    $query .= " AND (username LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
                    $params[] = "%$searchKeyword%";
                    $params[] = "%$searchKeyword%";
                    $params[] = "%$searchKeyword%";
                    $params[] = "%$searchKeyword%";
                }
                
                if (!empty($statusFilter)) {
                    $query .= " AND subscription_status = ?";
                    $params[] = $statusFilter;
                }
                
                $query .= " ORDER BY created_at DESC";
                
                $stmt = $db_users->prepare($query);
                $stmt->execute($params);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $totalResults = count($results);
                break;
        }
    }
    
} catch (PDOException $e) {
    $error = "خطا در اتصال به پایگاه داده: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>جستجو و مدیریت پیشرفته - پنل مدیریت انیمه موزیک</title>
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
    
    .btn-secondary {
      background: var(--secondary);
      color: white;
    }
    
    .btn-secondary:hover {
      background: #3651d8;
      transform: translateY(-2px);
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
    
    .btn-sm {
      padding: 8px 15px;
      font-size: 14px;
    }
    
    /* Search Form */
    .search-form {
      background: white;
      border-radius: var(--border-radius);
      padding: 25px;
      box-shadow: var(--box-shadow);
      margin-bottom: 30px;
    }
    
    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-bottom: 20px;
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
    
    select.form-control {
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
      background-repeat: no-repeat;
      background-position: left 0.75rem center;
      background-size: 16px 12px;
      padding-left: 35px;
    }
    
    .search-actions {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 20px;
    }
    
    .results-count {
      font-weight: 600;
      color: var(--primary);
    }
    
    /* Results Table */
    .results-container {
      background: white;
      border-radius: var(--border-radius);
      padding: 25px;
      box-shadow: var(--box-shadow);
      margin-bottom: 30px;
      overflow-x: auto;
    }
    
    .data-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
      background: white;
      border-radius: var(--border-radius);
      overflow: hidden;
      min-width: 800px;
    }
    
    .data-table th {
      background: var(--primary);
      color: white;
      padding: 15px;
      text-align: right;
      font-weight: 600;
      position: sticky;
      top: 0;
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
    
    .badge-warning {
      background: rgba(255, 193, 7, 0.1);
      color: var(--warning);
    }
    
    .badge-danger {
      background: rgba(220, 53, 69, 0.1);
      color: var(--danger);
    }
    
    .action-buttons {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }
    
    .no-results {
      text-align: center;
      padding: 40px;
      color: var(--gray);
    }
    
    .no-results i {
      font-size: 50px;
      margin-bottom: 15px;
      color: var(--light-gray);
    }
    
    /* Quick Edit Modal */
    .modal-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }
    
    .modal {
      background: white;
      border-radius: var(--border-radius);
      width: 90%;
      max-width: 600px;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }
    
    .modal-header {
      padding: 20px;
      border-bottom: 1px solid var(--light-gray);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .modal-body {
      padding: 20px;
    }
    
    .modal-footer {
      padding: 20px;
      border-top: 1px solid var(--light-gray);
      display: flex;
      justify-content: flex-end;
      gap: 10px;
    }
    
    .close-modal {
      background: none;
      border: none;
      font-size: 24px;
      cursor: pointer;
      color: var(--gray);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
      .form-grid {
        grid-template-columns: 1fr;
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
      
      .search-actions {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
      }
      
      .action-buttons {
        justify-content: center;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <header>
      <div class="logo">
        <i class="fas fa-search"></i>
        <h1>جستجو و مدیریت پیشرفته محتوا</h1>
      </div>
      <div class="user-actions">
        <a href="admin.php" class="btn btn-secondary">
          <i class="fas fa-arrow-right"></i>
          بازگشت به پنل اصلی
        </a>
        <a href="logout.php" class="btn btn-delete">
          <i class="fas fa-sign-out-alt"></i>
          خروج از پنل
        </a>
      </div>
    </header>

    <div class="search-form">
      <form method="get" action="admin_search.php">
        <div class="form-grid">
          <div class="form-group">
            <label>نوع محتوا:</label>
            <select name="type" class="form-control" onchange="this.form.submit()">
              <option value="anime" <?= $searchType == 'anime' ? 'selected' : '' ?>>انیمه‌ها</option>
              <option value="music" <?= $searchType == 'music' ? 'selected' : '' ?>>موزیک‌ها</option>
              <option value="singer" <?= $searchType == 'singer' ? 'selected' : '' ?>>خوانندگان</option>
              <option value="user" <?= $searchType == 'user' ? 'selected' : '' ?>>کاربران</option>
            </select>
          </div>
          
          <div class="form-group">
            <label>کلمه کلیدی:</label>
            <input type="text" name="keyword" class="form-control" value="<?= htmlspecialchars($searchKeyword) ?>" placeholder="عبارت مورد نظر را وارد کنید...">
          </div>
          
          <?php if ($searchType == 'music'): ?>
            <div class="form-group">
              <label>فیلتر انیمه:</label>
              <select name="anime_filter" class="form-control">
                <option value="">همه انیمه‌ها</option>
                <?php foreach ($animeList as $anime): ?>
                  <option value="<?= $anime['id'] ?>" <?= $animeFilter == $anime['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($anime['title_fa']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            
            <div class="form-group">
              <label>فیلتر نوع موزیک:</label>
              <select name="music_type_filter" class="form-control">
                <option value="">همه انواع</option>
                <?php foreach ($musicTypes as $type): ?>
                  <option value="<?= $type['id'] ?>" <?= $musicTypeFilter == $type['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($type['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            
            <div class="form-group">
              <label>فیلتر خواننده:</label>
              <select name="singer_filter" class="form-control">
                <option value="">همه خوانندگان</option>
                <?php foreach ($singersList as $singer): ?>
                  <option value="<?= $singer['id'] ?>" <?= $singerFilter == $singer['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($singer['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>
          
          <?php if ($searchType == 'user'): ?>
            <div class="form-group">
              <label>فیلتر وضعیت:</label>
              <select name="status_filter" class="form-control">
                <option value="">همه وضعیت‌ها</option>
                <option value="vip" <?= $statusFilter == 'vip' ? 'selected' : '' ?>>VIP</option>
                <option value="normal" <?= $statusFilter == 'normal' ? 'selected' : '' ?>>عادی</option>
              </select>
            </div>
          <?php endif; ?>
        </div>
        
        <div class="search-actions">
          <div class="results-count">
            <?php if ($totalResults > 0): ?>
              <i class="fas fa-check-circle"></i> 
              <?= $totalResults ?> نتیجه یافت شد
            <?php elseif (!empty($searchKeyword) || !empty($animeFilter) || !empty($musicTypeFilter) || !empty($singerFilter) || !empty($statusFilter)): ?>
              <i class="fas fa-info-circle"></i> 
              نتیجه‌ای یافت نشد
            <?php else: ?>
              <i class="fas fa-search"></i> 
              معیارهای جستجو را وارد کنید
            <?php endif; ?>
          </div>
          
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-search"></i>
            اجرای جستجو
          </button>
        </div>
      </form>
    </div>

    <?php if ($totalResults > 0): ?>
      <div class="results-container">
        <h2>
          <i class="fas fa-list"></i>
          نتایج جستجو
        </h2>
        
        <?php if ($searchType == 'anime'): ?>
          <table class="data-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>عنوان فارسی</th>
                <th>عنوان انگلیسی</th>
                <th>تاریخ ایجاد</th>
                <th>عملیات</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($results as $anime): ?>
                <tr>
                  <td><?= $anime['id'] ?></td>
                  <td><?= htmlspecialchars($anime['title_fa']) ?></td>
                  <td><?= htmlspecialchars($anime['title_en']) ?></td>
                  <td><?= date('Y/m/d', strtotime($anime['created_at'])) ?></td>
                  <td>
                    <div class="action-buttons">
                      <a href="edit_anime.php?id=<?= $anime['id'] ?>" class="btn btn-sm btn-outline btn-edit">
                        <i class="fas fa-edit"></i>
                        ویرایش
                      </a>
                      <a href="delete_anime.php?id=<?= $anime['id'] ?>" onclick="return confirm('آیا مطمئن هستید؟')" class="btn btn-sm btn-outline btn-delete">
                        <i class="fas fa-trash"></i>
                        حذف
                      </a>
                      <a href="../anime.php?id=<?= $anime['id'] ?>" target="_blank" class="btn btn-sm btn-outline btn-primary">
                        <i class="fas fa-eye"></i>
                        مشاهده
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          
        <?php elseif ($searchType == 'music'): ?>
          <table class="data-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>عنوان</th>
                <th>انیمه</th>
                <th>نوع</th>
                <th>فصل/قسمت</th>
                <th>عملیات</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($results as $music): ?>
                <tr>
                  <td><?= $music['id'] ?></td>
                  <td><?= htmlspecialchars($music['title']) ?></td>
                  <td><?= htmlspecialchars($music['anime_title']) ?></td>
                  <td><span class="badge badge-secondary"><?= $music['music_type_name'] ?></span></td>
                  <td>
                    <?php if ($music['season_number']): ?>
                      فصل <?= $music['season_number'] ?>
                    <?php endif; ?>
                    <?php if ($music['episode_number']): ?>
                      / قسمت <?= $music['episode_number'] ?>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="action-buttons">
                      <a href="edit_music.php?id=<?= $music['id'] ?>" class="btn btn-sm btn-outline btn-edit">
                        <i class="fas fa-edit"></i>
                        ویرایش
                      </a>
                      <a href="delete_music.php?id=<?= $music['id'] ?>" onclick="return confirm('آیا مطمئن هستید؟')" class="btn btn-sm btn-outline btn-delete">
                        <i class="fas fa-trash"></i>
                        حذف
                      </a>
                      <a href="../music.php?id=<?= $music['id'] ?>" target="_blank" class="btn btn-sm btn-outline btn-primary">
                        <i class="fas fa-eye"></i>
                        مشاهده
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          
        <?php elseif ($searchType == 'singer'): ?>
          <table class="data-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>نام</th>
                <th>تاریخ ایجاد</th>
                <th>عملیات</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($results as $singer): ?>
                <tr>
                  <td><?= $singer['id'] ?></td>
                  <td><?= htmlspecialchars($singer['name']) ?></td>
                  <td><?= date('Y/m/d', strtotime($singer['created_at'])) ?></td>
                  <td>
                    <div class="action-buttons">
                      <a href="edit_singer.php?id=<?= $singer['id'] ?>" class="btn btn-sm btn-outline btn-edit">
                        <i class="fas fa-edit"></i>
                        ویرایش
                      </a>
                      <a href="delete_singer.php?id=<?= $singer['id'] ?>" onclick="return confirm('آیا مطمئن هستید؟')" class="btn btn-sm btn-outline btn-delete">
                        <i class="fas fa-trash"></i>
                        حذف
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          
        <?php elseif ($searchType == 'user'): ?>
          <table class="data-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>نام کاربری</th>
                <th>نام کامل</th>
                <th>وضعیت</th>
                <th>تاریخ عضویت</th>
                <th>عملیات</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($results as $user): ?>
                <tr>
                  <td><?= $user['id'] ?></td>
                  <td><?= htmlspecialchars($user['username']) ?></td>
                  <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                  <td>
                    <span class="badge <?= $user['subscription_status'] === 'vip' ? 'badge-primary' : 'badge-secondary' ?>">
                      <?= $user['subscription_status'] === 'vip' ? 'VIP' : 'عادی' ?>
                    </span>
                  </td>
                  <td><?= date('Y/m/d', strtotime($user['created_at'])) ?></td>
                  <td>
                    <div class="action-buttons">
                      <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-outline btn-edit">
                        <i class="fas fa-edit"></i>
                        ویرایش
                      </a>
                      <a href="delete_user.php?id=<?= $user['id'] ?>" onclick="return confirm('آیا مطمئن هستید؟')" class="btn btn-sm btn-outline btn-delete">
                        <i class="fas fa-trash"></i>
                        حذف
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    <?php elseif (!empty($searchKeyword) || !empty($animeFilter) || !empty($musicTypeFilter) || !empty($singerFilter) || !empty($statusFilter)): ?>
      <div class="no-results">
        <i class="fas fa-search"></i>
        <h3>نتیجه‌ای یافت نشد</h3>
        <p>لطفاً معیارهای جستجو را تغییر دهید و دوباره امتحان کنید.</p>
      </div>
    <?php else: ?>
      <div class="no-results">
        <i class="fas fa-filter"></i>
        <h3>جستجوی پیشرفته محتوا</h3>
        <p>برای مشاهده نتایج، معیارهای جستجو را انتخاب و اعمال کنید.</p>
      </div>
    <?php endif; ?>
  </div>

  <script>
    // تابع برای تغییر خودکار نوع محتوا و ارسال فرم
    function changeContentType(type) {
      document.querySelector('select[name="type"]').value = type;
      document.querySelector('form').submit();
    }
    
    // نمایش پیام تأیید قبل از حذف
    document.addEventListener('DOMContentLoaded', function() {
      const deleteButtons = document.querySelectorAll('.btn-delete');
      deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
          if (!confirm('آیا از حذف این آیتم اطمینان دارید؟ این عمل غیرقابل بازگشت است.')) {
            e.preventDefault();
          }
        });
      });
    });
  </script>
</body>
</html>