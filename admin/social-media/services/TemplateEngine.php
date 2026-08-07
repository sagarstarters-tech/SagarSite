<?php
declare(strict_types=1);

namespace Admin\SocialMedia\Services;

use DbConnection;
use PDO;

/**
 * Class TemplateEngine
 * Replaces variables and conditional blocks in templates with product data.
 */
class TemplateEngine {

    /**
     * Renders a template with provided product data and options.
     *
     * @param string $template
     * @param array $product
     * @param array $options
     * @return string
     */
    public function render(string $template, array $product, array $options = []): string {
        // Evaluate conditionals
        $template = preg_replace_callback('/{if_discount}(.*?){(?:\\/if_discount)}/s', function($matches) use ($product) {
            return (isset($product['sale_price']) && $product['sale_price'] < $product['regular_price']) ? $matches[1] : '';
        }, $template);
        
        $template = preg_replace_callback('/{if_brand}(.*?){(?:\\/if_brand)}/s', function($matches) use ($product) {
            return !empty($product['brand']) ? $matches[1] : '';
        }, $template);
        
        $template = preg_replace_callback('/{if_description}(.*?){(?:\\/if_description)}/s', function($matches) use ($product) {
            return !empty($product['description']) || !empty($product['short_description']) ? $matches[1] : '';
        }, $template);

        // Fetch extra info if needed
        $categoryName = $this->getCategoryName((int)($product['category_id'] ?? 0));
        
        $siteUrl = defined('SITE_URL') ? SITE_URL : 'http://localhost';
        $productUrl = $siteUrl . '/product/' . ($product['slug'] ?? '');
        $storeName = 'Our Store'; // Could fetch from settings
        
        $discountAmount = 0;
        $discountPercent = 0;
        if (isset($product['sale_price'], $product['regular_price']) && $product['regular_price'] > 0) {
            $discountAmount = $product['regular_price'] - $product['sale_price'];
            $discountPercent = round(($discountAmount / $product['regular_price']) * 100);
        }

        $replacements = [
            '{product_name}' => $product['name'] ?? '',
            '{price}' => $product['price'] ?? ($product['sale_price'] ?? $product['regular_price'] ?? ''),
            '{regular_price}' => $product['regular_price'] ?? '',
            '{sale_price}' => $product['sale_price'] ?? '',
            '{discount_percent}' => $discountPercent,
            '{discount_amount}' => $discountAmount,
            '{short_description}' => $product['short_description'] ?? '',
            '{description}' => $product['description'] ?? '',
            '{product_url}' => $productUrl,
            '{store_name}' => $storeName,
            '{brand}' => $product['brand'] ?? '',
            '{category}' => $categoryName,
            '{sku}' => $product['sku'] ?? '',
            '{hashtags}' => $options['hashtags'] ?? '',
            '{cta}' => $options['cta'] ?? 'Shop Now',
            '{cta_text}' => $options['cta_text'] ?? 'Click here to buy'
        ];

        return strtr($template, $replacements);
    }

    /**
     * Gets available variables with descriptions.
     *
     * @return array
     */
    public function getAvailableVariables(): array {
        return [
            '{product_name}' => 'The name of the product',
            '{price}' => 'The current price of the product',
            '{regular_price}' => 'The regular price of the product',
            '{sale_price}' => 'The sale price of the product',
            '{discount_percent}' => 'The discount percentage',
            '{discount_amount}' => 'The amount discounted',
            '{short_description}' => 'Short description of the product',
            '{description}' => 'Full description of the product',
            '{product_url}' => 'Direct URL to the product',
            '{store_name}' => 'Name of the store',
            '{brand}' => 'Product brand',
            '{category}' => 'Product category',
            '{sku}' => 'Product SKU',
            '{hashtags}' => 'Relevant hashtags',
            '{cta}' => 'Call to action',
            '{cta_text}' => 'Call to action text',
        ];
    }

    /**
     * Truncates content for platform specific limits.
     *
     * @param string $content
     * @param string $platform
     * @return string
     */
    public function truncateForPlatform(string $content, string $platform): string {
        $limits = [
            'twitter' => 280,
            'linkedin' => 3000,
            'facebook' => 63206,
            'instagram' => 2200,
            'telegram' => 4096,
            'pinterest' => 500
        ];
        
        $platform = strtolower($platform);
        $limit = $limits[$platform] ?? null;
        
        if ($limit && mb_strlen($content) > $limit) {
            return mb_substr($content, 0, $limit - 3) . '...';
        }
        
        return $content;
    }

    /**
     * Helper to fetch category name from DB.
     *
     * @param int $categoryId
     * @return string
     */
    private function getCategoryName(int $categoryId): string {
        if ($categoryId <= 0) return '';
        try {
            $db = DbConnection::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT name FROM categories WHERE id = ?");
            $stmt->execute([$categoryId]);
            return $stmt->fetchColumn() ?: '';
        } catch (\Exception $e) {
            return '';
        }
    }
}
