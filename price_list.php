<?php
/**
 * ============================================================
 *  Product Price List (PDF) — Wholesale & Retailer Portal
 *  Location: /price_list.php
 * ============================================================
 *  Secured endpoint: Restricted strictly to Admin and Retailer
 *  (Wholesaler) user roles, controlled via Admin Panel.
 * ============================================================
 */

include_once __DIR__ . '/includes/session_setup.php';
include_once __DIR__ . '/includes/db_connect.php';

// ── 1. Authentication Check: Must be logged in ───────────────
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = 'price_list.php';
    $_SESSION['error'] = "Please log in with your Retailer or Administrator account to access the Wholesale Price List.";
    header("Location: user/login.php");
    exit;
}

$user_id   = intval($_SESSION['user_id']);
$user_role = $_SESSION['role'] ?? 'user';
$user_name = $_SESSION['name'] ?? 'Valued Partner';

// ── 2. Admin Panel Configuration & Permission Checks ──────────
$is_admin = ($user_role === 'admin');

// Check allowed roles from settings
$allowed_roles_raw = $global_settings['price_list_allowed_roles'] ?? 'admin,retailer';
$allowed_roles     = array_map('trim', explode(',', strtolower($allowed_roles_raw)));

$is_authorized = in_array(strtolower($user_role), $allowed_roles) || $is_admin;

// Check if feature is enabled
$is_enabled = ($global_settings['price_list_enabled'] ?? '1') === '1';

// If feature disabled and not admin previewing
if (!$is_enabled && !$is_admin) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Price List Unavailable — <?php echo htmlspecialchars($global_settings['site_name'] ?? "Sagar Starter's"); ?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.0/mdb.min.css" rel="stylesheet"/>
        <style>
            body { background: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
            .card-notice { max-width: 520px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); }
        </style>
    </head>
    <body>
        <div class="card card-notice border-0 p-4 p-md-5 text-center bg-white m-3">
            <div class="mb-3 text-warning">
                <i class="fas fa-clock fa-4x"></i>
            </div>
            <h3 class="fw-bold mb-2 text-dark">Price List Download Paused</h3>
            <p class="text-muted mb-4">
                The product price list download feature is currently undergoing scheduled maintenance or revision by our administration. Please check back shortly or contact our sales team.
            </p>
            <div class="d-flex justify-content-center gap-2">
                <a href="<?php echo SITE_URL; ?>/shop.php" class="btn btn-primary rounded-pill px-4">
                    <i class="fas fa-store me-1"></i> Visit Shop
                </a>
                <a href="<?php echo SITE_URL; ?>/contact.php" class="btn btn-outline-secondary rounded-pill px-4">
                    <i class="fas fa-envelope me-1"></i> Contact Us
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// If user is a normal customer (unauthorized role)
if (!$is_authorized) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Access Restricted — <?php echo htmlspecialchars($global_settings['site_name'] ?? "Sagar Starter's"); ?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.0/mdb.min.css" rel="stylesheet"/>
        <style>
            body { background: #f1f5f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
            .card-restricted { max-width: 540px; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.08); }
        </style>
    </head>
    <body>
        <div class="card card-restricted border-0 p-4 p-md-5 text-center bg-white m-3">
            <div class="mb-3 text-danger">
                <i class="fas fa-user-lock fa-4x"></i>
            </div>
            <h3 class="fw-bold mb-2 text-dark">Wholesale Access Required</h3>
            <p class="text-muted mb-4">
                The Product Price List (PDF) with bulk wholesale rates is exclusively reserved for <strong>Verified Retailers & Distributors</strong> and Store Administrators.
            </p>
            <div class="p-3 bg-light rounded-3 text-start small mb-4">
                <div class="fw-bold text-dark mb-1"><i class="fas fa-info-circle text-primary me-1"></i> Are you a Retailer, Wholesaler, or Electrician?</div>
                <span class="text-muted">Contact us with your GSTIN or business card to have your account upgraded to Retailer status to access discounted wholesale rates.</span>
            </div>
            <div class="d-flex justify-content-center gap-2 flex-wrap">
                <a href="<?php echo SITE_URL; ?>/shop.php" class="btn btn-primary rounded-pill px-4">
                    <i class="fas fa-arrow-left me-1"></i> Back to Shop
                </a>
                <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $global_settings['contact_phone'] ?? ''); ?>?text=Hello%2C%20I%20would%20like%20to%20apply%20for%20a%20Retailer%2FWholesale%20Account." target="_blank" class="btn btn-success rounded-pill px-4">
                    <i class="fab fa-whatsapp me-1"></i> Apply on WhatsApp
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ── 3. Check for Custom Uploaded PDF Mode ───────────────────────
$source_mode    = $global_settings['price_list_source'] ?? 'dynamic';
$custom_pdf_rel = $global_settings['price_list_custom_pdf'] ?? '';
$custom_pdf_abs = !empty($custom_pdf_rel) ? (BASE_PATH . '/' . ltrim($custom_pdf_rel, '/')) : '';

if ($source_mode === 'custom_pdf' && !empty($custom_pdf_abs) && file_exists($custom_pdf_abs)) {
    $is_download = isset($_GET['download']) && $_GET['download'] == '1';
    $download_name = "Sagar_Starters_Wholesale_Price_List_" . date('Y-m-d') . ".pdf";

    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($is_download ? 'attachment' : 'inline') . '; filename="' . $download_name . '"');
    header('Content-Length: ' . filesize($custom_pdf_abs));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    readfile($custom_pdf_abs);
    exit;
}

// ── 4. Dynamic Catalog Mode — Query Categories & Products from Database
// Admin configured selected categories (multi-select checkboxes)
$raw_cat_selection = $global_settings['price_list_selected_categories'] ?? '';
$admin_selected_cat_ids = [];
if ($raw_cat_selection === 'none') {
    $admin_selected_cat_ids = [-1]; // Explicitly none selected
} elseif (!empty($raw_cat_selection) && $raw_cat_selection !== 'all') {
    $admin_selected_cat_ids = array_filter(array_map('intval', explode(',', $raw_cat_selection)));
}

$cat_where_clause = "";
if (!empty($admin_selected_cat_ids)) {
    $cat_where_clause = " AND c.id IN (" . implode(',', $admin_selected_cat_ids) . ") ";
}

// Fetch all store categories with product counts (restricted to admin selected categories)
$categories_res = $conn->query("
    SELECT c.id, c.name, c.slug, 
           COUNT(p.id) as product_count,
           SUM(CASE WHEN p.bulk_price > 0 THEN 1 ELSE 0 END) as bulk_count
    FROM categories c 
    INNER JOIN products p ON p.category_id = c.id
    WHERE 1=1 $cat_where_clause
    GROUP BY c.id, c.name, c.slug 
    HAVING product_count > 0 
    ORDER BY c.name ASC
");
$all_categories = [];
$total_catalog_products = 0;
if ($categories_res) {
    while ($crow = $categories_res->fetch_assoc()) {
        $all_categories[] = $crow;
        $total_catalog_products += (int)$crow['product_count'];
    }
}

// Determine active category filter (URL override takes precedence over admin setting)
$configured_filter = $global_settings['price_list_filter'] ?? 'all';
$url_category = isset($_GET['category']) ? trim($_GET['category']) : (isset($_GET['cat']) ? trim($_GET['cat']) : (isset($_GET['category_id']) ? trim($_GET['category_id']) : ''));

$active_cat_id = null;
$active_cat_name = null;
$active_cat_slug = null;

if ($url_category !== '') {
    if ($url_category !== 'all' && $url_category !== '0') {
        foreach ($all_categories as $c) {
            if ((string)$c['id'] === (string)$url_category || $c['slug'] === $url_category) {
                $active_cat_id = (int)$c['id'];
                $active_cat_name = $c['name'];
                $active_cat_slug = $c['slug'];
                break;
            }
        }
        if (!$active_cat_id && is_numeric($url_category)) {
            $cstmt = $conn->prepare("SELECT id, name, slug FROM categories WHERE id = ?");
            $cstmt->bind_param("i", $url_category);
            $cstmt->execute();
            $cres = $cstmt->get_result();
            if ($cinfo = $cres->fetch_assoc()) {
                $active_cat_id = (int)$cinfo['id'];
                $active_cat_name = $cinfo['name'];
                $active_cat_slug = $cinfo['slug'];
            }
            $cstmt->close();
        }
    }
}

$sql = "
    SELECT 
        p.id, 
        p.name, 
        p.slug, 
        p.sku, 
        p.brand, 
        p.price, 
        p.sale_price, 
        p.regular_price, 
        p.bulk_price, 
        p.bulk_min_qty, 
        p.min_order_qty, 
        p.stock, 
        p.image, 
        c.id as category_id,
        c.name as category_name,
        c.slug as category_slug
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE 1=1
";

if (!empty($admin_selected_cat_ids)) {
    $sql .= " AND p.category_id IN (" . implode(',', $admin_selected_cat_ids) . ") ";
}

if ($active_cat_id) {
    $sql .= " AND p.category_id = " . (int)$active_cat_id . " ";
} elseif ($configured_filter === 'bulk_only' && empty($url_category)) {
    $sql .= " AND p.bulk_price > 0 ";
}

$sql .= " ORDER BY COALESCE(c.name, 'Z_Other') ASC, p.name ASC";

$products_res = $conn->query($sql);
$catalog = [];
$total_matched_items = 0;

if ($products_res) {
    while ($row = $products_res->fetch_assoc()) {
        $cat = !empty($row['category_name']) ? trim($row['category_name']) : 'General Products';
        $catalog[$cat][] = $row;
        $total_matched_items++;
    }
}

// ── 5. Prepare Layout Tokens & Configuration ───────────────────
$store_name    = $global_settings['site_name'] ?? "Sagar Starter's";
$store_phone   = $global_settings['contact_phone'] ?? '';
$store_email   = $global_settings['contact_email'] ?? '';

// Address resolution with multi-key fallback
$raw_address   = !empty($global_settings['contact_address']) 
    ? $global_settings['contact_address'] 
    : (!empty($global_settings['store_address']) 
        ? $global_settings['store_address'] 
        : ($global_settings['invoice_store_address'] ?? ''));
$store_address = trim(preg_replace('/\s*[\r\n]+\s*/', ', ', $raw_address));

// GST number resolution with multi-key fallback
$gst_number    = !empty($global_settings['invoice_gst_number']) 
    ? $global_settings['invoice_gst_number'] 
    : (!empty($global_settings['gst_number']) 
        ? $global_settings['gst_number'] 
        : ($global_settings['business_gst_number'] ?? ''));
$currency      = $global_currency ?? '₹';

$logo_val = $global_settings['header_logo_image'] ?? 'logo.jpg';
$logo_url = function_exists('resolve_image_url') ? resolve_image_url($logo_val) : (ASSETS_URL . '/images/logo.jpg');

$doc_title    = $global_settings['price_list_title'] ?? "Sagar Starter's — Wholesale & Retailer Price List";
$doc_subtitle = $global_settings['price_list_subtitle'] ?? "Official Authorized Product Catalog";
$doc_note     = $global_settings['price_list_note'] ?? "Note: All prices are in Indian Rupees (INR) and subject to change without prior notice. GST, transportation, and loading charges extra as applicable. For dealership inquiries, custom specifications, or bulk dispatches, contact our sales desk directly.";

$show_img   = ($global_settings['price_list_show_image'] ?? '1') === '1';
$show_sku   = ($global_settings['price_list_show_sku'] ?? '1') === '1';
$show_cat   = ($global_settings['price_list_show_category'] ?? '1') === '1';
$show_reg   = ($global_settings['price_list_show_regular_price'] ?? '1') === '1';
$show_sale  = ($global_settings['price_list_show_sale_price'] ?? '1') === '1';
$show_bulk  = ($global_settings['price_list_show_bulk_price'] ?? '1') === '1';
$show_moq   = ($global_settings['price_list_show_moq'] ?? '1') === '1';
$show_stock = ($global_settings['price_list_show_stock'] ?? '1') === '1';
$show_share = ($global_settings['price_list_show_share'] ?? '1') === '1';

$share_url   = SITE_URL . '/price_list.php' . (!empty($active_cat_id) ? '?category=' . urlencode($active_cat_id) : '');
$share_title = $doc_title;
$share_text  = "Sagar Starter's Wholesale & Retailer Price List (Official PDF): " . $share_url;

$gen_date = date('d M Y, h:i A');
$user_role_label = ($user_role === 'admin') ? 'Store Administrator' : 'Verified Wholesale Retailer';
$auto_download = isset($_GET['download']) && $_GET['download'] == '1';

// ── Open Graph & Social Share Metadata ───────────────────────
$page_html_title = (stripos($doc_title, $store_name) !== false) ? $doc_title : ($doc_title . ' — ' . $store_name);
$og_description  = !empty($doc_subtitle) ? $doc_subtitle : (isset($doc_note) ? substr(strip_tags($doc_note), 0, 160) : "Official wholesale and retailer price list for Sagar Starter's motors, panels & switchgear.");

$og_base_url = (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1')
    ? (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
    : (defined('SITE_URL') && strpos(SITE_URL, 'http') === 0 ? rtrim(SITE_URL, '/') : 'https://www.sagarstarters.com');

$og_banner_file  = __DIR__ . '/assets/images/price_list_og_banner.jpg';
$og_banner_v     = file_exists($og_banner_file) ? filemtime($og_banner_file) : '2026';
$og_image_url    = $og_base_url . '/assets/images/price_list_og_banner.jpg?v=' . $og_banner_v;
$og_image_secure = (strpos($og_image_url, 'http://') === 0) ? preg_replace('/^http:/', 'https:', $og_image_url) : $og_image_url;
$og_canonical_url = $og_base_url . '/price_list.php' . (!empty($active_cat_id) ? '?category=' . urlencode($active_cat_id) : '');
?>
<!DOCTYPE html>
<html lang="en" prefix="og: https://ogp.me/ns#">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_html_title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($og_description); ?>">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?php echo htmlspecialchars($store_name); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($og_canonical_url); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($page_html_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($og_description); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($og_image_url); ?>">
    <meta property="og:image:secure_url" content="<?php echo htmlspecialchars($og_image_secure); ?>">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="1376">
    <meta property="og:image:height" content="768">
    <meta property="og:image:alt" content="<?php echo htmlspecialchars($page_html_title); ?>">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?php echo htmlspecialchars($og_canonical_url); ?>">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($page_html_title); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($og_description); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($og_image_url); ?>">
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.0/mdb.min.css" rel="stylesheet"/>

    <style>
        /* ── Reset & Typography ────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #e2e8f0;
            color: #1e293b;
            line-height: 1.5;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ── Sticky Top Action Bar (Screen Only) ───────────────── */
        #action-bar {
            position: sticky;
            top: 0;
            z-index: 9999;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            padding: 12px 24px;
            color: #fff;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }
        .action-bar-inner {
            max-width: 1140px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: gap;
            gap: 12px;
        }
        .action-btns {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-act {
            border: none;
            padding: 8px 18px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-act-pdf {
            background: #ef4444;
            color: #fff;
            box-shadow: 0 4px 14px rgba(239, 68, 68, 0.4);
        }
        .btn-act-pdf:hover { background: #dc2626; color: #fff; transform: translateY(-1px); }
        .btn-act-print {
            background: #2563eb;
            color: #fff;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
        }
        .btn-act-print:hover { background: #1d4ed8; color: #fff; transform: translateY(-1px); }
        .btn-act-share {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: #fff;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
        }
        .btn-act-share:hover { background: #4338ca; color: #fff; transform: translateY(-1px); }
        .btn-act-store {
            background: rgba(255, 255, 255, 0.12);
            color: #f1f5f9;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .btn-act-store:hover { background: rgba(255, 255, 255, 0.22); color: #fff; }
        .btn-act-admin {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.4);
        }
        .btn-act-admin:hover { background: rgba(245, 158, 11, 0.35); color: #fef3c7; }

        /* ── Social Share & Copy Link Modal Styles ──────────────── */
        .share-modal-backdrop {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.72);
            backdrop-filter: blur(5px);
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            animation: shareFadeIn 0.2s ease-out;
        }
        @keyframes shareFadeIn { from { opacity: 0; } to { opacity: 1; } }
        .share-modal-box {
            background: #ffffff;
            border-radius: 16px;
            max-width: 480px;
            width: 100%;
            padding: 24px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            position: relative;
            animation: shareSlideUp 0.25s ease-out;
        }
        @keyframes shareSlideUp { from { transform: translateY(18px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .share-modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 18px;
        }
        .share-modal-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.15rem;
            font-weight: 700;
            margin: 0;
            color: #0f172a;
        }
        .share-modal-sub {
            font-size: 0.78rem;
            color: #64748b;
            margin: 3px 0 0 0;
        }
        .share-modal-close {
            background: transparent;
            border: none;
            font-size: 1.75rem;
            line-height: 1;
            color: #94a3b8;
            cursor: pointer;
            padding: 0 4px;
        }
        .share-modal-close:hover { color: #0f172a; }
        .share-copy-box {
            display: flex;
            gap: 8px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 6px 8px;
            margin-bottom: 18px;
        }
        .share-copy-box input {
            background: transparent;
            border: none;
            outline: none;
            font-size: 0.82rem;
            color: #334155;
            flex-grow: 1;
            font-family: monospace;
        }
        .share-social-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        .share-social-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 10px;
            color: #fff !important;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .share-social-btn:hover { transform: translateY(-2px); color: #fff !important; }
        .share-wa { background: #25d366; }
        .share-wa:hover { background: #1ebd59; }
        .share-tg { background: #229ed9; }
        .share-tg:hover { background: #1c88bc; }
        .share-fb { background: #1877f2; }
        .share-fb:hover { background: #1464cc; }
        .share-x { background: #0f172a; }
        .share-x:hover { background: #1e293b; }

        /* ── Main A4 Printable Document Container ──────────────── */
        .doc-wrapper {
            padding: 30px 15px;
            display: flex;
            justify-content: center;
        }
        .doc-page {
            width: 100%;
            max-width: 1050px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            padding: 36px 42px;
            box-sizing: border-box;
        }

        /* ── Document Header ───────────────────────────────────── */
        .doc-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            padding-bottom: 20px;
            border-bottom: 2px solid #0f172a;
            margin-bottom: 20px;
            gap: 20px;
        }
        .brand-block {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .brand-logo {
            max-height: 65px;
            max-width: 160px;
            object-fit: contain;
        }
        .brand-info h2 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.55rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            line-height: 1.2;
        }
        .brand-info p {
            font-size: 0.8rem;
            color: #64748b;
            margin: 3px 0 0 0;
        }
        .doc-meta {
            text-align: right;
            font-size: 0.8rem;
            color: #475569;
        }
        .doc-meta-badge {
            display: inline-block;
            background: #e0e7ff;
            color: #3730a3;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ── Title Banner ──────────────────────────────────────── */
        .title-banner {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: #ffffff;
            padding: 14px 20px;
            border-radius: 8px;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        .title-banner h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0;
            color: #ffffff;
        }
        .title-banner-sub {
            font-size: 0.8rem;
            color: #cbd5e1;
            margin: 0;
        }
        .recipient-pill {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.25);
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            color: #e2e8f0;
            font-weight: 500;
        }

        /* ── Category Section & Table ──────────────────────────── */
        .category-heading {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 6px;
            margin-top: 24px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .cat-badge-count {
            font-size: 0.72rem;
            background: #eff6ff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 600;
        }
        .price-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            margin-bottom: 20px;
        }
        .price-table th {
            background: #f1f5f9;
            color: #334155;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.5px;
            padding: 9px 10px;
            border-top: 1px solid #cbd5e1;
            border-bottom: 2px solid #94a3b8;
            text-align: left;
        }
        .price-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        .price-table tr:nth-child(even) td {
            background-color: #fafbfc;
        }
        .thumb-img {
            width: 40px;
            height: 40px;
            object-fit: contain;
            border-radius: 6px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            display: block;
        }
        .prod-name {
            font-weight: 600;
            color: #0f172a;
            line-height: 1.3;
        }
        .prod-sku {
            font-family: monospace;
            font-size: 0.72rem;
            color: #64748b;
            background: #f1f5f9;
            padding: 2px 5px;
            border-radius: 4px;
            display: inline-block;
        }
        .price-regular {
            text-decoration: line-through;
            color: #94a3b8;
            font-size: 0.75rem;
            margin-right: 4px;
        }
        .price-sale-bold {
            color: #2563eb;
            font-weight: 700;
            font-size: 0.95rem;
        }
        .price-bulk-bold {
            color: #15803d;
            font-weight: 700;
            font-size: 0.95rem;
        }
        .badge-moq {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
            padding: 2px 7px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        .badge-stock-in {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
            padding: 2px 7px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        .badge-stock-out {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
            padding: 2px 7px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }

        /* ── Document Footer & Commercial Terms ────────────────── */
        .doc-footer {
            margin-top: 30px;
            padding-top: 18px;
            border-top: 2px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 20px;
        }
        .doc-terms {
            flex: 1;
            min-width: 280px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #3b82f6;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.75rem;
            color: #475569;
        }
        .doc-terms strong {
            color: #1e293b;
            display: block;
            margin-bottom: 4px;
            font-size: 0.8rem;
        }
        .doc-seal-block {
            text-align: center;
            min-width: 200px;
        }
        .doc-seal-box {
            border: 1px dashed #94a3b8;
            border-radius: 8px;
            padding: 12px 20px;
            background: #fdfdfd;
        }
        .doc-seal-title {
            font-weight: 700;
            font-size: 0.78rem;
            color: #0f172a;
            margin-bottom: 2px;
        }
        .doc-seal-sub {
            font-size: 0.68rem;
            color: #64748b;
        }

        /* ── Category Filter Select & Pills ───────────────────── */
        .cat-filter-select {
            background-color: rgba(255, 255, 255, 0.12);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 20px;
            padding: 6px 14px;
            font-size: 0.8rem;
            font-weight: 600;
            max-width: 250px;
            cursor: pointer;
            outline: none;
            transition: all 0.2s ease;
        }
        .cat-filter-select:focus {
            background-color: rgba(255, 255, 255, 0.2);
            color: #ffffff;
            border-color: #60a5fa;
            box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.25);
        }
        .cat-filter-select option {
            background: #0f172a;
            color: #ffffff;
        }
        .cat-pill-bar {
            padding: 12px 20px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .cat-pill-link {
            text-decoration: none;
            font-size: 0.78rem;
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 20px;
            background: #ffffff;
            color: #334155;
            border: 1px solid #cbd5e1;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s ease;
        }
        .cat-pill-link:hover {
            background: #e2e8f0;
            color: #0f172a;
            transform: translateY(-1px);
        }
        .cat-pill-link.active {
            background: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 3px 8px rgba(37, 99, 235, 0.3);
        }
        .cat-pill-count {
            background: rgba(0, 0, 0, 0.08);
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.7rem;
        }
        .cat-pill-link.active .cat-pill-count {
            background: rgba(255, 255, 255, 0.25);
            color: #ffffff;
        }
        .cat-clear-link {
            font-size: 0.75rem;
            color: #ef4444;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
        }
        .cat-clear-link:hover {
            text-decoration: underline;
        }

        /* ── Print Media Optimization ──────────────────────────── */
        @page {
            size: A4 portrait;
            margin: 8mm 8mm 10mm 8mm;
        }
        @media print {
            body { background: #ffffff !important; }
            #action-bar { display: none !important; }
            .no-print, .cat-pill-bar { display: none !important; }
            .doc-wrapper { padding: 0 !important; }
            .doc-page {
                box-shadow: none !important;
                border-radius: 0 !important;
                max-width: 100% !important;
                padding: 0 !important;
            }
            .price-table tr {
                page-break-inside: avoid;
            }
            .category-heading {
                page-break-after: avoid;
            }
        }
    </style>
</head>
<body>

<!-- Sticky Action Bar (Hidden in Print & PDF export) -->
<header id="action-bar">
    <div class="action-bar-inner">
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-file-pdf text-danger fs-5"></i>
            <div>
                <span class="fw-bold d-block text-white" style="font-size: 0.9rem;">Wholesale Product Price List</span>
                <span class="small text-light text-opacity-75" style="font-size: 0.72rem;">Authorized Portal: <?php echo htmlspecialchars($user_name); ?> (<?php echo $user_role_label; ?>)</span>
            </div>
        </div>
        <div class="action-btns">
            <!-- Category Filter in Action Bar -->
            <?php if (!empty($all_categories)): ?>
            <div class="d-flex align-items-center gap-1">
                <label for="actionCatFilterPl" class="small text-white text-opacity-75 d-none d-lg-inline text-nowrap m-0">
                    <i class="fas fa-layer-group text-warning me-1"></i> Category:
                </label>
                <select id="actionCatFilterPl" class="cat-filter-select" onchange="filterPriceListCategory(this.value)">
                    <option value="all" <?php echo empty($active_cat_id) ? 'selected' : ''; ?>>All Categories (<?php echo $total_catalog_products; ?>)</option>
                    <?php foreach ($all_categories as $citem): ?>
                        <option value="<?php echo $citem['id']; ?>" <?php echo ($active_cat_id == $citem['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($citem['name']); ?> (<?php echo $citem['product_count']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <button type="button" class="btn-act btn-act-pdf" id="btnDownloadPdf" onclick="downloadPDF()">
                <i class="fas fa-file-download"></i> Download PDF
            </button>
            <button type="button" class="btn-act btn-act-print" onclick="window.print()">
                <i class="fas fa-print"></i> Print / Save as PDF
            </button>
            <?php if ($show_share): ?>
            <button type="button" class="btn-act btn-act-share" onclick="openShareModal()">
                <i class="fas fa-share-alt"></i> Share / Copy Link
            </button>
            <?php endif; ?>
            <a href="<?php echo SITE_URL; ?>/shop.php" class="btn-act btn-act-store">
                <i class="fas fa-store"></i> Shop
            </a>
            <?php if ($is_admin): ?>
                <a href="<?php echo SITE_URL; ?>/admin/manage_price_list.php" class="btn-act btn-act-admin">
                    <i class="fas fa-cog"></i> Settings
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Document Body Container -->
<main class="doc-wrapper">
    <div class="doc-page" id="priceListDocument">
        <!-- Document Header -->
        <div class="doc-header">
            <div class="brand-block">
                <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="Logo" class="brand-logo" onerror="this.style.display='none';">
                <div class="brand-info">
                    <h2><?php echo htmlspecialchars($store_name); ?></h2>
                    <p>
                        <?php if (!empty($store_address)): ?><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($store_address); ?> &bull; <?php endif; ?>
                        <?php if (!empty($store_phone)): ?><i class="fas fa-phone-alt me-1"></i><?php echo htmlspecialchars($store_phone); ?><?php endif; ?>
                    </p>
                    <p>
                        <?php if (!empty($store_email)): ?><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($store_email); ?><?php endif; ?>
                        <?php if (!empty($gst_number)): ?> &bull; <strong>GSTIN:</strong> <?php echo htmlspecialchars($gst_number); ?><?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="doc-meta">
                <span class="doc-meta-badge">CONFIDENTIAL</span>
                <div><strong>Date:</strong> <?php echo $gen_date; ?></div>
                <div><strong>Issued To:</strong> <?php echo htmlspecialchars($user_name); ?></div>
                <div class="text-success fw-bold"><?php echo $user_role_label; ?></div>
            </div>
        </div>

        <!-- Title Banner -->
        <div class="title-banner">
            <div>
                <h1><?php echo htmlspecialchars($doc_title); ?></h1>
                <p class="title-banner-sub"><?php echo htmlspecialchars($doc_subtitle); ?></p>
            </div>
            <div class="recipient-pill">
                <i class="fas fa-check-circle text-success me-1"></i> Authorized B2B Rates
                <?php if (!empty($active_cat_name)): ?>
                    &bull; <strong><?php echo htmlspecialchars($active_cat_name); ?></strong> (<?php echo $total_matched_items; ?> Items)
                <?php endif; ?>
            </div>
        </div>


        <!-- Product Catalog Listing by Category -->
        <?php if (empty($catalog)): ?>
            <div class="p-5 text-center text-muted">
                <i class="fas fa-box-open fa-3x mb-3 text-secondary"></i>
                <h5>No Products Found</h5>
                <p class="small">There are currently no products matching the active catalog filter.</p>
            </div>
        <?php else: ?>
            <?php $item_serial = 1; ?>
            <?php foreach ($catalog as $cat_name => $items): ?>
                <div class="category-heading">
                    <span><i class="fas fa-folder-open text-primary me-2"></i><?php echo htmlspecialchars($cat_name); ?></span>
                    <span class="cat-badge-count"><?php echo count($items); ?> Items</span>
                </div>

                <table class="price-table">
                    <thead>
                        <tr>
                            <th style="width: 35px; text-align: center;">#</th>
                            <?php if ($show_img): ?><th style="width: 50px; text-align: center;">Image</th><?php endif; ?>
                            <th>Product Name</th>
                            <?php if ($show_sku): ?><th style="width: 90px;">SKU</th><?php endif; ?>
                            <?php if ($show_reg): ?><th style="width: 100px; text-align: right;">Regular MRP</th><?php endif; ?>
                            <?php if ($show_sale): ?><th style="width: 100px; text-align: right;">Sale Price</th><?php endif; ?>
                            <?php if ($show_bulk): ?><th style="width: 120px; text-align: right;">Wholesale Rate</th><?php endif; ?>
                            <?php if ($show_moq): ?><th style="width: 85px; text-align: center;">MOQ</th><?php endif; ?>
                            <?php if ($show_stock): ?><th style="width: 90px; text-align: center;">Status</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $p): ?>
                            <?php
                            $p_img = !empty($p['image']) ? (function_exists('resolve_image_url') ? resolve_image_url($p['image']) : ASSETS_URL . '/images/placeholder.svg') : (ASSETS_URL . '/images/placeholder.svg');
                            $reg_price = (float)($p['regular_price'] ?? $p['price']);
                            $sale_price = !empty($p['sale_price']) && (float)$p['sale_price'] > 0 ? (float)$p['sale_price'] : 0;
                            $bulk_price = !empty($p['bulk_price']) && (float)$p['bulk_price'] > 0 ? (float)$p['bulk_price'] : 0;
                            
                            $moq = !empty($p['bulk_min_qty']) ? (int)$p['bulk_min_qty'] : (!empty($p['min_order_qty']) ? (int)$p['min_order_qty'] : 1);
                            $in_stock = (int)($p['stock'] ?? 0) > 0;
                            ?>
                            <tr>
                                <td style="text-align: center; color: #94a3b8; font-weight: 600; font-size: 0.75rem;">
                                    <?php echo $item_serial++; ?>
                                </td>
                                <?php if ($show_img): ?>
                                    <td style="text-align: center;">
                                        <img src="<?php echo htmlspecialchars($p_img); ?>" alt="img" class="thumb-img" onerror="this.style.opacity='0.2';">
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <div class="prod-name"><?php echo htmlspecialchars($p['name']); ?></div>
                                    <?php if (!empty($p['brand'])): ?>
                                        <small class="text-muted" style="font-size: 0.7rem;">Brand: <?php echo htmlspecialchars($p['brand']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <?php if ($show_sku): ?>
                                    <td>
                                        <span class="prod-sku"><?php echo !empty($p['sku']) ? htmlspecialchars($p['sku']) : '—'; ?></span>
                                    </td>
                                <?php endif; ?>
                                <?php if ($show_reg): ?>
                                    <td style="text-align: right;">
                                        <?php if (!$show_sale && $sale_price > 0 && $sale_price < $reg_price): ?>
                                            <span class="price-regular"><?php echo $currency . number_format($reg_price, 2); ?></span>
                                            <span class="fw-semibold text-dark"><?php echo $currency . number_format($sale_price, 2); ?></span>
                                        <?php else: ?>
                                            <span class="fw-semibold text-dark"><?php echo $reg_price > 0 ? $currency . number_format($reg_price, 2) : '—'; ?></span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <?php if ($show_sale): ?>
                                    <td style="text-align: right;">
                                        <?php if ($sale_price > 0): ?>
                                            <span class="price-sale-bold"><?php echo $currency . number_format($sale_price, 2); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <?php if ($show_bulk): ?>
                                    <td style="text-align: right;">
                                        <?php if ($bulk_price > 0): ?>
                                            <span class="price-bulk-bold"><?php echo $currency . number_format($bulk_price, 2); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">Standard Rate</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <?php if ($show_moq): ?>
                                    <td style="text-align: center;">
                                        <?php if ($bulk_price > 0): ?>
                                            <span class="badge-moq"><?php echo $moq; ?>+ units</span>
                                        <?php else: ?>
                                            <span class="text-muted small">1 unit</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <?php if ($show_stock): ?>
                                    <td style="text-align: center;">
                                        <?php if ($in_stock): ?>
                                            <span class="badge-stock-in"><i class="fas fa-check me-1"></i>In Stock</span>
                                        <?php else: ?>
                                            <span class="badge-stock-out"><i class="fas fa-times me-1"></i>Out</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Document Footer & Terms -->
        <footer class="doc-footer">
            <div class="doc-terms">
                <strong><i class="fas fa-file-contract text-primary me-1"></i> Wholesale Commercial Terms & Conditions:</strong>
                <?php echo nl2br(htmlspecialchars($doc_note)); ?>
            </div>
            <div class="doc-seal-block">
                <div class="doc-seal-box">
                    <div class="doc-seal-title"><?php echo htmlspecialchars($store_name); ?></div>
                    <div class="doc-seal-sub">Authorized Distributor Signature</div>
                    <div class="mt-2 text-primary" style="font-size: 1.4rem;">
                        <i class="fas fa-stamp"></i>
                    </div>
                    <small class="text-muted d-block mt-1" style="font-size: 0.65rem;">Officially Verified &bull; <?php echo date('Y'); ?></small>
                </div>
            </div>
        </footer>
    </div>
</main>

<!-- Social Share & Copy Link Modal -->
<div id="shareModal" class="share-modal-backdrop" style="display: none;" onclick="if(event.target===this) closeShareModal();">
    <div class="share-modal-box">
        <div class="share-modal-header">
            <div>
                <h5 class="share-modal-title"><i class="fas fa-share-alt text-primary me-2"></i>Share / Copy Link</h5>
                <p class="share-modal-sub">Share this Wholesale Price List with verified retailers and colleagues</p>
            </div>
            <button type="button" class="share-modal-close" onclick="closeShareModal()" aria-label="Close">&times;</button>
        </div>

        <div class="share-copy-box">
            <input type="text" id="shareUrlInput" readonly value="<?php echo htmlspecialchars($share_url); ?>">
            <button type="button" class="btn btn-primary btn-sm px-3" id="btnCopyShareLink" onclick="copyShareUrl()">
                <i class="fas fa-copy me-1"></i> Copy Link
            </button>
        </div>

        <div class="share-social-grid">
            <a href="https://api.whatsapp.com/send?text=<?php echo urlencode($share_text); ?>" target="_blank" class="share-social-btn share-wa">
                <i class="fab fa-whatsapp"></i> WhatsApp
            </a>
            <a href="https://t.me/share/url?url=<?php echo urlencode($share_url); ?>&text=<?php echo urlencode($share_title); ?>" target="_blank" class="share-social-btn share-tg">
                <i class="fab fa-telegram-plane"></i> Telegram
            </a>
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($share_url); ?>" target="_blank" class="share-social-btn share-fb">
                <i class="fab fa-facebook-f"></i> Facebook
            </a>
            <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($share_url); ?>&text=<?php echo urlencode($share_title); ?>" target="_blank" class="share-social-btn share-x">
                <i class="fab fa-twitter"></i> Twitter / X
            </a>
        </div>

        <button type="button" id="btnNativeShare" class="btn btn-outline-secondary w-100 mt-3 rounded-pill py-2 small fw-bold" style="display: none;" onclick="triggerNativeShare()">
            <i class="fas fa-mobile-screen-button me-2"></i> More Sharing Options (Device)
        </button>
    </div>
</div>

<!-- html2pdf Client-side High-Resolution PDF Generator -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function openShareModal() {
    var modal = document.getElementById('shareModal');
    if (modal) modal.style.display = 'flex';
    if (navigator.share) {
        var nBtn = document.getElementById('btnNativeShare');
        if (nBtn) nBtn.style.display = 'block';
    }
}

function closeShareModal() {
    var modal = document.getElementById('shareModal');
    if (modal) modal.style.display = 'none';
}

function copyShareUrl() {
    var inp = document.getElementById('shareUrlInput');
    var btn = document.getElementById('btnCopyShareLink');
    if (!inp) return;
    inp.select();
    inp.setSelectionRange(0, 99999);
    var orig = btn.innerHTML;

    function done() {
        btn.innerHTML = '<i class="fas fa-check me-1"></i> Copied!';
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-success');
        setTimeout(function() {
            btn.innerHTML = orig;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-primary');
        }, 2000);
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(inp.value).then(done).catch(function() {
            document.execCommand('copy');
            done();
        });
    } else {
        document.execCommand('copy');
        done();
    }
}

function triggerNativeShare() {
    var inp = document.getElementById('shareUrlInput');
    var url = inp ? inp.value : window.location.href;
    if (navigator.share) {
        navigator.share({
            title: <?php echo json_encode($share_title); ?>,
            text: <?php echo json_encode($share_text); ?>,
            url: url
        }).catch(function(err) {
            console.log('Share dismissed:', err);
        });
    }
}

function filterPriceListCategory(catId) {
    var url = new URL(window.location.href);
    if (catId === 'all') {
        url.searchParams.delete('category');
        url.searchParams.delete('cat');
        url.searchParams.delete('category_id');
    } else {
        url.searchParams.set('category', catId);
    }
    url.searchParams.delete('download');
    window.location.href = url.toString();
}

function downloadPDF() {
    var btn = document.getElementById('btnDownloadPdf');
    var origText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Building PDF...';
    btn.disabled = true;

    var element = document.getElementById('priceListDocument');
    var catSuffix = <?php echo json_encode(!empty($active_cat_slug) ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $active_cat_slug) : (!empty($active_cat_name) ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $active_cat_name) : 'All_Products')); ?>;
    var filename = '<?php echo preg_replace('/[^a-zA-Z0-9_-]/', '_', $store_name); ?>_Price_List_' + catSuffix + '_<?php echo date('Y'); ?>.pdf';

    var opt = {
        margin: [6, 6, 8, 6],
        filename: filename,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, logging: false },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    html2pdf().set(opt).from(element).save().then(function() {
        btn.innerHTML = origText;
        btn.disabled = false;
    }).catch(function(err) {
        console.warn('PDF generation fallback to print:', err);
        btn.innerHTML = origText;
        btn.disabled = false;
        window.print();
    });
}

<?php if ($auto_download): ?>
window.addEventListener('load', function() {
    setTimeout(downloadPDF, 750);
});
<?php endif; ?>
</script>
</body>
</html>
