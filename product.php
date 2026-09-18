<?php
require_once 'includes/init.php';
require_once 'includes/auth.php';

// دیباگ برای بررسی
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    error_log("========== PRODUCT PAGE LOADED ==========");
    error_log("GET params: " . print_r($_GET, true));
    error_log("Session ID: " . session_id());
    error_log("Logged in: " . ($auth->isLoggedIn() ? 'Yes' : 'No'));
    error_log("User ID: " . ($_SESSION['user_id'] ?? 'Not set'));
}

// دریافت ID محصول
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($productId <= 0) {
    header('Location: index.php');
    exit;
}

// افزایش تعداد بازدید
$db->execute("UPDATE products SET view_count = view_count + 1 WHERE id = ?", [$productId]);

// دریافت اطلاعات محصول
$product = $db->fetch("
    SELECT p.*, c.name as category_name, c.slug as category_slug
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id = ? AND p.is_active = 1
", [$productId]);

if (!$product) {
    header('Location: index.php?error=product_not_found');
    exit;
}

// دریافت تصاویر محصول
$productImages = $db->fetchAll("
    SELECT * FROM product_images 
    WHERE product_id = ? 
    ORDER BY is_main DESC, display_order ASC
", [$productId]);

// دریافت تنوع‌های محصول
$productVariants = $db->fetchAll("
    SELECT pv.*, 
           GROUP_CONCAT(CONCAT(a.display_name, ': ', ao.label) SEPARATOR '، ') as variant_attributes
    FROM product_variants pv
    LEFT JOIN variant_options vo ON pv.id = vo.variant_id
    LEFT JOIN attribute_options ao ON vo.option_id = ao.id
    LEFT JOIN attributes a ON ao.attribute_id = a.id
    WHERE pv.product_id = ? AND pv.is_active = 1
    GROUP BY pv.id
    ORDER BY pv.is_default DESC, pv.created_at ASC
", [$productId]);

// دریافت نظرات محصول
$reviews = $db->fetchAll("
    SELECT pr.*, u.full_name, u.avatar_url
    FROM product_ratings pr
    LEFT JOIN users u ON pr.user_id = u.id
    WHERE pr.product_id = ? AND pr.is_approved = 1
    ORDER BY pr.created_at DESC
    LIMIT 10
", [$productId]);

// محاسبه میانگین امتیاز
$averageRating = $product['rating_avg'];
$ratingCount = $product['rating_count'];

// دریافت محصولات مرتبط (همین دسته‌بندی)
$relatedProducts = $db->fetchAll("
    SELECT p.*, pi.image_url as main_image
    FROM products p
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_main = 1
    WHERE p.category_id = ? AND p.id != ? AND p.is_active = 1
    ORDER BY RAND()
    LIMIT 4
", [$product['category_id'], $productId]);

// تنظیم عنوان صفحه
$pageTitle = $product['name'] . " | فروشگاه آنلاین";

// تولید توکن CSRF
$csrf_token = Functions::generateCsrfToken();

if (defined('DEBUG_MODE') && DEBUG_MODE) {
    error_log("Generated CSRF Token: $csrf_token");
}

// ------------------------------------------------------------------
// مدیریت افزودن به سبد خرید (برای زمانی که JavaScript غیرفعال است)
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart']) && !Functions::isAjaxRequest()) {
    if (!$auth->isLoggedIn()) {
        $_SESSION['flash_message'] = [
            'text' => 'لطفاً ابتدا وارد حساب کاربری خود شوید',
            'type' => 'warning'
        ];
        header('Location: login.php?redirect=' . urlencode('product.php?id=' . $productId));
        exit;
    }
    
    // بررسی CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!Functions::verifyCsrfToken($token)) {
        $_SESSION['flash_message'] = [
            'text' => 'توکن امنیتی نامعتبر است',
            'type' => 'error'
        ];
        header('Location: product.php?id=' . $productId);
        exit;
    }
    
    $variantId = isset($_POST['variant_id']) && $_POST['variant_id'] !== '' ? (int)$_POST['variant_id'] : null;
    $quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;
    
    // بررسی موجودی
    $availableStock = 0;
    if ($variantId) {
        $variant = $db->fetch("SELECT stock FROM product_variants WHERE id = ? AND product_id = ?", 
                             [$variantId, $productId]);
        $availableStock = $variant ? $variant['stock'] : 0;
    } else {
        $availableStock = $product['stock'];
    }
    
    if ($availableStock < $quantity) {
        $_SESSION['flash_message'] = [
            'text' => 'موجودی محصول کافی نیست',
            'type' => 'error'
        ];
    } else {
        $sessionId = session_id();
        $userId = $_SESSION['user_id'];
        
        // بررسی وجود محصول در سبد (بدون expires_at)
        $existing = $db->fetch("
            SELECT id, quantity 
            FROM carts 
            WHERE user_id = ? AND product_id = ? 
            AND (product_variant_id = ? OR (? IS NULL AND product_variant_id IS NULL))
            AND session_id = ?
        ", [$userId, $productId, $variantId, $variantId, $sessionId]);
        
        if ($existing) {
            // افزایش تعداد
            $newQuantity = $existing['quantity'] + $quantity;
            $db->execute("UPDATE carts SET quantity = ? WHERE id = ?", [$newQuantity, $existing['id']]);
        } else {
            // اضافه کردن جدید (بدون expires_at)
            $db->execute("
                INSERT INTO carts (user_id, session_id, product_id, product_variant_id, quantity) 
                VALUES (?, ?, ?, ?, ?)
            ", [$userId, $sessionId, $productId, $variantId, $quantity]);
        }
        
        // کاهش موجودی
        if ($variantId) {
            $db->execute("UPDATE product_variants SET stock = stock - ? WHERE id = ?", 
                        [$quantity, $variantId]);
        } else {
            $db->execute("UPDATE products SET stock = stock - ? WHERE id = ?", 
                        [$quantity, $productId]);
        }
        
        $_SESSION['flash_message'] = [
            'text' => 'محصول به سبد خرید اضافه شد',
            'type' => 'success'
        ];
    }
    
    header('Location: product.php?id=' . $productId);
    exit;
}

// مدیریت خرید آنی (برای زمانی که JavaScript غیرفعال است)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_now']) && !Functions::isAjaxRequest()) {
    // همان منطق افزودن به سبد خرید اجرا می‌شود
    if (isset($_POST['redirect_to_checkout']) && $_POST['redirect_to_checkout'] == '1') {
        header('Location: cart.php?checkout=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Functions::escape($pageTitle); ?></title>
    
    <!-- استایل‌های Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css">
    
    <style>
        .product-gallery { position: relative; }
        .main-product-image { width: 100%; height: 400px; object-fit: contain; background: #f8f9fa; border-radius: 10px; cursor: zoom-in; }
        .thumbnail-images { display: flex; gap: 10px; margin-top: 15px; overflow-x: auto; padding: 10px 0; }
        .thumbnail-img { width: 80px; height: 80px; object-fit: cover; border-radius: 5px; cursor: pointer; border: 2px solid transparent; transition: all 0.3s; }
        .thumbnail-img:hover, .thumbnail-img.active { border-color: #0d6efd; transform: scale(1.05); }
        .product-info { position: sticky; top: 20px; }
        .price-box { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 10px; padding: 20px; margin: 20px 0; }
        .discount-badge { font-size: 1rem; padding: 5px 15px; }
        .rating-stars { color: #ffc107; font-size: 1.2rem; }
        .rating-count { color: #6c757d; font-size: 0.9rem; }
        .stock-badge { font-size: 0.9rem; padding: 5px 15px; }
        .quantity-selector { display: flex; align-items: center; gap: 10px; margin: 20px 0; }
        .quantity-btn { width: 40px; height: 40px; border: 1px solid #dee2e6; background: white; border-radius: 5px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1.2rem; }
        .quantity-input { width: 60px; text-align: center; border: 1px solid #dee2e6; border-radius: 5px; padding: 8px; }
        .variant-selector { margin: 20px 0; }
        .variant-option { display: inline-block; margin: 5px; padding: 8px 15px; border: 2px solid #dee2e6; border-radius: 5px; cursor: pointer; transition: all 0.3s; }
        .variant-option:hover, .variant-option.selected { border-color: #0d6efd; background: #f0f7ff; }
        .product-tabs { margin-top: 40px; }
        .nav-tabs .nav-link { border: none; color: #6c757d; font-weight: 500; padding: 12px 25px; }
        .nav-tabs .nav-link.active { color: #0d6efd; border-bottom: 3px solid #0d6efd; background: transparent; }
        .tab-content { padding: 30px; border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 10px 10px; }
        .review-card { border: 1px solid #dee2e6; border-radius: 10px; padding: 20px; margin-bottom: 15px; }
        .review-header { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .reviewer-info { display: flex; align-items: center; gap: 10px; }
        .reviewer-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .related-product-card { border: 1px solid #dee2e6; border-radius: 10px; overflow: hidden; transition: all 0.3s; }
        .related-product-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .breadcrumb { background: transparent; padding: 0; margin-bottom: 20px; }
        .breadcrumb-item a { color: #6c757d; text-decoration: none; }
        .breadcrumb-item a:hover { color: #0d6efd; }
        .breadcrumb-item.active { color: #0d6efd; }
        .share-buttons { display: flex; gap: 10px; margin-top: 20px; }
        .share-btn { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none; transition: all 0.3s; }
        .share-btn:hover { transform: translateY(-3px); }
        .share-telegram { background: #0088cc; }
        .share-whatsapp { background: #25D366; }
        .share-twitter { background: #1DA1F2; }
        .action-buttons { display: flex; gap: 15px; margin-top: 20px; }
        .btn-add-to-cart { flex: 1; padding: 12px; font-size: 1.1rem; }
        .btn-buy-now { flex: 1; padding: 12px; font-size: 1.1rem; }
        .product-meta { display: flex; gap: 20px; margin: 15px 0; color: #6c757d; font-size: 0.9rem; }
        .product-meta i { margin-left: 5px; }
        .flash-message-container { position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px; }
        @media (max-width: 768px) {
            .main-product-image { height: 300px; }
            .action-buttons { flex-direction: column; }
            .product-info { position: static; }
            .flash-message-container { top: 10px; right: 10px; left: 10px; min-width: auto; }
        }
    </style>
</head>
<body>
    <!-- هدر -->
    <header class="header shadow-sm">
        <nav class="navbar navbar-expand-lg navbar-light bg-white">
            <div class="container">
                <a class="navbar-brand" href="index.php">
                    <img src="assets/images/logo.png" alt="لوگو" height="40" class="d-inline-block align-text-top">
                    <span class="brand-name">فروشگاه آنلاین</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarMain">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-home"></i> خانه</a></li>
                        <li class="nav-item"><a class="nav-link" href="products.php"><i class="fas fa-box"></i> محصولات</a></li>
                        <li class="nav-item"><a class="nav-link" href="lottery.php"><i class="fas fa-gift"></i> قرعه‌کشی</a></li>
                        <?php if ($auth->isLoggedIn()): ?>
                        <li class="nav-item"><a class="nav-link" href="orders.php"><i class="fas fa-shopping-bag"></i> سفارشات من</a></li>
                        <?php endif; ?>
                    </ul>
                    <form class="d-flex me-3" action="products.php" method="GET">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="جستجوی محصولات...">
                            <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
                        </div>
                    </form>
                    <div class="d-flex align-items-center">
                        <a href="cart.php" class="position-relative me-3 text-decoration-none">
                            <i class="fas fa-shopping-cart fs-5 text-primary"></i>
                            <?php
                            $cartCount = 0;
                            if ($auth->isLoggedIn()) {
                                $cartCount = $db->fetchColumn("
                                    SELECT SUM(quantity) 
                                    FROM carts 
                                    WHERE user_id = ? AND session_id = ?
                                ", [$_SESSION['user_id'], session_id()]);
                            }
                            $cartCount = $cartCount ?: 0;
                            ?>
                            <?php if ($cartCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger cart-count">
                                <?php echo $cartCount; ?>
                            </span>
                            <?php endif; ?>
                        </a>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user"></i>
                                <?php if ($auth->isLoggedIn()): ?>
                                <span class="d-none d-md-inline"><?php echo Functions::escape($_SESSION['user_name'] ?: $_SESSION['user_mobile']); ?></span>
                                <?php else: ?>
                                <span class="d-none d-md-inline">حساب کاربری</span>
                                <?php endif; ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php if ($auth->isLoggedIn()): ?>
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle"></i> پروفایل</a></li>
                                <li><a class="dropdown-item" href="orders.php"><i class="fas fa-shopping-bag"></i> سفارشات من</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <?php if ($auth->isAdmin()): ?>
                                <li><a class="dropdown-item text-success" href="admin/"><i class="fas fa-cog"></i> پنل مدیریت</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> خروج</a></li>
                                <?php else: ?>
                                <li><a class="dropdown-item" href="login.php"><i class="fas fa-sign-in-alt"></i> ورود</a></li>
                                <li><a class="dropdown-item" href="register.php"><i class="fas fa-user-plus"></i> ثبت‌نام</a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <!-- پیام فلش -->
    <div class="container mt-3">
        <?php echo Functions::displayFlashMessage(); ?>
    </div>

    <!-- مسیر ناوبری -->
    <div class="container mt-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php"><i class="fas fa-home"></i> خانه</a></li>
                <li class="breadcrumb-item"><a href="products.php">محصولات</a></li>
                <?php if ($product['category_name']): ?>
                <li class="breadcrumb-item"><a href="products.php?category=<?php echo $product['category_id']; ?>">
                    <?php echo Functions::escape($product['category_name']); ?>
                </a></li>
                <?php endif; ?>
                <li class="breadcrumb-item active" aria-current="page"><?php echo Functions::escape($product['name']); ?></li>
            </ol>
        </nav>
    </div>

    <!-- محتوای اصلی محصول -->
    <div class="container py-5">
        <div class="row">
            <!-- گالری تصاویر -->
            <div class="col-lg-6 mb-4">
                <div class="product-gallery">
                    <a href="<?php echo !empty($productImages) ? $productImages[0]['image_url'] : 'assets/images/no-image.jpg'; ?>" 
                       data-lightbox="product-images" 
                       data-title="<?php echo Functions::escape($product['name']); ?>">
                        <img src="<?php echo !empty($productImages) ? $productImages[0]['image_url'] : 'assets/images/no-image.jpg'; ?>" 
                             alt="<?php echo Functions::escape($product['name']); ?>" 
                             class="main-product-image" 
                             id="mainProductImage">
                    </a>
                    <?php if (!empty($productImages)): ?>
                    <div class="thumbnail-images">
                        <?php foreach ($productImages as $index => $image): ?>
                        <img src="<?php echo $image['image_url']; ?>" 
                             alt="<?php echo Functions::escape($product['name']); ?> - تصویر <?php echo $index + 1; ?>"
                             class="thumbnail-img <?php echo $index == 0 ? 'active' : ''; ?>"
                             data-main-image="<?php echo $image['image_url']; ?>"
                             onclick="changeMainImage(this)">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- اطلاعات محصول -->
            <div class="col-lg-6">
                <div class="product-info">
                    <h1 class="h2 mb-3"><?php echo Functions::escape($product['name']); ?></h1>
                    <div class="product-meta">
                        <?php if ($product['sku']): ?>
                        <span><i class="fas fa-barcode"></i> کد محصول: <?php echo $product['sku']; ?></span>
                        <?php endif; ?>
                        <?php if ($product['category_name']): ?>
                        <span><i class="fas fa-folder"></i> دسته‌بندی: 
                            <a href="products.php?category=<?php echo $product['category_id']; ?>" class="text-decoration-none">
                                <?php echo Functions::escape($product['category_name']); ?>
                            </a>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex align-items-center mb-3">
                        <div class="rating-stars me-2">
                            <?php
                            $fullStars = floor($averageRating);
                            $halfStar = ($averageRating - $fullStars) >= 0.5;
                            $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
                            for ($i = 0; $i < $fullStars; $i++) echo '<i class="fas fa-star"></i>';
                            if ($halfStar) echo '<i class="fas fa-star-half-alt"></i>';
                            for ($i = 0; $i < $emptyStars; $i++) echo '<i class="far fa-star"></i>';
                            ?>
                        </div>
                        <span class="rating-count">(<?php echo $ratingCount; ?> نظر)</span>
                        <span class="ms-3"><i class="fas fa-eye"></i> <?php echo number_format($product['view_count']); ?> بازدید</span>
                    </div>
                    <div class="mb-4">
                        <p class="lead"><?php echo Functions::escape($product['short_description']); ?></p>
                    </div>
                    <div class="price-box">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <span class="text-muted">قیمت:</span>
                                <h3 class="d-inline-block ms-2 mb-0 text-primary" id="productPrice">
                                    <?php echo Functions::formatPrice($product['final_price']); ?>
                                </h3>
                            </div>
                            <?php if ($product['discount_type'] != 'none' && $product['discount_value'] > 0): ?>
                            <div>
                                <span class="badge bg-danger discount-badge">
                                    <?php if ($product['discount_type'] == 'percent'): ?>
                                    <?php echo $product['discount_value']; ?>٪ تخفیف
                                    <?php else: ?>
                                    <?php echo Functions::formatPrice($product['discount_value']); ?> تخفیف
                                    <?php endif; ?>
                                </span>
                                <div class="text-muted text-decoration-line-through mt-1">
                                    <?php echo Functions::formatPrice($product['base_price']); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <span class="fw-bold">موجودی:</span>
                            <span class="badge <?php echo $product['stock'] > 0 ? 'bg-success' : 'bg-danger'; ?> stock-badge" id="stockBadge">
                                <?php if ($product['stock'] > 0): ?>
                                <i class="fas fa-check"></i> موجود در انبار
                                <?php else: ?>
                                <i class="fas fa-times"></i> ناموجود
                                <?php endif; ?>
                            </span>
                            <?php if ($product['stock'] > 0): ?>
                            <small class="text-muted" id="stockCount"> (<?php echo number_format($product['stock']); ?> عدد)</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- انتخاب تنوع -->
                    <?php if (!empty($productVariants)): ?>
                    <div class="variant-selector">
                        <label class="form-label fw-bold mb-3">انتخاب تنوع:</label>
                        <div id="variantOptions">
                            <?php 
                            $defaultVariantId = null;
                            foreach ($productVariants as $variant): 
                                if ($variant['is_default'] && !$defaultVariantId) {
                                    $defaultVariantId = $variant['id'];
                                }
                            ?>
                            <div class="variant-option <?php echo $variant['is_default'] ? 'selected' : ''; ?>" 
                                 data-variant-id="<?php echo $variant['id']; ?>"
                                 data-price="<?php echo $variant['final_price']; ?>"
                                 data-stock="<?php echo $variant['stock']; ?>"
                                 onclick="selectVariant(this)">
                                <?php echo $variant['variant_attributes'] ?? 'استاندارد'; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- انتخاب تعداد -->
                    <div class="quantity-selector">
                        <label class="form-label fw-bold">تعداد:</label>
                        <div class="d-flex align-items-center">
                            <button class="quantity-btn" type="button" onclick="changeQuantity(-1)">-</button>
                            <input type="number" id="quantity" name="quantity" value="1" min="1" 
                                   max="<?php echo $product['stock']; ?>" class="quantity-input">
                            <button class="quantity-btn" type="button" onclick="changeQuantity(1)">+</button>
                        </div>
                    </div>
                    
                    <!-- دکمه‌های اقدام -->
                    <form method="POST" class="action-buttons" id="cartForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                        <input type="hidden" name="variant_id" id="selectedVariantId" 
                               value="<?php echo !empty($defaultVariantId) ? $defaultVariantId : ''; ?>">
                        
                        <button type="submit" name="add_to_cart" class="btn btn-primary btn-add-to-cart" id="addToCartBtn"
                                <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?>>
                            <i class="fas fa-cart-plus"></i>
                            <span id="addToCartText">افزودن به سبد خرید</span>
                        </button>
                        
                        <button type="submit" name="buy_now" class="btn btn-success btn-buy-now"
                                <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?>>
                            <i class="fas fa-bolt"></i> خرید آنی
                        </button>
                    </form>
                    
                    <!-- اشتراک‌گذاری -->
                    <div class="share-buttons">
                        <span class="text-muted me-2">اشتراک‌گذاری:</span>
                        <?php
                        $currentUrl = urlencode((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
                        $title = urlencode($product['name']);
                        ?>
                        <a href="https://telegram.me/share/url?url=<?php echo $currentUrl; ?>&text=<?php echo $title; ?>" target="_blank" class="share-btn share-telegram"><i class="fab fa-telegram"></i></a>
                        <a href="https://api.whatsapp.com/send?text=<?php echo $title . ' ' . $currentUrl; ?>" target="_blank" class="share-btn share-whatsapp"><i class="fab fa-whatsapp"></i></a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo $currentUrl; ?>&text=<?php echo $title; ?>" target="_blank" class="share-btn share-twitter"><i class="fab fa-twitter"></i></a>
                    </div>
                    
                    <!-- اطلاعات اضافی -->
                    <div class="mt-4">
                        <div class="row">
                            <?php if ($product['weight']): ?>
                            <div class="col-md-6 mb-2"><i class="fas fa-weight-hanging text-muted"></i> <span class="ms-2">وزن: <?php echo number_format($product['weight']); ?> گرم</span></div>
                            <?php endif; ?>
                            <?php if ($product['dimensions']): ?>
                            <div class="col-md-6 mb-2"><i class="fas fa-cube text-muted"></i> <span class="ms-2">ابعاد: <?php echo $product['dimensions']; ?></span></div>
                            <?php endif; ?>
                            <?php if ($product['shipping_cost'] > 0): ?>
                            <div class="col-md-6 mb-2"><i class="fas fa-shipping-fast text-muted"></i> <span class="ms-2">هزینه ارسال: <?php echo Functions::formatPrice($product['shipping_cost']); ?></span></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- تب‌های اطلاعات محصول -->
        <div class="product-tabs">
            <ul class="nav nav-tabs" id="productInfoTabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" id="description-tab" data-bs-toggle="tab" data-bs-target="#description" type="button"><i class="fas fa-align-left"></i> توضیحات کامل</button></li>
                <li class="nav-item"><button class="nav-link" id="specifications-tab" data-bs-toggle="tab" data-bs-target="#specifications" type="button"><i class="fas fa-list-alt"></i> مشخصات فنی</button></li>
                <li class="nav-item"><button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button"><i class="fas fa-star"></i> نظرات (<?php echo $ratingCount; ?>)</button></li>
                <li class="nav-item"><button class="nav-link" id="shipping-tab" data-bs-toggle="tab" data-bs-target="#shipping" type="button"><i class="fas fa-truck"></i> ارسال و بازگشت</button></li>
            </ul>
            <div class="tab-content" id="productInfoTabsContent">
                <!-- توضیحات کامل -->
                <div class="tab-pane fade show active" id="description" role="tabpanel">
                    <?php if ($product['full_description']): ?>
                    <div class="product-description-content"><?php echo htmlspecialchars_decode($product['full_description']); ?></div>
                    <?php else: ?>
                    <div class="text-center py-5"><i class="fas fa-align-left fa-3x text-muted mb-3"></i><p class="text-muted">توضیحاتی برای این محصول ثبت نشده است.</p></div>
                    <?php endif; ?>
                </div>
                <!-- مشخصات فنی -->
                <div class="tab-pane fade" id="specifications" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6"><table class="table table-striped"> ... </table></div>
                        <div class="col-md-6"><table class="table table-striped"> ... </table></div>
                    </div>
                </div>
                <!-- نظرات -->
                <div class="tab-pane fade" id="reviews" role="tabpanel"> ... </div>
                <!-- ارسال و بازگشت -->
                <div class="tab-pane fade" id="shipping" role="tabpanel"> ... </div>
            </div>
        </div>
        
        <!-- محصولات مرتبط -->
        <?php if (!empty($relatedProducts)): ?>
        <div class="mt-5">
            <h3 class="mb-4">محصولات مرتبط</h3>
            <div class="row">
                <?php foreach ($relatedProducts as $related): ?>
                <div class="col-md-3 col-lg-3 mb-4">
                    <div class="related-product-card">
                        <a href="product.php?id=<?php echo $related['id']; ?>" class="text-decoration-none">
                            <img src="<?php echo $related['main_image'] ?: 'assets/images/no-image.jpg'; ?>" class="card-img-top" alt="<?php echo Functions::escape($related['name']); ?>" style="height: 180px; object-fit: cover;">
                        </a>
                        <div class="card-body">
                            <h6 class="card-title"><a href="product.php?id=<?php echo $related['id']; ?>" class="text-decoration-none text-dark"><?php echo Functions::truncateText($related['name'], 40); ?></a></h6>
                            <div class="product-price mt-3"><span class="fw-bold text-primary"><?php echo Functions::formatPrice($related['final_price']); ?></span></div>
                            <div class="mt-3"><a href="product.php?id=<?php echo $related['id']; ?>" class="btn btn-sm btn-outline-primary w-100"><i class="fas fa-eye"></i> مشاهده محصول</a></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- فوتر -->
    <footer class="footer bg-dark text-white py-5"> ... </footer>

    <!-- مدال ثبت نظر -->
    <?php if ($auth->isLoggedIn()): ?>
    <div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true"> ... </div>
    <?php endif; ?>

    <!-- کانتینر برای پیام‌های فلش -->
    <div class="flash-message-container" id="flashMessageContainer"></div>

    <!-- اسکریپت‌ها -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // تغییر تصویر اصلی
        window.changeMainImage = function(thumbnail) {
            const mainImage = document.getElementById('mainProductImage');
            const mainImageLink = mainImage.parentElement;
            mainImage.src = thumbnail.getAttribute('data-main-image');
            mainImageLink.href = thumbnail.getAttribute('data-main-image');
            document.querySelectorAll('.thumbnail-img').forEach(img => img.classList.remove('active'));
            thumbnail.classList.add('active');
        };

        // تغییر تعداد
        window.changeQuantity = function(change) {
            const input = document.getElementById('quantity');
            let value = parseInt(input.value) + change;
            const max = parseInt(input.max);
            if (value < 1) value = 1;
            if (value > max) value = max;
            input.value = value;
        };

        // انتخاب تنوع
        window.selectVariant = function(variantOption) {
            document.querySelectorAll('.variant-option').forEach(opt => opt.classList.remove('selected'));
            variantOption.classList.add('selected');
            const variantId = variantOption.getAttribute('data-variant-id');
            const price = variantOption.getAttribute('data-price');
            const stock = parseInt(variantOption.getAttribute('data-stock'));
            document.getElementById('selectedVariantId').value = variantId;
            document.getElementById('productPrice').innerHTML = parseInt(price).toLocaleString('fa-IR') + ' تومان';
            
            const stockBadge = document.getElementById('stockBadge');
            const stockCount = document.getElementById('stockCount');
            const maxQuantity = document.getElementById('quantity');
            const addToCartBtn = document.getElementById('addToCartBtn');
            const buyNowBtn = document.querySelector('button[name="buy_now"]');
            
            if (stock > 0) {
                stockBadge.innerHTML = '<i class="fas fa-check"></i> موجود در انبار';
                stockBadge.className = 'badge bg-success stock-badge';
                if (stockCount) stockCount.textContent = ' (' + stock.toLocaleString('fa-IR') + ' عدد)';
                else {
                    const newStockCount = document.createElement('small');
                    newStockCount.className = 'text-muted';
                    newStockCount.id = 'stockCount';
                    newStockCount.textContent = ' (' + stock.toLocaleString('fa-IR') + ' عدد)';
                    stockBadge.parentElement.appendChild(newStockCount);
                }
                addToCartBtn.disabled = false;
                if (buyNowBtn) buyNowBtn.disabled = false;
                maxQuantity.max = stock;
                if (parseInt(maxQuantity.value) > stock) maxQuantity.value = stock;
            } else {
                stockBadge.innerHTML = '<i class="fas fa-times"></i> ناموجود';
                stockBadge.className = 'badge bg-danger stock-badge';
                if (stockCount) stockCount.remove();
                addToCartBtn.disabled = true;
                if (buyNowBtn) buyNowBtn.disabled = true;
            }
        };

        // امتیازدهی
        $('.rating-input i').hover(
            function() {
                const rating = $(this).data('rating');
                $(this).prevAll('i').addBack().removeClass('far').addClass('fas');
                $(this).nextAll('i').removeClass('fas').addClass('far');
            },
            function() {
                const currentRating = $('#ratingValue').val();
                $('.rating-input i').each(function() {
                    $(this).data('rating') <= currentRating ? $(this).removeClass('far').addClass('fas') : $(this).removeClass('fas').addClass('far');
                });
            }
        );
        $('.rating-input i').click(function() { $('#ratingValue').val($(this).data('rating')); });

        // Lightbox
        lightbox.option({ 'resizeDuration': 200, 'wrapAround': true, 'showImageNumberLabel': false, 'positionFromTop': 50 });

        // ------------------------------------------------------------
        // مدیریت فرم افزودن به سبد خرید - اصلاح نهایی برای مشکل تعداد
        // ------------------------------------------------------------
        const cartForm = $('#cartForm');

        cartForm.on('submit', function(e) {
            const submitButton = $(document.activeElement);

            // دکمه "افزودن به سبد خرید"
            if (submitButton.attr('name') === 'add_to_cart') {
                e.preventDefault();

                const button = submitButton;
                const quantity = parseInt($('#quantity').val());
                const stock = parseInt($('#quantity').attr('max'));

                if (quantity > stock) {
                    showFlashMessage('تعداد درخواستی بیشتر از موجودی است', 'error');
                    return;
                }

                button.prop('disabled', true);
                button.html('<i class="fas fa-spinner fa-spin"></i> در حال افزودن...');

                // ساخت داده‌های فرم به صورت دستی (مطمئن‌تر از serialize)
                const formData = {
                    csrf_token: $('input[name="csrf_token"]').val(),
                    product_id: $('input[name="product_id"]').val(),
                    variant_id: $('input[name="variant_id"]').val(),
                    quantity: quantity  // <--- مقدار دقیق از input گرفته می‌شود
                };

                console.log('Sending AJAX with quantity:', formData.quantity); // دیباگ

                $.ajax({
                    url: 'api/cart.php?action=add',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('.cart-count').text(response.cart_count);
                            button.html('<i class="fas fa-check"></i> اضافه شد');
                            button.removeClass('btn-primary').addClass('btn-success');
                            setTimeout(function() {
                                button.html('<i class="fas fa-cart-plus"></i> افزودن به سبد خرید');
                                button.removeClass('btn-success').addClass('btn-primary');
                                button.prop('disabled', false);
                            }, 2000);
                            showFlashMessage('محصول با موفقیت به سبد خرید اضافه شد', 'success');
                        } else {
                            showFlashMessage('خطا: ' + response.message, 'error');
                            button.prop('disabled', false);
                            button.html('<i class="fas fa-cart-plus"></i> افزودن به سبد خرید');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error, xhr.responseText);
                        showFlashMessage('خطا در ارتباط با سرور', 'error');
                        button.prop('disabled', false);
                        button.html('<i class="fas fa-cart-plus"></i> افزودن به سبد خرید');
                    }
                });
            }

            // دکمه "خرید آنی"
            if (submitButton.attr('name') === 'buy_now') {
                $('<input>').attr({ type: 'hidden', name: 'redirect_to_checkout', value: '1' }).appendTo(cartForm);
                return true; // ارسال معمولی فرم
            }
        });

        // نمایش پیام فلش
        function showFlashMessage(message, type = 'success') {
            const alertClass = type === 'error' ? 'alert-danger' : (type === 'warning' ? 'alert-warning' : 'alert-success');
            const alertId = 'flash-' + Date.now();
            const alertHtml = `<div id="${alertId}" class="alert ${alertClass} alert-dismissible fade show" role="alert">${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>`;
            $('#flashMessageContainer').append(alertHtml);
            setTimeout(() => $('#' + alertId).alert('close'), 5000);
        }

        console.log('Product page ready. Quantity input initial value:', $('#quantity').val());
    });
    </script>
</body>
</html>