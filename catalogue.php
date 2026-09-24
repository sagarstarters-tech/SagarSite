<?php
/**
 * ============================================================
 *  Professional Product Catalogue (PDF) — Public Portal
 *  Location: /catalogue.php
 * ============================================================
 *  Publicly accessible: Anyone (customers, dealers, farmers,
 *  contractors) can view, print and download the official catalogue.
 *  Editorial brochure design inspired by modern industrial catalogs.
 * ============================================================
 */

include_once __DIR__ . '/includes/session_setup.php';
include_once __DIR__ . '/includes/db_connect.php';

$user_role = $_SESSION['role'] ?? 'guest';
$user_name = $_SESSION['name'] ?? '';
$is_admin  = ($user_role === 'admin');

// ── 1. Master Feature Toggle Check ───────────────────────────
$cat_enabled = ($global_settings['catalogue_enabled'] ?? '1') === '1';

if (!$cat_enabled && !$is_admin) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Catalogue Updating — <?php echo htmlspecialchars($global_settings['site_name'] ?? "Sagar Starter's"); ?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.0/mdb.min.css" rel="stylesheet"/>
        <style>
            body { background: #f4f8f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
            .card-notice { max-width: 520px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); }
        </style>
    </head>
    <body>
        <div class="card card-notice border-0 p-4 p-md-5 text-center bg-white m-3">
            <div class="mb-3 text-primary">
                <i class="fas fa-book-reader fa-4x" style="color: #0b3344;"></i>
            </div>
            <h3 class="fw-bold mb-2 text-dark">Catalogue Under Revision</h3>
            <p class="text-muted mb-4">
                Our product catalogue is currently being updated with new motor starter models and technical specifications. Please check back soon or browse our online shop.
            </p>
            <div class="d-flex justify-content-center gap-2">
                <a href="<?php echo SITE_URL; ?>/shop.php" class="btn text-white rounded-pill px-4" style="background: #0b3344;">
                    <i class="fas fa-store me-1"></i> Browse Shop
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

// ── 2. Check for Custom Uploaded PDF Brochure Mode ────────────
$cat_source     = $global_settings['catalogue_source'] ?? 'dynamic';
$custom_pdf_rel = $global_settings['catalogue_custom_pdf'] ?? '';
$custom_pdf_abs = !empty($custom_pdf_rel) ? (BASE_PATH . '/' . ltrim($custom_pdf_rel, '/')) : '';

if ($cat_source === 'custom_pdf' && !empty($custom_pdf_abs) && file_exists($custom_pdf_abs)) {
    $is_download   = isset($_GET['download']) && $_GET['download'] == '1';
    $download_name = "Sagar_Starters_Official_Catalogue_" . date('Y') . ".pdf";

    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($is_download ? 'attachment' : 'inline') . '; filename="' . $download_name . '"');
    header('Content-Length: ' . filesize($custom_pdf_abs));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    readfile($custom_pdf_abs);
    exit;
}

// ── 3. Dynamic Visual Catalogue — Query Categories & Products from DB ─
// Admin configured selected categories (multi-select checkboxes)
$raw_cat_selection = $global_settings['catalogue_selected_categories'] ?? '';
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
    SELECT c.id, c.name, c.slug, COUNT(p.id) as product_count 
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
$configured_filter = $global_settings['catalogue_filter'] ?? 'all';
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
        p.short_description, 
        p.description, 
        p.features, 
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
} elseif ($configured_filter === 'trending_only' && empty($url_category)) {
    $sql .= " AND p.is_trending = 1 ";
}

$sql .= " ORDER BY COALESCE(c.name, 'General Products') ASC, p.name ASC";

$products_res = $conn->query($sql);
$catalog = [];
$total_matched_items = 0;

if ($products_res) {
    while ($row = $products_res->fetch_assoc()) {
        $cat = !empty($row['category_name']) ? trim($row['category_name']) : 'General Starters & Panels';
        $catalog[$cat][] = $row;
        $total_matched_items++;
    }
}

// ── 4. Layout Configurations & Tokens ─────────────────────────
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

$doc_title    = $global_settings['catalogue_title'] ?? "Sagar Starter's — Official Product Catalogue";
$doc_subtitle = $global_settings['catalogue_subtitle'] ?? "Agricultural & Industrial Motor Starters, Panels & Spares";
$doc_about    = $global_settings['catalogue_about'] ?? "Sagar Starter's is a trusted Indian manufacturer specializing in heavy-duty single phase and three phase motor starters, submersible pump control panels, and industrial switchgear. Engineered with 100% electrolytic grade copper contacts, precision thermal overload relays, and weather-resistant powder-coated sheet metal enclosures, our products ensure unmatched motor protection and longevity across agricultural and industrial applications.";

$show_price      = ($global_settings['catalogue_show_price'] ?? '1') === '1';
$show_sale_price = ($global_settings['catalogue_show_sale_price'] ?? '1') === '1';
$show_sku        = ($global_settings['catalogue_show_sku'] ?? '1') === '1';
$show_features   = ($global_settings['catalogue_show_features'] ?? '1') === '1';
$show_specs      = ($global_settings['catalogue_show_specs'] ?? '1') === '1';
$show_share      = ($global_settings['catalogue_show_share'] ?? '1') === '1';

$share_url       = SITE_URL . '/catalogue.php' . (!empty($active_cat_id) ? '?category=' . urlencode($active_cat_id) : '');
$share_title     = $doc_title;
$share_text      = "Check out " . $store_name . "'s Official Product Catalogue: " . $share_url;

$auto_download = isset($_GET['download']) && $_GET['download'] == '1';
$wa_phone_clean = preg_replace('/[^0-9]/', '', $store_phone);

// Determine Cover Spotlight Image (Pick first high-res product or flagship asset)
$cover_spotlight_img = '';
if (!empty($catalog)) {
    foreach ($catalog as $cname => $prods) {
        foreach ($prods as $p) {
            if (!empty($p['image'])) {
                $cover_spotlight_img = function_exists('resolve_image_url') ? resolve_image_url($p['image']) : '';
                if (!empty($cover_spotlight_img)) break 2;
            }
        }
    }
}
if (empty($cover_spotlight_img)) {
    if (file_exists(__DIR__ . '/assets/images/single hp dgt.webp')) {
        $cover_spotlight_img = ASSETS_URL . '/images/single hp dgt.webp';
    } elseif (file_exists(__DIR__ . '/assets/images/star delta gi.webp')) {
        $cover_spotlight_img = ASSETS_URL . '/images/star delta gi.webp';
    } elseif (file_exists(__DIR__ . '/assets/images/hero_product_1774248820.webp')) {
        $cover_spotlight_img = ASSETS_URL . '/images/hero_product_1774248820.webp';
    } else {
        $cover_spotlight_img = $logo_url;
    }
}

// ── Open Graph & Social Share Metadata ───────────────────────
$page_html_title = (stripos($doc_title, $store_name) !== false) ? $doc_title : ($doc_title . ' — ' . $store_name);
$og_description  = !empty($doc_subtitle) ? $doc_subtitle : (isset($doc_about) ? substr(strip_tags($doc_about), 0, 160) : "Explore Sagar Starter's official product catalogue for heavy-duty motor starters, submersible control panels & switchgear.");

$og_base_url = (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1')
    ? (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
    : (defined('SITE_URL') && strpos(SITE_URL, 'http') === 0 ? rtrim(SITE_URL, '/') : 'https://www.sagarstarters.com');

$og_banner_file  = __DIR__ . '/assets/images/catalogue_og_banner.jpg';
$og_banner_v     = file_exists($og_banner_file) ? filemtime($og_banner_file) : '2026';
$og_image_url    = $og_base_url . '/assets/images/catalogue_og_banner.jpg?v=' . $og_banner_v;
$og_image_secure = (strpos($og_image_url, 'http://') === 0) ? preg_replace('/^http:/', 'https:', $og_image_url) : $og_image_url;
$og_canonical_url = $og_base_url . '/catalogue.php' . (!empty($active_cat_id) ? '?category=' . urlencode($active_cat_id) : '');
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

    <!-- Typography & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.0/mdb.min.css" rel="stylesheet"/>

    <style>
        /* ── Design System & Theme Variables ───────────────────── */
        :root {
            --cat-teal-dark: #0a2e3d;
            --cat-teal-deep: #061f2a;
            --cat-teal-medium: #114256;
            --cat-sage: #6b9597;
            --cat-sage-light: #8faeaf;
            --cat-slate-light: #f4f8f9;
            --cat-slate-soft: #eaf1f3;
            --cat-border: #d5e2e5;
            --cat-border-light: #e6eff1;
            --cat-text-main: #08232f;
            --cat-text-muted: #5c707a;
            --cat-gold: #e6a117;
            --cat-white: #ffffff;
            --cat-shadow-subtle: 0 4px 18px rgba(10, 46, 61, 0.06);
            --cat-shadow-card: 0 6px 24px rgba(10, 46, 61, 0.08);
            --cat-radius: 12px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #dfe8eb;
            color: var(--cat-text-main);
            line-height: 1.55;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ── Sticky Top Action Bar (Screen Only) ───────────────── */
        #action-bar {
            position: sticky;
            top: 0;
            z-index: 9999;
            background: linear-gradient(135deg, #061c26 0%, #0a2e3d 100%);
            border-bottom: 2px solid rgba(107, 149, 151, 0.35);
            padding: 10px 22px;
            color: #ffffff;
            box-shadow: 0 4px 25px rgba(6, 28, 38, 0.4);
        }
        .action-bar-inner {
            max-width: 1180px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .action-branding {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .action-branding i {
            color: var(--cat-sage-light);
            font-size: 1.35rem;
        }
        .action-btn-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .btn-act {
            border: none;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            text-decoration: none;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            white-space: nowrap;
        }
        .btn-act-pdf {
            background: #dc2626;
            color: #ffffff;
            box-shadow: 0 3px 12px rgba(220, 38, 38, 0.35);
        }
        .btn-act-pdf:hover { background: #b91c1c; color: #ffffff; transform: translateY(-1px); }
        .btn-act-print {
            background: var(--cat-teal-medium);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .btn-act-print:hover { background: #16536c; color: #ffffff; transform: translateY(-1px); }
        .btn-act-share {
            background: var(--cat-sage);
            color: #ffffff;
            box-shadow: 0 3px 12px rgba(107, 149, 151, 0.35);
        }
        .btn-act-share:hover { background: #567e80; color: #ffffff; transform: translateY(-1px); }
        .btn-act-wa {
            background: #10b981;
            color: #ffffff;
            box-shadow: 0 3px 12px rgba(16, 185, 129, 0.35);
        }
        .btn-act-wa:hover { background: #059669; color: #ffffff; }
        .btn-act-store {
            background: rgba(255, 255, 255, 0.12);
            color: #e5eff1;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .btn-act-store:hover { background: rgba(255, 255, 255, 0.22); color: #ffffff; }
        .btn-act-admin {
            background: rgba(230, 161, 23, 0.18);
            color: #fbbf24;
            border: 1px solid rgba(251, 191, 36, 0.35);
        }
        .btn-act-admin:hover { background: rgba(230, 161, 23, 0.3); color: #fef3c7; }

        .cat-filter-select {
            background-color: rgba(255, 255, 255, 0.12);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 50px;
            padding: 6px 14px;
            font-size: 0.8rem;
            font-weight: 600;
            max-width: 240px;
            cursor: pointer;
            outline: none;
            transition: all 0.2s ease;
        }
        .cat-filter-select:focus {
            border-color: var(--cat-sage-light);
            background-color: rgba(255, 255, 255, 0.2);
        }
        .cat-filter-select option {
            background: #061c26;
            color: #ffffff;
        }

        /* ── Main Catalogue Page Document Wrapper ─────────────── */
        .catalogue-wrapper {
            padding: 30px 15px 60px 15px;
            display: flex;
            justify-content: center;
        }
        .catalogue-doc {
            width: 100%;
            max-width: 1040px;
            background: var(--cat-white);
            border-radius: 14px;
            box-shadow: 0 16px 50px rgba(6, 28, 38, 0.12);
            box-sizing: border-box;
            overflow: hidden;
            position: relative;
        }

        /* ══════════════════════════════════════════════════════════
           FRONT COVER — BROCHURE STYLE (MATCHING SCREENSHOT 1)
           ══════════════════════════════════════════════════════════ */
        .cover-page {
            position: relative;
            background: #ffffff;
            overflow: hidden;
            border-bottom: 2px solid var(--cat-border);
            break-after: page;
            page-break-after: always;
        }

        /* Top split section (Dark Teal left ~60%, White right ~40%) */
        .cover-top-split {
            position: relative;
            display: flex;
            min-height: 340px;
            background: #ffffff;
        }
        .cover-top-dark {
            flex: 0 0 62%;
            background: var(--cat-teal-dark);
            color: #ffffff;
            padding: 40px 45px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            z-index: 1;
        }
        .cover-top-white {
            flex: 0 0 38%;
            background: #ffffff;
            padding: 40px 40px;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: flex-end;
            position: relative;
            z-index: 1;
        }

        /* Logo box inside dark teal block */
        .cover-logo-box {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            background: rgba(255, 255, 255, 0.95);
            padding: 8px 18px;
            border-radius: 10px;
            width: fit-content;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
        }
        .cover-logo-img {
            max-height: 52px;
            max-width: 170px;
            object-fit: contain;
        }
        .cover-brand-tagline {
            font-size: 0.72rem;
            color: rgba(255, 255, 255, 0.8);
            letter-spacing: 1.5px;
            text-transform: uppercase;
            font-weight: 600;
            margin-top: 15px;
        }

        /* Year Number on Top Right White Block */
        .cover-edition-year {
            font-family: 'Montserrat', sans-serif;
            font-size: 3.5rem;
            font-weight: 900;
            line-height: 1;
            color: var(--cat-teal-dark);
            letter-spacing: -1.5px;
            text-align: right;
        }
        .cover-edition-label {
            font-size: 0.75rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--cat-sage);
            margin-top: 4px;
            text-align: right;
        }

        /* Overlapping Circular Spotlight Photo */
        .cover-circle-frame {
            position: absolute;
            left: 54%;
            top: 50%;
            transform: translate(-50%, -46%);
            width: 270px;
            height: 270px;
            border-radius: 50%;
            border: 10px solid #ffffff;
            box-shadow: 0 16px 40px rgba(10, 46, 61, 0.28);
            overflow: hidden;
            background: var(--cat-slate-soft);
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .cover-circle-frame img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        .cover-circle-frame:hover img {
            transform: scale(1.05);
        }

        /* Lower Cover Section (Clean White with Big Bold Editorial Title) */
        .cover-bottom-area {
            position: relative;
            padding: 50px 48px 45px 48px;
            background: #ffffff;
            z-index: 2;
        }
        .editorial-pretitle {
            font-size: 0.82rem;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--cat-sage);
            font-weight: 700;
            margin-bottom: 6px;
        }
        .editorial-cover-h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 3.6rem;
            font-weight: 900;
            line-height: 0.95;
            color: var(--cat-teal-dark);
            letter-spacing: -1.5px;
            margin-bottom: 12px;
            text-transform: uppercase;
        }
        .editorial-cover-sub {
            font-size: 1.08rem;
            font-weight: 500;
            color: var(--cat-text-muted);
            max-width: 620px;
            line-height: 1.5;
            margin-bottom: 25px;
        }

        /* Trust Badges on Cover */
        .cover-feature-pills {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }
        .cover-pill {
            background: var(--cat-slate-light);
            border: 1px solid var(--cat-border);
            color: var(--cat-teal-dark);
            font-size: 0.76rem;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 30px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .cover-pill i {
            color: var(--cat-sage);
        }

        /* Sage Teal Accent Block (Bottom Right, exactly as in screenshot 1) */
        .cover-sage-block {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 150px;
            height: 65px;
            background: var(--cat-sage);
            border-top-left-radius: 6px;
            z-index: 3;
        }

        /* Cover Footer Contacts Row */
        .cover-footer-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            padding-top: 20px;
            border-top: 1px solid var(--cat-border-light);
            font-size: 0.78rem;
            color: var(--cat-text-muted);
            max-width: calc(100% - 170px);
        }
        .cover-footer-meta i {
            color: var(--cat-teal-medium);
            margin-right: 6px;
        }

        /* ══════════════════════════════════════════════════════════
           PAGE 2 / SPREAD: WELCOME & TABLE OF CONTENTS
           ══════════════════════════════════════════════════════════ */
        .spread-welcome-toc {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: #ffffff;
            border-bottom: 2px solid var(--cat-border);
            break-inside: avoid;
            page-break-inside: avoid;
        }

        /* Left Side: Welcome / About Us */
        .welcome-pane {
            padding: 42px 40px;
            background: #ffffff;
            border-right: 1px solid var(--cat-border-light);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .welcome-circle-badge {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: var(--cat-slate-light);
            border: 4px solid var(--cat-sage);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            color: var(--cat-teal-dark);
            font-size: 2.2rem;
            box-shadow: 0 6px 16px rgba(107, 149, 151, 0.25);
        }
        .welcome-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--cat-teal-dark);
            letter-spacing: -0.5px;
            margin-bottom: 12px;
            text-transform: uppercase;
        }
        .welcome-text {
            font-size: 0.84rem;
            color: var(--cat-text-muted);
            line-height: 1.7;
            margin-bottom: 24px;
        }
        .welcome-features-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            padding-top: 18px;
            border-top: 1px solid var(--cat-border-light);
        }
        .wf-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--cat-teal-dark);
        }
        .wf-item i {
            color: var(--cat-sage);
            margin-top: 3px;
        }

        /* Right Side: Table of Contents (Dark Teal Background) */
        .toc-pane {
            padding: 42px 40px;
            background: var(--cat-teal-dark);
            color: #ffffff;
            display: flex;
            flex-direction: column;
        }
        .toc-header {
            margin-bottom: 26px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            padding-bottom: 15px;
        }
        .toc-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #ffffff;
            margin: 0;
            text-transform: uppercase;
        }
        .toc-subtitle {
            font-size: 0.78rem;
            color: var(--cat-sage-light);
            letter-spacing: 1.5px;
            text-transform: uppercase;
            font-weight: 600;
            margin-top: 4px;
        }

        /* TOC Items Grid */
        .toc-grid {
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex-grow: 1;
        }
        .toc-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            text-decoration: none;
            color: #ffffff;
            transition: all 0.2s ease;
        }
        .toc-row:hover {
            background: rgba(255, 255, 255, 0.14);
            border-color: var(--cat-sage);
            transform: translateX(4px);
            color: #ffffff;
        }
        .toc-num-box {
            width: 32px;
            height: 32px;
            background: #ffffff;
            color: var(--cat-teal-dark);
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 0.85rem;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-right: 12px;
        }
        .toc-info {
            flex-grow: 1;
        }
        .toc-name {
            font-family: 'Montserrat', sans-serif;
            font-size: 0.86rem;
            font-weight: 700;
            color: #ffffff;
        }
        .toc-count {
            font-size: 0.72rem;
            color: var(--cat-sage-light);
        }
        .toc-arrow {
            color: rgba(255, 255, 255, 0.4);
            font-size: 0.85rem;
        }

        /* ══════════════════════════════════════════════════════════
           QUALITY SPECIFICATIONS STRIP
           ══════════════════════════════════════════════════════════ */
        .specs-strip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            padding: 24px 45px;
            background: var(--cat-slate-light);
            border-bottom: 2px solid var(--cat-border);
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .spec-strip-card {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 14px;
            background: #ffffff;
            border: 1px solid var(--cat-border);
            border-radius: 8px;
        }
        .spec-icon-box {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: var(--cat-teal-dark);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .spec-strip-title {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--cat-teal-dark);
            line-height: 1.3;
        }
        .spec-strip-desc {
            font-size: 0.68rem;
            color: var(--cat-text-muted);
        }

        /* ══════════════════════════════════════════════════════════
           CATEGORY HEADER BANDS & PRODUCT CARDS GRID
           ══════════════════════════════════════════════════════════ */
        .cat-content-body {
            padding: 35px 45px;
            background: #ffffff;
        }

        /* Category Heading Bar (Deep Teal Band with Sage Accent) */
        .category-editorial-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--cat-teal-dark);
            color: #ffffff;
            padding: 14px 22px;
            border-radius: 8px;
            margin-top: 36px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            break-after: avoid;
            page-break-after: avoid;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .category-editorial-bar:first-child {
            margin-top: 0;
        }
        .category-editorial-bar::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 12px;
            height: 100%;
            background: var(--cat-sage);
        }
        .cat-bar-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.18rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .cat-bar-count-badge {
            background: var(--cat-sage);
            color: #ffffff;
            font-family: 'Poppins', sans-serif;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            letter-spacing: 0.5px;
        }

        /* 2-Column Product Grid (Matches Brochure Spreads 4 & 5) */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 22px;
            margin-bottom: 30px;
        }
        .cat-prod-card {
            border: 1px solid var(--cat-border);
            border-radius: 10px;
            padding: 16px;
            background: #ffffff;
            display: flex;
            gap: 16px;
            align-items: flex-start;
            position: relative;
            break-inside: avoid !important;
            page-break-inside: avoid !important;
            -webkit-column-break-inside: avoid !important;
            box-shadow: 0 2px 10px rgba(10, 46, 61, 0.04);
            box-sizing: border-box;
            transition: all 0.25s ease;
        }
        .cat-prod-card:hover {
            border-color: var(--cat-sage);
            box-shadow: 0 8px 24px rgba(10, 46, 61, 0.09);
            transform: translateY(-2px);
        }

        /* Product Thumbnail */
        .cat-prod-thumb-box {
            width: 125px;
            height: 125px;
            flex-shrink: 0;
            border-radius: 8px;
            border: 1px solid var(--cat-border-light);
            background: var(--cat-slate-light);
            padding: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .cat-prod-thumb {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            transition: transform 0.3s ease;
        }
        .cat-prod-card:hover .cat-prod-thumb {
            transform: scale(1.06);
        }

        /* Product Details */
        .cat-prod-details {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .cat-prod-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 0.98rem;
            font-weight: 800;
            color: var(--cat-teal-dark);
            margin-bottom: 6px;
            line-height: 1.35;
        }
        .cat-meta-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }
        .cat-code-badge {
            font-family: monospace;
            font-size: 0.7rem;
            font-weight: 700;
            background: var(--cat-slate-soft);
            color: var(--cat-teal-dark);
            padding: 2px 8px;
            border-radius: 4px;
            border: 1px solid var(--cat-border);
        }
        .cat-brand-badge {
            font-size: 0.68rem;
            font-weight: 700;
            background: rgba(107, 149, 151, 0.15);
            color: var(--cat-teal-dark);
            padding: 2px 8px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .cat-prod-desc {
            font-size: 0.74rem;
            color: var(--cat-text-muted);
            line-height: 1.45;
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex-grow: 1;
        }

        /* Price & Action Box */
        .cat-prod-bottom-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            padding-top: 8px;
            border-top: 1px dashed var(--cat-border-light);
            margin-top: auto;
        }
        .cat-price-wrapper {
            display: flex;
            align-items: baseline;
            gap: 6px;
        }
        .cat-price-tag {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.05rem;
            font-weight: 900;
            color: var(--cat-teal-dark);
        }
        .cat-price-mrp {
            font-size: 0.72rem;
            text-decoration: line-through;
            color: #94a3b8;
        }
        .btn-card-inquire {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s ease;
        }
        .btn-card-inquire:hover {
            background: #16a34a;
            color: #ffffff;
            border-color: #16a34a;
        }

        /* ══════════════════════════════════════════════════════════
           "OUR OFFER / VALUE PROPOSITION" FEATURE BANNER
           ══════════════════════════════════════════════════════════ */
        .our-offer-banner {
            background: var(--cat-teal-dark);
            color: #ffffff;
            border-radius: 10px;
            padding: 28px 32px;
            margin: 35px 0 25px 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            align-items: center;
            break-inside: avoid;
            page-break-inside: avoid;
            position: relative;
            overflow: hidden;
        }
        .our-offer-banner::before {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 100px;
            height: 8px;
            background: var(--cat-sage);
        }
        .offer-left h3 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.45rem;
            font-weight: 800;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: -0.5px;
        }
        .offer-left p {
            font-size: 0.8rem;
            color: var(--cat-sage-light);
            line-height: 1.6;
            margin: 0;
        }
        .offer-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .offer-box {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            padding: 10px 14px;
            border-radius: 8px;
        }
        .offer-box-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 0.78rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 2px;
        }
        .offer-box-sub {
            font-size: 0.68rem;
            color: rgba(255, 255, 255, 0.7);
        }

        /* ══════════════════════════════════════════════════════════
           BACK COVER / DEALERSHIP & CLOSING
           ══════════════════════════════════════════════════════════ */
        .cat-back-cover {
            background: var(--cat-teal-deep);
            color: #ffffff;
            padding: 45px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 30px;
            break-inside: avoid !important;
            page-break-inside: avoid !important;
            position: relative;
        }
        .cat-back-cover::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 0;
            width: 140px;
            height: 50px;
            background: var(--cat-sage);
        }
        .inquiry-block {
            max-width: 580px;
            z-index: 1;
        }
        .inquiry-block h3 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.45rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .inquiry-block p {
            font-size: 0.82rem;
            color: var(--cat-sage-light);
            margin-bottom: 16px;
            line-height: 1.6;
        }
        .inquiry-contacts {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            font-size: 0.8rem;
            color: #ffffff;
        }
        .inquiry-contacts a {
            color: var(--cat-sage-light);
            text-decoration: none;
            font-weight: 600;
        }
        .inquiry-contacts a:hover {
            color: #ffffff;
            text-decoration: underline;
        }
        .seal-stamp-box {
            background: rgba(255, 255, 255, 0.06);
            border: 2px dashed rgba(255, 255, 255, 0.25);
            border-radius: 12px;
            padding: 20px 28px;
            text-align: center;
            min-width: 220px;
            z-index: 1;
        }
        .seal-stamp-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 0.95rem;
            color: #ffffff;
        }
        .seal-stamp-icon {
            font-size: 2.2rem;
            color: var(--cat-gold);
            margin: 8px 0;
        }
        .seal-stamp-sub {
            font-size: 0.68rem;
            color: rgba(255, 255, 255, 0.6);
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* ── Social Share & Copy Link Modal ────────────────────── */
        .share-modal-backdrop {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(6, 28, 38, 0.75);
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
            color: var(--cat-teal-dark);
        }
        .share-modal-sub {
            font-size: 0.78rem;
            color: var(--cat-text-muted);
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
        .share-copy-box {
            display: flex;
            gap: 8px;
            background: var(--cat-slate-light);
            border: 1px solid var(--cat-border);
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
            color: #ffffff !important;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .share-social-btn:hover { transform: translateY(-2px); }
        .share-wa { background: #25d366; }
        .share-tg { background: #229ed9; }
        .share-fb { background: #1877f2; }
        .share-x  { background: #0a2e3d; }

        /* ── Mobile Responsive Adjustments ─────────────────────── */
        @media (max-width: 900px) {
            .cover-top-split { flex-direction: column; min-height: auto; }
            .cover-top-dark { flex: none; width: 100%; padding: 30px 24px; }
            .cover-top-white { flex: none; width: 100%; padding: 20px 24px; align-items: flex-start; }
            .cover-edition-year { font-size: 2.8rem; text-align: left; }
            .cover-edition-label { text-align: left; }
            .cover-circle-frame {
                position: relative;
                left: auto;
                top: auto;
                transform: none;
                margin: -40px auto 20px auto;
                width: 200px;
                height: 200px;
            }
            .cover-bottom-area { padding: 30px 24px 30px 24px; }
            .editorial-cover-h1 { font-size: 2.5rem; }
            .cover-sage-block { width: 90px; height: 40px; }
            .cover-footer-meta { max-width: 100%; }

            .spread-welcome-toc { grid-template-columns: 1fr; }
            .welcome-pane { padding: 30px 24px; border-right: none; border-bottom: 1px solid var(--cat-border-light); }
            .toc-pane { padding: 30px 24px; }

            .specs-strip { grid-template-columns: 1fr 1fr; padding: 20px 24px; }
            .cat-content-body { padding: 25px 20px; }
            .product-grid { grid-template-columns: 1fr; }
            .our-offer-banner { grid-template-columns: 1fr; padding: 24px 20px; }
            .cat-back-cover { padding: 30px 24px; flex-direction: column; align-items: flex-start; }
        }

        /* ── Discrete A4 PDF Export Staging Styles ─────────────────── */
        .pdf-staging-container {
            position: absolute !important;
            left: -9999px !important;
            top: 0 !important;
            width: 1040px !important;
            background: #ffffff !important;
            z-index: -9999 !important;
            box-sizing: border-box !important;
        }

        .pdf-a4-page {
            width: 1040px !important;
            min-height: 1470px !important;
            max-height: 1470px !important;
            height: 1470px !important;
            box-sizing: border-box !important;
            background: #ffffff !important;
            position: relative !important;
            overflow: hidden !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: space-between !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .pdf-running-header {
            background: #061c26 !important;
            color: #ffffff !important;
            font-family: 'Montserrat', sans-serif !important;
            font-size: 0.72rem !important;
            font-weight: 700 !important;
            letter-spacing: 1px !important;
            text-transform: uppercase !important;
            padding: 10px 35px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            border-bottom: 2px solid #6b9597 !important;
            flex-shrink: 0 !important;
        }

        .pdf-running-footer {
            background: #f8fafc !important;
            color: #64748b !important;
            font-family: 'Poppins', sans-serif !important;
            font-size: 0.72rem !important;
            font-weight: 600 !important;
            padding: 10px 35px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            border-top: 1px solid #e2e8f0 !important;
            flex-shrink: 0 !important;
            margin-top: auto !important;
        }

        .pdf-page-content {
            flex: 1 1 auto !important;
            padding: 24px 35px !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: flex-start !important;
            background: #ffffff !important;
            box-sizing: border-box !important;
        }

        .pdf-page-cover {
            justify-content: flex-start !important;
            background: #ffffff !important;
        }

        .pdf-page-cover .cover-page {
            border-bottom: none !important;
            height: 100% !important;
            min-height: 1470px !important;
            max-height: 1470px !important;
            display: flex !important;
            flex-direction: column !important;
            box-sizing: border-box !important;
        }

        .pdf-page-cover .cover-top-split {
            min-height: 560px !important;
            height: 560px !important;
            flex: 0 0 560px !important;
            position: relative !important;
            display: flex !important;
        }

        .pdf-page-cover .cover-top-dark {
            flex: 0 0 62% !important;
            background: #0a2e3d !important;
            padding: 45px 50px !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: space-between !important;
        }

        .pdf-page-cover .cover-top-white {
            flex: 0 0 38% !important;
            background: #ffffff !important;
            padding: 45px 50px !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: flex-start !important;
            align-items: flex-end !important;
        }

        .pdf-page-cover .cover-edition-year {
            font-size: 3.8rem !important;
            font-weight: 900 !important;
            color: #0a2e3d !important;
            line-height: 1 !important;
            text-align: right !important;
        }

        .pdf-page-cover .cover-edition-label {
            font-size: 0.82rem !important;
            letter-spacing: 2.5px !important;
            text-transform: uppercase !important;
            font-weight: 700 !important;
            color: #6b9597 !important;
            margin-top: 4px !important;
            text-align: right !important;
        }

        .pdf-page-cover .cover-circle-frame {
            position: absolute !important;
            width: 320px !important;
            height: 320px !important;
            left: 410px !important;
            top: 120px !important;
            transform: none !important;
            border-radius: 50% !important;
            border: 12px solid #ffffff !important;
            box-shadow: 0 16px 40px rgba(10, 46, 61, 0.28) !important;
            overflow: hidden !important;
            background: #eef3f5 !important;
            z-index: 10 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .pdf-page-cover .cover-bottom-area {
            flex: 1 1 auto !important;
            padding: 45px 50px 35px 50px !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: flex-start !important;
            gap: 14px !important;
            position: relative !important;
            background: #ffffff !important;
        }

        .pdf-page-cover .editorial-pretitle {
            font-size: 0.85rem !important;
            letter-spacing: 3.5px !important;
            text-transform: uppercase !important;
            color: #6b9597 !important;
            font-weight: 700 !important;
            margin-bottom: 2px !important;
        }

        .pdf-page-cover .editorial-cover-h1 {
            font-family: 'Montserrat', sans-serif !important;
            font-size: 3.8rem !important;
            font-weight: 900 !important;
            line-height: 0.95 !important;
            color: #0a2e3d !important;
            letter-spacing: -1.5px !important;
            margin: 0 0 4px 0 !important;
            text-transform: uppercase !important;
        }

        .pdf-page-cover .editorial-cover-sub {
            font-size: 1.1rem !important;
            font-weight: 500 !important;
            color: #475569 !important;
            max-width: 660px !important;
            line-height: 1.5 !important;
            margin: 0 0 12px 0 !important;
        }

        .pdf-page-cover .cover-feature-pills {
            display: flex !important;
            gap: 10px !important;
            flex-wrap: wrap !important;
            margin: 0 0 18px 0 !important;
        }

        .pdf-page-cover .cover-pill {
            background: #f1f6f8 !important;
            border: 1px solid #cbd5e1 !important;
            color: #0a2e3d !important;
            font-size: 0.78rem !important;
            font-weight: 600 !important;
            padding: 7px 16px !important;
            border-radius: 30px !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 7px !important;
        }

        .pdf-page-cover .cover-footer-meta {
            margin-top: auto !important;
            display: grid !important;
            grid-template-columns: repeat(4, 1fr) !important;
            gap: 12px !important;
            padding-top: 20px !important;
            border-top: 1px solid #e2e8f0 !important;
            font-size: 0.76rem !important;
            color: #64748b !important;
            max-width: calc(100% - 170px) !important;
        }

        .pdf-page-cover .cover-sage-block {
            position: absolute !important;
            bottom: 0 !important;
            right: 0 !important;
            width: 160px !important;
            height: 70px !important;
            background: #6b9597 !important;
            border-top-left-radius: 6px !important;
            z-index: 3 !important;
        }

        /* Ensure product grid inside PDF page is strictly 2 columns */
        .pdf-staging-container .product-grid {
            display: grid !important;
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 14px !important;
            margin-bottom: 14px !important;
        }

        .pdf-staging-container .cat-prod-card {
            padding: 10px 14px !important;
            gap: 12px !important;
            min-height: 175px !important;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04) !important;
            border: 1px solid #e2e8f0 !important;
            background: #ffffff !important;
        }

        .pdf-staging-container .cat-prod-thumb-box {
            width: 95px !important;
            height: 95px !important;
            flex-shrink: 0 !important;
        }

        .pdf-staging-container .cat-prod-title {
            font-size: 0.9rem !important;
            line-height: 1.3 !important;
            margin-bottom: 4px !important;
        }

        .pdf-staging-container .cat-prod-desc {
            font-size: 0.7rem !important;
            line-height: 1.35 !important;
            margin-bottom: 4px !important;
            display: -webkit-box !important;
            -webkit-line-clamp: 2 !important;
            -webkit-box-orient: vertical !important;
            overflow: hidden !important;
        }

        .pdf-staging-container .cat-price-tag {
            font-size: 1rem !important;
        }

        .pdf-staging-container .category-editorial-bar {
            padding: 10px 18px !important;
            margin-top: 14px !important;
            margin-bottom: 12px !important;
            border-radius: 6px !important;
        }
        .pdf-staging-container .category-editorial-bar:first-child {
            margin-top: 0 !important;
        }

        .pdf-staging-container .cat-bar-title {
            font-size: 1.05rem !important;
        }

        .pdf-staging-container .our-offer-banner {
            margin: 14px 0 !important;
            padding: 18px 22px !important;
            gap: 16px !important;
            border-radius: 8px !important;
        }

        .pdf-staging-container .cat-back-cover {
            padding: 30px 35px !important;
            background: #061c26 !important;
            color: #ffffff !important;
            border-radius: 8px !important;
        }

        /* ── Print Media Optimization (Standard A4 Portrait) ─── */
        @page {
            size: A4 portrait;
            margin: 8mm 8mm 10mm 8mm;
        }
        @media print {
            body { background: #ffffff !important; color: #000000 !important; }
            #action-bar, .no-print, .btn-card-inquire { display: none !important; }
            .catalogue-wrapper { padding: 0 !important; }
            .catalogue-doc {
                box-shadow: none !important;
                border-radius: 0 !important;
                max-width: 100% !important;
            }
            .cover-page {
                page-break-after: always !important;
                break-after: page !important;
            }
            .spread-welcome-toc {
                page-break-after: always !important;
                break-after: page !important;
            }
            .cat-prod-card {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
            .category-editorial-bar {
                page-break-after: avoid !important;
                break-after: avoid !important;
            }
            .our-offer-banner {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
            .cat-back-cover {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
        }
    </style>
</head>
<body>

<!-- Sticky Top Action Bar (Screen Only) -->
<header id="action-bar">
    <div class="action-bar-inner">
        <div class="action-branding">
            <i class="fas fa-book-open"></i>
            <div>
                <span class="fw-bold d-block text-white" style="font-size: 0.88rem; font-family: 'Montserrat', sans-serif; letter-spacing: 0.5px;">Official Product Catalogue</span>
                <span class="small text-white text-opacity-75" style="font-size: 0.7rem;"><?php echo htmlspecialchars($store_name); ?> &bull; Edition <?php echo date('Y'); ?></span>
            </div>
        </div>

        <div class="action-btn-group">
            <!-- Category Filter Dropdown -->
            <?php if (!empty($all_categories)): ?>
            <div class="d-flex align-items-center gap-1">
                <label for="actionCatFilter" class="small text-white text-opacity-75 d-none d-lg-inline text-nowrap m-0">
                    <i class="fas fa-layer-group me-1" style="color: var(--cat-sage-light);"></i> Filter:
                </label>
                <select id="actionCatFilter" class="cat-filter-select" onchange="filterCatalogueCategory(this.value)">
                    <option value="all" <?php echo empty($active_cat_id) ? 'selected' : ''; ?>>All Categories (<?php echo $total_catalog_products; ?>)</option>
                    <?php foreach ($all_categories as $citem): ?>
                        <option value="<?php echo $citem['id']; ?>" <?php echo ($active_cat_id == $citem['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($citem['name']); ?> (<?php echo $citem['product_count']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <button type="button" class="btn-act btn-act-pdf" id="btnDownloadPdf" onclick="downloadCataloguePDF()">
                <i class="fas fa-file-download"></i> Download Catalogue (PDF)
            </button>
            <button type="button" class="btn-act btn-act-print" onclick="window.print()">
                <i class="fas fa-print"></i> Print / Save as PDF
            </button>
            <?php if ($show_share): ?>
            <button type="button" class="btn-act btn-act-share" onclick="openShareModal()">
                <i class="fas fa-share-alt"></i> Share / Copy Link
            </button>
            <?php endif; ?>
            <a href="https://wa.me/<?php echo $wa_phone_clean; ?>?text=Hello%2C%20I%20reviewed%20your%20Product%20Catalogue%20and%20want%20more%20information." target="_blank" class="btn-act btn-act-wa">
                <i class="fab fa-whatsapp"></i> Inquire on WhatsApp
            </a>
            <a href="<?php echo SITE_URL; ?>/shop.php" class="btn-act btn-act-store">
                <i class="fas fa-store"></i> Browse Store
            </a>
            <?php if ($is_admin): ?>
                <a href="<?php echo SITE_URL; ?>/admin/manage_catalogue.php" class="btn-act btn-act-admin">
                    <i class="fas fa-sliders-h"></i> Settings
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Main Printable Catalogue Document -->
<main class="catalogue-wrapper">
    <div class="catalogue-doc" id="catalogueDocument">

        <!-- ══════════════════════════════════════════════════════════
             FRONT COVER PAGE (BROCHURE DESIGN AS IN SCREENSHOT 1)
             ══════════════════════════════════════════════════════════ -->
        <section class="cover-page">
            <!-- Top Split Section -->
            <div class="cover-top-split">
                <!-- Left Dark Teal Area with Logo -->
                <div class="cover-top-dark">
                    <div class="cover-logo-box">
                        <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="<?php echo htmlspecialchars($store_name); ?>" class="cover-logo-img" onerror="this.style.display='none';">
                    </div>
                    <div class="cover-brand-tagline">
                        <i class="fas fa-bolt text-warning me-1"></i> Heavy-Duty Motor Starters & Control Switchgear
                    </div>
                </div>

                <!-- Right Clean White Area with Year -->
                <div class="cover-top-white">
                    <div class="cover-edition-year"><?php echo date('Y'); ?></div>
                    <div class="cover-edition-label">Official Edition</div>
                </div>

                <!-- Overlapping Circular Spotlight Frame -->
                <div class="cover-circle-frame">
                    <img src="<?php echo htmlspecialchars($cover_spotlight_img); ?>" alt="Flagship Motor Starter" onerror="this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';">
                </div>
            </div>

            <!-- Lower White Area with Big Bold Editorial Title -->
            <div class="cover-bottom-area">
                <div class="editorial-pretitle">Official Product Portfolio</div>
                <h1 class="editorial-cover-h1">CATALOGUE</h1>
                <div class="editorial-cover-sub"><?php echo htmlspecialchars($doc_subtitle); ?></div>

                <!-- Feature Pills -->
                <div class="cover-feature-pills">
                    <span class="cover-pill"><i class="fas fa-shield-halved"></i> Precision Overload Protection</span>
                    <span class="cover-pill"><i class="fas fa-bolt"></i> 100% Electrolytic Copper</span>
                    <span class="cover-pill"><i class="fas fa-certificate"></i> Weatherproof Enclosure</span>
                    <span class="cover-pill"><i class="fas fa-truck-fast"></i> All-India Dispatch</span>
                </div>

                <!-- Cover Footer Contact Meta -->
                <div class="cover-footer-meta">
                    <?php if (!empty($store_phone)): ?>
                        <div><i class="fas fa-phone-alt"></i> <?php echo htmlspecialchars($store_phone); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($store_email)): ?>
                        <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($store_email); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($store_address)): ?>
                        <div><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($store_address); ?></div>
                    <?php endif; ?>
                    <div><i class="fas fa-file-invoice"></i> GSTIN: <?php echo htmlspecialchars(!empty($gst_number) ? $gst_number : 'Available on Request'); ?></div>
                </div>

                <!-- Bottom Right Sage Teal Accent Rectangle (Matching Screenshot 1) -->
                <div class="cover-sage-block"></div>
            </div>
        </section>

        <!-- ══════════════════════════════════════════════════════════
             SPREAD 2: WELCOME & TABLE OF CONTENTS
             ══════════════════════════════════════════════════════════ -->
        <section class="spread-welcome-toc">
            <!-- Left Page: Welcome / Company Profile -->
            <div class="welcome-pane">
                <div>
                    <div class="welcome-circle-badge">
                        <i class="fas fa-industry"></i>
                    </div>
                    <div class="editorial-pretitle">About Our Manufacturing</div>
                    <h2 class="welcome-title">WELCOME</h2>
                    <p class="welcome-text">
                        <?php echo nl2br(htmlspecialchars($doc_about)); ?>
                    </p>
                </div>
                <div class="welcome-features-grid">
                    <div class="wf-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Electrolytic Copper Contactors</span>
                    </div>
                    <div class="wf-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Powder-Coated Enclosures</span>
                    </div>
                    <div class="wf-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Low-Voltage Stable Relays</span>
                    </div>
                    <div class="wf-item">
                        <i class="fas fa-check-circle"></i>
                        <span>100% Factory Bench Tested</span>
                    </div>
                </div>
            </div>

            <!-- Right Page: Table of Contents (Dark Teal Layout) -->
            <div class="toc-pane">
                <div class="toc-header">
                    <h2 class="toc-title">TABLE OF CONTENTS</h2>
                    <div class="toc-subtitle">Comprehensive Range of Agricultural & Industrial Starters</div>
                </div>
                <div class="toc-grid">
                    <?php 
                    $toc_index = 1;
                    foreach ($all_categories as $cat_item): 
                    ?>
                        <a href="#cat-section-<?php echo $cat_item['id']; ?>" class="toc-row">
                            <div class="d-flex align-items-center">
                                <div class="toc-num-box"><?php echo str_pad($toc_index++, 2, '0', STR_PAD_LEFT); ?></div>
                                <div class="toc-info">
                                    <div class="toc-name"><?php echo htmlspecialchars($cat_item['name']); ?></div>
                                    <div class="toc-count"><?php echo (int)$cat_item['product_count']; ?> Certified Models</div>
                                </div>
                            </div>
                            <div class="toc-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════════════════════════════════════
             TECHNICAL SPECIFICATIONS STRIP
             ══════════════════════════════════════════════════════════ -->
        <section class="specs-strip">
            <div class="spec-strip-card">
                <div class="spec-icon-box"><i class="fas fa-microchip"></i></div>
                <div>
                    <div class="spec-strip-title">Thermal Overload Relay</div>
                    <div class="spec-strip-desc">Guards motors against phase failure & overcurrent</div>
                </div>
            </div>
            <div class="spec-strip-card">
                <div class="spec-icon-box"><i class="fas fa-bolt"></i></div>
                <div>
                    <div class="spec-strip-title">100% Copper Contacts</div>
                    <div class="spec-strip-desc">Minimal contact wear & superior arc suppression</div>
                </div>
            </div>
            <div class="spec-strip-card">
                <div class="spec-icon-box"><i class="fas fa-shield-alt"></i></div>
                <div>
                    <div class="spec-strip-title">IP54 Weather Enclosure</div>
                    <div class="spec-strip-desc">Corrosion-proof finish for rugged field operations</div>
                </div>
            </div>
            <div class="spec-strip-card">
                <div class="spec-icon-box"><i class="fas fa-truck-fast"></i></div>
                <div>
                    <div class="spec-strip-title">Fast Nationwide Logistics</div>
                    <div class="spec-strip-desc">Assured spares support & rapid dealer delivery</div>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════════════════════════════════════
             CATALOGUE PRODUCTS BY CATEGORY (SPREADS 3, 4, 5)
             ══════════════════════════════════════════════════════════ -->
        <div class="cat-content-body">
            <?php if (empty($catalog)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="fas fa-box-open fa-3x mb-3" style="color: var(--cat-sage);"></i>
                    <h5 class="fw-bold" style="color: var(--cat-teal-dark);">No Catalogue Products Available</h5>
                    <p class="small text-muted">Please check back shortly or explore our online shop.</p>
                </div>
            <?php else: ?>
                <?php 
                $cat_counter = 0;
                foreach ($catalog as $category_name => $products): 
                    $cat_counter++;
                    $cat_anchor_id = !empty($products[0]['category_id']) ? (int)$products[0]['category_id'] : $cat_counter;
                ?>
                    <!-- Category Header Band -->
                    <div class="category-editorial-bar" id="cat-section-<?php echo $cat_anchor_id; ?>">
                        <div class="cat-bar-title">
                            <i class="fas fa-folder-open text-warning"></i>
                            <span><?php echo htmlspecialchars($category_name); ?></span>
                        </div>
                        <div class="cat-bar-count-badge">
                            <?php echo count($products); ?> Models Available
                        </div>
                    </div>

                    <!-- 2-Column Product Grid -->
                    <div class="product-grid">
                        <?php foreach ($products as $p): ?>
                            <?php
                            $p_img = !empty($p['image']) ? (function_exists('resolve_image_url') ? resolve_image_url($p['image']) : ASSETS_URL . '/images/placeholder.svg') : (ASSETS_URL . '/images/placeholder.svg');
                            $reg_price = (float)($p['regular_price'] ?? $p['price']);
                            $sale_price = !empty($p['sale_price']) && (float)$p['sale_price'] > 0 ? (float)$p['sale_price'] : 0;
                            $summary = !empty($p['short_description']) ? trim($p['short_description']) : (!empty($p['description']) ? substr(strip_tags($p['description']), 0, 110) . '...' : 'Heavy-duty performance motor starter engineered for Indian power conditions.');
                            $sku_code = !empty($p['sku']) ? $p['sku'] : ('SS-' . str_pad($p['id'], 3, '0', STR_PAD_LEFT));
                            $wa_inq_msg = "Hello Sagar Starter's, I am inquiring about " . $p['name'] . " (Code: " . $sku_code . ") from your Official Catalogue.";
                            ?>
                            <div class="cat-prod-card">
                                <div class="cat-prod-thumb-box">
                                    <img src="<?php echo htmlspecialchars($p_img); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" class="cat-prod-thumb" onerror="this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';">
                                </div>
                                <div class="cat-prod-details">
                                    <h4 class="cat-prod-title"><?php echo htmlspecialchars($p['name']); ?></h4>
                                    
                                    <div class="cat-meta-row">
                                        <?php if ($show_sku): ?>
                                            <span class="cat-code-badge">CODE: <?php echo htmlspecialchars($sku_code); ?></span>
                                        <?php endif; ?>
                                        <span class="cat-brand-badge"><?php echo htmlspecialchars(!empty($p['brand']) ? $p['brand'] : "Sagar Starter's"); ?></span>
                                    </div>
                                    
                                    <?php if ($show_features): ?>
                                        <p class="cat-prod-desc"><?php echo htmlspecialchars($summary); ?></p>
                                    <?php endif; ?>

                                    <div class="cat-prod-bottom-row">
                                        <?php if ($show_price || $show_sale_price): ?>
                                            <div class="cat-price-wrapper">
                                                <?php if ($show_price && $reg_price > 0 && $show_sale_price && $sale_price > 0): ?>
                                                    <?php if ($sale_price < $reg_price): ?>
                                                        <span class="cat-price-mrp"><?php echo $currency . number_format($reg_price, 2); ?></span>
                                                        <span class="cat-price-tag"><?php echo $currency . number_format($sale_price, 2); ?></span>
                                                    <?php else: ?>
                                                        <span class="cat-price-tag"><?php echo $currency . number_format($sale_price, 2); ?></span>
                                                    <?php endif; ?>
                                                <?php elseif ($show_sale_price && $sale_price > 0): ?>
                                                    <span class="cat-price-tag"><?php echo $currency . number_format($sale_price, 2); ?></span>
                                                <?php elseif ($show_price && $reg_price > 0): ?>
                                                    <span class="cat-price-tag"><?php echo $currency . number_format($reg_price, 2); ?></span>
                                                <?php else: ?>
                                                    <span class="cat-price-tag text-muted small">Inquire Price</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Quick WhatsApp Inquiry Button -->
                                        <a href="https://wa.me/<?php echo $wa_phone_clean; ?>?text=<?php echo urlencode($wa_inq_msg); ?>" target="_blank" class="btn-card-inquire no-print">
                                            <i class="fab fa-whatsapp"></i> Inquire
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Highlight Feature Banner after 2nd Category (Matching "OUR OFFER" Spread 4) -->
                    <?php if ($cat_counter === 2 || ($cat_counter === 1 && count($catalog) === 1)): ?>
                        <div class="our-offer-banner">
                            <div class="offer-left">
                                <div class="editorial-pretitle" style="color: var(--cat-sage-light);">The Sagar Advantage</div>
                                <h3>ENGINEERED FOR EXTREME DURABILITY</h3>
                                <p>Designed specifically to withstand Indian rural voltage fluctuations, high ambient temperatures, and dust in agricultural fields.</p>
                            </div>
                            <div class="offer-grid">
                                <div class="offer-box">
                                    <div class="offer-box-title"><i class="fas fa-check-double text-warning me-1"></i> 100% Tested</div>
                                    <div class="offer-box-sub">Triple quality inspected before leaving factory</div>
                                </div>
                                <div class="offer-box">
                                    <div class="offer-box-title"><i class="fas fa-shield-alt text-warning me-1"></i> 1-Year Guarantee</div>
                                    <div class="offer-box-sub">Full warranty on contactor coils & relays</div>
                                </div>
                                <div class="offer-box">
                                    <div class="offer-box-title"><i class="fas fa-tools text-warning me-1"></i> Spares Readily Available</div>
                                    <div class="offer-box-sub">Guaranteed spare parts for all historical models</div>
                                </div>
                                <div class="offer-box">
                                    <div class="offer-box-title"><i class="fas fa-handshake text-warning me-1"></i> Direct Dealer Margins</div>
                                    <div class="offer-box-sub">Highly attractive wholesale dealer terms</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ══════════════════════════════════════════════════════════
             BACK COVER / DEALERSHIP & INQUIRIES (SPREAD 6)
             ══════════════════════════════════════════════════════════ -->
        <footer class="cat-back-cover">
            <div class="inquiry-block">
                <h3><i class="fas fa-handshake text-warning me-2"></i> Dealership & Bulk Inquiries</h3>
                <p>
                    We welcome agricultural equipment distributors, pump contractors, electrical retailers, and bulk buyers across India. Enjoy dedicated technical support, attractive wholesale profit margins, and rapid turnarounds on custom starter panels.
                </p>
                <div class="inquiry-contacts">
                    <?php if (!empty($store_phone)): ?>
                        <div><i class="fas fa-phone-alt text-warning me-1"></i> <?php echo htmlspecialchars($store_phone); ?></div>
                    <?php endif; ?>
                    <div><i class="fab fa-whatsapp text-success me-1"></i> <a href="https://wa.me/<?php echo $wa_phone_clean; ?>" target="_blank">WhatsApp Chat</a></div>
                    <div><i class="fas fa-globe text-info me-1"></i> <a href="<?php echo SITE_URL; ?>" target="_blank"><?php echo parse_url(SITE_URL, PHP_URL_HOST) ?: 'sagarstarters.com'; ?></a></div>
                    <?php if (!empty($store_address)): ?>
                        <div><i class="fas fa-map-marker-alt text-danger me-1"></i> <?php echo htmlspecialchars($store_address); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($gst_number)): ?>
                        <div><i class="fas fa-file-invoice text-primary me-1"></i> GSTIN: <?php echo htmlspecialchars($gst_number); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Official Certification Stamp Box -->
            <div class="seal-stamp-box">
                <div class="seal-stamp-title"><?php echo htmlspecialchars($store_name); ?></div>
                <div class="seal-stamp-icon"><i class="fas fa-stamp"></i></div>
                <div class="small text-white fw-bold mb-1">Quality Certified &bull; Authentic</div>
                <div class="seal-stamp-sub">Official Edition <?php echo date('Y'); ?></div>
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
                <p class="share-modal-sub">Share this official Product Catalogue with customers, dealers & team</p>
            </div>
            <button type="button" class="share-modal-close" onclick="closeShareModal()" aria-label="Close">&times;</button>
        </div>

        <div class="share-copy-box">
            <input type="text" id="shareUrlInput" readonly value="<?php echo htmlspecialchars($share_url); ?>">
            <button type="button" class="btn text-white btn-sm px-3" style="background: var(--cat-teal-dark);" id="btnCopyShareLink" onclick="copyShareUrl()">
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

<!-- High-Resolution Client-side Multi-Page PDF Generator -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
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
        btn.style.background = '#10b981';
        setTimeout(function() {
            btn.innerHTML = orig;
            btn.style.background = 'var(--cat-teal-dark)';
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

function filterCatalogueCategory(catId) {
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

async function downloadCataloguePDF() {
    var btn = document.getElementById('btnDownloadPdf');
    var origText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparing Catalogue...';
    btn.disabled = true;

    try {
        if (!window.jspdf || !window.html2canvas) {
            throw new Error('PDF export libraries not loaded.');
        }

        var storeName = <?php echo json_encode($store_name); ?>;
        var docYear = '<?php echo date('Y'); ?>';
        var catSuffix = <?php echo json_encode(!empty($active_cat_slug) ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $active_cat_slug) : (!empty($active_cat_name) ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $active_cat_name) : 'All_Products')); ?>;
        var filename = storeName.replace(/[^a-zA-Z0-9_-]/g, '_') + '_Catalogue_' + catSuffix + '_' + docYear + '.pdf';

        // 1. Create Staging Container off-screen (width 1040px)
        var stage = document.createElement('div');
        stage.className = 'pdf-staging-container';
        document.body.appendChild(stage);

        var pages = [];

        // Helper to construct an exact A4 page wrapper (1040px x 1470px)
        function createA4Page(headerTitle, pageNumStr, isCover) {
            var page = document.createElement('div');
            page.className = 'pdf-a4-page' + (isCover ? ' pdf-page-cover' : '');
            
            if (!isCover) {
                var hdr = document.createElement('div');
                hdr.className = 'pdf-running-header';
                hdr.innerHTML = '<span>' + storeName.toUpperCase() + ' &bull; OFFICIAL PRODUCT CATALOGUE</span><span>' + (headerTitle || 'PORTFOLIO').toUpperCase() + '</span>';
                page.appendChild(hdr);
            }

            var content = document.createElement('div');
            content.className = isCover ? 'w-100 h-100 d-flex flex-column' : 'pdf-page-content';
            page.appendChild(content);

            if (!isCover) {
                var ftr = document.createElement('div');
                ftr.className = 'pdf-running-footer';
                ftr.innerHTML = '<span>' + (pageNumStr || '') + ' &bull; Heavy-Duty Motor Starters & Control Switchgear</span><span>WhatsApp: <?php echo htmlspecialchars($store_phone); ?></span>';
                page.appendChild(ftr);
            }

            stage.appendChild(page);
            pages.push(page);
            return content;
        }

        // ══════════════════════════════════════════════════════════════
        // PAGE 1: FRONT COVER SPREAD
        // ══════════════════════════════════════════════════════════════
        var origCover = document.querySelector('.cover-page');
        if (origCover) {
            var p1Content = createA4Page('', '', true);
            var coverClone = origCover.cloneNode(true);
            p1Content.appendChild(coverClone);
        }

        // ══════════════════════════════════════════════════════════════
        // PAGE 2: WELCOME, TABLE OF CONTENTS & TECHNICAL SPECS
        // ══════════════════════════════════════════════════════════════
        var origWelcome = document.querySelector('.spread-welcome-toc');
        var origSpecs = document.querySelector('.specs-strip');
        if (origWelcome) {
            var p2Content = createA4Page('Company Profile & Specifications', 'Page 02');
            p2Content.appendChild(origWelcome.cloneNode(true));
            if (origSpecs) {
                p2Content.appendChild(origSpecs.cloneNode(true));
            }
        }

        // ══════════════════════════════════════════════════════════════
        // PRODUCT PAGES (DYNAMIC CONTINUOUS FLOW PACKER)
        // Eliminates giant blank spaces by packing categories & cards naturally
        // ══════════════════════════════════════════════════════════════
        var catBars = document.querySelectorAll('.cat-content-body > .category-editorial-bar');
        var catPageNum = 2; // starts after Cover (1) & Welcome/TOC (2)
        var maxPageHeight = 1260; // safe usable height in px inside each A4 sheet
        var currentPageHeight = 0;
        var currentPageContent = null;
        var currentCatTitle = 'Products';

        function startNewProductPage(title) {
            catPageNum++;
            var pLabel = 'Page ' + (catPageNum < 10 ? '0' + catPageNum : catPageNum);
            currentPageContent = createA4Page(title || currentCatTitle, pLabel);
            currentPageHeight = 0;
            return currentPageContent;
        }

        if (catBars.length > 0) {
            var firstCatTitle = catBars[0].querySelector('.cat-bar-title span') ? catBars[0].querySelector('.cat-bar-title span').innerText.trim() : 'Products';
            currentCatTitle = firstCatTitle;
            startNewProductPage(firstCatTitle);

            catBars.forEach(function(bar) {
                var catTitle = bar.querySelector('.cat-bar-title span') ? bar.querySelector('.cat-bar-title span').innerText.trim() : 'Products';
                currentCatTitle = catTitle;

                var grid = bar.nextElementSibling;
                while (grid && !grid.classList.contains('product-grid')) {
                    grid = grid.nextElementSibling;
                }
                var cards = grid ? Array.from(grid.querySelectorAll('.cat-prod-card')) : [];
                
                var nextEl = grid ? grid.nextElementSibling : null;
                var bannerEl = (nextEl && nextEl.classList.contains('our-offer-banner')) ? nextEl : null;

                // Check if Category Header (55px) + at least 1 row of cards (200px) fits on current page
                if (currentPageHeight + 255 > maxPageHeight && currentPageHeight > 100) {
                    startNewProductPage(catTitle);
                }

                // Add Category Header
                var barClone = bar.cloneNode(true);
                currentPageContent.appendChild(barClone);
                currentPageHeight += 55;

                // Process cards in rows of 2
                var totalRows = Math.ceil(cards.length / 2);
                for (var r = 0; r < totalRows; r++) {
                    // Check if this row (200px) fits on current page
                    if (currentPageHeight + 200 > maxPageHeight && currentPageHeight > 100) {
                        startNewProductPage(catTitle + ' (Cont.)');
                        // Add sleek continuation bar
                        var contBar = bar.cloneNode(true);
                        var tSpan = contBar.querySelector('.cat-bar-title span');
                        if (tSpan) tSpan.innerText += ' (Cont.)';
                        currentPageContent.appendChild(contBar);
                        currentPageHeight += 55;
                    }

                    // Create row container with 2-col grid
                    var rowGrid = document.createElement('div');
                    rowGrid.className = 'product-grid';
                    var c1 = cards[r * 2];
                    var c2 = cards[r * 2 + 1];
                    if (c1) rowGrid.appendChild(c1.cloneNode(true));
                    if (c2) rowGrid.appendChild(c2.cloneNode(true));
                    currentPageContent.appendChild(rowGrid);
                    currentPageHeight += 200;
                }

                // If this category has the "The Sagar Advantage" banner
                if (bannerEl) {
                    if (currentPageHeight + 270 > maxPageHeight && currentPageHeight > 100) {
                        startNewProductPage('The Sagar Advantage');
                    }
                    currentPageContent.appendChild(bannerEl.cloneNode(true));
                    currentPageHeight += 270;
                }
            });
        }

        // ══════════════════════════════════════════════════════════════
        // FINAL PAGE: DEALERSHIP & BULK INQUIRIES BACK COVER
        // ══════════════════════════════════════════════════════════════
        var origBackCover = document.querySelector('.cat-back-cover');
        if (origBackCover) {
            catPageNum++;
            var finalLabel = 'Page ' + (catPageNum < 10 ? '0' + catPageNum : catPageNum);
            var backContent = createA4Page('Dealership & Support', finalLabel);
            
            var backIntro = document.createElement('div');
            backIntro.style.cssText = 'padding: 24px 0 16px 0; text-align: center;';
            backIntro.innerHTML = '<h3 style="font-family: \'Montserrat\', sans-serif; font-size: 1.45rem; font-weight: 800; color: #0a2e3d; text-transform: uppercase; margin-bottom: 8px;"><i class="fas fa-certificate text-warning me-2"></i>Direct From Manufacturer</h3><p style="font-size: 0.84rem; color: #64748b; max-width: 720px; margin: 0 auto; line-height: 1.6;">Trusted by thousands of farmers, pump contractors, and electrical retailers across India for heavy-duty reliability, 100% copper contactors, and rapid pan-India dispatch.</p>';
            backContent.appendChild(backIntro);

            backContent.appendChild(origBackCover.cloneNode(true));
        }

        // ══════════════════════════════════════════════════════════════
        // RENDER PAGES TO PDF USING jsPDF + html2canvas
        // ══════════════════════════════════════════════════════════════
        var { jsPDF } = window.jspdf;
        var pdf = new jsPDF({
            unit: 'mm',
            format: 'a4',
            orientation: 'portrait',
            compress: true
        });

        var totalPages = pages.length;

        for (var i = 0; i < totalPages; i++) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating Page ' + (i + 1) + ' of ' + totalPages + '...';
            
            var canvas = await html2canvas(pages[i], {
                scale: 2,
                useCORS: true,
                allowTaint: true,
                logging: false,
                backgroundColor: '#ffffff'
            });

            var imgData = canvas.toDataURL('image/jpeg', 0.96);

            if (i > 0) {
                pdf.addPage([210, 297], 'portrait');
            }

            pdf.addImage(imgData, 'JPEG', 0, 0, 210, 297, undefined, 'FAST');
        }

        btn.innerHTML = '<i class="fas fa-check"></i> Saving PDF...';
        pdf.save(filename);

        // Cleanup staging
        if (stage && stage.parentNode) {
            stage.parentNode.removeChild(stage);
        }

        setTimeout(function() {
            btn.innerHTML = origText;
            btn.disabled = false;
        }, 1200);

    } catch (err) {
        console.error('PDF Generation failed, falling back to print:', err);
        var s = document.querySelector('.pdf-staging-container');
        if (s && s.parentNode) s.parentNode.removeChild(s);
        btn.innerHTML = origText;
        btn.disabled = false;
        window.print();
    }
}

<?php if ($auto_download): ?>
window.addEventListener('load', function() {
    setTimeout(downloadCataloguePDF, 750);
});
<?php endif; ?>
</script>
</body>
</html>
