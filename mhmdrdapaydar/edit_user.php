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
  <style>
    /* استایل‌ها مشابه edit_anime.php */
  </style>
</head>
<body>
  <div class="container">
    <h1><i class="fas fa-edit"></i> ویرایش کاربر</h1>
    
    <?php if (isset($error)): ?>
      <div class="error"><?= $error ?></div>
    <?php endif; ?>
    
    <form method="post">
      <div class="form-group">
        <label>نام:</label>
        <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>
      </div>
      
      <div class="form-group">
        <label>نام خانوادگی:</label>
        <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>
      </div>
      
      <div class="form-group">
        <label>نام کاربری:</label>
        <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
      </div>
      
      <div class="form-group">
        <label>رمز عبور جدید (اختیاری):</label>
        <input type="password" name="password">
      </div>
      
      <div class="form-group">
        <label>وضعیت اشتراک:</label>
        <select name="subscription_status">
          <option value="free" <?= $user['subscription_status'] === 'free' ? 'selected' : '' ?>>عادی</option>
          <option value="vip" <?= $user['subscription_status'] === 'vip' ? 'selected' : '' ?>>VIP</option>
        </select>
      </div>
      
      <div class="form-group">
        <label>تاریخ پایان اشتراک (اختیاری):</label>
        <input type="date" name="subscription_end_date" value="<?= $user['subscription_end_date'] ?>">
      </div>
      
      <button type="submit">ذخیره تغییرات</button>
      <a href="admin.php" style="margin-right: 15px;">بازگشت</a>
    </form>
  </div>
</body>
</html>