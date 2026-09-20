<?php
require __DIR__ . '/../config/database.php';

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

/* ═══════════════════════════════════════════════════════
   داده‌های صفحه اصلی — همه از دیتابیس
   عکس‌ها از product_images (زیرکوئری، is_main اولویت)
   ═══════════════════════════════════════════════════════ */

/* دسته‌بندی‌ها */
$categories = db()->query("SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order")->fetchAll();


/* بنر فعال هیرو */
$heroBanner = db()->query("
    SELECT title, subtitle, image_path, link
    FROM banners
    WHERE position = 'hero'
      AND is_active = 1
    ORDER BY id DESC
    LIMIT 1
")->fetch();

/* پرفروش‌ها — عکس از product_images */
$bestSellers = db()->query("
    SELECT p.id, p.slug, p.code, p.name, p.base_moq, pr.price, inv.qty_available,
           (SELECT image_path FROM product_images pi
            WHERE pi.product_id = p.id
            ORDER BY pi.is_main DESC, pi.sort_order ASC LIMIT 1) AS img
    FROM products p
    LEFT JOIN product_prices pr ON pr.product_id = p.id AND pr.customer_group='wholesale'
    LEFT JOIN inventory inv ON inv.product_id = p.id
    WHERE p.is_active = 1
    ORDER BY inv.qty_available DESC
    LIMIT 8
")->fetchAll();

/* تازه‌ها — عکس از product_images */
$newArrivals = db()->query("
    SELECT p.id, p.slug, p.code, p.name, p.base_moq, pr.price, inv.qty_available,
           (SELECT image_path FROM product_images pi
            WHERE pi.product_id = p.id
            ORDER BY pi.is_main DESC, pi.sort_order ASC LIMIT 1) AS img
    FROM products p
    LEFT JOIN product_prices pr ON pr.product_id = p.id AND pr.customer_group='wholesale'
    LEFT JOIN inventory inv ON inv.product_id = p.id
    WHERE p.is_active = 1
    ORDER BY p.id DESC
    LIMIT 8
")->fetchAll();

$pageTitle = 'رابطی — خرید عمده جوراب مستقیم از تولیدکننده';
ob_start();
?>

<!-- ═══════════ ۱. HERO — Editorial کوتاه ═══════════ -->
<section class="r-hero">
    <div class="r-hero-text">
        <h1><?= $h($heroBanner['title'] ?? 'جوراب‌هایی که فروش می‌روند.') ?></h1>

        <p>
            <?= $h($heroBanner['subtitle'] ?? 'تأمین مستقیم از تولیدکننده — قیمت همکاری، موجودی واقعی و سفارش سریع') ?>
        </p>

        <div class="r-hero-cta">
            <a href="<?= $h($heroBanner['link'] ?? '/products') ?>" class="r-btn r-btn-primary">
                مشاهده محصولات
            </a>
            <a href="/register" class="r-btn r-btn-soft">
                خرید عمده
            </a>
        </div>
    </div>

    <div class="r-hero-visual">
        <div class="r-hero-img-slot">
            <?php if (!empty($heroBanner['image_path'])): ?>
                <img
                    src="<?= $h($heroBanner['image_path']) ?>"
                    alt="<?= $h($heroBanner['title'] ?? 'بنر رابطی') ?>"
                    style="width:100%;height:100%;object-fit:cover;border-radius:inherit;display:block;">
            <?php else: ?>
                <span>🧦</span>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ═══════════ ۲. دسته‌ها — Compact ═══════════ -->
<section class="r-section">
    <div class="r-sec-head">
        <div>
            <h2>چه جورابی می‌خواهید؟</h2>
        </div>
    </div>
    <div class="r-cat-row">
        <?php foreach ($categories as $cat): ?>
            <a href="/products?cat=<?= $h($cat['slug']) ?>" class="r-cat">
                <span class="r-cat-ic"><?= $h(trim($cat['icon'] ?? '') ?: '🧦') ?></span>
                <span class="r-cat-name"><?= $h($cat['name']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- ═══════════ ۳. پرفروش‌های این هفته ═══════════ -->
<section class="r-section">
    <div class="r-sec-head">
        <div>
            <h2>پرفروش‌های این هفته</h2>
            <p>مدل‌هایی که بیشتر از همه سفارش داده می‌شوند.</p>
        </div>
        <a href="/products" class="r-sec-link">همه ←</a>
    </div>

    <div class="r-grid">
        <?php foreach ($bestSellers as $p): ?>
            <?php $stock = (int)($p['qty_available'] ?? 0); ?>
            <article class="r-card">
                <a href="/product/<?= $h($p['slug']) ?>" class="r-card-img">
                    <?php if ($stock > 0): ?>
                        <span class="r-badge r-badge-ok">موجود</span>
                    <?php else: ?>
                        <span class="r-badge r-badge-no">ناموجود</span>
                    <?php endif; ?>
                    <?php if (!empty($p['img'])): ?>
                        <img src="<?= $h($p['img']) ?>" alt="<?= $h($p['name']) ?>" loading="lazy">
                    <?php else: ?>
                        <span>🧦</span>
                    <?php endif; ?>
                </a>
                <div class="r-card-body">
                    <h3><a href="/product/<?= $h($p['slug']) ?>"><?= $h($p['name']) ?></a></h3>
                    <span class="r-card-attr">کد <?= $h($p['code']) ?> · حداقل <?= (int)$p['base_moq'] ?> جفت</span>
                    <div class="r-card-foot">
                        <div class="r-price">
                            <small>قیمت همکاری</small>
                            <b><?= $p['price'] !== null ? number_format((float)$p['price']) : 'استعلام' ?><i> تومان</i></b>
                        </div>
                        <?php if ($stock > 0): ?>
                            <button type="button" class="r-quick-add"
                                data-code="<?= $h($p['code']) ?>"
                                onclick="showToast('✓ به سبد اضافه شد — سبد در فاز بعد فعال می‌شود')">
                                افزودن سریع
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<!-- ═══════════ ۴. تازه‌های رابطی — Rail ═══════════ -->
<section class="r-section">
    <div class="r-sec-head">
        <div>
            <h2>تازه‌های رابطی</h2>
            <p>جدیدترین مدل‌های خط تولید.</p>
        </div>
        <a href="/products" class="r-sec-link">همه ←</a>
    </div>

    <div class="r-rail">
        <?php foreach ($newArrivals as $p): ?>
            <a href="/product/<?= $h($p['slug']) ?>" class="r-card r-card-rail">
                <div class="r-card-img">
                    <?php if (!empty($p['img'])): ?>
                        <img src="<?= $h($p['img']) ?>" alt="<?= $h($p['name']) ?>" loading="lazy">
                    <?php else: ?>
                        <span>🧦</span>
                    <?php endif; ?>
                </div>
                <div class="r-card-body">
                    <h3><?= $h($p['name']) ?></h3>
                    <span class="r-card-attr">کد <?= $h($p['code']) ?></span>
                    <div class="r-price">
                        <b><?= $p['price'] !== null ? number_format((float)$p['price']) : 'استعلام' ?><i> تومان</i></b>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- ═══════════ ۵. چرا رابطی — ظریف ═══════════ -->
<section class="r-section">
    <div class="r-why">
        <div class="r-why-item">
            <span class="r-why-ic">➊</span>
            <div><b>تولید مستقیم</b><span>بدون واسطه، از کارگاه رابطی</span></div>
        </div>
        <div class="r-why-item">
            <span class="r-why-ic">➋</span>
            <div><b>قیمت همکاری</b><span>شفاف و ثابت برای اعضا</span></div>
        </div>
        <div class="r-why-item">
            <span class="r-why-ic">➌</span>
            <div><b>موجودی واقعی</b><span>آنچه می‌بینید قابل سفارش است</span></div>
        </div>
        <div class="r-why-item">
            <span class="r-why-ic">➍</span>
            <div><b>ارسال سریع</b><span>۲۴ تا ۷۲ ساعت به سراسر کشور</span></div>
        </div>
    </div>
</section>

<!-- ═══════════ ۶. سبدهای پیشنهادی B2B ═══════════ -->
<section class="r-section">
    <div class="r-sec-head">
        <div>
            <h2>برای فروشگاهت آماده‌ای؟</h2>
            <p>سبدهای پیشنهادی رابطی را ببین و سفارش عمده را سریع‌تر ببند.</p>
        </div>
    </div>
    <div class="r-baskets">
        <a href="/products" class="r-basket">
            <b>سبد اقتصادی</b>
            <span>شروع همکاری با کمترین سرمایه</span>
            <em>از ۵۰ جفت</em>
        </a>
        <a href="/products" class="r-basket r-basket-hot">
            <b>سبد پرفروش</b>
            <span>مدل‌هایی که همیشه می‌روند</span>
            <em>پیشنهاد هفته</em>
        </a>
        <a href="/products" class="r-basket">
            <b>سبد کامل فروشگاه</b>
            <span>پوشش کامل قفسه، یک‌جا</span>
            <em>پیشنهاد مشاور</em>
        </a>
    </div>
</section>

<!-- ═══════════ ۷. قیمت پلکانی — بنر کوچک ═══════════ -->
<section class="r-section">
    <div class="r-promo">
        <div>
            <b>با خرید بیشتر، قیمت بهتری بگیر.</b>
            <span>قیمت‌های پلکانی مخصوص سفارش‌های عمده</span>
        </div>
        <a href="/products" class="r-btn r-btn-accent">مشاهده قیمت‌ها</a>
    </div>
</section>

<!-- ═══════════ ۸. برند استوری — فقط دو خط ═══════════ -->
<section class="r-section">
    <div class="r-story">
        <b>مستقیم از کارگاه رابطی</b>
        <p>جوراب رابطی از خط تولید ما به قفسه فروشگاه شما می‌رسد — بدون واسطه.</p>
        <a href="/about" class="r-sec-link">درباره رابطی ←</a>
    </div>
</section>

<!-- ═══════════ ۹. CTA نهایی ═══════════ -->
<section class="r-final">
    <p>سفارش بعدی فروشگاهت را از همین‌جا شروع کن.</p>
    <a href="/products" class="r-btn r-btn-primary">مشاهده محصولات</a>
</section>

<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layouts/main.php';
