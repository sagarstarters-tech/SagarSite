<?php
require_once 'SeoRepository.php';

class SeoService {
    private $repo;
    private $globalSettings;

    public function __construct($conn) {
        $this->repo = new SeoRepository($conn);
        $this->globalSettings = $this->repo->getGlobalSettings();
    }

    /**
     * Get merged SEO data for a page.
     */
    public function getPageSeo($type, $id = 0, $fallbackData = []) {
        $metadata = $this->repo->getMetadata($type, $id);
        
        // Fallback to table-specific metas if available
        if ($type === 'product' && $id > 0) {
            $res = $this->repo->getConnection()->query("SELECT name, meta_description, image FROM products WHERE id=$id");
            if ($res && $res->num_rows > 0) {
                $p = $res->fetch_assoc();
                if (empty($fallbackData['title'])) $fallbackData['title'] = $p['name'];
                if (empty($fallbackData['description'])) $fallbackData['description'] = $p['meta_description'];
                if (empty($fallbackData['image'])) {
                    $fallbackData['image'] = function_exists('resolve_product_image_url') ? resolve_product_image_url($p['image'] ?? '', $this->repo->getConnection(), $id) : ($p['image'] ?? '');
                }
            }
        } elseif (!$metadata) {
            if ($type === 'category' && $id > 0) {
                $res = $this->repo->getConnection()->query("SELECT name, image FROM categories WHERE id=$id");
                if ($res && $res->num_rows > 0) {
                    $p = $res->fetch_assoc();
                    $fallbackData['title'] = $p['name'];
                    // Categories don't have descriptions in this system schema
                    $fallbackData['description'] = "Shop for " . $p['name']; 
                    $fallbackData['image'] = $p['image'];
                }
            } elseif ($type === 'page' && $id > 0) {
                $res = $this->repo->getConnection()->query("SELECT title, meta_title, meta_description FROM pages WHERE id=$id");
                if ($res && $res->num_rows > 0) {
                    $p = $res->fetch_assoc();
                    $fallbackData['title'] = !empty($p['meta_title']) ? $p['meta_title'] : $p['title'];
                    $fallbackData['description'] = $p['meta_description'];
                }
            }
        }

        $seo = [
            'title' => !empty($metadata['meta_title']) ? $metadata['meta_title'] : (!empty($fallbackData['title']) ? $fallbackData['title'] : ($this->globalSettings['default_meta_title'] ?? '')),
            'description' => !empty($metadata['meta_description']) ? $metadata['meta_description'] : (!empty($fallbackData['description']) ? $fallbackData['description'] : ($this->globalSettings['default_meta_description'] ?? '')),
            'keywords' => !empty($metadata['meta_keywords']) ? $metadata['meta_keywords'] : ($this->globalSettings['default_meta_keywords'] ?? ''),
            'og_title' => !empty($metadata['og_title']) ? $metadata['og_title'] : (!empty($metadata['meta_title']) ? $metadata['meta_title'] : (!empty($fallbackData['title']) ? $fallbackData['title'] : '')),
            'og_description' => !empty($metadata['og_description']) ? $metadata['og_description'] : (!empty($metadata['meta_description']) ? $metadata['meta_description'] : (!empty($fallbackData['description']) ? $fallbackData['description'] : '')),
            'og_image' => !empty($metadata['og_image']) ? $metadata['og_image'] : (!empty($fallbackData['image']) ? $fallbackData['image'] : ($this->globalSettings['og_default_image'] ?? '')),
            'og_type' => ($type === 'product') ? 'product' : 'website',
            'twitter_title' => !empty($metadata['twitter_title']) ? $metadata['twitter_title'] : (!empty($metadata['meta_title']) ? $metadata['meta_title'] : (!empty($fallbackData['title']) ? $fallbackData['title'] : '')),
            'twitter_description' => !empty($metadata['twitter_description']) ? $metadata['twitter_description'] : (!empty($metadata['meta_description']) ? $metadata['meta_description'] : (!empty($fallbackData['description']) ? $fallbackData['description'] : '')),
            'twitter_image' => !empty($metadata['twitter_image']) ? $metadata['twitter_image'] : (!empty($fallbackData['image']) ? $fallbackData['image'] : (!empty($metadata['og_image']) ? $metadata['og_image'] : ($this->globalSettings['og_default_image'] ?? ''))),
            'canonical' => $metadata['canonical_url'] ?? '',
            'robots' => !empty($metadata['robots_tag']) ? $metadata['robots_tag'] : ($this->globalSettings['robots_default'] ?? 'index, follow'),
            'schema' => $metadata['schema_markup'] ?? '',
            'site_name' => $this->globalSettings['site_name'] ?? '',
            'favicon' => $this->globalSettings['site_favicon'] ?? ''
        ];

        // Apply sitename and separator to title
        $siteName = $this->globalSettings['site_name'] ?? '';
        $separator = $this->globalSettings['site_separator'] ?? '|';
        
        if ($type !== 'home' && !empty($siteName)) {
            $seo['title'] = $seo['title'] . " $separator " . $siteName;
        }

        return $seo;
    }

    /**
     * Generate JSON-LD Product Schema.
     */
    public function generateProductSchema($product) {
        $baseUrl = defined('SITE_URL') ? SITE_URL : '';
        $assetsUrl = defined('ASSETS_URL') ? ASSETS_URL : '/assets';
        
        // Ensure assetsUrl is used correctly relative to baseUrl
        if (strpos($assetsUrl, 'http') !== 0) {
            $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $assetsUrl = $scheme . "://" . $host . (strpos($assetsUrl, '/') === 0 ? '' : '/') . $assetsUrl;
        }

        $imagePath = isset($product['image']) ? $assetsUrl . "/images/" . $product['image'] : "";

        $schema = [
            "@context" => "https://schema.org/",
            "@type" => "Product",
            "name" => $product['name'],
            "image" => $imagePath,
            "description" => isset($product['description']) ? strip_tags($product['description']) : "",
            "offers" => [
                "@type" => "Offer",
                "priceCurrency" => "INR", // Assuming INR from context
                "price" => $product['price'] ?? 0,
                "availability" => (isset($product['stock']) && $product['stock'] > 0) ? "https://schema.org/InStock" : "https://schema.org/OutOfStock"
            ]
        ];
        return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
