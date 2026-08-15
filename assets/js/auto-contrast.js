/**
 * ============================================================
 *  Universal Automatic Text Contrast System (Robust v2.0)
 *  File: /assets/js/auto-contrast.js
 * ============================================================
 *  Automatically adjusts text color (Light/Dark) based on
 *  true background brightness of sections, cards, banners,
 *  gradients, images, and buttons.
 *
 *  Luminance formula (ITU-R BT.601 / WCAG relative luminance):
 *    L = 0.299·R + 0.587·G + 0.114·B
 *    L < 165  →  Dark Background  → Light Text (.light-text)
 *    L ≥ 165  →  Light Background → Dark Text  (.dark-text)
 * ============================================================
 */

(function () {
    'use strict';

    const CONFIG = {
        threshold: 165,
        // Containers with visual surfaces to check
        targetSelectors: [
            'section',
            'header',
            'footer',
            '.navbar',
            '.bottom-nav',
            '.hero-slider',
            '.hero-slide',
            '.carousel-item',
            '.card',
            '.promo-spotlight-card',
            '.home-stats-section',
            '.home-cta-banner',
            '.product-card',
            '.product-card-pro',
            '.category-card-modern',
            '.category-card-pro',
            '.starter-selector-box',
            '.feature-block',
            '.banner',
            '.hero-section',
            '.bg-image',
            '.banner-section',
            '.modal-content',
            '[data-auto-contrast]'
        ].join(', '),

        // Interactive elements (buttons, inputs)
        btnSelector: [
            '.btn',
            'button:not(.navbar-toggler):not(.btn-close)',
            'input[type="submit"]',
            'input[type="button"]',
            '.promo-cta-btn',
            '.btn-cta-wa',
            '.btn-cta-call',
            '.selector-pill-btn',
            '.btn-pro-view'
        ].join(', '),

        // Classes to completely skip
        skipClasses: ['no-contrast', 'navbar-toggler', 'btn-close', 'carousel-control-prev', 'carousel-control-next', 'carousel-indicators', 'admin-main-wrapper', 'admin-sidebar', 'admin-main-col']
    };

    // Cache for image luminance sampling
    const imgBrightnessCache = new Map();

    /**
     * Check if auto-contrast is enabled in settings
     */
    function isAutoContrastEnabled() {
        if (typeof window !== 'undefined' && window.location && window.location.pathname.includes('/admin/')) {
            return false;
        }
        if (window.siteConfig && window.siteConfig.autoContrast === false) {
            return false;
        }
        return true;
    }

    /**
     * Parse any color format string (rgb, rgba, hex, named) into { r, g, b, a }
     */
    function parseColor(colorStr) {
        if (!colorStr || typeof colorStr !== 'string') return null;
        colorStr = colorStr.trim().toLowerCase();
        if (colorStr === 'transparent' || colorStr === 'rgba(0, 0, 0, 0)') return null;

        // Named quick checks
        if (colorStr === 'white') return { r: 255, g: 255, b: 255, a: 1 };
        if (colorStr === 'black') return { r: 0, g: 0, b: 0, a: 1 };

        // 1. rgba(...) or rgb(...)
        const rgbMatch = colorStr.match(/rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)(?:\s*,\s*([\d.]+))?\s*\)/);
        if (rgbMatch) {
            const a = rgbMatch[4] !== undefined ? parseFloat(rgbMatch[4]) : 1;
            if (a <= 0.05) return null; // practically transparent
            return {
                r: Math.round(parseFloat(rgbMatch[1])),
                g: Math.round(parseFloat(rgbMatch[2])),
                b: Math.round(parseFloat(rgbMatch[3])),
                a: a
            };
        }

        // 2. Hex formats: #rgb, #rgba, #rrggbb, #rrggbbaa
        if (colorStr.startsWith('#')) {
            const hex = colorStr.slice(1);
            if (hex.length === 3 || hex.length === 4) {
                const r = parseInt(hex[0] + hex[0], 16);
                const g = parseInt(hex[1] + hex[1], 16);
                const b = parseInt(hex[2] + hex[2], 16);
                const a = hex.length === 4 ? parseInt(hex[3] + hex[3], 16) / 255 : 1;
                return a > 0.05 ? { r, g, b, a } : null;
            }
            if (hex.length === 6 || hex.length === 8) {
                const r = parseInt(hex.substring(0, 2), 16);
                const g = parseInt(hex.substring(2, 4), 16);
                const b = parseInt(hex.substring(4, 6), 16);
                const a = hex.length === 8 ? parseInt(hex.substring(6, 8), 16) / 255 : 1;
                return a > 0.05 ? { r, g, b, a } : null;
            }
        }

        return null;
    }

    /**
     * Calculate luminance (0 to 255) using standard BT.601 formula
     */
    function calculateLuminance(rgb) {
        if (!rgb) return 255;
        return (0.299 * rgb.r) + (0.587 * rgb.g) + (0.114 * rgb.b);
    }

    /**
     * Extract and calculate average luminance of gradient stops in a CSS gradient string
     */
    function parseGradientLuminance(gradientStr) {
        if (!gradientStr || typeof gradientStr !== 'string') return null;
        if (!gradientStr.includes('gradient')) return null;

        // Extract all rgb/rgba and hex color tokens from the gradient
        const colorMatches = gradientStr.match(/rgba?\(\s*[\d.]+\s*,\s*[\d.]+\s*,\s*[\d.]+(?:\s*,\s*[\d.]+)?\s*\)|#[0-9a-fA-F]{3,8}/gi);
        if (!colorMatches || colorMatches.length === 0) return null;

        let totalLuminance = 0;
        let validStops = 0;

        for (const token of colorMatches) {
            const parsed = parseColor(token);
            if (parsed) {
                totalLuminance += calculateLuminance(parsed);
                validStops++;
            }
        }

        if (validStops === 0) return null;
        return totalLuminance / validStops;
    }

    /**
     * Sample background image brightness using off-screen canvas
     */
    async function sampleImageBrightness(url) {
        if (imgBrightnessCache.has(url)) return imgBrightnessCache.get(url);

        return new Promise((resolve) => {
            const img = new Image();
            img.crossOrigin = "Anonymous";
            img.src = url;

            img.onload = function () {
                try {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    canvas.width = 16;
                    canvas.height = 16;
                    ctx.drawImage(img, 0, 0, 16, 16);

                    const data = ctx.getImageData(0, 0, 16, 16).data;
                    let totalLum = 0;
                    let count = 0;

                    for (let i = 0; i < data.length; i += 4) {
                        const a = data[i + 3] / 255;
                        if (a > 0.1) {
                            totalLum += calculateLuminance({ r: data[i], g: data[i + 1], b: data[i + 2] });
                            count++;
                        }
                    }

                    const avgBrightness = count > 0 ? (totalLum / count) : 255;
                    imgBrightnessCache.set(url, avgBrightness);
                    resolve(avgBrightness);
                } catch (e) {
                    // Cross-origin tainted canvas fallback
                    resolve(null);
                }
            };

            img.onerror = () => resolve(null);
        });
    }

    /**
     * Calculate the true effective background brightness of an element
     * Returns a number between 0 and 255, or null if transparent with no background of its own.
     */
    async function getElementBrightness(el) {
        const computed = window.getComputedStyle(el);
        const bgImg = computed.backgroundImage;

        // 1. Check for CSS Gradient Background (linear-gradient, radial-gradient)
        if (bgImg && bgImg !== 'none' && bgImg.includes('gradient')) {
            const gradLum = parseGradientLuminance(bgImg);

            // If it also contains an image url (e.g. gradient overlay on top of photo)
            if (bgImg.includes('url(')) {
                const urlMatch = bgImg.match(/url\(["']?([^"')]+)["']?\)/);
                if (urlMatch) {
                    const imgLum = await sampleImageBrightness(urlMatch[1]);
                    if (imgLum !== null && gradLum !== null) {
                        // Weighted blend (gradient overlay heavily influences perceived brightness)
                        return (gradLum * 0.65) + (imgLum * 0.35);
                    }
                    if (gradLum !== null) return gradLum;
                    if (imgLum !== null) return imgLum;
                }
            }

            if (gradLum !== null) return gradLum;
        }

        // 2. Check for pure Background Image
        if (bgImg && bgImg !== 'none' && bgImg.includes('url(')) {
            const urlMatch = bgImg.match(/url\(["']?([^"')]+)["']?\)/);
            if (urlMatch) {
                const imgLum = await sampleImageBrightness(urlMatch[1]);
                if (imgLum !== null) return imgLum;
            }
        }

        // 3. Check for explicit Background Color on element
        const rgb = parseColor(computed.backgroundColor);
        if (rgb && rgb.a >= 0.3) {
            return calculateLuminance(rgb);
        }

        // 4. If element is a landmark (body, navbar, header, footer, or explicitly marked)
        if (el.tagName === 'BODY' || el.classList.contains('navbar') || el.hasAttribute('data-auto-contrast')) {
            let parent = el.parentElement;
            while (parent && parent !== document.documentElement) {
                const pStyle = window.getComputedStyle(parent);
                const pRgb = parseColor(pStyle.backgroundColor);
                if (pRgb && pRgb.a >= 0.3) {
                    return calculateLuminance(pRgb);
                }
                parent = parent.parentElement;
            }
            return 255; // default fallback for top-level landmarks
        }

        // Return null for transparent wrappers without their own background
        return null;
    }

    /**
     * Apply contrast class to a container element
     */
    async function applyToElement(el) {
        if (!el || CONFIG.skipClasses.some(cls => el.classList.contains(cls))) return;

        const brightness = await getElementBrightness(el);
        if (brightness === null) {
            // Element has transparent background: do not force contrast classes on it
            return;
        }

        const isDark = brightness < CONFIG.threshold;

        el.classList.remove('light-text', 'dark-text');
        el.classList.add(isDark ? 'light-text' : 'dark-text');

        // Bootstrap / MDB Navbar compatibility
        if (el.classList.contains('navbar')) {
            el.classList.remove('navbar-light', 'navbar-dark');
            el.classList.add(isDark ? 'navbar-dark' : 'navbar-light');
        }

        el.dataset.acDone = "true";
    }

    /**
     * Apply contrast class to a button / interactive control
     */
    function applyToButton(btn) {
        if (!btn || CONFIG.skipClasses.some(cls => btn.classList.contains(cls))) return;
        if (btn.className && (btn.className.includes('btn-outline-') || btn.className.includes('btn-light') || btn.className.includes('var-btn'))) return;
        if (btn.closest && btn.closest('.admin-main-wrapper, .admin-sidebar, .admin-main-col')) return;

        const style = window.getComputedStyle(btn);

        // 1. Check if button has a gradient background
        if (style.backgroundImage && style.backgroundImage !== 'none' && style.backgroundImage.includes('gradient')) {
            const gradLum = parseGradientLuminance(style.backgroundImage);
            if (gradLum !== null) {
                const isDark = gradLum < CONFIG.threshold;
                btn.classList.remove('light-text', 'dark-text');
                btn.classList.add(isDark ? 'light-text' : 'dark-text');
                return;
            }
        }

        // 2. Check button's own background color
        let rgb = parseColor(style.backgroundColor);

        // 3. If transparent, look at nearest ancestor background
        if (!rgb || rgb.a < 0.3) {
            let ancestor = btn.parentElement;
            while (ancestor && ancestor !== document.documentElement) {
                const aStyle = window.getComputedStyle(ancestor);
                const aRgb = parseColor(aStyle.backgroundColor);
                if (aRgb && aRgb.a >= 0.3) {
                    rgb = aRgb;
                    break;
                }
                ancestor = ancestor.parentElement;
            }
        }

        if (!rgb) rgb = { r: 255, g: 255, b: 255, a: 1 };

        const brightness = calculateLuminance(rgb);
        const isDark = brightness < CONFIG.threshold;
        btn.classList.remove('light-text', 'dark-text');
        btn.classList.add(isDark ? 'light-text' : 'dark-text');
    }

    /**
     * Clean up all auto-contrast classes from DOM (when disabled)
     */
    function disableSystem() {
        document.querySelectorAll('.light-text, .dark-text').forEach(el => {
            el.classList.remove('light-text', 'dark-text');
            delete el.dataset.acDone;
        });
    }

    /**
     * Main scan cycle
     */
    function scan() {
        if (!isAutoContrastEnabled()) {
            disableSystem();
            return;
        }

        document.querySelectorAll(CONFIG.targetSelectors).forEach(applyToElement);
        document.querySelectorAll(CONFIG.btnSelector).forEach(applyToButton);
    }

    /**
     * Debounce helper
     */
    function debounce(fn, delay) {
        let timer;
        return function () {
            const ctx = this, args = arguments;
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(ctx, args), delay);
        };
    }

    /**
     * Initialize listeners and observer
     */
    function init() {
        scan();

        const observer = new MutationObserver(debounce(() => {
            if (isAutoContrastEnabled()) scan();
        }, 150));

        observer.observe(document.body, { childList: true, subtree: true });

        window.addEventListener('resize', debounce(scan, 200));
        window.addEventListener('themeColorChanged', () => {
            document.querySelectorAll('[data-ac-done]').forEach(el => delete el.dataset.acDone);
            scan();
        });
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Secondary scan on complete window load
    window.addEventListener('load', () => {
        setTimeout(scan, 100);
        setTimeout(scan, 500);
    });

    // Public API
    window.UniversalContrast = {
        scan: scan,
        disable: disableSystem,
        reprocess: function () {
            document.querySelectorAll('[data-ac-done]').forEach(el => delete el.dataset.acDone);
            scan();
        }
    };

})();
