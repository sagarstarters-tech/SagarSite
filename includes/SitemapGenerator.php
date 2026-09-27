<?php

class SitemapGenerator {
    private $conn;
    private $baseUrl;

    public function __construct(mysqli $conn) {
        $this->conn = $conn;
        
        $base = rtrim(defined('SITE_URL') ? SITE_URL : '', '/');
        if (empty($base) || strpos($base, 'localhost') !== false || strpos($base, '127.0.0.1') !== false) {
            global $global_settings;
            if (isset($global_settings['site_domain']) && !empty($global_settings['site_domain'])) {
                $base = rtrim($global_settings['site_domain'], '/');
            } else {
                $base = 'https://www.sagarstarters.com';
            }
        }
        if (strpos($base, 'http') !== 0) {
            $base = 'https://' . ltrim($base, '/');
        }
        $this->baseUrl = $base;
    }

    public function generate() {
        $today = date('Y-m-d');
        
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        
        $urlset = $xml->createElement('urlset');
        $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $urlset->setAttribute('xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1');
        $xml->appendChild($urlset);

        // 1. Primary Static Pages
        $static_pages = [
            ['url' => '/', 'priority' => '1.0', 'changefreq' => 'daily'],
            ['url' => '/shop.php', 'priority' => '0.9', 'changefreq' => 'daily'],
            ['url' => '/about.php', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['url' => '/contact.php', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['url' => '/catalogue.php', 'priority' => '0.8', 'changefreq' => 'weekly'],
        ];

        foreach ($static_pages as $p) {
            $this->addUrl($xml, $urlset, $this->baseUrl . $p['url'], $p['priority'], $p['changefreq'], $today);
        }

        // 2. CMS Pages (exclude legacy about and contact)
        $pages = $this->conn->query("SELECT slug, updated_at FROM pages WHERE slug NOT IN ('about', 'contact') ORDER BY id ASC");
        if ($pages) {
            while ($pg = $pages->fetch_assoc()) {
                $lastmod = !empty($pg['updated_at']) ? date('Y-m-d', strtotime($pg['updated_at'])) : $today;
                $this->addUrl($xml, $urlset, $this->baseUrl . '/page/' . $pg['slug'], '0.6', 'monthly', $lastmod);
            }
        }

        // 3. Categories
        $cats = $this->conn->query("SELECT slug FROM categories ORDER BY id ASC");
        if ($cats) {
            while ($c = $cats->fetch_assoc()) {
                $this->addUrl($xml, $urlset, $this->baseUrl . '/category/' . $c['slug'], '0.8', 'weekly', $today);
            }
        }

        // 4. Products with Images
        $prods = $this->conn->query("SELECT id, name, slug, image, created_at FROM products ORDER BY id ASC");
        if ($prods) {
            while ($p = $prods->fetch_assoc()) {
                $lastmod = !empty($p['created_at']) ? date('Y-m-d', strtotime($p['created_at'])) : $today;
                
                $resolved_img = '';
                if (function_exists('resolve_product_image_url')) {
                    $raw_img = resolve_product_image_url($p['image'] ?? '', $this->conn, $p['id'], $p['name']);
                    if (!empty($raw_img)) {
                        if (strpos($raw_img, 'http://') === 0 || strpos($raw_img, 'https://') === 0) {
                            $parsed_path = parse_url($raw_img, PHP_URL_PATH);
                            $clean_path = ltrim((string)$parsed_path, '/');
                            if (strpos($clean_path, 'SagarSite/') === 0) {
                                $clean_path = substr($clean_path, strlen('SagarSite/'));
                            }
                            $resolved_img = $this->baseUrl . '/' . $clean_path;
                        } else {
                            $resolved_img = $this->baseUrl . '/' . ltrim($raw_img, '/');
                        }
                    }
                }

                $this->addUrl($xml, $urlset, $this->baseUrl . '/product/' . $p['slug'], '0.9', 'daily', $lastmod, $resolved_img, $p['name']);
            }
        }

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
        $filePath = $basePath . '/sitemap.xml';
        if ($xml->save($filePath)) {
            return ['success' => true, 'path' => '/sitemap.xml'];
        }
        return ['success' => false, 'error' => 'Failed to save sitemap.xml'];
    }

    private function addUrl($xml, $urlset, $loc, $priority, $changefreq, $lastmod = null, $imageUrl = '', $imageTitle = '') {
        $url = $xml->createElement('url');
        $url->appendChild($xml->createElement('loc', htmlspecialchars($loc)));
        $url->appendChild($xml->createElement('lastmod', $lastmod ?: date('Y-m-d')));
        $url->appendChild($xml->createElement('changefreq', $changefreq));
        $url->appendChild($xml->createElement('priority', $priority));

        if (!empty($imageUrl)) {
            $img = $xml->createElement('image:image');
            $img->appendChild($xml->createElement('image:loc', htmlspecialchars($imageUrl)));
            if (!empty($imageTitle)) {
                $img->appendChild($xml->createElement('image:title', htmlspecialchars($imageTitle)));
            }
            $url->appendChild($img);
        }

        $urlset->appendChild($url);
    }
}
