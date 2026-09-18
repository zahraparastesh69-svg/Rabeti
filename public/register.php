<?php
$pageTitle = 'ثبت‌نام عمده‌فروشی | جوراب رابطی';
ob_start();
?>

<section class="auth-wrap">
    <div class="auth-card">
        <div class="auth-head">
            <h1 class="auth-title">ثبت‌نام عمده‌فروشی</h1>
            <p class="auth-sub">
                پس از ثبت اطلاعات، حساب شما توسط کارشناس ما بررسی و فعال می‌شود —
                معمولاً کمتر از یک روز کاری
            </p>
        </div>

        <!-- 🔧 نمایشی — منطق در فاز بک‌اند -->
        <form method="post" action="/register" novalidate>
            <div class="form-field">
                <label>نام و نام خانوادگی <i>*</i></label>
                <input type="text" name="full_name" placeholder="مثلاً: کاظم قوطبی">
            </div>

            <div class="form-field">
                <label>نام مغازه / شرکت <i>*</i></label>
                <input type="text" name="company_name" placeholder="مثلاً: پخش قوطبی">
            </div>

            <div class="form-field">
                <label>شماره موبایل <i>*</i></label>
                <input type="tel" name="mobile" dir="ltr" placeholder="09121234567">
                <span class="field-hint">این شماره، نام کاربری شما خواهد بود</span>
            </div>

            <div class="form-field">
                <label>رمز عبور <i>*</i></label>
                <input type="password" name="password" dir="ltr" placeholder="حداقل ۸ کاراکتر">
            </div>

            <div class="form-field">
                <label>تکرار رمز عبور <i>*</i></label>
                <input type="password" name="password2" dir="ltr">
            </div>

            <button type="submit" class="btn btn-orange co-submit">ثبت درخواست عضویت ←</button>
        </form>

        <p class="auth-switch">
            قبلاً ثبت‌نام کرده‌اید؟ <a href="/login">ورود</a>
        </p>
    </div>
</section>

<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layouts/main.php';
