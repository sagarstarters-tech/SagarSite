/**
 * Universal Contextual Hover Tooltip Engine (Vanilla JS)
 * High performance event-delegated tooltip with smart auto-detection & collision avoidance.
 * Covers 100% of website buttons, links, navigation menus, and action controls.
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
    const SHOW_DELAY = 90; // ms delay before showing for smooth responsiveness

    // ── Icon-to-Context Mapping ────────────────────────────────
    const ICON_CONTEXT_MAP = [
        { regex: /fa-(?:home)/i, text: 'Return to store homepage', icon: 'fa-home' },
        { regex: /fa-(?:trash|trash-can|trash-alt)/i, text: 'Delete / Remove item', icon: 'fa-trash-alt' },
        { regex: /fa-(?:heart|heart-circle-plus)/i, text: 'Add to Wishlist / Saved favorites', icon: 'fa-heart' },
        { regex: /fa-(?:cart-plus)/i, text: 'Add this product to your shopping cart', icon: 'fa-cart-plus' },
        { regex: /fa-(?:shopping-cart|shopping-bag|basket-shopping|cart-shopping|bag-shopping)/i, text: 'View items in your shopping cart', icon: 'fa-shopping-cart' },
        { regex: /fa-(?:pencil|edit|pen-to-square)/i, text: 'Edit / Modify details', icon: 'fa-pencil-alt' },
        { regex: /fa-eye/i, text: 'Quick preview & product details', icon: 'fa-eye' },
        { regex: /fa-search/i, text: 'Search products & categories', icon: 'fa-search' },
        { regex: /fa-(?:share|share-alt|share-nodes)/i, text: 'Share with friends on social media', icon: 'fa-share-alt' },
        { regex: /fa-whatsapp/i, text: 'Chat directly with our support team on WhatsApp', icon: 'fa-whatsapp' },
        { regex: /fa-(?:download|file-arrow-down|file-invoice)/i, text: 'Download official file or invoice', icon: 'fa-download' },
        { regex: /fa-print/i, text: 'Print invoice or document', icon: 'fa-print' },
        { regex: /fa-(?:copy|clipboard)/i, text: 'Copy to clipboard', icon: 'fa-copy' },
        { regex: /fa-filter/i, text: 'Filter & refine product list', icon: 'fa-filter' },
        { regex: /fa-(?:sliders|gear|cog|cogs)/i, text: 'Settings & configurations', icon: 'fa-cog' },
        { regex: /fa-(?:moon|sun|adjust)/i, text: 'Toggle Dark / Light theme', icon: 'fa-adjust' },
        { regex: /fa-(?:globe|language)/i, text: 'Change website display language', icon: 'fa-globe' },
        { regex: /fa-(?:user|user-circle|user-gear|user-shield)/i, text: 'My Account & Profile settings', icon: 'fa-user' },
        { regex: /fa-(?:sign-out-alt|arrow-right-from-bracket)/i, text: 'Log out securely', icon: 'fa-sign-out-alt' },
        { regex: /fa-(?:sign-in-alt|arrow-right-to-bracket)/i, text: 'Log in to your customer account', icon: 'fa-sign-in-alt' },
        { regex: /fa-bell/i, text: 'View notifications & alerts', icon: 'fa-bell' },
        { regex: /fa-(?:arrow-left|chevron-left)/i, text: 'Go back to previous page', icon: 'fa-arrow-left' },
        { regex: /fa-(?:arrow-right|chevron-right)/i, text: 'Proceed / Next step', icon: 'fa-arrow-right' },
        { regex: /fa-(?:sync|rotate|arrows-rotate)/i, text: 'Refresh data', icon: 'fa-sync' },
        { regex: /fa-plus/i, text: 'Add new item / Increase quantity', icon: 'fa-plus' },
        { regex: /fa-minus/i, text: 'Decrease quantity', icon: 'fa-minus' },
        { regex: /fa-(?:lock|shield-halved|shield)/i, text: '100% Safe & Secure Encryption', icon: 'fa-shield-alt' },
        { regex: /fa-(?:truck|truck-fast)/i, text: 'Track order shipping & delivery', icon: 'fa-truck' },
        { regex: /fa-(?:box|boxes-stacked|box-open)/i, text: 'View your orders & shipments', icon: 'fa-box-open' },
        { regex: /fa-(?:phone|phone-flip)/i, text: 'Call our customer helpline', icon: 'fa-phone' },
        { regex: /fa-envelope/i, text: 'Send an email to support', icon: 'fa-envelope' },
        { regex: /fa-bars/i, text: 'Toggle navigation menu', icon: 'fa-bars' },
        { regex: /fa-(?:times|xmark|close)/i, text: 'Close window / Dismiss', icon: 'fa-times' }
    ];

    // ── Direct Text-to-Context Semantic Mapping ────────────────
    const TEXT_CONTEXT_MAP = [
        // Main Navigation
        { match: /^home$/i, text: 'Return to store homepage' },
        { match: /^(?:shop|all products|catalog|products|store)$/i, text: 'Browse our full catalog of motor starters & spares' },
        { match: /^(?:about|about us|about sagar starters|who we are)$/i, text: 'Learn about our company story, quality & manufacturing' },
        { match: /^(?:contact|contact us|get in touch|reach us)$/i, text: 'Get in touch with customer support & view store address' },
        { match: /^(?:track order|track your order|order tracking)$/i, text: 'Check real-time delivery and shipping status' },
        { match: /^(?:policies|store policies|our policies)$/i, text: 'View store terms, privacy policy, and return guidelines' },
        { match: /^(?:privacy policy|privacy)$/i, text: 'Read our Privacy Policy & personal data protection terms' },
        { match: /^(?:terms & conditions|terms and conditions|terms of service|terms of use|terms)$/i, text: 'Read our customer terms of service & store conditions' },
        { match: /^(?:shipping policy|shipping & delivery|delivery info|shipping info)$/i, text: 'View delivery options, courier partners & transit times' },
        { match: /^(?:return & refund policy|refund policy|return policy|returns & refund)$/i, text: 'Read guidelines for product returns, refunds, and replacements' },
        { match: /^(?:disclaimer)$/i, text: 'Read legal disclaimers regarding products and technical usage' },
        { match: /^(?:cancellation policy)$/i, text: 'Learn rules and timelines for cancelling placed orders' },
        { match: /^(?:f&q|faq|faqs|help|help & support|support)$/i, text: 'Find answers to frequently asked questions & get help' },
        { match: /^(?:testimonials|reviews|customer reviews)$/i, text: 'Read verified customer feedback and experiences' },

        // User Account & Session
        { match: /^(?:my account|account|profile|my profile)$/i, text: 'Manage profile details, passwords, and addresses' },
        { match: /^(?:my orders|orders|order history)$/i, text: 'View your order history, delivery status & invoices' },
        { match: /^(?:my wishlist|wishlist|favorites)$/i, text: 'View products you have saved for later' },
        { match: /^(?:login|sign in|log in)$/i, text: 'Sign in to access your saved cart, orders & profile' },
        { match: /^(?:register|sign up|create account)$/i, text: 'Create a new customer account' },
        { match: /^(?:logout|sign out|log out)$/i, text: 'Securely log out of your account' },
        { match: /^(?:admin|admin panel|dashboard)$/i, text: 'Open admin management dashboard' },

        // Ecommerce Actions
        { match: /^(?:add to cart|add to bag)$/i, text: 'Add this product to your shopping cart' },
        { match: /^(?:buy now|instant buy)$/i, text: 'Proceed immediately to instant checkout' },
        { match: /^(?:apply|apply coupon|apply code)$/i, text: 'Apply promotional discount coupon to order total' },
        { match: /^(?:place order|confirm order|pay now)$/i, text: 'Confirm your order & proceed to secure payment' },
        { match: /^(?:proceed to checkout|checkout)$/i, text: 'Proceed to checkout to enter shipping & payment details' },
        { match: /^(?:view cart)$/i, text: 'Review and update items in your shopping cart' },
        { match: /^(?:view details|quick view|see details|explore)$/i, text: 'View complete specifications, diagrams & pricing' },
        { match: /^(?:read more|learn more)$/i, text: 'Read full information and details' },
        { match: /^(?:submit review|add review|write review)$/i, text: 'Share your rating & feedback for this product' },
        { match: /^(?:save|save changes|update)$/i, text: 'Save updated configuration' },
        { match: /^(?:cancel|back|go back)$/i, text: 'Return to previous view' },
        { match: /^(?:delete|remove)$/i, text: 'Delete / Remove this item permanently' },
        { match: /^(?:edit|modify)$/i, text: 'Edit / Update details' },
        { match: /^(?:filter|apply filters)$/i, text: 'Filter product list by selected options' },
        { match: /^(?:reset|clear|clear all)$/i, text: 'Reset all selections to default' }
    ];

    // ── URL / HREF Semantic Mapping ────────────────────────────
    const HREF_CONTEXT_MAP = [
        { match: /(?:\/index\.php|\/)$/i, text: 'Return to store homepage' },
        { match: /\/shop\.php/i, text: 'Browse our full catalog of motor starters & spares' },
        { match: /(?:about\.php|slug=about)/i, text: 'Learn about our company story, quality & manufacturing' },
        { match: /(?:contact\.php|slug=contact)/i, text: 'Get in touch with customer support & view store address' },
        { match: /(?:track_order\.php|\/track)/i, text: 'Check real-time delivery and shipping status' },
        { match: /slug=privacy/i, text: 'Read our Privacy Policy & personal data protection terms' },
        { match: /slug=shipping/i, text: 'View delivery options, courier partners & shipping terms' },
        { match: /slug=(?:return|refund)/i, text: 'Read guidelines for product returns, refunds, and replacements' },
        { match: /slug=terms/i, text: 'Read our customer terms of service & store conditions' },
        { match: /slug=disclaimer/i, text: 'Read legal disclaimers regarding products and technical usage' },
        { match: /slug=(?:cancellation)/i, text: 'Learn rules and timelines for cancelling placed orders' },
        { match: /slug=(?:f-q|faq|help)/i, text: 'Find answers to frequently asked questions & get help' },
        { match: /\/cart\.php/i, text: 'View and manage items in your shopping cart' },
        { match: /\/checkout\.php/i, text: 'Proceed to safe and secure checkout' },
        { match: /\/wishlist\.php/i, text: 'View products you have saved for later' },
        { match: /\/profile\.php/i, text: 'Manage profile details, passwords, and addresses' },
        { match: /(?:\/my-orders\.php|\/user\/orders\.php|\/orders\.php)/i, text: 'View your order history, delivery status & invoices' },
        { match: /\/login\.php/i, text: 'Sign in to access your saved cart, orders & profile' },
        { match: /\/register\.php/i, text: 'Create a new customer account' },
        { match: /action=logout/i, text: 'Securely log out of your account' },
        { match: /\/admin\//i, text: 'Open admin management dashboard' },
        { match: /(?:wa\.me|whatsapp\.com)/i, text: 'Chat directly with our support team on WhatsApp' },
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

        // Form control inputs with explicit purpose
        if (target.matches('input[name="search"], input[name="q"], input[type="search"], input[title], button[title], select[title], textarea[title]')) {
            return target;
        }

        // Standard interactive elements: buttons, links, icons, dropdown items/toggles, nav links
        const interactive = target.closest(
            'button, a, .btn, [role="button"], input[type="submit"], input[type="button"], input[type="reset"], .theme-toggle, .language-btn, .action-icon, .nav-link, .dropdown-item, .dropdown-toggle, .navbar-brand, .bottom-nav-item, .admin-sidebar .nav-link, #whatsapp-link'
        );

        return interactive;
    }

    /**
     * Helper to clean up visible text from element
     */
    function getCleanText(el) {
        let text = (el.innerText || el.textContent || '').trim();
        // Remove badge text numbers or excessive whitespace
        text = text.replace(/\s+/g, ' ');
        return text;
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

        // Priority 3: Search input special case
        if (el.matches('input[name="search"], input[name="q"], input[type="search"]')) {
            return { text: 'Type product name, HP rating, or model to search', icon: 'fa-search' };
        }

        // Priority 4: Brand logo special case
        if (el.matches('.navbar-brand') || el.querySelector('img[alt*="logo" i]')) {
            return { text: 'Return to store homepage', icon: 'fa-home' };
        }

        // Priority 5: Language selector option
        if (el.hasAttribute('data-lang') || el.classList.contains('lang-option')) {
            const langName = getCleanText(el) || el.getAttribute('data-lang');
            return { text: `Switch website language to ${langName}`, icon: 'fa-globe' };
        }

        // Priority 6: aria-label if informative and not generic
        const ariaLabel = el.getAttribute('aria-label');
        if (ariaLabel && ariaLabel.trim() && ariaLabel.length > 3 && !/^(?:button|toggle|menu)$/i.test(ariaLabel.trim())) {
            return { text: ariaLabel.trim(), icon: null };
        }

        // Priority 7: Icon-based intelligent detection
        const iconEl = el.querySelector('i, svg') || (el.tagName && el.tagName.toLowerCase() === 'i' ? el : null);
        if (iconEl) {
            const iconClass = (iconEl.className || '') + ' ' + (iconEl.getAttribute('data-icon') || '');
            for (let i = 0; i < ICON_CONTEXT_MAP.length; i++) {
                if (ICON_CONTEXT_MAP[i].regex.test(iconClass)) {
                    return { text: ICON_CONTEXT_MAP[i].text, icon: ICON_CONTEXT_MAP[i].icon };
                }
            }
        }

        // Priority 8: Text-based semantic matching
        const directText = getCleanText(el);
        if (directText && directText.length <= 40) {
            for (let i = 0; i < TEXT_CONTEXT_MAP.length; i++) {
                if (TEXT_CONTEXT_MAP[i].match.test(directText)) {
                    return { text: TEXT_CONTEXT_MAP[i].text, icon: null };
                }
            }
        }

        // Priority 9: Href-based matching
        const href = el.getAttribute('href') || '';
        if (href && href !== '#' && !href.startsWith('javascript:')) {
            for (let i = 0; i < HREF_CONTEXT_MAP.length; i++) {
                if (HREF_CONTEXT_MAP[i].match.test(href)) {
                    return { text: HREF_CONTEXT_MAP[i].text, icon: null };
                }
            }
        }

        // Priority 10: Dropdown toggle buttons or links (e.g. Policies, Menus)
        if (el.matches('.dropdown-toggle, [data-bs-toggle="dropdown"], [data-mdb-toggle="dropdown"]')) {
            const label = directText ? `"${directText}"` : 'options';
            return { text: `Click to view ${label} menu`, icon: 'fa-bars' };
        }

        // Priority 11: Product / Category card contextual links
        const productCard = el.closest('.product-card, .card, .product-item');
        if (productCard) {
            const titleEl = productCard.querySelector('.card-title, .product-title, h4, h5, h6');
            if (titleEl) {
                const cardTitle = getCleanText(titleEl);
                if (cardTitle && cardTitle.length > 2) {
                    if (el.matches('a, button, img')) {
                        return { text: `View specifications & price for ${cardTitle}`, icon: 'fa-info-circle' };
                    }
                }
            }
        }

        // Priority 12: General Navigation / Button / Link Universal Fallback
        if (directText && directText.length > 0 && directText.length <= 45) {
            if (el.matches('.nav-link, .navbar-nav a')) {
                return { text: `Open ${directText} page`, icon: 'fa-compass' };
            }
            if (el.matches('.dropdown-item')) {
                return { text: `Go to ${directText}`, icon: 'fa-chevron-right' };
            }
            if (el.matches('.btn, button, input[type="submit"]')) {
                return { text: `Click to ${directText.toLowerCase()}`, icon: null };
            }
            if (el.matches('a')) {
                return { text: `Open ${directText}`, icon: 'fa-arrow-right' };
            }
        }

        // Priority 13: Image link with alt text
        const img = el.querySelector('img');
        if (img && img.getAttribute('alt')) {
            const altText = img.getAttribute('alt').trim();
            if (altText) {
                return { text: `View ${altText}`, icon: 'fa-image' };
            }
        }

        // Priority 14: Final safety fallback for any interactive button or link
        if (el.matches('button, .btn')) {
            return { text: 'Click to perform action', icon: null };
        }
        if (el.matches('a[href]')) {
            return { text: 'Click to navigate', icon: null };
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
        const padding = 12;
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
