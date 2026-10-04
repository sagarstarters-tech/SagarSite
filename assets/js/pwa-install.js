/**
 * Sagar Starter's - PWA Installation & Lifecycle Controller
 * 
 * Logic Enforced:
 * - Install Button ONLY displays when the app is NOT installed on the device.
 * - Detects whether app is already installed via:
 *   1. navigator.getInstalledRelatedApps() (Official Chromium/Chrome/Edge/Android API)
 *   2. Standalone display mode (display-mode: standalone, minimal-ui, fullscreen, iOS standalone)
 *   3. Persistent localStorage installation flag ('pwa_installed')
 * - Immediately hides triggers when:
 *   - The app is running standalone
 *   - The browser reports the app is already installed on the device
 *   - The 'appinstalled' event fires
 *   - The user accepts the install prompt
 * - Re-enables install trigger only if user uninstalls the app and beforeinstallprompt fires again.
 */

(function () {
    'use strict';

    let deferredPrompt = null;
    let isAppAlreadyInstalled = false;

    // Check if web app is currently running inside an installed standalone window or web container
    function isRunningStandalone() {
        return (
            (window.matchMedia && (
                window.matchMedia('(display-mode: standalone)').matches ||
                window.matchMedia('(display-mode: minimal-ui)').matches ||
                window.matchMedia('(display-mode: fullscreen)').matches ||
                window.matchMedia('(display-mode: window-controls-overlay)').matches
            )) ||
            (window.navigator.standalone === true) ||
            (document.referrer && document.referrer.includes('android-app://'))
        );
    }

    // Toggle visibility of all install buttons on the page
    function setInstallButtonsVisibility(visible) {
        const buttons = document.querySelectorAll('.pwa-install-btn, #pwaInstallBtn, #mobilePwaInstallBtn');
        buttons.forEach(btn => {
            if (visible && !isAppAlreadyInstalled && !isRunningStandalone()) {
                btn.classList.remove('d-none');
                btn.removeAttribute('aria-hidden');
                btn.style.removeProperty('display');
            } else {
                btn.classList.add('d-none');
                btn.setAttribute('aria-hidden', 'true');
                btn.style.setProperty('display', 'none', 'important');
            }
        });
    }

    // Asynchronously determine if the app is installed on the user's device
    async function checkDeviceInstallStatus() {
        // 1. If currently in standalone / installed PWA window, it is 100% installed
        if (isRunningStandalone()) {
            isAppAlreadyInstalled = true;
            try { localStorage.setItem('pwa_installed', 'true'); } catch (e) {}
            return true;
        }

        // 2. Query official browser API navigator.getInstalledRelatedApps() (Chrome 80+, Edge, Android)
        if ('getInstalledRelatedApps' in navigator && typeof navigator.getInstalledRelatedApps === 'function') {
            try {
                const relatedApps = await navigator.getInstalledRelatedApps();
                if (Array.isArray(relatedApps) && relatedApps.length > 0) {
                    console.log('[PWA] App is verified as already installed on this device:', relatedApps);
                    isAppAlreadyInstalled = true;
                    try { localStorage.setItem('pwa_installed', 'true'); } catch (e) {}
                    return true;
                } else if (Array.isArray(relatedApps) && relatedApps.length === 0) {
                    // Browser specifically reported 0 installed apps (not installed or uninstalled)
                    isAppAlreadyInstalled = false;
                    try { localStorage.removeItem('pwa_installed'); } catch (e) {}
                    return false;
                }
            } catch (err) {
                console.warn('[PWA] Error checking getInstalledRelatedApps:', err);
            }
        }

        // 3. Fallback: check localStorage persistence from previous installation in this browser
        try {
            if (localStorage.getItem('pwa_installed') === 'true') {
                isAppAlreadyInstalled = true;
                return true;
            }
        } catch (e) {}

        return false;
    }

    // Initialize controller state on page load
    async function initPwaController() {
        // Hide all buttons by default while verifying
        setInstallButtonsVisibility(false);

        const installed = await checkDeviceInstallStatus();
        if (installed) {
            console.log('[PWA] Device check: App is already installed. Install button remains hidden.');
            setInstallButtonsVisibility(false);
            return;
        }

        // If not installed and we already have a captured prompt, show the button
        if (deferredPrompt && !isAppAlreadyInstalled) {
            setInstallButtonsVisibility(true);
        }

        // Listen for display mode changes (e.g. user launches the installed app)
        try {
            if (window.matchMedia) {
                const standaloneMedia = window.matchMedia('(display-mode: standalone)');
                const handleModeChange = (e) => {
                    if (e.matches) {
                        isAppAlreadyInstalled = true;
                        try { localStorage.setItem('pwa_installed', 'true'); } catch (err) {}
                        setInstallButtonsVisibility(false);
                    }
                };
                if (standaloneMedia.addEventListener) {
                    standaloneMedia.addEventListener('change', handleModeChange);
                } else if (standaloneMedia.addListener) {
                    standaloneMedia.addListener(handleModeChange);
                }
            }
        } catch (err) {}
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPwaController);
    } else {
        initPwaController();
    }
    window.addEventListener('load', initPwaController);

    // Capture beforeinstallprompt event
    // IMPORTANT: The browser ONLY fires this event if the app is NOT already installed on this device!
    window.addEventListener('beforeinstallprompt', async function (e) {
        e.preventDefault();
        deferredPrompt = e;

        // Verify device status before showing
        const isInstalled = await checkDeviceInstallStatus();
        if (isInstalled) {
            console.log('[PWA] beforeinstallprompt fired, but app is already installed on device. Suppressing button.');
            deferredPrompt = null;
            setInstallButtonsVisibility(false);
            return;
        }

        // App is NOT installed -> Show the Install Button
        console.log('[PWA] App is NOT installed on this device. Revealing install button.');
        setInstallButtonsVisibility(true);
    });

    // Capture appinstalled event (fired whenever the app is installed via website or browser UI)
    window.addEventListener('appinstalled', function () {
        deferredPrompt = null;
        isAppAlreadyInstalled = true;
        try {
            localStorage.setItem('pwa_installed', 'true');
            localStorage.setItem('pwa_installed_at', Date.now().toString());
        } catch (e) {}
        setInstallButtonsVisibility(false);
        console.log('[PWA] Sagar Starter\'s App successfully installed on device.');
        if (typeof showToast === 'function') {
            showToast('success', "Sagar Starter's app installed successfully!");
        }
    });

    // Handle click on any install trigger
    document.addEventListener('click', async function (e) {
        const target = e.target.closest('.pwa-install-btn, #pwaInstallBtn, #mobilePwaInstallBtn');
        if (!target) return;

        e.preventDefault();

        // If the app is already installed, hide button and notify
        if (await checkDeviceInstallStatus()) {
            setInstallButtonsVisibility(false);
            if (typeof showToast === 'function') {
                showToast('info', "Sagar Starter's app is already installed on your device.");
            }
            return;
        }

        // If prompt is not available (e.g. iOS or manual browser install)
        if (!deferredPrompt) {
            if (/iPhone|iPad|iPod/.test(navigator.userAgent)) {
                if (typeof showToast === 'function') {
                    showToast('primary', 'To install on iOS: tap the Share button <i class="fas fa-arrow-up-from-bracket"></i> and select "Add to Home Screen".');
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

        // Trigger native install prompt
        deferredPrompt.prompt();

        try {
            const choiceResult = await deferredPrompt.userChoice;
            if (choiceResult && choiceResult.outcome === 'accepted') {
                console.log('[PWA] User accepted the install prompt');
                isAppAlreadyInstalled = true;
                try {
                    localStorage.setItem('pwa_installed', 'true');
                    localStorage.setItem('pwa_installed_at', Date.now().toString());
                } catch (err) {}
                setInstallButtonsVisibility(false);
            } else {
                console.log('[PWA] User dismissed the install prompt');
            }
        } catch (err) {
            console.warn('[PWA] Install prompt error:', err);
        } finally {
            deferredPrompt = null;
        }
    });

})();
