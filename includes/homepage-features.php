<?php
// includes/homepage-features.php
$features_enabled = $global_settings['homepage_features_enabled'] ?? '1';

if ($features_enabled == '1') {
    // Fetch active features
    $features_query = $conn->query("SELECT * FROM homepage_features WHERE status = 'active' ORDER BY display_order ASC, id DESC");
    
    if ($features_query && $features_query->num_rows > 0) {
        ?>
        <section class="feature-section mt-5 border-top">
            <div class="container">
                <div class="feature-grid">
                    <?php while ($f = $features_query->fetch_assoc()): ?>
                        <div class="feature-block border">
                            <div class="feature-icon-wrapper">
                                <?php if ($f['icon_type'] === 'font'): ?>
                                    <i class="<?php echo htmlspecialchars($f['icon_value']); ?> feature-icon-font"></i>
                                <?php else: 
                                    $icon_src = resolve_feature_icon_url($f['title'], $f['icon_value']);
                                    $fallback_font = get_feature_fallback_font_icon($f['title']);
                                ?>
                                    <img src="<?php echo htmlspecialchars($icon_src); ?>" alt="<?php echo htmlspecialchars($f['title']); ?> Icon" class="feature-icon-img" width="48" height="48" loading="lazy" decoding="async" onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                                    <i class="<?php echo htmlspecialchars($fallback_font); ?> feature-icon-font" style="display:none;"></i>
                                <?php endif; ?>
                            </div>
                            <h4 class="feature-title"><?php echo htmlspecialchars($f['title']); ?></h4>
                            <?php if (!empty($f['description'])): ?>
                                <p class="feature-desc"><?php echo nl2br(htmlspecialchars($f['description'])); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </section>
        <?php
    }
}
?>
