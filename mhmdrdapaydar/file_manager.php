<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// مسیر ریشه پروژه
$root_path = realpath(__DIR__ . '/../');
$current_path = isset($_GET['path']) ? realpath($root_path . '/' . $_GET['path']) : $root_path;

// اطمینان از اینکه مسیر جاری در محدوده مجاز است
if (strpos($current_path, $root_path) !== 0) {
    $current_path = $root_path;
}

// عملیات مدیریت فایل
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'delete':
            if (isset($_GET['file'])) {
                $file_path = realpath($current_path . '/' . $_GET['file']);
                if (strpos($file_path, $root_path) === 0 && file_exists($file_path)) {
                    if (is_dir($file_path)) {
                        rmdir($file_path);
                    } else {
                        unlink($file_path);
                    }
                    header("Location: file_manager.php?path=" . urlencode(str_replace($root_path, '', $current_path)));
                    exit;
                }
            }
            break;
            
        case 'create_folder':
            if (isset($_POST['folder_name'])) {
                $folder_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['folder_name']);
                if (!empty($folder_name)) {
                    mkdir($current_path . '/' . $folder_name);
                    header("Location: file_manager.php?path=" . urlencode(str_replace($root_path, '', $current_path)));
                    exit;
                }
            }
            break;
            
        case 'upload':
            if (isset($_FILES['file'])) {
                $file_name = $_FILES['file']['name'];
                $file_tmp = $_FILES['file']['tmp_name'];
                $file_path = $current_path . '/' . $file_name;
                
                if (move_uploaded_file($file_tmp, $file_path)) {
                    header("Location: file_manager.php?path=" . urlencode(str_replace($root_path, '', $current_path)));
                    exit;
                }
            }
            break;
    }
}

// خواندن محتوای دایرکتوری
$files = [];
if (is_dir($current_path)) {
    $items = scandir($current_path);
    foreach ($items as $item) {
        if ($item != '.' && $item != '..') {
            $file_path = $current_path . '/' . $item;
            $files[] = [
                'name' => $item,
                'path' => $file_path,
                'is_dir' => is_dir($file_path),
                'size' => is_dir($file_path) ? '-' : format_size(filesize($file_path)),
                'modified' => date('Y-m-d H:i:s', filemtime($file_path))
            ];
        }
    }
}

// تابع فرمت‌بندی اندازه فایل
function format_size($size) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    return round($size, 2) . ' ' . $units[$i];
}

// مسیر نسبی برای نمایش
$relative_path = str_replace($root_path, '', $current_path);
if (empty($relative_path)) {
    $relative_path = '/';
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>مدیریت فایل‌ها</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn.min.css" rel="stylesheet" />
  <style>
    body {
      font-family: 'Vazirmatn', sans-serif;
      background: #f8f9fa;
      color: #212529;
      direction: rtl;
      padding: 20px;
    }
    
    .container {
      max-width: 1200px;
      margin: 0 auto;
      background: white;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }
    
    h1 {
      color: #00aa6f;
      margin-bottom: 20px;
    }
    
    .breadcrumb {
      margin-bottom: 20px;
      padding: 10px;
      background: #e9ecef;
      border-radius: 5px;
    }
    
    .breadcrumb a {
      color: #00aa6f;
      text-decoration: none;
    }
    
    .file-list {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
    }
    
    .file-list th, .file-list td {
      padding: 12px;
      text-align: right;
      border-bottom: 1px solid #dee2e6;
    }
    
    .file-list th {
      background: #00aa6f;
      color: white;
    }
    
    .file-list tr:hover {
      background: #f8f9fa;
    }
    
    .actions {
      display: flex;
      gap: 10px;
      margin-bottom: 20px;
    }
    
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 10px 15px;
      border-radius: 5px;
      text-decoration: none;
      color: white;
      background: #00aa6f;
      border: none;
      cursor: pointer;
      font-family: inherit;
    }
    
    .btn-danger {
      background: #dc3545;
    }
    
    .btn i {
      font-size: 14px;
    }
    
    .upload-form {
      margin-top: 20px;
      padding: 20px;
      background: #e9ecef;
      border-radius: 5px;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1><i class="fas fa-folder-open"></i> مدیریت فایل‌ها</h1>
    
    <div class="breadcrumb">
      <a href="file_manager.php">صفحه اصلی</a>
      <?php
      $path_parts = explode('/', trim($relative_path, '/'));
      $current_path_link = '';
      foreach ($path_parts as $part) {
          if (!empty($part)) {
              $current_path_link .= '/' . $part;
              echo ' / <a href="file_manager.php?path=' . urlencode($current_path_link) . '">' . $part . '</a>';
          }
      }
      ?>
    </div>
    
    <div class="actions">
      <button type="button" onclick="document.getElementById('createFolderModal').style.display='block'" class="btn">
        <i class="fas fa-folder-plus"></i> ایجاد پوشه جدید
      </button>
      
      <button type="button" onclick="document.getElementById('uploadModal').style.display='block'" class="btn">
        <i class="fas fa-upload"></i> آپلود فایل
      </button>
      
      <a href="admin.php" class="btn">
        <i class="fas fa-arrow-left"></i> بازگشت به پنل
      </a>
    </div>
    
    <table class="file-list">
      <thead>
        <tr>
          <th>نام</th>
          <th>نوع</th>
          <th>اندازه</th>
          <th>تاریخ تغییر</th>
          <th>عملیات</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($files)): ?>
          <?php foreach ($files as $file): ?>
            <tr>
              <td>
                <?php if ($file['is_dir']): ?>
                  <i class="fas fa-folder" style="color: #ffc107;"></i>
                  <a href="file_manager.php?path=<?= urlencode($relative_path . '/' . $file['name']) ?>">
                    <?= htmlspecialchars($file['name']) ?>
                  </a>
                <?php else: ?>
                  <i class="fas fa-file" style="color: #6c757d;"></i>
                  <?= htmlspecialchars($file['name']) ?>
                <?php endif; ?>
              </td>
              <td><?= $file['is_dir'] ? 'پوشه' : 'فایل' ?></td>
              <td><?= $file['size'] ?></td>
              <td><?= $file['modified'] ?></td>
              <td>
                <?php if (!$file['is_dir']): ?>
                  <a href="<?= str_replace($root_path, '', $file['path']) ?>" target="_blank" class="btn">
                    <i class="fas fa-download"></i> دانلود
                  </a>
                <?php endif; ?>
                <a href="file_manager.php?path=<?= urlencode($relative_path) ?>&action=delete&file=<?= urlencode($file['name']) ?>" 
                   onclick="return confirm('آیا مطمئن هستید؟')" class="btn btn-danger">
                  <i class="fas fa-trash"></i> حذف
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="5" style="text-align: center;">پوشه خالی است</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
    
    <!-- مودال ایجاد پوشه -->
    <div id="createFolderModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000;">
      <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 10px; width: 400px;">
        <h2>ایجاد پوشه جدید</h2>
        <form method="post" action="file_manager.php?path=<?= urlencode($relative_path) ?>&action=create_folder">
          <div style="margin-bottom: 15px;">
            <label>نام پوشه:</label>
            <input type="text" name="folder_name" required style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 5px;">
          </div>
          <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn">ایجاد</button>
            <button type="button" onclick="document.getElementById('createFolderModal').style.display='none'" class="btn btn-danger">لغو</button>
          </div>
        </form>
      </div>
    </div>
    
    <!-- مودال آپلود فایل -->
    <div id="uploadModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000;">
      <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 10px; width: 400px;">
        <h2>آپلود فایل</h2>
        <form method="post" action="file_manager.php?path=<?= urlencode($relative_path) ?>&action=upload" enctype="multipart/form-data">
          <div style="margin-bottom: 15px;">
            <label>انتخاب فایل:</label>
            <input type="file" name="file" required style="width: 100%; padding: 8px;">
          </div>
          <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn">آپلود</button>
            <button type="button" onclick="document.getElementById('uploadModal').style.display='none'" class="btn btn-danger">لغو</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</body>
</html>