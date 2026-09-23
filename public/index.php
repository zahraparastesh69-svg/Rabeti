<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once ROOT_PATH . '/config/database.php';

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$categories = db()->query("
    SELECT *
    FROM categories
    WHERE is_active = 1
    ORDER BY sort_order
")->fetchAll();

$heroBanners = db()->query("
    SELECT
        id,
        title,
        subtitle,
        image_path,
        mobile_image_path,
        link,
        sort_order
    FROM banners
    WHERE position = 'hero'
      AND is_active = 1
    ORDER BY sort_order ASC, id ASC
")->fetchAll();

$bestSellers = db()->query("
    SELECT
        p.id,
        p.slug,
        p.code,
        p.name,
        p.base_moq,
        pr.price,
        inv.qty_available,
        (
            SELECT image_path
            FROM product_images pi
            WHERE pi.product_id = p.id
            ORDER BY pi.is_main DESC, pi.sort_order ASC
            LIMIT 1
        ) AS img
    FROM products p
    LEFT JOIN product_prices pr
        ON pr.product_id = p.id
       AND pr.customer_group = 'wholesale'
    LEFT JOIN inventory inv
        ON inv.product_id = p.id
    WHERE p.is_active = 1
    ORDER BY inv.qty_available DESC
    LIMIT 4
")->fetchAll();

$newArrivals = db()->query("
    SELECT
        p.id,
        p.slug,
        p.code,
        p.name,
        p.base_moq,
        pr.price,
        inv.qty_available,
        (
            SELECT image_path
            FROM product_images pi
            WHERE pi.product_id = p.id
            ORDER BY pi.is_main DESC, pi.sort_order ASC
            LIMIT 1
        ) AS img
    FROM products p
    LEFT JOIN product_prices pr
        ON pr.product_id = p.id
       AND pr.customer_group = 'wholesale'
    LEFT JOIN inventory inv
        ON inv.product_id = p.id
    WHERE p.is_active = 1
    ORDER BY p.id DESC
    LIMIT 6
")->fetchAll();

$pageTitle = 'رابطی — خرید عمده جوراب مستقیم از تولیدکننده';

ob_start();
?>

<!-- ═══ ۱. HERO ═══ -->
<section class="r6-hero" id="r6Hero">

    <?php if (!empty($heroBanners)): ?>

        <div class="r6-hero-slides">

            <?php foreach ($heroBanners as $index => $banner): ?>

                <article class="r6-hero-slide <?= $index === 0 ? 'is-active' : '' ?>">

                    <?php if (!empty($banner['image_path'])): ?>

                        <picture class="r6-hero-picture">

                            <?php if (!empty($banner['mobile_image_path'])): ?>
                                <source
                                    media="(max-width: 760px)"
                                    srcset="<?= $h($banner['mobile_image_path']) ?>">
                            <?php endif; ?>

                            <img
                                class="r6-hero-img"
                                src="<?= $h($banner['image_path']) ?>"
                                alt="<?= $h($banner['title'] ?? 'بنر رابطی') ?>">

                        </picture>

                    <?php endif; ?>

                    <div class="r6-hero-fade"></div>

                    <div class="r6-hero-content">

                        <span class="r6-eyebrow">
                            تولید مستقیم · فروش عمده
                        </span>

                        <h1>
                            <?= $h(
                                $banner['title']
                                    ?? 'جوراب‌هایی که فروش می‌روند.'
                            ) ?>
                        </h1>

                        <p>
                            <?= $h(
                                $banner['subtitle']
                                    ?? 'تأمین مستقیم از تولیدکننده؛ قیمت همکاری، موجودی واقعی و سفارش سریع'
                            ) ?>
                        </p>

                        <div class="r6-hero-cta">

                            <a
                                href="<?= $h($banner['link'] ?? '/products') ?>"
                                class="r-btn r-btn-primary">
                                مشاهده محصولات
                                <span class="r6-arrow">←</span>
                            </a>

                            <a
                                href="/custom-order"
                                class="r-btn r-btn-outline">
                                سفارش اختصاصی
                            </a>

                        </div>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

        <?php if (count($heroBanners) > 1): ?>

            <!-- دکمه قبلی -->
            <button
                type="button"
                class="r6-hero-prev"
                aria-label="بنر قبلی">
                ‹
            </button>

            <!-- دکمه بعدی -->
            <button
                type="button"
                class="r6-hero-next"
                aria-label="بنر بعدی">
                ›
            </button>

            <!-- نقاط اسلایدر -->
            <div class="r6-hero-dots">

                <?php foreach ($heroBanners as $index => $banner): ?>

                    <button
                        type="button"
                        class="r6-hero-dot <?= $index === 0 ? 'is-active' : '' ?>"
                        data-slide="<?= $index ?>"
                        aria-label="بنر <?= $index + 1 ?>"></button>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    <?php else: ?>

        <!-- حالت بدون بنر -->
        <div class="r6-hero-empty">

            <h1>
                جوراب‌هایی که فروش می‌روند.
            </h1>

            <p>
                تأمین مستقیم از تولیدکننده؛
                قیمت همکاری، موجودی واقعی و سفارش سریع
            </p>

            <a
                href="/products"
                class="r-btn r-btn-primary">
                مشاهده محصولات
            </a>

        </div>

    <?php endif; ?>

</section>

<!-- ═══ ۲. دسته‌ها — «چه جورابی می‌خواهید؟» ═══ -->
<section class="r-section">

    <div class="r-sec-head">

        <div>
            <h2>چه جورابی می‌خواهید؟</h2>
        </div>

        <a
            href="/products"
            class="r-sec-link">
            همه محصولات ←
        </a>

    </div>

    <div class="r6-cats">

        <?php foreach ($categories as $cat): ?>

            <a
                href="/products?cat=<?= $h($cat['slug']) ?>"
                class="r6-cat">

                <?php if (!empty($cat['image_path'])): ?>

                    <img
                        src="<?= $h($cat['image_path']) ?>"
                        alt="<?= $h($cat['name']) ?>">

                <?php else: ?>

                    <span class="r6-cat-fallback">
                        <?= $h(trim($cat['icon'] ?? '') ?: '🧦') ?>
                    </span>

                <?php endif; ?>

                <span>
                    <?= $h($cat['name']) ?>
                </span>

            </a>

        <?php endforeach; ?>

    </div>

</section>

<!-- ═══ ۳. پرفروش‌ها — PRODUCT FIRST ═══ -->
<section class="r-section">

    <div class="r-sec-head">

        <div>

            <h2>پرفروش‌های این هفته</h2>

            <p>
                مدل‌هایی که بیشتر از همه سفارش داده می‌شوند.
            </p>

        </div>

        <a
            href="/products"
            class="r-sec-link">
            مشاهده همه ←
        </a>

    </div>

    <div class="r-grid">

        <?php foreach ($bestSellers as $i => $p): ?>

            <?php
            $stock = (int)($p['qty_available'] ?? 0);
            ?>

            <article class="r6-card" data-product-id="<?= (int)$p['id'] ?>">
                <a
                    href="/product/<?= $h($p['slug']) ?>"
                    class="r6-card-img">

                    <?php if ($i === 0): ?>

                        <span class="r6-badge">
                            محبوب
                        </span>

                    <?php endif; ?>

                    <?php if (!empty($p['img'])): ?>

                        <img
                            src="<?= $h($p['img']) ?>"
                            alt="<?= $h($p['name']) ?>"
                            loading="lazy">

                    <?php else: ?>

                        <span>🧦</span>

                    <?php endif; ?>

                </a>

                <div class="r6-card-body">

                    <h3>
                        <a href="/product/<?= $h($p['slug']) ?>">
                            <?= $h($p['name']) ?>
                        </a>
                    </h3>

                    <span class="r6-attr">
                        کد <?= $h($p['code']) ?>
                        |
                        حداقل <?= (int)$p['base_moq'] ?> جفت
                    </span>

                    <div class="r6-price">

                        <b>
                            <?= $p['price'] !== null
                                ? number_format((float)$p['price'])
                                : 'استعلام' ?>
                            تومان
                        </b>

                        <small>
                            قیمت همکاری
                        </small>

                    </div>

                    <span class="r6-stock <?= $stock > 0 ? 'in' : 'out' ?>">
                        <?= $stock > 0
                            ? '● موجود در انبار'
                            : '● ناموجود' ?>
                    </span>

                    <?php if ($stock > 0): ?>

                        <div
                            class="r6-stepper"
                            data-min="1">

                            <button
                                type="button"
                                class="r6-step plus">
                                +
                            </button>

                           <input type="text" inputmode="numeric" class="r6-num step-input" value="۱">

                            <button
                                type="button"
                                class="r6-step minus">
                                −
                            </button>

                        </div>

                        <button
                            type="button"
                            class="r6-add"
                            onclick="showToast('✓ اضافه شد — سبد در فاز بعد فعال می‌شود')">
                            افزودن سریع
                        </button>

                    <?php endif; ?>

                </div>

            </article>

        <?php endforeach; ?>

    </div>

</section>

<!-- ═══ ۴. تازه‌ها ═══ -->
<section class="r6-newwrap">

    <div class="r6-new-head">

        <div>

            <span class="r6-eyebrow">
                کالکشن تازه
            </span>

            <h2>
                تازه‌های رابطی
            </h2>

            <p>
                رنگ‌ها و بافت‌های تازه برای ویترین فصل جدید.
            </p>

            <a
                href="/products"
                class="r-sec-link">
                دیدن همه تازه‌ها ←
            </a>

        </div>

    </div>

    <div class="r-grid">

        <?php foreach ($newArrivals as $i => $p): ?>

            <a
                href="/product/<?= $h($p['slug']) ?>"
                class="r6-card r6-card-new">

                <div class="r6-card-img">

                    <?php if (!empty($p['img'])): ?>

                        <img
                            src="<?= $h($p['img']) ?>"
                            alt="<?= $h($p['name']) ?>"
                            loading="lazy">

                    <?php else: ?>

                        <span>🧦</span>

                    <?php endif; ?>

                </div>

                <div class="r6-card-body">

                    <span class="r6-new-num">
                        <?= (string)($i + 1) . '.' ?>
                    </span>

                    <h3>
                        <?= $h($p['name']) ?>
                    </h3>

                    <span class="r6-attr">
                        کد <?= $h($p['code']) ?>
                    </span>

                </div>

            </a>

        <?php endforeach; ?>

    </div>

</section>

<!-- ═══ ۵. چرا رابطی ═══ -->
<section class="r-section">

    <h2 class="r6-center-title">
        چرا خرید از رابطی؟
    </h2>

    <div class="r6-why">

        <div class="r6-why-cell">

            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.6">
                <path
                    stroke-linejoin="round"
                    d="M21 8l-9-5-9 5v8l9 5 9-5V8zM3.3 8.3L12 13l8.7-4.7M12 13v9" />
            </svg>

            <b>تولید مستقیم</b>
            <span>بدون واسطه</span>

        </div>

        <div class="r6-why-cell">

            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.6">
                <path
                    stroke-linejoin="round"
                    d="M20.6 13.4L11 3.8A2 2 0 009.6 3.2H4a1 1 0 00-1 1v5.6c0 .5.2 1 .6 1.4l9.6 9.6a2 2 0 002.8 0l4.6-4.6a2 2 0 000-2.8zM7.5 8a1 1 0 110-2 1 1 0 010 2z" />
            </svg>

            <b>قیمت همکاری</b>
            <span>پلکانی و شفاف</span>

        </div>

        <div class="r6-why-cell">

            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.6">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M1 5h13v11H1zM14 8h4l3 3v5h-7M5.5 19a2 2 0 100-4 2 2 0 000 4zm11 0a2 2 0 100-4 2 2 0 000 4z" />
            </svg>

            <b>ارسال سریع</b>
            <span>به سراسر ایران</span>

        </div>

        <div class="r6-why-cell">

            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M20 6L9 17l-5-5" />
            </svg>

            <b>موجودی واقعی</b>
            <span>آماده سفارش</span>

        </div>

    </div>

</section>

<!-- ═══ ۶. سبدهای پیشنهادی ═══ -->
<section class="r-section">

    <div class="r-sec-head">

        <div>

            <span class="r6-eyebrow">
                خرید عمده، ساده‌تر
            </span>

            <h2>
                برای فروشگاهت آماده‌ای؟
            </h2>

            <p>
                پیشنهادهای رابطی را ببین و سفارش عمده را سریع‌تر ببند.
            </p>

        </div>

    </div>

    <div class="r6-baskets">

        <a
            href="/products"
            class="r6-basket">

            <span class="r6-ghost">
                01
            </span>

            <div class="r6-basket-info">
                <b>سبد اقتصادی</b>
                <span>شروع کم‌ریسک</span>
            </div>

            <span class="r6-basket-link">
                ۶۰ جفت ←
            </span>

        </a>

        <a
            href="/products"
            class="r6-basket is-hot">

            <span class="r6-basket-flag">
                انتخاب فروشگاه‌ها
            </span>

            <span class="r6-ghost">
                02
            </span>

            <div class="r6-basket-info">
                <b>سبد پرفروش</b>
                <span>ترکیب منتخب بازار</span>
            </div>

            <span class="r6-basket-link">
                ۱۲۰ جفت ←
            </span>

        </a>

        <a
            href="/products"
            class="r6-basket">

            <span class="r6-ghost">
                03
            </span>

            <div class="r6-basket-info">
                <b>سبد کامل فروشگاه</b>
                <span>تنوع کامل ویترین</span>
            </div>

            <span class="r6-basket-link">
                ۲۴۰ جفت ←
            </span>

        </a>

    </div>

</section>

<!-- ═══ ۷. قیمت پلکانی ═══ -->
<section class="r-section">

    <div class="r6-promo">

        <span class="r6-eyebrow-light">
            قیمت پلکانی سفارش عمده
        </span>

        <b>
            بیشتر بردار، بهتر بخر.
        </b>

        <p>
            با افزایش تعداد سفارش، قیمت هر جفت کمتر می‌شود.
        </p>

        <a
            href="/products"
            class="r-btn r-btn-accent">
            مشاهده قیمت‌ها
            <span class="r6-arrow">←</span>
        </a>

    </div>

</section>

<!-- ═══ ۸. برند استوری ═══ -->
<section class="r-section">

    <div class="r6-story">

        <div class="r6-story-tile">
            ر
        </div>

        <div class="r6-story-text">

            <span class="r6-eyebrow">
                مستقیم از کارگاه رابطی
            </span>

            <b>
                کیفیتی که برای فروش ساخته شده.
            </b>

            <p>
                از انتخاب نخ تا بسته‌بندی، هر جوراب در رابطی
                با تمرکز بر دوام، راحتی و فروش بهتر تولید می‌شود.
            </p>

            <a
                href="/about"
                class="r-sec-link">
                درباره رابطی ←
            </a>

        </div>

    </div>

</section>

<!-- ═══ ۹. CTA نهایی ═══ -->
<section class="r6-final">

    <span class="r6-eyebrow-light">
        رابطی، همراه ویترین شما
    </span>

    <b>
        سفارش بعدی فروشگاهت را<br>
        از همین‌جا شروع کن.
    </b>

    <a
        href="/products"
        class="r-btn r-btn-white">
        مشاهده محصولات
        <span class="r6-arrow">←</span>
    </a>

</section>

<!-- ═══ HERO SLIDER JS ═══ -->
<script>
    (function() {
        function initHero() {
            const hero = document.getElementById('r6Hero');

            if (!hero) return;

            const slides = Array.from(
                hero.querySelectorAll('.r6-hero-slide')
            );

            const dots = Array.from(
                hero.querySelectorAll('.r6-hero-dot')
            );

            const prevButton = hero.querySelector('.r6-hero-prev');
            const nextButton = hero.querySelector('.r6-hero-next');

            if (slides.length <= 1) return;

            let current = 0;
            let timer = null;

            function showSlide(index) {
                current = (index + slides.length) % slides.length;

                slides.forEach((slide, i) => {
                    const active = i === current;

                    slide.classList.toggle(
                        'is-active',
                        active
                    );

                    slide.style.opacity = active ? '1' : '0';
                    slide.style.visibility = active ? 'visible' : 'hidden';
                    slide.style.pointerEvents = active ? 'auto' : 'none';
                    slide.style.zIndex = active ? '1' : '0';
                });

                dots.forEach((dot, i) => {
                    dot.classList.toggle(
                        'is-active',
                        i === current
                    );
                });
            }

            function nextSlide() {
                showSlide(current + 1);
            }

            function prevSlide() {
                showSlide(current - 1);
            }

            function restartAutoPlay() {
                clearInterval(timer);

                timer = setInterval(
                    nextSlide,
                    5000
                );
            }

            nextButton?.addEventListener(
                'click',
                function(e) {
                    e.preventDefault();
                    nextSlide();
                    restartAutoPlay();
                }
            );

            prevButton?.addEventListener(
                'click',
                function(e) {
                    e.preventDefault();
                    prevSlide();
                    restartAutoPlay();
                }
            );

            dots.forEach((dot, index) => {
                dot.addEventListener(
                    'click',
                    function(e) {
                        e.preventDefault();
                        showSlide(index);
                        restartAutoPlay();
                    }
                );
            });

            hero.addEventListener(
                'mouseenter',
                function() {
                    clearInterval(timer);
                }
            );

            hero.addEventListener(
                'mouseleave',
                function() {
                    restartAutoPlay();
                }
            );

            showSlide(0);
            restartAutoPlay();

            console.log(
                'R6 Hero Slider فعال شد:',
                slides.length,
                'بنر'
            );
        }

        if (document.readyState === 'loading') {
            document.addEventListener(
                'DOMContentLoaded',
                initHero
            );
        } else {
            initHero();
        }
    })();
</script>

<?php
$content = ob_get_clean();

require ROOT_PATH . '/views/layouts/main.php';
?>