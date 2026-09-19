<?php
require_once __DIR__ . '/../config/database.php'; 
require __DIR__ . '/../../config/auth.php';

session_destroy();
header('Location: /admin/login');
exit;
