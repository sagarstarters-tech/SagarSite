/**
 * Sagar Starter's - PWA Installation & Lifecycle Controller
 * 
 * Features:
 * - Listens for beforeinstallprompt
 * - Handles App Install triggers seamlessly
 * - Supports both header button and mobile menu items
 * - Auto-hides when app is already installed or running standalone
 * - Listens for appinstalled event
 * - Zero intrusive popups
 */

(function () {
    'use strict';

    let deferredPrompt = null;

    // Check if app is already running in standalone PWA mode
    function isRunningStandalone() {
        return (window.matchMedia('(display-mode: standalone)').matches) ||
               (window.navigator.standalone === true) ||
               (document.referrer.includes('android-app://'));
    }

    // Toggle visibility of all install buttons on the page
    function setInstallButtonsVisibility(visible) {
        const buttons = document.querySelectorAll('.pwa-install-btn, #pwaInstallBtn, #mobilePwaInstallBtn');
        buttons.forEach(btn => {
            if (visible && !isRunningStandalone()) {
                btn.classList.remove('d-none');
                btn.style.display = '';
            } else {
                btn.classList.add('d-none');
                btn.style.display = 'none';
            }
        });
    }

    // Initialize event listeners once DOM is ready
    document.addEventListener('DOMContentLoaded', function () {
        // If already in standalone mode, ensure install triggers are hidden
        if (isRunningStandalone()) {
            setInstallButtonsVisibility(false);
            return;
        }

        // Attach click listener via delegation to any install button
        document.addEventListener('click', function (e) {
            const target = e.target.closest('.pwa-install-btn, #pwaInstallBtn, #mobilePwaInstallBtn');
            if (!target) return;

            e.preventDefault();

            if (!deferredPrompt) {
                // If beforeinstallprompt hasn't fired yet or is unsupported (e.g. iOS), show a gentle instruction toast
                if (/iPhone|iPad|iPod/.test(navigator.userAgent)) {
                    if (typeof showToast === 'function') {
                        showToast('primary', 'To install on iOS: tap the Share button <i class="fas fa-arrow-up-from-bracket"></i> below and select "Add to Home Screen".');
                    } else {
                        alert('To install on iOS: tap the Share button and select "Add to Home Screen".');
                    }
                } else {
                    if (typeof showToast === 'function') {
                        showToast('primary', 'Tap your browser menu (⋮) and select "Install app" or "Add to Home Screen".');
                    }
                }
                return;
            }

            // Trigger browser prompt
            deferredPrompt.prompt();

            deferredPrompt.userChoice.then(choiceResult => {
                if (choiceResult.outcome === 'accepted') {
                    console.log('[PWA] User accepted the install prompt');
                    setInstallButtonsVisibility(false);
                } else {
                    console.log('[PWA] User dismissed the install prompt');
                }
                deferredPrompt = null;
            }).catch(err => {
                console.warn('[PWA] Install prompt error:', err);
            });
        });
    });

    // Capture beforeinstallprompt event
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;
        console.log('[PWA] beforeinstallprompt captured');
        setInstallButtonsVisibility(true);
    });

    // Capture appinstalled event
    window.addEventListener('appinstalled', function () {
        deferredPrompt = null;
        setInstallButtonsVisibility(false);
        console.log('[PWA] App successfully installed');
        if (typeof showToast === 'function') {
            showToast('success', "Sagar Starter's app installed successfully!");
        }
    });

})();
