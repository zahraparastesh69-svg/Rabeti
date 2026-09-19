<?php

require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/config/auth.php';

require_admin();

session_destroy();
header('Location: /admin/login');
exit;
