<?php
session_start();
require_once __DIR__ . '/includes/admin_auth.php';
am_admin_guard();

$ads_file = __DIR__ . '/../ads.json';
$ads = [];

if (file_exists($ads_file)) {
    $ads = json_decode(file_get_contents($ads_file), true);
}
if (!is_array($ads)) $ads = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $ad_id = $_POST['ad_id'] ?? '';

    if ($action && $ad_id) {
        foreach ($ads as &$ad) {
            if (isset($ad['id']) && $ad['id'] === $ad_id) {
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
                        if (isset($_POST['name'])) $ad['name'] = $_POST['name'];
                        if (isset($_POST['description'])) $ad['description'] = $_POST['description'];
                        if (isset($_POST['url'])) $ad['url'] = $_POST['url'];
                        if (array_key_exists('aparat_url', $_POST)) $ad['aparat_url'] = $_POST['aparat_url'];
                        if (isset($ad['status']) && $ad['status'] === 'completed') {
                            if (array_key_exists('completed_views', $_POST)) $ad['completed_views'] = $_POST['completed_views'];
                            if (array_key_exists('completed_notes', $_POST)) $ad['completed_notes'] = $_POST['completed_notes'];
                        }
                        break;
                    case 'delete':
                        $ads = array_values(array_filter($ads, function ($item) use ($ad_id) {
                            return isset($item['id']) && $item['id'] !== $ad_id;
                        }));
                        break;
                }
                break;
            }
        }
        unset($ad);

        if ($action === 'delete') {
            $ads = array_values($ads);
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
  <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/admin.css?v=4">
  <style>
    .ads-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; margin-top: 16px; }
    .ad-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px; box-shadow: var(--shadow); border-right: 4px solid var(--primary); }
    .ad-card h3 { font-size: 15px; color: var(--dark); margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
    .ad-card h3 i { color: var(--primary); }
    .ad-meta { display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap; font-size: 12.5px; color: var(--gray); margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid var(--border); }
    .ad-detail { font-size: 13.5px; margin-bottom: 10px; line-height: 1.8; }
    .ad-link { display: block; margin: 6px 0; color: var(--secondary); text-decoration: none; font-size: 13px; word-break: break-all; }
    .ad-link:hover { text-decoration: underline; }
    .status-pill { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; margin-top: 8px; }
    .st-pending { background: #fff4d6; color: #9a6b00; border: 1px solid #ffc107; }
    .st-accepted { background: #e3f6ea; color: #1e7e34; border: 1px solid #28a745; }
    .st-rejected { background: #fdecec; color: #bd2130; border: 1px solid #dc3545; }
    .st-completed { background: #e6f0ff; color: #0056c9; border: 1px solid #007bff; }
    .ad-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px; }
    .date-note { font-size: 12px; color: var(--gray); margin-top: 8px; }
    .done-box { margin-top: 10px; padding: 10px; background: var(--primary-soft); border-radius: 8px; font-size: 13px; }
    .filter-pills { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 4px; }
    .pill { background: var(--surface); border: 1px solid var(--border); color: var(--dark); padding: 7px 16px; border-radius: 30px; cursor: pointer; font-size: 13px; font-weight: 600; transition: var(--transition); font-family: inherit; }
    .pill:hover { border-color: var(--primary); color: var(--primary-dark); }
    .pill.active { background: var(--primary); border-color: var(--primary); color: #fff; }
    .modal-mask { display: none; position: fixed; inset: 0; background: rgba(15,23,42,.6); z-index: 1000; align-items: center; justify-content: center; padding: 16px; }
    .modal-card { background: var(--surface); border-radius: var(--radius); width: 100%; max-width: 500px; max-height: 92vh; overflow-y: auto; padding: 22px; box-shadow: var(--shadow); position: relative; }
    .modal-card h3 { font-size: 16px; margin-bottom: 16px; color: var(--dark); }
    .close-x { position: absolute; top: 12px; left: 14px; font-size: 22px; color: var(--gray); cursor: pointer; background: none; border: none; }
    .btn-soft { border: 1px solid transparent; background: var(--light-gray); color: var(--dark); padding: 8px 14px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; font-family: inherit; flex: 1; min-width: 110px; transition: var(--transition); }
    .btn-acc { background: #e3f6ea; color: #1e7e34; border-color: #c8ecd3; }
    .btn-acc:hover { background: #d2efdb; }
    .btn-rej { background: #fdecec; color: #bd2130; border-color: #f5c6cb; }
    .btn-rej:hover { background: #f9d1d5; }
    .btn-cmp { background: #e6f0ff; color: #0056c9; border-color: #c6e0ff; }
    .btn-cmp:hover { background: #d3e5ff; }
    .btn-edt { background: #fff4d6; color: #9a6b00; border-color: #ffe1a8; }
    .btn-edt:hover { background: #ffecbf; }
    .btn-del { background: var(--light-gray); color: var(--gray); border-color: var(--border); }
    .btn-del:hover { background: #e3e7ec; color: var(--danger); }
    @media (max-width: 640px) {
      .ads-grid { grid-template-columns: 1fr; }
      .ad-actions .btn-soft { flex: 1 1 100%; }
    }
  </style>
</head>
<body>
<div class="container">
  <header class="admin-header">
    <div class="brand"><i class="fas fa-ad"></i><span>مدیریت درخواست‌های تبلیغات</span></div>
    <div class="header-actions">
      <a class="btn" href="admin.php"><i class="fas fa-arrow-right"></i> پنل اصلی</a>
      <a class="btn" href="logout.php"><i class="fas fa-sign-out-alt"></i> خروج</a>
    </div>
  </header>

  <div class="card" style="padding:20px;">
    <div class="filter-pills">
      <button class="pill active" data-status="all">همه (<?= count($ads) ?>)</button>
      <button class="pill" data-status="pending">در انتظار بررسی</button>
      <button class="pill" data-status="accepted">پذیرفته شده</button>
      <button class="pill" data-status="rejected">رد شده</button>
      <button class="pill" data-status="completed">تکمیل شده</button>
    </div>

    <div class="ads-grid">
      <?php foreach ($ads as $ad): ?>
        <?php
          $st = $ad['status'] ?? 'pending';
          $map = [
            'pending'   => ['st-pending', 'در انتظار بررسی'],
            'accepted'  => ['st-accepted', 'پذیرفته شده'],
            'rejected'  => ['st-rejected', 'رد شده'],
            'completed' => ['st-completed', 'تکمیل شده'],
          ];
          list($stClass, $stText) = $map[$st] ?? $map['pending'];
        ?>
        <div class="ad-card" data-status="<?= htmlspecialchars($st) ?>">
          <h3><i class="fas fa-ad"></i> <?= htmlspecialchars($ad['name'] ?? 'بدون نام') ?></h3>

          <div class="ad-meta">
            <span>شناسه: <?= htmlspecialchars(substr($ad['id'] ?? '', 0, 8)) ?></span>
            <span>ثبت: <?= htmlspecialchars($ad['timestamp'] ?? '—') ?></span>
          </div>

          <div class="ad-detail"><strong>توضیحات:</strong><br><?= nl2br(htmlspecialchars($ad['description'] ?? '—')) ?></div>

          <?php if (!empty($ad['url'])): ?>
            <a href="<?= htmlspecialchars($ad['url']) ?>" class="ad-link" target="_blank" rel="noopener"><i class="fas fa-link"></i> <?= htmlspecialchars($ad['url']) ?></a>
          <?php endif; ?>
          <?php if (!empty($ad['aparat_url'])): ?>
            <a href="<?= htmlspecialchars($ad['aparat_url']) ?>" class="ad-link" target="_blank" rel="noopener"><i class="fa-brands fa-youtube"></i> لینک آپارات</a>
          <?php endif; ?>

          <span class="status-pill <?= $stClass ?>"><?= $stText ?></span>

          <?php if (!empty($ad['accepted_date'])): ?>
            <div class="date-note"><i class="fas fa-calendar-check"></i> تاریخ پذیرش: <?= htmlspecialchars($ad['accepted_date']) ?></div>
          <?php endif; ?>
          <?php if (!empty($ad['completed_date'])): ?>
            <div class="date-note"><i class="fas fa-calendar-check"></i> تاریخ تکمیل: <?= htmlspecialchars($ad['completed_date']) ?></div>
          <?php endif; ?>
          <?php if ($st === 'completed'): ?>
            <div class="done-box"><strong>بازدید انجام شده:</strong> <?= htmlspecialchars($ad['completed_views'] ?? 0) ?>
              <?php if (!empty($ad['completed_notes'])): ?><br><strong>توضیحات:</strong> <?= nl2br(htmlspecialchars($ad['completed_notes'])) ?><?php endif; ?>
            </div>
          <?php endif; ?>

          <div class="ad-actions">
            <form method="POST" style="width:100%; display:contents;">
              <input type="hidden" name="ad_id" value="<?= htmlspecialchars($ad['id'] ?? '') ?>">
              <?php if ($st === 'pending'): ?>
                <button type="submit" name="action" value="accept" class="btn-soft btn-acc"><i class="fas fa-check"></i> پذیرش</button>
                <button type="submit" name="action" value="reject" class="btn-soft btn-rej"><i class="fas fa-times"></i> رد درخواست</button>
              <?php endif; ?>
              <?php if ($st === 'accepted'): ?>
                <button type="button" onclick="openCompleteModal('<?= htmlspecialchars($ad['id'] ?? '') ?>')" class="btn-soft btn-cmp"><i class="fas fa-flag-checkered"></i> تکمیل شد</button>
              <?php endif; ?>
              <?php if ($st === 'completed'): ?>
                <button type="button" onclick="openEditModal(<?= htmlspecialchars(json_encode($ad, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)" class="btn-soft btn-edt"><i class="fas fa-edit"></i> ویرایش</button>
              <?php endif; ?>
              <button type="submit" name="action" value="delete" class="btn-soft btn-del" onclick="return confirm('حذف این تبلیغ؟')"><i class="fas fa-trash"></i> حذف</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if (empty($ads)): ?>
        <div class="empty-state"><i class="fas fa-ad"></i><h3>هیچ تبلیغی یافت نشد</h3><p>درخواست تبلیغاتی جدیدی ثبت نشده است.</p></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- مودال تکمیل تبلیغ -->
<div id="completeModal" class="modal-mask">
  <div class="modal-card">
    <button type="button" class="close-x" onclick="closeModal('completeModal')">&times;</button>
    <h3>تکمیل تبلیغ</h3>
    <form method="POST" id="completeForm">
      <input type="hidden" name="ad_id" id="complete_ad_id">
      <input type="hidden" name="action" value="complete_with_details">
      <div class="form-group"><label>تعداد بازدید انجام شده</label><input type="number" id="completed_views" name="completed_views" class="form-control" required min="1"></div>
      <div class="form-group"><label>توضیحات تکمیل تبلیغ</label><textarea id="completed_notes" name="completed_notes" class="form-control" required></textarea></div>
      <button type="submit" class="btn btn-primary btn-block">ثبت اطلاعات</button>
    </form>
  </div>
</div>

<!-- مودال ویرایش تبلیغ -->
<div id="editModal" class="modal-mask">
  <div class="modal-card">
    <button type="button" class="close-x" onclick="closeModal('editModal')">&times;</button>
    <h3>ویرایش تبلیغ</h3>
    <form method="POST" id="editForm">
      <input type="hidden" name="ad_id" id="edit_ad_id">
      <input type="hidden" name="action" value="edit">
      <div class="form-group"><label>نام تبلیغ</label><input type="text" id="edit_name" name="name" class="form-control" required></div>
      <div class="form-group"><label>توضیحات</label><textarea id="edit_description" name="description" class="form-control" required></textarea></div>
      <div class="form-group"><label>لینک وبسایت</label><input type="url" id="edit_url" name="url" class="form-control" required></div>
      <div class="form-group"><label>لینک آپارات</label><input type="url" id="edit_aparat_url" name="aparat_url" class="form-control" required></div>
      <div class="form-group"><label>تعداد بازدید انجام شده</label><input type="number" id="edit_completed_views" name="completed_views" class="form-control" required min="1"></div>
      <div class="form-group"><label>توضیحات تکمیل تبلیغ</label><textarea id="edit_completed_notes" name="completed_notes" class="form-control"></textarea></div>
      <button type="submit" class="btn btn-primary btn-block">ذخیره تغییرات</button>
    </form>
  </div>
</div>

<script>
  const pills = document.querySelectorAll('.pill');
  const cards = document.querySelectorAll('.ad-card[data-status]');
  pills.forEach(p => {
    p.addEventListener('click', () => {
      const s = p.dataset.status;
      pills.forEach(b => b.classList.remove('active'));
      p.classList.add('active');
      cards.forEach(c => { c.style.display = (s === 'all' || c.dataset.status === s) ? '' : 'none'; });
    });
  });

  function openCompleteModal(adId) {
    document.getElementById('complete_ad_id').value = adId;
    document.getElementById('completeModal').style.display = 'flex';
  }
  function openEditModal(ad) {
    document.getElementById('edit_ad_id').value = ad.id || '';
    document.getElementById('edit_name').value = ad.name || '';
    document.getElementById('edit_description').value = ad.description || '';
    document.getElementById('edit_url').value = ad.url || '';
    document.getElementById('edit_aparat_url').value = ad.aparat_url || '';
    document.getElementById('edit_completed_views').value = ad.completed_views || 0;
    document.getElementById('edit_completed_notes').value = ad.completed_notes || '';
    document.getElementById('editModal').style.display = 'flex';
  }
  function closeModal(id) { document.getElementById(id).style.display = 'none'; }
  window.addEventListener('click', e => { if (e.target.classList && e.target.classList.contains('modal-mask')) e.target.style.display = 'none'; });
</script>
</body>
</html>
