/* =========================================================
   GLOBAL ANIMATION SCRIPTS - OPTIMIZED
   Performance Upgrade for eCommerce Platform
   ========================================================= */

document.addEventListener('DOMContentLoaded', () => {
    window.addEventListener('load', () => {
        document.body.classList.add('loaded');
    });

    /* ── Button Ripple Effect via Event Delegation (Zero initial scan) ──── */
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-custom, .btn-primary, .btn-outline-primary');
        if (!btn) return;
        const ripple = document.createElement('span');
        ripple.classList.add('ripple');
        btn.appendChild(ripple);
        const rect = btn.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        ripple.style.left = `${x}px`;
        ripple.style.top  = `${y}px`;
        setTimeout(() => { ripple.remove(); }, 600);
    });
});

/**
 * Add to Cart Fly Animation
 * Lightweight CSS-transition-based animation (no GSAP dependency).
 */
window.flyToCartAnimation = function (startElement, endElementSelector = '.fa-shopping-cart') {
    if (!startElement) return;
    const cartIcon = document.querySelector(endElementSelector);
    if (!cartIcon) return;

    const clone = startElement.cloneNode(true);
    const startRect = startElement.getBoundingClientRect();
    const endRect   = cartIcon.getBoundingClientRect();

    clone.classList.remove('lazy-anim', 'loaded');
    clone.classList.add('flying-img');
    clone.style.top    = `${startRect.top}px`;
    clone.style.left   = `${startRect.left}px`;
    clone.style.width  = `${startRect.width}px`;
    clone.style.height = `${startRect.height}px`;
    clone.style.transition = 'all 0.6s cubic-bezier(0.42, 0, 0.58, 1)';
    clone.style.opacity = '1';
    document.body.appendChild(clone);

    requestAnimationFrame(() => {
        clone.style.top    = `${endRect.top}px`;
        clone.style.left   = `${endRect.left}px`;
        clone.style.width  = '30px';
        clone.style.height = '30px';
        clone.style.opacity   = '0.2';
        clone.style.transform = 'scale(0.5)';
    });

    setTimeout(() => {
        clone.remove();
        cartIcon.classList.add('cart-shake');
        setTimeout(() => { cartIcon.classList.remove('cart-shake'); }, 500);
    }, 600);
};
