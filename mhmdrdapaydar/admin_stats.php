<?php
session_start();
require_once __DIR__ . '/includes/admin_auth.php';
am_admin_guard();

// مسیر دقیق فایل JSON (یک سطح بالاتر از پوشه admin)
$dataFile = __DIR__ . '/../visits.json';
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

// آمار تفکیک‌شده بر اساس زبان
$langTotals = [];
foreach (($data['langs'] ?? []) as $code => $lng) {
    if (!is_array($lng)) continue;
    $langTotals[$code] = $lng['total'] ?? 0;
}
arsort($langTotals); // از بیشترین بازدید

// نام‌های نمایشی زبان‌ها برای جدول
$langNames = [
    'fa' => 'فارسی', 'en' => 'English', 'ja' => '日本語', 'es' => 'Español',
    'pt' => 'Português', 'fr' => 'Français', 'de' => 'Deutsch', 'ar' => 'العربية',
    'hi' => 'हिन्दी', 'th' => 'ไทย', 'ko' => '한국어',
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>پنل آمار بازدیدها</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="stylesheet" href="assets/admin.css?v=4">
  <style>
    .chart-box { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px; box-shadow: var(--shadow); margin-bottom: 18px; }
    .chart-title { font-size: 15px; margin-bottom: 16px; color: var(--dark); display: flex; align-items: center; gap: 8px; }
    .chart-title i { color: var(--primary); }
    .chart-box canvas { max-height: 320px; }
  </style>
</head>
<body>
  <div class="container">
    <header class="admin-header">
      <div class="brand"><i class="fas fa-chart-bar"></i><span>پنل آمار بازدیدها</span></div>
      <div class="header-actions">
        <a class="btn" href="admin.php"><i class="fas fa-arrow-right"></i> پنل اصلی</a>
        <a class="btn" href="logout.php"><i class="fas fa-sign-out-alt"></i> خروج</a>
      </div>
    </header>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
        <div class="stat-body"><div class="stat-value"><?= number_format($dailyVisits) ?></div><div class="stat-title">بازدید امروز · <?= $today ?></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
        <div class="stat-body"><div class="stat-value"><?= number_format($weeklyVisits) ?></div><div class="stat-title">بازدید این هفته · هفته <?= explode('-', $thisWeek)[1] ?></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
        <div class="stat-body"><div class="stat-value"><?= number_format($monthlyVisits) ?></div><div class="stat-title">بازدید این ماه · <?= $thisMonth ?></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-database"></i></div>
        <div class="stat-body"><div class="stat-value"><?= number_format($totalVisits) ?></div><div class="stat-title">بازدید کل از ابتدا</div></div>
      </div>
    </div>

    <div class="chart-box">
      <h3 class="chart-title"><i class="fas fa-language"></i> بازدید به تفکیک زبان</h3>
      <?php if (empty($langTotals)): ?>
        <p style="color: var(--gray);">هنوز آماری بر اساس زبان ثبت نشده است.</p>
      <?php else: ?>
        <canvas id="langChart" style="max-height:300px;"></canvas>
        <table style="width:100%; border-collapse:collapse; font-size:13.5px; margin-top:10px;">
          <thead>
            <tr style="border-bottom:2px solid var(--border); text-align:right;">
              <th style="padding:8px;">زبان</th>
              <th style="padding:8px;">کد</th>
              <th style="padding:8px;">بازدید کل</th>
              <th style="padding:8px;">سهم</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($langTotals as $code => $cnt): ?>
              <?php
                $share = $totalVisits > 0 ? round($cnt / $totalVisits * 100, 1) : 0;
                $name = $langNames[$code] ?? $code;
              ?>
              <tr style="border-bottom:1px solid var(--border); text-align:right;">
                <td style="padding:8px;"><?= htmlspecialchars($name) ?></td>
                <td style="padding:8px; color:var(--gray);"><?= htmlspecialchars($code) ?></td>
                <td style="padding:8px;"><?= number_format($cnt) ?></td>
                <td style="padding:8px;">
                  <div style="background:var(--border); border-radius:6px; height:8px; width:120px; display:inline-block; vertical-align:middle; overflow:hidden;">
                    <div style="background:var(--primary); height:100%; width:<?= $share ?>%;"></div>
                  </div>
                  <span style="margin-inline-start:8px;"><?= $share ?>%</span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="chart-box">
      <h3 class="chart-title"><i class="fas fa-chart-line"></i> آمار بازدید روزانه (۷ روز اخیر)</h3>
      <canvas id="dailyChart"></canvas>
    </div>
    <div class="chart-box">
      <h3 class="chart-title"><i class="fas fa-chart-area"></i> آمار بازدید هفتگی (۴ هفته اخیر)</h3>
      <canvas id="weeklyChart"></canvas>
    </div>
    <div class="chart-box">
      <h3 class="chart-title"><i class="fas fa-chart-bar"></i> آمار بازدید ماهانه (۶ ماه اخیر)</h3>
      <canvas id="monthlyChart"></canvas>
    </div>
  </div>
  
  <script>
    // نمودار بازدید به تفکیک زبان
    <?php if (!empty($langTotals)): ?>
    (function(){
      const langEl = document.getElementById('langChart');
      if (!langEl) return;
      const langData = <?= json_encode(array_values($langTotals)) ?>;
      const langLabels = <?= json_encode(array_map(function($c){ global $langNames; return $langNames[$c] ?? $c; }, array_keys($langTotals))) ?>;
      const colors = ['#00aa6f','#4361ee','#ffc107','#e63946','#9b5de5','#00b4d8','#ff6b6b','#06d6a0','#f77f00','#7209b7','#3a86ff'];
      new Chart(langEl, {
        type: 'bar',
        data: {
          labels: langLabels,
          datasets: [{
            label: 'بازدید',
            data: langData,
            backgroundColor: langLabels.map((_, i) => colors[i % colors.length])
          }]
        },
        options: {
          responsive: true,
          plugins: { legend: { display: false } },
          scales: { y: { beginAtZero: true } }
        }
      });
    })();
    <?php endif; ?>

    // نمودار روزانه
    new Chart(document.getElementById('dailyChart'), {
      type: 'line',
      data: {
        labels: [<?= "'" . implode("','", array_keys($dailyData)) . "'" ?>],
        datasets: [{
          label: 'بازدید روزانه',
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
          label: 'بازدید هفتگی',
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
          label: 'بازدید ماهانه',
          data: [<?= implode(',', array_values($monthlyData)) ?>],
          backgroundColor: 'rgba(255, 193, 7, 0.7)'
        }]
      }
    });
  </script>
</body>
</html>