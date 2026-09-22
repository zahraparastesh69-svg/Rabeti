<?php
// register.php

$pageTitle = 'ثبت‌نام | جوراب رابطی';

ob_start();
?>

<section class="auth-wrap">

    <div class="auth-card">

        <div class="auth-head">

            <span class="auth-pane-kicker">
                شروع همکاری با رابطی
            </span>

            <h1 class="auth-title">
                ثبت‌نام
            </h1>

            <p class="auth-sub">
                اطلاعات فروشگاه خود را وارد کنید تا حساب شما
                برای استفاده از خدمات فروشگاه ایجاد شود.
            </p>

        </div>

        <form
            method="post"
            action="/register"
            novalidate>

            <div class="form-field">

                <label>
                    نام و نام خانوادگی
                    <i>*</i>
                </label>

                <input
                    type="text"
                    name="full_name"
                    autocomplete="name"
                    placeholder="مثلاً: کاظم قوطبی"
                    required>

            </div>

            <div class="form-field">

                <label>
                    نام مغازه / شرکت
                    <i>*</i>
                </label>

                <input
                    type="text"
                    name="company_name"
                    autocomplete="organization"
                    placeholder="مثلاً: پخش قوطبی"
                    required>

            </div>

            <div class="form-field">

                <label>
                    شماره موبایل
                    <i>*</i>
                </label>

                <input
                    type="tel"
                    name="mobile"
                    dir="ltr"
                    inputmode="numeric"
                    autocomplete="tel"
                    placeholder="09121234567"
                    required>

                <span class="field-hint">
                    این شماره برای ورود به حساب استفاده می‌شود.
                </span>

            </div>

            <div class="form-field">

                <label>
                    رمز عبور
                    <i>*</i>
                </label>

                <input
                    type="password"
                    name="password"
                    dir="ltr"
                    autocomplete="new-password"
                    placeholder="حداقل ۸ کاراکتر"
                    required>

            </div>

            <div class="form-field">

                <label>
                    تکرار رمز عبور
                    <i>*</i>
                </label>

                <input
                    type="password"
                    name="password2"
                    dir="ltr"
                    autocomplete="new-password"
                    required>

            </div>

            <button
                type="submit"
                class="btn btn-orange co-submit">
                ایجاد حساب
            </button>

        </form>

        <p class="auth-switch">

            قبلاً حساب دارید؟

            <button
                type="button"
                class="auth-inline-button"
                data-auth-open="login">
                ورود
            </button>

        </p>

    </div>

</section>

<?php

$content = ob_get_clean();

require __DIR__ . '/../views/layouts/main.php';
