<?php

require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/config/auth.php';

// اگر از قبل لاگین است → داشبورد
if (!empty($_SESSION['admin_id'])) {
    header('Location: /admin');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mobile   = trim($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';

    if (attempt_admin_login($mobile, $password)) {
        header('Location: /admin');
        exit;
    }

    $error = 'شماره یا رمز عبور نادرست است — یا بیش از حد تلاش کرده‌اید';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود مدیریت</title>
    <link rel="stylesheet" href="/assets/css/app.css?v=12">
</head>

<body class="app-body" style="display:grid;place-items:center;min-height:100vh;">

    <div class="auth-card" style="margin:20px;">

        <div class="auth-head">

            <div class="brand-icon" style="width:52px;height:52px;margin:0 auto 14px;">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75M4.5 19.5h15a1.5 1.5 0 001.5-1.5v-6a1.5 1.5 0 00-1.5-1.5h-15a1.5 1.5 0 00-1.5 1.5v6a1.5 1.5 0 001.5 1.5z" />
                </svg>
            </div>

            <h1 class="auth-title">ورود مدیریت</h1>
            <p class="auth-sub">دسترسی به پنل کنترل فروشگاه</p>

        </div>

        <?php if ($error): ?>
            <div class="form-alert error" style="display:block;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post">

            <div class="form-field">
                <label>موبایل</label>
                <input
                    type="tel"
                    name="mobile"
                    dir="ltr"
                    required
                    placeholder="09120000000"
                    value="<?= htmlspecialchars($mobile ?? '') ?>">
            </div>

            <div class="form-field">
                <label>رمز عبور</label>
                <input
                    type="password"
                    name="password"
                    dir="ltr"
                    required>
            </div>

            <button type="submit" class="btn btn-orange co-submit">
                ورود ←
            </button>

        </form>

    </div>

</body>

</html>