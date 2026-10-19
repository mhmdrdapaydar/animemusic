<?php
session_start();
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
am_admin_guard();

$ads_file = __DIR__ . '/../ads.json';
$ads = [];

if (file_exists($ads_file)) {
    $ads = json_decode(file_get_contents($ads_file), true);
}
if (!is_array($ads)) $ads = [];

// نام‌های نمایشی زبان‌ها (برای فرم تنظیم تبلیغ هر زبان)
$langNames = [
    'fa' => 'فارسی', 'en' => 'English', 'ja' => '日本語', 'es' => 'Español',
    'pt' => 'Português', 'fr' => 'Français', 'de' => 'Deutsch', 'ar' => 'العربية',
    'hi' => 'हिन्दी', 'th' => 'ไทย', 'ko' => '한국어',
];

// ============================================================
// تنظیم تبلیغ برای هر زبان (ad_config.json)
// هر زبان ۲ جایگاه (نمایش/دانلود) × ۲ نوع (معمولی/کد) دارد
// ============================================================
$adConfig = am_ad_config();
$adConfigSaved = false;
$adUploadMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_lang_ads') {
    $newConfig = is_array($adConfig) ? $adConfig : [];
    $buildSlot = function ($src) {
        $src  = is_array($src) ? $src : [];
        $type = (($src['type'] ?? '') === 'code') ? 'code' : 'normal';
        $n    = is_array($src['normal'] ?? null) ? $src['normal'] : [];
        return [
            'type'   => $type,
            'normal' => [
                'url'        => trim((string)($n['url'] ?? '')),
                'aparat_url' => trim((string)($n['aparat_url'] ?? '')),
                'video'      => trim((string)($n['video'] ?? '')),
                'image'      => trim((string)($n['image'] ?? '')),
            ],
            'code'   => (string)($src['code'] ?? ''),
        ];
    };
    foreach ((array)($_POST['langs'] ?? []) as $code => $fields) {
        $code = (string)$code;
        if ($code === '' || !preg_match('/^[a-z]{2}$/', $code)) continue;
        $fields = (array)$fields;
        $slotSrc = is_array($fields['slots'] ?? null) ? $fields['slots'] : [];
        $slots = [
            'display'  => $buildSlot((array)($slotSrc['display'] ?? [])),
            'download' => $buildSlot((array)($slotSrc['download'] ?? [])),
        ];
        $newConfig[$code] = [
            // فیلدهای قدیمی برای سازگاری با کدهای موجود = جایگاه نمایش / نوع معمولی
            'url'        => $slots['display']['normal']['url'],
            'aparat_url' => $slots['display']['normal']['aparat_url'],
            'video'      => $slots['display']['normal']['video'],
            'image'      => $slots['display']['normal']['image'],
            'slots'      => $slots,
        ];
    }

    // آپلود اختیاری تصویر تبلیغ برای یک زبان/جایگاه مشخص
    if (isset($_FILES['ad_image']) && ($_FILES['ad_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $uploadLang = (string)($_POST['image_lang'] ?? '');
        $uploadSlot = (string)($_POST['image_slot'] ?? 'display');
        if (!in_array($uploadSlot, ['display', 'download'], true)) $uploadSlot = 'display';
        if (isset($newConfig[$uploadLang]) && preg_match('/^[a-z]{2}$/', $uploadLang)) {
            $file = $_FILES['ad_image'];
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $info = @getimagesize($file['tmp_name']);
            $mime = $info ? ($info['mime'] ?? '') : '';
            if ($info && in_array($mime, $allowed, true) && $file['size'] <= 5 * 1024 * 1024) {
                $dir = __DIR__ . '/../assets/ads';
                if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) $ext = 'jpg';
                $name = 'ad_' . $uploadLang . '_' . $uploadSlot . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $target = $dir . '/' . $name;
                if (@move_uploaded_file($file['tmp_name'], $target)) {
                    $rel = 'assets/ads/' . $name;
                    $newConfig[$uploadLang]['slots'][$uploadSlot]['normal']['image'] = $rel;
                    if ($uploadSlot === 'display') {
                        $newConfig[$uploadLang]['image'] = $rel;
                    }
                    $adUploadMessage = 'تصویر تبلیغ برای زبان «' . htmlspecialchars($uploadLang) . '» (جایگاه ' . ($uploadSlot === 'display' ? 'نمایش' : 'دانلود') . ') ذخیره شد.';
                } else {
                    $adUploadMessage = 'خطا در ذخیره تصویر تبلیغ.';
                }
            } else {
                $adUploadMessage = 'تصویر نامعتبر است (فقط JPG/PNG/GIF/WebP تا ۵ مگابایت).';
            }
        }
    }

    // اگر تنظیمات هر جایگاهِ زبانی تغییر کرد، شمارش کلیک آن زبان از صفر شروع شود
    $adFp = function ($cfg) {
        if (!is_array($cfg)) $cfg = [];
        return md5((string)json_encode([
            $cfg['url'] ?? '', $cfg['aparat_url'] ?? '', $cfg['video'] ?? '', $cfg['image'] ?? '',
            $cfg['slots'] ?? null,
        ], JSON_UNESCAPED_UNICODE));
    };
    $oldConfig = is_array($adConfig) ? $adConfig : [];
    foreach ($newConfig as $code => $cfg) {
        if (!is_array($cfg)) continue;
        $old = isset($oldConfig[$code]) && is_array($oldConfig[$code]) ? $oldConfig[$code] : [];
        if ($adFp($old) !== $adFp($cfg)) {
            am_ad_reset_clicks($code);
        }
    }

    am_ad_config_save($newConfig);
    $adConfig = am_ad_config();
    $adConfigSaved = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $ad_id = $_POST['ad_id'] ?? '';

    if ($action && $action !== 'save_lang_ads' && $ad_id) {
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

  <div class="card" style="padding:20px; margin-bottom:20px;">
    <h3 style="font-size:16px; color:var(--dark); margin-bottom:6px;"><i class="fas fa-globe" style="color:var(--primary);"></i> تنظیم تبلیغ برای هر زبان</h3>
    <p style="font-size:13px; color:var(--gray); margin-bottom:16px;">
      برای هر زبان <strong>دو جایگاه</strong> تبلیغ دارید: «نمایش تبلیغ» (بالای صفحه محتوا) و «دانلود» (دروازه دانلود با مشاهده تبلیغ).
      برای هر جایگاه می‌توانید نوع تبلیغ را <strong>«تبلیغ معمولی»</strong> (تصویر/ویدیو + لینک) یا <strong>«کد تبلیغ»</strong> (مثل mediaad) انتخاب کنید.
      برای جایگاه «کد تبلیغ»، از شبکه تبلیغاتی برای هر جایگاه یک کد جدا (با شناسه متفاوت) بگیرید تا شمارش کلیک درست بماند.
      اگر زبانی خالی بماند، تبلیغ فارسی برایش نمایش داده می‌شود.
    </p>
    <?php if ($adConfigSaved): ?><div class="done-box" style="background:#e3f6ea;color:#1e7e34;padding:10px;border-radius:8px;margin-bottom:14px;"><i class="fas fa-check"></i> تنظیمات تبلیغ ذخیره شد.</div><?php endif; ?>
    <?php if ($adUploadMessage !== ''): ?><div class="done-box" style="background:#fff4d6;color:#9a6b00;padding:10px;border-radius:8px;margin-bottom:14px;"><i class="fas fa-image"></i> <?= $adUploadMessage ?></div><?php endif; ?>
    <style>
      .am-lang-card { border:1px solid var(--border); border-radius:10px; margin-bottom:12px; background:var(--surface); overflow:hidden; }
      .am-lang-card summary { cursor:pointer; padding:12px 16px; font-weight:700; font-size:14px; display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
      .am-lang-card summary::marker { color:var(--primary); }
      .am-lang-card[open] summary { border-bottom:1px solid var(--border); background:var(--primary-soft); }
      .am-clicks-pill { font-size:11.5px; font-weight:600; background:var(--light-gray); color:var(--gray); border-radius:20px; padding:3px 10px; }
      .am-slots-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; padding:14px; }
      .am-slot { border:1px solid var(--border); border-radius:8px; padding:12px; margin:0; }
      .am-slot legend { font-size:12.5px; font-weight:800; color:var(--primary-dark, var(--primary)); padding:0 6px; }
      .am-type-row { display:flex; gap:14px; margin-bottom:10px; font-size:13px; }
      .am-type-row label { display:flex; align-items:center; gap:5px; cursor:pointer; }
      .am-slot textarea { width:100%; min-height:110px; font-family:monospace; font-size:12px; direction:ltr; text-align:left; }
      .am-slot input[type="url"], .am-slot input[type="text"] { direction:ltr; text-align:left; }
      .am-slot .form-control { margin-bottom:8px; }
      .am-slot small { color:var(--gray); display:block; margin-bottom:8px; }
      .am-code-note { background:var(--primary-soft, #eef6ff); border-radius:6px; padding:8px 10px; font-size:12px; margin-bottom:8px; }
      @media (max-width: 860px) { .am-slots-grid { grid-template-columns:1fr; } }
    </style>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="save_lang_ads">
      <?php foreach ($langNames as $code => $name): ?>
        <?php
          $dSlot = am_ad_slot($code, 'display');
          $lSlot = am_ad_slot($code, 'download');
          $langClicks = am_ad_clicks_stat($code);
          $cDisp = (int)(($langClicks['slots']['display']['total'] ?? 0));
          $cDl   = (int)(($langClicks['slots']['download']['total'] ?? 0));
          $cAll  = (int)($langClicks['total'] ?? 0);
          $renderSlot = function ($slotName, $slotData, $code) {
              $isCode = ($slotData['type'] === 'code');
              $n = $slotData['normal'];
              $nm = $slotName === 'display' ? 'نمایش تبلیغ' : 'دانلود';
              $f = "langs[{$code}][slots][{$slotName}]";
              ?>
              <fieldset class="am-slot">
                <legend>جایگاه <?= $nm ?></legend>
                <div class="am-type-row">
                  <label>
                    <input type="radio" name="<?= $f ?>[type]" value="normal" <?= !$isCode ? 'checked' : '' ?>>
                    تبلیغ معمولی
                  </label>
                  <label>
                    <input type="radio" name="<?= $f ?>[type]" value="code" <?= $isCode ? 'checked' : '' ?>>
                    کد تبلیغ
                  </label>
                </div>
                <div class="am-normal-fields" style="<?= $isCode ? 'display:none;' : '' ?>">
                  <input type="url" name="<?= $f ?>[normal][url]" class="form-control" placeholder="لینک مقصد — https://..." value="<?= htmlspecialchars($n['url']) ?>">
                  <input type="url" name="<?= $f ?>[normal][aparat_url]" class="form-control" placeholder="لینک آپارات (اختیاری)" value="<?= htmlspecialchars($n['aparat_url']) ?>">
                  <input type="text" name="<?= $f ?>[normal][video]" class="form-control" placeholder="مسیر ویدیو (اختیاری) — assets/ads/myad.mp4" value="<?= htmlspecialchars($n['video']) ?>">
                  <input type="text" name="<?= $f ?>[normal][image]" class="form-control" placeholder="مسیر تصویر (اختیاری) — assets/ads/ad_<?= $code ?>.jpg" value="<?= htmlspecialchars($n['image']) ?>">
                  <?php if ($n['image'] !== ''): ?>
                    <div style="margin-bottom:8px;"><img src="/<?= htmlspecialchars(ltrim($n['image'], '/')) ?>" alt="" style="max-width:120px; max-height:70px; border-radius:6px; border:1px solid var(--border);"></div>
                  <?php endif; ?>
                </div>
                <div class="am-code-fields" style="<?= $isCode ? '' : 'display:none;' ?>">
                  <div class="am-code-note">
                    <i class="fas fa-code"></i> کد شبکه تبلیغاتی (اسکریپت + تگ div) را اینجا بگذارید. برای هر جایگاه از شبکه تبلیغاتی،
                    <strong>جایگاه (پلیسمنت) جدا با شناسه متفاوت</strong> بسازید تا شمارش کلیک‌ها با هم قاطی نشود.
                  </div>
                  <textarea name="<?= $f ?>[code]" class="form-control" placeholder='&lt;script ...&gt;&lt;/script&gt;&#10;&lt;div id="..."&gt;&lt;/div&gt;' spellcheck="false"><?= htmlspecialchars($slotData['code']) ?></textarea>
                </div>
              </fieldset>
              <?php
          };
        ?>
        <details class="am-lang-card" <?= $code === 'fa' ? 'open' : '' ?>>
          <summary>
            <i class="fas fa-language" style="color:var(--primary);"></i>
            <?= htmlspecialchars($name) ?> <span style="color:var(--gray); font-weight:400;">(<?= $code ?>)</span>
            <span class="am-clicks-pill">کلیک نمایش: <?= number_format($cDisp) ?></span>
            <span class="am-clicks-pill">کلیک دانلود: <?= number_format($cDl) ?></span>
            <span class="am-clicks-pill">مجموع: <?= number_format($cAll) ?></span>
          </summary>
          <div class="am-slots-grid">
            <?php $renderSlot('display', $dSlot, $code); ?>
            <?php $renderSlot('download', $lSlot, $code); ?>
          </div>
        </details>
      <?php endforeach; ?>

      <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-top:14px; padding-top:14px; border-top:1px dashed var(--border);">
        <label style="font-weight:700; font-size:13px;"><i class="fas fa-image" style="color:var(--primary);"></i> آپلود تصویر تبلیغ:</label>
        <select name="image_lang" class="form-control" style="width:auto; min-width:140px;">
          <?php foreach ($langNames as $code => $name): ?>
            <option value="<?= $code ?>"><?= htmlspecialchars($name) ?> (<?= $code ?>)</option>
          <?php endforeach; ?>
        </select>
        <select name="image_slot" class="form-control" style="width:auto; min-width:130px;">
          <option value="display">جایگاه نمایش</option>
          <option value="download">جایگاه دانلود</option>
        </select>
        <input type="file" name="ad_image" accept="image/*" class="form-control" style="width:auto;">
        <small style="color:var(--gray);">JPG/PNG/GIF/WebP تا ۵MB — تصویر در assets/ads ذخیره می‌شود.</small>
      </div>
      <button type="submit" class="btn btn-primary" style="margin-top:14px;"><i class="fas fa-save"></i> ذخیره تنظیمات تبلیغ زبان‌ها</button>
    </form>
  </div>

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

  // تعویض فیلدهای «تبلیغ معمولی / کد تبلیغ» در هر جایگاه
  document.querySelectorAll('.am-type-row input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', () => {
      const fieldset = radio.closest('.am-slot');
      if (!fieldset) return;
      const isCode = radio.value === 'code' && radio.checked;
      const normal = fieldset.querySelector('.am-normal-fields');
      const code = fieldset.querySelector('.am-code-fields');
      if (normal) normal.style.display = isCode ? 'none' : '';
      if (code) code.style.display = isCode ? '' : 'none';
    });
  });
</script>
</body>
</html>
