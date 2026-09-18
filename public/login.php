<?php
$pageTitle = 'ورود | جوراب رابطی';
ob_start();
?>

<section class="auth-wrap">
    <div class="auth-card">
        <div class="auth-head">
            <div class="brand-icon" style="width:48px;height:48px;margin:0 auto 12px;">
                <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64M21 21V14.3m0 0L19.3 5.7a1.5 1.5 0 00-1.47-1.2H6.17a1.5 1.5 0 00-1.47 1.2L3 14.3m18 0H3m4.5 6.75V16.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75v4.5" />
                </svg>
            </div>
            <h1 class="auth-title">ورود به حساب</h1>
            <p class="auth-sub">قیمت‌های ویژه و سابقه سفارش‌ها منتظر شماست</p>
        </div>

        <!-- 🔧 فرم فعلاً نمایشی است — منطق ورود در فاز بک‌اند فعال می‌شود -->
        <form method="post" action="/login" novalidate>
            <div class="form-field">
                <label>شماره موبایل</label>
                <input type="tel" name="mobile" dir="ltr" placeholder="09121234567">
            </div>

            <div class="form-field">
                <label>رمز عبور</label>
                <input type="password" name="password" dir="ltr" placeholder="••••••••">
            </div>

            <button type="submit" class="btn btn-orange co-submit">ورود ←</button>
        </form>

        <p class="auth-switch">
            حساب ندارید؟ <a href="/register">ثبت‌نام عمده‌فروشی</a>
        </p>
    </div>
</section>

<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layouts/main.php';
