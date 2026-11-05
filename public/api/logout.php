<?php
/* Auto-detect bootstrap (local + host) */
$__bp = [
    __DIR__ . '/../../sock-b2b/config/bootstrap.php',
    __DIR__ . '/../../config/bootstrap.php',
];
foreach ($__bp as $__b) { if (is_file($__b)) { require_once $__b; break; } }
unset($__bp, $__b);
// api/logout.php

require_once ROOT_PATH . '/config/auth.php';

$wasAdmin = is_admin_logged_in();

$token = (string)($_POST['csrf_token'] ?? $_GET['t'] ?? '');
if (!hash_equals(csrf_token(), $token)) {
    header('Location: ' . ($wasAdmin ? '/admin' : '/'));
    exit;
}

logout_session();

header(
    'Location: ' .
        ($wasAdmin ? '/admin/login' : '/')
);

exit;
