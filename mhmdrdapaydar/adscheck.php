<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$ads_file = __DIR__ . '/../ads.json';
$ads = [];

if (file_exists($ads_file)) {
    $ads = json_decode(file_get_contents($ads_file), true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $ad_id = $_POST['ad_id'] ?? '';
    
    if ($action && $ad_id) {
        foreach ($ads as &$ad) {
            if ($ad['id'] === $ad_id) {
                switch ($action) {
                    case 'accept':
                        $ad['status'] = 'accepted';
                        $ad['accepted_date'] = date('Y-m-d H:i:s');
                        break;
                    case 'reject':
                        $ad['status'] = 'rejected';
                        break;
                    case 'complete_with_details':
                        $ad['status'] = 'completed';
                        $ad['completed_date'] = date('Y-m-d H:i:s');
                        $ad['completed_views'] = $_POST['completed_views'] ?? 0;
                        $ad['completed_notes'] = $_POST['completed_notes'] ?? '';
                        break;
                    case 'edit':
                        $ad['name'] = $_POST['name'] ?? $ad['name'];
                        $ad['description'] = $_POST['description'] ?? $ad['description'];
                        $ad['url'] = $_POST['url'] ?? $ad['url'];
                        $ad['aparat_url'] = $_POST['aparat_url'] ?? $ad['aparat_url'];
                        if ($ad['status'] === 'completed') {
                            $ad['completed_views'] = $_POST['completed_views'] ?? $ad['completed_views'] ?? 0;
                            $ad['completed_notes'] = $_POST['completed_notes'] ?? $ad['completed_notes'] ?? '';
                        }
                        break;
                    case 'delete':
                        $ads = array_filter($ads, function($item) use ($ad_id) {
                            return $item['id'] !== $ad_id;
                        });
                        break;
                }
                break;
            }
        }
        
        file_put_contents($ads_file, json_encode(array_values($ads), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <title>پنل مدیریت تبلیغات</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    :root {
      --primary-color: #a8e12e;
      --secondary-color: #ffd700;
      --accent-color: #ff6b00;
      --bg-dark: #121212;
      --card-bg: #1e1e1e;
      --text-light: #f0f0f0;
    }
    
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    
    body {
      background: var(--bg-dark);
      color: var(--text-light);
      font-family: 'Vazirmatn', sans-serif;
      line-height: 1.6;
      min-height: 100vh;
      padding: 20px;
    }

    .header {
      background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
      padding: 12px 20px;
      text-align: center;
      box-shadow: 0 4px 15px rgba(0,0,0,0.3);
      margin-bottom: 30px;
      border-radius: 8px;
    }
    
    .page-title {
      text-align: center;
      margin: 20px 0 30px;
      font-size: 2rem;
      color: var(--secondary-color);
      padding: 0 15px;
      text-shadow: 0 2px 4px rgba(0,0,0,0.5);
    }
    
    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 15px;
    }
    
    .status-filters {
      display: flex;
      justify-content: center;
      gap: 15px;
      margin-bottom: 30px;
      flex-wrap: wrap;
    }
    
    .status-btn {
      background: var(--card-bg);
      border: 1px solid #444;
      color: #ddd;
      padding: 8px 20px;
      border-radius: 30px;
      cursor: pointer;
      transition: all 0.3s;
    }
    
    .status-btn.active {
      background: var(--primary-color);
      color: #000;
      border-color: var(--primary-color);
    }
    
    .ads-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
      gap: 25px;
      margin-top: 20px;
    }
    
    .ad-card {
      background: var(--card-bg);
      border-radius: 12px;
      padding: 25px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
      border-left: 4px solid var(--primary-color);
      position: relative;
    }
    
    .ad-card h3 {
      color: var(--primary-color);
      margin-bottom: 15px;
      font-size: 1.4rem;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .ad-meta {
      display: flex;
      justify-content: space-between;
      margin-bottom: 15px;
      padding-bottom: 15px;
      border-bottom: 1px solid #333;
    }
    
    .ad-detail {
      margin-bottom: 15px;
      line-height: 1.7;
    }
    
    .ad-link {
      display: block;
      margin: 10px 0;
      color: var(--accent-color);
      text-decoration: none;
      word-break: break-all;
    }
    
    .ad-link:hover {
      text-decoration: underline;
    }
    
    .status-badge {
      display: inline-block;
      padding: 5px 12px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: bold;
      margin-top: 10px;
    }
    
    .status-pending {
      background: rgba(255, 193, 7, 0.2);
      color: #ffc107;
      border: 1px solid #ffc107;
    }
    
    .status-accepted {
      background: rgba(40, 167, 69, 0.2);
      color: #28a745;
      border: 1px solid #28a745;
    }
    
    .status-rejected {
      background: rgba(220, 53, 69, 0.2);
      color: #dc3545;
      border: 1px solid #dc3545;
    }
    
    .status-completed {
      background: rgba(0, 123, 255, 0.2);
      color: #007bff;
      border: 1px solid #007bff;
    }
    
    .actions {
      display: flex;
      gap: 10px;
      margin-top: 20px;
      flex-wrap: wrap;
    }
    
    .action-btn {
      flex: 1;
      min-width: 120px;
      padding: 8px 15px;
      border-radius: 6px;
      border: none;
      cursor: pointer;
      font-weight: bold;
      transition: all 0.3s;
      text-align: center;
      font-size: 0.9rem;
    }
    
    .btn-accept {
      background: rgba(40, 167, 69, 0.2);
      color: #28a745;
      border: 1px solid #28a745;
    }
    
    .btn-accept:hover {
      background: rgba(40, 167, 69, 0.3);
    }
    
    .btn-reject {
      background: rgba(220, 53, 69, 0.2);
      color: #dc3545;
      border: 1px solid #dc3545;
    }
    
    .btn-reject:hover {
      background: rgba(220, 53, 69, 0.3);
    }
    
    .btn-complete {
      background: rgba(0, 123, 255, 0.2);
      color: #007bff;
      border: 1px solid #007bff;
    }
    
    .btn-complete:hover {
      background: rgba(0, 123, 255, 0.3);
    }
    
    .btn-edit {
      background: rgba(255, 193, 7, 0.2);
      color: #ffc107;
      border: 1px solid #ffc107;
    }
    
    .btn-edit:hover {
      background: rgba(255, 193, 7, 0.3);
    }
    
    .btn-delete {
      background: rgba(108, 117, 125, 0.2);
      color: #6c757d;
      border: 1px solid #6c757d;
    }
    
    .btn-delete:hover {
      background: rgba(108, 117, 125, 0.3);
    }
    
    .date-info {
      font-size: 0.85rem;
      color: #aaa;
      margin-top: 10px;
    }
    
    /* استایل‌های مودال */
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.7);
      z-index: 1000;
      justify-content: center;
      align-items: center;
      padding: 20px;
    }
    
    .modal-content {
      background: var(--card-bg);
      border-radius: 12px;
      padding: 30px;
      width: 100%;
      max-width: 500px;
      box-shadow: 0 5px 25px rgba(0,0,0,0.5);
      position: relative;
    }
    
    .close-modal {
      position: absolute;
      top: 15px;
      left: 15px;
      font-size: 1.5rem;
      color: #aaa;
      cursor: pointer;
    }
    
    .modal-title {
      text-align: center;
      margin-bottom: 20px;
      color: var(--primary-color);
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 8px;
      color: #ddd;
    }
    
    .form-group input,
    .form-group textarea {
      width: 100%;
      padding: 10px 15px;
      border-radius: 6px;
      background: #2d2d2d;
      border: 1px solid #444;
      color: #fff;
      font-family: inherit;
    }
    
    .form-group textarea {
      min-height: 100px;
    }
    
    .submit-btn {
      background: var(--primary-color);
      color: #000;
      border: none;
      padding: 12px 20px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: bold;
      width: 100%;
      font-size: 1rem;
    }
    
    .completed-info {
      margin: 10px 0;
      padding: 10px;
      background: rgba(0, 123, 255, 0.1);
      border-radius: 6px;
      border-left: 3px solid #007bff;
    }
    
    @media (max-width: 768px) {
      .ads-grid {
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      }
      
      .modal-content {
        padding: 20px;
      }
    }
    
    @media (max-width: 480px) {
      .status-filters {
        gap: 8px;
      }
      
      .status-btn {
        padding: 6px 15px;
        font-size: 0.9rem;
      }
      
      .ad-card {
        padding: 20px;
      }
      
      .actions {
        flex-direction: column;
      }
      
      .action-btn {
        width: 100%;
      }
    }
</style>
</head>
<body>
  
  <div class="header">
    <h1>پنل مدیریت تبلیغات</h1>
  </div>

  <div class="container">
    <h2 class="page-title">مدیریت درخواست‌های تبلیغات</h2>
    
    <div class="status-filters">
      <button class="status-btn active" data-status="all">همه</button>
      <button class="status-btn" data-status="pending">در انتظار بررسی</button>
      <button class="status-btn" data-status="accepted">پذیرفته شده</button>
      <button class="status-btn" data-status="rejected">رد شده</button>
      <button class="status-btn" data-status="completed">تکمیل شده</button>
    </div>
    
    <div class="ads-grid">
      <?php foreach ($ads as $ad): ?>
        <?php
          $status_class = '';
          $status_text = '';
          switch ($ad['status']) {
            case 'pending':
              $status_class = 'status-pending';
              $status_text = 'در انتظار بررسی';
              break;
            case 'accepted':
              $status_class = 'status-accepted';
              $status_text = 'پذیرفته شده';
              break;
            case 'rejected':
              $status_class = 'status-rejected';
              $status_text = 'رد شده';
              break;
            case 'completed':
              $status_class = 'status-completed';
              $status_text = 'تکمیل شده';
              break;
          }
        ?>
        <div class="ad-card" data-status="<?= $ad['status'] ?>">
          <h3><i class="fas fa-ad"></i> <?= htmlspecialchars($ad['name']) ?></h3>
          
          <div class="ad-meta">
            <div>
              <strong>شناسه:</strong> <?= substr($ad['id'], 0, 8) ?>
            </div>
            <div>
              <strong>تاریخ ثبت:</strong> <?= $ad['timestamp'] ?>
            </div>
          </div>
          
          <div class="ad-detail">
            <strong>توضیحات:</strong><br>
            <?= nl2br(htmlspecialchars($ad['description'])) ?>
          </div>
          
          <a href="<?= htmlspecialchars($ad['url']) ?>" class="ad-link" target="_blank">
            <i class="fas fa-link"></i> <?= htmlspecialchars($ad['url']) ?>
          </a>
          
          <a href="<?= htmlspecialchars($ad['aparat_url']) ?>" class="ad-link" target="_blank">
            <i class="fab fa-youtube"></i> لینک آپارات
          </a>
          
          <div class="status-badge <?= $status_class ?>">
            <?= $status_text ?>
          </div>
          
          <?php if (isset($ad['accepted_date'])): ?>
            <div class="date-info">
              <i class="fas fa-calendar-check"></i> تاریخ پذیرش: <?= $ad['accepted_date'] ?>
            </div>
          <?php endif; ?>
          
          <?php if (isset($ad['completed_date'])): ?>
            <div class="date-info">
              <i class="fas fa-calendar-check"></i> تاریخ تکمیل: <?= $ad['completed_date'] ?>
            </div>
          <?php endif; ?>
          
          <?php if ($ad['status'] === 'completed' && isset($ad['completed_views'])): ?>
            <div class="completed-info">
              <strong>بازدید انجام شده:</strong> <?= $ad['completed_views'] ?>
            </div>
          <?php endif; ?>
          
          <?php if ($ad['status'] === 'completed' && isset($ad['completed_notes'])): ?>
            <div class="completed-info">
              <strong>توضیحات تکمیل:</strong><br>
              <?= nl2br(htmlspecialchars($ad['completed_notes'])) ?>
            </div>
          <?php endif; ?>
          
          <div class="actions">
            <form method="POST" style="width: 100%;">
              <input type="hidden" name="ad_id" value="<?= $ad['id'] ?>">
              
              <?php if ($ad['status'] === 'pending'): ?>
                <button type="submit" name="action" value="accept" class="action-btn btn-accept">
                  <i class="fas fa-check"></i> پذیرش
                </button>
                <button type="submit" name="action" value="reject" class="action-btn btn-reject">
                  <i class="fas fa-times"></i> رد درخواست
                </button>
              <?php endif; ?>
              
              <?php if ($ad['status'] === 'accepted'): ?>
                <button type="button" onclick="openCompleteModal('<?= $ad['id'] ?>')" class="action-btn btn-complete">
                  <i class="fas fa-flag-checkered"></i> تکمیل شده
                </button>
              <?php endif; ?>
              
              <?php if ($ad['status'] === 'completed'): ?>
                <button type="button" onclick="openEditModal(
                  '<?= $ad['id'] ?>',
                  `<?= htmlspecialchars($ad['name'], ENT_QUOTES) ?>`,
                  `<?= htmlspecialchars($ad['description'], ENT_QUOTES) ?>`,
                  `<?= htmlspecialchars($ad['url'], ENT_QUOTES) ?>`,
                  `<?= htmlspecialchars($ad['aparat_url'], ENT_QUOTES) ?>`,
                  '<?= $ad['completed_views'] ?? 0 ?>',
                  `<?= htmlspecialchars($ad['completed_notes'] ?? '', ENT_QUOTES) ?>`
                )" class="action-btn btn-edit">
                  <i class="fas fa-edit"></i> ویرایش
                </button>
              <?php endif; ?>
              
              <button type="submit" name="action" value="delete" class="action-btn btn-delete">
                <i class="fas fa-trash"></i> حذف
              </button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
      
      <?php if (empty($ads)): ?>
        <div class="ad-card">
          <h3><i class="fas fa-info-circle"></i> هیچ تبلیغی یافت نشد</h3>
          <p>در حال حاضر هیچ درخواست تبلیغاتی برای نمایش وجود ندارد.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
  
  <!-- مودال تکمیل تبلیغ -->
  <div id="completeModal" class="modal">
    <div class="modal-content">
      <span class="close-modal" onclick="closeModal('completeModal')">&times;</span>
      <h3 class="modal-title">تکمیل تبلیغ</h3>
      <form method="POST" id="completeForm">
        <input type="hidden" name="ad_id" id="complete_ad_id">
        <input type="hidden" name="action" value="complete_with_details">
        
        <div class="form-group">
          <label for="completed_views">تعداد بازدید انجام شده:</label>
          <input type="number" id="completed_views" name="completed_views" required min="1">
        </div>
        
        <div class="form-group">
          <label for="completed_notes">توضیحات تکمیل تبلیغ:</label>
          <textarea id="completed_notes" name="completed_notes" required></textarea>
        </div>
        
        <button type="submit" class="submit-btn">ثبت اطلاعات</button>
      </form>
    </div>
  </div>
  
  <!-- مودال ویرایش تبلیغ -->
  <div id="editModal" class="modal">
    <div class="modal-content">
      <span class="close-modal" onclick="closeModal('editModal')">&times;</span>
      <h3 class="modal-title">ویرایش تبلیغ</h3>
      <form method="POST" id="editForm">
        <input type="hidden" name="ad_id" id="edit_ad_id">
        <input type="hidden" name="action" value="edit">
        
        <div class="form-group">
          <label for="edit_name">نام تبلیغ:</label>
          <input type="text" id="edit_name" name="name" required>
        </div>
        
        <div class="form-group">
          <label for="edit_description">توضیحات:</label>
          <textarea id="edit_description" name="description" required></textarea>
        </div>
        
        <div class="form-group">
          <label for="edit_url">لینک وبسایت:</label>
          <input type="url" id="edit_url" name="url" required>
        </div>
        
        <div class="form-group">
          <label for="edit_aparat_url">لینک آپارات:</label>
          <input type="url" id="edit_aparat_url" name="aparat_url" required>
        </div>
        
        <div class="form-group">
          <label for="edit_completed_views">تعداد بازدید انجام شده:</label>
          <input type="number" id="edit_completed_views" name="completed_views" required min="1">
        </div>
        
        <div class="form-group">
          <label for="edit_completed_notes">توضیحات تکمیل تبلیغ:</label>
          <textarea id="edit_completed_notes" name="completed_notes" required></textarea>
        </div>
        
        <button type="submit" class="submit-btn">ذخیره تغییرات</button>
      </form>
    </div>
  </div>
  
  <script>
    // مدیریت فیلتر وضعیت‌ها
    const statusBtns = document.querySelectorAll('.status-btn');
    const adCards = document.querySelectorAll('.ad-card');
    
    statusBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        const status = btn.dataset.status;
        
        statusBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        adCards.forEach(card => {
          if (status === 'all') {
            card.style.display = 'block';
          } else {
            card.style.display = card.dataset.status === status ? 'block' : 'none';
          }
        });
      });
    });
    
    // مدیریت مودال‌ها
    function openCompleteModal(adId) {
      document.getElementById('complete_ad_id').value = adId;
      document.getElementById('completeModal').style.display = 'flex';
    }
    
    function openEditModal(adId, name, description, url, aparatUrl, views, notes) {
      document.getElementById('edit_ad_id').value = adId;
      document.getElementById('edit_name').value = name;
      document.getElementById('edit_description').value = description;
      document.getElementById('edit_url').value = url;
      document.getElementById('edit_aparat_url').value = aparatUrl;
      document.getElementById('edit_completed_views').value = views;
      document.getElementById('edit_completed_notes').value = notes;
      document.getElementById('editModal').style.display = 'flex';
    }
    
    function closeModal(modalId) {
      document.getElementById(modalId).style.display = 'none';
    }
    
    // بستن مودال با کلیک خارج از آن
    window.addEventListener('click', (e) => {
      if (e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
      }
    });
  </script>
</body>
</html>