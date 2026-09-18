<?php
// صفحه ایندکس پنل مدیریت — فقط هدایت به ورود یا داشبورد
session_start();

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin.php");
} else {
    header("Location: login.php");
}
exit;
