<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// بررسی وجود و صحت پارامتر ID
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    header("Location: admin.php?error=invalid_request");
    exit;
}

$user_id = (int)$_GET['id'];

try {
    // اتصال به دیتابیس کاربران
    $db_users = new PDO('sqlite:' . __DIR__ . '/../db/users.db');
    $db_users->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // دریافت اطلاعات کاربر
    $stmt = $db_users->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header("Location: admin.php?error=content_not_found");
        exit;
    }
    
    // اگر فرم ارسال شده باشد
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // اعتبارسنجی فیلدهای اجباری
        $required_fields = ['first_name', 'last_name', 'username'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $error = "فیلد اجباری پر نشده است: " . $field;
                break;
            }
        }
        
        if (!isset($error)) {
            // اعتبارسنجی وضعیت اشتراک
            $subStatus = ($_POST['subscription_status'] === 'vip') ? 'vip' : 'free';
            $subEnd = trim($_POST['subscription_end_date'] ?? '');

            // اگر تاریخ پایان نامعتبر است، مقدار null قرار بده
            if ($subEnd === '') {
                $subEnd = null;
            } else {
                $ts = strtotime($subEnd);
                if ($ts === false) {
                    $error = 'تاریخ پایان اشتراک نامعتبر است';
                } else {
                    $subEnd = date('Y-m-d', $ts);
                }
            }

            if (!isset($error)) {
                // قاعده: اگر وضعیت عادی است، تاریخ پایان را خالی کن
                if ($subStatus === 'free') {
                    $subEnd = null;
                }

                // به‌روزرسانی اطلاعات کاربر
                $stmt = $db_users->prepare("
                    UPDATE users 
                    SET first_name = ?, last_name = ?, username = ?, 
                        subscription_status = ?, subscription_end_date = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $_POST['first_name'],
                    $_POST['last_name'],
                    $_POST['username'],
                    $subStatus,
                    $subEnd,
                    $user_id
                ]);
                
                // اگر رمز عبور جدید وارد شده باشد
                if (!empty($_POST['password'])) {
                    $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $db_users->prepare("UPDATE users SET password = ? WHERE id = ?")
                             ->execute([$hashed_password, $user_id]);
                }
                
                header("Location: admin.php?success=content_updated");
                exit;
            }
        }
    }
    
} catch (PDOException $e) {
    error_log("Edit user error: " . $e->getMessage());
    header("Location: admin.php?error=database_error&message=" . urlencode($e->getMessage()));
    exit;
}

// نمایش فرم ویرایش
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ویرایش کاربر</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/admin.css?v=4">
</head>
<body>
<div class="container">
  <header class="admin-header">
    <div class="brand"><i class="fas fa-edit"></i><span>ویرایش کاربر</span></div>
    <div class="header-actions"><a class="btn" href="admin.php"><i class="fas fa-arrow-right"></i> بازگشت</a></div>
  </header>

  <div class="card" style="padding:20px;">
    <?php if (isset($error)): ?>
      <div class="message error-message"><i class="fas fa-exclamation-circle"></i><div><?= htmlspecialchars($error) ?></div></div>
    <?php endif; ?>

    <form method="post">
      <div class="form-grid">
        <div class="form-group"><label>نام</label><input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($user['first_name']) ?>" required></div>
        <div class="form-group"><label>نام خانوادگی</label><input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($user['last_name']) ?>" required></div>
        <div class="form-group"><label>نام کاربری</label><input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required></div>
        <div class="form-group"><label>رمز عبور جدید (اختیاری)</label><input type="password" name="password" class="form-control"></div>
        <div class="form-group"><label>وضعیت اشتراک</label>
          <select name="subscription_status" class="form-control">
            <option value="free" <?= $user['subscription_status'] === 'free' ? 'selected' : '' ?>>عادی</option>
            <option value="vip" <?= $user['subscription_status'] === 'vip' ? 'selected' : '' ?>>VIP</option>
          </select>
        </div>
        <div class="form-group"><label>تاریخ پایان اشتراک (اختیاری)</label><input type="date" name="subscription_end_date" class="form-control" value="<?= htmlspecialchars($user['subscription_end_date']) ?>"></div>
      </div>
      <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> ذخیره تغییرات</button>
    </form>
  </div>
</div>
</body>
</html>