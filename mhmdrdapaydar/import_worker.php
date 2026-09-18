<?php
/**
 * انیمه موزیک — endpoint پردازش وضعیت واردات
 *
 * GET  ?status=1        => وضعیت لحظه‌ای؛ اگر پردازش تمام نشده باشد، خودش یک قدم پیش می‌رود
 * POST ?step=1&job=xxx  => (سازگاری با صفحات قدیمی) همان پیشروی خودکار
 * GET  ?abort=1&job=xxx => لغو پروژه
 *
 * پاسخ همیشه JSON است.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/admin_auth.php';
am_admin_guard();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

if (file_exists(__DIR__ . '/../includes/headless.php')) {
    require_once __DIR__ . '/../includes/headless.php';
}
require_once __DIR__ . '/includes/import_common.php';
require_once __DIR__ . '/includes/import_processor.php';

// آزادسازی قفل session بعد از همه include‌ها و احراز هویت
// تا درخواست‌های موازی «وضعیت» پشت درخواست طولانی «قدم» صف نشوند
if (function_exists('session_write_close')) {
    session_write_close();
}

function out(array $payload) {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function am_worker_idle() {
    return ['status' => 'idle', 'done' => false, 'failed' => false, 'busy' => false,
            'total' => 0, 'processed' => 0, 'progress' => 0,
            'new_anime' => 0, 'dup_anime' => 0, 'new_music' => 0, 'dup_music' => 0,
            'new_singers' => 0, 'dup_singers' => 0, 'errors' => 0, 'message' => ''];
}

/**
 * پیشروی خودکار یک پروژه: اگر تمام نشده و اکنون آزاد است، یک قدم اجرا می‌شود.
 * قفل (LOCK_NB) از اجرای موازی دو درخواست جلوگیری می‌کند.
 */
function am_worker_advance($job, $budget) {
    $st = am_import_status_load($job);
    if (!$st) {
        return am_worker_idle();
    }

    // ریکاوری busy کهنه (بریده‌شدن درخواست وسط یک قدم)
    if (!empty($st['busy']) && (time() - (int)($st['updated'] ?? 0)) > 20) {
        $st['busy'] = false;
        am_import_status_save($job, $st);
    }

    if (($st['status'] ?? '') === 'done' || ($st['status'] ?? '') === 'failed' || !empty($st['busy'])) {
        return am_import_output($st);
    }

    $lock = am_import_lock_file($job);
    $fp = @fopen($lock, 'c');
    if ($fp && flock($fp, LOCK_EX | LOCK_NB)) {
        $res = am_import_step($job, $budget, 60);
        flock($fp, LOCK_UN);
        fclose($fp);
        return is_array($res) ? $res : am_import_output($st);
    }
    // پردازنده در حال اجراست — همین وضعیت فعلی را برگردان
    return am_import_output($st);
}

if (function_exists('set_time_limit')) @set_time_limit(90);
if (function_exists('ignore_user_abort')) @ignore_user_abort(true);

am_import_bootstrap();

$isStep = !empty($_POST['step']) || !empty($_GET['step']);
$action = $isStep ? 'step'
        : (!empty($_GET['abort']) ? 'abort' : 'status');

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
    out(am_worker_idle());
}

// status یا step: هر دو پروژه‌ی «جاری» روی دیسک را پیش می‌برند
$cur = am_import_current();
if (!$cur) {
    out(am_worker_idle());
}
$job = $cur['job'] ?? '';
if (!$job) {
    am_import_current_set(null);
    out(am_worker_idle());
}

// در حالت step اگر job صریحی فرستاده شده و مخالف جاری باشد، خطای واضح (نمی‌گذاریم لوپ شود)
if ($action === 'step') {
    $reqJob = $_POST['job'] ?? ($_GET['job'] ?? '');
    if (!empty($reqJob) && $reqJob !== $job) {
        out(['status' => 'failed', 'message' => 'پروژه جاری نیست', 'done' => true, 'failed' => true, 'busy' => false,
             'total' => 0, 'processed' => 0, 'progress' => 100,
             'new_anime' => 0, 'dup_anime' => 0, 'new_music' => 0, 'dup_music' => 0,
             'new_singers' => 0, 'dup_singers' => 0, 'errors' => 0]);
    }
}

$res = am_worker_advance($job, 4);
$res['job'] = $job;
out($res);
