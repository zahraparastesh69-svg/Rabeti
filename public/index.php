<?php
$pageTitle = 'تولیدی جوراب رابطی — عمده، بدون واسطه';
ob_start();
?>

<!-- ▂▂▂ هیرو ▂▂▂ -->
<section class="hero">
    <h1>محصولات تولیدی جوراب رابطی با کیفیت تضمینی و قیمت عمده</h1>
    <p>
        خرید عمده جوراب مستقیم از تولیدکننده — کاتالوگ کامل، قیمت ویژه
        فروشگاه‌داران و پخش‌کنندگان، ارسال به سراسر کشور
    </p>
    <div class="hero-actions">
        <a href="/products" class="btn btn-orange">مشاهده محصولات ←</a>
        <a href="/register" class="btn btn-ghost">به‌عنوان تولیدکننده شروع کنید</a>
    </div>
    <!-- 📷 عکس واقعی کارخانه — فعلاً Placeholder -->
    <div class="hero-img">🧦 🏭</div>
</section>

<section class="section">
  <div class="section-head">
    <h2 class="section-title">دسته‌بندی محصولات</h2>
    <p class="section-sub">انواع جوراب تولیدی — عمده و با قیمت کارخانه (برای مشاهده مدل‌ها کلیک کنید)</p>
  </div>

  <div class="cat-grid">
    <?php foreach ($categories as $cat): ?>
      <div class="cat-card" tabindex="0" role="button" aria-expanded="false">
        <div class="cat-icon"><?= htmlspecialchars($cat['icon']) ?></div>
        <div class="cat-name"><?= htmlspecialchars($cat['title']) ?></div>
        <span class="cat-badge"><?= count($cat['subcategories']) ?> زیردسته</span>
        
        <?php if (!empty($cat['subcategories'])): ?>
          <div class="cat-subs">
            <div class="cat-chips">
              <?php foreach ($cat['subcategories'] as $sub): ?>
                <a href="/products?category=<?= urlencode($sub['slug']) ?>" 
                   class="cat-chip" 
                   onclick="event.stopPropagation();">
                  <?= htmlspecialchars($sub['title']) ?>
                </a>
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
            چون مستقیم از خط تولید کارخانه خرید می‌کنید — بدون واسطه،
            با قیمت عمده شفاف، کنترل کیفیت تضمینی و امکان تولید
            با برند اختصاصی شما از ۵۰۰ جفت به بالا
        </p>
        <a href="/products" class="btn btn-orange">مشاهده محصولات ←</a>
    </div>
</section>

<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layouts/main.php';
