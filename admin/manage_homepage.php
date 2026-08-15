<?php
/**
 * manage_homepage.php — Complete Homepage UI & Content Manager
 * 100% Admin Panel Control over every section of the Homepage.
 */

include 'admin_header.php';

// Helper function to get setting with default fallback
function get_home_setting($key, $default = '') {
    global $global_settings;
    return isset($global_settings[$key]) && $global_settings[$key] !== '' ? $global_settings[$key] : $default;
}

// Default settings definitions with sensible, high-converting defaults
$default_keys = [
    // 1. Trust Bar
    'home_trust_enabled' => '1',
    'home_trust1_icon'   => 'fas fa-truck-fast',
    'home_trust1_title'  => 'Pan-India Dispatch',
    'home_trust1_desc'   => 'Fast & insured doorstep delivery',
    'home_trust2_icon'   => 'fas fa-shield-halved',
    'home_trust2_title'  => '100% Genuine Copper',
    'home_trust2_desc'   => '1-Year replacement warranty',
    'home_trust3_icon'   => 'fas fa-bolt',
    'home_trust3_title'  => 'Complete Protection',
    'home_trust3_desc'   => 'Overload & dry run auto-switch',
    'home_trust4_icon'   => 'fab fa-whatsapp',
    'home_trust4_title'  => 'Expert Support',
    'home_trust4_desc'   => 'Direct engineer consultation',
    'home_trust5_icon'   => 'fas fa-lock',
    'home_trust5_title'  => 'Secure & COD',
    'home_trust5_desc'   => 'UPI, Cards, Netbanking & COD',

    // 2. Categories Section
    'home_cats_enabled'   => '1',
    'home_cats_badge'     => 'EXPLORE CATALOG',
    'home_cats_title'     => 'Shop by Category',
    'home_cats_subtitle'  => 'Industrial motor starters, submersible controllers, star delta panels & stabilizers',
    'home_cats_btn_text'  => 'View All Categories',

    // 3. Featured / Trending Products
    'home_prods_enabled'  => '1',
    'home_prods_badge'    => 'BESTSELLERS & TRENDING',
    'home_prods_title'    => 'Featured Motor Starters',
    'home_prods_subtitle' => 'High-performance starters and panels engineered for agricultural and industrial pumps',
    'home_prods_count'    => '12',
    'home_prods_btn_text' => 'View All Products',

    // 4. Starter Selector Widget
    'home_selector_enabled'     => '1',
    'home_selector_badge'       => 'SMART PRODUCT FINDER',
    'home_selector_title'       => 'Find the Right Starter for Your Motor',
    'home_selector_subtitle'    => 'Select your motor specifications to get the exact matching starter panel instantly',
    'home_selector_btn_text'    => 'Find Starters',
    'home_selector_action_url'  => 'shop.php',
    
    'home_selector_step1_label' => '1. Power Phase',
    'home_selector_phase1_text' => 'All Phases',
    'home_selector_phase1_val'  => '',
    'home_selector_phase1_link' => '',
    'home_selector_phase2_text' => '1-Phase (220V)',
    'home_selector_phase2_val'  => '1-Phase',
    'home_selector_phase2_link' => 'shop.php?phase=1-Phase',
    'home_selector_phase3_text' => '3-Phase (415V)',
    'home_selector_phase3_val'  => '3-Phase',
    'home_selector_phase3_link' => 'shop.php?phase=3-Phase',
    
    'home_selector_step2_label' => '2. Motor Rating (HP)',
    'home_selector_hp1_text'    => 'All HP',
    'home_selector_hp1_val'     => '',
    'home_selector_hp1_link'    => '',
    'home_selector_hp2_text'    => '1 - 3 HP',
    'home_selector_hp2_val'     => '1-3 HP',
    'home_selector_hp2_link'    => '',
    'home_selector_hp3_text'    => '5 - 7.5 HP',
    'home_selector_hp3_val'     => '5-7.5 HP',
    'home_selector_hp3_link'    => '',
    'home_selector_hp4_text'    => '10 - 25+ HP',
    'home_selector_hp4_val'     => '10-25 HP',
    'home_selector_hp4_link'    => '',
    
    'home_selector_step3_label' => '3. Application / Motor Type',
    'home_selector_app1_text'   => 'Submersible Pump',
    'home_selector_app1_icon'   => 'fas fa-water',
    'home_selector_app1_val'    => 'submersible',
    'home_selector_app1_link'   => 'shop.php?category=4',
    'home_selector_app2_text'   => 'Openwell / Monoblock',
    'home_selector_app2_icon'   => 'fas fa-industry',
    'home_selector_app2_val'    => 'openwell',
    'home_selector_app2_link'   => 'shop.php?app=openwell',
    'home_selector_app3_text'   => 'Flour Mill / Heavy Motor',
    'home_selector_app3_icon'   => 'fas fa-cog',
    'home_selector_app3_val'    => 'flourmill',
    'home_selector_app3_link'   => 'shop.php?category=6',

    // 5. Promotional Spotlights
    'home_promo_enabled'   => '1',
    'home_promo1_badge'    => 'Agricultural & Submersible',
    'home_promo1_title'    => 'Submersible Pump Starters & Panels',
    'home_promo1_desc'     => 'Equipped with dry run auto cut, electronic overload relays, digital ammeter-voltmeter, and surge safety for borewell motors.',
    'home_promo1_btn_text' => 'Explore Submersible Starters',
    'home_promo1_btn_link' => 'shop.php?category=4',
    
    'home_promo2_badge'    => '3-Phase Industrial Range',
    'home_promo2_title'    => 'Star Delta & Heavy Duty Panels',
    'home_promo2_desc'     => 'Engineered for factories, flour mills, and heavy agricultural motors. 100% heavy copper coils with thermal overload trip mechanism.',
    'home_promo2_btn_text' => 'Explore Star Delta Starters',
    'home_promo2_btn_link' => 'shop.php?category=6',

    // 6. Industrial Trust Stats
    'home_stats_enabled'   => '1',
    'home_stats_badge'     => 'PROVEN RELIABILITY',
    'home_stats_title'     => "Why Farmers & Engineers Trust Sagar Starter's",
    'home_stats_subtitle'  => 'Over a decade of manufacturing excellence in motor control systems and agricultural power protection.',
    
    'home_stat1_num'       => '15+',
    'home_stat1_label'     => 'Years of Excellence',
    'home_stat1_icon'      => 'fas fa-calendar-check',
    
    'home_stat2_num'       => '50,000+',
    'home_stat2_label'     => 'Motors Protected',
    'home_stat2_icon'      => 'fas fa-shield-virus',
    
    'home_stat3_num'       => '100%',
    'home_stat3_label'     => 'Pre-Tested Relays',
    'home_stat3_icon'      => 'fas fa-microchip',
    
    'home_stat4_num'       => '4.9 / 5',
    'home_stat4_label'     => 'Customer Rating',
    'home_stat4_icon'      => 'fas fa-star',

    // 7. Expert Assistance CTA
    'home_cta_enabled'     => '1',
    'home_cta_badge'       => 'ENGINEERING CONSULTATION',
    'home_cta_title'       => 'Need a Custom Control Panel or Bulk Order?',
    'home_cta_desc'        => 'Talk directly with our senior electrical engineers for custom DOL panels, automatic water level controllers, or commercial pricing.',
    'home_cta_btn1_text'   => 'Chat on WhatsApp',
    'home_cta_btn2_text'   => 'Call Technical Support',

    // 8. FAQ Section
    'home_faq_enabled'     => '1',
    'home_faq_badge'       => 'HELP & BUYING GUIDE',
    'home_faq_title'       => 'Frequently Asked Questions',
    'home_faq_subtitle'    => 'Quick answers to help you choose, install, and protect your motor with Sagar Starters',
    
    'home_faq_q1'          => 'Which starter is suitable for my submersible pump motor?',
    'home_faq_a1'          => 'For Single Phase (1 HP - 3 HP) submersible pumps, our Digital Submersible Starter with Dry Run & Voltage Protection is best. For Three Phase (3 HP - 25 HP) pumps, choose our Heavy Duty DOL or Star Delta Starter with phase failure prevention.',
    
    'home_faq_q2'          => 'What is the advantage of 100% Genuine Copper Coils in Sagar Starters?',
    'home_faq_a2'          => 'Pure copper coils operate at significantly lower temperatures, resist voltage fluctuations, prevent relay burnout, and provide long-lasting durability even in continuous rural farming environments.',
    
    'home_faq_q3'          => 'How does the Dry Run and Overload auto-cut feature protect motors?',
    'home_faq_a3'          => 'When water runs dry in your borewell or when motor current surges abnormally, our built-in sensor relay automatically cuts power within seconds, preventing expensive motor winding burnouts.',
    
    'home_faq_q4'          => 'Do you provide Pan-India delivery and warranty replacement?',
    'home_faq_a4'          => 'Yes! We deliver across India via fast insured courier services. All Sagar Starters come with 1-Year replacement warranty and lifetime engineer telephone support.'
];

// Handle Form Submission
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_homepage_settings') {
    // Process Checkboxes (Toggles)
    $toggles = ['home_trust_enabled', 'home_cats_enabled', 'home_prods_enabled', 'home_selector_enabled', 'home_promo_enabled', 'home_stats_enabled', 'home_cta_enabled', 'home_faq_enabled'];
    foreach ($toggles as $toggle_key) {
        $val = isset($_POST[$toggle_key]) ? '1' : '0';
        $safe_val = $conn->real_escape_string($val);
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('$toggle_key', '$safe_val') ON DUPLICATE KEY UPDATE setting_value='$safe_val'");
        $global_settings[$toggle_key] = $val;
    }

    // Process all text inputs
    foreach ($default_keys as $key => $default_val) {
        if (!in_array($key, $toggles) && isset($_POST[$key])) {
            $val = trim($_POST[$key]);
            $safe_val = $conn->real_escape_string($val);
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$safe_val') ON DUPLICATE KEY UPDATE setting_value='$safe_val'");
            $global_settings[$key] = $val;
        }
    }

    $success_msg = 'Homepage sections and content updated successfully! All changes are live on the storefront.';
}

$active_tab = $_GET['tab'] ?? 'trust';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-desktop me-2 text-primary"></i>Homepage Sections &amp; UI Manager</h4>
        <small class="text-muted">Customize every title, badge, counter, trust badge, banner &amp; FAQ on your storefront home page.</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo STORE_BASE_URL; ?>" target="_blank" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-external-link-alt me-1"></i>View Live Website
        </a>
    </div>
</div>

<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success alert-dismissible fade show py-2 mb-3" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_msg); ?>
        <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST" action="manage_homepage.php?tab=<?php echo htmlspecialchars($active_tab); ?>" id="homepageForm">
    <?php echo csrf_input(); ?>
    <input type="hidden" name="action" value="save_homepage_settings">

    <!-- Nav Tabs -->
    <ul class="nav nav-tabs mb-4 border-bottom-0" id="homeTabs">
        <?php
        $nav_tabs = [
            'trust'    => ['icon' => 'fa-shield-halved', 'label' => 'Trust Strip (5 Badges)'],
            'cats'     => ['icon' => 'fa-layer-group',   'label' => 'Categories Section'],
            'prods'    => ['icon' => 'fa-bolt',          'label' => 'Featured Products'],
            'selector' => ['icon' => 'fa-sliders',       'label' => 'Starter Selector Widget'],
            'promo'    => ['icon' => 'fa-rectangle-ad',  'label' => 'Promo Spotlights'],
            'stats'    => ['icon' => 'fa-chart-line',    'label' => 'Reliability Stats'],
            'cta'      => ['icon' => 'fa-headset',       'label' => 'Engineering CTA Bar'],
            'faq'      => ['icon' => 'fa-circle-question','label' => 'FAQ & Guide'],
        ];

        foreach ($nav_tabs as $slug => $tab):
            $isActive = ($active_tab === $slug);
            $cls = $isActive ? 'active shadow-sm bg-white rounded-top-4 text-primary border-bottom border-primary border-3' : 'text-muted';
        ?>
        <li class="nav-item">
            <a class="nav-link fs-6 fw-bold border-0 bg-transparent <?php echo $cls; ?>" href="?tab=<?php echo $slug; ?>">
                <i class="fas <?php echo $tab['icon']; ?> me-1"></i><?php echo $tab['label']; ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content">

        <!-- ══ 1. TRUST STRIP TAB ═════════════════════════════════════ -->
        <div class="tab-pane fade <?php echo $active_tab === 'trust' ? 'show active' : ''; ?>" id="tab-trust">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold mb-0"><i class="fas fa-shield-halved me-2 text-primary"></i>Value &amp; Trust Badges Strip</h5>
                        <p class="text-muted small mb-0">The high-impact guarantee strip positioned right beneath the Hero Slider.</p>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="home_trust_enabled" id="home_trust_enabled" value="1" 
                               <?php echo get_home_setting('home_trust_enabled', '1') == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="home_trust_enabled">Show Trust Strip</label>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="p-3 border rounded-3 bg-light h-100">
                                <h6 class="fw-bold text-primary mb-3"><i class="fas fa-check-circle me-1"></i>Badge #<?php echo $i; ?></h6>
                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Icon (FontAwesome Class)</label>
                                    <input type="text" name="home_trust<?php echo $i; ?>_icon" class="form-control form-control-sm" 
                                           value="<?php echo htmlspecialchars(get_home_setting("home_trust{$i}_icon", $default_keys["home_trust{$i}_icon"])); ?>" placeholder="e.g. fas fa-truck-fast">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Title</label>
                                    <input type="text" name="home_trust<?php echo $i; ?>_title" class="form-control form-control-sm" 
                                           value="<?php echo htmlspecialchars(get_home_setting("home_trust{$i}_title", $default_keys["home_trust{$i}_title"])); ?>" placeholder="Title">
                                </div>
                                <div class="mb-0">
                                    <label class="form-label small fw-bold">Subtitle / Description</label>
                                    <input type="text" name="home_trust<?php echo $i; ?>_desc" class="form-control form-control-sm" 
                                           value="<?php echo htmlspecialchars(get_home_setting("home_trust{$i}_desc", $default_keys["home_trust{$i}_desc"])); ?>" placeholder="Short Subtitle">
                                </div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ 2. CATEGORIES SECTION TAB ══════════════════════════════ -->
        <div class="tab-pane fade <?php echo $active_tab === 'cats' ? 'show active' : ''; ?>" id="tab-cats">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold mb-0"><i class="fas fa-layer-group me-2 text-primary"></i>Category Showcase Section</h5>
                        <p class="text-muted small mb-0">Control the headings, badge pill, and button text for the category catalog grid.</p>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="home_cats_enabled" id="home_cats_enabled" value="1" 
                               <?php echo get_home_setting('home_cats_enabled', '1') == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="home_cats_enabled">Show Category Grid</label>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Section Badge Pill</label>
                            <input type="text" name="home_cats_badge" class="form-control" 
                                   value="<?php echo htmlspecialchars(get_home_setting('home_cats_badge', $default_keys['home_cats_badge'])); ?>" placeholder="EXPLORE CATALOG">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Section Main Title</label>
                            <input type="text" name="home_cats_title" class="form-control" 
                                   value="<?php echo htmlspecialchars(get_home_setting('home_cats_title', $default_keys['home_cats_title'])); ?>" placeholder="Shop by Category">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Section Subtitle / Description</label>
                            <input type="text" name="home_cats_subtitle" class="form-control" 
                                   value="<?php echo htmlspecialchars(get_home_setting('home_cats_subtitle', $default_keys['home_cats_subtitle'])); ?>" placeholder="Industrial motor starters, submersible controllers...">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">View All Button Label</label>
                            <input type="text" name="home_cats_btn_text" class="form-control" 
                                   value="<?php echo htmlspecialchars(get_home_setting('home_cats_btn_text', $default_keys['home_cats_btn_text'])); ?>" placeholder="View All Categories">
                        </div>
                    </div>
                    <div class="alert alert-info mt-4 mb-0 py-2 rounded-3 small">
                        <i class="fas fa-info-circle me-1"></i> Individual category names, images, and ordering are managed in <a href="manage_categories.php" class="fw-bold alert-link">Products &rarr; Categories</a>.
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ 3. FEATURED PRODUCTS TAB ═══════════════════════════════ -->
        <div class="tab-pane fade <?php echo $active_tab === 'prods' ? 'show active' : ''; ?>" id="tab-prods">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold mb-0"><i class="fas fa-bolt me-2 text-primary"></i>Trending &amp; Featured Motor Starters</h5>
                        <p class="text-muted small mb-0">Control the showcase for trending products with pricing, ratings &amp; WhatsApp ordering.</p>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="home_prods_enabled" id="home_prods_enabled" value="1" 
                               <?php echo get_home_setting('home_prods_enabled', '1') == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="home_prods_enabled">Show Featured Products</label>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Section Badge Pill</label>
                            <input type="text" name="home_prods_badge" class="form-control" 
                                   value="<?php echo htmlspecialchars(get_home_setting('home_prods_badge', $default_keys['home_prods_badge'])); ?>" placeholder="BESTSELLERS & TRENDING">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Section Main Title</label>
                            <input type="text" name="home_prods_title" class="form-control" 
                                   value="<?php echo htmlspecialchars(get_home_setting('home_prods_title', $default_keys['home_prods_title'])); ?>" placeholder="Featured Motor Starters">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Section Subtitle / Description</label>
                            <input type="text" name="home_prods_subtitle" class="form-control" 
                                   value="<?php echo htmlspecialchars(get_home_setting('home_prods_subtitle', $default_keys['home_prods_subtitle'])); ?>" placeholder="High-performance starters and panels...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Max Products to Display</label>
                            <input type="number" name="home_prods_count" class="form-control" min="4" max="24" step="4"
                                   value="<?php echo htmlspecialchars(get_home_setting('home_prods_count', $default_keys['home_prods_count'])); ?>" placeholder="12">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">View All Button Label</label>
                            <input type="text" name="home_prods_btn_text" class="form-control" 
                                   value="<?php echo htmlspecialchars(get_home_setting('home_prods_btn_text', $default_keys['home_prods_btn_text'])); ?>" placeholder="View All Products">
                        </div>
                    </div>
                    <div class="alert alert-info mt-4 mb-0 py-2 rounded-3 small">
                        <i class="fas fa-info-circle me-1"></i> Products marked as "Trending" in <a href="manage_products.php" class="fw-bold alert-link">Products &rarr; All Products</a> are featured here first.
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ 4. STARTER SELECTOR TAB ════════════════════════════════ -->
        <div class="tab-pane fade <?php echo $active_tab === 'selector' ? 'show active' : ''; ?>" id="tab-selector">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold mb-0"><i class="fas fa-sliders me-2 text-primary"></i>Interactive Motor Starter Selector</h5>
                        <p class="text-muted small mb-0">High-converting smart tool helping farmers and buyers find the right starter for their pump motor.</p>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="home_selector_enabled" id="home_selector_enabled" value="1" 
                               <?php echo get_home_setting('home_selector_enabled', '1') == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="home_selector_enabled">Show Starter Selector</label>
                    </div>
                </div>
                <div class="card-body p-4">
                    <!-- General Headings & Default Action -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Badge Text</label>
                            <input type="text" name="home_selector_badge" class="form-control" 
                                   value="<?php echo htmlspecialchars(get_home_setting('home_selector_badge', $default_keys['home_selector_badge'])); ?>" placeholder="SMART PRODUCT FINDER">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold">Selector Headline</label>
                            <input type="text" name="home_selector_title" class="form-control" 
                                   value="<?php echo htmlspecialchars(get_home_setting('home_selector_title', $default_keys['home_selector_title'])); ?>" placeholder="Find the Right Starter for Your Motor">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Search Button Text</label>
                            <input type="text" name="home_selector_btn_text" class="form-control" 
                                   value="<?php echo htmlspecialchars(get_home_setting('home_selector_btn_text', $default_keys['home_selector_btn_text'])); ?>" placeholder="Find Starters">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold text-success"><i class="fas fa-link me-1"></i>Default Page URL</label>
                            <input type="text" name="home_selector_action_url" class="form-control" 
                                   value="<?php echo htmlspecialchars(get_home_setting('home_selector_action_url', $default_keys['home_selector_action_url'])); ?>" placeholder="shop.php">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Selector Subtitle / Instructions</label>
                            <input type="text" name="home_selector_subtitle" class="form-control" 
                                   value="<?php echo htmlspecialchars(get_home_setting('home_selector_subtitle', $default_keys['home_selector_subtitle'])); ?>" placeholder="Select your motor specifications...">
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Step 1: Power Phase Configuration -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold text-primary mb-0"><i class="fas fa-bolt me-1"></i>Step 1: Power Phase Filter</h6>
                        </div>
                        <div class="row g-3 mb-2">
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Step Title Label</label>
                                <input type="text" name="home_selector_step1_label" class="form-control form-control-sm" 
                                       value="<?php echo htmlspecialchars(get_home_setting('home_selector_step1_label', $default_keys['home_selector_step1_label'])); ?>">
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="p-3 border rounded-3 bg-light">
                                    <span class="badge bg-secondary mb-2">Pill Option 1</span>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Display Text</label>
                                        <input type="text" name="home_selector_phase1_text" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_phase1_text', $default_keys['home_selector_phase1_text'])); ?>">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Filter Value (Empty for All)</label>
                                        <input type="text" name="home_selector_phase1_val" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_phase1_val', $default_keys['home_selector_phase1_val'])); ?>" placeholder="(All)">
                                    </div>
                                    <div>
                                        <label class="form-label small fw-bold text-success"><i class="fas fa-link me-1"></i>Redirect Link (Optional)</label>
                                        <input type="text" name="home_selector_phase1_link" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_phase1_link', $default_keys['home_selector_phase1_link'])); ?>" placeholder="e.g. shop.php">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 border rounded-3 bg-light">
                                    <span class="badge bg-primary mb-2">Pill Option 2</span>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Display Text</label>
                                        <input type="text" name="home_selector_phase2_text" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_phase2_text', $default_keys['home_selector_phase2_text'])); ?>">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Filter Value</label>
                                        <input type="text" name="home_selector_phase2_val" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_phase2_val', $default_keys['home_selector_phase2_val'])); ?>" placeholder="1-Phase">
                                    </div>
                                    <div>
                                        <label class="form-label small fw-bold text-success"><i class="fas fa-link me-1"></i>Redirect Link (Optional)</label>
                                        <input type="text" name="home_selector_phase2_link" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_phase2_link', $default_keys['home_selector_phase2_link'])); ?>" placeholder="e.g. shop.php?category=4">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 border rounded-3 bg-light">
                                    <span class="badge bg-primary mb-2">Pill Option 3</span>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Display Text</label>
                                        <input type="text" name="home_selector_phase3_text" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_phase3_text', $default_keys['home_selector_phase3_text'])); ?>">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Filter Value</label>
                                        <input type="text" name="home_selector_phase3_val" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_phase3_val', $default_keys['home_selector_phase3_val'])); ?>" placeholder="3-Phase">
                                    </div>
                                    <div>
                                        <label class="form-label small fw-bold text-success"><i class="fas fa-link me-1"></i>Redirect Link (Optional)</label>
                                        <input type="text" name="home_selector_phase3_link" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_phase3_link', $default_keys['home_selector_phase3_link'])); ?>" placeholder="e.g. shop.php?category=6">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Step 2: Motor Rating (HP) Configuration -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold text-primary mb-0"><i class="fas fa-gauge-high me-1"></i>Step 2: Motor Rating (HP) Filter</h6>
                        </div>
                        <div class="row g-3 mb-2">
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Step Title Label</label>
                                <input type="text" name="home_selector_step2_label" class="form-control form-control-sm" 
                                       value="<?php echo htmlspecialchars(get_home_setting('home_selector_step2_label', $default_keys['home_selector_step2_label'])); ?>">
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="p-3 border rounded-3 bg-light h-100">
                                    <span class="badge bg-secondary mb-2">HP Option 1</span>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Display Text</label>
                                        <input type="text" name="home_selector_hp1_text" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_hp1_text', $default_keys['home_selector_hp1_text'])); ?>">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Filter Value</label>
                                        <input type="text" name="home_selector_hp1_val" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_hp1_val', $default_keys['home_selector_hp1_val'])); ?>" placeholder="(All)">
                                    </div>
                                    <div>
                                        <label class="form-label small fw-bold text-success"><i class="fas fa-link me-1"></i>Redirect Link (Opt.)</label>
                                        <input type="text" name="home_selector_hp1_link" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_hp1_link', $default_keys['home_selector_hp1_link'])); ?>" placeholder="e.g. shop.php">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 border rounded-3 bg-light h-100">
                                    <span class="badge bg-primary mb-2">HP Option 2</span>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Display Text</label>
                                        <input type="text" name="home_selector_hp2_text" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_hp2_text', $default_keys['home_selector_hp2_text'])); ?>">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Filter Value</label>
                                        <input type="text" name="home_selector_hp2_val" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_hp2_val', $default_keys['home_selector_hp2_val'])); ?>" placeholder="1-3 HP">
                                    </div>
                                    <div>
                                        <label class="form-label small fw-bold text-success"><i class="fas fa-link me-1"></i>Redirect Link (Opt.)</label>
                                        <input type="text" name="home_selector_hp2_link" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_hp2_link', $default_keys['home_selector_hp2_link'])); ?>" placeholder="e.g. shop.php?hp=1-3+HP">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 border rounded-3 bg-light h-100">
                                    <span class="badge bg-primary mb-2">HP Option 3</span>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Display Text</label>
                                        <input type="text" name="home_selector_hp3_text" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_hp3_text', $default_keys['home_selector_hp3_text'])); ?>">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Filter Value</label>
                                        <input type="text" name="home_selector_hp3_val" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_hp3_val', $default_keys['home_selector_hp3_val'])); ?>" placeholder="5-7.5 HP">
                                    </div>
                                    <div>
                                        <label class="form-label small fw-bold text-success"><i class="fas fa-link me-1"></i>Redirect Link (Opt.)</label>
                                        <input type="text" name="home_selector_hp3_link" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_hp3_link', $default_keys['home_selector_hp3_link'])); ?>" placeholder="e.g. shop.php?hp=5-7.5+HP">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 border rounded-3 bg-light h-100">
                                    <span class="badge bg-primary mb-2">HP Option 4</span>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Display Text</label>
                                        <input type="text" name="home_selector_hp4_text" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_hp4_text', $default_keys['home_selector_hp4_text'])); ?>">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Filter Value</label>
                                        <input type="text" name="home_selector_hp4_val" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_hp4_val', $default_keys['home_selector_hp4_val'])); ?>" placeholder="10-25 HP">
                                    </div>
                                    <div>
                                        <label class="form-label small fw-bold text-success"><i class="fas fa-link me-1"></i>Redirect Link (Opt.)</label>
                                        <input type="text" name="home_selector_hp4_link" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_hp4_link', $default_keys['home_selector_hp4_link'])); ?>" placeholder="e.g. shop.php?hp=10-25+HP">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Step 3: Application / Motor Type Configuration -->
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold text-primary mb-0"><i class="fas fa-industry me-1"></i>Step 3: Application / Motor Type Filter</h6>
                        </div>
                        <div class="row g-3 mb-2">
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Step Title Label</label>
                                <input type="text" name="home_selector_step3_label" class="form-control form-control-sm" 
                                       value="<?php echo htmlspecialchars(get_home_setting('home_selector_step3_label', $default_keys['home_selector_step3_label'])); ?>">
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="p-3 border rounded-3 bg-light">
                                    <span class="badge bg-primary mb-2">App Option 1</span>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Display Text</label>
                                        <input type="text" name="home_selector_app1_text" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_app1_text', $default_keys['home_selector_app1_text'])); ?>">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Icon (FontAwesome)</label>
                                        <input type="text" name="home_selector_app1_icon" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_app1_icon', $default_keys['home_selector_app1_icon'])); ?>" placeholder="fas fa-water">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Filter Keyword</label>
                                        <input type="text" name="home_selector_app1_val" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_app1_val', $default_keys['home_selector_app1_val'])); ?>" placeholder="submersible">
                                    </div>
                                    <div>
                                        <label class="form-label small fw-bold text-success"><i class="fas fa-link me-1"></i>Redirect Page URL</label>
                                        <input type="text" name="home_selector_app1_link" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_app1_link', $default_keys['home_selector_app1_link'])); ?>" placeholder="e.g. shop.php?category=4">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 border rounded-3 bg-light">
                                    <span class="badge bg-primary mb-2">App Option 2</span>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Display Text</label>
                                        <input type="text" name="home_selector_app2_text" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_app2_text', $default_keys['home_selector_app2_text'])); ?>">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Icon (FontAwesome)</label>
                                        <input type="text" name="home_selector_app2_icon" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_app2_icon', $default_keys['home_selector_app2_icon'])); ?>" placeholder="fas fa-industry">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Filter Keyword</label>
                                        <input type="text" name="home_selector_app2_val" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_app2_val', $default_keys['home_selector_app2_val'])); ?>" placeholder="openwell">
                                    </div>
                                    <div>
                                        <label class="form-label small fw-bold text-success"><i class="fas fa-link me-1"></i>Redirect Page URL</label>
                                        <input type="text" name="home_selector_app2_link" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_app2_link', $default_keys['home_selector_app2_link'])); ?>" placeholder="e.g. shop.php?app=openwell">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 border rounded-3 bg-light">
                                    <span class="badge bg-primary mb-2">App Option 3</span>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Display Text</label>
                                        <input type="text" name="home_selector_app3_text" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_app3_text', $default_keys['home_selector_app3_text'])); ?>">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Icon (FontAwesome)</label>
                                        <input type="text" name="home_selector_app3_icon" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_app3_icon', $default_keys['home_selector_app3_icon'])); ?>" placeholder="fas fa-cog">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Filter Keyword</label>
                                        <input type="text" name="home_selector_app3_val" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_app3_val', $default_keys['home_selector_app3_val'])); ?>" placeholder="flourmill">
                                    </div>
                                    <div>
                                        <label class="form-label small fw-bold text-success"><i class="fas fa-link me-1"></i>Redirect Page URL</label>
                                        <input type="text" name="home_selector_app3_link" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_selector_app3_link', $default_keys['home_selector_app3_link'])); ?>" placeholder="e.g. shop.php?category=6">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ 5. PROMO SPOTLIGHTS TAB ════════════════════════════════ -->
        <div class="tab-pane fade <?php echo $active_tab === 'promo' ? 'show active' : ''; ?>" id="tab-promo">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold mb-0"><i class="fas fa-rectangle-ad me-2 text-primary"></i>Promotional Spotlight Banners</h5>
                        <p class="text-muted small mb-0">Featured spotlight cards highlighting key industrial &amp; agricultural ranges.</p>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="home_promo_enabled" id="home_promo_enabled" value="1" 
                               <?php echo get_home_setting('home_promo_enabled', '1') == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="home_promo_enabled">Show Promo Spotlights</label>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <!-- Promo 1 -->
                        <div class="col-lg-6">
                            <div class="p-3 border rounded-3 bg-light">
                                <h6 class="fw-bold text-primary mb-3"><i class="fas fa-water me-1"></i>Spotlight Card #1 (Left)</h6>
                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Badge</label>
                                    <input type="text" name="home_promo1_badge" class="form-control form-control-sm" 
                                           value="<?php echo htmlspecialchars(get_home_setting('home_promo1_badge', $default_keys['home_promo1_badge'])); ?>">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Title</label>
                                    <input type="text" name="home_promo1_title" class="form-control form-control-sm" 
                                           value="<?php echo htmlspecialchars(get_home_setting('home_promo1_title', $default_keys['home_promo1_title'])); ?>">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Description</label>
                                    <textarea name="home_promo1_desc" class="form-control form-control-sm" rows="3"><?php echo htmlspecialchars(get_home_setting('home_promo1_desc', $default_keys['home_promo1_desc'])); ?></textarea>
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label small fw-bold">Button Text</label>
                                        <input type="text" name="home_promo1_btn_text" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_promo1_btn_text', $default_keys['home_promo1_btn_text'])); ?>">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-bold">Button Link URL</label>
                                        <input type="text" name="home_promo1_btn_link" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_promo1_btn_link', $default_keys['home_promo1_btn_link'])); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Promo 2 -->
                        <div class="col-lg-6">
                            <div class="p-3 border rounded-3 bg-light">
                                <h6 class="fw-bold text-dark mb-3"><i class="fas fa-industry me-1"></i>Spotlight Card #2 (Right)</h6>
                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Badge</label>
                                    <input type="text" name="home_promo2_badge" class="form-control form-control-sm" 
                                           value="<?php echo htmlspecialchars(get_home_setting('home_promo2_badge', $default_keys['home_promo2_badge'])); ?>">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Title</label>
                                    <input type="text" name="home_promo2_title" class="form-control form-control-sm" 
                                           value="<?php echo htmlspecialchars(get_home_setting('home_promo2_title', $default_keys['home_promo2_title'])); ?>">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Description</label>
                                    <textarea name="home_promo2_desc" class="form-control form-control-sm" rows="3"><?php echo htmlspecialchars(get_home_setting('home_promo2_desc', $default_keys['home_promo2_desc'])); ?></textarea>
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label small fw-bold">Button Text</label>
                                        <input type="text" name="home_promo2_btn_text" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_promo2_btn_text', $default_keys['home_promo2_btn_text'])); ?>">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-bold">Button Link URL</label>
                                        <input type="text" name="home_promo2_btn_link" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars(get_home_setting('home_promo2_btn_link', $default_keys['home_promo2_btn_link'])); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ 6. INDUSTRIAL STATS TAB ════════════════════════════════ -->
        <div class="tab-pane fade <?php echo $active_tab === 'stats' ? 'show active' : ''; ?>" id="tab-stats">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold mb-0"><i class="fas fa-chart-line me-2 text-primary"></i>Industrial Excellence &amp; Trust Stats</h5>
                        <p class="text-muted small mb-0">Dynamic milestone counter boosting customer trust and social proof.</p>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="home_stats_enabled" id="home_stats_enabled" value="1" 
                               <?php echo get_home_setting('home_stats_enabled', '1') == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="home_stats_enabled">Show Stats Section</label>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Section Badge</label>
                            <input type="text" name="home_stats_badge" class="form-control" 
                                   value="<?php echo htmlspecialchars(get_home_setting('home_stats_badge', $default_keys['home_stats_badge'])); ?>" placeholder="PROVEN RELIABILITY">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Section Main Title</label>
                            <input type="text" name="home_stats_title" class="form-control" 
                                   value="<?php echo htmlspecialchars(get_home_setting('home_stats_title', $default_keys['home_stats_title'])); ?>" placeholder="Why Farmers & Engineers Trust Sagar Starter's">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Section Subtitle / Description</label>
                            <input type="text" name="home_stats_subtitle" class="form-control" 
                                   value="<?php echo htmlspecialchars(get_home_setting('home_stats_subtitle', $default_keys['home_stats_subtitle'])); ?>" placeholder="Over a decade of manufacturing excellence...">
                        </div>
                    </div>

                    <div class="row g-3">
                        <?php for ($s = 1; $s <= 4; $s++): ?>
                        <div class="col-md-6 col-lg-3">
                            <div class="p-3 border rounded-3 bg-light h-100">
                                <h6 class="fw-bold text-primary mb-2">Stat #<?php echo $s; ?></h6>
                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Number / Metric</label>
                                    <input type="text" name="home_stat<?php echo $s; ?>_num" class="form-control form-control-sm" 
                                           value="<?php echo htmlspecialchars(get_home_setting("home_stat{$s}_num", $default_keys["home_stat{$s}_num"])); ?>" placeholder="e.g. 15+">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Label / Title</label>
                                    <input type="text" name="home_stat<?php echo $s; ?>_label" class="form-control form-control-sm" 
                                           value="<?php echo htmlspecialchars(get_home_setting("home_stat{$s}_label", $default_keys["home_stat{$s}_label"])); ?>" placeholder="e.g. Years of Excellence">
                                </div>
                                <div class="mb-0">
                                    <label class="form-label small fw-bold">Icon (FontAwesome)</label>
                                    <input type="text" name="home_stat<?php echo $s; ?>_icon" class="form-control form-control-sm" 
                                           value="<?php echo htmlspecialchars(get_home_setting("home_stat{$s}_icon", $default_keys["home_stat{$s}_icon"])); ?>" placeholder="e.g. fas fa-star">
                                </div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ 7. ENGINEERING CTA TAB ═════════════════════════════════ -->
        <div class="tab-pane fade <?php echo $active_tab === 'cta' ? 'show active' : ''; ?>" id="tab-cta">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold mb-0"><i class="fas fa-headset me-2 text-primary"></i>Engineering Consultation &amp; Bulk Order CTA</h5>
                        <p class="text-muted small mb-0">Direct conversion banner encouraging farmers, dealers &amp; industrial buyers to connect directly.</p>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="home_cta_enabled" id="home_cta_enabled" value="1" 
                               <?php echo get_home_setting('home_cta_enabled', '1') == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="home_cta_enabled">Show Consultation CTA</label>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Badge Text</label>
                            <input type="text" name="home_cta_badge" class="form-control" 
                                   value="<?php echo htmlspecialchars(get_home_setting('home_cta_badge', $default_keys['home_cta_badge'])); ?>" placeholder="ENGINEERING CONSULTATION">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Main Headline</label>
                            <input type="text" name="home_cta_title" class="form-control" 
                                   value="<?php echo htmlspecialchars(get_home_setting('home_cta_title', $default_keys['home_cta_title'])); ?>" placeholder="Need a Custom Control Panel or Bulk Order?">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Description Text</label>
                            <textarea name="home_cta_desc" class="form-control" rows="2"><?php echo htmlspecialchars(get_home_setting('home_cta_desc', $default_keys['home_cta_desc'])); ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Primary Button Text (WhatsApp)</label>
                            <input type="text" name="home_cta_btn1_text" class="form-control" 
                                   value="<?php echo htmlspecialchars(get_home_setting('home_cta_btn1_text', $default_keys['home_cta_btn1_text'])); ?>" placeholder="Chat on WhatsApp">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Secondary Button Text (Call)</label>
                            <input type="text" name="home_cta_btn2_text" class="form-control" 
                                   value="<?php echo htmlspecialchars(get_home_setting('home_cta_btn2_text', $default_keys['home_cta_btn2_text'])); ?>" placeholder="Call Technical Support">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ 8. FAQ & BUYING GUIDE TAB ══════════════════════════════ -->
        <div class="tab-pane fade <?php echo $active_tab === 'faq' ? 'show active' : ''; ?>" id="tab-faq">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold mb-0"><i class="fas fa-circle-question me-2 text-primary"></i>FAQ &amp; Motor Starter Buyer's Guide</h5>
                        <p class="text-muted small mb-0">Helpful customer answers with automatic Google SEO FAQPage schema markup.</p>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="home_faq_enabled" id="home_faq_enabled" value="1" 
                               <?php echo get_home_setting('home_faq_enabled', '1') == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="home_faq_enabled">Show FAQ Section</label>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Section Badge</label>
                            <input type="text" name="home_faq_badge" class="form-control" 
                                   value="<?php echo htmlspecialchars(get_home_setting('home_faq_badge', $default_keys['home_faq_badge'])); ?>" placeholder="HELP & BUYING GUIDE">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Section Title</label>
                            <input type="text" name="home_faq_title" class="form-control" 
                                   value="<?php echo htmlspecialchars(get_home_setting('home_faq_title', $default_keys['home_faq_title'])); ?>" placeholder="Frequently Asked Questions">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Section Subtitle</label>
                            <input type="text" name="home_faq_subtitle" class="form-control" 
                                   value="<?php echo htmlspecialchars(get_home_setting('home_faq_subtitle', $default_keys['home_faq_subtitle'])); ?>" placeholder="Quick answers to help you choose...">
                        </div>
                    </div>

                    <div class="row g-3">
                        <?php for ($q = 1; $q <= 4; $q++): ?>
                        <div class="col-md-6">
                            <div class="p-3 border rounded-3 bg-light h-100">
                                <h6 class="fw-bold text-primary mb-2"><i class="fas fa-question-circle me-1"></i>FAQ Item #<?php echo $q; ?></h6>
                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Question</label>
                                    <input type="text" name="home_faq_q<?php echo $q; ?>" class="form-control form-control-sm" 
                                           value="<?php echo htmlspecialchars(get_home_setting("home_faq_q{$q}", $default_keys["home_faq_q{$q}"])); ?>">
                                </div>
                                <div class="mb-0">
                                    <label class="form-label small fw-bold">Answer</label>
                                    <textarea name="home_faq_a<?php echo $q; ?>" class="form-control form-control-sm" rows="3"><?php echo htmlspecialchars(get_home_setting("home_faq_a{$q}", $default_keys["home_faq_a{$q}"])); ?></textarea>
                                </div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Sticky Save Button Bar -->
    <div class="card border-0 shadow-sm rounded-4 mt-4 p-3 bg-white d-flex flex-row justify-content-between align-items-center">
        <div>
            <span class="text-muted small"><i class="fas fa-info-circle me-1 text-primary"></i>All changes save immediately and reflect live on your store.</span>
        </div>
        <button type="submit" class="btn btn-primary btn-lg btn-custom px-5">
            <i class="fas fa-save me-2"></i>Save All Changes
        </button>
    </div>

</form>

<?php include 'admin_footer.php'; ?>
