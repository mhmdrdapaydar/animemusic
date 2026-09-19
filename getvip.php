<?php
require_once __DIR__ . '/includes/headless.php';

// اگر کاربر لاگین نکرده باشد، به صفحه ورود ریدایرکت شود
am_require_login();

// بررسی وضعیت تم کاربر
$isDarkMode = am_theme();

// دریافت کاربر جاری + اصلاح خودکار VIP منقضی
$user = am_current_user();

// زبان جاری (fa => تومان؛ غیر fa => دلار)
$lang = am_current_lang();
$isFaLang = ($lang === 'fa');

// دریافت اطلاعات پلن از URL
$plan = $_GET['plan'] ?? '';
$plan_name = '';
$plan_price = '';
$plan_period = '';

switch($plan) {
    case 'monthly':
        $plan_name = am_t('monthly_plan');
        $plan_price = am_plan_price_label('monthly');
        $plan_period = am_t('one_month_sub');
        break;
    case '3months':
        $plan_name = am_t('quarterly_plan');
        $plan_price = am_plan_price_label('3months');
        $plan_period = am_t('three_month_sub');
        break;
    case 'yearly':
        $plan_name = am_t('yearly_plan');
        $plan_price = am_plan_price_label('yearly');
        $plan_period = am_t('one_year_sub');
        break;
    default:
        header("Location: " . am_lang_url('vip.php'));
        exit;
}

// تنظیمات پرداخت ادمین
$cardNumber   = am_pay_setting('card_number', '');
$cardHolder   = am_pay_setting('card_holder', '');
$telegramId   = am_pay_setting('telegram_id', 'I_MHP_I');
$rubikaId     = am_pay_setting('rubika_id', 'I_MHP_I');
$cryptoCoin   = am_pay_setting('crypto_coin', 'USDT (TRC20)');
$cryptoWallet = am_pay_setting('crypto_wallet', '');

$message = '';
$message_type = '';
$result = '';

// تابع تبدیل تاریخ میلادی به شمسی (الگوریتم استاندارد)
function jdate($format, $timestamp = '') {
    return am_jdate($format, $timestamp);
}

/* ===================== پردازش فرم‌ها ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        /* --- کارت‌به‌کارت: ساخت پرداخت + ذخیره رسید --- */
        case 'card':
            $receiptPath = am_save_receipt('receipt');
            if ($receiptPath === null) {
                $message = am_t('receipt_upload_error');
                $message_type = 'error';
            } else {
                $pid = am_payment_create((int)$user['id'], $plan, 'card');
                if ($pid) {
                    am_payment_attach_receipt($pid, $receiptPath);
                    $result = 'receipt_sent';
                    $message = am_t('receipt_sent');
                    $message_type = 'success';
                } else {
                    @unlink($receiptPath);
                    $message = am_t('signup_error');
                    $message_type = 'error';
                }
            }
            break;

        /* --- کریپتو: رزرو مبلغ یکتا --- */
        case 'crypto_reserve':
            $pid = am_payment_create((int)$user['id'], $plan, 'crypto');
            if ($pid) {
                $result = 'crypto_reserved';
            } else {
                $message = am_t('signup_error');
                $message_type = 'error';
            }
            break;

        /* --- کریپتو: ثبت شناسه تراکنش --- */
        case 'crypto':
            $txHash = trim($_POST['tx_hash'] ?? '');
            $pid = (int)($_POST['payment_id'] ?? 0);
            if ($pid > 0 && $txHash !== '') {
                try {
                    $db = am_users_db();
                    $stmt = $db->prepare("UPDATE payments SET tx_hash = ?, updated_at = datetime('now')
                                           WHERE id = ? AND user_id = ? AND method = 'crypto' AND status = 'pending'");
                    $stmt->execute([$txHash, $pid, (int)$user['id']]);
                    if ($stmt->rowCount() > 0) {
                        $result = 'tx_hash_sent';
                        $message = am_t('tx_hash_sent');
                        $message_type = 'success';
                    } else {
                        $message = am_t('signup_error');
                        $message_type = 'error';
                    }
                } catch (PDOException $e) {
                    $message = am_t('signup_error');
                    $message_type = 'error';
                }
            } else {
                $message = am_t('fill_required_fields');
                $message_type = 'error';
            }
            break;

        /* --- تلگرام: ثبت درخواست پرداخت (ادمین از طریق گفتگو تأیید می‌کند) --- */
        case 'telegram':
            if (am_payment_create((int)$user['id'], $plan, 'telegram')) {
                $result = 'telegram';
                $message = am_t('payment_pending');
                $message_type = 'success';
            } else {
                $message = am_t('signup_error');
                $message_type = 'error';
            }
            break;
    }
}

/* ---------- وضعیت پرداخت‌های قبلی کاربر برای همین پلن ---------- */
$pendingPayment = am_payment_for_plan((int)$user['id'], $plan);
$cryptoPending = am_payment_for_plan((int)$user['id'], $plan, 'crypto');
?>
<!DOCTYPE html>
<html lang="<?= am_e(am_lang()) ?>" dir="<?= am_e(am_lang_dir()) ?>" data-theme="<?= $isDarkMode ? 'dark' : 'light' ?>">
<head>
  <?= am_lang_base_tag() ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= am_te('complete_purchase') ?> | <?= am_te('site_name') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #00ff6a;
            --secondary-color: #00ffc3;
            --accent-color: #ff00aa;
            --bg-dark: #0a0a0a;
            --bg-light: #f5f5f5;
            --card-bg-dark: #1a1a1a;
            --card-bg-light: #ffffff;
            --text-dark: #eee;
            --text-light: #333;
            --text-gray-dark: #bbb;
            --text-gray-light: #777;
            --border-radius: 14px;
            --border-color-dark: #333;
            --border-color-light: #e0e0e0;
            --transition-speed: 0.3s;
        }

        [data-theme="light"] {
            --bg-primary: var(--bg-light);
            --bg-card: var(--card-bg-light);
            --text-primary: var(--text-light);
            --text-secondary: var(--text-gray-light);
            --border-color: var(--border-color-light);
            --shadow-color: rgba(0, 0, 0, 0.1);
        }

        [data-theme="dark"] {
            --bg-primary: var(--bg-dark);
            --bg-card: var(--card-bg-dark);
            --text-primary: var(--text-dark);
            --text-secondary: var(--text-gray-dark);
            --border-color: var(--border-color-dark);
            --shadow-color: rgba(0, 0, 0, 0.3);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Vazirmatn', sans-serif;
            margin: 0;
            padding: 0;
            transition: background-color var(--transition-speed), color var(--transition-speed);
            padding-bottom: 90px;
        }

        .header {
            background: var(--bg-card);
            padding: 15px 20px;
            text-align: center;
            box-shadow: 0 4px 12px var(--shadow-color);
            margin-bottom: 30px;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header img {
            height: 50px;
        }

        .theme-toggle {
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 24px;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: background-color var(--transition-speed);
        }

        .theme-toggle:hover {
            background-color: rgba(0, 0, 0, 0.1);
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .plan-summary {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: 0 4px 20px var(--shadow-color);
            border: 1px solid var(--border-color);
            margin-bottom: 30px;
            text-align: center;
            transition: all var(--transition-speed);
        }

        .plan-summary:hover {
            box-shadow: 0 8px 25px var(--shadow-color);
        }

        .plan-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
            color: var(--primary-color);
        }

        .plan-price {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--text-primary);
        }

        .plan-period {
            font-size: 16px;
            color: var(--text-secondary);
            margin-bottom: 20px;
        }

        .payment-methods {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: 0 4px 20px var(--shadow-color);
            border: 1px solid var(--border-color);
            transition: all var(--transition-speed);
        }

        .payment-methods:hover {
            box-shadow: 0 8px 25px var(--shadow-color);
        }

        .section-title {
            color: var(--primary-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
            font-size: 22px;
            font-weight: 700;
            text-align: center;
        }

        .payment-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 20px;
        }

        .payment-option {
            background: linear-gradient(135deg, var(--bg-card), var(--bg-primary));
            border-radius: var(--border-radius);
            border: 2px solid var(--border-color);
            transition: all var(--transition-speed);
            cursor: pointer;
            overflow: hidden;
        }

        .payment-option-head {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 18px;
            width: 100%;
            background: none;
            border: none;
            color: inherit;
            font-family: inherit;
            text-align: start;
            cursor: pointer;
        }

        .payment-option:hover,
        .payment-option.open {
            border-color: var(--primary-color);
            box-shadow: 0 8px 20px rgba(0, 255, 106, 0.12);
        }

        .payment-icon {
            font-size: 30px;
            color: var(--primary-color);
            flex-shrink: 0;
            width: 52px;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: rgba(0, 255, 106, 0.08);
        }

        .payment-name {
            font-size: 17px;
            font-weight: bold;
            color: var(--text-primary);
            margin-bottom: 2px;
        }

        .payment-description {
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .payment-caret {
            margin-inline-start: auto;
            color: var(--text-secondary);
            transition: transform 0.3s ease;
            flex-shrink: 0;
        }
        .payment-option.open .payment-caret {
            transform: rotate(180deg);
        }

        .payment-panel {
            padding: 0 18px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.35s ease, padding 0.35s ease;
        }
        .payment-option.open .payment-panel {
            padding: 16px 18px;
            max-height: 900px;
            overflow-y: auto;
            border-top: 1px solid var(--border-color);
        }

        .payment-panel-inner {
            text-align: start;
        }

        .instruction-title {
            font-weight: bold;
            margin-bottom: 15px;
            color: var(--primary-color);
            font-size: 18px;
        }

        .instruction-text {
            margin-bottom: 15px;
            line-height: 1.8;
        }

        .contact-link {
            display: inline-block;
            background: var(--primary-color);
            color: #000;
            padding: 10px 20px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: bold;
            margin: 10px 5px;
            transition: all var(--transition-speed);
        }

        .contact-link:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }

        .card-box {
            background: rgba(255, 255, 255, 0.05);
            border: 1px dashed var(--primary-color);
            border-radius: var(--border-radius);
            padding: 20px;
            margin: 15px 0;
            text-align: center;
        }

        .card-box .card-number-txt {
            font-size: 24px;
            letter-spacing: 2px;
            font-weight: bold;
            direction: ltr;
            color: var(--text-primary);
            word-break: break-all;
        }

        .card-box .card-holder-txt {
            margin-top: 8px;
            color: var(--text-secondary);
            font-size: 14px;
        }

        .copy-btn {
            background: var(--secondary-color);
            color: #000;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            font-family: inherit;
            margin-top: 10px;
        }

        .unique-amount {
            font-size: 30px;
            font-weight: bold;
            direction: ltr;
            color: var(--accent-color);
            letter-spacing: 1px;
            background: rgba(255, 0, 170, 0.08);
            display: inline-block;
            padding: 10px 24px;
            border-radius: 12px;
            border: 1px dashed var(--accent-color);
            margin: 10px 0;
        }

        .form-group {
            margin-bottom: 15px;
            text-align: right;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            color: var(--text-secondary);
        }
        .form-control {
            width: 100%;
            padding: 12px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-primary);
            font-family: inherit;
            font-size: 15px;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .btn {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: #000;
            border: none;
            padding: 12px 24px;
            border-radius: var(--border-radius);
            font-family: inherit;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: all var(--transition-speed);
            width: 100%;
            text-align: center;
        }
        .btn:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            transform: translateY(-2px);
        }

        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeIn 0.5s ease;
        }
        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }
        .alert-error {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .navbar {
            position: fixed;
            bottom: 0;
            width: 100%;
            background-color: var(--bg-card);
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            text-align: center;
            padding: 12px 0 10px;
            border-top: 1px solid var(--border-color);
            z-index: 999;
            box-shadow: 0 -2px 10px var(--shadow-color);
        }

        .navbar a {
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 13px;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: 0.3s;
        }

        .navbar a i {
            font-size: 22px;
            margin-bottom: 2px;
        }

        .navbar a.active {
            color: var(--primary-color);
        }

        .navbar a:hover {
            color: var(--primary-color);
            transform: translateY(-3px);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .payment-options {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                gap: 15px;
                padding: 15px;
            }
        }
    </style>
    <link rel="icon" type="image/x-icon" href="/favicon.ico" />
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png" />
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" />
    <link rel="stylesheet" href="/assets/site.css?v=4" />
    <script defer src="/assets/site.js?v=1"></script>
</head>
<body>
    <div class="header">
        <img src="/image.png" alt="Logo">
        <button class="theme-toggle" id="themeToggle">
            <i class="bi <?= $isDarkMode ? 'bi-sun' : 'bi-moon' ?>"></i>
        </button>
    </div>

    <div class="container">
        <div class="plan-summary">
            <h2><?= am_te('selected_plan') ?></h2>
            <div class="plan-name"><?= am_e($plan_name) ?></div>
            <div class="plan-price"><?= am_e($plan_price) ?></div>
            <div class="plan-period"><?= am_e($plan_period) ?></div>
            <p style="margin-top: 15px; color: var(--text-secondary);"><?= am_te('choose_payment_method') ?></p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>">
                <i class="bi <?= $message_type == 'success' ? 'bi-check-circle' : 'bi-exclamation-circle' ?>"></i>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="payment-methods">
            <h2 class="section-title"><?= am_te('payment_method_title') ?></h2>

            <div class="payment-options">
                <?php if ($isFaLang): ?>
                <!-- کارت به کارت -->
                <div class="payment-option" id="opt-cardPanel">
                    <button type="button" class="payment-option-head" onclick="togglePanel('cardPanel')" aria-expanded="false">
                        <div class="payment-icon"><i class="bi bi-credit-card"></i></div>
                        <div>
                            <div class="payment-name"><?= am_te('pay_card') ?></div>
                            <div class="payment-description"><?= am_te('pay_card_desc') ?></div>
                        </div>
                        <i class="bi bi-chevron-down payment-caret"></i>
                    </button>
                    <div id="cardPanel" class="payment-panel">
                        <div class="payment-panel-inner">
                            <?php if ($cardNumber !== ''): ?>
                            <div class="card-box">
                                <div><?= am_te('card_number') ?></div>
                                <div class="card-number-txt" id="cardNumberTxt"><?= am_e($cardNumber) ?></div>
                                <?php if ($cardHolder !== ''): ?>
                                <div class="card-holder-txt"><?= am_te('card_holder') ?>: <?= am_e($cardHolder) ?></div>
                                <?php endif; ?>
                                <button type="button" class="copy-btn" onclick="copyCard()"><i class="bi bi-clipboard"></i> <?= am_te('copy_card') ?></button>
                            </div>
                            <div class="instruction-text"><?= am_te('settle_card_payment') ?></div>
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="card">
                                <div class="form-group">
                                    <label for="receipt"><?= am_te('upload_receipt') ?> (JPG/PNG/WebP &le; 10MB)</label>
                                    <input type="file" id="receipt" name="receipt" class="form-control" accept="image/*" required>
                                </div>
                                <button type="submit" class="btn"><i class="bi bi-cloud-upload"></i> <?= am_te('submit_receipt') ?></button>
                            </form>
                            <?php else: ?>
                            <div class="instruction-text"><?= am_te('payment_pending') ?></div>
                            <div class="instruction-text" style="margin-top:10px;"><?= am_te('contact_admin') ?></div>
                            <a href="http://t.me/<?= am_e($telegramId) ?>" target="_blank" class="contact-link"><i class="bi bi-telegram"></i> <?= am_te('contact_admin') ?></a>
                            <?php endif; ?>
                            <?php if ($pendingPayment && $pendingPayment['method'] === 'card' && $pendingPayment['status'] === 'pending'): ?>
                            <div class="instruction-text" style="margin-top:15px;"><?= am_te('payment_pending') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- روبیکا -->
                <div class="payment-option" id="opt-rubikaPanel">
                    <button type="button" class="payment-option-head" onclick="togglePanel('rubikaPanel')" aria-expanded="false">
                        <div class="payment-icon"><i class="bi bi-chat-dots"></i></div>
                        <div>
                            <div class="payment-name"><?= am_te('pay_rubika') ?></div>
                            <div class="payment-description"><?= am_te('pay_rubika_desc') ?></div>
                        </div>
                        <i class="bi bi-chevron-down payment-caret"></i>
                    </button>
                    <div id="rubikaPanel" class="payment-panel">
                        <div class="payment-panel-inner">
                            <div class="instruction-title"><?= am_te('rubika_guide') ?></div>
                            <div class="instruction-text"><?= am_te('enter_rubika_id') ?></div>
                            <a href="https://rubika.ir/<?= am_e($rubikaId) ?>" target="_blank" class="contact-link">
                                <i class="bi bi-chat-dots"></i> <?= am_e($rubikaId) ?>@
                            </a>
                            <div class="instruction-text" style="margin-top: 20px;"><?= am_te('send_plan_info') ?></div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- کریپتو -->
                <div class="payment-option" id="opt-cryptoPanel">
                    <button type="button" class="payment-option-head" onclick="togglePanel('cryptoPanel')" aria-expanded="false">
                        <div class="payment-icon"><i class="bi bi-currency-bitcoin"></i></div>
                        <div>
                            <div class="payment-name"><?= am_te('pay_crypto') ?></div>
                            <div class="payment-description"><?= am_te('pay_crypto_desc') ?></div>
                        </div>
                        <i class="bi bi-chevron-down payment-caret"></i>
                    </button>
                    <div id="cryptoPanel" class="payment-panel">
                        <div class="payment-panel-inner">
                            <div class="instruction-title"><i class="bi bi-currency-bitcoin"></i> <?= am_te('pay_crypto') ?> — <?= am_e($cryptoCoin) ?></div>

                            <?php if ($cryptoPending && $cryptoPending['status'] === 'pending' && $cryptoPending['crypto_amount'] !== ''): ?>
                                <div class="instruction-text"><?= am_te('crypto_amount_unique') ?></div>
                                <div class="unique-amount"><?= am_e($cryptoPending['crypto_amount']) ?></div>
                                <?php if ($cryptoWallet !== ''): ?>
                                <div class="form-group">
                                    <label><?= am_te('crypto_wallet') ?></label>
                                    <div class="card-box"><span style="direction:ltr;word-break:break-all;"><?= am_e($cryptoWallet) ?></span></div>
                                </div>
                                <?php endif; ?>
                                <?php if ($cryptoPending['tx_hash'] === ''): ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="crypto">
                                    <input type="hidden" name="payment_id" value="<?= (int)$cryptoPending['id'] ?>">
                                    <div class="form-group">
                                        <label for="tx_hash"><?= am_te('tx_hash') ?></label>
                                        <input type="text" id="tx_hash" name="tx_hash" class="form-control" style="direction:ltr;" required>
                                    </div>
                                    <button type="submit" class="btn"><i class="bi bi-send"></i> <?= am_te('submit_hash') ?></button>
                                </form>
                                <?php else: ?>
                                <div class="instruction-text"><?= am_te('payment_pending') ?></div>
                                <?php endif; ?>
                            <?php elseif ($cryptoPending && $cryptoPending['tx_hash'] !== '' && $cryptoPending['status'] === 'pending'): ?>
                                <div class="instruction-text"><?= am_te('payment_pending') ?></div>
                            <?php else: ?>
                                <div class="instruction-text"><?= am_te('pay_crypto_desc') ?></div>
                                <form method="post">
                                    <input type="hidden" name="action" value="crypto_reserve">
                                    <button type="submit" class="btn"><i class="bi bi-lock"></i> <?= am_te('select_payment_continue') ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- تلگرام -->
                <div class="payment-option" id="opt-telegramPanel">
                    <button type="button" class="payment-option-head" onclick="togglePanel('telegramPanel')" aria-expanded="false">
                        <div class="payment-icon"><i class="bi bi-telegram"></i></div>
                        <div>
                            <div class="payment-name"><?= am_te('pay_telegram') ?></div>
                            <div class="payment-description"><?= am_te('pay_telegram_desc') ?></div>
                        </div>
                        <i class="bi bi-chevron-down payment-caret"></i>
                    </button>
                    <div id="telegramPanel" class="payment-panel">
                        <div class="payment-panel-inner">
                            <div class="instruction-title"><?= am_te('telegram_guide') ?></div>
                            <div class="instruction-text"><?= am_te('message_admin_vpn') ?></div>
                            <a href="http://t.me/<?= am_e($telegramId) ?>" target="_blank" class="contact-link">
                                <i class="bi bi-telegram"></i> <?= am_te('admin_telegram') ?>
                            </a>
                            <form method="post" style="margin-top:20px;">
                                <input type="hidden" name="action" value="telegram">
                                <button type="submit" class="btn"><i class="bi bi-send"></i> <?= am_te('complete_purchase') ?></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="navbar">
            </div>
        </div>
    </div>

    <div class="navbar">
        <a href="<?= am_lang_url('index.php') ?>"><i class="bi bi-house"></i> <?= am_te('home') ?></a>
        <a href="<?= am_lang_url('categories.php') ?>"><i class="bi bi-grid-1x2-fill"></i> <?= am_te('categories') ?></a>
        <a href="<?= am_lang_url('vip.php') ?>"><i class="bi bi-star"></i> VIP</a>
        <a href="<?= am_lang_url('profile.php') ?>"><i class="bi bi-person"></i> <?= am_te('profile') ?></a>
    </div>

    <script>
        // سیستم تغییر تم
        const themeToggle = document.getElementById('themeToggle');
        const htmlElement = document.documentElement;

        themeToggle.addEventListener('click', () => {
            const isDark = htmlElement.getAttribute('data-theme') === 'dark';
            const newTheme = isDark ? 'light' : 'dark';

            htmlElement.setAttribute('data-theme', newTheme);
            themeToggle.innerHTML = `<i class="bi ${newTheme === 'dark' ? 'bi-sun' : 'bi-moon'}"></i>`;

            // ذخیره تنظیمات در کوکی به مدت 30 روز
            document.cookie = `dark_mode=${newTheme === 'dark'}; max-age=${30 * 24 * 60 * 60}; path=/`;
        });

        // باز/بسته‌کردن پنل پرداخت به‌صورت آکاردئونی (بدون پرش به انتهای صفحه)
        function togglePanel(id) {
            const panel = document.getElementById(id);
            if (!panel) return;
            const option = panel.closest('.payment-option');
            const isOpen = option.classList.contains('open');

            // بستن همهٔ پنل‌ها
            document.querySelectorAll('.payment-option.open').forEach(function (o) {
                o.classList.remove('open');
                const btn = o.querySelector('.payment-option-head');
                if (btn) btn.setAttribute('aria-expanded', 'false');
            });

            // اگر بسته بود، همین پنل را باز کن
            if (!isOpen) {
                option.classList.add('open');
                const btn = option.querySelector('.payment-option-head');
                if (btn) btn.setAttribute('aria-expanded', 'true');
                // اسکرول نرم تا ابتدای گزینه (پنل زیر همان گزینه باز می‌شود)
                option.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        // کپی شماره کارت
        function copyCard() {
            const txt = document.getElementById('cardNumberTxt').textContent.trim();
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(txt).then(() => alert('<?= addslashes(am_t('copied')) ?>'));
            } else {
                const ta = document.createElement('textarea');
                ta.value = txt;
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); alert('<?= addslashes(am_t('copied')) ?>'); } catch (e) {}
                document.body.removeChild(ta);
            }
        }

        <?php if ($result === 'receipt_sent' || $result === 'tx_hash_sent' || $result === 'crypto_reserved'): ?>
        // باز کردن خودکار پنل مربوطه بعد از ارسال فرم
        togglePanel('<?= ($result === 'tx_hash_sent' || $result === 'crypto_reserved') ? 'cryptoPanel' : 'cardPanel' ?>');
        <?php else: ?>
        // پیش‌فرض: اولین روش پرداخت باز باشد تا کاربر فوراً جزئیات را ببیند
        (function () {
            const firstHead = document.querySelector('.payment-option .payment-option-head');
            if (firstHead) firstHead.click();
        })();
        <?php endif; ?>
    </script>
</body>
</html>
