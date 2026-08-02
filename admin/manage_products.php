<?php
// Handle AJAX actions before HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['action'] ?? ''), ['delete_gallery_image', 'toggle_trending', 'update_gallery_order'])) {
    include_once __DIR__ . '/../includes/session_setup.php';
    include_once __DIR__ . '/../includes/db_connect.php';
    
    header('Content-Type: application/json');
    
    // Auth Check
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }

    $action = $_POST['action'];
    if ($action === 'delete_gallery_image') {
        $img_id = intval($_POST['image_id']);
        $img_q = $conn->query("SELECT image FROM product_images WHERE id=$img_id")->fetch_assoc();
        if ($img_q && $img_q['image'] && file_exists('../assets/images/'.$img_q['image'])) {
            unlink('../assets/images/'.$img_q['image']);
        }
        if ($conn->query("DELETE FROM product_images WHERE id=$img_id")) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        exit;
    } elseif ($action === 'toggle_trending') {
        $id = intval($_POST['id']);
        if ($conn->query("UPDATE products SET is_trending = 1 - is_trending WHERE id=$id")) {
            $updated = $conn->query("SELECT is_trending FROM products WHERE id=$id")->fetch_assoc();
            echo json_encode(['success' => true, 'is_trending' => $updated['is_trending']]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        exit;
    } elseif ($action === 'update_gallery_order') {
        $order = $_POST['order'] ?? [];
        if (!empty($order)) {
            foreach ($order as $pos => $img_id) {
                $pos = intval($pos);
                $img_id = intval($img_id);
                $conn->query("UPDATE product_images SET position = $pos WHERE id = $img_id");
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No order provided']);
        }
        exit;
    }
}

include 'admin_header.php';
require_once '../includes/SeoRepository.php';
$seoRepo = new SeoRepository($conn);

// Auto-migrate: Ensure required columns exist (Safe for live server)
$check_col = $conn->query("SHOW COLUMNS FROM products LIKE 'is_trending'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE products ADD COLUMN is_trending TINYINT(1) DEFAULT 0 AFTER cod_available");
}
$check_features = $conn->query("SHOW COLUMNS FROM products LIKE 'features'");
if ($check_features && $check_features->num_rows == 0) {
    $conn->query("ALTER TABLE products ADD COLUMN features TEXT AFTER description");
}
// COD charge per-product column
$check_cod_charge = $conn->query("SHOW COLUMNS FROM products LIKE 'cod_charge'");
if ($check_cod_charge && $check_cod_charge->num_rows == 0) {
    $conn->query("ALTER TABLE products ADD COLUMN cod_charge DECIMAL(10,2) DEFAULT NULL COMMENT 'Per-product COD charge (NULL = use global default)'");
}

// Migration for Gallery Position
$check_pos = $conn->query("SHOW COLUMNS FROM product_images LIKE 'position'");
if ($check_pos && $check_pos->num_rows == 0) {
    $conn->query("ALTER TABLE product_images ADD COLUMN position INT DEFAULT 0");
}

// Note: Removed aggressive auto-delete of product_images rows on every page load.
// That was deleting legitimate gallery entries. Placeholder filtering is now done only at display time.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = $conn->real_escape_string($_POST['name']);
        $desc = $conn->real_escape_string($_POST['description']);
        $features = $conn->real_escape_string($_POST['features'] ?? '');
        $short_desc = $conn->real_escape_string($_POST['short_description'] ?? '');
        $cat_id = intval($_POST['category_id']);
        $regular_price = floatval($_POST['regular_price']);
        $sale_price = floatval($_POST['sale_price'] ?? 0);
        $price = ($sale_price > 0) ? $sale_price : $regular_price;
        $sku = $conn->real_escape_string($_POST['sku'] ?? '');
        $brand = $conn->real_escape_string($_POST['brand'] ?? '');
        $stock = intval($_POST['stock']);
        $shipping_cost = floatval($_POST['shipping_cost'] ?? 0);
        $weight = floatval($_POST['weight'] ?? 0);
        $length = floatval($_POST['length'] ?? 0);
        $width = floatval($_POST['width'] ?? 0);
        $height = floatval($_POST['height'] ?? 0);
        $cod_available = isset($_POST['cod_available']) ? 1 : 0;
        $is_trending = isset($_POST['is_trending']) ? 1 : 0;
        $cod_charge_raw = $_POST['cod_charge'] ?? '';
        $cod_charge = ($cod_charge_raw !== '' && $cod_charge_raw !== null) ? floatval($cod_charge_raw) : null;
        $cod_charge_sql = ($cod_charge !== null) ? $cod_charge : 'NULL';



        $meta_desc = $conn->real_escape_string($_POST['meta_description'] ?? '');
        $image_fit = $conn->real_escape_string($_POST['image_fit'] ?? 'contain');
        $slug = $conn->real_escape_string(strtolower(str_replace(' ', '-', preg_replace('/[^a-z0-9-]+/', '-', $_POST['slug'] ?? ''))));
        if (empty($slug)) $slug = time() . '-' . substr(preg_replace('/[^a-z0-9-]+/', '-', strtolower($name)), 0, 50);
        
        $product_type = $conn->real_escape_string($_POST['product_type'] ?? 'physical');
        $download_url = $conn->real_escape_string($_POST['download_url'] ?? '');
        $download_limit = !empty($_POST['download_limit']) ? intval($_POST['download_limit']) : "NULL";
        $download_expiry = !empty($_POST['download_expiry_days']) ? intval($_POST['download_expiry_days']) : "NULL";
        
        $download_file = '';
        if (isset($_FILES['download_file']) && $_FILES['download_file']['error'] === 0) {
            $ext = pathinfo($_FILES['download_file']['name'], PATHINFO_EXTENSION);
            $download_file = 'dl_' . uniqid() . '.' . $ext;
            if (!is_dir('../uploads/downloads')) {
                mkdir('../uploads/downloads', 0755, true);
            }
            if (!file_exists('../uploads/downloads/.htaccess')) {
                file_put_contents('../uploads/downloads/.htaccess', "order deny,allow\ndeny from all");
            }
            move_uploaded_file($_FILES['download_file']['tmp_name'], '../uploads/downloads/' . $download_file);
        }

        
        $image = null;
        if (isset($_FILES['image'])) {
            if ($_FILES['image']['error'] === 0) {
                $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $image = uniqid() . '.' . $ext;
                if (!move_uploaded_file($_FILES['image']['tmp_name'], '../assets/images/' . $image)) {
                    $error = "Failed to move uploaded main image to assets/images/. Please check directory permissions.";
                    $image = null;
                }
            } elseif ($_FILES['image']['error'] !== 4) {
                $error = "Failed to upload main image. PHP Error code: " . $_FILES['image']['error'];
            }
        }

        // Use NULL in SQL when no image is uploaded, not empty string
        $image_sql = ($image !== null) ? "'" . $conn->real_escape_string($image) . "'" : "NULL";
        $sql = "INSERT INTO products (name, slug, short_description, description, features, meta_description, category_id, product_type, download_file, download_url, download_limit, download_expiry_days, regular_price, sale_price, price, sku, brand, stock, shipping_cost, weight, length, width, height, cod_available, is_trending, cod_charge, image, image_fit) VALUES ('$name', '$slug', '$short_desc', '$desc', '$features', '$meta_desc', $cat_id, '$product_type', '$download_file', '$download_url', $download_limit, $download_expiry, $regular_price, $sale_price, $price, '$sku', '$brand', $stock, $shipping_cost, $weight, $length, $width, $height, $cod_available, $is_trending, $cod_charge_sql, $image_sql, '$image_fit')";



        if ($conn->query($sql)) {
            $product_id = $conn->insert_id;
            
            // Save SEO Metadata
            $seoRepo->saveMetadata([
                'entity_type' => 'product',
                'entity_id' => $product_id,
                'meta_title' => $_POST['seo_title'] ?? '',
                'meta_description' => $meta_desc,
                'focus_keyword' => $_POST['focus_keyword'] ?? ''
            ]);

            // Handle Gallery Uploads
            if (isset($_FILES['gallery']) && !empty($_FILES['gallery']['name'][0])) {
                $count = count($_FILES['gallery']['name']);
                for ($i = 0; $i < $count; $i++) {
                    if ($_FILES['gallery']['error'][$i] === 0) {
                        $ext = pathinfo($_FILES['gallery']['name'][$i], PATHINFO_EXTENSION);
                        $g_image = uniqid('gal_') . '.' . $ext;
                        if (move_uploaded_file($_FILES['gallery']['tmp_name'][$i], '../assets/images/' . $g_image)) {
                            $conn->query("INSERT INTO product_images (product_id, image, position) VALUES ($product_id, '$g_image', $i)");
                        }
                    }
                }
            }
            $_SESSION['flash_success'] = "Product added successfully.";
            header("Location: manage_products.php?action=list");
            exit;
        } else {
            $_SESSION['flash_error'] = "Failed to add product: " . $conn->error;
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['id']);
        $return_page = intval($_POST['current_page'] ?? 1);
        if ($return_page < 1) $return_page = 1;
        $name = $conn->real_escape_string($_POST['name']);
        $desc = $conn->real_escape_string($_POST['description']);
        $features = $conn->real_escape_string($_POST['features'] ?? '');
        $short_desc = $conn->real_escape_string($_POST['short_description'] ?? '');
        $cat_id = intval($_POST['category_id']);
        $regular_price = floatval($_POST['regular_price']);
        $sale_price = floatval($_POST['sale_price'] ?? 0);
        $price = ($sale_price > 0) ? $sale_price : $regular_price;
        $sku = $conn->real_escape_string($_POST['sku'] ?? '');
        $brand = $conn->real_escape_string($_POST['brand'] ?? '');
        $stock = intval($_POST['stock']);
        $shipping_cost = floatval($_POST['shipping_cost'] ?? 0);
        $weight = floatval($_POST['weight'] ?? 0);
        $length = floatval($_POST['length'] ?? 0);
        $width = floatval($_POST['width'] ?? 0);
        $height = floatval($_POST['height'] ?? 0);
        $cod_available = isset($_POST['cod_available']) ? 1 : 0;
        $is_trending = isset($_POST['is_trending']) ? 1 : 0;
        $cod_charge_raw = $_POST['cod_charge'] ?? '';
        $cod_charge = ($cod_charge_raw !== '' && $cod_charge_raw !== null) ? floatval($cod_charge_raw) : null;
        $cod_charge_sql = ($cod_charge !== null) ? $cod_charge : 'NULL';



        $meta_desc = $conn->real_escape_string($_POST['meta_description'] ?? '');
        $image_fit = $conn->real_escape_string($_POST['image_fit'] ?? 'contain');
        $slug = $conn->real_escape_string(strtolower(str_replace(' ', '-', preg_replace('/[^a-z0-9-]+/', '-', $_POST['slug'] ?? ''))));
        if (empty($slug)) {
            // Fallback: auto-generate slug from product name if admin cleared it
            $existing = $conn->query("SELECT slug FROM products WHERE id=$id")->fetch_assoc();
            $slug = !empty($existing['slug']) ? $existing['slug'] : time() . '-' . substr(preg_replace('/[^a-z0-9-]+/', '-', strtolower($name)), 0, 50);
        }
        
        $product_type = $conn->real_escape_string($_POST['product_type'] ?? 'physical');
        $download_url = $conn->real_escape_string($_POST['download_url'] ?? '');
        $download_limit = !empty($_POST['download_limit']) ? intval($_POST['download_limit']) : "NULL";
        $download_expiry = !empty($_POST['download_expiry_days']) ? intval($_POST['download_expiry_days']) : "NULL";
        
        $dl_file_query = "";
        if (isset($_FILES['download_file']) && $_FILES['download_file']['error'] === 0) {
            $ext = pathinfo($_FILES['download_file']['name'], PATHINFO_EXTENSION);
            $download_file = 'dl_' . uniqid() . '.' . $ext;
            if (!is_dir('../uploads/downloads')) {
                mkdir('../uploads/downloads', 0755, true);
            }
            if (move_uploaded_file($_FILES['download_file']['tmp_name'], '../uploads/downloads/' . $download_file)) {
                 $old_dl_q = $conn->query("SELECT download_file FROM products WHERE id=$id")->fetch_assoc();
                 if ($old_dl_q && $old_dl_q['download_file'] && file_exists('../uploads/downloads/'.$old_dl_q['download_file'])) {
                     unlink('../uploads/downloads/'.$old_dl_q['download_file']);
                 }
                 $dl_file_query = ", download_file='$download_file'";
            }
        }

        $image_query = "";
        if (isset($_FILES['image'])) {
            if ($_FILES['image']['error'] === 0) {
                $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $image = uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], '../assets/images/' . $image)) {
                     $img_q = $conn->query("SELECT image FROM products WHERE id=$id")->fetch_assoc();
                     if ($img_q && $img_q['image'] && file_exists('../assets/images/'.$img_q['image'])) {
                         unlink('../assets/images/'.$img_q['image']);
                     }
                     $image_query = ", image='$image'";
                } else {
                    $_SESSION['flash_error'] = "Failed to move uploaded main image to assets/images/. Please check directory permissions.";
                }
            } elseif ($_FILES['image']['error'] !== 4) {
                $_SESSION['flash_error'] = "Failed to upload main image. PHP Error code: " . $_FILES['image']['error'];
            }
        }

        $sql = "UPDATE products SET name='$name', slug='$slug', short_description='$short_desc', description='$desc', features='$features', meta_description='$meta_desc', category_id=$cat_id, product_type='$product_type', download_url='$download_url', download_limit=$download_limit, download_expiry_days=$download_expiry, regular_price=$regular_price, sale_price=$sale_price, price=$price, sku='$sku', brand='$brand', stock=$stock, shipping_cost=$shipping_cost, weight=$weight, length=$length, width=$width, height=$height, cod_available=$cod_available, is_trending=$is_trending, cod_charge=$cod_charge_sql, image_fit='$image_fit' $image_query $dl_file_query WHERE id=$id";




        if ($conn->query($sql)) {
            // Save SEO Metadata
            $seoRepo->saveMetadata([
                'entity_type' => 'product',
                'entity_id' => $id,
                'meta_title' => $_POST['seo_title'] ?? '',
                'meta_description' => $meta_desc,
                'focus_keyword' => $_POST['focus_keyword'] ?? ''
            ]);

            // Handle Gallery Uploads (Appends to existing)
            if (isset($_FILES['gallery']) && !empty($_FILES['gallery']['name'][0])) {
                $count = count($_FILES['gallery']['name']);
                for ($i = 0; $i < $count; $i++) {
                    if ($_FILES['gallery']['error'][$i] === 0) {
                        $ext = pathinfo($_FILES['gallery']['name'][$i], PATHINFO_EXTENSION);
                        $g_image = uniqid('gal_') . '.' . $ext;
                        if (move_uploaded_file($_FILES['gallery']['tmp_name'][$i], '../assets/images/' . $g_image)) {
                            $max_pos_q = $conn->query("SELECT MAX(position) as max_p FROM product_images WHERE product_id=$id");
                            $max_p = ($max_pos_q && $max_pos_q->num_rows > 0) ? intval($max_pos_q->fetch_assoc()['max_p']) + 1 : 0;
                            $conn->query("INSERT INTO product_images (product_id, image, position) VALUES ($id, '$g_image', $max_p + $i)");
                        }
                    }
                }
            }
            $_SESSION['flash_success'] = "Product updated successfully.";
            header("Location: manage_products.php?page=" . $return_page);
            exit;
        } else {
            $_SESSION['flash_error'] = "Failed to update product: " . $conn->error;
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        
        // Remove main image
        $img_q = $conn->query("SELECT image FROM products WHERE id=$id")->fetch_assoc();
        if ($img_q && $img_q['image'] && file_exists('../assets/images/'.$img_q['image'])) {
            unlink('../assets/images/'.$img_q['image']);
        }
        
        // Remove gallery images from disk
        $gal_img_q = $conn->query("SELECT image FROM product_images WHERE product_id=$id");
        if ($gal_img_q) {
            while ($g = $gal_img_q->fetch_assoc()) {
                if (file_exists('../assets/images/'.$g['image'])) {
                    unlink('../assets/images/'.$g['image']);
                }
            }
        }
        
        $conn->query("DELETE FROM products WHERE id=$id");
        $_SESSION['flash_success'] = "Product deleted successfully.";
        header("Location: manage_products.php?action=list");
        exit;
    }
}

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Flash messages
$success = $_SESSION['flash_success'] ?? null;
$error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$products = $conn->query("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC LIMIT $limit OFFSET $offset");
$total_products = $conn->query("SELECT COUNT(*) as c FROM products")->fetch_assoc()['c'];
$total_pages = ceil($total_products / $limit);

$cats = $conn->query("SELECT * FROM categories");
// Make sure categories exist to allow product addition
$has_cats = $cats && $cats->num_rows > 0;

// Fetch all gallery images and group by product (Sorted by position)
$product_images = [];
$pi_q = $conn->query("SELECT * FROM product_images ORDER BY position ASC, id ASC");
if ($pi_q) {
    $dummies = ['placeholder.svg', 'placeholder.png', 'no-image.png', 'no-image.jpg', 'null', 'undefined'];
    while($pi = $pi_q->fetch_assoc()) {
        $img_file = strtolower(basename(trim($pi['image'] ?? '')));
        if (empty($img_file) || in_array($img_file, $dummies)) {
            continue; // Skip dummy placeholder entries
        }
        $pi['image_url'] = resolve_product_image_url($pi['image']);
        $product_images[$pi['product_id']][] = $pi;
    }
}

// Fetch SEO metadata for all products
$product_seo = [];
$seo_q = $conn->query("SELECT * FROM seo_metadata WHERE entity_type='product'");
if ($seo_q) {
    while ($s = $seo_q->fetch_assoc()) {
        $product_seo[$s['entity_id']] = $s;
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Manage Products</h4>
    <div class="d-flex gap-2">
        <a href="export_products.php" class="btn btn-success btn-custom px-3" title="Export Products to CSV"><i class="fas fa-file-export me-2"></i>Export</a>
        <button class="btn btn-info btn-custom px-3 text-white" data-mdb-toggle="modal" data-mdb-target="#importProductModal" title="Import Products"><i class="fas fa-file-import me-2"></i>Import</button>
        <button class="btn btn-primary btn-custom px-3" data-mdb-toggle="modal" data-mdb-target="#addProductModal" <?php echo !$has_cats ? 'disabled' : ''; ?>><i class="fas fa-plus me-2"></i>Add Product</button>
    </div>
</div>

<?php if(!$has_cats): ?>
    <div class="alert alert-warning">You must create at least one category before adding products.</div>
<?php endif; ?>

<?php if(isset($success)): ?>
    <div class="alert alert-success py-2"><?php echo $success; ?></div>
<?php endif; ?>

<?php if(isset($error)): ?>
    <div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if(isset($_SESSION['import_success'])): ?>
    <div class="alert alert-success py-3 d-flex align-items-center">
        <i class="fas fa-check-circle fa-2x me-3"></i>
        <div>
            <h6 class="fw-bold mb-1">Import Report</h6>
            <?php echo $_SESSION['import_success']; ?>
        </div>
    </div>
    <?php unset($_SESSION['import_success']); ?>
<?php endif; ?>

<?php if(isset($_SESSION['import_errors']) && !empty($_SESSION['import_errors'])): ?>
    <div class="alert alert-warning py-3">
        <h6 class="fw-bold text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Import Discrepancies (<?php echo count($_SESSION['import_errors']); ?> issues found)</h6>
        <div style="max-height: 150px; overflow-y: auto; font-size: 0.9em;" class="bg-light p-2 rounded border mt-2">
            <ul class="mb-0 text-muted ps-3">
                <?php foreach($_SESSION['import_errors'] as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php unset($_SESSION['import_errors']); ?>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Product</th>
                        <th>Category</th>
                        <th>Trending</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th class="pe-4 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($products && $products->num_rows > 0): ?>
                        <?php while($p = $products->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo htmlspecialchars(resolve_product_image_url($p['image'] ?? '', $conn, $p['id'])); ?>" onerror="this.onerror=null; this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';" class="rounded" style="width: 50px; height: 50px; object-fit: <?php echo htmlspecialchars($p['image_fit'] ?? 'contain'); ?>;">
                                    <div class="ms-3">
                                        <h6 class="fw-bold mb-0 text-truncate" style="max-width: 200px;"><?php echo htmlspecialchars($p['name']); ?></h6>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-secondary text-white" style="color: #fff !important;"><?php echo htmlspecialchars($p['category_name']); ?></span>
                                <?php if($p['product_type'] === 'virtual'): ?>
                                    <span class="badge bg-info ms-1 text-white" style="color: #fff !important;">Virtual</span>
                                <?php elseif($p['product_type'] === 'downloadable'): ?>
                                    <span class="badge bg-primary ms-1 text-white" style="color: #fff !important;">Downloadable</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="trending-toggle" data-id="<?php echo $p['id']; ?>" style="cursor: pointer;" title="Click to toggle Trending status">
                                    <?php if(isset($p['is_trending']) && $p['is_trending']): ?>
                                        <span class="badge bg-warning text-dark"><i class="fas fa-star me-1"></i>Trending</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted"><i class="far fa-star me-1"></i>Regular</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="fw-bold"><?php echo $global_currency; ?><?php echo number_format($p['price'], 2); ?></td>
                            <td>
                                <?php if($p['product_type'] !== 'physical'): ?>
                                    <span class="text-muted small">N/A</span>
                                <?php elseif($p['stock'] > 10): ?>
                                    <span class="text-success fw-bold"><?php echo $p['stock']; ?></span>
                                <?php elseif($p['stock'] > 0): ?>
                                    <span class="text-warning fw-bold"><?php echo $p['stock']; ?></span>
                                <?php else: ?>
                                    <span class="badge bg-danger text-white" style="color: #fff !important;">Out of Stock</span>
                                <?php endif; ?>
                            </td>
                            <td class="pe-4">
                                <div class="action-btns">
                                    <button class="btn btn-primary btn-sm btn-custom edit-product-btn" 
                                        data-id="<?php echo $p['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($p['name']); ?>"
                                        data-slug="<?php echo htmlspecialchars($p['slug'] ?? ''); ?>"
                                        data-category="<?php echo $p['category_id']; ?>"
                                        data-product-type="<?php echo $p['product_type']; ?>"
                                        data-download-file="<?php echo htmlspecialchars($p['download_file'] ?? ''); ?>"
                                        data-download-url="<?php echo htmlspecialchars($p['download_url'] ?? ''); ?>"
                                        data-download-limit="<?php echo $p['download_limit']; ?>"
                                        data-download-expiry="<?php echo $p['download_expiry_days']; ?>"
                                        data-regular-price="<?php echo $p['regular_price']; ?>"

                                        data-sale-price="<?php echo $p['sale_price']; ?>"
                                        data-sku="<?php echo htmlspecialchars($p['sku'] ?? ''); ?>"
                                        data-brand="<?php echo htmlspecialchars($p['brand'] ?? ''); ?>"
                                        data-short-description="<?php echo htmlspecialchars($p['short_description'] ?? ''); ?>"
                                        data-stock="<?php echo $p['stock']; ?>"
                                        data-shipping-cost="<?php echo $p['shipping_cost']; ?>"
                                        data-weight="<?php echo $p['weight']; ?>"
                                        data-length="<?php echo $p['length']; ?>"
                                        data-width="<?php echo $p['width']; ?>"
                                        data-height="<?php echo $p['height']; ?>"
                                        data-cod-available="<?php echo $p['cod_available']; ?>"
                                        data-cod-charge="<?php echo $p['cod_charge'] ?? ''; ?>"
                                        data-is-trending="<?php echo $p['is_trending'] ?? 0; ?>"
                                        data-features="<?php echo htmlspecialchars($p['features'] ?? ''); ?>"
                                        data-image-fit="<?php echo htmlspecialchars($p['image_fit'] ?? 'cover'); ?>"



                                        data-gallery="<?php echo htmlspecialchars(json_encode($product_images[$p['id']] ?? [])); ?>"
                                        data-description="<?php echo htmlspecialchars($p['description']); ?>"
                                        data-meta-description="<?php echo htmlspecialchars($p['meta_description'] ?? ''); ?>"
                                        data-seo-title="<?php echo htmlspecialchars($product_seo[$p['id']]['meta_title'] ?? ''); ?>"
                                        data-focus-keyword="<?php echo htmlspecialchars($product_seo[$p['id']]['focus_keyword'] ?? ''); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="m-0 p-0" onsubmit="return confirm('Delete this product?');">
    <?php echo csrf_input(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm btn-custom"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No products found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($total_pages > 1): ?>
        <div class="p-3 border-top">
            <nav>
                <ul class="pagination justify-content-center mb-0">
                    <?php for($i=1; $i<=$total_pages; $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content rounded-4 border-0">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="editProductModalLabel">Edit Product</h5>
        <button type="button" class="btn-close" data-mdb-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
    <?php echo csrf_input(); ?>
        <div class="modal-body p-4">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_p_id">
            <input type="hidden" name="current_page" id="edit_current_page" value="1">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Product Name</label>
                    <input type="text" name="name" id="edit_p_name" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Slug (URL)</label>
                    <input type="text" name="slug" id="edit_p_slug" class="form-control" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Category</label>
                    <select name="category_id" id="edit_p_category" class="form-select form-control" required>
                        <?php 
                        $cats->data_seek(0);
                        while($c = $cats->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Product Type</label>
                    <select name="product_type" id="edit_p_product_type" class="form-select form-control" onchange="toggleProductFields('edit')">
                        <option value="physical">Physical Product</option>
                        <option value="virtual">Virtual Product</option>
                        <option value="downloadable">Downloadable Product</option>
                    </select>
                </div>
            </div>

            <!-- Downloadable Fields -->
            <div id="edit_download_fields" class="row d-none bg-light p-3 rounded mb-3 border border-primary border-opacity-25">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Update Download File</label>
                    <input type="file" name="download_file" class="form-control" accept=".zip,.pdf,.jpg,.png,.mp4,.mp3,.docx">
                    <div id="edit_current_download_file" class="small mt-1 text-primary"></div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">External Download URL</label>
                    <input type="url" name="download_url" id="edit_p_download_url" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Download Limit</label>
                    <input type="number" name="download_limit" id="edit_p_download_limit" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Download Expiry Days</label>
                    <input type="number" name="download_expiry_days" id="edit_p_download_expiry_days" class="form-control">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">SKU</label>
                    <input type="text" name="sku" id="edit_p_sku" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Brand</label>
                    <input type="text" name="brand" id="edit_p_brand" class="form-control">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Short Description</label>
                <textarea name="short_description" id="edit_p_short_description" class="form-control" rows="2"></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Description</label>
                <textarea name="description" id="edit_p_description" class="form-control" rows="4" required></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Key Features <span class="text-muted small">(One per line)</span></label>
                <textarea name="features" id="edit_p_features" class="form-control" rows="3" placeholder="Bullet points..."></textarea>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">SEO Title</label>
                    <input type="text" name="seo_title" id="edit_p_seo_title" class="form-control" placeholder="Search engine title...">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Focus Keyword</label>
                    <div class="input-group">
                        <input type="text" name="focus_keyword" id="edit_p_focus_keyword" class="form-control" placeholder="Main keyword...">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="checkSeoScore()"><i class="fas fa-magic"></i></button>
                    </div>
                    <div id="seo_feedback" class="small mt-1"></div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Meta Description (SEO)</label>
                <textarea name="meta_description" id="edit_p_meta_description" class="form-control" rows="2" placeholder="Brief SEO description..."></textarea>
            </div>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Regular Price (<?php echo $global_currency; ?>)</label>
                    <input type="number" step="0.01" name="regular_price" id="edit_p_regular_price" class="form-control" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Sale Price (<?php echo $global_currency; ?>)</label>
                    <input type="number" step="0.01" name="sale_price" id="edit_p_sale_price" class="form-control">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Stock Quantity</label>
                    <input type="number" name="stock" id="edit_p_stock" class="form-control" required>
                </div>
                <div class="col-md-3 mb-3" id="edit_shipping_cost_container">
                    <label class="form-label fw-bold">Shipping Cost (₹)</label>
                    <input type="number" step="0.01" name="shipping_cost" id="edit_p_shipping_cost" class="form-control" value="0">
                </div>

                <!-- Product Shipping Details Group -->
                <div class="col-12 mb-3 edit_shipping_details_group">
                    <div class="bg-light p-3 rounded border">
                        <h6 class="fw-bold mb-3"><i class="fas fa-box-open me-2"></i>Product Shipping Details</h6>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold small">Weight (kg)</label>
                                <input type="number" step="0.01" name="weight" id="edit_p_weight" class="form-control" value="0">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold small">Length (cm)</label>
                                <input type="number" step="0.01" name="length" id="edit_p_length" class="form-control" value="0">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold small">Width (cm)</label>
                                <input type="number" step="0.01" name="width" id="edit_p_width" class="form-control" value="0">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold small">Height (cm)</label>
                                <input type="number" step="0.01" name="height" id="edit_p_height" class="form-control" value="0">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment & Visibility Options Group -->
                <div class="col-12 mb-3 edit_payment_options_group">
                    <div class="bg-light p-3 rounded border">
                        <h6 class="fw-bold mb-3"><i class="fas fa-cog me-2"></i>Product Options</h6>
                        <div class="row">
                            <div class="col-md-6 border-end">
                                <div class="form-check form-switch container-fluid ps-0">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <label class="form-check-label fw-bold small mb-0" for="edit_p_cod_available">Allow Cash on Delivery (COD)</label>
                                        <input class="form-check-input" type="checkbox" name="cod_available" id="edit_p_cod_available">
                                    </div>
                                    <div class="form-text mt-1 small">If disabled, this product will prevent COD option at checkout.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch container-fluid ps-0">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <label class="form-check-label fw-bold small mb-0 text-warning" for="edit_p_is_trending"><i class="fas fa-star me-1"></i>Trending Product</label>
                                        <input class="form-check-input" type="checkbox" name="is_trending" id="edit_p_is_trending">
                                    </div>
                                    <div class="form-text mt-1 small">If enabled, this product will appear in the "Trending Products" section.</div>
                                </div>
                            </div>
                        </div>
                        <hr class="my-3">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small mb-1"><i class="fas fa-money-bill-wave me-1 text-success"></i>COD Charge (<?php echo $global_currency; ?>)</label>
                                <input type="number" step="0.01" min="0" name="cod_charge" id="edit_p_cod_charge" class="form-control" placeholder="Leave blank for default">
                                <div class="form-text mt-1 small">Product-specific COD charge. Leave empty to use the global default.</div>
                            </div>
                        </div>
                    </div>
                </div>



                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Product Image</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <div class="form-text mb-2">Leave empty to keep current image.</div>
                    <span class="badge bg-primary px-3 py-2 shadow-sm rounded-pill text-white" style="color: #fff !important;"><i class="fas fa-crop-alt me-2"></i>Rec. Size: 800x800px (1:1 Ratio)</span>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label fw-bold">Image Size Adjustment</label>
                    <select name="image_fit" id="edit_p_image_fit" class="form-select form-control">
                        <option value="contain">Contain (Shrink to fit entirely inside the box)</option>
                        <option value="cover">Cover (Fill the box, corners may cut off)</option>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12 mb-3 border-top pt-3">
                    <label class="form-label fw-bold">Product Gallery <small class="text-muted">(Optional, Multiple)</small></label>
                    <input type="file" id="edit_p_gallery" name="gallery[]" multiple class="form-control" accept="image/*">
                    <div class="form-text">Choose multiple files to add a gallery to this product. Note: Adding new images appends to the existing gallery. You can drag to reorder existing images.</div>
                    <div id="edit_gallery_preview" class="d-flex flex-wrap gap-2 mt-3"></div>
                    <div id="edit_gallery_preview_new" class="d-flex flex-wrap gap-2 mt-3"></div>
                </div>
            </div>
        </div>

        <div class="modal-footer border-0 pb-4 pe-4">
            <button type="button" class="btn btn-light btn-custom" data-mdb-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary btn-custom px-4">Update Product</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content rounded-4 border-0">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="addProductModalLabel">Add New Product</h5>
        <button type="button" class="btn-close" data-mdb-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
    <?php echo csrf_input(); ?>
        <div class="modal-body p-4">
            <input type="hidden" name="action" value="add">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Product Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Slug (Optional)</label>
                    <input type="text" name="slug" class="form-control" placeholder="auto-generated if empty">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Category</label>
                    <select name="category_id" class="form-select form-control" required>
                        <?php 
                        $cats->data_seek(0);
                        while($c = $cats->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Product Type</label>
                    <select name="product_type" id="add_product_type" class="form-select form-control" onchange="toggleProductFields('add')">
                        <option value="physical">Physical Product</option>
                        <option value="virtual">Virtual Product</option>
                        <option value="downloadable">Downloadable Product</option>
                    </select>
                </div>
            </div>

            <!-- Downloadable Fields (Initially Hidden) -->
            <div id="add_download_fields" class="row d-none bg-light p-3 rounded mb-3 border border-primary border-opacity-25">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Upload Download File</label>
                    <input type="file" name="download_file" class="form-control" accept=".zip,.pdf,.jpg,.png,.mp4,.mp3,.docx">
                    <small class="text-muted">Supported: zip, pdf, jpg, png, mp4, mp3, docx</small>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">External Download URL (Optional)</label>
                    <input type="url" name="download_url" class="form-control" placeholder="https://example.com/file">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Download Limit</label>
                    <input type="number" name="download_limit" class="form-control" placeholder="Unlimited if empty">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Download Expiry Days</label>
                    <input type="number" name="download_expiry_days" class="form-control" placeholder="Never expires if empty">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">SKU</label>
                    <input type="text" name="sku" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Brand</label>
                    <input type="text" name="brand" class="form-control">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Short Description</label>
                <textarea name="short_description" class="form-control" rows="2"></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Description</label>
                <textarea name="description" class="form-control" rows="4" required></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Key Features <span class="text-muted small">(One per line)</span></label>
                <textarea name="features" class="form-control" rows="3" placeholder="Bullet points..."></textarea>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">SEO Title</label>
                    <input type="text" name="seo_title" id="add_p_seo_title" class="form-control" placeholder="Search engine title...">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Focus Keyword</label>
                    <div class="input-group">
                        <input type="text" name="focus_keyword" id="add_p_focus_keyword" class="form-control" placeholder="Main keyword...">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="checkSeoScore('add')"><i class="fas fa-magic"></i></button>
                    </div>
                    <div id="add_seo_feedback" class="small mt-1"></div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Meta Description (SEO)</label>
                <textarea name="meta_description" id="add_p_meta_description" class="form-control" rows="2" placeholder="Brief SEO description..."></textarea>
            </div>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Regular Price (<?php echo $global_currency; ?>)</label>
                    <input type="number" step="0.01" name="regular_price" class="form-control" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Sale Price (<?php echo $global_currency; ?>)</label>
                    <input type="number" step="0.01" name="sale_price" class="form-control">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Stock Quantity</label>
                    <input type="number" name="stock" class="form-control" required>
                </div>
                <div class="col-md-3 mb-3" id="add_shipping_cost_container">
                    <label class="form-label fw-bold">Shipping Cost (₹)</label>
                    <input type="number" step="0.01" name="shipping_cost" class="form-control" value="0">
                </div>

                <!-- Product Shipping Details Group -->
                <div class="col-12 mb-3 add_shipping_details_group">
                    <div class="bg-light p-3 rounded border">
                        <h6 class="fw-bold mb-3"><i class="fas fa-box-open me-2"></i>Product Shipping Details</h6>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold small">Weight (kg)</label>
                                <input type="number" step="0.01" name="weight" class="form-control" value="0">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold small">Length (cm)</label>
                                <input type="number" step="0.01" name="length" class="form-control" value="0">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold small">Width (cm)</label>
                                <input type="number" step="0.01" name="width" class="form-control" value="0">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold small">Height (cm)</label>
                                <input type="number" step="0.01" name="height" class="form-control" value="0">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment & Visibility Options Group -->
                <div class="col-12 mb-3 add_payment_options_group">
                    <div class="bg-light p-3 rounded border">
                        <h6 class="fw-bold mb-3"><i class="fas fa-cog me-2"></i>Product Options</h6>
                        <div class="row">
                            <div class="col-md-6 border-end">
                                <div class="form-check form-switch container-fluid ps-0">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <label class="form-check-label fw-bold small mb-0" for="add_p_cod_available">Allow Cash on Delivery (COD)</label>
                                        <input class="form-check-input" type="checkbox" name="cod_available" id="add_p_cod_available" checked>
                                    </div>
                                    <div class="form-text mt-1 small">If disabled, this product will prevent COD option at checkout.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch container-fluid ps-0">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <label class="form-check-label fw-bold small mb-0 text-warning" for="add_p_is_trending"><i class="fas fa-star me-1"></i>Trending Product</label>
                                        <input class="form-check-input" type="checkbox" name="is_trending" id="add_p_is_trending">
                                    </div>
                                    <div class="form-text mt-1 small">If enabled, this product will appear in the "Trending Products" section.</div>
                                </div>
                            </div>
                        </div>
                        <hr class="my-3">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small mb-1"><i class="fas fa-money-bill-wave me-1 text-success"></i>COD Charge (<?php echo $global_currency; ?>)</label>
                                <input type="number" step="0.01" min="0" name="cod_charge" class="form-control" placeholder="Leave blank for default">
                                <div class="form-text mt-1 small">Product-specific COD charge. Leave empty to use the global default.</div>
                            </div>
                        </div>
                    </div>
                </div>



                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Product Image</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <span class="badge bg-primary px-3 py-2 shadow-sm rounded-pill mt-2 text-white" style="color: #fff !important;"><i class="fas fa-crop-alt me-2"></i>Rec. Size: 800x800px (1:1 Ratio)</span>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label fw-bold">Image Size Adjustment</label>
                    <select name="image_fit" class="form-select form-control">
                        <option value="contain" selected>Contain (Shrink to fit entirely inside the box)</option>
                        <option value="cover">Cover (Fill the box, corners may cut off)</option>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12 mb-3 border-top pt-3">
                    <label class="form-label fw-bold">Product Gallery <small class="text-muted">(Optional, Multiple)</small></label>
                    <input type="file" id="add_p_gallery" name="gallery[]" multiple class="form-control" accept="image/*">
                    <div id="add_gallery_preview" class="d-flex flex-wrap gap-2 mt-3"></div>
                    <div class="form-text mt-2"><i class="fas fa-info-circle me-1"></i> You can drag and drop images to reorder them before saving.</div>
                </div>
            </div>
        </div>
        <div class="modal-footer border-0 pb-4 pe-4">
            <button type="button" class="btn btn-light btn-custom" data-mdb-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary btn-custom px-4">Save Product</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Load SortableJS for Drag and Drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editBtns = document.querySelectorAll('.edit-product-btn');
    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_p_id').value = this.dataset.id;
            document.getElementById('edit_current_page').value = new URLSearchParams(window.location.search).get('page') || '1';
            document.getElementById('edit_p_name').value = this.dataset.name;
            document.getElementById('edit_p_slug').value = this.dataset.slug;
            document.getElementById('edit_p_category').value = this.dataset.category;
            document.getElementById('edit_p_product_type').value = this.dataset.productType;
            document.getElementById('edit_p_download_url').value = this.dataset.downloadUrl;
            document.getElementById('edit_p_download_limit').value = this.dataset.downloadLimit || '';
            document.getElementById('edit_p_download_expiry_days').value = this.dataset.downloadExpiry || '';
            document.getElementById('edit_p_cod_available').checked = this.dataset.codAvailable == 1;
            document.getElementById('edit_p_is_trending').checked = this.dataset.isTrending == 1;
            document.getElementById('edit_p_cod_charge').value = this.dataset.codCharge || '';
            
            const currentDlFile = document.getElementById('edit_current_download_file');
            if (this.dataset.downloadFile) {
                currentDlFile.innerHTML = 'Current file: ' + this.dataset.downloadFile;
            } else {
                currentDlFile.innerHTML = '';
            }

            // Trigger fields visibility
            toggleProductFields('edit');
            
            document.getElementById('edit_p_regular_price').value = this.dataset.regularPrice;
            document.getElementById('edit_p_sale_price').value = this.dataset.salePrice;
            document.getElementById('edit_p_sku').value = this.dataset.sku;
            document.getElementById('edit_p_brand').value = this.dataset.brand;
            document.getElementById('edit_p_short_description').value = this.dataset.shortDescription;
            document.getElementById('edit_p_stock').value = this.dataset.stock;
            document.getElementById('edit_p_shipping_cost').value = this.dataset.shippingCost;
            document.getElementById('edit_p_weight').value = this.dataset.weight || 0;
            document.getElementById('edit_p_length').value = this.dataset.length || 0;
            document.getElementById('edit_p_width').value = this.dataset.width || 0;
            document.getElementById('edit_p_height').value = this.dataset.height || 0;
            // cod_available already set on line 985, no duplicate needed



            document.getElementById('edit_p_description').value = this.dataset.description;
            document.getElementById('edit_p_features').value = this.dataset.features || '';
            document.getElementById('edit_p_meta_description').value = this.dataset.metaDescription;
            document.getElementById('edit_p_seo_title').value = this.dataset.seoTitle;
            document.getElementById('edit_p_focus_keyword').value = this.dataset.focusKeyword;
            
            const imageFitSelect = document.getElementById('edit_p_image_fit');
            if (this.dataset.imageFit && ['cover', 'contain'].includes(this.dataset.imageFit)) {
                imageFitSelect.value = this.dataset.imageFit;
            } else {
                imageFitSelect.value = 'contain';
            }
            
            const galleryDiv = document.getElementById('edit_gallery_preview');
            galleryDiv.innerHTML = '';
            let galleryHtml = '';
            if (this.dataset.gallery) {
                const gallery = JSON.parse(this.dataset.gallery);
                gallery.forEach(img => {
                    let imgUrl = img.image_url ? img.image_url : '<?php echo ASSETS_URL; ?>/images/placeholder.svg';
                    if ((!img.image_url || img.image_url.includes('placeholder.svg')) && img.image) {
                        let cleanImg = img.image.replace(/^(assets\/images\/|\/)/, '');
                        imgUrl = `<?php echo ASSETS_URL; ?>/images/${cleanImg}`;
                    }
                    galleryHtml += `
                        <div class="position-relative border rounded p-1" style="width: 80px; height: 80px;" id="gal_img_${img.id}" data-img-id="${img.id}">
                            <img src="${imgUrl}" class="w-100 h-100" style="object-fit: cover;" onerror="this.onerror=null; this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';">
                            <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 p-1 delete-gallery-btn" data-img-id="${img.id}" style="line-height: .8;"><i class="fas fa-times" style="font-size: 10px;"></i></button>
                        </div>
                    `;
                });
            }
            galleryDiv.innerHTML = galleryHtml;
            
            // Initialize Sortable for existing images
            new Sortable(galleryDiv, {
                animation: 150,
                onEnd: function() {
                    const order = [];
                    galleryDiv.querySelectorAll('.position-relative').forEach(el => {
                        if (el.dataset.imgId) order.push(el.dataset.imgId);
                    });
                    
                    if (order.length > 0) {
                        const formData = new FormData();
                        formData.append('action', 'update_gallery_order');
                        order.forEach((id, index) => {
                            formData.append(`order[${index}]`, id);
                        });
                        
                        fetch('manage_products.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (!data.success) console.error('Failed to update order:', data.error);
                        });
                    }
                }
            });
            
            document.querySelectorAll('.delete-gallery-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if(confirm('Delete this gallery image?')) {
                        const imgId = this.dataset.imgId;
                        fetch('manage_products.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'action=delete_gallery_image&image_id=' + imgId
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                const el = document.getElementById('gal_img_' + imgId);
                                if (el) el.remove();
                            } else {
                                alert('Error: ' + (data.error || 'Failed to delete image.'));
                            }
                        })
                        .catch(err => {
                            console.error('Logout or session issue?', err);
                            alert('Failed to connect to server. Please refresh.');
                        });
                    }
                });
            });
            
            var modal = new mdb.Modal(document.getElementById('editProductModal'));
            modal.show();
        });
    });
    // Trending Toggle AJAX
    const trendingToggles = document.querySelectorAll('.trending-toggle');
    trendingToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const id = this.dataset.id;
            const container = this;
            
            // Add a small animation/feedback
            container.style.opacity = '0.5';
            
            const formData = new FormData();
            formData.append('action', 'toggle_trending');
            formData.append('id', id);
            
            fetch('manage_products.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                container.style.opacity = '1';
                if(data.success) {
                    const badge = container.querySelector('.badge');
                    if(badge.classList.contains('bg-light')) {
                        container.innerHTML = '<span class="badge bg-warning text-dark"><i class="fas fa-star me-1"></i>Trending</span>';
                        // Also update any other attribute if needed (like the edit button data-is-trending)
                        const editBtn = container.closest('tr').querySelector('.edit-product-btn');
                        if(editBtn) editBtn.dataset.isTrending = '1';
                    } else {
                        container.innerHTML = '<span class="badge bg-light text-muted"><i class="far fa-star me-1"></i>Regular</span>';
                        const editBtn = container.closest('tr').querySelector('.edit-product-btn');
                        if(editBtn) editBtn.dataset.isTrending = '0';
                    }
                }
            })
            .catch(err => {
                container.style.opacity = '1';
                console.error('Error toggling trending status:', err);
            });
        });
    });

    // Handle New Gallery Previews and Sorting (Add & Edit Modal)
    function setupNewGallerySorting(inputId, previewId) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        let selectedFiles = [];

        input.addEventListener('change', function() {
            const files = Array.from(this.files);
            // Append new files to our list
            selectedFiles = selectedFiles.concat(files);
            renderPreviews();
            updateInputFiles();
        });

        function renderPreviews() {
            preview.innerHTML = '';
            selectedFiles.forEach((file, index) => {
                const reader = new FileReader();
                const div = document.createElement('div');
                div.className = 'position-relative border rounded p-1 new-gal-item';
                div.style.width = '80px';
                div.style.height = '80px';
                div.style.cursor = 'move';
                div.dataset.index = index;

                reader.onload = function(e) {
                    div.innerHTML = `
                        <img src="${e.target.result}" class="w-100 h-100" style="object-fit: cover;">
                        <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 p-1 remove-new-img" data-index="${index}" style="line-height: .8;"><i class="fas fa-times" style="font-size: 10px;"></i></button>
                    `;
                    
                    div.querySelector('.remove-new-img').addEventListener('click', function(e) {
                        e.stopPropagation();
                        selectedFiles.splice(index, 1);
                        renderPreviews();
                        updateInputFiles();
                    });
                };
                reader.readAsDataURL(file);
                preview.appendChild(div);
            });

            if (selectedFiles.length > 0) {
                if (!preview.sortable) {
                    preview.sortable = new Sortable(preview, {
                        animation: 150,
                        draggable: '.new-gal-item',
                        onEnd: function() {
                            const newOrder = [];
                            preview.querySelectorAll('.new-gal-item').forEach(el => {
                                newOrder.push(selectedFiles[parseInt(el.dataset.index)]);
                            });
                            selectedFiles = newOrder;
                            renderPreviews();
                            updateInputFiles();
                        }
                    });
                }
            }
        }

        function updateInputFiles() {
            const dataTransfer = new DataTransfer();
            selectedFiles.forEach(file => dataTransfer.items.add(file));
            input.files = dataTransfer.files;
        }

        return {
            reset: function() {
                selectedFiles = [];
                input.value = '';
                preview.innerHTML = '';
            }
        };
    }

    const addGallerySort = setupNewGallerySorting('add_p_gallery', 'add_gallery_preview');
    const editGallerySort = setupNewGallerySorting('edit_p_gallery', 'edit_gallery_preview_new');

    // Reset when modals open/close to avoid state leakage
    document.getElementById('editProductModal').addEventListener('show.mdb.modal', () => {
        editGallerySort.reset();
    });
    document.getElementById('addProductModal').addEventListener('show.mdb.modal', () => {
        addGallerySort.reset();
    });
});

function toggleProductFields(mode) {
    const type = mode === 'add' ? document.getElementById('add_product_type').value : document.getElementById('edit_p_product_type').value;
    const dlFields = document.getElementById(mode === 'add' ? 'add_download_fields' : 'edit_download_fields');
    const shippingContainer = document.getElementById(mode === 'add' ? 'add_shipping_cost_container' : 'edit_shipping_cost_container');
    const stockField = mode === 'add' ? document.getElementsByName('stock')[0] : document.getElementById('edit_p_stock');

    
    // Toggle Downloadable Fields
    if (type === 'downloadable') {
        dlFields.classList.remove('d-none');
    } else {
        dlFields.classList.add('d-none');
    }

    // Toggle Shipping Cost field visibility
    if (type === 'physical') {
        shippingContainer.classList.remove('d-none');
        document.querySelectorAll('.' + mode + '_shipping_details_group').forEach(el => el.classList.remove('d-none'));
        document.querySelectorAll('.' + mode + '_payment_options_group').forEach(el => el.classList.remove('d-none'));
    } else {
        shippingContainer.classList.add('d-none');
        document.querySelectorAll('.' + mode + '_shipping_details_group').forEach(el => el.classList.add('d-none'));
        // Automatically disable COD for digital products
        document.querySelectorAll('.' + mode + '_payment_options_group').forEach(el => el.classList.add('d-none'));
        const codToggle = document.getElementById(mode === 'add' ? 'add_p_cod_available' : 'edit_p_cod_available');
        if (codToggle) codToggle.checked = false;
    }



    
    // Toggle/Disable Shipping Related fields (Stock used as proxy here for simplicity in UI)
    if (type === 'virtual') {
        if (stockField) {
            stockField.closest('.col-md-3').classList.add('d-none');
            stockField.value = '999999'; // Large number for virtual
            stockField.required = false;
        }
    } else {
        if (stockField) {
            stockField.closest('.col-md-3').classList.remove('d-none');
            if (mode === 'add' && stockField.value === '999999') stockField.value = '';
            stockField.required = true;
        }
    }
}


function checkSeoScore(mode = 'edit') {
    const prefix = mode === 'add' ? 'add_p_' : 'edit_p_';
    const feedbackId = mode === 'add' ? 'add_seo_feedback' : 'seo_feedback';
    
    // Check if elements exist before accessing
    const keywordEl = document.getElementById(prefix + 'focus_keyword');
    const titleEl = document.getElementById(prefix + 'seo_title');
    const descEl = document.getElementById(prefix + 'meta_description');
    const feedback = document.getElementById(feedbackId);
    
    if (!keywordEl || !titleEl || !descEl || !feedback) return;

    const keyword = keywordEl.value.toLowerCase();
    const title = titleEl.value.toLowerCase();
    const desc = descEl.value.toLowerCase();
    
    if (!keyword) {
        feedback.innerHTML = '<span class="text-muted">Enter a keyword to analyze.</span>';
        return;
    }
    
    let score = 0;
    let messages = [];
    
    if (title.includes(keyword)) {
        score += 50;
        messages.push('<span class="text-success"><i class="fas fa-check-circle"></i> Title</span>');
    } else {
        messages.push('<span class="text-danger"><i class="fas fa-times-circle"></i> Title</span>');
    }
    
    if (desc.includes(keyword)) {
        score += 50;
        messages.push('<span class="text-success"><i class="fas fa-check-circle"></i> Meta Desc</span>');
    } else {
        messages.push('<span class="text-danger"><i class="fas fa-times-circle"></i> Meta Desc</span>');
    }
    
    feedback.innerHTML = messages.join(' | ') + ` <strong>Score: ${score}%</strong>`;
}

// Event Listeners for Live Feedback
['add', 'edit'].forEach(mode => {
    const prefix = mode === 'add' ? 'add_p_' : 'edit_p_';
    const fields = ['focus_keyword', 'seo_title', 'meta_description'];
    fields.forEach(f => {
        const el = document.getElementById(prefix + f);
        if (el) el.addEventListener('input', () => checkSeoScore(mode));
    });
});
</script>

<?php if(isset($_GET['action']) && $_GET['action'] === 'add' && $has_cats && !isset($success)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var addModal = new mdb.Modal(document.getElementById('addProductModal'));
    addModal.show();
    
    // Clean up URL parameter when modal is closed
    document.getElementById('addProductModal').addEventListener('hidden.mdb.modal', function () {
        const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + "?action=list";
        window.history.pushState({path:newUrl}, '', newUrl);
        // Also remove active class from sidebar "Add Product" and set "All Products" active
        document.querySelectorAll('a[href="manage_products.php?action=add"]').forEach(el => el.classList.remove('text-white', 'fw-bold'));
        document.querySelectorAll('a[href="manage_products.php?action=list"]').forEach(el => el.classList.add('text-white', 'fw-bold'));
    });
});
</script>
<?php endif; ?>

<!-- Import Product Modal -->
<div class="modal fade" id="importProductModal" tabindex="-1" aria-labelledby="importProductModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-info text-white border-0 px-4 py-3">
                <h5 class="modal-title fw-bold" id="importProductModalLabel"><i class="fas fa-file-import me-2"></i>Import Products</h5>
                <button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="import_products.php" method="POST" enctype="multipart/form-data" id="importForm">
    <?php echo csrf_input(); ?>
                <div class="modal-body p-4">
                    <div class="alert alert-light border shadow-sm mb-4">
                        <i class="fas fa-info-circle text-info me-2"></i> Supported formats: <strong>.csv, .xlsx</strong><br>
                        <a href="export_sample.php" class="text-decoration-none fw-bold mt-2 d-inline-block"><i class="fas fa-download me-1"></i>Download Sample Format</a>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select File</label>
                        <input type="file" name="import_file" class="form-control" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" required>
                        <div class="form-text mt-2">
                            The system will automatically Add new products or Update existing products (matched by Product ID or Slug).
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light btn-custom" data-mdb-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnImportSubmit" class="btn btn-info text-white btn-custom px-4">
                        <span id="importBtnText"><i class="fas fa-upload me-2"></i>Upload & Import</span>
                        <span id="importSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        <span id="importingText" class="d-none ms-2">Processing Data...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Add lightweight progress UI feedback on form submittal
    document.getElementById('importForm').addEventListener('submit', function() {
        var btn = document.getElementById('btnImportSubmit');
        var btnText = document.getElementById('importBtnText');
        var spinner = document.getElementById('importSpinner');
        var compilingText = document.getElementById('importingText');
        
        btn.disabled = true;
        btn.classList.add('opacity-75');
        btnText.classList.add('d-none');
        spinner.classList.remove('d-none');
        compilingText.classList.remove('d-none');
    });
</script>

<?php include 'admin_footer.php'; ?>

