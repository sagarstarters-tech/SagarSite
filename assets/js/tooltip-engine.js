/**
 * Universal Contextual Hover Tooltip Engine (Vanilla JS)
 * High performance event-delegated tooltip with smart auto-detection & collision avoidance.
 * Zero external dependencies.
 */
(function () {
    'use strict';

    // Touchscreen / non-hover check: do not trigger hover tooltips on pure touch devices
    const isHoverCapable = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    if (!isHoverCapable) {
        return; // Preserve native mobile touch behavior
    }

    // Singleton tooltip DOM element
    let tooltipEl = null;
    let activeTarget = null;
    let showTimeout = null;
    const SHOW_DELAY = 100; // ms delay before showing for pleasant feel

    // Smart contextual rules mapping
    const ICON_CONTEXT_MAP = [
        { regex: /fa-(?:trash|trash-can|trash-alt)/i, text: 'Delete / Remove item', icon: 'fa-trash-alt' },
        { regex: /fa-(?:heart|heart-circle-plus)/i, text: 'Add to Wishlist / Favorites', icon: 'fa-heart' },
        { regex: /fa-(?:cart-plus|shopping-cart|shopping-bag|basket-shopping)/i, text: 'Add product to your cart', icon: 'fa-shopping-cart' },
        { regex: /fa-(?:pencil|edit|pen-to-square)/i, text: 'Edit / Modify details', icon: 'fa-pencil-alt' },
        { regex: /fa-eye/i, text: 'Quick preview & details', icon: 'fa-eye' },
        { regex: /fa-search/i, text: 'Search products & categories', icon: 'fa-search' },
        { regex: /fa-(?:share|share-alt|share-nodes)/i, text: 'Share with friends', icon: 'fa-share-alt' },
        { regex: /fa-whatsapp/i, text: 'Chat with our support team on WhatsApp', icon: 'fa-whatsapp' },
        { regex: /fa-(?:download|file-arrow-down)/i, text: 'Download file or invoice', icon: 'fa-download' },
        { regex: /fa-(?:copy|clipboard)/i, text: 'Copy to clipboard', icon: 'fa-copy' },
        { regex: /fa-filter/i, text: 'Filter & refine results', icon: 'fa-filter' },
        { regex: /fa-(?:arrow-left|chevron-left)/i, text: 'Go back to previous page', icon: 'fa-arrow-left' },
        { regex: /fa-(?:arrow-right|chevron-right)/i, text: 'Proceed / Next page', icon: 'fa-arrow-right' },
        { regex: /fa-(?:user|user-circle|user-gear)/i, text: 'My Account / Profile settings', icon: 'fa-user' },
        { regex: /fa-(?:sign-out-alt|arrow-right-from-bracket)/i, text: 'Log out securely', icon: 'fa-sign-out-alt' },
        { regex: /fa-(?:sign-in-alt|arrow-right-to-bracket)/i, text: 'Log in to your account', icon: 'fa-sign-in-alt' },
        { regex: /fa-bell/i, text: 'View notifications & alerts', icon: 'fa-bell' },
        { regex: /fa-(?:sliders|gear|cog|cogs)/i, text: 'Settings & configurations', icon: 'fa-cog' },
        { regex: /fa-(?:moon|sun)/i, text: 'Toggle Dark / Light theme', icon: 'fa-adjust' },
        { regex: /fa-(?:sync|rotate|arrows-rotate)/i, text: 'Refresh data', icon: 'fa-sync' },
        { regex: /fa-print/i, text: 'Print invoice / document', icon: 'fa-print' },
        { regex: /fa-plus/i, text: 'Add new item / Increase quantity', icon: 'fa-plus' },
        { regex: /fa-minus/i, text: 'Decrease quantity', icon: 'fa-minus' },
        { regex: /fa-(?:lock|shield-halved|shield)/i, text: '100% Safe & Secure Encryption', icon: 'fa-shield-alt' }
    ];

    const TEXT_CONTEXT_MAP = [
        { match: /^(?:add to cart|add to bag)$/i, text: 'Add this product to your shopping bag' },
        { match: /^(?:buy now|instant buy)$/i, text: 'Proceed immediately to instant checkout' },
        { match: /^(?:apply|apply coupon|apply code)$/i, text: 'Apply promotional coupon discount' },
        { match: /^(?:place order|confirm order)$/i, text: 'Confirm your order & proceed to secure payment' },
        { match: /^(?:proceed to checkout|checkout)$/i, text: 'Go to checkout to enter shipping & payment details' },
        { match: /^(?:view cart)$/i, text: 'Review and manage items in your cart' },
        { match: /^(?:track order)$/i, text: 'Check real-time delivery and shipping status' },
        { match: /^(?:save|save changes)$/i, text: 'Save updated information' },
        { match: /^(?:filter|apply filters)$/i, text: 'Apply selected filters to list' },
        { match: /^(?:submit review|add review)$/i, text: 'Share your feedback & rating for this product' }
    ];

    const HREF_CONTEXT_MAP = [
        { match: /\/cart\.php/i, text: 'View items in your shopping cart' },
        { match: /\/checkout\.php/i, text: 'Proceed to safe and secure checkout' },
        { match: /\/wishlist\.php/i, text: 'View your saved wishlist' },
        { match: /\/profile\.php/i, text: 'Manage profile, addresses, and details' },
        { match: /\/my-orders\.php|\/user\/orders\.php/i, text: 'View your order history & invoices' },
        { match: /\/login\.php/i, text: 'Sign in to access your account' },
        { match: /\/register\.php/i, text: 'Create a new customer account' },
        { match: /\/shop\.php/i, text: 'Browse our full catalog of products' },
        { match: /\/admin\//i, text: 'Open admin management dashboard' },
        { match: /(?:wa\.me|whatsapp\.com)/i, text: 'Chat with our support team on WhatsApp' },
        { match: /^mailto:/i, text: 'Send an email to our support team' },
        { match: /^tel:/i, text: 'Call our customer support helpline' }
    ];

    /**
     * Initializes the single tooltip DOM container
     */
    function initTooltipDOM() {
        if (tooltipEl) return;
        tooltipEl = document.createElement('div');
        tooltipEl.className = 'ss-tooltip-box';
        tooltipEl.setAttribute('role', 'tooltip');
        tooltipEl.setAttribute('aria-hidden', 'true');
        document.body.appendChild(tooltipEl);
    }

    /**
     * Finds the nearest interactive target element eligible for a tooltip
     */
    function getInteractiveTarget(target) {
        if (!target || target === document.body || target === document.documentElement) return null;

        // Skip ignored elements or elements explicitly asking for no tooltip
        if (target.closest('[data-no-tooltip], .no-tooltip, .no-custom-tooltip')) return null;

        // Explicit tooltip marker
        const explicit = target.closest('[data-tooltip], [data-hint], [data-ss-title]');
        if (explicit) return explicit;

        // Form control inputs with native title
        if (target.matches('input[title], button[title], select[title], textarea[title]')) return target;

        // Standard interactive elements: buttons, links, action icons, dropdown toggles
        const interactive = target.closest(
            'button, a[href], .btn, [role="button"], input[type="submit"], input[type="button"], .theme-toggle, .language-btn, .action-icon, .admin-sidebar .nav-link'
        );

        return interactive;
    }

    /**
     * Intelligently resolves the tooltip message and optional icon for the given element
     */
    function resolveTooltipContent(el) {
        // Priority 1: Explicit data-tooltip / data-hint attribute
        const explicitTooltip = el.getAttribute('data-tooltip') || el.getAttribute('data-hint');
        if (explicitTooltip && explicitTooltip.trim()) {
            return { text: explicitTooltip.trim(), icon: null };
        }

        // Priority 2: Title or cached title attribute (clears browser's native yellow tooltip)
        let title = el.getAttribute('title');
        if (title && title.trim()) {
            el.setAttribute('data-ss-title', title.trim());
            el.removeAttribute('title'); // Prevent native browser tooltip overlap
            return { text: title.trim(), icon: null };
        }
        const cachedTitle = el.getAttribute('data-ss-title');
        if (cachedTitle && cachedTitle.trim()) {
            return { text: cachedTitle.trim(), icon: null };
        }

        // Priority 3: aria-label if informative
        const ariaLabel = el.getAttribute('aria-label');
        if (ariaLabel && ariaLabel.trim() && ariaLabel.length > 2) {
            return { text: ariaLabel.trim(), icon: null };
        }

        // Priority 4: Icon-based intelligent detection
        const iconEl = el.querySelector('i, svg') || (el.tagName.toLowerCase() === 'i' ? el : null);
        if (iconEl) {
            const iconClass = iconEl.className || '';
            for (let i = 0; i < ICON_CONTEXT_MAP.length; i++) {
                if (ICON_CONTEXT_MAP[i].regex.test(iconClass)) {
                    return { text: ICON_CONTEXT_MAP[i].text, icon: ICON_CONTEXT_MAP[i].icon };
                }
            }
        }

        // Priority 5: Button visible text matching
        const directText = (el.innerText || el.textContent || '').trim().replace(/\s+/g, ' ');
        if (directText && directText.length < 35) {
            for (let i = 0; i < TEXT_CONTEXT_MAP.length; i++) {
                if (TEXT_CONTEXT_MAP[i].match.test(directText)) {
                    return { text: TEXT_CONTEXT_MAP[i].text, icon: null };
                }
            }
        }

        // Priority 6: Href-based matching
        const href = el.getAttribute('href');
        if (href && href !== '#' && !href.startsWith('javascript:')) {
            for (let i = 0; i < HREF_CONTEXT_MAP.length; i++) {
                if (HREF_CONTEXT_MAP[i].match.test(href)) {
                    return { text: HREF_CONTEXT_MAP[i].text, icon: null };
                }
            }
        }

        // Priority 7: General Action Button fallback
        // If it's a prominent button or icon without other context, give a clean helpful hint
        if (el.matches('.btn, button, input[type="submit"]') && directText && directText.length > 0 && directText.length <= 25) {
            return { text: 'Click to ' + directText.toLowerCase(), icon: null };
        }

        return null;
    }

    /**
     * Positions the tooltip accurately with boundary collision avoidance
     */
    function positionTooltip(el, positionPreference) {
        if (!tooltipEl) return;

        const targetRect = el.getBoundingClientRect();
        const tooltipRect = tooltipEl.getBoundingClientRect();
        const padding = 10;
        const arrowOffset = 8;

        let placement = positionPreference || el.getAttribute('data-tooltip-pos') || 'top';

        // Auto collision check: if top overflows window, flip to bottom
        if (placement === 'top' && targetRect.top - tooltipRect.height - arrowOffset < padding) {
            placement = 'bottom';
        } else if (placement === 'bottom' && targetRect.bottom + tooltipRect.height + arrowOffset > window.innerHeight - padding) {
            placement = 'top';
        }

        let top = 0;
        let left = 0;

        if (placement === 'top') {
            top = targetRect.top - tooltipRect.height - arrowOffset;
            left = targetRect.left + (targetRect.width / 2) - (tooltipRect.width / 2);
        } else if (placement === 'bottom') {
            top = targetRect.bottom + arrowOffset;
            left = targetRect.left + (targetRect.width / 2) - (tooltipRect.width / 2);
        } else if (placement === 'left') {
            top = targetRect.top + (targetRect.height / 2) - (tooltipRect.height / 2);
            left = targetRect.left - tooltipRect.width - arrowOffset;
            if (left < padding) placement = 'bottom'; // flip if offscreen
        } else if (placement === 'right') {
            top = targetRect.top + (targetRect.height / 2) - (tooltipRect.height / 2);
            left = targetRect.right + arrowOffset;
            if (left + tooltipRect.width > window.innerWidth - padding) placement = 'bottom';
        }

        // Recalculate if flipped to bottom
        if (placement === 'bottom' && top < targetRect.bottom) {
            top = targetRect.bottom + arrowOffset;
            left = targetRect.left + (targetRect.width / 2) - (tooltipRect.width / 2);
        }

        // Clamp horizontal position so tooltip stays cleanly within viewport
        const maxLeft = window.innerWidth - tooltipRect.width - padding;
        left = Math.max(padding, Math.min(left, maxLeft));

        // Apply coordinates
        tooltipEl.style.top = `${Math.round(top)}px`;
        tooltipEl.style.left = `${Math.round(left)}px`;

        // Reset and apply direction classes
        tooltipEl.classList.remove('pos-top', 'pos-bottom', 'pos-left', 'pos-right');
        tooltipEl.classList.add(`pos-${placement}`);
    }

    /**
     * Show tooltip for element
     */
    function show(el) {
        initTooltipDOM();
        const content = resolveTooltipContent(el);
        if (!content || !content.text) {
            hide();
            return;
        }

        activeTarget = el;

        // Construct HTML content with optional icon
        let innerHTML = '';
        if (content.icon) {
            innerHTML += `<i class="fas ${content.icon} ss-tooltip-icon"></i>`;
        }
        innerHTML += `<span>${escapeHTML(content.text)}</span>`;
        tooltipEl.innerHTML = innerHTML;

        // Position & Display
        positionTooltip(el);
        tooltipEl.classList.add('ss-tooltip-show');
        tooltipEl.setAttribute('aria-hidden', 'false');
    }

    /**
     * Hide active tooltip
     */
    function hide() {
        clearTimeout(showTimeout);
        if (activeTarget && activeTarget.hasAttribute('data-ss-title')) {
            // Restore native title if element loses focus or hover
            const originalTitle = activeTarget.getAttribute('data-ss-title');
            if (originalTitle) {
                activeTarget.setAttribute('title', originalTitle);
            }
        }
        activeTarget = null;
        if (tooltipEl) {
            tooltipEl.classList.remove('ss-tooltip-show');
            tooltipEl.setAttribute('aria-hidden', 'true');
        }
    }

    /**
     * Helper to escape HTML characters in text
     */
    function escapeHTML(str) {
        return str.replace(/[&<>'"]/g, function (tag) {
            const chars = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#39;',
                '"': '&quot;'
            };
            return chars[tag] || tag;
        });
    }

    // Event Delegation: Mouseover / Focusin
    document.addEventListener('mouseover', function (e) {
        const target = getInteractiveTarget(e.target);
        if (!target) {
            hide();
            return;
        }
        if (target === activeTarget) return;

        clearTimeout(showTimeout);
        showTimeout = setTimeout(function () {
            show(target);
        }, SHOW_DELAY);
    }, { passive: true });

    // Event Delegation: Mouseout / Focusout
    document.addEventListener('mouseout', function (e) {
        if (!activeTarget) return;
        const toElement = e.relatedTarget;
        if (toElement && activeTarget.contains(toElement)) return;
        hide();
    }, { passive: true });

    // Keyboard Accessibility: Focus in / out
    document.addEventListener('focusin', function (e) {
        const target = getInteractiveTarget(e.target);
        if (target) {
            clearTimeout(showTimeout);
            show(target);
        }
    }, { passive: true });

    document.addEventListener('focusout', function () {
        hide();
    }, { passive: true });

    // Hide tooltip when scrolling or clicking
    window.addEventListener('scroll', hide, { passive: true });
    document.addEventListener('click', hide, { passive: true });

    // Export minimal API for external calls if needed
    window.SagarTooltip = {
        show: show,
        hide: hide,
        refresh: function () {
            if (activeTarget) positionTooltip(activeTarget);
        }
    };

})();
