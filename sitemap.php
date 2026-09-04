<?php
/**
 * Dynamic Sitemap Generator
 * Location: /sitemap.php
 * Access: /sitemap.xml → rewrites to this via .htaccess
 */
require_once 'includes/db_connect.php';

$base_url = rtrim(SITE_URL, '/');
// On live, override SITE_URL to use the actual domain
if (isset($global_settings['site_domain']) && !empty($global_settings['site_domain'])) {
    $base_url = rtrim($global_settings['site_domain'], '/');
}

header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

$today = date('Y-m-d');

// ── Static pages ─────────────────────────────────────────────
$static_pages = [
    ['url' => '/', 'priority' => '1.0', 'changefreq' => 'daily'],
    ['url' => '/shop.php', 'priority' => '0.9', 'changefreq' => 'daily'],
    ['url' => '/about.php', 'priority' => '0.7', 'changefreq' => 'monthly'],
    ['url' => '/contact.php', 'priority' => '0.7', 'changefreq' => 'monthly'],
];

foreach ($static_pages as $p) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($base_url . $p['url']) . "</loc>\n";
    echo "    <lastmod>{$today}</lastmod>\n";
    echo "    <changefreq>{$p['changefreq']}</changefreq>\n";
    echo "    <priority>{$p['priority']}</priority>\n";
    echo "  </url>\n";
}

// ── Dynamic pages ─────────────────────────────────────────────
$pages_q = $conn->query("SELECT slug, updated_at FROM pages");
if ($pages_q) {
    while ($page = $pages_q->fetch_assoc()) {
        $lastmod = date('Y-m-d', strtotime($page['updated_at'] ?? $today));
        echo "  <url>\n";
        echo "    <loc>" . htmlspecialchars($base_url . '/page/' . $page['slug']) . "</loc>\n";
        echo "    <lastmod>{$lastmod}</lastmod>\n";
        echo "    <changefreq>monthly</changefreq>\n";
        echo "    <priority>0.6</priority>\n";
        echo "  </url>\n";
    }
}

// ── Categories ────────────────────────────────────────────────
$cats_q = $conn->query("SELECT slug FROM categories");
if ($cats_q) {
    while ($cat = $cats_q->fetch_assoc()) {
        $lastmod = $today;
        echo "  <url>\n";
        echo "    <loc>" . htmlspecialchars($base_url . '/category/' . $cat['slug']) . "</loc>\n";
        echo "    <lastmod>{$lastmod}</lastmod>\n";
        echo "    <changefreq>weekly</changefreq>\n";
        echo "    <priority>0.8</priority>\n";
        echo "  </url>\n";
    }
}

// ── Products ─────────────────────────────────────────────────
$prods_q = $conn->query("SELECT slug, created_at FROM products");
if ($prods_q) {
    while ($prod = $prods_q->fetch_assoc()) {
        $lastmod = !empty($prod['created_at']) ? date('Y-m-d', strtotime($prod['created_at'])) : $today;
        echo "  <url>\n";
        echo "    <loc>" . htmlspecialchars($base_url . '/product/' . $prod['slug']) . "</loc>\n";
        echo "    <lastmod>{$lastmod}</lastmod>\n";
        echo "    <changefreq>daily</changefreq>\n";
        echo "    <priority>0.9</priority>\n";
        echo "  </url>\n";
    }
}

echo '</urlset>';
