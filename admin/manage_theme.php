<?php
/**
 * manage_theme.php — Frontend Theme & Color Customizer
 * Admin-controlled design system. Stores in `settings` table.
 */

include 'admin_header.php';          // $conn, session, auth already done
require_once __DIR__ . '/../includes/ThemeService.php';

// ── Init defaults (inserts IGNORE on first visit) ────────────
ThemeService::initDefaults($conn);

$success = '';
$error   = '';

// ── POST: Save Settings ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_theme') {
    $data = [];
    $allowed = array_keys(ThemeService::getDefaults());
    foreach ($allowed as $key) {
        if (isset($_POST[$key])) {
            $data[$key] = $_POST[$key];
        }
    }
    ThemeService::saveTheme($conn, $data);
    $success = 'Theme settings saved! Changes are now live on the frontend.';
}

// ── POST: Reset to Defaults ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_theme') {
    ThemeService::resetDefaults($conn);
    $success = 'Theme reset to default values.';
}

// ── Load current theme for form population ────────────────────
$theme = ThemeService::getTheme($conn);

// ── Font list ─────────────────────────────────────────────────
$fontList = ['Poppins', 'Montserrat', 'Roboto', 'Open Sans', 'Lato', 'Nunito', 'Inter', 'Raleway', 'Playfair Display', 'Merriweather'];

$active_tab = $_GET['tab'] ?? 'colors';
?>

<div class="container-fluid px-4 py-4 adm-wrapper">
    <!-- Hero Header -->
    <div class="adm-hero">
        <div class="adm-hero-content">
            <div class="adm-hero-badge">
                <i class="fas fa-palette"></i> Design System & Styling
            </div>
            <h1 class="adm-hero-title">Storefront Theme Customizer</h1>
            <p class="adm-hero-subtitle">Fine-tune brand palettes, Google Fonts typography, header navigation layouts, and live real-time preview.</p>
        </div>
        <div class="adm-hero-actions">
            <button type="button" class="adm-btn-white" id="btnClearCache">
                <i class="fas fa-broom me-1"></i>Clear Cache
            </button>
            <button type="button" class="adm-btn-white" id="btnExport">
                <i class="fas fa-download me-1"></i>Export Theme
            </button>
            <label class="adm-btn-white mb-0" style="cursor:pointer;">
                <i class="fas fa-upload me-1"></i>Import Theme
                <input type="file" id="importFileInput" accept=".json" style="display:none;">
            </label>
            <form method="POST" onsubmit="return confirm('Reset ALL theme settings to factory defaults?');" class="d-inline m-0">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="reset_theme">
                <button type="submit" class="adm-btn-white text-danger border-danger">
                    <i class="fas fa-undo me-1"></i>Reset Default
                </button>
            </form>
        </div>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 py-3 mb-4" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 py-3 mb-4" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Toast for AJAX feedback -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">
        <div id="themeToast" class="toast align-items-center text-white border-0" role="alert" aria-live="assertive">
            <div class="d-flex">
                <div class="toast-body" id="themeToastMsg">Done.</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-mdb-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <!-- ═══ Main Form ═══════════════════════════════════════════════ -->
    <form method="POST" action="manage_theme.php?tab=<?php echo htmlspecialchars($active_tab); ?>" id="themeForm">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="save_theme">

        <!-- Tabs Nav -->
        <div class="adm-filter-tabs mb-4">
            <?php
            $tabs = [
                'colors'   => ['icon' => 'fa-fill-drip',  'label' => 'Color Settings'],
                'theme'    => ['icon' => 'fa-sun',         'label' => 'Theme & Typography'],
                'layout'   => ['icon' => 'fa-layer-group', 'label' => 'Header & Footer'],
                'preview'  => ['icon' => 'fa-eye',         'label' => 'Live Preview'],
            ];
            foreach ($tabs as $slug => $info):
                $isActive = ($active_tab === $slug);
            ?>
                <a class="adm-filter-tab <?php echo $isActive ? 'active' : ''; ?>" href="?tab=<?php echo $slug; ?>">
                    <i class="fas <?php echo $info['icon']; ?> me-1"></i><?php echo $info['label']; ?>
                </a>
            <?php endforeach; ?>
        </div>

    <div class="tab-content">

        <!-- ══ COLOR SETTINGS TAB ═══════════════════════════════ -->
        <div class="tab-pane fade <?php echo $active_tab === 'colors' ? 'show active' : ''; ?>" id="tab-colors">
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-header bg-white border-0 pt-4 pb-0">
                            <h5 class="fw-bold mb-0"><i class="fas fa-fill-drip me-2 text-primary"></i>Color Settings</h5>
                            <p class="text-muted small mt-1 mb-0">Control every color across the frontend using the pickers below.</p>
                        </div>
                        <div class="card-body p-4">
                            <?php
                            $colorFields = [
                                'theme_primary_color'      => ['label' => 'Primary Color',         'desc' => 'Main brand color used for links, accents, badges.'],
                                'theme_secondary_color'    => ['label' => 'Secondary Color',        'desc' => 'Used for muted text, borders, and secondary elements.'],
                                'theme_button_color'       => ['label' => 'Button Background',      'desc' => 'Background color of primary action buttons (Buy, Add to Cart).'],
                                'theme_button_hover_color' => ['label' => 'Button Hover Color',     'desc' => 'Button color on mouse hover.'],
                                'theme_header_bg'          => ['label' => 'Header Background',      'desc' => 'Navbar / topbar background.'],
                                'theme_footer_bg'          => ['label' => 'Footer Background',      'desc' => 'Footer section background.'],
                                'theme_text_color'         => ['label' => 'Body Text Color',        'desc' => 'Default text color throughout.'],
                                'theme_link_color'         => ['label' => 'Link Color',             'desc' => 'Color for hyperlinks.'],
                                'theme_footer_link_color'  => ['label' => 'Footer Link Color',      'desc' => 'Color for links in the footer section.'],
                            ];
                            foreach ($colorFields as $key => $field):
                                $val = htmlspecialchars($theme[$key] ?? '#000000');
                            ?>
                            <div class="mb-4 d-flex align-items-start gap-3">
                                <div class="position-relative mt-1">
                                    <input type="color"
                                           name="<?php echo $key; ?>"
                                           id="<?php echo $key; ?>"
                                           value="<?php echo $val; ?>"
                                           class="theme-color-picker"
                                           style="width:52px;height:52px;border:3px solid #dee2e6;border-radius:12px;cursor:pointer;padding:2px;">
                                </div>
                                <div class="flex-grow-1">
                                    <label for="<?php echo $key; ?>" class="form-label fw-bold mb-0">
                                        <?php echo $field['label']; ?>
                                    </label>
                                    <div class="text-muted small mb-1"><?php echo $field['desc']; ?></div>
                                    <input type="text"
                                           class="form-control form-control-sm hex-input"
                                           data-target="<?php echo $key; ?>"
                                           value="<?php echo $val; ?>"
                                           maxlength="7"
                                           placeholder="#000000"
                                           style="max-width:120px; font-family:monospace;">
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <!-- Color Preview Card -->
                    <div class="card border-0 shadow-sm rounded-4 sticky-top" style="top: 80px;">
                        <div class="card-header bg-white border-0 pt-4 pb-0">
                            <h6 class="fw-bold mb-0"><i class="fas fa-eye me-2 text-info"></i>Quick Color Preview</h6>
                        </div>
                        <div class="card-body p-4">
                            <div id="colorPreviewBox" style="border-radius:12px; overflow:hidden; border:1px solid #eee;">
                                <!-- Mini Header -->
                                <div id="previewHeader" style="background:<?php echo $theme['theme_header_bg']; ?>; padding:12px 16px; display:flex; align-items:center; gap:12px; border-bottom:1px solid #eee;">
                                    <div style="background:<?php echo $theme['theme_primary_color']; ?>; border-radius:4px; width:60px; height:20px;"></div>
                                    <div style="font-size:12px; color:<?php echo $theme['theme_text_color']; ?>; flex:1;">Nav Menu</div>
                                    <div id="previewBtn" style="background:<?php echo $theme['theme_button_color']; ?>; color:#fff; border-radius:6px; padding:4px 10px; font-size:11px;">Button</div>
                                </div>
                                <!-- Mini Body -->
                                <div id="previewBody" style="background:<?php echo $theme['theme_bg_color'] ?? '#f8f9fa'; ?>; padding:16px;">
                                    <div style="background:<?php echo $theme['theme_card_bg']; ?>; border-radius:8px; padding:12px; margin-bottom:8px; box-shadow:0 1px 4px rgba(0,0,0,0.06);">
                                        <div style="font-size:13px; color:<?php echo $theme['theme_text_color']; ?>; font-weight:600; margin-bottom:4px;">Card Title</div>
                                        <div style="font-size:11px; color:<?php echo $theme['theme_secondary_color']; ?>;">Secondary text sample</div>
                                        <a href="#" class="preview-body-link" style="font-size:11px; color:<?php echo $theme['theme_link_color']; ?>; text-decoration:none;">Link text →</a>
                                    </div>
                                </div>
                                <!-- Mini Footer -->
                                <div id="previewFooter" style="background:<?php echo $theme['theme_footer_bg']; ?>; padding:10px 16px; font-size:11px; color:<?php echo $theme['theme_footer_link_color']; ?>; text-align:center; opacity:0.9;">
                                    Footer Area Sample
                                </div>
                            </div>
                            <p class="text-muted small mt-3 mb-0 text-center">
                                <i class="fas fa-info-circle me-1"></i>Updates live as you pick colors.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ THEME & TYPOGRAPHY TAB ═══════════════════════════ -->
        <div class="tab-pane fade <?php echo $active_tab === 'theme' ? 'show active' : ''; ?>" id="tab-theme">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-header bg-white border-0 pt-4 pb-0">
                            <h5 class="fw-bold mb-0"><i class="fas fa-paint-roller me-2 text-primary"></i>Theme Settings</h5>
                        </div>
                        <div class="card-body p-4">

                            <!-- Dark Mode Toggle -->
                            <div class="mb-4 p-3 rounded-3" style="background:#f7f8fa; border:1px solid #e9ecef;">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="fw-bold"><i class="fas fa-moon me-2 text-primary"></i>Light / Dark Mode</div>
                                        <small class="text-muted">Switch your site between light and dark design.</small>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                               name="theme_mode" id="darkModeToggle"
                                               value="dark"
                                               <?php echo ($theme['theme_mode'] === 'dark') ? 'checked' : ''; ?>>
                                        <label class="form-check-label ms-2 fs-6 text-muted" for="darkModeToggle">
                                            <?php echo ($theme['theme_mode'] === 'dark') ? 'Dark' : 'Light'; ?> Mode
                                        </label>
                                    </div>
                                    <input type="hidden" name="theme_mode" id="theme_mode_hidden"
                                           value="<?php echo htmlspecialchars($theme['theme_mode']); ?>">
                                </div>
                            </div>

                            <!-- Automatic Text Contrast Toggle -->
                            <div class="mb-4 p-3 rounded-3" style="background:#f0f7ff; border:1px solid #cce5ff;">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="fw-bold"><i class="fas fa-adjust me-2 text-primary"></i>Automatic Text Contrast</div>
                                        <small class="text-muted">Dynamically adjust text color based on section backgrounds.</small>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                               id="autoContrastToggle"
                                               <?php echo ($theme['auto_text_contrast'] === '1') ? 'checked' : ''; ?>>
                                        <input type="hidden" name="auto_text_contrast" id="auto_contrast_hidden"
                                               value="<?php echo htmlspecialchars($theme['auto_text_contrast']); ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Background Color -->
                            <div class="mb-4 d-flex align-items-start gap-3">
                                <input type="color" name="theme_bg_color" id="theme_bg_color"
                                       value="<?php echo htmlspecialchars($theme['theme_bg_color']); ?>"
                                       class="theme-color-picker"
                                       style="width:52px;height:52px;border:3px solid #dee2e6;border-radius:12px;cursor:pointer;padding:2px;">
                                <div class="flex-grow-1">
                                    <label class="form-label fw-bold mb-0" for="theme_bg_color">Website Background Color</label>
                                    <div class="text-muted small mb-1">Page background color behind all content.</div>
                                    <input type="text" class="form-control form-control-sm hex-input"
                                           data-target="theme_bg_color"
                                           value="<?php echo htmlspecialchars($theme['theme_bg_color']); ?>"
                                           maxlength="7" style="max-width:120px;font-family:monospace;">
                                </div>
                            </div>

                            <!-- Card BG Color -->
                            <div class="mb-4 d-flex align-items-start gap-3">
                                <input type="color" name="theme_card_bg" id="theme_card_bg"
                                       value="<?php echo htmlspecialchars($theme['theme_card_bg']); ?>"
                                       class="theme-color-picker"
                                       style="width:52px;height:52px;border:3px solid #dee2e6;border-radius:12px;cursor:pointer;padding:2px;">
                                <div class="flex-grow-1">
                                    <label class="form-label fw-bold mb-0" for="theme_card_bg">Card / Section Background</label>
                                    <div class="text-muted small mb-1">Background of cards, panels, and content sections.</div>
                                    <input type="text" class="form-control form-control-sm hex-input"
                                           data-target="theme_card_bg"
                                           value="<?php echo htmlspecialchars($theme['theme_card_bg']); ?>"
                                           maxlength="7" style="max-width:120px;font-family:monospace;">
                                </div>
                            </div>

                            <hr class="my-4">
                            <h6 class="fw-bold text-secondary mb-3">Typography</h6>

                            <!-- Font Family -->
                            <div class="mb-4">
                                <label class="form-label fw-bold" for="theme_font_family">
                                    <i class="fas fa-font me-2 text-primary"></i>Font Family (Google Fonts)
                                </label>
                                <select name="theme_font_family" id="theme_font_family" class="form-select"
                                        style="font-size:1rem;">
                                    <?php foreach ($fontList as $font): ?>
                                    <option value="<?php echo $font; ?>"
                                            <?php echo ($theme['theme_font_family'] === $font) ? 'selected' : ''; ?>
                                            style="font-family:'<?php echo $font; ?>'">
                                        <?php echo $font; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Font is automatically loaded from Google Fonts. Applied sitewide.</small>
                            </div>

                            <!-- Font Size -->
                            <div class="mb-4">
                                <label class="form-label fw-bold" for="theme_font_size">
                                    <i class="fas fa-text-height me-2 text-primary"></i>Base Font Size
                                    <span class="badge bg-primary ms-2" id="fontSizeBadge"><?php echo intval($theme['theme_font_size']); ?>px</span>
                                </label>
                                <input type="range" class="form-range" name="theme_font_size" id="theme_font_size"
                                       min="12" max="24" step="1"
                                       value="<?php echo intval($theme['theme_font_size']); ?>"
                                       oninput="document.getElementById('fontSizeBadge').textContent=this.value+'px'">
                                <div class="d-flex justify-content-between text-muted small">
                                    <span>12px (Small)</span>
                                    <span>16px (Default)</span>
                                    <span>24px (Large)</span>
                                </div>
                            </div>
                            
                            <!-- Product Title Size -->
                            <div class="mb-4">
                                <label class="form-label fw-bold" for="theme_product_title_size">
                                    <i class="fas fa-heading me-2 text-primary"></i>Product Title Size
                                    <span class="badge bg-primary ms-2" id="productTitleSizeBadge"><?php echo intval($theme['theme_product_title_size'] ?? 18); ?>px</span>
                                </label>
                                <input type="range" class="form-range" name="theme_product_title_size" id="theme_product_title_size"
                                       min="12" max="48" step="1"
                                       value="<?php echo intval($theme['theme_product_title_size'] ?? 18); ?>"
                                       oninput="document.getElementById('productTitleSizeBadge').textContent=this.value+'px'">
                                <div class="d-flex justify-content-between text-muted small">
                                    <span>12px (Small)</span>
                                    <span>18px (Default)</span>
                                    <span>48px (Huge)</span>
                                </div>
                            </div>

                            <!-- Border Radius -->
                            <div class="mb-4">
                                <label class="form-label fw-bold" for="theme_border_radius">
                                    <i class="fas fa-border-style me-2 text-primary"></i>Border Radius (Roundness)
                                    <span class="badge bg-primary ms-2" id="borderRadiusBadge"><?php echo intval($theme['theme_border_radius']); ?>px</span>
                                </label>
                                <input type="range" class="form-range" name="theme_border_radius" id="theme_border_radius"
                                       min="0" max="50" step="1"
                                       value="<?php echo intval($theme['theme_border_radius']); ?>"
                                       oninput="document.getElementById('borderRadiusBadge').textContent=this.value+'px'">
                                <div class="d-flex justify-content-between text-muted small">
                                    <span>0 (Sharp)</span>
                                    <span>8 (Default)</span>
                                    <span>50 (Pill)</span>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Font Preview -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 sticky-top" style="top:80px;">
                        <div class="card-header bg-white border-0 pt-4 pb-0">
                            <h6 class="fw-bold mb-0"><i class="fas fa-eye me-2 text-info"></i>Typography Preview</h6>
                        </div>
                        <div class="card-body p-4" id="fontPreviewBox">
                            <div style="font-size:24px; font-weight:700; margin-bottom:8px;" id="prevFontH1">Heading Text</div>
                            <div style="font-size:18px; font-weight:600; margin-bottom:8px;" id="prevFontH2">Sub-Heading</div>
                            <div style="font-size:16px; margin-bottom:8px;" id="prevFontBody">Body paragraph text. This is how your site content will look.</div>
                            <div style="font-size:14px; color:#6c757d;" id="prevFontSmall">Small / helper text sample.</div>
                            <hr>
                            <div style="padding:8px 16px; background:var(--btn-color,#0d6efd); color:#fff; border-radius:8px; display:inline-block; font-size:14px; font-weight:500;">
                                Sample Button
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ HEADER & FOOTER TAB ══════════════════════════════ -->
        <div class="tab-pane fade <?php echo $active_tab === 'layout' ? 'show active' : ''; ?>" id="tab-layout">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white border-0 pt-4 pb-0">
                        <h5 class="fw-bold mb-0"><i class="fas fa-layer-group me-2 text-primary"></i>Header & Footer Layout</h5>
                    </div>
                    <div class="card-body p-4">

                        <!-- Header Style -->
                        <div class="mb-5">
                            <label class="form-label fw-bold mb-3"><i class="fas fa-window-maximize me-2 text-primary"></i>Header Style</label>
                            <div class="row g-3">
                                <?php
                                $headerStyles = [
                                    'default'  => ['icon' => 'fa-align-left',   'desc' => 'Logo left, menu center (Standard)'],
                                    'centered' => ['icon' => 'fa-align-center',  'desc' => 'Centered logo & navigation'],
                                    'minimal'  => ['icon' => 'fa-minus',         'desc' => 'Minimal slim header bar'],
                                ];
                                foreach ($headerStyles as $val => $info):
                                    $checked = ($theme['theme_header_style'] === $val) ? 'checked' : '';
                                ?>
                                <div class="col-md-4">
                                    <input type="radio" class="btn-check" name="theme_header_style"
                                           id="hs_<?php echo $val; ?>" value="<?php echo $val; ?>" <?php echo $checked; ?>>
                                    <label class="btn btn-outline-secondary w-100 py-3 text-start" for="hs_<?php echo $val; ?>">
                                        <i class="fas <?php echo $info['icon']; ?> d-block mb-2 fs-4 text-primary"></i>
                                        <strong><?php echo ucfirst($val); ?></strong>
                                        <div class="small text-muted mt-1"><?php echo $info['desc']; ?></div>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Sticky Header -->
                        <div class="mb-5 p-3 rounded-3" style="background:#f7f8fa; border:1px solid #e9ecef;">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="fw-bold"><i class="fas fa-thumbtack me-2 text-primary"></i>Sticky Header</div>
                                    <small class="text-muted">Header stays visible when the user scrolls down.</small>
                                </div>
                                <div class="form-check form-switch fs-4 mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="stickyHeaderCheck"
                                           <?php echo ($theme['theme_sticky_header'] === '1') ? 'checked' : ''; ?>>
                                    <input type="hidden" name="theme_sticky_header" id="sticky_header_hidden"
                                           value="<?php echo htmlspecialchars($theme['theme_sticky_header']); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Footer Layout -->
                        <div class="mb-4">
                            <label class="form-label fw-bold mb-3"><i class="fas fa-window-restore me-2 text-primary"></i>Footer Layout</label>
                            <div class="row g-3">
                                <?php
                                $footerLayouts = [
                                    'default'  => ['icon' => 'fa-th-large',    'desc' => '4-column layout (Default)'],
                                    'three_col'=> ['icon' => 'fa-columns',     'desc' => '3-column compact layout'],
                                    'centered' => ['icon' => 'fa-align-center', 'desc' => 'Centered single-column'],
                                ];
                                foreach ($footerLayouts as $val => $info):
                                    $checked = ($theme['theme_footer_layout'] === $val) ? 'checked' : '';
                                ?>
                                <div class="col-md-4">
                                    <input type="radio" class="btn-check" name="theme_footer_layout"
                                           id="fl_<?php echo $val; ?>" value="<?php echo $val; ?>" <?php echo $checked; ?>>
                                    <label class="btn btn-outline-secondary w-100 py-3 text-start" for="fl_<?php echo $val; ?>">
                                        <i class="fas <?php echo $info['icon']; ?> d-block mb-2 fs-4 text-primary"></i>
                                        <strong><?php echo ucfirst(str_replace('_', ' ', $val)); ?></strong>
                                        <div class="small text-muted mt-1"><?php echo $info['desc']; ?></div>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- ══ LIVE PREVIEW TAB ══════════════════════════════════ -->
        <div class="tab-pane fade <?php echo $active_tab === 'preview' ? 'show active' : ''; ?>" id="tab-preview">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 pb-0 d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="fw-bold mb-0"><i class="fas fa-eye me-2 text-primary"></i>Live Preview</h5>
                        <small class="text-muted">Changes from the Color Settings tab are reflected here in real-time.</small>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary" id="previewMobile" title="Switch to Mobile View">
                            <i class="fas fa-mobile-alt me-1"></i> Mobile
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" id="previewDesktop" title="Switch to Desktop View">
                            <i class="fas fa-desktop me-1"></i> Desktop
                        </button>
                        <a href="<?php echo (defined('STORE_BASE_URL') ? STORE_BASE_URL : '../'); ?>index.php" target="_blank"
                           class="btn btn-sm btn-primary">
                            <i class="fas fa-external-link-alt me-1"></i> Open Live Site
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div style="background:#e9ecef; padding:8px; border-bottom:1px solid #dee2e6;">
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:12px;height:12px;border-radius:50%;background:#ff5f57;"></div>
                            <div style="width:12px;height:12px;border-radius:50%;background:#febc2e;"></div>
                            <div style="width:12px;height:12px;border-radius:50%;background:#28c840;"></div>
                            <div class="flex-grow-1 mx-2">
                                <div style="background:#fff; border-radius:6px; padding:4px 12px; font-size:12px; color:#666;">
                                    <?php echo defined('SITE_URL') ? SITE_URL : 'http://localhost/store'; ?>/index.php
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="previewWrapper" style="width:100%; transition:width 0.3s ease; overflow-x:auto;">
                        <iframe id="livePreviewFrame"
                                src="<?php echo defined('SITE_URL') ? SITE_URL : ''; ?>/index.php"
                                style="width:100%; height:700px; border:none; display:block;"
                                title="Live Site Preview">
                        </iframe>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /.tab-content -->

    <!-- ── Save Button ─────────────────────────────────────── -->
    <?php if ($active_tab !== 'preview'): ?>
    <div class="mt-4 d-flex gap-3 align-items-center">
        <button type="submit" class="btn btn-primary btn-lg px-5 shadow-sm">
            <i class="fas fa-save me-2"></i>Save Settings
        </button>
        <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Changes apply immediately to the frontend after saving.</small>
    </div>
    <?php endif; ?>

</form>

<!-- Hidden import form -->
<form id="importForm" method="POST" enctype="multipart/form-data" action="ajax_theme_action.php" style="display:none;">
    <?php echo csrf_input(); ?>
    <input type="hidden" name="action" value="import">
    <input type="file" name="theme_file" id="importFileHidden">
</form>

<!-- ═══════════════════════ JavaScript ═══════════════════════ -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Utility: show toast ──────────────────────────────────
    function showToast(msg, type) {
        var toast = document.getElementById('themeToast');
        var msgEl = document.getElementById('themeToastMsg');
        toast.classList.remove('bg-success', 'bg-danger', 'bg-info', 'bg-warning');
        toast.classList.add('bg-' + (type || 'success'));
        msgEl.textContent = msg;
        var t = new mdb.Toast(toast, { delay: 4000 });
        t.show();
    }

    // ── Color picker ↔ Hex input sync ────────────────────────
    document.querySelectorAll('.theme-color-picker').forEach(function (picker) {
        picker.addEventListener('input', function () {
            var key = picker.getAttribute('name') || picker.getAttribute('id');
            var hex = picker.value;

            // Sync hex input
            var hexInput = document.querySelector('.hex-input[data-target="' + key + '"]');
            if (hexInput) hexInput.value = hex;

            // Live update the quick preview box
            updateColorPreview(key, hex);
        });
    });

    // ── Hex text input → color picker sync ──────────────────
    document.querySelectorAll('.hex-input').forEach(function (input) {
        input.addEventListener('input', function () {
            var key = input.dataset.target;
            var hex = input.value;
            if (/^#[0-9a-fA-F]{6}$/.test(hex)) {
                var picker = document.getElementById(key);
                if (picker) picker.value = hex;
                updateColorPreview(key, hex);
            }
        });
    });

    function updateColorPreview(key, hex) {
        var ph = document.getElementById('previewHeader');
        var pb = document.getElementById('previewBody');
        var pf = document.getElementById('previewFooter');
        var pbtn = document.getElementById('previewBtn');

        if (!ph) return;
        if (key === 'theme_header_bg')       ph.style.background   = hex;
        if (key === 'theme_footer_bg')        pf.style.background   = hex;
        if (key === 'theme_bg_color')         pb.style.background   = hex;
        if (key === 'theme_button_color')     pbtn.style.background = hex;
        if (key === 'theme_footer_link_color') {
             pf.style.color = hex;
             pb.querySelectorAll('.preview-footer-link').forEach(el => el.style.color = hex);
        }
        if (key === 'theme_primary_color') {
            ph.querySelector('div').style.background = hex;
        }
        if (key === 'theme_text_color') {
            pf.style.color = hex;
        }
        if (key === 'theme_link_color') {
             pb.querySelectorAll('.preview-body-link').forEach(el => el.style.color = hex);
        }

        // Also update the live iframe via postMessage
        var iframe = document.getElementById('livePreviewFrame');
        if (iframe && iframe.contentWindow) {
            try {
                iframe.contentWindow.postMessage({
                    type: 'THEME_PREVIEW',
                    key: key,
                    value: hex
                }, '*');
            } catch(e) {}
        }

        // Notify auto-contrast to re-scan all buttons on this page
        window.dispatchEvent(new Event('themeColorChanged'));
    }

    // ── Dark mode toggle label update ────────────────────────
    var darkToggle = document.getElementById('darkModeToggle');
    var modeHidden = document.getElementById('theme_mode_hidden');
    if (darkToggle && modeHidden) {
        darkToggle.addEventListener('change', function () {
            var lbl = darkToggle.nextElementSibling;
            if (darkToggle.checked) {
                modeHidden.value = 'dark';
                if (lbl) lbl.textContent = 'Dark Mode';
            } else {
                modeHidden.value = 'light';
                if (lbl) lbl.textContent = 'Light Mode';
            }
        });
        // Remove the duplicate name to avoid double-posting
        darkToggle.removeAttribute('name');
    }

    // ── Sticky header toggle ─────────────────────────────────
    var stickyCheck  = document.getElementById('stickyHeaderCheck');
    var stickyHidden = document.getElementById('sticky_header_hidden');
    if (stickyCheck && stickyHidden) {
        stickyCheck.addEventListener('change', function () {
            stickyHidden.value = stickyCheck.checked ? '1' : '0';
        });
    }

    // ── Auto Contrast toggle ─────────────────────────────────
    var acToggle = document.getElementById('autoContrastToggle');
    var acHidden = document.getElementById('auto_contrast_hidden');
    if (acToggle && acHidden) {
        acToggle.addEventListener('change', function () {
            acHidden.value = acToggle.checked ? '1' : '0';
        });
    }

    // ── Font preview update ──────────────────────────────────
    var fontSel = document.getElementById('theme_font_family');
    if (fontSel) {
        function applyFontPreview(fontName) {
            var link = document.getElementById('previewFontLink');
            if (!link) {
                link = document.createElement('link');
                link.id  = 'previewFontLink';
                link.rel = 'stylesheet';
                document.head.appendChild(link);
            }
            var enc = fontName.replace(/ /g, '+');
            link.href = 'https://fonts.googleapis.com/css2?family=' + enc + ':wght@400;600;700&display=swap';

            var box = document.getElementById('fontPreviewBox');
            if (box) box.style.fontFamily = "'" + fontName + "', sans-serif";
        }
        fontSel.addEventListener('change', function () { applyFontPreview(fontSel.value); });
        applyFontPreview(fontSel.value);
    }

    // ── Preview: mobile/desktop resize ──────────────────────
    document.getElementById('previewMobile') && document.getElementById('previewMobile').addEventListener('click', function () {
        document.getElementById('livePreviewFrame').style.width = '375px';
        document.getElementById('previewWrapper').style.overflow = 'auto';
    });
    document.getElementById('previewDesktop') && document.getElementById('previewDesktop').addEventListener('click', function () {
        document.getElementById('livePreviewFrame').style.width = '100%';
    });

    // ── Export ───────────────────────────────────────────────
    document.getElementById('btnExport') && document.getElementById('btnExport').addEventListener('click', function () {
        window.location.href = 'ajax_theme_action.php?action=export';
    });

    // ── Import ───────────────────────────────────────────────
    var importFileInput = document.getElementById('importFileInput');
    if (importFileInput) {
        importFileInput.addEventListener('change', function () {
            if (!importFileInput.files.length) return;
            var formData = new FormData();
            formData.append('action', 'import');
            formData.append('theme_file', importFileInput.files[0]);
            formData.append('_csrf_token', document.querySelector('input[name="_csrf_token"]').value);
            fetch('ajax_theme_action.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(function (res) {
                    showToast(res.message || 'Done.', res.success ? 'success' : 'danger');
                    if (res.success) {
                        setTimeout(function () { location.reload(); }, 2000);
                    }
                })
                .catch(function () { showToast('Import failed. Check file format.', 'danger'); });
            importFileInput.value = '';
        });
    }

    // ── Clear Cache ──────────────────────────────────────────
    document.getElementById('btnClearCache') && document.getElementById('btnClearCache').addEventListener('click', function () {
        var formData = new FormData();
        formData.append('action', 'clear_cache');
        formData.append('_csrf_token', document.querySelector('input[name="_csrf_token"]').value);
        fetch('ajax_theme_action.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(function (res) {
                showToast(res.message || 'Cache cleared.', res.success ? 'success' : 'warning');
            })
            .catch(function () { showToast('Cache clear request failed.', 'danger'); });
    });

});
</script>
</div>
<?php include 'admin_footer.php'; ?>
