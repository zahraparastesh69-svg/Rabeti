<?php
/* sitemap پویا — از طریق /sitemap.xml (RewriteRule در .htaccess) */
/* Auto-detect bootstrap (local + host) */
$__bp = [
    __DIR__ . '/../sock-b2b/config/bootstrap.php',
    __DIR__ . '/../config/bootstrap.php',
];
foreach ($__bp as $__b) {
    if (is_file($__b)) {
        require_once $__b;
        break;
    }
}
unset($__bp, $__b);
require_once ROOT_PATH . '/config/database.php';

/* [مسیر، priority، changefreq, lastmod] */
$urls = [
    ['/',             '1.0', 'daily',   null],
    ['/products',     '0.9', 'daily',   null],
    ['/custom-order', '0.7', 'monthly', null],
    ['/about',        '0.5', 'monthly', null],
    ['/contact',      '0.5', 'monthly', null],
];

try {
    $categories = db()->query("
        SELECT slug
        FROM categories
        WHERE is_active = 1
        ORDER BY id
    ")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($categories as $slug) {
        $urls[] = ['/products?cat=' . rawurlencode($slug), '0.8', 'weekly', null];
    }

    $products = db()->query("
        SELECT p.slug, p.updated_at
        FROM products p
        JOIN categories c ON c.id = p.category_id
        WHERE p.is_active = 1
        ORDER BY p.id
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($products as $p) {
        $slug = (string)$p['slug'];
        if (preg_match('/^[a-zA-Z0-9\-]+$/', $slug)) {
            $lastmod = null;
            if (!empty($p['updated_at'])) {
                $ts = strtotime((string)$p['updated_at']);
                if ($ts !== false) {
                    $lastmod = date('c', $ts);
                }
            }
            $urls[] = ['/product/' . $slug, '0.8', 'weekly', $lastmod];
        }
    }
} catch (Throwable $e) {
    error_log('sitemap: ' . $e->getMessage());
}

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";

foreach ($urls as $u) {
    [$path, $priority, $changefreq, $lastmod] = $u;
    echo '    <url><loc>',
    htmlspecialchars(SITE_URL . $path, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
    '</loc>';
    if ($lastmod) {
        echo '<lastmod>', $lastmod, '</lastmod>';
    }
    echo '<changefreq>', $changefreq,
    '</changefreq><priority>', $priority,
    '</priority></url>', "\n";
}

echo '</urlset>', "\n";
