<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// مسیر دقیق فایل JSON (یک سطح بالاتر از پوشه admin)
$dataFile = __DIR__ . '/../visits_ads.json';
$data = [];

if (file_exists($dataFile)) {
    $json = file_get_contents($dataFile);
    $data = json_decode($json, true) ?: [];
}

// محاسبه آمار جاری
$today = date('Y-m-d');
$thisWeek = date('Y-W');
$thisMonth = date('Y-m');

$dailyVisits = $data['daily'][$today] ?? 0;
$weeklyVisits = $data['weekly'][$thisWeek] ?? 0;
$monthlyVisits = $data['monthly'][$thisMonth] ?? 0;
$totalVisits = $data['total'] ?? 0;

// آماده‌سازی داده‌ها برای نمودارها
$dailyData = array_slice($data['daily'] ?? [], -7, 7, true);
$weeklyData = array_slice($data['weekly'] ?? [], -4, 4, true);
$monthlyData = array_slice($data['monthly'] ?? [], -6, 6, true);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>پنل آمار تبلیغات</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    /* استایل‌ها مشابه کد قبلی با کمی تغییرات */
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
      font-family: 'Vazir', 'Segoe UI', Tahoma, sans-serif;
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      color: var(--dark);
      direction: rtl;
      min-height: 100vh;
      padding: 20px;
      line-height: 1.6;
    }
    
    .container {
      max-width: 1200px;
      margin: 0 auto;
    }
    
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    
    .stat-card {
      background: white;
      border-radius: var(--border-radius);
      padding: 20px;
      box-shadow: var(--box-shadow);
      text-align: center;
      transition: var(--transition);
    }
    
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    }
    
    .stat-value {
      font-size: 2.5rem;
      font-weight: bold;
      color: var(--primary);
      margin: 15px 0;
    }
    
    .stat-title {
      color: var(--gray);
      font-size: 1.1rem;
    }
    
    .chart-container {
      background: white;
      border-radius: var(--border-radius);
      padding: 20px;
      box-shadow: var(--box-shadow);
      margin-bottom: 30px;
    }
    
    .chart-title {
      font-size: 1.3rem;
      margin-bottom: 20px;
      color: var(--primary);
      display: flex;
      align-items: center;
      gap: 10px;
    }
  </style>
</head>
<body>
  <div class="container">
    <header>
      <div class="logo">
        <i class="fas fa-chart-bar"></i>
        <h1>پنل آمار تبلیغات</h1>
      </div>
      <div class="user-actions">
        <a href="admin.php" class="btn btn-secondary">
          <i class="fas fa-arrow-right"></i>
          بازگشت به پنل
        </a>
      </div>
    </header>
    
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-title">تبلیغات امروز</div>
        <div class="stat-value"><?= number_format($dailyVisits) ?></div>
        <div class="stat-meta"><?= $today ?></div>
      </div>
      
      <div class="stat-card">
        <div class="stat-title">تبلیغات این هفته</div>
        <div class="stat-value"><?= number_format($weeklyVisits) ?></div>
        <div class="stat-meta">هفته <?= explode('-', $thisWeek)[1] ?></div>
      </div>
      
      <div class="stat-card">
        <div class="stat-title">تبلیغات این ماه</div>
        <div class="stat-value"><?= number_format($monthlyVisits) ?></div>
        <div class="stat-meta"><?= $thisMonth ?></div>
      </div>
      
      <div class="stat-card">
        <div class="stat-title">تبلیغات کل</div>
        <div class="stat-value"><?= number_format($totalVisits) ?></div>
        <div class="stat-meta">از ابتدا</div>
      </div>
    </div>
    
    <div class="chart-container">
      <h3 class="chart-title">
        <i class="fas fa-chart-line"></i>
        آمار تبلیغات روزانه (7 روز اخیر)
      </h3>
      <canvas id="dailyChart"></canvas>
    </div>
    
    <div class="chart-container">
      <h3 class="chart-title">
        <i class="fas fa-chart-area"></i>
        آمار تبلیغات هفتگی (4 هفته اخیر)
      </h3>
      <canvas id="weeklyChart"></canvas>
    </div>
    
    <div class="chart-container">
      <h3 class="chart-title">
        <i class="fas fa-chart-bar"></i>
        آمار تبلیغات ماهانه (6 ماه اخیر)
      </h3>
      <canvas id="monthlyChart"></canvas>
    </div>
  </div>
  
  <script>
    // نمودار روزانه
    new Chart(document.getElementById('dailyChart'), {
      type: 'line',
      data: {
        labels: [<?= "'" . implode("','", array_keys($dailyData)) . "'" ?>],
        datasets: [{
          label: 'تبلیغات روزانه',
          data: [<?= implode(',', array_values($dailyData)) ?>],
          backgroundColor: 'rgba(0, 170, 111, 0.1)',
          borderColor: '#00aa6f',
          tension: 0.3,
          fill: true
        }]
      }
    });
    
    // نمودار هفتگی
    new Chart(document.getElementById('weeklyChart'), {
      type: 'bar',
      data: {
        labels: [<?= "'" . implode("','", array_keys($weeklyData)) . "'" ?>],
        datasets: [{
          label: 'تبلیغات هفتگی',
          data: [<?= implode(',', array_values($weeklyData)) ?>],
          backgroundColor: 'rgba(67, 97, 238, 0.7)'
        }]
      }
    });
    
    // نمودار ماهانه
    new Chart(document.getElementById('monthlyChart'), {
      type: 'bar',
      data: {
        labels: [<?= "'" . implode("','", array_keys($monthlyData)) . "'" ?>],
        datasets: [{
          label: 'تبلیغات ماهانه',
          data: [<?= implode(',', array_values($monthlyData)) ?>],
          backgroundColor: 'rgba(255, 193, 7, 0.7)'
        }]
      }
    });
  </script>
</body>
</html>