<?php
require_once __DIR__ . '/includes/headless.php';
// ذخیره اطلاعات در فایل JSON هنگام ثبت فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ad'])) {
    $ad_data = [
        'id' => uniqid(),
        'name' => $_POST['ad_name'],
        'url' => $_POST['ad_url'],
        'description' => $_POST['ad_description'],
        'aparat_url' => $_POST['aparat_url'],
        'status' => 'pending',
        'timestamp' => date('Y-m-d H:i:s')
    ];

    // خواندن فایل موجود (مسیر مطلق برای اطمینان در همه شرایط)
    $ads = [];
    $ads_file = __DIR__ . '/ads.json';
    
    if (file_exists($ads_file)) {
        $ads = json_decode(file_get_contents($ads_file), true);
    }

    // افزودن تبلیغ جدید
    $ads[] = $ad_data;

    // ذخیره اطلاعات
    file_put_contents($ads_file, json_encode($ads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // نمایش پیام موفقیت
    $success = true;
}
?>

<!DOCTYPE html>
<html lang="<?= am_e(am_lang()) ?>" dir="<?= am_e(am_lang_dir()) ?>">
<head>
  <?= am_lang_base_tag() ?>
  <meta charset="UTF-8" />
  <title><?= am_te('register_ad_title') ?> | <?= am_te('site_name') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --primary-color: #a8e12e;  /* سبز روشن */
      --secondary-color: #ffd700;  /* زرد طلایی */
      --accent-color: #ff6b00;    /* نارنجی */
      --bg-dark: #121212;
      --card-bg: #1e1e1e;
      --text-light: #f0f0f0;
    }
    
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    
    body {
      background: var(--bg-dark);
      color: var(--text-light);
      font-family: 'Vazirmatn', sans-serif;
      line-height: 1.6;
      min-height: 100vh;
      padding-bottom: 30px;
    }

    .header {
      background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
      padding: 12px 20px;
      text-align: center;
      box-shadow: 0 4px 15px rgba(0,0,0,0.3);
      position: sticky;
      top: 0;
      z-index: 1000;
    }
    
    .header img {
      height: 48px;
      max-width: 160px;
      object-fit: contain;
    }
    
    .telegram-note {
      background: rgba(0, 0, 0, 0.3);
      border: 1px solid var(--primary-color);
      border-radius: 8px;
      padding: 12px 15px;
      margin: 20px auto;
      max-width: 800px;
      text-align: center;
      font-size: 0.95rem;
      color: var(--text-light);
    }
    
    .telegram-note a {
      color: var(--secondary-color);
      text-decoration: none;
      font-weight: bold;
    }
    
    .telegram-note a:hover {
      text-decoration: underline;
    }
    
    .telegram-note i {
      margin-left: 8px;
      color: var(--primary-color);
    }
    
    .page-title {
      text-align: center;
      margin: 10px 0 15px;
      font-size: 1.8rem;
      color: var(--secondary-color);
      padding: 0 15px;
      text-shadow: 0 2px 4px rgba(0,0,0,0.5);
    }
    
    .container {
      max-width: 800px;
      margin: 0 auto;
      padding: 0 15px;
    }
    
    .steps-container {
      display: flex;
      justify-content: center;
      margin: 25px 0;
      gap: 15px;
    }
    
    .step {
      display: flex;
      flex-direction: column;
      align-items: center;
      position: relative;
      z-index: 1;
    }
    
    .step-number {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: var(--card-bg);
      border: 2px solid var(--primary-color);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      color: var(--text-light);
      margin-bottom: 8px;
    }
    
    .step.active .step-number {
      background: var(--primary-color);
      color: #000;
    }
    
    .step-label {
      font-size: 0.9rem;
      color: #aaa;
      text-align: center;
      max-width: 100px;
    }
    
    .step.active .step-label {
      color: var(--secondary-color);
      font-weight: bold;
    }
    
    .steps-line {
      position: absolute;
      top: 20px;
      left: 50px;
      right: 50px;
      height: 3px;
      background: #333;
      z-index: 0;
    }
    
    .progress-line {
      position: absolute;
      top: 20px;
      left: 0;
      height: 3px;
      background: var(--primary-color);
      transition: width 0.5s ease;
    }

    .card {
      background: var(--card-bg);
      border-radius: 12px;
      padding: 25px;
      margin-top: 20px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
      border-left: 4px solid var(--primary-color);
    }

    .card h3 {
      color: var(--primary-color);
      margin-bottom: 20px;
      font-size: 1.4rem;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .form-group {
      margin-bottom: 25px;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 500;
      color: #ddd;
    }
    
    .form-control {
      width: 100%;
      padding: 12px 15px;
      border-radius: 8px;
      border: 1px solid #444;
      background: #2a2a2a;
      color: var(--text-light);
      font-family: inherit;
      font-size: 1rem;
      transition: border-color 0.3s;
    }
    
    .form-control:focus {
      outline: none;
      border-color: var(--primary-color);
      box-shadow: 0 0 0 2px rgba(168, 225, 46, 0.3);
    }
    
    textarea.form-control {
      min-height: 120px;
      resize: vertical;
    }
    
    .aparat-guide {
      background: rgba(168, 225, 46, 0.1);
      border: 1px solid var(--primary-color);
      border-radius: 8px;
      padding: 15px;
      margin: 20px 0;
      text-align: center;
    }
    
    .aparat-guide h4 {
      color: var(--secondary-color);
      margin-bottom: 10px;
      font-size: 1.2rem;
    }
    
    .aparat-guide ol {
      text-align: right;
      padding-right: 20px;
      margin: 15px 0;
    }
    
    .aparat-guide li {
      margin-bottom: 10px;
      line-height: 1.6;
    }
    
    .aparat-guide a {
      color: var(--accent-color);
      text-decoration: none;
    }
    
    .aparat-guide a:hover {
      text-decoration: underline;
    }
    
    .requirements {
      background: rgba(255, 107, 0, 0.1);
      border-left: 3px solid var(--accent-color);
      padding: 12px 15px;
      border-radius: 5px;
      margin-top: 15px;
      font-size: 0.9rem;
    }
    
    .requirements ul {
      padding-right: 20px;
      margin-top: 8px;
    }
    
    .requirements li {
      margin-bottom: 5px;
    }
    
    .checkbox-group {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      margin: 25px 0;
    }
    
    .checkbox-group input {
      margin-top: 5px;
    }
    
    .checkbox-group label {
      color: #ddd;
      line-height: 1.6;
    }
    
    .checkbox-group a {
      color: var(--primary-color);
      text-decoration: none;
    }
    
    .checkbox-group a:hover {
      text-decoration: underline;
    }
    
    .button-group {
      display: flex;
      justify-content: center;
      gap: 15px;
      margin-top: 30px;
      flex-wrap: wrap;
    }
    
    .button {
      background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
      color: #000;
      border: none;
      padding: 12px 25px;
      border-radius: 30px;
      cursor: pointer;
      font-weight: bold;
      transition: all 0.3s ease;
      font-size: 1.05rem;
      box-shadow: 0 4px 8px rgba(0,0,0,0.2);
      display: inline-flex;
      align-items: center;
      gap: 10px;
      min-width: 140px;
      justify-content: center;
    }
    
    .button:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 12px rgba(0,0,0,0.3);
    }
    
    .button.secondary {
      background: transparent;
      color: var(--text-light);
      border: 2px solid var(--primary-color);
    }
    
    .button.secondary:hover {
      background: rgba(168, 225, 46, 0.1);
    }
    
    .preview-container {
      margin-top: 25px;
      text-align: center;
    }
    
    .preview-title {
      color: var(--secondary-color);
      margin-bottom: 15px;
      font-size: 1.3rem;
    }
    
    .preview-box {
      border: 2px dashed var(--primary-color);
      border-radius: 10px;
      padding: 20px;
      background: rgba(42, 42, 42, 0.3);
      min-height: 200px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
    }
    
    .preview-video {
      width: 100%;
      max-width: 500px;
      border-radius: 8px;
      overflow: hidden;
      position: relative;
      padding-bottom: 56.25%; /* 16:9 aspect ratio */
      height: 0;
    }
    
    .preview-video iframe {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      border: none;
      border-radius: 8px;
    }
    
    .preview-details {
      text-align: right;
      margin-top: 20px;
      width: 100%;
      max-width: 500px;
    }
    
    .preview-details h4 {
      color: var(--primary-color);
      margin-bottom: 10px;
      font-size: 1.2rem;
    }
    
    .preview-details p {
      color: #ddd;
      line-height: 1.7;
    }
    
    .preview-link {
      display: inline-block;
      margin-top: 15px;
      color: var(--accent-color);
      text-decoration: none;
    }
    
    .preview-link:hover {
      text-decoration: underline;
    }
    
    .success-message {
      text-align: center;
      padding: 40px 20px;
    }
    
    .success-icon {
      font-size: 4rem;
      color: var(--primary-color);
      margin-bottom: 25px;
      animation: pulse 1.5s infinite;
    }
    
    .success-message h2 {
      color: var(--secondary-color);
      margin-bottom: 20px;
      font-size: 2rem;
    }
    
    .success-message p {
      color: #ddd;
      font-size: 1.1rem;
      margin-bottom: 30px;
      max-width: 600px;
      margin-left: auto;
      margin-right: auto;
      line-height: 1.8;
    }
    
    @keyframes pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.1); }
      100% { transform: scale(1); }
    }
    
    .form-section {
      display: none;
    }
    
    .form-section.active {
      display: block;
      animation: fadeIn 0.5s ease;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    /* استایل‌های ریسپانسیو */
    @media (max-width: 768px) {
      .container {
        padding: 0 10px;
      }
      
      .page-title {
        font-size: 1.6rem;
      }
      
      .steps-container {
        gap: 5px;
      }
      
      .step-label {
        font-size: 0.8rem;
        max-width: 70px;
      }
      
      .card {
        padding: 20px;
      }
      
      .button {
        padding: 10px 20px;
        font-size: 1rem;
        min-width: 120px;
      }
    }
    
    @media (max-width: 480px) {
      .header {
        padding: 10px;
      }
      
      .page-title {
        font-size: 1.4rem;
        margin-top: 20px;
      }
      
      .step-number {
        width: 35px;
        height: 35px;
      }
      
      .step-label {
        font-size: 0.75rem;
      }
      
      .steps-line, .progress-line {
        top: 17px;
      }
      
      .button-group {
        flex-direction: column;
        gap: 12px;
      }
      
      .button {
        width: 100%;
      }
      
      .aparat-guide ol {
        padding-right: 15px;
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
    <img src="/image.png" alt="لوگو">
  </div>

  <div class="telegram-note">
    <i class="fab fa-telegram-plane"></i>
    <?= am_te('register_ad_intro') ?>
    <a href="https://t.me/I_MHP_I" target="_blank">@I_MHP_I</a>
  </div>

  <h1 class="page-title"><?= am_te('register_ad_title') ?></h1>
  
  <div class="container">
    <!-- مراحل ثبت تبلیغ -->
    <div class="steps-container">
      <div class="step active" data-step="1">
        <div class="step-number">1</div>
        <div class="step-label"><?= am_te('ad_info') ?></div>
      </div>
      <div class="step" data-step="2">
        <div class="step-number">2</div>
        <div class="step-label"><?= am_te('aparat_link') ?></div>
      </div>
      <div class="step" data-step="3">
        <div class="step-number">3</div>
        <div class="step-label"><?= am_te('final_confirm') ?></div>
      </div>
      <div class="steps-line">
        <div class="progress-line" id="progress-line" style="width: 0%"></div>
      </div>
    </div>
    
    <?php if (isset($success) && $success): ?>
      <!-- پیام موفقیت‌آمیز -->
      <div class="form-section active" id="success-message">
        <div class="success-message">
          <div class="success-icon">
            <i class="fas fa-check-circle"></i>
          </div>
          <h2><?= am_te('ad_submitted') ?></h2>
          <p>
            <?= am_te('ad_under_review') ?>
          </p>
          <button class="button" id="go-to-home">
            <?= am_te('back_to_home_i') ?> <i class="fas fa-home"></i>
          </button>
        </div>
      </div>
    <?php else: ?>
      <!-- فرم مرحله ۱: اطلاعات تبلیغ -->
      <div class="form-section active" id="step-1">
        <div class="card">
          <h3><i class="fas fa-info-circle"></i> <?= am_te('ad_main_info') ?></h3>
          
          <div class="form-group">
            <label for="ad-name"><?= am_te('ad_name') ?>:</label>
            <input type="text" id="ad-name" class="form-control" placeholder="نام تبلیغ خود را وارد کنید">
          </div>
          
          <div class="form-group">
            <label for="ad-url"><?= am_te('ad_dest_link') ?>:</label>
            <input type="url" id="ad-url" class="form-control" placeholder="https://example.com">
          </div>
          
          <div class="form-group">
            <label for="ad-description"><?= am_te('ad_description') ?>:</label>
            <textarea id="ad-description" class="form-control" placeholder="توضیحات کامل درباره تبلیغ..."></textarea>
          </div>
          
          <div class="requirements">
            <p><strong><?= am_te('notes') ?>:</strong></p>
            <ul>
              <li><?= am_te('note_1') ?></li>
              <li><?= am_te('note_2') ?></li>
              <li><?= am_te('note_3') ?></li>
              <li><?= am_te('note_4') ?></li>
            </ul>
          </div>
        </div>
        
        <div class="button-group">
          <button class="button" id="next-to-step2">
            <?= am_te('next_step') ?> <i class="fas fa-arrow-left"></i>
          </button>
        </div>
      </div>
      
      <!-- فرم مرحله ۲: لینک آپارات -->
      <div class="form-section" id="step-2">
        <div class="card">
          <h3><i class="fas fa-video"></i> <?= am_te('aparat_video_link') ?></h3>
          
          <div class="form-group">
            <label for="aparat-url"><?= am_te('aparat_video_link_label') ?>:</label>
            <input type="url" id="aparat-url" class="form-control" placeholder="https://www.aparat.com/v/...">
          </div>
          
          <div class="requirements">
            <p><strong><?= am_te('notes') ?>:</strong></p>
            <ul>
              <li><?= am_te('aparat_note_1') ?></li>
              <li><?= am_te('aparat_note_2') ?></li>
              <li><?= am_te('aparat_note_3') ?></li>
            </ul>
          </div>
        </div>
        
        <div class="aparat-guide">
          <h4><i class="fas fa-question-circle"></i> <?= am_te('how_upload_aparat') ?></h4>
          <ol>
            <li><?= am_te('guide_1') ?></li>
            <li><?= am_te('guide_2') ?></li>
            <li><?= am_te('guide_3') ?></li>
            <li><?= am_te('guide_4') ?></li>
            <li><?= am_te('guide_5') ?></li>
            <li><?= am_te('guide_6') ?></li>
            <li><?= am_te('guide_7') ?></li>
          </ol>
        </div>
        
        <div class="preview-container" id="aparat-preview" style="display: none;">
          <h3 class="preview-title"><?= am_te('video_preview') ?></h3>
          <div class="preview-box">
            <div class="preview-video" id="aparat-preview-frame">
              <!-- آپارات embed در اینجا نمایش داده می‌شود -->
            </div>
          </div>
        </div>
        
        <div class="button-group">
          <button class="button secondary" id="back-to-step1">
            <i class="fas fa-arrow-right"></i> <?= am_te('prev_step') ?>
          </button>
          <button class="button" id="next-to-step3">
            <?= am_te('next_step') ?> <i class="fas fa-arrow-left"></i>
          </button>
        </div>
      </div>
      
      <!-- فرم مرحله ۳: تایید نهایی -->
      <div class="form-section" id="step-3">
        <form method="POST" action="<?= am_lang_url('ads.php') ?>">  <!-- اصلاح action به ads.php -->
          <div class="card">
            <h3><i class="fas fa-check-circle"></i> <?= am_te('confirm_info') ?></h3>
            
            <div class="preview-container">
              <h3 class="preview-title"><?= am_te('ad_preview') ?></h3>
              <div class="preview-box">
                <div class="preview-video" id="final-aparat-preview">
                  <!-- آپارات embed در اینجا نمایش داده می‌شود -->
                </div>
                <div class="preview-details">
                  <h4 id="preview-ad-name"><?= am_te('ad_name') ?></h4>
                  <p id="preview-ad-description"><?= am_te('ad_description') ?></p>
                  <a href="#" id="preview-ad-url" class="preview-link" target="_blank"><?= am_te('view_dest_link') ?></a>
                </div>
              </div>
            </div>
            
            <div class="checkbox-group">
              <input type="checkbox" id="terms-agree" name="terms_agree" required>
              <label for="terms-agree">
                <?= am_te('terms_agree') ?>
              </label>
            </div>
          </div>
          
          <div class="button-group">
            <button type="button" class="button secondary" id="back-to-step2">
              <i class="fas fa-arrow-right"></i> <?= am_te('prev_step') ?>
            </button>
            <button type="submit" class="button" id="submit-ad" name="submit_ad">
              <?= am_te('final_submit_ad') ?> <i class="fas fa-paper-plane"></i>
            </button>
          </div>
          
          <!-- فیلدهای مخفی برای ارسال داده‌ها -->
          <input type="hidden" id="hidden-ad-name" name="ad_name">
          <input type="hidden" id="hidden-ad-url" name="ad_url">
          <input type="hidden" id="hidden-ad-description" name="ad_description">
          <input type="hidden" id="hidden-aparat-url" name="aparat_url">
        </form>
      </div>
    <?php endif; ?>
  </div>
  
  <script>
    // مدیریت مراحل فرم
    const steps = document.querySelectorAll('.step');
    const formSections = document.querySelectorAll('.form-section');
    const progressLine = document.getElementById('progress-line');
    let currentStep = 1;
    
    // تابع برای تغییر مرحله
    function goToStep(step) {
      // مخفی کردن تمام بخش‌ها
      formSections.forEach(section => {
        section.classList.remove('active');
      });
      
      // نمایش بخش فعلی
      document.getElementById(`step-${step}`).classList.add('active');
      
      // به‌روزرسانی مراحل
      steps.forEach(s => {
        const stepNum = parseInt(s.dataset.step);
        s.classList.toggle('active', stepNum === step);
      });
      
      // به‌روزرسانی خط پیشرفت
      const progress = ((step - 1) / (steps.length - 1)) * 100;
      progressLine.style.width = `${progress}%`;
      
      currentStep = step;
    }
    
    // دکمه‌های ناوبری
    document.getElementById('next-to-step2')?.addEventListener('click', () => {
      const adName = document.getElementById('ad-name').value.trim();
      const adUrl = document.getElementById('ad-url').value.trim();
      const adDesc = document.getElementById('ad-description').value.trim();
      
      // اعتبارسنجی اولیه
      if (!adName) {
        alert('لطفاً نام تبلیغ را وارد کنید.');
        return;
      }
      
      if (!adUrl || !adUrl.startsWith('http')) {
        alert('لطفاً لینک معتبر وارد کنید.');
        return;
      }
      
      if (adDesc.length < 30) {
        alert('توضیحات باید حداقل ۳۰ کاراکتر باشد.');
        return;
      }
      
      goToStep(2);
    });
    
    document.getElementById('back-to-step1')?.addEventListener('click', () => goToStep(1));
    
    document.getElementById('next-to-step3')?.addEventListener('click', () => {
      const aparatUrl = document.getElementById('aparat-url').value.trim();
      
      if (!aparatUrl) {
        alert('لطفاً لینک ویدیو آپارات را وارد کنید.');
        return;
      }
      
      if (!aparatUrl.includes('aparat.com/v/')) {
        alert('لطفاً لینک معتبر آپارات وارد کنید. (فرمت صحیح: https://www.aparat.com/v/XXXXX)');
        return;
      }
      
      goToStep(3);
      updatePreview();
    });
    
    document.getElementById('back-to-step2')?.addEventListener('click', () => goToStep(2));
    
    // مدیریت لینک آپارات
    const aparatUrlInput = document.getElementById('aparat-url');
    const aparatPreview = document.getElementById('aparat-preview');
    const aparatPreviewFrame = document.getElementById('aparat-preview-frame');
    
    if (aparatUrlInput) {
      aparatUrlInput.addEventListener('input', function() {
        const aparatUrl = this.value.trim();
        
        if (aparatUrl.includes('aparat.com/v/')) {
          // استخراج شناسه ویدیو از لینک آپارات
          const videoId = aparatUrl.split('/v/')[1].split('/')[0].split('?')[0];
          
          if (videoId) {
            // ایجاد کد embed برای نمایش ویدیو
            aparatPreviewFrame.innerHTML = `
              <iframe 
                src="https://www.aparat.com/video/video/embed/videohash/${videoId}/vt/frame" 
                allowfullscreen
                webkitallowfullscreen
                mozallowfullscreen
              ></iframe>
            `;
            aparatPreview.style.display = 'block';
          }
        } else {
          aparatPreview.style.display = 'none';
        }
      });
    }
    
    // به‌روزرسانی پیش‌نمایش نهایی
    function updatePreview() {
      // به‌روزرسانی اطلاعات تبلیغ
      document.getElementById('preview-ad-name').textContent = document.getElementById('ad-name').value;
      document.getElementById('preview-ad-description').textContent = document.getElementById('ad-description').value;
      
      const adUrl = document.getElementById('ad-url').value;
      const linkElement = document.getElementById('preview-ad-url');
      linkElement.href = adUrl;
      linkElement.textContent = adUrl;
      
      // به‌روزرسانی ویدیو آپارات
      const aparatUrl = document.getElementById('aparat-url').value.trim();
      const finalAparatPreview = document.getElementById('final-aparat-preview');
      
      if (aparatUrl.includes('aparat.com/v/')) {
        const videoId = aparatUrl.split('/v/')[1].split('/')[0].split('?')[0];
        
        if (videoId) {
          finalAparatPreview.innerHTML = `
            <iframe 
              src="https://www.aparat.com/video/video/embed/videohash/${videoId}/vt/frame" 
              allowfullscreen
              webkitallowfullscreen
              mozallowfullscreen
            ></iframe>
          `;
        }
      }
      
      // ذخیره مقادیر در فیلدهای مخفی
      document.getElementById('hidden-ad-name').value = document.getElementById('ad-name').value;
      document.getElementById('hidden-ad-url').value = document.getElementById('ad-url').value;
      document.getElementById('hidden-ad-description').value = document.getElementById('ad-description').value;
      document.getElementById('hidden-aparat-url').value = document.getElementById('aparat-url').value;
    }
    
    // بازگشت به صفحه اصلی
    document.getElementById('go-to-home')?.addEventListener('click', () => {
      window.location.href = <?= json_encode(am_lang_url('index.php')) ?>;
    });
  </script>
</body>
</html>