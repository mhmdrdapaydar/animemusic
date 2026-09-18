<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// اتصال به دیتابیس‌ها
try {
    // اتصال به دیتابیس محتوا
    $db_content = new PDO('sqlite:' . __DIR__ . '/../db/content.db');
    $db_content->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // اتصال به دیتابیس کاربران
    $db_users = new PDO('sqlite:' . __DIR__ . '/../db/users.db');
    $db_users->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // دریافت انواع موزیک
    $musicTypes = $db_content->query("SELECT * FROM music_types")->fetchAll(PDO::FETCH_ASSOC);
    
    // دریافت لیست انیمه‌ها
    $animeList = $db_content->query("SELECT id, title_fa FROM anime_series ORDER BY title_fa")->fetchAll(PDO::FETCH_ASSOC);
    
    // دریافت لیست خوانندگان
    $singersList = $db_content->query("SELECT id, name FROM singers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    
    // تعیین نوع محتوا از URL
    $contentType = $_GET['type'] ?? 'anime';
    
    // پارامترهای صفحه‌بندی
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = 10;
    $offset = ($page - 1) * $perPage;
    
    // متغیرهای مورد نیاز
    $totalItems = 0;
    $totalPages = 0;
    $items = [];
    
    // بر اساس نوع محتوا، داده‌ها را بگیر
    switch ($contentType) {
        case 'anime':
            $totalItems = $db_content->query("SELECT COUNT(*) FROM anime_series")->fetchColumn();
            $totalPages = ceil($totalItems / $perPage);
            
            $stmt = $db_content->prepare("SELECT * FROM anime_series ORDER BY id DESC LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'music':
            $totalItems = $db_content->query("SELECT COUNT(*) FROM anime_contents")->fetchColumn();
            $totalPages = ceil($totalItems / $perPage);
            
            $stmt = $db_content->prepare("
                SELECT ac.*, asr.title_fa as anime_title, mt.name as music_type_name 
                FROM anime_contents ac 
                JOIN anime_series asr ON ac.anime_id = asr.id 
                JOIN music_types mt ON ac.music_type_id = mt.id 
                ORDER BY ac.id DESC LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'singer':
            $totalItems = $db_content->query("SELECT COUNT(*) FROM singers")->fetchColumn();
            $totalPages = ceil($totalItems / $perPage);
            
            $stmt = $db_content->prepare("SELECT * FROM singers ORDER BY name LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'user':
            $totalItems = $db_users->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $totalPages = ceil($totalItems / $perPage);
            
            $stmt = $db_users->prepare("SELECT * FROM users ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
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
  <title>مدیریت همه محتوا - پنل مدیریت انیمه موزیک</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn.min.css" rel="stylesheet" />
  <style>
    /* استایل‌ها مشابه فایل admin_search.php */
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
    
    /* Pagination */
    .pagination {
      display: flex;
      justify-content: center;
      gap: 10px;
      margin-top: 30px;
    }
    
    .pagination a, .pagination span {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 40px;
      height: 40px;
      padding: 0 10px;
      border-radius: var(--border-radius);
      background: white;
      color: var(--dark);
      text-decoration: none;
      font-weight: 500;
      box-shadow: var(--box-shadow);
      transition: var(--transition);
    }
    
    .pagination a:hover {
      background: var(--primary);
      color: white;
    }
    
    .pagination .current {
      background: var(--primary);
      color: white;
    }
    
    .pagination .disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
      header {
        flex-direction: column;
        gap: 20px;
        text-align: center;
      }
      
      .user-actions {
        width: 100%;
        justify-content: center;
      }
      
      .tabs {
        flex-direction: column;
      }
      
      .action-buttons {
        justify-content: center;
      }
      
      .pagination {
        flex-wrap: wrap;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <header>
      <div class="logo">
        <i class="fas fa-list"></i>
        <h1>مدیریت همه محتواها</h1>
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

    <div class="tabs">
      <div class="tab <?= $contentType == 'anime' ? 'active' : '' ?>" onclick="changeContentType('anime')">
        <i class="fas fa-film"></i>
        انیمه‌ها (<?= $contentType == 'anime' ? $totalItems : $db_content->query("SELECT COUNT(*) FROM anime_series")->fetchColumn() ?>)
      </div>
      <div class="tab <?= $contentType == 'music' ? 'active' : '' ?>" onclick="changeContentType('music')">
        <i class="fas fa-music"></i>
        موزیک‌ها (<?= $contentType == 'music' ? $totalItems : $db_content->query("SELECT COUNT(*) FROM anime_contents")->fetchColumn() ?>)
      </div>
      <div class="tab <?= $contentType == 'singer' ? 'active' : '' ?>" onclick="changeContentType('singer')">
        <i class="fas fa-microphone"></i>
        خوانندگان (<?= $contentType == 'singer' ? $totalItems : $db_content->query("SELECT COUNT(*) FROM singers")->fetchColumn() ?>)
      </div>
      <div class="tab <?= $contentType == 'user' ? 'active' : '' ?>" onclick="changeContentType('user')">
        <i class="fas fa-users"></i>
        کاربران (<?= $contentType == 'user' ? $totalItems : $db_users->query("SELECT COUNT(*) FROM users")->fetchColumn() ?>)
      </div>
    </div>

    <div class="results-container">
      <h2>
        <i class="fas fa-list"></i>
        لیست <?php
          switch($contentType) {
            case 'anime': echo 'انیمه‌ها'; break;
            case 'music': echo 'موزیک‌ها'; break;
            case 'singer': echo 'خوانندگان'; break;
            case 'user': echo 'کاربران'; break;
          }
        ?>
      </h2>
      
      <?php if (count($items) > 0): ?>
        <?php if ($contentType == 'anime'): ?>
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
              <?php foreach ($items as $anime): ?>
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
          
        <?php elseif ($contentType == 'music'): ?>
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
              <?php foreach ($items as $music): ?>
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
          
        <?php elseif ($contentType == 'singer'): ?>
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
              <?php foreach ($items as $singer): ?>
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
          
        <?php elseif ($contentType == 'user'): ?>
          <table class="data-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>نام کاربری</th>
                <th>نام کامل</th>
                <th>وضعیت</th>
                <th>اعتبار اشتراک</th>
                <th>تاریخ عضویت</th>
                <th>عملیات</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $user): ?>
                <tr>
                  <td><?= $user['id'] ?></td>
                  <td><?= htmlspecialchars($user['username']) ?></td>
                  <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                  <td>
                    <span class="badge <?= $user['subscription_status'] === 'vip' ? 'badge-primary' : 'badge-secondary' ?>">
                      <?= $user['subscription_status'] === 'vip' ? 'VIP' : 'عادی' ?>
                    </span>
                  </td>
                  <td>
                    <?php if ($user['subscription_status'] === 'vip' && !empty($user['subscription_end_date'])): ?>
                      <?= date('Y/m/d', strtotime($user['subscription_end_date'])) ?>
                    <?php elseif ($user['subscription_status'] === 'vip'): ?>
                      <span class="badge badge-success">نامحدود</span>
                    <?php else: ?>
                      —
                    <?php endif; ?>
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
        
        <!-- صفحه‌بندی -->
        <?php if ($totalPages > 1): ?>
          <div class="pagination">
            <?php if ($page > 1): ?>
              <a href="?type=<?= $contentType ?>&page=<?= $page - 1 ?>"><i class="fas fa-chevron-right"></i></a>
            <?php else: ?>
              <span class="disabled"><i class="fas fa-chevron-right"></i></span>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <?php if ($i == $page): ?>
                <span class="current"><?= $i ?></span>
              <?php else: ?>
                <a href="?type=<?= $contentType ?>&page=<?= $i ?>"><?= $i ?></a>
              <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
              <a href="?type=<?= $contentType ?>&page=<?= $page + 1 ?>"><i class="fas fa-chevron-left"></i></a>
            <?php else: ?>
              <span class="disabled"><i class="fas fa-chevron-left"></i></span>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        
      <?php else: ?>
        <div class="no-results">
          <i class="fas fa-inbox"></i>
          <h3>محتوایی وجود ندارد</h3>
          <p>هنوز هیچ <?php
            switch($contentType) {
              case 'anime': echo 'انیمه‌ای'; break;
              case 'music': echo 'موزیکی'; break;
              case 'singer': echo 'خواننده‌ای'; break;
              case 'user': echo 'کاربری'; break;
            }
          ?> ثبت نشده است.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <script>
    // تابع برای تغییر نوع محتوا
    function changeContentType(type) {
      window.location.href = `?type=${type}&page=1`;
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