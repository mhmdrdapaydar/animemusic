<?php
/**
 * انیمه موزیک — endpoint پردازش وضعیت واردات
 *
 * GET  ?status=1         => وضعیت لحظه‌ای پروژه جاری
 * POST ?step=1&job=xxx   => اجرای یک قدم از پردازش (گزارش نوار پیشرفت)
 * GET  ?abort=1&job=xxx  => لغو پروژه
 *
 * پاسخ همیشه JSON است.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/admin_auth.php';
am_admin_guard();

// پاسخی که می‌فرستیم همیشه JSON بدون کش باشد
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

if (file_exists(__DIR__ . '/../includes/headless.php')) {
    require_once __DIR__ . '/../includes/headless.php';
}
require_once __DIR__ . '/includes/import_common.php';

$action = isset($_GET['step']) ? 'step'
        : (isset($_GET['status']) ? 'status'
        : (isset($_GET['abort']) ? 'abort' : 'none'));

// مدیریت زمان اجرای طولانی
if (function_exists('set_time_limit')) @set_time_limit(60);
if (function_exists('ignore_user_abort')) @ignore_user_abort(true);

function out(array $payload) {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

am_import_bootstrap();

if ($action === 'status') {
    $cur = am_import_current();
    if (!$cur) {
        out(['status' => 'idle', 'done' => false, 'failed' => false, 'busy' => false,
             'total' => 0, 'processed' => 0, 'progress' => 0,
             'new_anime' => 0, 'dup_anime' => 0, 'new_music' => 0, 'dup_music' => 0,
             'new_singers' => 0, 'dup_singers' => 0, 'errors' => 0, 'message' => '']);
    }
    $job = $cur['job'] ?? '';
    $st = am_import_status_load($job);
    if (!$st) {
        // پروژه‌ای که status ندارد (حالت نادر) — پاک‌سازی
        am_import_current_set(null);
        out(['status' => 'idle', 'done' => false, 'failed' => false, 'busy' => false,
             'total' => 0, 'processed' => 0, 'progress' => 0,
             'new_anime' => 0, 'dup_anime' => 0, 'new_music' => 0, 'dup_music' => 0,
             'new_singers' => 0, 'dup_singers' => 0, 'errors' => 0, 'message' => '']);
    }
    // ریکاوری: اگر پردازنده وسط یک قدم به‌جای مانده باشد (busy=true کهنه) آن را آزاد می‌کنیم
    if (!empty($st['busy']) && (time() - (int)($st['updated'] ?? 0)) > 120) {
        $st['busy'] = false;
        am_import_status_save($job, $st);
    }

    // اگر نوار پیشرفت از قبل تمام شده، وضعیت done برمی‌گردانیم
    if (($st['status'] ?? '') === 'done') {
        out(am_import_output($st) + ['job' => $job]);
    }
    out(am_import_output($st) + ['job' => $job]);
}

if ($action === 'step') {
    $job = $_POST['job'] ?? ($_GET['job'] ?? '');
    if (!$job || !preg_match('/^[a-zA-Z0-9_-]+$/', $job)) {
        out(['status' => 'failed', 'message' => 'پروژه نامعتبر', 'done' => true, 'failed' => true, 'busy' => false,
             'total' => 0, 'processed' => 0, 'progress' => 100,
             'new_anime' => 0, 'dup_anime' => 0, 'new_music' => 0, 'dup_music' => 0,
             'new_singers' => 0, 'dup_singers' => 0, 'errors' => 0]);
    }

    // فقط پروژه‌ای که به عنوان جاری ثبت شده قابل پردازش است (جلوگیری از اجرای متفرقه)
    $cur = am_import_current();
    if (!$cur || ($cur['job'] ?? '') !== $job) {
        out(['status' => 'failed', 'message' => 'پروژه جاری نیست', 'done' => true, 'failed' => true, 'busy' => false,
             'total' => 0, 'processed' => 0, 'progress' => 100,
             'new_anime' => 0, 'dup_anime' => 0, 'new_music' => 0, 'dup_music' => 0,
             'new_singers' => 0, 'dup_singers' => 0, 'errors' => 0]);
    }

    $lock = am_import_lock_file($job);
    $fp = @fopen($lock, 'c');
    if ($fp && flock($fp, LOCK_EX | LOCK_NB)) {
        // اجرای یک قدم
        if (!is_file(__DIR__ . '/includes/import_processor.php')) {
            flock($fp, LOCK_UN); fclose($fp);
            out(['status' => 'failed', 'message' => 'پردازنده یافت نشد', 'done' => true, 'failed' => true, 'busy' => false,
                 'total' => 0, 'processed' => 0, 'progress' => 100,
                 'new_anime' => 0, 'dup_anime' => 0, 'new_music' => 0, 'dup_music' => 0,
                 'new_singers' => 0, 'dup_singers' => 0, 'errors' => 0]);
        }
        require_once __DIR__ . '/includes/import_processor.php';
        $res = am_import_step($job, 10, 60);
        // همیشه بعد از قدم، قفل آزاد است
        flock($fp, LOCK_UN); fclose($fp);
        $res['job'] = $job;
        out($res);
    }
    // پردازنده مشغول است — این درخواست عمل نکرد
    out(['status' => 'running', 'done' => false, 'failed' => false, 'busy' => true, 'locked' => true,
         'total' => 0, 'processed' => 0, 'progress' => -1,
         'new_anime' => 0, 'dup_anime' => 0, 'new_music' => 0, 'dup_music' => 0,
         'new_singers' => 0, 'dup_singers' => 0, 'errors' => 0, 'message' => '']);
}

if ($action === 'abort') {
    $job = $_GET['job'] ?? '';
    $claim = am_import_current();
    if ($job && preg_match('/^[a-zA-Z0-9_-]+$/', $job) && $claim && ($claim['job'] ?? '') === $job) {
        $st = am_import_status_load($job);
        if ($st && ($st['status'] ?? '') !== 'done') {
            @unlink(am_import_status_file($job));
            @unlink(am_import_work_file($job));
            @unlink(am_import_lock_file($job));
        }
        am_import_current_set(null);
    }
    out(['status' => 'idle', 'done' => false, 'failed' => false, 'busy' => false,
         'total' => 0, 'processed' => 0, 'progress' => 0,
         'new_anime' => 0, 'dup_anime' => 0, 'new_music' => 0, 'dup_music' => 0,
         'new_singers' => 0, 'dup_singers' => 0, 'errors' => 0, 'message' => '']);
}

out(['status' => 'idle', 'done' => false, 'failed' => false, 'busy' => false,
     'total' => 0, 'processed' => 0, 'progress' => 0,
     'new_anime' => 0, 'dup_anime' => 0, 'new_music' => 0, 'dup_music' => 0,
     'new_singers' => 0, 'dup_singers' => 0, 'errors' => 0, 'message' => '']);
