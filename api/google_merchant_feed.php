<?php
/**
 * Google Merchant Center RSS 2.0 Product Feed Generator
 * Location: /api/google_merchant_feed.php
 */

require_once __DIR__ . '/../includes/db_connect.php';

header('Content-Type: application/xml; charset=utf-8');

// Load settings
$gmc_enabled       = $global_settings['gmc_enabled'] ?? '1';
$gmc_default_brand = $global_settings['gmc_default_brand'] ?? ($global_settings['site_name'] ?? "Sagar Starter's");
$gmc_condition     = $global_settings['gmc_condition'] ?? 'new';
$gmc_currency      = !empty($global_settings['gmc_currency']) ? $global_settings['gmc_currency'] : 'INR';
$site_name         = $global_settings['site_name'] ?? "Sagar Starter's";
$site_url          = rtrim(SITE_URL, '/');

if ($gmc_enabled !== '1') {
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<rss version="2.0"><channel><title>' . htmlspecialchars($site_name) . ' Feed Disabled</title><description>Google Merchant Center Feed is currently disabled in the admin panel.</description></channel></rss>';
    exit;
}

// Helper to append cache buster for instant Googlebot-Image re-fetch
function format_gmc_image_url($url) {
    if (empty($url)) return $url;
    $sep = (strpos($url, '?') !== false) ? '&' : '?';
    return $url . $sep . 'gmc_v=2';
}

// Fetch all active products
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        ORDER BY p.id DESC";
$result = $conn->query($sql);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">
  <channel>
    <title><?php echo htmlspecialchars($site_name); ?></title>
    <link><?php echo htmlspecialchars($site_url); ?></link>
    <description>Product feed for Google Merchant Center - <?php echo htmlspecialchars($site_name); ?></description>

    <?php if ($result && $result->num_rows > 0): ?>
      <?php while ($product = $result->fetch_assoc()): ?>
        <?php
          $prod_id = $product['id'];
          $title   = trim($product['name']);
          
          // Description fallback
          $desc_raw = !empty($product['description']) ? $product['description'] : (!empty($product['short_description']) ? $product['short_description'] : $title);
          $desc     = strip_tags($desc_raw);
          if (mb_strlen($desc) > 5000) {
              $desc = mb_substr($desc, 0, 4997) . '...';
          }
          
          // Product URL
          $product_url = !empty($product['slug']) ? $site_url . '/product.php?slug=' . urlencode($product['slug']) : $site_url . '/product.php?id=' . $prod_id;
          
          // Image URL with cache buster
          $raw_image_url = resolve_product_image_url($product['image'] ?? '', $conn, $prod_id, $title);
          $image_url     = format_gmc_image_url($raw_image_url);
          
          // Additional Gallery Images
          $additional_images = [];
          $gq = $conn->query("SELECT image FROM product_images WHERE product_id = {$prod_id} ORDER BY position ASC, id ASC LIMIT 10");
          if ($gq) {
              while ($gRow = $gq->fetch_assoc()) {
                  $add_img = resolve_product_image_url($gRow['image'] ?? '', $conn, $prod_id, $title);
                  if (!empty($add_img) && $add_img !== $raw_image_url) {
                      $formatted_add = format_gmc_image_url($add_img);
                      if (!in_array($formatted_add, $additional_images)) {
                          $additional_images[] = $formatted_add;
                      }
                  }
              }
          }

          // Price calculation: Regular Price -> <g:price>, Sale Price -> <g:sale_price>
          $reg_price_val = (!empty($product['regular_price']) && (float)$product['regular_price'] > 0)
                            ? (float)$product['regular_price']
                            : ((!empty($product['price']) && (float)$product['price'] > 0) ? (float)$product['price'] : 0);
          $price_formatted = number_format((float)$reg_price_val, 2, '.', '') . ' ' . strtoupper($gmc_currency);

          $sale_price_val = (!empty($product['sale_price']) && (float)$product['sale_price'] > 0) ? (float)$product['sale_price'] : 0;
          $has_sale_price = ($sale_price_val > 0 && $sale_price_val < $reg_price_val);
          $sale_price_formatted = $has_sale_price ? number_format((float)$sale_price_val, 2, '.', '') . ' ' . strtoupper($gmc_currency) : null;

          // Availability
          $stock = (int)($product['stock'] ?? 1);
          $availability = ($stock > 0) ? 'in_stock' : 'out_of_stock';

          // Brand
          $brand = !empty($product['brand']) ? $product['brand'] : $gmc_default_brand;

          // Condition
          $condition = !empty($product['condition_type']) ? $product['condition_type'] : $gmc_condition;

          // Category
          $category = !empty($product['google_product_category']) ? $product['google_product_category'] : (!empty($product['category_name']) ? $product['category_name'] : '');
        ?>
        <item>
          <g:id><?php echo htmlspecialchars(!empty($product['sku']) ? $product['sku'] : 'PROD-' . $prod_id); ?></g:id>
          <g:title><![CDATA[<?php echo $title; ?>]]></g:title>
          <g:description><![CDATA[<?php echo $desc; ?>]]></g:description>
          <g:link><?php echo htmlspecialchars($product_url); ?></g:link>
          <g:image_link><?php echo htmlspecialchars($image_url); ?></g:image_link>
          <?php foreach ($additional_images as $add_img_link): ?>
            <g:additional_image_link><?php echo htmlspecialchars($add_img_link); ?></g:additional_image_link>
          <?php endforeach; ?>
          <g:condition><?php echo htmlspecialchars($condition); ?></g:condition>
          <g:availability><?php echo htmlspecialchars($availability); ?></g:availability>
          <g:price><?php echo htmlspecialchars($price_formatted); ?></g:price>
          <?php if (!empty($sale_price_formatted)): ?>
          <g:sale_price><?php echo htmlspecialchars($sale_price_formatted); ?></g:sale_price>
          <?php endif; ?>
          <g:brand><![CDATA[<?php echo $brand; ?>]]></g:brand>
          <?php if (!empty($category)): ?>
            <g:google_product_category><![CDATA[<?php echo $category; ?>]]></g:google_product_category>
          <?php endif; ?>
          <?php if (!empty($product['gtin'])): ?>
            <g:gtin><?php echo htmlspecialchars($product['gtin']); ?></g:gtin>
          <?php elseif (!empty($product['mpn'])): ?>
            <g:mpn><?php echo htmlspecialchars($product['mpn']); ?></g:mpn>
          <?php else: ?>
            <g:identifier_exists>no</g:identifier_exists>
          <?php endif; ?>
        </item>
      <?php endwhile; ?>
    <?php endif; ?>
  </channel>
</rss>
