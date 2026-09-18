<?php
session_start();
// فقط برای ادمین قابل دسترسی باشد
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("دسترسی غیرمجاز");
}

$db_path = '../db/data.db';

try {
    // پاک کردن فایل قدیمی اگر وجود دارد
    if (file_exists($db_path)) {
        unlink($db_path);
    }

    // ایجاد اتصال به دیتابیس جدید
    $db = new PDO("sqlite:$db_path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ایجاد جدول content
    $db->exec("
        CREATE TABLE content (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            type TEXT NOT NULL,
            category TEXT,
            image_url TEXT,
            video_360 TEXT,
            video_480 TEXT,
            video_720 TEXT,
            video_1080 TEXT,
            file_link TEXT,
            price INTEGER DEFAULT 0,
            discount INTEGER DEFAULT 0,
            team TEXT,
            details TEXT,
            has_seasons INTEGER DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // ایجاد جدول episodes
    $db->exec("
        CREATE TABLE episodes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content_id INTEGER NOT NULL,
            season INTEGER NOT NULL,
            episode INTEGER NOT NULL,
            video_360 TEXT,
            video_480 TEXT,
            video_720 TEXT NOT NULL,
            video_1080 TEXT,
            FOREIGN KEY (content_id) REFERENCES content(id)
        )
    ");

    echo "دیتابیس با موفقیت ریست شد و جداول اصلی ایجاد شدند";
    
} catch (PDOException $e) {
    die("خطا در ریست دیتابیس: " . $e->getMessage());
}
?>