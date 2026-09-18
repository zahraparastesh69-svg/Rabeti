<?php
require __DIR__ . '/../config/database.php';

// ─── داده‌های صفحه اصلی ───
$banner = db()->query("SELECT * FROM banners WHERE position='hero' AND is_active=1 ORDER BY sort_order LIMIT 1")->fetch();
$banner = $banner ?: [];
$bannerImage = trim($banner['image_path'] ?? '');
$bannerLink  = $banner['link'] ?? '/products';

$categories = db()->query("SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order")->fetchAll();

$products = db()->query("
    SELECT p.id, p.slug, p.code, p.name, p.base_moq, pr.price, inv.qty_available
    FROM products p
    LEFT JOIN product_prices pr ON pr.product_id = p.id AND pr.customer_group='wholesale'
    LEFT JOIN inventory inv ON inv.product_id = p.id
    WHERE p.is_active = 1
    ORDER BY p.id
    LIMIT 4
")->fetchAll();

$h = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$productCount = count($products);
$categoryCount = count($categories);
?>
<?php
$pageTitle = 'تولیدی جوراب رابطی — خرید عمده مستقیم از تولیدی';
ob_start();
?>

<!-- ═══════════════════════════════════════════════════════════════
     HERO / بخش اول صفحه
     ═══════════════════════════════════════════════════════════════ -->
<section class="home-hero">
    <div class="hero-orbit hero-orbit-one" aria-hidden="true"></div>
    <div class="hero-orbit hero-orbit-two" aria-hidden="true"></div>
    <div class="hero-noise" aria-hidden="true"></div>

    <div class="hero-media">
        <?php if ($bannerImage): ?>
            <img
                class="banner-img"
                src="<?= $h($bannerImage) ?>"
                alt="تولیدی جوراب رابطی"
                onerror="this.style.display='none'; document.getElementById('bannerFallback').classList.add('show');">
        <?php endif; ?>
        <div class="hero-image-overlay" aria-hidden="true"></div>
    </div>

    <div class="banner-fallback<?= $bannerImage ? '' : ' show' ?>" id="bannerFallback">
        <div class="hero-content">
            <div class="hero-kicker"><span></span> تولید مستقیم از کارخانه</div>
            <h1>جوراب رابطی</h1>
            <p class="hero-lead">خرید عمده، مستقیم از تولیدی؛<br><strong>ساده، سریع و بدون واسطه.</strong></p>
            <p class="hero-description">مدل‌های متنوع جوراب برای بنکداران و فروشگاه‌ها، با امکان تولید اختصاصی برای برند شما.</p>
            <div class="hero-actions">
                <a href="<?= $h($bannerLink) ?>" class="btn-hero-main">
                    مشاهده کاتالوگ
                    <span class="hero-arrow">←</span>
                </a>
                <a href="/custom-order" class="btn-hero-secondary">تولید اختصاصی</a>
            </div>
        </div>

        <div class="hero-product-art" aria-hidden="true">
            <div class="art-halo"></div>
            <div class="art-card art-card-back"></div>
            <div class="art-card art-card-front">
                <div class="art-label">RABETI</div>
                <div class="art-sock">🧦</div>
                <div class="art-lines"><i></i><i></i><i></i></div>
            </div>
            <span class="art-dot dot-a"></span>
            <span class="art-dot dot-b"></span>
            <span class="art-dot dot-c"></span>
        </div>
    </div>

    <div class="hero-bottom">
        <div class="hero-stat">
            <strong>مستقیم</strong><span>از تولیدی</span>
        </div>
        <div class="hero-stat-divider"></div>
        <div class="hero-stat">
            <strong>عمده</strong><span>برای فروشگاه‌ها</span>
        </div>
        <div class="hero-stat-divider"></div>
        <div class="hero-stat">
            <strong>اختصاصی</strong><span>با برند شما</span>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════
     دسته‌بندی‌ها
     ═══════════════════════════════════════════════════════════════ -->
<section class="home-section categories-section">
    <div class="home-section-head">
        <div>
            <span class="eyebrow">CATALOG</span>
            <h2>دسته‌بندی محصولات</h2>
            <p>مدل موردنظر خود را انتخاب کنید و وارد کاتالوگ شوید.</p>
        </div>
        <a href="/products" class="section-more">مشاهده همه <span>←</span></a>
    </div>

    <?php if ($categories): ?>
        <div class="premium-cat-grid">
            <?php foreach ($categories as $index => $cat): ?>
                <?php
                $icon = trim($cat['icon'] ?? '');
                $iconIsImage = $icon !== '' && (
                    preg_match('~^https?://~i', $icon) ||
                    preg_match('~\.(png|jpe?g|webp|svg|gif)(\?.*)?$~i', $icon)
                );
                ?>
                <a href="/products?cat=<?= $h($cat['slug']) ?>" class="premium-cat-card">
                    <span class="cat-number">0<?= $index + 1 ?></span>
                    <div class="premium-cat-icon">
                        <?php if ($iconIsImage): ?>
                            <img src="<?= $h($icon) ?>" alt="<?= $h($cat['name']) ?>" loading="lazy">
                        <?php elseif ($icon !== ''): ?>
                            <span><?= $h($icon) ?></span>
                        <?php else: ?>
                            <span>🧦</span>
                        <?php endif; ?>
                    </div>
                    <div class="premium-cat-copy">
                        <strong><?= $h($cat['name']) ?></strong>
                        <span>مشاهده مدل‌ها <b>←</b></span>
                    </div>
                    <span class="cat-card-glow" aria-hidden="true"></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">در حال حاضر دسته‌بندی فعالی ثبت نشده است.</div>
    <?php endif; ?>
</section>

<!-- ═══════════════════════════════════════════════════════════════
     محصولات ویژه
     ═══════════════════════════════════════════════════════════════ -->
<section class="home-section products-section">
    <div class="home-section-head">
        <div>
            <span class="eyebrow">SELECTED PRODUCTS</span>
            <h2>محصولات ویژه</h2>
            <p>چند انتخاب از محصولات تولیدی رابطی با شرایط عمده.</p>
        </div>
        <a href="/products" class="section-more">کاتالوگ کامل <span>←</span></a>
    </div>

    <?php if ($products): ?>
        <div class="premium-product-grid">
            <?php foreach ($products as $index => $p): ?>
                <?php $stock = (int)($p['qty_available'] ?? 0); ?>
                <a href="/product/<?= $h($p['slug'] ?? $p['id']) ?>" class="premium-product-card">
                    <div class="product-visual">
                        <span class="product-index">0<?= $index + 1 ?></span>
                        <span class="product-badge">عمده</span>
                        <div class="product-surface"></div>
                        <div class="product-sock">🧦</div>
                        <div class="product-shadow"></div>
                        <span class="product-open">↗</span>
                    </div>
                    <div class="premium-product-info">
                        <div class="product-meta-line">
                            <span>کد <?= $h($p['code']) ?></span>
                            <?php if ($stock > 0): ?><span class="stock-dot">موجود</span><?php endif; ?>
                        </div>
                        <h3><?= $h($p['name']) ?></h3>
                        <div class="product-bottom-line">
                            <div>
                                <small>قیمت همکاری</small>
                                <strong><?= $p['price'] !== null ? number_format((float)$p['price']) . ' تومان' : 'استعلام قیمت' ?></strong>
                            </div>
                            <span class="product-moq">حداقل <?= (int)$p['base_moq'] ?> جفت</span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">محصول فعالی برای نمایش وجود ندارد.</div>
    <?php endif; ?>
</section>

<!-- ═══════════════════════════════════════════════════════════════
     سفارش اختصاصی — بخش تجاری مهم
     ═══════════════════════════════════════════════════════════════ -->
<section class="custom-order-panel">
    <div class="custom-order-grid">
        <div class="custom-order-copy">
            <span class="eyebrow eyebrow-light">PRIVATE LABEL</span>
            <h2>طرح شما، تولید ما.</h2>
            <p>اگر سازمان، فروشگاه یا برند شما به جوراب اختصاصی با طرح و نام خودش نیاز دارد، سفارش تولید اختصاصی ثبت کنید.</p>
            <a href="/custom-order" class="custom-order-btn">شروع سفارش اختصاصی <span>←</span></a>
        </div>
        <div class="custom-order-art" aria-hidden="true">
            <div class="custom-ring ring-one"></div>
            <div class="custom-ring ring-two"></div>
            <div class="custom-card">
                <span>YOUR BRAND</span>
                <b>🧦</b>
                <small>MADE BY RABETI</small>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════
     چرا رابطی؟
     ═══════════════════════════════════════════════════════════════ -->
<section class="home-section why-section">
    <div class="home-section-head centered-head">
        <div>
            <span class="eyebrow">WHY RABETI</span>
            <h2>تجربه خرید عمده، ساده‌تر از همیشه</h2>
            <p>پلتفرم رابطی برای اینکه سفارش از تولیدی تا فروشگاه، شفاف و سریع پیش برود.</p>
        </div>
    </div>

    <div class="why-grid">
        <article class="why-card">
            <div class="why-icon">01</div>
            <div>
                <h3>مستقیم از تولیدی</h3>
                <p>بدون زنجیره واسطه؛ ارتباط مستقیم با تولید و فروش عمده.</p>
            </div>
        </article>
        <article class="why-card">
            <div class="why-icon">02</div>
            <div>
                <h3>قیمت همکاری</h3>
                <p>نمایش شرایط خرید عمده و حداقل تعداد سفارش برای هر محصول.</p>
            </div>
        </article>
        <article class="why-card">
            <div class="why-icon">03</div>
            <div>
                <h3>سفارش اختصاصی</h3>
                <p>تولید با طرح، نام یا هویت بصری اختصاصی مجموعه شما.</p>
            </div>
        </article>
        <article class="why-card">
            <div class="why-icon">04</div>
            <div>
                <h3>پیگیری آنلاین</h3>
                <p>از ثبت سفارش تا پیگیری، اطلاعات در یک پلتفرم در دسترس شماست.</p>
            </div>
        </article>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════
     نوار پایانی / CTA
     ═══════════════════════════════════════════════════════════════ -->
<section class="home-final-cta">
    <div>
        <span class="eyebrow">READY TO ORDER?</span>
        <h2>برای سفارش عمده آماده‌اید؟</h2>
        <p>کاتالوگ را ببینید یا برای تولید اختصاصی با ما شروع کنید.</p>
    </div>
    <div class="final-cta-actions">
        <a href="/products" class="btn-final-primary">مشاهده محصولات</a>
        <a href="/contact" class="btn-final-secondary">ارتباط با ما</a>
    </div>
</section>

<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layouts/main.php';
?>