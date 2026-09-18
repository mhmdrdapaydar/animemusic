<?php
session_start();
require_once __DIR__ . '/includes/admin_auth.php';
am_admin_guard();

$page_title = 'مدیریت فایل‌ها';

// مسیر ریشه پروژه
$root_path = realpath(__DIR__ . '/../');
$current_path = isset($_GET['path']) ? realpath($root_path . '/' . $_GET['path']) : $root_path;

// اطمینان از اینکه مسیر جاری در محدوده مجاز است
if ($current_path === false || strpos($current_path, $root_path) !== 0) {
    $current_path = $root_path;
}

// عملیات مدیریت فایل
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'delete':
            if (isset($_GET['file'])) {
                $file_path = realpath($current_path . '/' . $_GET['file']);
                if ($file_path && strpos($file_path, $root_path) === 0 && file_exists($file_path)) {
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
                    @mkdir($current_path . '/' . $folder_name);
                    header("Location: file_manager.php?path=" . urlencode(str_replace($root_path, '', $current_path)));
                    exit;
                }
            }
            break;

        case 'upload':
            if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $file_name = basename($_FILES['file']['name']);
                $file_path = $current_path . '/' . $file_name;
                if (move_uploaded_file($_FILES['file']['tmp_name'], $file_path)) {
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
                'size' => is_dir($file_path) ? '-' : format_size((int)@filesize($file_path)),
                'modified' => date('Y-m-d H:i:s', filemtime($file_path))
            ];
        }
    }
    usort($files, function ($a, $b) {
        if ($a['is_dir'] !== $b['is_dir']) return $a['is_dir'] ? -1 : 1;
        return strcmp($a['name'], $b['name']);
    });
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
$escaped_rel = ltrim($relative_path, '/');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title) ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/admin.css?v=4">
  <style>
    .crumbs { display:flex; align-items:center; flex-wrap:wrap; gap:4px; font-size:14px; margin-bottom:16px; }
    .crumbs a { color: var(--primary); text-decoration:none; }
    .crumbs a:hover { text-decoration:underline; }
    .file-icon.folder { color:#ffb020; }
    .file-icon.file { color:#8a97a5; }
    .modal-mask { display:none; position:fixed; inset:0; background:rgba(15,23,42,.55); z-index:1000; align-items:center; justify-content:center; padding:16px; }
    .modal-card { background:var(--surface); border-radius:var(--radius); width:100%; max-width:420px; padding:20px; box-shadow:var(--shadow); }
    .modal-card h3 { font-size:16px; margin-bottom:14px; }
    .modal-actions { display:flex; gap:8px; margin-top:14px; }
  </style>
</head>
<body>
<div class="container">
  <header class="admin-header">
    <div class="brand"><i class="fas fa-folder-open"></i><span>مدیریت فایل‌ها</span></div>
    <div class="header-actions">
      <a class="btn" href="admin.php"><i class="fas fa-arrow-right"></i> پنل اصلی</a>
      <a class="btn" href="logout.php"><i class="fas fa-sign-out-alt"></i> خروج</a>
    </div>
  </header>

  <div class="card" style="padding:20px;">
    <div class="crumbs">
      <a href="file_manager.php"><i class="fas fa-home"></i> ریشه</a>
      <?php
      $path_parts = explode('/', trim($escaped_rel, '/'));
      $current_path_link = '';
      foreach ($path_parts as $part) {
          if (!empty($part)) {
              $current_path_link .= '/' . $part;
              echo '<span>/</span> <a href="file_manager.php?path=' . urlencode($current_path_link) . '">' . htmlspecialchars($part) . '</a>';
          }
      }
      ?>
    </div>

    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:18px;">
      <button type="button" onclick="document.getElementById('createFolderModal').style.display='flex'" class="btn btn-primary"><i class="fas fa-folder-plus"></i> پوشه جدید</button>
      <button type="button" onclick="document.getElementById('uploadModal').style.display='flex'" class="btn btn-primary"><i class="fas fa-upload"></i> آپلود فایل</button>
    </div>

    <div class="table-wrap"><div class="scroll-x"><table class="data-table">
      <thead>
        <tr><th>نام</th><th>نوع</th><th>اندازه</th><th>تاریخ تغییر</th><th>عملیات</th></tr>
      </thead>
      <tbody>
        <?php if (!empty($files)): ?>
          <?php foreach ($files as $file): ?>
            <tr>
              <td style="white-space:nowrap;">
                <?php if ($file['is_dir']): ?>
                  <i class="fas fa-folder file-icon folder"></i>
                  <a href="file_manager.php?path=<?= urlencode($escaped_rel . '/' . $file['name']) ?>" style="font-weight:600; color:var(--dark);">
                    <?= htmlspecialchars($file['name']) ?>
                  </a>
                <?php else: ?>
                  <i class="fas fa-file file-icon file"></i>
                  <?= htmlspecialchars($file['name']) ?>
                <?php endif; ?>
              </td>
              <td><?= $file['is_dir'] ? 'پوشه' : 'فایل' ?></td>
              <td style="white-space:nowrap;"><?= $file['size'] ?></td>
              <td style="white-space:nowrap;"><?= $file['modified'] ?></td>
              <td style="white-space:nowrap;">
                <?php if (!$file['is_dir']): ?>
                  <a href="<?= htmlspecialchars(str_replace($root_path, '', $file['path'])) ?>" target="_blank" class="btn btn-sm btn-outline btn-secondary"><i class="fas fa-download"></i> دانلود</a>
                <?php endif; ?>
                <a href="file_manager.php?path=<?= urlencode($escaped_rel) ?>&action=delete&file=<?= urlencode($file['name']) ?>" onclick="return confirm('حذف «<?= htmlspecialchars($file['name']) ?>»؟')" class="btn btn-sm btn-outline btn-delete"><i class="fas fa-trash"></i> حذف</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="5" style="text-align:center; padding:30px; color:var(--gray);">پوشه خالی است</td></tr>
        <?php endif; ?>
      </tbody>
    </table></div></div>
  </div>

  <!-- مودال ایجاد پوشه -->
  <div id="createFolderModal" class="modal-mask">
    <div class="modal-card">
      <h3><i class="fas fa-folder-plus"></i> ایجاد پوشه جدید</h3>
      <form method="post" action="file_manager.php?path=<?= urlencode($escaped_rel) ?>&action=create_folder">
        <div class="form-group">
          <label>نام پوشه</label>
          <input type="text" name="folder_name" class="form-control" required pattern="[a-zA-Z0-9_-]+">
        </div>
        <div class="modal-actions">
          <button type="submit" class="btn btn-primary">ایجاد</button>
          <button type="button" class="btn btn-outline" onclick="document.getElementById('createFolderModal').style.display='none'">لغو</button>
        </div>
      </form>
    </div>
  </div>

  <!-- مودال آپلود فایل -->
  <div id="uploadModal" class="modal-mask">
    <div class="modal-card">
      <h3><i class="fas fa-upload"></i> آپلود فایل</h3>
      <form method="post" action="file_manager.php?path=<?= urlencode($escaped_rel) ?>&action=upload" enctype="multipart/form-data">
        <div class="form-group">
          <label>انتخاب فایل</label>
          <input type="file" name="file" class="form-control" required>
        </div>
        <div class="modal-actions">
          <button type="submit" class="btn btn-primary">آپلود</button>
          <button type="button" class="btn btn-outline" onclick="document.getElementById('uploadModal').style.display='none'">لغو</button>
        </div>
      </form>
    </div>
  </div>
</div>
</body>
</html>
