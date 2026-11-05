<?php
/* Auto-detect bootstrap (local + host) */
$__bp = [
    __DIR__ . '/../../sock-b2b/config/bootstrap.php',
    __DIR__ . '/../../config/bootstrap.php',
];
foreach ($__bp as $__b) { if (is_file($__b)) { require_once $__b; break; } }
unset($__bp, $__b);
// admin/logout.php

require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/auth.php';

$wasAdmin = is_admin_logged_in();

$token = (string)($_POST['csrf_token'] ?? $_GET['t'] ?? '');
if ($wasAdmin && !hash_equals(csrf_token(), $token)) {
    header('Location: /admin');
    exit;
}

if ($wasAdmin) {
    logout_session();
}

header('Location: /admin/login');
exit;
