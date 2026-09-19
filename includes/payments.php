<?php
/**
 * انیمه موزیک — موتور پرداخت (کاردتی، کریپتو، تلگرام، روبیکا) و تیکت پشتیبانی
 *
 * این فایل تمام منطق مربوط به:
 *   - ساختار جداول payments / tickets در users.db (مهاجرت خودکار)
 *   - تنظیمات پرداخت ادمین (شماره کارت، آیدی تلگرام/روبیکا، ارز کریپتو) در pay_config.json
 *   - اکتیو کردن VIP پس از تأیید پرداخت
 *   - مبلغ یکتای کریپتو (رزرو/آزادسازی اسلات)
 *   - رسید پرداخت کارت‌به‌کارت (آپلود و تأیید/رد)
 *   - تیکت‌های VIP و پاکسازی تیکت‌های قدیمی
 * را در خود دارد.
 */

if (!defined('AM_PAYMENTS_LOADED')) {
    define('AM_PAYMENTS_LOADED', 1);

    if (!defined('AM_ROOT')) {
        define('AM_ROOT', dirname(__DIR__));
    }

    if (file_exists(__DIR__ . '/db.php')) {
        require_once __DIR__ . '/db.php';
    }

    if (!defined('AM_PAY_CONFIG_FILE')) {
        define('AM_PAY_CONFIG_FILE', AM_ROOT . '/pay_config.json');
    }
    if (!defined('AM_RECEIPTS_DIR')) {
        define('AM_RECEIPTS_DIR', AM_ROOT . '/uploads/receipts');
    }

    /**
     * مهاجرت خودکار: ساخت جداول payments و tickets در users.db
     * این تابع idempotent است و با هر درخواست اجرا می‌شود.
     */
    function am_payments_migrate() {
        static $done = false;
        if ($done) return;
        $done = true;
        try {
            $db = am_db_users();
            $db->exec("CREATE TABLE IF NOT EXISTS payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                plan TEXT NOT NULL,
                method TEXT NOT NULL,
                price_label TEXT DEFAULT '',
                status TEXT NOT NULL DEFAULT 'pending',
                tx_hash TEXT DEFAULT '',
                crypto_amount TEXT DEFAULT '',
                receipt_path TEXT DEFAULT '',
                payer_name TEXT DEFAULT '',
                payer_card TEXT DEFAULT '',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
            // ستون‌های جدید برای تأیید کارت‌به‌کارت بدون آپلود رسید (نام و شماره کارت واریزکننده)
            $cols = array_column($db->query("PRAGMA table_info(payments)")->fetchAll(PDO::FETCH_ASSOC), 'name');
            if (!in_array('payer_name', $cols, true)) {
                $db->exec("ALTER TABLE payments ADD COLUMN payer_name TEXT DEFAULT ''");
            }
            if (!in_array('payer_card', $cols, true)) {
                $db->exec("ALTER TABLE payments ADD COLUMN payer_card TEXT DEFAULT ''");
            }
            $db->exec("CREATE TABLE IF NOT EXISTS tickets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                subject TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'open',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS ticket_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ticket_id INTEGER NOT NULL,
                sender TEXT NOT NULL DEFAULT 'user',
                body TEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
        } catch (PDOException $e) {
            error_log('[payments] migrate error: ' . $e->getMessage());
        }
    }

    /**
     * تنظیمات پرداخت ادمین (pay_config.json)
     * ساختار: card_number, card_holder, telegram_id, rubika_id, crypto_coin
     */
    function am_pay_config() {
        static $config = null;
        if ($config === null) {
            $config = [];
            $file = AM_PAY_CONFIG_FILE;
            if (file_exists($file)) {
                $decoded = json_decode((string)file_get_contents($file), true);
                if (is_array($decoded)) {
                    $config = $decoded;
                }
            }
        }
        return $config;
    }

    function am_pay_config_save($config) {
        if (!is_array($config)) $config = [];
        return @file_put_contents(AM_PAY_CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /** دسترسی ایمن به یک کلید داخل pay_config.json */
    function am_pay_setting($key, $default = '') {
        $c = am_pay_config();
        return isset($c[$key]) ? $c[$key] : $default;
    }

    /**
     * مزیت پلن: تعداد ماه‌های اشتراک برای هر پلن.
     * monthly=1, 3months=3, yearly=12
     */
    function am_plan_months($plan) {
        switch ($plan) {
            case 'monthly': return 1;
            case '3months': return 3;
            case 'yearly': return 12;
            default: return 0;
        }
    }

    /** برچسب قیمت بر اساس زبان جاری: تومان برای فارسی، دلار برای سایر زبان‌ها */
    function am_plan_price_label($plan) {
        $lang = function_exists('am_current_lang') ? am_current_lang() : 'fa';
        if ($lang !== 'fa') {
            // قیمت دلاری برای کاربران غیر فارسی‌زبان
            switch ($plan) {
                case 'monthly': return '$1';
                case '3months': return '$2.5';
                case 'yearly': return '$11';
                default: return '$1';
            }
        }
        switch ($plan) {
            case 'monthly': return '۲۹,۰۰۰ تومان';
            case '3months': return '۷۹,۰۰۰ تومان';
            case 'yearly': return '۲۵۹,۰۰۰ تومان';
            default: return '۲۹,۰۰۰ تومان';
        }
    }

    /**
     * پاکسازی خودکار پرداخت‌های تأیید/رد شده‌ی قدیمی‌تر از یک هفته
     * تا حجم داده‌ها (و رسیدهای آپلودشده) زیاد نشود.
     * فقط رکوردهای resolved (approved/rejected) حذف می‌شوند؛ pending دست نمی‌خورد.
     * @return int تعداد رکوردهای حذف‌شده
     */
    function am_payments_purge_resolved_old($days = 7) {
        am_payments_migrate();
        $days = max(1, (int)$days);
        try {
            $db = am_db_users();
            $cutoff = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
            $stmt = $db->prepare("SELECT id, receipt_path FROM payments WHERE status IN ('approved','rejected') AND updated_at < ?");
            $stmt->execute([$cutoff]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $count = 0;
            foreach ($rows as $row) {
                // حذف فایل رسید از دیسک در صورت وجود
                $rp = (string)($row['receipt_path'] ?? '');
                if ($rp !== '' && file_exists($rp)) {
                    @unlink($rp);
                }
                $db->prepare("DELETE FROM payments WHERE id = ?")->execute([(int)$row['id']]);
                $count++;
            }
            return $count;
        } catch (PDOException $e) {
            error_log('[payments] purge error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * آزادسازی مبلغ‌های یکتای کریپتو که رها شده‌اند:
     * اگر کاربری مبلغ یکتا رزرو کند ولی تا ۲۴ ساعت شناسه تراکنش ثبت نکند
     * و پرداخت هم تأیید/رد نشده باشد، آن پرداخت خودکار رد می‌شود تا اسلات آزاد گردد.
     */
    function am_crypto_expire_stale() {
        try {
            $db = am_db_users();
            $db->exec("UPDATE payments SET status = 'rejected', updated_at = datetime('now')
                       WHERE method = 'crypto' AND status = 'pending'
                         AND (tx_hash IS NULL OR tx_hash = '')
                         AND created_at < datetime('now', '-24 hours')");
        } catch (PDOException $e) {
            error_log('[payments] crypto expire error: ' . $e->getMessage());
        }
    }

    /**
     * مبلغ یکتای کریپتو برای یک کاربر.
     * تا وقتی پرداخت تأیید/رد نشده، این مبلغ متعلق به همان کاربر است و
     * هیچ کاربر دیگری همان مبلغ را نمی‌بیند. بعد از تأیید یا رد، اسلات آزاد می‌شود.
     */
    function am_crypto_unique_amount($plan, $userId) {
        am_crypto_expire_stale();
        try {
            $db = am_db_users();
            // اگر پرداخت معلق همین کاربر با همین پلن وجود دارد، همان مبلغ را برگردان
            $stmt = $db->prepare("SELECT crypto_amount FROM payments
                WHERE user_id = ? AND plan = ? AND method = 'crypto' AND status = 'pending'
                ORDER BY id DESC LIMIT 1");
            $stmt->execute([(int)$userId, $plan]);
            $existing = $stmt->fetch();
            if ($existing && $existing['crypto_amount'] !== '' && $existing['crypto_amount'] !== null) {
                return $existing['crypto_amount'];
            }

            // مبلغ دلاری پایه
            $usdTotal = 0.0;
            switch ($plan) {
                case 'monthly': $usdTotal = 1.0; break;
                case '3months': $usdTotal = 2.5; break;
                case 'yearly':  $usdTotal = 11.0; break;
            }

            // پیدا کردن اسلات یکتا: شروع از n=1 و ساخت مبلغ دقیق مثلاً 2.501
            $occupied = [];
            $rows = $db->query("SELECT crypto_amount FROM payments WHERE method = 'crypto' AND status = 'pending'")->fetchAll(PDO::FETCH_COLUMN);
            $occupied = array_map('floatval', $rows);
            $occupiedSet = array_flip(array_map(function ($v) { return number_format($v, 3, '.', ''); }, $occupied));

            for ($n = 1; $n < 10000; $n++) {
                $amount = round($usdTotal + ($n / 1000), 3); // 2.501, 2.502, ...
                $fmt = number_format($amount, 3, '.', '');
                if (!isset($occupiedSet[$fmt])) {
                    return $fmt;
                }
            }
            // fallback نهایی
            return number_format($usdTotal + rand(1, 999) / 1000, 3, '.', '');
        } catch (PDOException $e) {
            error_log('[payments] crypto amount error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * ثبت یک درخواست پرداخت.
     * @return int|null شناسه پرداخت یا null در خطا
     */
    function am_payment_create($userId, $plan, $method, $extra = []) {
        am_payments_migrate();
        try {
            $db = am_db_users();
            $priceLabel = am_plan_price_label($plan);
            $txHash = trim((string)($extra['tx_hash'] ?? ''));
            $cryptoAmount = trim((string)($extra['crypto_amount'] ?? ''));
            $payerName = trim((string)($extra['payer_name'] ?? ''));
            $payerCard = preg_replace('/[\s\-]/', '', trim((string)($extra['payer_card'] ?? '')));

            // برای کریپتو حتماً مبلغ یکتا تولید کن
            if ($method === 'crypto') {
                if ($cryptoAmount === '') {
                    $cryptoAmount = am_crypto_unique_amount($plan, (int)$userId);
                }
            }

            // اگر پرداخت معلق مشابه از همین کاربر/پلن/روش هست، دوباره نساز
            $stmt = $db->prepare("SELECT id FROM payments
                WHERE user_id = ? AND plan = ? AND method = ? AND status = 'pending'
                ORDER BY id DESC LIMIT 1");
            $stmt->execute([(int)$userId, $plan, $method]);
            $dup = $stmt->fetch();
            if ($dup) {
                // در صورت وجود، اطلاعات واریزکننده‌ی جدید را روی همان رکورد به‌روزرسانی کن
                if ($method === 'card' && ($payerName !== '' || $payerCard !== '')) {
                    $db->prepare("UPDATE payments SET payer_name = ?, payer_card = ?, updated_at = datetime('now') WHERE id = ?")
                       ->execute([$payerName, $payerCard, (int)$dup['id']]);
                }
                return (int)$dup['id'];
            }

            $stmt = $db->prepare("INSERT INTO payments (user_id, plan, method, price_label, tx_hash, crypto_amount, payer_name, payer_card)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([(int)$userId, $plan, $method, $priceLabel, $txHash, $cryptoAmount, $payerName, $payerCard]);
            return (int)$db->lastInsertId();
        } catch (PDOException $e) {
            error_log('[payments] create error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * فعال‌سازی VIP برای کاربر بعد از تأیید پرداخت.
     */
    function am_activate_vip($userId, $plan) {
        $months = am_plan_months($plan);
        if ($months <= 0) return false;
        try {
            $db = am_db_users();
            // اگر کاربر در حال حاضر VIP معتبر است، به پایان فعلی اضافه کن؛ وگرنه از امروز
            $stmt = $db->prepare("SELECT subscription_end_date FROM users WHERE id = ?");
            $stmt->execute([(int)$userId]);
            $row = $stmt->fetch();
            $endDate = $row && isset($row['subscription_end_date']) && $row['subscription_end_date']
                ? $row['subscription_end_date'] : date('Y-m-d');

            $expiry = new DateTime($endDate);
            // اگر تاریخ پایان قبلاً گذشته بود، از امروز شروع کن
            $today = new DateTime('today');
            if ($expiry < $today) {
                $expiry = $today;
            }
            $expiry->modify('+' . $months . ' months');
            $newEnd = $expiry->format('Y-m-d');

            $stmt = $db->prepare("UPDATE users SET subscription_status = 'vip', subscription_end_date = ? WHERE id = ?");
            $stmt->execute([$newEnd, (int)$userId]);
            return true;
        } catch (PDOException $e) {
            error_log('[payments] activate error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * تغییر وضعیت پرداخت (تأیید/رد).
     * در تأیید: VIP فعال می‌شود؛ در رد: فعال نمی‌شود.
     * در هر دو حالت رسید ذخیره‌شده حذف می‌شود.
     * اسلات مبلغ یکتای کریپتو بعد از تغییر وضعیت آزاد می‌شود (status دیگر pending نیست).
     */
    function am_payment_set_status($paymentId, $status) {
        am_payments_migrate();
        try {
            $db = am_db_users();
            $stmt = $db->prepare("SELECT * FROM payments WHERE id = ?");
            $stmt->execute([(int)$paymentId]);
            $pay = $stmt->fetch();
            if (!$pay) return false;

            $allowed = ['approved', 'rejected'];
            if (!in_array($status, $allowed, true)) return false;

            // فقط پرداخت‌های pending قابل تغییرند
            if ($pay['status'] !== 'pending') return false;

            $db->beginTransaction();
            try {
                $stmt = $db->prepare("UPDATE payments SET status = ?, updated_at = datetime('now') WHERE id = ?");
                $stmt->execute([$status, (int)$paymentId]);

                if ($status === 'approved') {
                    am_activate_vip((int)$pay['user_id'], (string)$pay['plan']);
                }

                // حذف فایل رسید (در هر دو حالت تأیید و رد)
                if (!empty($pay['receipt_path']) && file_exists($pay['receipt_path'])) {
                    @unlink($pay['receipt_path']);
                }
                $db->commit();
                return true;
            } catch (Exception $inner) {
                $db->rollBack();
                throw $inner;
            }
        } catch (PDOException $e) {
            error_log('[payments] set status error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * ذخیره‌سازی فایل رسید (کارت‌به‌کارت / کریپتو) در uploads/receipts
     * @return string|null مسیر ذخیره‌شده یا null
     */
    function am_save_receipt($fileField) {
        if (!isset($_FILES[$fileField]) || ($_FILES[$fileField]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        $file = $_FILES[$fileField];
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $maxSize) {
            return null;
        }
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $info = @getimagesize($file['tmp_name']);
        $mime = $info ? $info['mime'] : '';
        if (!$info || !in_array($mime, $allowed, true)) {
            return null;
        }
        $dir = AM_RECEIPTS_DIR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        // جلوگیری از دسترسی مستقیم وب به رسیدها
        $ht = $dir . '/.htaccess';
        if (!file_exists($ht)) {
            @file_put_contents($ht, "Order Deny,Allow\nDeny from all\n");
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $ext = 'jpg';
        }
        $name = 'receipt_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $target = $dir . '/' . $name;
        if (@move_uploaded_file($file['tmp_name'], $target)) {
            return $target;
        }
        return null;
    }

    /**
     * پیوست‌کردن رسید به یک پرداخت معلق.
     */
    function am_payment_attach_receipt($paymentId, $receiptPath) {
        try {
            $db = am_db_users();
            $stmt = $db->prepare("UPDATE payments SET receipt_path = ?, updated_at = datetime('now') WHERE id = ? AND status = 'pending'");
            $stmt->execute([$receiptPath, (int)$paymentId]);
            return ($stmt->rowCount() > 0);
        } catch (PDOException $e) {
            error_log('[payments] attach receipt error: ' . $e->getMessage());
            return false;
        }
    }

    /** آخرین پرداخت کاربر برای یک پلن/روش خاص */
    function am_payment_for_plan($userId, $plan, $method = null) {
        am_payments_migrate();
        try {
            $db = am_db_users();
            if ($method !== null) {
                $stmt = $db->prepare("SELECT * FROM payments WHERE user_id = ? AND plan = ? AND method = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([(int)$userId, $plan, $method]);
            } else {
                $stmt = $db->prepare("SELECT * FROM payments WHERE user_id = ? AND plan = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([(int)$userId, $plan]);
            }
            return $stmt->fetch() ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    /* ==================== تیکت‌ها ==================== */

    /** ایجاد تیکت جدید (تنها برای کاربران VIP از پنل خودشان) */
    function am_ticket_create($userId, $subject, $body) {
        am_payments_migrate();
        try {
            $db = am_db_users();
            $stmt = $db->prepare("INSERT INTO tickets (user_id, subject, status) VALUES (?, ?, 'open')");
            $stmt->execute([(int)$userId, trim($subject)]);
            $ticketId = (int)$db->lastInsertId();
            $stmt = $db->prepare("INSERT INTO ticket_messages (ticket_id, sender, body) VALUES (?, 'user', ?)");
            $stmt->execute([$ticketId, trim($body)]);
            return $ticketId;
        } catch (PDOException $e) {
            error_log('[tickets] create error: ' . $e->getMessage());
            return null;
        }
    }

    /** افزودن پیام به تیکت (sender = user یا admin) */
    function am_ticket_message_add($ticketId, $sender, $body) {
        try {
            $db = am_db_users();
            $stmt = $db->prepare("INSERT INTO ticket_messages (ticket_id, sender, body) VALUES (?, ?, ?)");
            $stmt->execute([(int)$ticketId, ($sender === 'admin') ? 'admin' : 'user', trim($body)]);
            $db->prepare("UPDATE tickets SET status = 'open', updated_at = datetime('now') WHERE id = ?")
               ->execute([(int)$ticketId]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /** تیکت‌های کاربر مشخص */
    function am_tickets_for_user($userId) {
        am_payments_migrate();
        try {
            $db = am_db_users();
            $stmt = $db->prepare("SELECT * FROM tickets WHERE user_id = ? ORDER BY id DESC");
            $stmt->execute([(int)$userId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /** پیام‌های یک تیکت */
    function am_ticket_messages($ticketId) {
        try {
            $db = am_db_users();
            $stmt = $db->prepare("SELECT * FROM ticket_messages WHERE ticket_id = ? ORDER BY id ASC");
            $stmt->execute([(int)$ticketId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /** یک تیکت خاص (با بررسی مالکیت توسط فراخواننده) */
    function am_ticket_get($ticketId) {
        try {
            $db = am_db_users();
            $stmt = $db->prepare("SELECT * FROM tickets WHERE id = ?");
            $stmt->execute([(int)$ticketId]);
            return $stmt->fetch() ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    /** بستن تیکت توسط ادمین */
    function am_ticket_close($ticketId) {
        try {
            $db = am_db_users();
            $stmt = $db->prepare("UPDATE tickets SET status = 'closed', updated_at = datetime('now') WHERE id = ?");
            $stmt->execute([(int)$ticketId]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /** حذف تیکت توسط ادمین */
    function am_ticket_delete($ticketId) {
        try {
            $db = am_db_users();
            $db->prepare("DELETE FROM ticket_messages WHERE ticket_id = ?")->execute([(int)$ticketId]);
            $db->prepare("DELETE FROM tickets WHERE id = ?")->execute([(int)$ticketId]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /** حذف همه تیکت‌های قدیمی‌تر از یک ماه */
    function am_tickets_delete_older_than_month() {
        am_payments_migrate();
        try {
            $db = am_db_users();
            $cutoff = date('Y-m-d H:i:s', strtotime('-1 month'));
            $ids = $db->query("SELECT id FROM tickets WHERE updated_at < datetime('now', '-1 month')")
                      ->fetchAll(PDO::FETCH_COLUMN);
            $count = 0;
            foreach ($ids as $id) {
                $db->prepare("DELETE FROM ticket_messages WHERE ticket_id = ?")->execute([(int)$id]);
                $db->prepare("DELETE FROM tickets WHERE id = ?")->execute([(int)$id]);
                $count++;
            }
            return $count;
        } catch (PDOException $e) {
            return 0;
        }
    }
}
