<?php
require __DIR__ . '/../config/database.php';
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

/* ─────────── خواندن محصول با slug ─────────── */
$slug = trim($_GET['slug'] ?? '');

$stmt = db()->prepare("
    SELECT p.*, c.name AS cat_name, c.slug AS cat_slug,
           inv.qty_available
    FROM products p
    JOIN categories c ON c.id = p.category_id
    LEFT JOIN inventory inv ON inv.product_id = p.id
    WHERE p.slug = ? AND p.is_active = 1
    LIMIT 1
");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    $pageTitle = 'محصول یافت نشد';
    ob_start();
    echo '<div class="empty-state"><div class="empty-icon">🔍</div><h2>این محصول پیدا نشد</h2><a href="/products" class="btn btn-orange">مشاهده کاتالوگ</a></div>';
    $content = ob_get_clean();
    require __DIR__ . '/../views/layouts/main.php';
    exit;
}

/* ─────────── جدول قیمت‌ها (همه گروه‌ها) ─────────── */
$stmt = db()->prepare("
    SELECT customer_group, min_qty, price
    FROM product_prices
    WHERE product_id = ?
    ORDER BY min_qty ASC
");
$stmt->execute([$product['id']]);
$prices = $stmt->fetchAll();

<!-- ▂▂▂ محصول ▂▂▂ -->
<section class="product-detail">

    <div class="pd-gallery">
        <div class="pd-main-img premium-visual">
            <div class="product-surface"></div>
            <?php if ($mainImage): ?>
                <img src="/uploads/products/<?= $h($mainImage['image_path']) ?>"
                     alt="<?= $h($product['name']) ?>">
            <?php else: ?>
                <span class="product-sock">🧦</span>
            <?php endif; ?>
        </div>
        <div class="pd-note">📷 عکس واقعی به‌زودی</div>
    </div>

    <div class="pd-info">
        <div class="product-meta-line" style="margin-bottom:8px;">
            <span>کد <?= $h($product['code']) ?></span>
            <span class="stock-dot"><?= $inStock ? 'موجود' : 'ناموجود' ?></span>
        </div>

        <h1 class="pd-title"><?= $h($product['name']) ?></h1>

        <div class="pd-desc">
            <?= nl2br($h($product['description'] ?? 'این محصول مستقیماً از خط تولید کارخانه عرضه می‌شود. برای دریافت نمونه و مذاکره قیمت با ما در تماس باشید.')) ?>
        </div>

        <div class="pd-price-box">
            <div class="pd-price-title">قیمت همکاری</div>
            <?php if ($prices): ?>
            <table class="pd-price-table">
                <thead><tr><th>تعداد</th><th>قیمت واحد</th></tr></thead>
                <tbody>
                    <?php foreach ($prices as $row): ?>
                    <tr>
                        <td><?= $row['min_qty'] > 1 ? 'از ' . number_format($row['min_qty']) . ' جفت' : 'شروع همکاری' ?></td>
                        <td class="pd-price-cell"><?= number_format($row['price']) ?> تومان</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="pd-no-price">قیمت به‌زودی اعلام می‌شود — تماس: <span dir="ltr">0912-1234567</span></div>
            <?php endif; ?>
        </div>

        <div class="pd-specs">
            <div class="pd-spec"><span>حداقل سفارش (MOQ)</span><b><?= (int)$product['base_moq'] ?> جفت</b></div>
            <div class="pd-spec"><span>دسته</span><b><?= $h($product['cat_name'] ?? '') ?></b></div>
            <div class="pd-spec"><span>موجودی انبار</span><b><?= number_format((int)($product['qty_available'] ?? 0)) ?> جفت</b></div>
            <div class="pd-spec"><span>ارسال</span><b>سراسر کشور — ۲۴ تا ۷۲ ساعت</b></div>
        </div>

        <div class="pd-actions">
            <?php if ($inStock): ?>
            <button class="btn btn-orange pd-order-btn"
                    onclick="showToast('🛒 سبد خرید در فاز بعدی فعال می‌شود')">
                افزودن به سبد سفارش
            </button>
            <?php else: ?>
            <button class="btn pd-disabled" disabled>فعلاً ناموجود</button>
            <?php endif; ?>
            <a href="/custom-order" class="pd-link">درخواست تولید با برند اختصاصی ←</a>
        </div>
    </div>
</section>

/* ─────────── محصولات مشابه (همان دسته) ─────────── */
$stmt = db()->prepare("
    SELECT p.id, p.name, p.slug, p.code, pr.price
    FROM products p
    LEFT JOIN product_prices pr ON pr.product_id = p.id AND pr.customer_group='wholesale'
    WHERE p.category_id = ? AND p.id != ? AND p.is_active = 1
    LIMIT 4
");
$stmt->execute([$product['category_id'], $product['id']]);
$related = $stmt->fetchAll();

/* ─────────── عکس اصلی (اگر ثبت شده باشد) ─────────── */
$stmt = db()->prepare("
    SELECT image_path FROM product_images
    WHERE product_id = ? ORDER BY is_main DESC, sort_order ASC LIMIT 1
");
$stmt->execute([$product['id']]);
$mainImage = $stmt->fetch();

$inStock = ($product['qty_available'] ?? 0) > 0;

$pageTitle = $product['name'] . ' | جوراب رابطی';
ob_start();
?>

<!-- ▂▂▂ مسیر راهنما ▂▂▂ -->
<nav class="breadcrumb">
    <a href="/">خانه</a> ›
    <a href="/products">محصولات</a> ›
    <a href="/products?cat=<?= $h($product['cat_slug'] ?? '') ?>"><?= $h($product['cat_name'] ?? '') ?></a> ›
    <span><?= $h($product['name']) ?></span>
</nav>

<!-- ▂▂▂ بخش اصلی محصول ▂▂▂ -->
<section class="product-detail">

    <!-- عکس -->
    <div class="pd-gallery">
        <div class="pd-main-img">
            <?php if ($mainImage): ?>
                <img src="/uploads/products/<?= htmlspecialchars($mainImage['image_path']) ?>"
                    alt="<?= htmlspecialchars($product['name']) ?>">
            <?php else: ?>
                <span class="pd-placeholder">🧦</span>
            <?php endif; ?>
        </div>
        <div class="pd-note">📷 عکس واقعی به‌زودی —placeholder موقت</div>
    </div>

    <!-- اطلاعات -->
    <div class="pd-info">
        <div class="pd-code">کد محصول: <?= $product['code'] ?></div>
        <h1 class="pd-title"><?= htmlspecialchars($product['name']) ?></h1>

        <div class="pd-badges">
            <span class="pd-badge <?= $inStock ? 'ok' : 'no' ?>">
                <?= $inStock ? '✓ موجود در انبار' : '✗ ناموجود' ?>
            </span>
            <span class="pd-badge neutral">
                موجودی: <?= number_format($product['qty_available'] ?? 0) ?> جفت
            </span>
        </div>

        <div class="pd-desc">
            <?= nl2br(htmlspecialchars($product['description'] ?? 'این محصول مستقیماً از خط تولید کارخانه عرضه می‌شود. برای دریافت نمونه و مذاکره قیمت با ما در تماس باشید.')) ?>
        </div>

        <!-- جدول قیمت عمده -->
        <div class="pd-price-box">
            <div class="pd-price-title">قیمت عمده</div>
            <?php if ($prices): ?>
                <table class="pd-price-table">
                    <thead>
                        <tr>
                            <th>تعداد</th>
                            <th>قیمت واحد</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prices as $row): ?>
                            <tr>
                                <td><?= $row['min_qty'] > 1 ? 'از ' . number_format($row['min_qty']) . ' جفت' : 'تک‌فروش (شروع)' ?></td>
                                <td class="pd-price-cell"><?= number_format($row['price']) ?> تومان</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="pd-no-price">قیمت این محصول به‌زودی اعلام می‌شود — تماس بگیرید: ۰۹۱۲-۱۲۳۴۵۶۷</div>
            <?php endif; ?>
        </div>

        <!-- مشخصات -->
        <div class="pd-specs">
            <div class="pd-spec"><span>حداقل سفارش (MOQ)</span><b><?= $product['base_moq'] ?> جفت</b></div>
            <div class="pd-spec"><span>دسته</span><b><?= htmlspecialchars($product['cat_name']) ?></b></div>
            <div class="pd-spec"><span>ارسال</span><b>سراسر کشور — ۲۴ تا ۷۲ ساعت</b></div>
        </div>

        <!-- اکشن -->
        <div class="pd-actions">
            <?php if ($inStock): ?>
                <button class="btn btn-orange pd-order-btn" onclick="alert('🛒 سبد خرید در فاز بعدی فعال می‌شود')">
                    افزودن به سبد سفارش
                </button>
            <?php else: ?>
                <button class="btn pd-disabled" disabled>فعلاً ناموجود</button>
            <?php endif; ?>
            <a href="/custom-order" class="pd-link">درخواست تولید با برند اختصاصی ←</a>
        </div>
    </div>
</section>

<!-- ▂▂▂ محصولات مشابه ▂▂▂ -->
<?php if ($related): ?>
    <section class="section">
        <h2 class="section-title">محصولات مشابه</h2>
        <div class="prod-grid">
            <?php foreach ($related as $r): ?>
                <a href="/product/<?= $r['slug'] ?>" class="prod-card">
                    <div class="prod-img">🧦</div>
                    <div class="prod-info">
                        <div class="prod-name"><?= htmlspecialchars($r['name']) ?></div>
                        <div class="prod-code">کد: <?= $r['code'] ?></div>
                        <div class="prod-price"><?= number_format($r['price']) ?> تومان</div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layouts/main.php';
