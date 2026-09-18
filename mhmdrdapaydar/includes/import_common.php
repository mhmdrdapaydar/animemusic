<?php
/**
 * انیمه موزیک — توابع مشترک فرآیند واردات JSON
 * پردازش به صورت مرحله‌ای (چانک) انجام می‌شود تا:
 *   ۱) روی هاست‌های محدود timeout نخورد
 *   ۲) پیشرفت به صورت زنده به ادمین نشان داده شود
 *   ۳) با رفرش صفحه، فرآیند از همان‌جا ادامه پیدا کند (وضعیت روی دیسک ذخیره می‌شود)
 */

if (!defined('AM_IMPORT_DIR')) {
    define('AM_IMPORT_DIR', dirname(__DIR__) . '/imports');
}

/** ساخت پوشه کار و محافظت از دسترسی وب به فایل‌های میانی */
function am_import_bootstrap() {
    if (!is_dir(AM_IMPORT_DIR)) {
        @mkdir(AM_IMPORT_DIR, 0755, true);
    }
    $ht = AM_IMPORT_DIR . '/.htaccess';
    if (!file_exists($ht)) {
        @file_put_contents($ht, "Require all denied\nDeny from all\n");
    }
}

function am_import_status_file($job) { return AM_IMPORT_DIR . '/' . $job . '.status.json'; }
function am_import_work_file($job)  { return AM_IMPORT_DIR . '/' . $job . '.work.json'; }
function am_import_lock_file($job)  { return AM_IMPORT_DIR . '/' . $job . '.lock'; }
function am_import_current_file()   { return AM_IMPORT_DIR . '/_current.json'; }

function am_import_status_load($job) {
    $f = am_import_status_file($job);
    if (!is_file($f)) return null;
    $d = json_decode((string)@file_get_contents($f), true);
    return is_array($d) ? $d : null;
}

function am_import_status_save($job, array $st) {
    $st['updated'] = time();
    @file_put_contents(am_import_status_file($job), json_encode($st, JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $st;
}

/**
 * فرآیند جاری (برای مقاوم بودن به رفرش).
 * اگر پروژه‌ی ذخیره‌شده دیگر قابل ادامه نباشد (status و work هر دو نباشند) خودبه‌خود پاک می‌شود
 * تا ادمین در وضعیت ناتمام قفل نشود.
 */
function am_import_current() {
    $f = am_import_current_file();
    if (!is_file($f)) return null;
    $d = json_decode((string)@file_get_contents($f), true);
    if (!is_array($d) || empty($d['job'])) {
        @unlink($f);
        return null;
    }
    $job = $d['job'];
    $hasStatus = is_file(am_import_status_file($job));
    $hasWork   = is_file(am_import_work_file($job));
    if (!$hasStatus && !$hasWork) {
        @unlink($f);
        @unlink(am_import_lock_file($job));
        return null;
    }
    return $d;
}

function am_import_current_set($job) {
    if ($job) {
        @file_put_contents(am_import_current_file(), json_encode(['job' => $job, 'updated' => time()], JSON_UNESCAPED_UNICODE), LOCK_EX);
    } else {
        @unlink(am_import_current_file());
    }
}

/** تبدیل وضعیت به خروجی JSON برای فرانت‌اند */
function am_import_output(array $st) {
    $total  = (int)($st['total'] ?? 0);
    $proc   = (int)($st['processed'] ?? 0);
    $status = $st['status'] ?? 'ready';
    return [
        'status'       => $status,
        'done'         => $status === 'done',
        'failed'       => $status === 'failed',
        'busy'         => !empty($st['busy']),
        'total'        => $total,
        'processed'    => $proc,
        'progress'     => $total > 0 ? (int)round($proc / $total * 100) : 0,
        'new_anime'    => (int)($st['new_anime'] ?? 0),
        'dup_anime'    => (int)($st['dup_anime'] ?? 0),
        'new_music'    => (int)($st['new_music'] ?? 0),
        'dup_music'    => (int)($st['dup_music'] ?? 0),
        'new_singers'  => (int)($st['new_singers'] ?? 0),
        'dup_singers'  => (int)($st['dup_singers'] ?? 0),
        'errors'       => (int)($st['errors'] ?? 0),
        'message'      => $st['message'] ?? '',
        'updated'      => (int)($st['updated'] ?? 0),
    ];
}
