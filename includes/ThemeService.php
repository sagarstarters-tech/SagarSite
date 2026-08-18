<?php
/**
 * ============================================================
 *  ThemeService — Frontend Theme & Color Customizer
 *  Location: /includes/ThemeService.php
 * ============================================================
 *  Manages all theme/color settings stored in the `settings`
 *  table under the `theme_` key prefix.
 *
 *  Usage (in includes/header.php, inside <head>):
 *      require_once __DIR__ . '/ThemeService.php';
 *      ThemeService::injectCSS($conn);
 * ============================================================
 */

class ThemeService
{
    // ── Default theme values ─────────────────────────────────
    public static function getDefaults(): array
    {
        return [
            'theme_primary_color'      => '#0d6efd',
            'theme_secondary_color'    => '#6c757d',
            'theme_button_color'       => '#0d6efd',
            'theme_button_hover_color' => '#0a58ca',
            'theme_header_bg'          => '#ffffff',
            'theme_footer_bg'          => '#ebebeb',
            'theme_text_color'         => '#333333',
            'theme_link_color'         => '#0d6efd',
            'theme_footer_link_color'  => '#333333',
            'theme_mode'               => 'light',
            'theme_bg_color'           => '#f8f9fa',
            'theme_card_bg'            => '#ffffff',
            'theme_border_radius'      => '8',
            'theme_font_family'        => 'Poppins',
            'theme_font_size'          => '16',
            'theme_product_title_size' => '18',
            'theme_header_style'       => 'default',
            'theme_footer_layout'      => 'default',
            'theme_sticky_header'      => '1',
            'auto_text_contrast'       => '1',
        ];
    }

    // ── Load theme from DB (falls back to defaults) ──────────
    public static function getTheme($conn): array
    {
        $defaults = self::getDefaults();
        $keys     = array_keys($defaults);
        $in       = implode(',', array_map(fn($k) => "'$k'", $keys));

        $result = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($in)");
        if (!$result) {
            return $defaults;
        }

        $theme = $defaults;
        while ($row = $result->fetch_assoc()) {
            $theme[$row['setting_key']] = $row['setting_value'];
        }
        return $theme;
    }

    // ── Initialise defaults in DB (run once) ─────────────────
    public static function initDefaults($conn): void
    {
        foreach (self::getDefaults() as $key => $val) {
            $k = $conn->real_escape_string($key);
            $v = $conn->real_escape_string($val);
            $conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('$k', '$v')");
        }
    }

    // ── Mass-save theme settings ─────────────────────────────
    public static function saveTheme($conn, array $data): void
    {
        $allowed = array_keys(self::getDefaults());
        foreach ($allowed as $key) {
            if (!isset($data[$key])) {
                continue;
            }
            $sanitized = self::sanitizeValue($key, (string)$data[$key]);
            $k = $conn->real_escape_string($key);
            $v = $conn->real_escape_string($sanitized);
            $conn->query("INSERT INTO settings (setting_key, setting_value)
                          VALUES ('$k', '$v')
                          ON DUPLICATE KEY UPDATE setting_value='$v'");
        }
    }

    // ── Reset all theme keys to defaults ─────────────────────
    public static function resetDefaults($conn): void
    {
        self::saveTheme($conn, self::getDefaults());
    }

    // ── Validate color hex value ─────────────────────────────
    private static function isValidHex(string $val): bool
    {
        return (bool) preg_match('/^#[0-9a-fA-F]{3,8}$/', $val);
    }

    // ── Sanitize a single theme value ────────────────────────
    private static function sanitizeValue(string $key, string $val): string
    {
        $colorKeys = [
            'theme_primary_color', 'theme_secondary_color',
            'theme_button_color', 'theme_button_hover_color',
            'theme_header_bg', 'theme_footer_bg',
            'theme_text_color', 'theme_link_color', 'theme_footer_link_color',
            'theme_bg_color', 'theme_card_bg',
        ];

        if (in_array($key, $colorKeys)) {
            return self::isValidHex($val) ? $val : self::getDefaults()[$key];
        }
        if ($key === 'theme_border_radius') {
            $n = intval($val);
            return ($n >= 0 && $n <= 50) ? (string)$n : '8';
        }
        if ($key === 'theme_font_size') {
            $n = intval($val);
            return ($n >= 10 && $n <= 32) ? (string)$n : '16';
        }
        if ($key === 'theme_product_title_size') {
            $n = intval($val);
            return ($n >= 10 && $n <= 72) ? (string)$n : '18';
        }
        if ($key === 'theme_mode') {
            return in_array($val, ['light', 'dark']) ? $val : 'light';
        }
        if ($key === 'theme_sticky_header' || $key === 'auto_text_contrast') {
            return in_array($val, ['0', '1']) ? $val : '1';
        }
        // Font family, header_style, footer_layout — strip tags
        return htmlspecialchars(strip_tags($val), ENT_QUOTES, 'UTF-8');
    }

    // ── Inject Google Fonts <link> based on font choice ──────
    private static function googleFontLink(string $font): string
    {
        // Poppins and Montserrat are already loaded in header.php
        if (in_array($font, ['Poppins', 'Montserrat'], true)) {
            return '';
        }
        $safeFonts = [
            'Roboto'     => 'Roboto:wght@300;400;500;700',
            'Open Sans'  => 'Open+Sans:wght@300;400;600;700',
            'Lato'       => 'Lato:wght@300;400;700',
            'Nunito'     => 'Nunito:wght@300;400;600;700',
            'Inter'      => 'Inter:wght@300;400;500;600;700',
            'Raleway'    => 'Raleway:wght@300;400;600;700',
            'Playfair Display' => 'Playfair+Display:wght@400;600;700',
            'Merriweather' => 'Merriweather:wght@300;400;700',
        ];
        if (!isset($safeFonts[$font])) {
            return '';
        }
        $enc = $safeFonts[$font];
        return "<link href=\"https://fonts.googleapis.com/css2?family={$enc}&display=swap\" rel=\"stylesheet\">\n";
    }

    // ── Main: inject CSS variables into <head> ───────────────
    public static function injectCSS($conn): void
    {
        $theme = self::getTheme($conn);

        // Dark-mode overrides
        $isDark = ($theme['theme_mode'] === 'dark');
        if ($isDark) {
            if ($theme['theme_bg_color']   === '#f8f9fa') $theme['theme_bg_color']   = '#121212';
            if ($theme['theme_card_bg']    === '#ffffff') $theme['theme_card_bg']    = '#1e1e1e';
            if ($theme['theme_text_color'] === '#333333') $theme['theme_text_color'] = '#e0e0e0';
            if ($theme['theme_header_bg']  === '#ffffff') $theme['theme_header_bg']  = '#1a1a2e';
            if ($theme['theme_footer_bg']  === '#ebebeb') $theme['theme_footer_bg']  = '#0d0d0d';
        }

        $br      = intval($theme['theme_border_radius']);
        $fs      = intval($theme['theme_font_size']);
        $pts     = intval($theme['theme_product_title_size'] ?? 18);
        $font    = $theme['theme_font_family'];
        $sticky  = ($theme['theme_sticky_header'] === '1') ? 'sticky' : 'relative';

        echo self::googleFontLink($font);

        echo "<style>\n";
        echo ":root {\n";
        echo "  --primary:          {$theme['theme_primary_color']};\n";
        echo "  --secondary:        {$theme['theme_secondary_color']};\n";
        echo "  --btn-color:        {$theme['theme_button_color']};\n";
        echo "  --btn-hover:        {$theme['theme_button_hover_color']};\n";
        echo "  --header-bg:        {$theme['theme_header_bg']};\n";
        echo "  --footer-bg:        {$theme['theme_footer_bg']};\n";
        echo "  --text-color:       {$theme['theme_text_color']};\n";
        echo "  --link-color:       {$theme['theme_link_color']};\n  --footer-link-color: {$theme['theme_footer_link_color']};\n";
        echo "  --bg-color:         {$theme['theme_bg_color']};\n";
        echo "  --card-bg:          {$theme['theme_card_bg']};\n";
        echo "  --border-radius:    {$br}px;\n";
        echo "  --font-family:      '{$font}', sans-serif;\n";
        echo "  --font-size:        {$fs}px;\n";
        echo "  --product-title-size: {$pts}px;\n";
        echo "}\n";

        // Apply CSS variables to standard elements
        echo "
body {
    background-color: var(--bg-color) !important;
    color: var(--text-color) !important;
    font-family: var(--font-family) !important;
    font-size: var(--font-size) !important;
}
a:not(.btn):not(.dropdown-item):not([class*='badge']):not(.text-reset):not(.page-link):not(.list-group-item) {
    color: var(--link-color);
}
a:not(.btn):not(.dropdown-item):not([class*='badge']):not(.text-reset):not(.page-link):not(.list-group-item):hover {
    color: var(--primary);
}
/* Ensure all button variants always show readable text */
a.btn-primary, a.btn-primary:hover,
.btn-primary a, .btn-primary:hover a {
    color: #ffffff !important;
}
a.btn-secondary, a.btn-secondary:hover {
    color: #ffffff !important;
}
a.btn-danger, a.btn-danger:hover {
    color: #ffffff !important;
}
a.btn-success, a.btn-success:hover {
    color: #ffffff !important;
}
a.btn-warning, a.btn-warning:hover {
    color: #212529 !important;
}
a.btn-dark, a.btn-dark:hover {
    color: #ffffff !important;
}
a.btn-light, a.btn-light:hover {
    color: #212529 !important;
}
.navbar-custom {
    background-color: var(--header-bg) !important;
    position: {$sticky} !important;
}
footer {
    background-color: var(--footer-bg) !important;
}
/* Enhanced footer color targeting (Links, Text, Headings) */
footer, 
footer h1, footer h2, footer h3, footer h4, footer h5, footer h6,
footer p, footer span,
footer a:not(.btn), 
footer a:not(.btn) *, 
footer .text-dark, 
footer .text-muted,
footer .text-dark i,
footer .text-muted i {
    color: var(--footer-link-color) !important;
}
footer a:not(.btn):hover, 
footer a:not(.btn):hover * {
    opacity: 0.8 !important;
}
.btn-primary, .btn-custom.btn-primary {
    background-color: var(--btn-color) !important;
    border-color: var(--btn-color) !important;
}
.btn-primary:hover, .btn-custom.btn-primary:hover {
    background-color: var(--btn-hover) !important;
    border-color: var(--btn-hover) !important;
}
.btn-outline-primary {
    color: var(--primary) !important;
    border-color: var(--primary) !important;
}
.btn-outline-primary:hover {
    background-color: var(--primary) !important;
    color: #fff !important;
}
.primary-blue { color: var(--primary) !important; }
.bg-primary-blue { background-color: var(--primary) !important; color: #ffffff !important; }
.list-group-item.active {
    background-color: var(--primary) !important;
    border-color: var(--primary) !important;
    color: #ffffff !important;
}
.card {
    background-color: var(--card-bg) !important;
    border-radius: var(--border-radius) !important;
}
/* Restore Bootstrap color-utility cards that intentionally use bg-primary/success/etc. */
.card.bg-primary  { background-color: var(--primary) !important; color: #ffffff !important; }
.card.bg-secondary{ background-color: #6c757d !important;         color: #ffffff !important; }
.card.bg-success  { background-color: #198754 !important;         color: #ffffff !important; }
.card.bg-danger   { background-color: #dc3545 !important;         color: #ffffff !important; }
.card.bg-warning  { background-color: #ffc107 !important;         color: #212529 !important; }
.card.bg-info     { background-color: #0dcaf0 !important;         color: #212529 !important; }
.card.bg-dark     { background-color: #212529 !important;         color: #ffffff !important; }
/* Ensure .text-white and dark-card text always win, even when body color is overridden */
.text-white, .text-white *, .bg-primary.text-white *, .bg-dark.text-white * { color: #ffffff !important; }
.text-white-50, .text-white-50 * { color: rgba(255, 255, 255, 0.75) !important; }
.text-primary { color: var(--primary) !important; }
.badge.bg-primary { background-color: var(--primary) !important; }
.form-control:focus {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 0.25rem color-mix(in srgb, var(--primary) 25%, transparent) !important;
}
.product-card .card-title, .product-main-title {
    font-size: var(--product-title-size) !important;
}";

        if ($isDark) {
            echo "
/* --- Dark Mode Extras --- */
.card, .navbar-custom, .dropdown-menu, .modal-content {
    background-color: var(--card-bg) !important;
    color: var(--text-color) !important;
}
.text-muted { color: #999 !important; }
.border-top, .border-bottom { border-color: #333 !important; }
.bg-light { background-color: #222 !important; }
.table { color: var(--text-color) !important; }
";
        }
        echo "</style>\n";
    }
}
