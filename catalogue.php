<?php
/**
 * ============================================================
 *  Professional Product Catalogue (PDF) — Public Portal
 *  Location: /catalogue.php
 * ============================================================
 *  Publicly accessible: Anyone (customers, dealers, farmers,
 *  visitors) can view and download the official catalogue.
 *  Controlled completely via Admin Panel.
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
            body { background: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
            .card-notice { max-width: 520px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); }
        </style>
    </head>
    <body>
        <div class="card card-notice border-0 p-4 p-md-5 text-center bg-white m-3">
            <div class="mb-3 text-primary">
                <i class="fas fa-book-reader fa-4x"></i>
            </div>
            <h3 class="fw-bold mb-2 text-dark">Catalogue Under Revision</h3>
            <p class="text-muted mb-4">
                Our product catalogue is currently being updated with new motor starter models and technical specifications. Please check back soon or browse our online shop.
            </p>
            <div class="d-flex justify-content-center gap-2">
                <a href="<?php echo SITE_URL; ?>/shop.php" class="btn btn-primary rounded-pill px-4">
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

// ── 3. Dynamic Visual Catalogue — Query Products from DB ──────
$cat_filter = $global_settings['catalogue_filter'] ?? 'all';
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
        c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE 1=1
";

if ($cat_filter === 'trending_only') {
    $sql .= " AND p.is_trending = 1 ";
}

$sql .= " ORDER BY COALESCE(c.name, 'General Products') ASC, p.name ASC";

$products_res = $conn->query($sql);
$catalog = [];

if ($products_res) {
    while ($row = $products_res->fetch_assoc()) {
        $cat = !empty($row['category_name']) ? trim($row['category_name']) : 'General Starters & Panels';
        $catalog[$cat][] = $row;
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

$show_price    = ($global_settings['catalogue_show_price'] ?? '1') === '1';
$show_sku      = ($global_settings['catalogue_show_sku'] ?? '1') === '1';
$show_features = ($global_settings['catalogue_show_features'] ?? '1') === '1';
$show_specs    = ($global_settings['catalogue_show_specs'] ?? '1') === '1';

$auto_download = isset($_GET['download']) && $_GET['download'] == '1';
$wa_phone_clean = preg_replace('/[^0-9]/', '', $store_phone);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($doc_title); ?> — <?php echo htmlspecialchars($store_name); ?></title>
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800;900&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.0/mdb.min.css" rel="stylesheet"/>

    <style>
        /* ── Base Setup ────────────────────────────────────────── */
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
            background: linear-gradient(135deg, #091e3a 0%, #1e3a8a 100%);
            padding: 12px 24px;
            color: #fff;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
        }
        .action-bar-inner {
            max-width: 1140px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
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
        .btn-act-wa {
            background: #10b981;
            color: #fff;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4);
        }
        .btn-act-wa:hover { background: #059669; color: #fff; }
        .btn-act-store {
            background: rgba(255, 255, 255, 0.15);
            color: #f1f5f9;
            border: 1px solid rgba(255, 255, 255, 0.25);
        }
        .btn-act-store:hover { background: rgba(255, 255, 255, 0.25); color: #fff; }
        .btn-act-admin {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.4);
        }
        .btn-act-admin:hover { background: rgba(245, 158, 11, 0.35); color: #fef3c7; }

        /* ── Catalogue Page Container ──────────────────────────── */
        .catalogue-wrapper {
            padding: 30px 15px;
            display: flex;
            justify-content: center;
        }
        .catalogue-doc {
            width: 100%;
            max-width: 1080px;
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            box-sizing: border-box;
            overflow: hidden;
        }

        /* ── Cover / Hero Banner ───────────────────────────────── */
        .cat-cover {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
            color: #fff;
            padding: 40px 45px;
            position: relative;
        }
        .cover-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            margin-bottom: 25px;
            gap: 20px;
        }
        .cover-logo {
            max-height: 70px;
            max-width: 200px;
            object-fit: contain;
            background: rgba(255,255,255,0.9);
            padding: 6px 14px;
            border-radius: 8px;
        }
        .cover-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .cat-badge {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: #f1f5f9;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .cat-title-block h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 2rem;
            font-weight: 800;
            margin: 0;
            color: #ffffff;
            letter-spacing: -0.5px;
        }
        .cat-title-block p {
            font-size: 1.05rem;
            color: #93c5fd;
            margin: 6px 0 0 0;
            font-weight: 500;
        }
        .cover-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            font-size: 0.8rem;
            color: #cbd5e1;
        }
        .cover-meta-grid i {
            color: #60a5fa;
            margin-right: 6px;
        }

        /* ── Company Intro Section ─────────────────────────────── */
        .cat-intro-section {
            padding: 26px 45px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .intro-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .intro-text {
            font-size: 0.82rem;
            color: #475569;
            line-height: 1.7;
            margin: 0;
        }

        /* ── Quality Highlights Bar ────────────────────────────── */
        .quality-strip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            padding: 16px 45px;
            background: #ffffff;
            border-bottom: 2px solid #e2e8f0;
        }
        .quality-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.78rem;
            color: #334155;
            font-weight: 600;
        }
        .quality-item i {
            font-size: 1.2rem;
            color: #2563eb;
        }

        /* ── Category & Product Cards Grid ─────────────────────── */
        .cat-content-body {
            padding: 30px 45px;
        }
        .cat-group-heading {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.2rem;
            font-weight: 800;
            color: #0f172a;
            padding-bottom: 8px;
            margin-top: 25px;
            margin-bottom: 20px;
            border-bottom: 2px solid #2563eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .cat-group-heading:first-child { margin-top: 0; }
        .cat-group-count {
            font-size: 0.75rem;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 600;
        }

        /* Product Card */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .cat-prod-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px;
            background: #ffffff;
            display: flex;
            gap: 16px;
            align-items: flex-start;
            page-break-inside: avoid;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .cat-prod-thumb {
            width: 110px;
            height: 110px;
            object-fit: contain;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            padding: 4px;
            flex-shrink: 0;
        }
        .cat-prod-details {
            flex: 1;
            min-width: 0;
        }
        .cat-prod-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
            line-height: 1.3;
        }
        .cat-meta-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 6px;
        }
        .cat-sku-tag {
            font-family: monospace;
            font-size: 0.68rem;
            background: #f1f5f9;
            color: #475569;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
        }
        .cat-brand-tag {
            font-size: 0.68rem;
            background: #eff6ff;
            color: #1d4ed8;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 600;
        }
        .cat-prod-desc {
            font-size: 0.72rem;
            color: #64748b;
            line-height: 1.4;
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .cat-prod-price {
            font-size: 0.95rem;
            font-weight: 800;
            color: #0f172a;
        }
        .cat-price-cut {
            font-size: 0.72rem;
            text-decoration: line-through;
            color: #94a3b8;
            margin-right: 4px;
        }

        /* ── Back Cover / Dealership Section ───────────────────── */
        .cat-back-cover {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: #ffffff;
            padding: 35px 45px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 25px;
        }
        .inquiry-block {
            max-width: 580px;
        }
        .inquiry-block h3 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: #f8fafc;
            margin-bottom: 6px;
        }
        .inquiry-block p {
            font-size: 0.8rem;
            color: #94a3b8;
            margin-bottom: 12px;
            line-height: 1.6;
        }
        .inquiry-contacts {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            font-size: 0.78rem;
            color: #e2e8f0;
        }
        .inquiry-contacts a {
            color: #60a5fa;
            text-decoration: none;
            font-weight: 600;
        }
        .official-seal-box {
            background: rgba(255, 255, 255, 0.08);
            border: 1px dashed rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            padding: 16px 24px;
            text-align: center;
            min-width: 200px;
        }

        /* ── Print Media ───────────────────────────────────────── */
        @page {
            size: A4 portrait;
            margin: 8mm 8mm 10mm 8mm;
        }
        @media print {
            body { background: #ffffff !important; }
            #action-bar { display: none !important; }
            .catalogue-wrapper { padding: 0 !important; }
            .catalogue-doc {
                box-shadow: none !important;
                border-radius: 0 !important;
                max-width: 100% !important;
            }
            .cat-prod-card {
                page-break-inside: avoid;
            }
            .cat-group-heading {
                page-break-after: avoid;
            }
        }
    </style>
</head>
<body>

<!-- Sticky Top Action Bar (Hidden in Print & PDF Export) -->
<header id="action-bar">
    <div class="action-bar-inner">
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-book-open text-warning fs-5"></i>
            <div>
                <span class="fw-bold d-block text-white" style="font-size: 0.9rem;">Official Product Catalogue</span>
                <span class="small text-light text-opacity-75" style="font-size: 0.72rem;">Sagar Starter's &bull; High-Performance Motor Starters</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="button" class="btn-act btn-act-pdf" id="btnDownloadPdf" onclick="downloadCataloguePDF()">
                <i class="fas fa-file-download"></i> Download Catalogue (PDF)
            </button>
            <button type="button" class="btn-act btn-act-print" onclick="window.print()">
                <i class="fas fa-print"></i> Print / Save as PDF
            </button>
            <a href="https://wa.me/<?php echo $wa_phone_clean; ?>?text=Hello%2C%20I%20reviewed%20your%20Product%20Catalogue%20and%20want%20more%20information." target="_blank" class="btn-act btn-act-wa">
                <i class="fab fa-whatsapp"></i> Inquire on WhatsApp
            </a>
            <a href="<?php echo SITE_URL; ?>/shop.php" class="btn-act btn-act-store">
                <i class="fas fa-shopping-bag"></i> Browse Store
            </a>
            <?php if ($is_admin): ?>
                <a href="<?php echo SITE_URL; ?>/admin/manage_catalogue.php" class="btn-act btn-act-admin">
                    <i class="fas fa-cog"></i> Settings
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Main Printable Catalogue Document -->
<main class="catalogue-wrapper">
    <div class="catalogue-doc" id="catalogueDocument">
        <!-- Front Cover / Hero Banner -->
        <div class="cat-cover">
            <div class="cover-header">
                <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="<?php echo htmlspecialchars($store_name); ?>" class="cover-logo" onerror="this.style.display='none';">
                <div class="cover-badges">
                    <span class="cat-badge"><i class="fas fa-certificate text-warning me-1"></i> Heavy Duty</span>
                    <span class="cat-badge"><i class="fas fa-bolt text-warning me-1"></i> 100% Copper</span>
                    <span class="cat-badge"><i class="fas fa-shield-alt text-success me-1"></i> ISO Certified</span>
                </div>
            </div>
            <div class="cat-title-block">
                <h1><?php echo htmlspecialchars($doc_title); ?></h1>
                <p><?php echo htmlspecialchars($doc_subtitle); ?></p>
            </div>
            <div class="cover-meta-grid">
                <?php if (!empty($store_phone)): ?>
                    <div><i class="fas fa-phone-alt"></i> <?php echo htmlspecialchars($store_phone); ?></div>
                <?php endif; ?>
                <?php if (!empty($store_email)): ?>
                    <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($store_email); ?></div>
                <?php endif; ?>
                <?php if (!empty($store_address)): ?>
                    <div><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($store_address); ?></div>
                <?php endif; ?>
                <div><i class="fas fa-file-invoice"></i> GSTIN: <?php echo htmlspecialchars(!empty($gst_number) ? $gst_number : 'Available on request'); ?></div>
            </div>
        </div>

        <!-- Quality Features Strip -->
        <div class="quality-strip">
            <div class="quality-item">
                <i class="fas fa-microchip"></i>
                <span>Thermal Overload Protection</span>
            </div>
            <div class="quality-item">
                <i class="fas fa-cog"></i>
                <span>100% Copper Contactors</span>
            </div>
            <div class="quality-item">
                <i class="fas fa-box-tissue"></i>
                <span>Weatherproof Enclosure</span>
            </div>
            <div class="quality-item">
                <i class="fas fa-truck-fast"></i>
                <span>Nationwide Express Dispatch</span>
            </div>
        </div>

        <!-- Company Introduction Section -->
        <div class="cat-intro-section">
            <div class="intro-title">
                <i class="fas fa-award text-primary"></i> About Sagar Starter's
            </div>
            <p class="intro-text">
                <?php echo nl2br(htmlspecialchars($doc_about)); ?>
            </p>
        </div>

        <!-- Catalog Product Grid by Categories -->
        <div class="cat-content-body">
            <?php if (empty($catalog)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="fas fa-box-open fa-3x mb-3 text-secondary"></i>
                    <h5>No Catalogue Products Available</h5>
                    <p class="small">Please check back shortly or visit our online store.</p>
                </div>
            <?php else: ?>
                <?php foreach ($catalog as $category_name => $products): ?>
                    <div class="cat-group-heading">
                        <span><i class="fas fa-folder text-primary me-2"></i><?php echo htmlspecialchars($category_name); ?></span>
                        <span class="cat-group-count"><?php echo count($products); ?> Models</span>
                    </div>

                    <div class="product-grid">
                        <?php foreach ($products as $p): ?>
                            <?php
                            $p_img = !empty($p['image']) ? (function_exists('resolve_image_url') ? resolve_image_url($p['image']) : ASSETS_URL . '/images/placeholder.svg') : (ASSETS_URL . '/images/placeholder.svg');
                            $reg_price = (float)($p['regular_price'] ?? $p['price']);
                            $sale_price = !empty($p['sale_price']) && (float)$p['sale_price'] > 0 ? (float)$p['sale_price'] : 0;
                            $summary = !empty($p['short_description']) ? trim($p['short_description']) : (!empty($p['description']) ? substr(strip_tags($p['description']), 0, 110) . '...' : 'Heavy-duty performance motor starter engineered for Indian power conditions.');
                            ?>
                            <div class="cat-prod-card">
                                <img src="<?php echo htmlspecialchars($p_img); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" class="cat-prod-thumb" onerror="this.src='<?php echo ASSETS_URL; ?>/images/placeholder.svg';">
                                <div class="cat-prod-details">
                                    <div class="cat-prod-title"><?php echo htmlspecialchars($p['name']); ?></div>
                                    <div class="cat-meta-row">
                                        <?php if ($show_sku && !empty($p['sku'])): ?>
                                            <span class="cat-sku-tag">SKU: <?php echo htmlspecialchars($p['sku']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($p['brand'])): ?>
                                            <span class="cat-brand-tag"><?php echo htmlspecialchars($p['brand']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($show_features): ?>
                                        <p class="cat-prod-desc"><?php echo htmlspecialchars($summary); ?></p>
                                    <?php endif; ?>

                                    <?php if ($show_price && $reg_price > 0): ?>
                                        <div class="cat-prod-price">
                                            <?php if ($sale_price > 0 && $sale_price < $reg_price): ?>
                                                <span class="cat-price-cut"><?php echo $currency . number_format($reg_price, 2); ?></span>
                                                <span class="text-primary"><?php echo $currency . number_format($sale_price, 2); ?></span>
                                            <?php else: ?>
                                                <span class="text-primary"><?php echo $currency . number_format($reg_price, 2); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Back Cover / Dealership & Inquiries -->
        <footer class="cat-back-cover">
            <div class="inquiry-block">
                <h3><i class="fas fa-handshake me-2 text-warning"></i> Dealership & Bulk Inquiries</h3>
                <p>
                    We welcome distributors, agricultural retailers, electric pump contractors, and dealers nationwide. Attractive wholesale margin structures, guaranteed spare support, and rapid turnaround on custom starter panels available.
                </p>
                <div class="inquiry-contacts">
                    <?php if (!empty($store_phone)): ?>
                        <div><i class="fas fa-phone-alt text-warning me-1"></i> <?php echo htmlspecialchars($store_phone); ?></div>
                    <?php endif; ?>
                    <div><i class="fab fa-whatsapp text-success me-1"></i> <a href="https://wa.me/<?php echo $wa_phone_clean; ?>" target="_blank">Chat on WhatsApp</a></div>
                    <div><i class="fas fa-globe text-info me-1"></i> <a href="<?php echo SITE_URL; ?>" target="_blank"><?php echo parse_url(SITE_URL, PHP_URL_HOST) ?: 'sagarstarters.com'; ?></a></div>
                    <?php if (!empty($store_address)): ?>
                        <div><i class="fas fa-map-marker-alt text-danger me-1"></i> <?php echo htmlspecialchars($store_address); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($gst_number)): ?>
                        <div><i class="fas fa-file-invoice text-primary me-1"></i> GSTIN: <?php echo htmlspecialchars($gst_number); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="official-seal-box">
                <div class="fw-bold text-white mb-1"><?php echo htmlspecialchars($store_name); ?></div>
                <div class="small text-light text-opacity-75" style="font-size: 0.7rem;">Official Product Catalogue</div>
                <div class="my-2 text-warning" style="font-size: 1.6rem;">
                    <i class="fas fa-stamp"></i>
                </div>
                <small class="text-light text-opacity-50 d-block" style="font-size: 0.65rem;">Quality Assured &bull; Edition <?php echo date('Y'); ?></small>
            </div>
        </footer>
    </div>
</main>

<!-- html2pdf Client-side High-Resolution PDF Generator -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadCataloguePDF() {
    var btn = document.getElementById('btnDownloadPdf');
    var origText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating PDF...';
    btn.disabled = true;

    var element = document.getElementById('catalogueDocument');
    var opt = {
        margin: [4, 4, 6, 4],
        filename: '<?php echo preg_replace('/[^a-zA-Z0-9_-]/', '_', $store_name); ?>_Product_Catalogue_<?php echo date('Y'); ?>.pdf',
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
    setTimeout(downloadCataloguePDF, 750);
});
<?php endif; ?>
</script>
</body>
</html>
