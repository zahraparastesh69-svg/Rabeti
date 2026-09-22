<?php
// api/register.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

header(
    'Content-Type: application/json; charset=utf-8'
);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'ok' => false,
        'error' =>
        'متد درخواست نامعتبر است.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$fullName =
    trim($_POST['full_name'] ?? '');

$companyName =
    trim($_POST['company_name'] ?? '');

$mobile =
    trim($_POST['mobile'] ?? '');

$password =
    $_POST['password'] ?? '';

$password2 =
    $_POST['password2'] ?? '';

/* ══════════ اعتبارسنجی ══════════ */

if ($fullName === '') {

    http_response_code(422);

    echo json_encode([
        'ok' => false,
        'error' =>
        'نام و نام خانوادگی را وارد کنید.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$mobile =
    preg_replace(
        '/[۰-۹]/u',
        fn($d) =>
        mb_strpos(
            '۰۱۲۳۴۵۶۷۸۹',
            $d
        ),
        $mobile
    );

/*
 * تبدیل مطمئن اعداد فارسی
 */

$mobileMap = [
    '۰' => '0',
    '۱' => '1',
    '۲' => '2',
    '۳' => '3',
    '۴' => '4',
    '۵' => '5',
    '۶' => '6',
    '۷' => '7',
    '۸' => '8',
    '۹' => '9',
];

$mobile =
    strtr($mobile, $mobileMap);

if (!preg_match('/^09\d{9}$/', $mobile)) {

    http_response_code(422);

    echo json_encode([
        'ok' => false,
        'error' =>
        'شماره موبایل صحیح نیست.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if (strlen($password) < 8) {

    http_response_code(422);

    echo json_encode([
        'ok' => false,
        'error' =>
        'رمز عبور باید حداقل ۸ کاراکتر باشد.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if ($password !== $password2) {

    http_response_code(422);

    echo json_encode([
        'ok' => false,
        'error' =>
        'تکرار رمز عبور صحیح نیست.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

/* ══════════ ثبت در دیتابیس ══════════ */

try {

    $result =
        register_customer(
            $fullName,
            $companyName,
            $mobile,
            $password
        );

    if (!$result['ok']) {

        http_response_code(409);

        echo json_encode(
            $result,
            JSON_UNESCAPED_UNICODE
        );

        exit;
    }

    echo json_encode([
        'ok' => true,
        'role' => 'customer',
        'name' => $result['name'],
        'redirect' => '/'
    ], JSON_UNESCAPED_UNICODE);

    exit;
} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'error' =>
        'ثبت‌نام انجام نشد. لطفاً دوباره تلاش کنید.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}
