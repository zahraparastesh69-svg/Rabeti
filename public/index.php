<?php
$pageTitle = 'تولیدی جوراب رابطی — عمده، بدون واسطه';
ob_start();
?>

<!-- ▂▂▂ هیرو ▂▂▂ -->
<section class="hero">
    <h1>محصولات تولیدی جوراب رابطی با کیفیت تضمینی و قیمت عمده</h1>
    <p>
        کارخانه‌های جوراب رابطی را به فروشگاه‌داران، عمده‌فروشان و
        توزیع‌کنندگان متصل می‌کنیم. کشف کنید، مقایسه کنید، مذاکره کنید
        و با اطمینان سفارش دهید.
    </p>
    <div class="hero-actions">
        <a href="/products" class="btn btn-orange">مشاهده محصولات ←</a>
        <a href="/register" class="btn btn-ghost">به‌عنوان تولیدکننده شروع کنید</a>
    </div>
    <!-- 📷 عکس واقعی کارخانه — فعلاً Placeholder -->
    <div class="hero-img">🧦 🏭</div>
</section>

<!-- ▂▂▂ دسته‌بندی صنایع ▂▂▂ -->
<section class="section">
    <h2 class="section-title">دسته‌بندی محصولات</h2>
    <p class="section-sub">با جوراب شروع می‌کنیم و به همه صنایع تولیدی ایران گسترش می‌یابیم.</p>

    <div class="cat-grid">
        <?php
        // 🔧 موقت — بعداً از دیتابیس. ready=false یعنی بج «به‌زودی»
        $categories = [
            ['🧦', 'جوراب',        true,  ['مردانه', 'زنانه', 'بچگانه', 'ورزشی']],
            ['👕', 'پوشاک',        false, []],
            ['👟', 'کفش',          false, []],
            ['👜', 'کیف',          false, []],
            ['🔌', 'لوازم برقی',   false, []],
            ['🏠', 'لوازم خانه',   false, []],
        ];
        foreach ($categories as [$icon, $name, $ready, $subs]):
        ?>
            <div class="cat-card <?= $ready ? '' : 'is-soon' ?>" role="button" tabindex="0">
                <div class="cat-icon"><?= $icon ?></div>
                <div class="cat-name"><?= $name ?></div>
                <?php if ($ready): ?>
                    <span class="cat-badge"><?= count($subs) ?> زیردسته</span>
                <?php else: ?>
                    <span class="cat-badge cat-badge-soon">به‌زودی</span>
                <?php endif; ?>

                <?php if ($ready && $subs): ?>
                    <div class="cat-subs">
                        <div class="cat-chips">
                            <?php foreach ($subs as $sub): ?>
                                <a href="/products" class="cat-chip"><?= $sub ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- ▂▂▂ چرا جوراب رابطی؟ ▂▂▂ -->
<section class="section">
    <div class="why-card">
        <div class="why-icon">🛡️</div>
        <h2>چرا جوراب رابطی؟</h2>
        <p>
            تولیدکنندگان تأییدشده، قیمت‌گذاری شفاف عمده، مدیریت سفارش
            سریع‌تر و پشتیبانی چندزبانه برای بازار ایران و خاورمیانه.
        </p>
        <a href="/products" class="btn btn-orange">مشاهده محصولات ←</a>
    </div>
</section>

<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layouts/main.php';
