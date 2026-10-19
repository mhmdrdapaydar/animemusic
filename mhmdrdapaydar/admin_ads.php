<?php
session_start();
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
am_admin_guard();

// مسیر دقیق فایل JSON (یک سطح بالاتر از پوشه admin)
// خواندن مقاوم: اگر فایل خراب شده باشد از نسخه پشتیبان (.bak) بازیابی می‌شود
$dataFile = __DIR__ . '/../visits_ads.json';
$data = am_json_read($dataFile, []);

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

// آمار تبلیغات تفکیک‌شده بر اساس زبان
$langTotals = [];
foreach (($data['langs'] ?? []) as $code => $lng) {
    if (!is_array($lng)) continue;
    $langTotals[$code] = $lng['total'] ?? 0;
}
arsort($langTotals);

// آمار کلیک روی تبلیغ (ad_clicks.json)
$clicksFile = __DIR__ . '/../ad_clicks.json';
$clicks = am_json_read($clicksFile, []);
$clicksTotal = $clicks['total'] ?? 0;
$clicksToday = ($clicks['daily'] ?? [])[date('Y-m-d')] ?? 0;
$clickLangTotals = [];
$clickLangSlots = [];
$allLangStats = am_ad_clicks_stat()['langs'] ?? [];
$agg = [
    'views'   => ['display' => 0, 'download' => 0],
    'clicks'  => ['display' => 0, 'download' => 0],
    'unlocks' => 0,
    'lifetime_views' => 0, 'lifetime_clicks' => 0, 'lifetime_unlocks' => 0,
];
// روند ۷ روز اخیر برای نمودار (جمع همه زبان‌ها، تفکیک جایگاه)
$last7 = [];
for ($i = 6; $i >= 0; $i--) {
    $last7[] = date('Y-m-d', strtotime("-$i days"));
}
$chart = [
    'display'  => ['views' => array_fill_keys($last7, 0), 'clicks' => array_fill_keys($last7, 0)],
    'download' => ['views' => array_fill_keys($last7, 0), 'clicks' => array_fill_keys($last7, 0)],
];
foreach ($allLangStats as $code => $g) {
    if (!is_array($g)) continue;
    $d = $g['slots']['display'];
    $l = $g['slots']['download'];
    $clickLangTotals[$code] = (int)$g['total'];
    $clickLangSlots[$code] = ['display' => $d, 'download' => $l];
    foreach (['display' => $d, 'download' => $l] as $sl => $st) {
        $agg['views'][$sl]  += (int)$st['views']['total'];
        $agg['clicks'][$sl] += (int)$st['clicks']['total'];
        $agg['lifetime_views']  += (int)$st['lifetime']['views'];
        $agg['lifetime_clicks'] += (int)$st['lifetime']['clicks'];
        foreach ($last7 as $day) {
            $chart[$sl]['views'][$day]  += (int)($st['views']['daily'][$day] ?? 0);
            $chart[$sl]['clicks'][$day] += (int)($st['clicks']['daily'][$day] ?? 0);
        }
    }
    $agg['unlocks'] += (int)$l['unlocks'];
    $agg['lifetime_unlocks'] += (int)$l['lifetime']['unlocks'];
}
arsort($clickLangTotals);
$aggViews  = $agg['views']['display'] + $agg['views']['download'];
$aggClicks = $agg['clicks']['display'] + $agg['clicks']['download'];
$aggCtr    = $aggViews > 0 ? round(100 * $aggClicks / $aggViews, 1) : 0;

// نام‌های نمایشی زبان‌ها
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
  <title>پنل آمار تبلیغات</title>
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
      <div class="brand"><i class="fas fa-chart-bar"></i><span>پنل آمار تبلیغات</span></div>
      <div class="header-actions">
        <a class="btn" href="admin.php"><i class="fas fa-arrow-right"></i> پنل اصلی</a>
        <a class="btn" href="logout.php"><i class="fas fa-sign-out-alt"></i> خروج</a>
      </div>
    </header>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
        <div class="stat-body"><div class="stat-value"><?= number_format($dailyVisits) ?></div><div class="stat-title">تبلیغات امروز · <?= $today ?></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
        <div class="stat-body"><div class="stat-value"><?= number_format($weeklyVisits) ?></div><div class="stat-title">تبلیغات این هفته · هفته <?= explode('-', $thisWeek)[1] ?></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
        <div class="stat-body"><div class="stat-value"><?= number_format($monthlyVisits) ?></div><div class="stat-title">تبلیغات این ماه · <?= $thisMonth ?></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-database"></i></div>
        <div class="stat-body"><div class="stat-value"><?= number_format($totalVisits) ?></div><div class="stat-title">تبلیغات کل از ابتدا</div></div>
      </div>
    </div>

    <div class="stats-grid" style="margin-top:20px;">
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-mouse-pointer"></i></div>
        <div class="stat-body"><div class="stat-value"><?= number_format($clicksTotal) ?></div><div class="stat-title">کل کلیک تبلیغ (تاریخی)</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
        <div class="stat-body"><div class="stat-value"><?= number_format($clicksToday) ?></div><div class="stat-title">کلیک امروز تبلیغ</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-eye"></i></div>
        <div class="stat-body"><div class="stat-value"><?= number_format($aggViews) ?></div><div class="stat-title">بازدید جایگاه‌ها (از بروزرسانی) — نمایش <?= number_format($agg['views']['display']) ?> · دانلود <?= number_format($agg['views']['download']) ?></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-percentage"></i></div>
        <div class="stat-body"><div class="stat-value"><?= $aggCtr ?>%</div><div class="stat-title">نرخ کلیک (CTR) از بروزرسانی</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-unlock-alt"></i></div>
        <div class="stat-body"><div class="stat-value"><?= number_format($agg['unlocks']) ?></div><div class="stat-title">فعال‌سازی دانلود با تبلیغ (از بروزرسانی)</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-infinity"></i></div>
        <div class="stat-body"><div class="stat-value"><?= number_format($agg['lifetime_views']) ?> / <?= number_format($agg['lifetime_clicks']) ?></div><div class="stat-title">مادام‌العمر: بازدید / کلیک (فعال‌سازی: <?= number_format($agg['lifetime_unlocks']) ?>)</div></div>
      </div>
    </div>

    <div class="chart-box">
      <h3 class="chart-title"><i class="fas fa-language"></i> بازدید تبلیغ به تفکیک زبان</h3>
      <?php if (empty($langTotals)): ?>
        <p style="color: var(--gray);">هنوز آماری بر اساس زبان ثبت نشده است.</p>
      <?php else: ?>
        <canvas id="langViewsChart" style="max-height:300px;"></canvas>
        <table style="width:100%; border-collapse:collapse; font-size:13.5px; margin-top:10px;">
          <thead>
            <tr style="border-bottom:2px solid var(--border); text-align:right;">
              <th style="padding:8px;">زبان</th>
              <th style="padding:8px;">کد</th>
              <th style="padding:8px;">بازدید</th>
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
      <h3 class="chart-title"><i class="fas fa-mouse-pointer"></i> آمار تفکیکی هر زبان و جایگاه (از آخرین بروزرسانی همان جایگاه)</h3>
      <?php if (empty($clickLangTotals)): ?>
        <p style="color: var(--gray);">هنوز آماری ثبت نشده است.</p>
      <?php else: ?>
        <canvas id="langClicksChart" style="max-height:300px;"></canvas>
        <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:13px; margin-top:10px; min-width:860px;">
          <thead>
            <tr style="border-bottom:2px solid var(--border); text-align:right;">
              <th style="padding:8px;">زبان</th>
              <th style="padding:8px;">جایگاه</th>
              <th style="padding:8px;">از بروزرسانی</th>
              <th style="padding:8px;">بازدید</th>
              <th style="padding:8px;">کلیک</th>
              <th style="padding:8px;">CTR</th>
              <th style="padding:8px;">فعال‌سازی دانلود</th>
              <th style="padding:8px;">میانگین/روز</th>
              <th style="padding:8px;">مادام‌العمر (بازدید/کلیک)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($clickLangTotals as $code => $cnt): ?>
              <?php foreach (['display' => 'نمایش تبلیغ', 'download' => 'دانلود'] as $sl => $slName): ?>
                <?php $st = $clickLangSlots[$code][$sl] ?? null; if (!$st) continue; ?>
                <tr style="border-bottom:1px solid var(--border); text-align:right;">
                  <td style="padding:8px;"><?= htmlspecialchars($langNames[$code] ?? $code) ?> <span style="color:var(--gray);">(<?= htmlspecialchars($code) ?>)</span></td>
                  <td style="padding:8px;"><span class="status-pill <?= $sl === 'display' ? 'st-accepted' : 'st-completed' ?>" style="white-space:nowrap;"><?= $slName ?></span></td>
                  <td style="padding:8px; color:var(--gray); white-space:nowrap;"><?= htmlspecialchars($st['since']) ?></td>
                  <td style="padding:8px;"><?= number_format((int)$st['views']['total']) ?></td>
                  <td style="padding:8px;"><?= number_format((int)$st['clicks']['total']) ?></td>
                  <td style="padding:8px;"><?= $st['ctr'] ?>%</td>
                  <td style="padding:8px;"><?= $sl === 'download' ? number_format((int)$st['unlocks']) : '—' ?></td>
                  <td style="padding:8px; color:var(--gray); white-space:nowrap;"><?= $st['views_per_day'] ?> بازدید / <?= $st['clicks_per_day'] ?> کلیک</td>
                  <td style="padding:8px; color:var(--gray); white-space:nowrap;"><?= number_format((int)$st['lifetime']['views']) ?> / <?= number_format((int)$st['lifetime']['clicks']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
        <p style="color:var(--gray); font-size:12px; margin-top:8px;">
          <i class="fas fa-info-circle"></i>
          «از بروزرسانی» = زمانی که تنظیمات همان جایگاه را ذخیره کردید؛ با بروزرسانی یک جایگاه فقط آمار همان جایگاه صفر می‌شود و مادام‌العمر همیشه حفظ می‌شود.
          «فعال‌سازی دانلود» = دفعاتی که کاربر با کلیک روی تبلیغ جایگاه دانلود، قفل دانلود یک موزیک را باز کرده است.
        </p>
      <?php endif; ?>

      <div class="chart-box" style="margin-top:18px;">
        <h3 class="chart-title"><i class="fas fa-chart-line"></i> روند ۷ روز اخیر — بازدید و کلیک هر جایگاه</h3>
        <canvas id="slotTrendChart" style="max-height:300px;"></canvas>
      </div>
    </div>

    <div class="chart-box">
      <h3 class="chart-title"><i class="fas fa-chart-line"></i> آمار تبلیغات روزانه (۷ روز اخیر)</h3>
      <canvas id="dailyChart"></canvas>
    </div>
    <div class="chart-box">
      <h3 class="chart-title"><i class="fas fa-chart-area"></i> آمار تبلیغات هفتگی (۴ هفته اخیر)</h3>
      <canvas id="weeklyChart"></canvas>
    </div>
    <div class="chart-box">
      <h3 class="chart-title"><i class="fas fa-chart-bar"></i> آمار تبلیغات ماهانه (۶ ماه اخیر)</h3>
      <canvas id="monthlyChart"></canvas>
    </div>
  </div>
  
  <script>
    // نمودار بازدید تبلیغ به تفکیک زبان
    <?php if (!empty($langTotals)): ?>
    (function(){
      const el = document.getElementById('langViewsChart');
      if (!el) return;
      const colors = ['#00aa6f','#4361ee','#ffc107','#e63946','#9b5de5','#00b4d8','#ff6b6b','#06d6a0','#f77f00','#7209b7','#3a86ff'];
      const labels = <?= json_encode(array_map(function($c){ global $langNames; return $langNames[$c] ?? $c; }, array_keys($langTotals))) ?>;
      new Chart(el, {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [{
            label: 'بازدید',
            data: <?= json_encode(array_values($langTotals)) ?>,
            backgroundColor: labels.map((_, i) => colors[i % colors.length])
          }]
        },
        options: { responsive: true, plugins:{ legend:{display:false} }, scales:{ y:{beginAtZero:true} } }
      });
    })();
    <?php endif; ?>

    // نمودار کلیک تبلیغ به تفکیک زبان
    <?php if (!empty($clickLangTotals)): ?>
    (function(){
      const el = document.getElementById('langClicksChart');
      if (!el) return;
      const colors = ['#ff6b6b','#f77f00','#ffc107','#06d6a0','#00aa6f','#00b4d8','#4361ee','#9b5de5','#7209b7','#3a86ff','#e63946'];
      const labels = <?= json_encode(array_map(function($c){ global $langNames; return $langNames[$c] ?? $c; }, array_keys($clickLangTotals))) ?>;
      new Chart(el, {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [{
            label: 'کلیک',
            data: <?= json_encode(array_values($clickLangTotals)) ?>,
            backgroundColor: labels.map((_, i) => colors[i % colors.length])
          }]
        },
        options: { responsive: true, plugins:{ legend:{display:false} }, scales:{ y:{beginAtZero:true} } }
      });
    })();
    <?php endif; ?>

    // نمودار روند ۷ روز اخیر: بازدید و کلیک هر جایگاه (جمع همه زبان‌ها)
    (function(){
      const el = document.getElementById('slotTrendChart');
      if (!el) return;
      new Chart(el, {
        type: 'line',
        data: {
          labels: <?= json_encode(array_map(function($d){ return substr($d, 5); }, $last7)) ?>,
          datasets: [
            { label: 'بازدید جایگاه نمایش', data: <?= json_encode(array_values($chart['display']['views'])) ?>, borderColor: '#00aa6f', backgroundColor: 'rgba(0,170,111,.12)', tension: .3, fill: true },
            { label: 'کلیک جایگاه نمایش',  data: <?= json_encode(array_values($chart['display']['clicks'])) ?>, borderColor: '#f77f00', backgroundColor: 'rgba(247,127,0,.12)', tension: .3, fill: true },
            { label: 'بازدید جایگاه دانلود', data: <?= json_encode(array_values($chart['download']['views'])) ?>, borderColor: '#4361ee', backgroundColor: 'rgba(67,97,238,.12)', tension: .3, fill: true },
            { label: 'کلیک جایگاه دانلود',  data: <?= json_encode(array_values($chart['download']['clicks'])) ?>, borderColor: '#9b5de5', backgroundColor: 'rgba(155,93,229,.12)', tension: .3, fill: true }
          ]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
      });
    })();

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