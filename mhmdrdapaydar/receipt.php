<?php
/**
 * انیمه موزیک — نمایش امن رسید پرداخت برای ادمین
 * چون پوشه uploads/receipts از دسترسی مستقیم وب محافظت می‌شود،
 * رسید فقط از همین مسیر (با احراز هویت ادمین) قابل مشاهده است.
 */

session_start();
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../includes/config.php';
am_admin_guard();

$pid = (int)($_GET['id'] ?? 0);
if ($pid <= 0) {
    http_response_code(400);
    exit('Invalid request');
}

try {
    $db = new PDO('sqlite:' . AM_DB_USERS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $db->prepare("SELECT receipt_path FROM payments WHERE id = ?");
    $stmt->execute([$pid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $row = false;
}

if (!$row || empty($row['receipt_path'])) {
    http_response_code(404);
    exit('No receipt');
}

$path = (string)$row['receipt_path'];

// مسیر باید حتماً داخل پوشه رسیدها باشد (جلوگیری از path traversal)
$real = realpath($path);
$base = realpath(AM_RECEIPTS_DIR);
if ($real === false || $base === false || strpos($real, $base) !== 0 || !is_file($real)) {
    http_response_code(404);
    exit('Receipt not found');
}

$info = @getimagesize($real);
$mime = $info ? $info['mime'] : 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($real));
header('X-Content-Type-Options: nosniff');
readfile($real);
exit;
