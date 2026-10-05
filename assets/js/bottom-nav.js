/**
 * Sagar Starters - Mobile Bottom Navigation Bar Logic
 * Flipkart / Amazon Style Active Detection, Live Cart Badge Sync & Tactile Feedback
 */
document.addEventListener('DOMContentLoaded', () => {
    const bottomNav = document.getElementById('bottomNav');
    if (!bottomNav) return;

    const navItems = bottomNav.querySelectorAll('.bottom-nav-item');
    const currentPath = window.location.pathname.toLowerCase();

    // 1. Smart Active Tab Detection (Flipkart / Amazon standard routing)
    let matched = false;
    navItems.forEach(item => {
        try {
            const itemPath = new URL(item.href).pathname.toLowerCase();
            const tab = item.getAttribute('data-tab');

            // Exact match or directory index match
            if (currentPath === itemPath || (currentPath.endsWith('/') && itemPath.endsWith('index.php'))) {
                item.classList.add('active');
                matched = true;
            } else if (tab === 'categories' && (currentPath.includes('shop.php') || currentPath.includes('category.php') || currentPath.includes('catalogue.php') || currentPath.includes('product.php'))) {
                item.classList.add('active');
                matched = true;
            } else if (tab === 'orders' && currentPath.includes('order')) {
                item.classList.add('active');
                matched = true;
            } else if (tab === 'profile' && (currentPath.includes('profile.php') || currentPath.includes('user/'))) {
                item.classList.add('active');
                matched = true;
            } else if (tab === 'cart' && (currentPath.includes('cart.php') || currentPath.includes('checkout.php'))) {
                item.classList.add('active');
                matched = true;
            } else {
                item.classList.remove('active');
            }
        } catch (e) { /* ignore invalid URLs */ }
    });

    // Default to Home if root path or no match
    if (!matched) {
        const homeItem = bottomNav.querySelector('[data-tab="home"]');
        if (homeItem && (currentPath.endsWith('/') || currentPath.endsWith('index.php') || currentPath === '')) {
            homeItem.classList.add('active');
        }
    }

    // 2. Tactile Touch Ripple Effect
    navItems.forEach(item => {
        item.addEventListener('click', function (e) {
            const oldRipple = this.querySelector('.ripple');
            if (oldRipple) oldRipple.remove();

            const ripple = document.createElement('span');
            ripple.classList.add('ripple');
            this.appendChild(ripple);

            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            ripple.style.width = size + 'px';
            ripple.style.height = size + 'px';
            ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
            ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';

            navItems.forEach(nav => nav.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // 3. Live Synchronize Cart Badge with Header Cart
    function syncCartBadge() {
        const headerBadge = document.querySelector('#header-cart-container .badge');
        const bottomBadge = document.getElementById('bottomNavCartBadge');
        if (!bottomBadge) return;

        if (headerBadge) {
            const text = headerBadge.textContent.trim();
            const count = parseInt(text, 10);
            if (!isNaN(count) && count > 0) {
                bottomBadge.textContent = count > 99 ? '99+' : count;
                bottomBadge.style.display = 'inline-flex';
            } else {
                bottomBadge.style.display = 'none';
            }
        }
    }

    // Observe changes in header-cart-container for dynamic updates
    const headerCart = document.getElementById('header-cart-container');
    if (headerCart && window.MutationObserver) {
        const observer = new MutationObserver(syncCartBadge);
        observer.observe(headerCart, { childList: true, subtree: true, characterData: true });
    }
});
