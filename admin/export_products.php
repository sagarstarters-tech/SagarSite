<?php
include_once __DIR__ . '/../includes/session_setup.php';
require_once '../includes/db_connect.php';
require_once 'core/AuthMiddleware.php';

// Ensure the user is logged in as an admin
AuthMiddleware::check($conn);

// Fetch all products with category names and SEO info
$sql = "
    SELECT 
        p.*, 
        c.name as category_name,
        s.meta_title,
        s.meta_description as seo_desc,
        s.meta_keywords
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN seo_metadata s ON s.entity_type = 'product' AND s.entity_id = p.id
    ORDER BY p.id ASC
";

$result = $conn->query($sql);

if (!$result) {
    die("Error fetching products: " . $conn->error);
}

// Fetch additional product images
$images_q = $conn->query("SELECT product_id, image FROM product_images");
$extra_images = [];
if ($images_q) {
    while($img = $images_q->fetch_assoc()) {
        $extra_images[$img['product_id']][] = $img['image'];
    }
}

// Setup headers for CSV download
$filename = "products-export-" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Add UTF-8 BOM for full Excel compatibility so it renders properly as a spreadsheet
fputs($output, "\xEF\xBB\xBF");

// Define CSV headers
$headers = [
    'Product ID',
    'Product Name',
    'Slug',
    'SKU',
    'Category',
    'Subcategory',
    'Brand',
    'Product Description',
    'Short Description',
    'Price',
    'Discount Price',
    'Stock Quantity',
    'Stock Status',
    'Thumbnail Image',
    'Additional Images (Comma Separated)',
    'Tags',
    'Status (Active/Inactive)',
    'SEO Title',
    'SEO Description',
    'SEO Keywords',
    'Created Date'
];
fputcsv($output, $headers);

while ($row = $result->fetch_assoc()) {
    
    // Format Stock Status
    $stock_status = ($row['stock'] > 0) ? 'In Stock' : 'Out of Stock';
    
    // Get additional images
    $product_gallery = isset($extra_images[$row['id']]) ? implode(',', $extra_images[$row['id']]) : '';

    $csvRow = [
        $row['id'],
        $row['name'],
        $row['slug'],
        trim($row['sku'] ?? ''), // SKU
        trim($row['category_name'] ?? 'Uncategorized'),
        '', // Subcategory (Not in DB)
        trim($row['brand'] ?? ''), // Brand
        trim($row['description']),
        trim($row['short_description'] ?? ''), // Short Description
        $row['price'],
        $row['sale_price'] ?? '', // Discount Price
        $row['stock'],
        $stock_status,
        trim($row['image'] ?? ''),
        $product_gallery,
        trim($row['meta_keywords'] ?? ''), // Tags mapped to meta keywords
        'Active', // Status (Not in DB explicitly, assume active)
        trim($row['meta_title'] ?? ''),
        trim($row['seo_desc'] ?? $row['meta_description'] ?? ''), // Prefer SEO metadata explicitly set for the product, fallback to product basic meta_desc
        trim($row['meta_keywords'] ?? ''),
        $row['created_at']
    ];
    
    fputcsv($output, $csvRow);
}

fclose($output);
exit;
?>
