<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'تولیدی جوراب رابطی') ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

  <!-- هدر اصلی -->
  <header class="app-header">
    <div class="header-container">
      <!-- سمت راست: دکمه همبرگر منو -->
      <button class="hamburger" id="menuBtn" type="button" aria-label="منو">
        <span></span>
        <span></span>
        <span></span>
      </button>

      <!-- وسط یا چپ: برند -->
      <a href="/" class="brand">
        <span class="brand-title">جوراب رابطی</span>
        <div class="brand-icon">🧦</div>
      </a>
    </div>
  </header>

  <!-- پرده تاریک‌کننده پس‌زمینه هنگام باز بودن کشو -->
  <div class="overlay" id="drawerOverlay"></div>

  <!-- منوی کشویی سمت راست -->
  <aside class="drawer" id="appDrawer">
    <div class="drawer-header">
      <span class="drawer-title">منوی دسترسی سریع</span>
      <button class="drawer-close" id="closeDrawerBtn" type="button" aria-label="بستن">&times;</button>
    </div>
    <nav class="drawer-nav">
      <a href="/" class="drawer-link">🏠 صفحه اصلی</a>
      <a href="/products" class="drawer-link">🛍️ کاتالوگ و قیمت عمده</a>
      <a href="/orders" class="drawer-link">📦 پیگیری سفارشات</a>
      <a href="/invoices" class="drawer-link">🧾 فاکتورها</a>
      <a href="/contact" class="drawer-link">📞 تماس با کارخانه</a>
    </nav>
  </aside>

  <!-- محتوای صفحات (index.php و ...) -->
  <main class="page-wrapper">
    <?= $content ?>
  </main>

  <!-- جاوااسکریپت مطمئن و تست‌شده برای باز و بسته شدن منو -->
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const menuBtn = document.getElementById('menuBtn');
      const closeBtn = document.getElementById('closeDrawerBtn');
      const overlay = document.getElementById('drawerOverlay');
      const drawer = document.getElementById('appDrawer');

      function openDrawer() {
        drawer.classList.add('is-open');
        overlay.classList.add('is-open');
        document.body.style.overflow = 'hidden';
      }

      function closeDrawer() {
        drawer.classList.remove('is-open');
        overlay.classList.remove('is-open');
        document.body.style.overflow = '';
      }

      if (menuBtn) menuBtn.addEventListener('click', openDrawer);
      if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
      if (overlay) overlay.addEventListener('click', closeDrawer);

      // بستن منو با فشردن دکمه Escape کیبورد
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && drawer.classList.contains('is-open')) {
          closeDrawer();
        }
      });
    });
  </script>
</body>
</html>
