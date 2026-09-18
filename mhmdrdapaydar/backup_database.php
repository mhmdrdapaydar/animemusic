<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// تنظیمات هدر برای فایل دانلودی
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="anime_music_backup_' . date('Y-m-d') . '.sql"');

// ایجاد پشتیبان از دیتابیس‌ها
try {
    // اتصال به دیتابیس محتوا
    $db_content = new PDO('sqlite:' . __DIR__ . '/../db/content.db');
    $db_content->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // اتصال به دیتابیس کاربران
    $db_users = new PDO('sqlite:' . __DIR__ . '/../db/users.db');
    $db_users->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // شروع تولید پشتیبان
    echo "-- پشتیبان پایگاه داده انیمه موزیک\n";
    echo "-- تاریخ ایجاد: " . date('Y-m-d H:i:s') . "\n";
    echo "-- \n\n";
    
    // پشتیبان‌گیری از دیتابیس محتوا
    echo "-- دیتابیس محتوا (content.db)\n";
    echo "-- ========================================\n\n";
    
    // جداول دیتابیس محتوا
    $tables_content = ['anime_series', 'anime_contents', 'music_types', 'singers', 'content_singers', 'content_images'];
    
    foreach ($tables_content as $table) {
        echo "-- ساختار جدول $table\n";
        $stmt = $db_content->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='$table'");
        $create_table = $stmt->fetch(PDO::FETCH_ASSOC);
        echo $create_table['sql'] . ";\n\n";
        
        echo "-- داده‌های جدول $table\n";
        $stmt = $db_content->query("SELECT * FROM $table");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rows as $row) {
            $columns = implode(', ', array_keys($row));
            $values = implode(', ', array_map(function($value) use ($db_content) {
                if ($value === null) return 'NULL';
                return $db_content->quote($value);
            }, $row));
            
            echo "INSERT INTO $table ($columns) VALUES ($values);\n";
        }
        echo "\n";
    }
    
    // پشتیبان‌گیری از دیتابیس کاربران
    echo "-- دیتابیس کاربران (users.db)\n";
    echo "-- ========================================\n\n";
    
    // جداول دیتابیس کاربران
    $tables_users = ['users', 'playlists', 'playlist_contents', 'user_favorites', 'user_preferences'];
    
    foreach ($tables_users as $table) {
        echo "-- ساختار جدول $table\n";
        $stmt = $db_users->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='$table'");
        $create_table = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($create_table) {
            echo $create_table['sql'] . ";\n\n";
            
            echo "-- داده‌های جدول $table\n";
            $stmt = $db_users->query("SELECT * FROM $table");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($rows as $row) {
                $columns = implode(', ', array_keys($row));
                $values = implode(', ', array_map(function($value) use ($db_users) {
                    if ($value === null) return 'NULL';
                    return $db_users->quote($value);
                }, $row));
                
                echo "INSERT INTO $table ($columns) VALUES ($values);\n";
            }
            echo "\n";
        }
    }
    
    echo "-- پایان پشتیبان\n";
    
} catch (PDOException $e) {
    echo "-- خطا در ایجاد پشتیبان: " . $e->getMessage() . "\n";
}
?>