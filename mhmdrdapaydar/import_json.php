<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/admin_auth.php';
am_admin_guard();
require_once __DIR__ . '/../includes/headless.php';
require_once __DIR__ . '/includes/import_common.php';

am_import_bootstrap();

$page_title = 'واردات JSON - پنل مدیریت انیمه موزیک';
$message = '';
$message_type = '';
$hasCurrent = false;

// پردازش آپلود — دریافت RAW (بدون هیچ محدودیت حجمی)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'stage') {
    $done = false;

    // اگر پروژه‌ای هنوز در حال پردازش است، آپلود جدید مسدود است تا وضعیت‌ها به هم نریزند
    $existing = am_import_current();
    if ($existing && ($existing['job'] ?? '')) {
        $message = 'یک پردازش هنوز در جریان است. ابتدا صبر کنید یا آن را لغو کنید.';
        $message_type = 'error';
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => $message, 'type' => 'error', 'hasCurrent' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: import_json.php?error=1');
        exit;
    }

    // ۱) فایل .json واقعی
    if (isset($_FILES['json_file']) && is_array($_FILES['json_file']) && ($_FILES['json_file']['error'] ?? 1) === UPLOAD_ERR_OK) {
        $data = json_decode((string)@file_get_contents($_FILES['json_file']['tmp_name']), true);
        if (!is_array($data)) {
            $message = 'فایل JSON معتبر نیست: ' . (json_last_error() ? json_last_error_msg() : 'ساختار نامعتبر');
            $message_type = 'error';
        } else {
            $job = date('Ymd_His') . '_' . substr(uniqid(), -5);
            @file_put_contents(am_import_work_file($job), json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
            am_import_status_save($job, [
                'status' => 'created', 'busy' => false,
                'total' => count($data), 'cursor' => 0, 'processed' => 0,
                'new_anime' => 0, 'dup_anime' => 0, 'new_music' => 0, 'dup_music' => 0,
                'new_singers' => 0, 'dup_singers' => 0, 'errors' => 0, 'message' => ''
            ]);
            am_import_current_set($job);
            $done = true;
        }
    }
    // ۲) محتوای مستقیم
    elseif (isset($_POST['json_text']) && trim((string)$_POST['json_text']) !== '') {
        $data = json_decode((string)$_POST['json_text'], true);
        if (!is_array($data)) {
            $message = 'فایل JSON معتبر نیست: ' . (json_last_error() ? json_last_error_msg() : 'ساختار نامعتبر');
            $message_type = 'error';
        } else {
            $job = date('Ymd_His') . '_' . substr(uniqid(), -5);
            @file_put_contents(am_import_work_file($job), json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
            am_import_status_save($job, [
                'status' => 'created', 'busy' => false,
                'total' => count($data), 'cursor' => 0, 'processed' => 0,
                'new_anime' => 0, 'dup_anime' => 0, 'new_music' => 0, 'dup_music' => 0,
                'new_singers' => 0, 'dup_singers' => 0, 'errors' => 0, 'message' => ''
            ]);
            am_import_current_set($job);
            $done = true;
        }
    } else {
        $message = 'هیچ فایلی انتخاب نشده است.';
        $message_type = 'error';
    }

    // پاسخ برای fetch
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => $done,
            'message' => $done ? 'فایل با موفقیت آماده شد' : $message,
            'type' => $done ? 'success' : 'error'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: import_json.php?' . ($done ? 'done=1' : 'error=1'));
    exit;
}

// بازخورد پس از ریدایرکت (برای فرم‌های بدون JavaScript)
if (isset($_GET['done'])) {
    $message = 'فایل دریافت شد؛ پردازش به‌صورت خودکار آغاز می‌شود.';
    $message_type = 'success';
} elseif (isset($_GET['error'])) {
    $message = 'در دریافت فایل خطایی رخ داد. دوباره تلاش کنید.';
    $message_type = 'error';
}

// آیا پروژه‌ای در جریان است؟
$cur = am_import_current();
if ($cur) {
    $hasCurrent = true;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title) ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/admin.css?v=4">
  <style>
    .upload-area { border: 2px dashed var(--primary); border-radius: var(--radius); padding: 26px; text-align: center; background: var(--surface); transition: var(--transition); cursor: pointer; margin-bottom: 14px; }
    .upload-area.dragover { background: var(--primary-soft); border-color: var(--primary-dark); }
    .upload-icon i { font-size: 44px; color: var(--primary); margin-bottom: 10px; }
    .upload-text h3 { font-size: 16px; margin: 8px 0 4px; color: var(--dark); }
    .upload-text p { color: var(--gray); font-size: 13px; }
    .upload-area .form-group { max-width: 460px; margin: 14px auto 0; }
    .upload-tools { display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap; }
    .hidden { display: none !important; }

    /* نوار پیشرفت آپلود */
    .upload-progress-wrap { display: none; margin-top: 14px; }
    .upload-progress-wrap .bar { height: 10px; background: var(--light-gray); border-radius: 10px; overflow: hidden; }
    .upload-progress-wrap .bar > div { height: 100%; width: 0; background: linear-gradient(90deg, var(--primary), var(--primary-dark)); border-radius: 10px; transition: width .2s; }
    .upload-progress-wrap .lbl { font-size: 12.5px; color: var(--gray); margin-top: 6px; }

    /* نوار پیشرفت پردازش */
    .progress-panel { display: none; margin-top: 20px; }
    .progress-panel .bar { height: 14px; background: var(--light-gray); border-radius: 10px; overflow: hidden; }
    .progress-panel .bar > div { height: 100%; width: 0; background: linear-gradient(90deg, var(--primary), var(--secondary)); border-radius: 10px; transition: width .3s; }
    .progress-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; margin-top: 14px; }
    .stat-mini { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 10px 12px; text-align: center; box-shadow: var(--shadow); }
    .stat-mini .v { font-size: 22px; font-weight: 800; line-height: 1.2; }
    .stat-mini .t { font-size: 12px; color: var(--gray); }
    .stat-mini.dup .v { color: var(--warning); }
    .stat-mini.new .v { color: var(--success); }
    .stat-mini.err .v { color: var(--danger); }

    .instructions { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px; margin-top: 18px; box-shadow: var(--shadow); }
    .instructions h3 { font-size: 15px; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; color: var(--primary); }
    .instructions p, .instructions li { color: var(--gray); font-size: 13.5px; line-height: 1.9; }
    .instructions ul { padding-right: 20px; }
    footer { text-align: center; color: var(--gray); font-size: 13px; margin-top: 20px; line-height: 1.8; }
  </style>
</head>
<body>
<div class="container">
  <header class="admin-header">
    <div class="brand"><i class="fas fa-file-import"></i><span>واردات داده از فایل JSON</span></div>
    <div class="header-actions">
      <a class="btn" href="admin.php"><i class="fas fa-arrow-right"></i> پنل اصلی</a>
      <a class="btn" href="logout.php"><i class="fas fa-sign-out-alt"></i> خروج</a>
    </div>
  </header>

  <?php if ($message): ?>
    <div class="message <?= $message_type === 'success' ? 'success-message' : 'error-message' ?>">
      <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
      <div><?= $message ?></div>
    </div>
  <?php endif; ?>

  <div class="card" style="padding:20px;" id="upload-card">
    <form id="import-form" method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="stage">
      <div class="upload-area" id="upload-area">
        <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
        <div class="upload-text">
          <h3 id="upload-title">فایل JSON خود را اینجا رها کنید یا برای انتخاب کلیک کنید</h3>
          <p>فرمت مجاز: json — بدون محدودیت حجم</p>
        </div>
        <div class="form-group">
          <input type="file" name="json_file" id="json_file" accept=".json,application/json" class="form-control">
        </div>
        <button type="submit" class="btn btn-primary" id="submit-btn" style="margin-top:10px;">
          <i class="fas fa-upload"></i> بارگذاری و شروع پردازش
        </button>
      </div>
      <div class="upload-progress-wrap" id="upload-progress">
        <div class="bar"><div id="upload-bar"></div></div>
        <div class="lbl" id="upload-lbl">در حال بارگذاری… 0%</div>
      </div>
    </form>
    <p style="color:var(--gray); font-size:12.5px; margin-top:10px;">
      <i class="fas fa-info-circle"></i> اگر فایل خیلی بزرگ است و آپلود مستقیم ممکن نیست،
      محتوای JSON را داخل کادر زیر بچسبانید:
    </p>
    <form method="post" id="paste-form">
      <input type="hidden" name="action" value="stage">
      <textarea name="json_text" id="json_text" class="form-control" rows="4" placeholder="[{&quot;name&quot;:&quot;...&quot;}]"></textarea>
      <button type="submit" class="btn btn-secondary" style="margin-top:8px;"><i class="fas fa-paste"></i> ارسال محتوا</button>
    </form>
  </div>

  <!-- پنل پیشرفت پردازش (زنده) -->
  <div class="card progress-panel" style="display:none; padding:20px;" id="progress-panel">
    <h3 class="section-title"><i class="fas fa-spinner"></i> در حال پردازش فایل JSON…</h3>
    <div class="bar"><div id="progress-bar"></div></div>
    <p style="color:var(--gray); font-size:13px; margin:8px 0;" id="progress-label">0 از 0</p>

    <div class="progress-stats">
      <div class="stat-mini new"><div class="v" id="s-new">0</div><div class="t">جدید اضافه شد</div></div>
      <div class="stat-mini dup"><div class="v" id="s-dup">0</div><div class="t">تکراری (رد شد)</div></div>
      <div class="stat-mini"><div class="v" id="s-total">0</div><div class="t">کل</div></div>
      <div class="stat-mini"><div class="v" id="s-proc">0</div><div class="t">پردازش شده</div></div>
      <div class="stat-mini"><div class="v" id="s-rem">0</div><div class="t">در حال بررسی</div></div>
      <div class="stat-mini"><div class="v" id="s-anime">0</div><div class="t">انیمه جدید</div></div>
      <div class="stat-mini"><div class="v" id="s-singers">0</div><div class="t">خواننده جدید</div></div>
      <div class="stat-mini err"><div class="v" id="s-err">0</div><div class="t">خطا</div></div>
    </div>

    <div id="progress-abort-row" class="upload-tools" style="margin-top:16px;">
      <button class="btn btn-light" id="abort-btn"><i class="fas fa-stop"></i> لغو</button>
    </div>
  </div>

  <div class="instructions">
    <h3><i class="fas fa-info-circle"></i> راهنمای فرمت JSON</h3>
    <ul>
      <li>آرایه‌ای از آبجکت‌های انیمه</li>
      <li>هر انیمه دارای نام، آدرس و آدرس تصویر اصلی است</li>
      <li>هر انیمه می‌تواند چندین فصل داشته باشد</li>
      <li>هر فصل می‌تواند چندین تم (موزیک) داشته باشد</li>
      <li>هر تم دارای نوع (OP/ED/IN)، عنوان، هنرمند و ویدیوها است</li>
    </ul>
    <h3 style="margin-top:14px;"><i class="fas fa-shield-alt"></i> محافظت در برابر تکراری‌ها</h3>
    <p>
      موزیک‌هایی که از قبل در سایت وجود دارند (انیمه + نوع + عنوان + فصل/قسمت یا لینک یکسان)
      دوباره اضافه نمی‌شوند و در گزارش زنده به‌صورت «تکراری» شمارش می‌شوند.
    </p>
    <p style="margin-top:8px;">
      پاکسازی تکراری‌های به‌جا مانده از واردات‌های قبلی:
      <a href="clean_duplicates.php" style="color:var(--danger); font-weight:700;"><i class="fas fa-broom"></i> پاکسازی تکراری‌ها</a>
    </p>
  </div>

  <footer><p>پنل مدیریت انیمه موزیک © <?= date('Y') ?></p></footer>
</div>

<script>
(function () {
  var uploadArea  = document.getElementById('upload-area');
  var fileInput   = document.getElementById('json_file');
  var uploadTitle = document.getElementById('upload-title');
  var uploadProg  = document.getElementById('upload-progress');
  var uploadBar   = document.getElementById('upload-bar');
  var uploadLbl   = document.getElementById('upload-lbl');

  // drag & drop
  ['dragenter', 'dragover'].forEach(function (ev) {
    uploadArea.addEventListener(ev, function (e) { e.preventDefault(); e.stopPropagation(); uploadArea.classList.add('dragover'); });
  });
  ['dragleave', 'drop'].forEach(function (ev) {
    uploadArea.addEventListener(ev, function (e) { e.preventDefault(); e.stopPropagation(); uploadArea.classList.remove('dragover'); });
  });
  uploadArea.addEventListener('drop', function (e) {
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      fileInput.files = e.dataTransfer.files;
      uploadTitle.textContent = 'فایل انتخاب شده: ' + e.dataTransfer.files[0].name;
    }
  });
  fileInput.addEventListener('change', function () {
    if (this.files && this.files[0]) uploadTitle.textContent = 'فایل انتخاب شده: ' + this.files[0].name;
  });

  var form = document.getElementById('import-form');
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!fileInput.files || !fileInput.files[0]) return;

    uploadProg.style.display = 'block';
    uploadBar.style.width = '0%';
    uploadLbl.textContent = 'در حال بارگذاری… 0%';

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'import_json.php', true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    xhr.upload.addEventListener('progress', function (ev) {
      if (ev.lengthComputable) {
        var pct = Math.round(ev.loaded / ev.total * 100);
        uploadBar.style.width = pct + '%';
        uploadLbl.textContent = 'در حال بارگذاری… ' + pct + '%';
      }
    });

    xhr.addEventListener('load', function () {
      try {
        var res = JSON.parse(xhr.responseText || '{}');
        if (res.ok) {
          uploadBar.style.width = '100%';
          uploadLbl.textContent = 'بارگذاری کامل شد — شروع پردازش…';
          startPolling();
        } else {
          uploadLbl.textContent = 'خطا: ' + (res.message || 'نامشخص');
          uploadLbl.style.color = 'var(--danger)';
        }
      } catch (err) {
        uploadBar.style.width = '100%';
        startPolling(); // ممکن است پاسخ غیر JSON ولی موفق بوده باشد
      }
    });

    xhr.addEventListener('error', function () {
      uploadLbl.textContent = 'خطا در اتصال هنگام بارگذاری.';
      uploadLbl.style.color = 'var(--danger)';
    });

    xhr.send(new FormData(form));
  });

  // ---- پایش زنده پردازش ----
  var panel     = document.getElementById('progress-panel');
  var pBar      = document.getElementById('progress-bar');
  var pLabel    = document.getElementById('progress-label');
  var timer     = null;
  var lastJob   = '';
  var shutting  = false;
  var inFlight  = false;

  function startPolling() {
    panel.style.display = 'block';
    panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    tick();
    timer = setInterval(tick, 500);
  }

  // حلقه واحد: ابتدا وضعیت را می‌خواند و اگر تمام نشده قدم اجرا می‌کند.
  // (به پرچم busy وابسته نیستیم؛ قفل سرور کارها را سریالایز می‌کند تا لوپ نشود)
  function tick() {
    if (shutting) return;
    fetch('import_worker.php?status=1&t=' + Date.now(), { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (shutting) return;
        if (d.status === 'idle') { return; }
        lastJob = d.job || lastJob;
        render(d);
        if (d.done) { finish(d); return; }
        if (d.failed) { fail(d); return; }
        kick();
      })
      .catch(function () { /* خطای شبکه لحظه‌ای؛ چرخه بعدی دوباره تلاش می‌کند */ });
  }

  // اجرای یک قدم (قفل سمت سرور از اجرای موازی دو درخواست همزمان جلوگیری می‌کند)
  function kick() {
    if (shutting || inFlight || !lastJob) return;
    inFlight = true;
    fetch('import_worker.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'step=1&job=' + encodeURIComponent(lastJob)
    }).then(function (r) { return r.json(); }).then(function (d) {
      inFlight = false;
      if (shutting) return;
      lastJob = d.job || lastJob;
      if (d.progress >= 0) render(d);
      if (d.done) finish(d);
      else if (d.failed) fail(d);
    }).catch(function () { inFlight = false; });
  }

  function fail(d) {
    if (shutting) return;
    shutting = true;
    stopPolling();
    pLabel.textContent = '❌ خطا: ' + (d.message || 'پردازش با خطا متوقف شد.');
    pLabel.style.color = 'var(--danger)';
  }

  function finish(d) {
    if (shutting) return;
    shutting = true;
    stopPolling();
    pBar.style.width = '100%';
    render(d);
    pLabel.innerHTML = '✅ پردازش کامل شد — ' + (d.message || '') +
      '<br><a href="admin.php" class="btn btn-primary" style="margin-top:10px; display:inline-block;">بازگشت به پنل</a>';
    // مخفی کردن دکمه لغو
    var row = document.getElementById('progress-abort-row');
    if (row) row.style.display = 'none';
  }

  function render(d) {
    if (d.progress >= 0) {
      pBar.style.width = d.progress + '%';
      pLabel.textContent = d.processed + ' از ' + d.total + ' پردازش شد (' + d.progress + '%)';
    }
    setVal('s-new', (d.new_music || 0) + (d.new_anime || 0) + (d.new_singers || 0));
    setVal('s-dup', (d.dup_music || 0) + (d.dup_anime || 0) + (d.dup_singers || 0));
    setVal('s-total', d.total || 0);
    setVal('s-proc', d.processed || 0);
    setVal('s-rem', Math.max(0, (d.total || 0) - (d.processed || 0)));
    setVal('s-anime', d.new_anime || 0);
    setVal('s-singers', d.new_singers || 0);
    setVal('s-err', d.errors || 0);
  }

  function setVal(id, v) { var el = document.getElementById(id); if (el) el.textContent = v; }

  function stopPolling() { if (timer) { clearInterval(timer); timer = null; } }

  // لغو
  var abortBtn = document.getElementById('abort-btn');
  if (abortBtn) abortBtn.addEventListener('click', function () {
    if (!confirm('پردازش متوقف شود؟')) return;
    shutting = true;
    stopPolling();
    fetch('import_worker.php?abort=1&job=' + encodeURIComponent(lastJob || 'x') + '&t=' + Date.now(), { cache: 'no-store' })
      .then(function () {
        pBar.style.width = '0%';
        pLabel.textContent = 'پردازش لغو شد.';
        panel.style.display = 'none';
        var row = document.getElementById('progress-abort-row');
        if (row) row.style.display = '';
        lastJob = '';
      });
  });

  // اگر از قبل پروژه‌ای در جریان است، بلافاصله ادامه بدهیم (مقاوم به رفرش)
  <?php if ($hasCurrent): ?>
  startPolling();
  <?php endif; ?>
})();
</script>
</body>
</html>
