<?php
/**
 * Dynamic XML Sitemap Generator
 * Location: /sitemap.php
 * Access: /sitemap.xml → rewrites to this via .htaccess
 */
require_once __DIR__ . '/includes/session_setup.php';
require_once __DIR__ . '/includes/db_connect.php';

$base_url = rtrim(defined('SITE_URL') ? SITE_URL : '', '/');
if (empty($base_url) || strpos($base_url, 'localhost') !== false || strpos($base_url, '127.0.0.1') !== false) {
    if (isset($global_settings['site_domain']) && !empty($global_settings['site_domain'])) {
        $base_url = rtrim($global_settings['site_domain'], '/');
    } else {
        $base_url = 'https://www.sagarstarters.com';
    }
}

if (strpos($base_url, 'http') !== 0) {
    $base_url = 'https://' . ltrim($base_url, '/');
}

header('Content-Type: application/xml; charset=utf-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

$today = date('Y-m-d');

// ── 1. Primary Static Pages ─────────────────────────────────────────────
$static_pages = [
    ['url' => '/', 'priority' => '1.0', 'changefreq' => 'daily'],
    ['url' => '/shop.php', 'priority' => '0.9', 'changefreq' => 'daily'],
    ['url' => '/about.php', 'priority' => '0.8', 'changefreq' => 'monthly'],
    ['url' => '/contact.php', 'priority' => '0.8', 'changefreq' => 'monthly'],
    ['url' => '/catalogue.php', 'priority' => '0.8', 'changefreq' => 'weekly'],
];

foreach ($static_pages as $p) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($base_url . $p['url']) . "</loc>\n";
    echo "    <lastmod>{$today}</lastmod>\n";
    echo "    <changefreq>{$p['changefreq']}</changefreq>\n";
    echo "    <priority>{$p['priority']}</priority>\n";
    echo "  </url>\n";
}

// ── 2. CMS Policy & Information Pages ──────────────────────────────────
try {
    $pages_q = $conn->query("SELECT slug, updated_at FROM pages WHERE slug NOT IN ('about', 'contact') ORDER BY id ASC");
    if ($pages_q) {
        while ($page = $pages_q->fetch_assoc()) {
            $lastmod = !empty($page['updated_at']) ? date('Y-m-d', strtotime($page['updated_at'])) : $today;
            echo "  <url>\n";
            echo "    <loc>" . htmlspecialchars($base_url . '/page/' . $page['slug']) . "</loc>\n";
            echo "    <lastmod>{$lastmod}</lastmod>\n";
            echo "    <changefreq>monthly</changefreq>\n";
            echo "    <priority>0.6</priority>\n";
            echo "  </url>\n";
        }
    }
} catch (\Throwable $e) {}

// ── 3. Categories ──────────────────────────────────────────────────────
try {
    $cats_q = $conn->query("SELECT slug FROM categories ORDER BY id ASC");
    if ($cats_q) {
        while ($cat = $cats_q->fetch_assoc()) {
            echo "  <url>\n";
            echo "    <loc>" . htmlspecialchars($base_url . '/category/' . $cat['slug']) . "</loc>\n";
            echo "    <lastmod>{$today}</lastmod>\n";
            echo "    <changefreq>weekly</changefreq>\n";
            echo "    <priority>0.8</priority>\n";
            echo "  </url>\n";
        }
    }
} catch (\Throwable $e) {}

// ── 4. Products with Image Sitemaps ────────────────────────────────────
try {
    $prods_q = $conn->query("SELECT id, name, slug, image, created_at FROM products ORDER BY id ASC");
    if ($prods_q) {
        while ($prod = $prods_q->fetch_assoc()) {
            $lastmod = !empty($prod['created_at']) ? date('Y-m-d', strtotime($prod['created_at'])) : $today;
            
            echo "  <url>\n";
            echo "    <loc>" . htmlspecialchars($base_url . '/product/' . $prod['slug']) . "</loc>\n";
            echo "    <lastmod>{$lastmod}</lastmod>\n";
            echo "    <changefreq>daily</changefreq>\n";
            echo "    <priority>0.9</priority>\n";

            $resolved_img = '';
            if (function_exists('resolve_product_image_url')) {
                $raw_img = resolve_product_image_url($prod['image'] ?? '', $conn, $prod['id'], $prod['name']);
                if (!empty($raw_img)) {
                    if (strpos($raw_img, 'http://') === 0 || strpos($raw_img, 'https://') === 0) {
                        $parsed_path = parse_url($raw_img, PHP_URL_PATH);
                        $clean_path = ltrim((string)$parsed_path, '/');
                        if (strpos($clean_path, 'SagarSite/') === 0) {
                            $clean_path = substr($clean_path, strlen('SagarSite/'));
                        }
                        $resolved_img = $base_url . '/' . $clean_path;
                    } else {
                        $resolved_img = $base_url . '/' . ltrim($raw_img, '/');
                    }
                }
            }

            if (!empty($resolved_img)) {
                echo "    <image:image>\n";
                echo "      <image:loc>" . htmlspecialchars($resolved_img) . "</image:loc>\n";
                echo "      <image:title>" . htmlspecialchars($prod['name']) . "</image:title>\n";
                echo "    </image:image>\n";
            }

            echo "  </url>\n";
        }
    }
} catch (\Throwable $e) {}

echo '</urlset>' . "\n";
