<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';

$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

/* ═══ داده‌ها از دیتابیس ═══ */
$categories = db()->query("SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order")->fetchAll();

$heroBanner = db()->query("
    SELECT title, subtitle, image_path, link FROM banners
    WHERE position='hero' AND is_active=1 ORDER BY id DESC LIMIT 1
")->fetch();

/* محصولات با عکس و تخفیف */
$products = db()->query("
    SELECT p.id, p.slug, p.code, p.name, p.base_moq, pr.price,
           COALESCE(inv.qty_available, 0) AS qty_available,
           (SELECT image_path FROM product_images pi
            WHERE pi.product_id = p.id
            ORDER BY pi.is_main DESC, pi.sort_order ASC LIMIT 1) AS img
    FROM products p
    LEFT JOIN product_prices pr ON pr.product_id = p.id AND pr.customer_group='wholesale' AND pr.min_qty=1
    LEFT JOIN inventory inv ON inv.product_id = p.id
    WHERE p.is_active = 1
    ORDER BY p.id ASC
    LIMIT 8
")->fetchAll();

/* تازه‌ها — ۳ محصول */
$arrivals = array_slice(
    array_filter($products, fn($p) => $p['img']),
    0,
    3
);

/* دسته‌بندی‌ها به‌صورت لیست اسم — مثل Lovable */
$categoryNames = ['همه'];
foreach ($categories as $c) $categoryNames[] = $c['name'];

$activeCat = 'همه';

/* فیلتر محصولات بر اساس دسته (اگر URL پارامتر داشت) */
$catFilter = trim((string)($_GET['cat'] ?? ''));
$visibleProducts = $products;
if ($catFilter !== '') {
    $visibleProducts = array_values(array_filter($products, fn($p) =>
    str_contains(strtolower($p['name']), $catFilter)));
}

$glyphs = ['▥', '◇', '✦', '⌁', '━', '▯', '▮'];

$pageTitle = 'رابطی | خرید عمده جوراب';
ob_start();
?>

<main>

    <!-- ═══ HERO ═══ -->
    <section class="hero">
        <div class="hero-media">
            <?php if (!empty($heroBanner['image_path'])): ?>
                <img src="<?= $h($heroBanner['image_path']) ?>"
                    alt="مجموعه جوراب‌های رابطی"
                    width="1600" height="1050"
                    fetchpriority="high" decoding="async">
            <?php endif; ?>
        </div>
        <div class="hero-copy">
            <span class="eyebrow">تولید مستقیم · فروش عمده</span>
            <h1 id="hero-title">جوراب‌هایی که<br>فروش می‌روند.</h1>
            <p>تأمین مستقیم از تولیدکننده؛<br>قیمت همکاری، موجودی واقعی و سفارش سریع</p>

            <div class="hero-actions">
                <a class="button primary" href="/products">
                    مشاهده محصولات <span class="hero-arrow">←</span>
                </a>
                <a class="button secondary hero-secondary" href="/custom-order">خرید عمده</a>
            </div>

            <div class="hero-proof">
                <span><b>+۱۲۰</b> مدل فعال</span>
                <span><b>۶ جفت</b> حداقل هر مدل</span>
                <span><b>۴۸ ساعت</b> آماده‌سازی</span>
            </div>
        </div>
    </section>

    <!-- ═══ دسته‌بندی‌ها ═══ -->
    <section class="categories section" id="products">
        <div class="section-heading">
            <div>
                <h2>چه جورابی می‌خواهید؟</h2>
            </div>
            <a href="/products">همه محصولات <span class="hero-arrow">←</span></a>
        </div>
        <div class="category-rail">
            <a href="/products" class="active">
                <span>✦</span>
                همه
            </a>
            <?php foreach ($categories as $index => $cat): ?>
                <a href="/products?cat=<?= $h($cat['slug']) ?>">
                    <span><?= $h(trim($cat['icon'] ?? '') ?: $glyphs[$index % count($glyphs)]) ?></span>
                    <?= $h($cat['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ═══ پرفروش‌ها ═══ -->
    <section class="section best-sellers" id="best">
        <div class="section-heading">
            <div>
                <h2>پرفروش‌های این هفته</h2>
                <p>مدل‌هایی که بیشتر از همه سفارش داده می‌شوند.</p>
            </div>
        </div>

        <?php if (empty($visibleProducts)): ?>
            <p class="page-state">فعلاً محصولی در این دسته ثبت نشده است.</p>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($visibleProducts as $product):
                    $stock = (int)$product['qty_available'];
                ?>
                    <article class="product-card">
                        <div class="product-visual">
                            <a href="/product/<?= $h($product['slug']) ?>" class="product-image">
                                <?php if (!empty($product['img'])): ?>
                                    <img src="<?= $h($product['img']) ?>"
                                        alt="<?= $h($product['name']) ?>" loading="lazy">
                                <?php else: ?>
                                    <span class="pd-placeholder">🧦</span>
                                <?php endif; ?>
                            </a>
                            <?php if ($stock > 0): ?>
                                <span class="badge">موجود</span>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <h3><a href="/product/<?= $h($product['slug']) ?>"><?= $h($product['name']) ?></a></h3>
                            <p>کد <?= $h($product['code']) ?> · حداقل <?= (int)$product['base_moq'] ?> جفت</p>

                            <div class="price-row">
                                <strong>
                                    <?= $product['price'] !== null ? number_format((float)$product['price']) : 'استعلام' ?>
                                    <small>تومان</small>
                                </strong>
                                <span>قیمت همکاری</span>
                            </div>

                            <div class="availability">
                                <i></i>
                                <?= $stock > 0 ? 'موجود در انبار' : 'ناموجود' ?>
                            </div>

                            <div class="buy-row">
                                <div class="stepper" data-min="1">
                                    <button type="button" class="step minus">−</button>
                                    <span class="step-num">۱</span>
                                    <button type="button" class="step plus">+</button>
                                </div>
                                <button type="button" class="quick-add"
                                    data-product-id="<?= (int)$product['id'] ?>">
                                    افزودن سریع
                                </button>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- ═══ تازه‌های رابطی ═══ -->
    <section class="new-arrivals" id="new">
        <div class="section new-inner">
            <div class="new-intro">
                <span class="eyebrow">کالکشن تازه</span>
                <h2>تازه‌های رابطی</h2>
                <p>رنگ‌ها و بافت‌های تازه برای ویترین فصل جدید.</p>
                <a href="/products">دیدن همه تازه‌ها <span class="hero-arrow">←</span></a>
            </div>
            <div class="arrival-rail">
                <?php foreach ($arrivals as $index => $item): ?>
                    <a class="arrival-card" href="/product/<?= $h($item['slug']) ?>">
                        <div class="product-image">
                            <?php if (!empty($item['img'])): ?>
                                <img src="<?= $h($item['img']) ?>" alt="<?= $h($item['name']) ?>" loading="lazy">
                            <?php else: ?>
                                <span class="pd-placeholder">🧦</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span>۰<?= $index + 1 ?></span>
                            <h3><?= $h($item['name']) ?></h3>
                            <p><?= $h($item['code']) ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ═══ چرا خرید از رابطی؟ ═══ -->
    <section class="section values">
        <div class="section-heading">
            <div>
                <h2>چرا خرید از رابطی؟</h2>
            </div>
        </div>
        <div class="value-grid">
            <div>
                <span class="vg-icon">📦</span>
                <strong>تولید مستقیم</strong>
                <span>بدون واسطه</span>
            </div>
            <div>
                <span class="vg-icon">🏷️</span>
                <strong>قیمت همکاری</strong>
                <span>پلکانی و شفاف</span>
            </div>
            <div>
                <span class="vg-icon">✓</span>
                <strong>موجودی واقعی</strong>
                <span>آماده سفارش</span>
            </div>
            <div>
                <span class="vg-icon">🚚</span>
                <strong>ارسال سریع</strong>
                <span>به سراسر ایران</span>
            </div>
        </div>
    </section>

    <!-- ═══ سبد خرید عمده ═══ -->
    <section class="section bulk" id="bulk">
        <div class="bulk-heading">
            <span class="eyebrow">خرید عمده، ساده‌تر</span>
            <h2>برای فروشگاهت آماده‌ای؟</h2>
            <p>سبدهای پیشنهادی رابطی را ببین و سفارش عمده را سریع‌تر ببند.</p>
        </div>
        <div class="bulk-options">
            <a href="/products">
                <span>۰۱</span>
                <h3>سبد اقتصادی</h3>
                <p>شروع کم‌ریسک</p>
                <strong>۶۰ جفت <span class="hero-arrow">←</span></strong>
            </a>
            <a href="/products" class="featured">
                <span>۰۲</span>
                <em>انتخاب فروشگاه‌ها</em>
                <h3>سبد پرفروش</h3>
                <p>ترکیب منتخب بازار</p>
                <strong>۱۲۰ جفت <span class="hero-arrow">←</span></strong>
            </a>
            <a href="/products">
                <span>۰۳</span>
                <h3>سبد کامل فروشگاه</h3>
                <p>تنوع کامل ویترین</p>
                <strong>۲۴۰ جفت <span class="hero-arrow">←</span></strong>
            </a>
        </div>
    </section>

    <!-- ═══ پرومو ═══ -->
    <section class="section promo">
        <div>
            <span>قیمت پلکانی سفارش عمده</span>
            <h2>بیشتر بردار، بهتر بخر.</h2>
            <p>با افزایش تعداد سفارش، قیمت هر جفت کمتر می‌شود.</p>
        </div>
        <a class="button accent" href="/products">مشاهده قیمت‌ها <span class="hero-arrow">←</span></a>
    </section>

    <!-- ═══ استوری ═══ -->
    <section class="section story" id="story">
        <div class="story-mark">ر</div>
        <div>
            <span class="eyebrow">مستقیم از کارگاه رابطی</span>
            <h2>کیفیتی که برای فروش ساخته شده.</h2>
            <p>از انتخاب نخ تا بسته‌بندی، هر جوراب در رابطی با تمرکز بر دوام، راحتی و فروش بهتر تولید می‌شود.</p>
        </div>
        <a href="/about">درباره رابطی <span class="hero-arrow">←</span></a>
    </section>

    <!-- ═══ CTA نهایی ═══ -->
    <section class="final-cta">
        <span>رابطی، همراه ویترین شما</span>
        <h2>سفارش بعدی فروشگاهت را<br>از همین‌جا شروع کن.</h2>
        <a class="button light" href="/products">مشاهده محصولات <span class="hero-arrow">←</span></a>
    </section>

</main>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/views/layouts/main.php';
