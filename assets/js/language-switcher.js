/**
 * Sagar Starter's - Multi-Language Switcher Engine
 * Seamlessly integrates Google Translate without page-shifting frames or UI breaks.
 */

(function () {
    'use strict';

    const LANGUAGES = {
        'en': { name: 'English', native: 'English', flag: '🇬🇧' },
        'hi': { name: 'Hindi', native: 'हिन्दी', flag: '🇮🇳' },
        'gu': { name: 'Gujarati', native: 'ગુજરાતી', flag: '🇮🇳' },
        'mr': { name: 'Marathi', native: 'मराठी', flag: '🇮🇳' },
        'pa': { name: 'Punjabi', native: 'ਪੰਜਾਬੀ', flag: '🇮🇳' },
        'bn': { name: 'Bengali', native: 'বাংলা', flag: '🇮🇳' },
        'te': { name: 'Telugu', native: 'తెలుగు', flag: '🇮🇳' },
        'ta': { name: 'Tamil', native: 'தமிழ்', flag: '🇮🇳' },
        'kn': { name: 'Kannada', native: 'ಕನ್ನಡ', flag: '🇮🇳' },
        'ml': { name: 'Malayalam', native: 'മലയാളം', flag: '🇮🇳' },
        'or': { name: 'Odia', native: 'ଓଡ଼ିଆ', flag: '🇮🇳' },
        'ur': { name: 'Urdu', native: 'اردو', flag: '🇮🇳' }
    };

    /**
     * Helper to read cookie value
     */
    function getCookie(name) {
        const match = document.cookie.match(new RegExp('(^|;\\s*)(' + name + ')=([^;]*)'));
        return match ? decodeURIComponent(match[3]) : null;
    }

    /**
     * Helper to set cookie across root domain and current domain
     */
    function setCookie(name, value, days) {
        let expires = '';
        if (days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = '; expires=' + date.toUTCString();
        }

        const host = window.location.hostname;
        const rootDomain = host.replace(/^www\./i, '');

        // 1. Current domain
        document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Lax';

        // 2. Explicit hostname
        document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; domain=' + host + '; SameSite=Lax';

        // 3. Root domain (if not IP and not localhost)
        if (rootDomain !== host && !host.match(/^\d+\.\d+\.\d+\.\d+$/)) {
            document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; domain=.' + rootDomain + '; SameSite=Lax';
        }
    }

    /**
     * Helper to clear cookie across all domain variants
     */
    function deleteCookie(name) {
        const host = window.location.hostname;
        const rootDomain = host.replace(/^www\./i, '');

        document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
        document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=' + host + ';';
        if (rootDomain !== host && !host.match(/^\d+\.\d+\.\d+\.\d+$/)) {
            document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=.' + rootDomain + ';';
        }
    }

    /**
     * Get current active language code
     */
    function getCurrentLanguage() {
        const googtrans = getCookie('googtrans');
        if (googtrans) {
            const parts = googtrans.split('/');
            const code = parts[parts.length - 1];
            if (code && LANGUAGES[code]) return code;
        }

        const stored = localStorage.getItem('sagar_site_lang');
        if (stored && LANGUAGES[stored]) return stored;

        return 'en';
    }

    /**
     * Update UI elements (labels, checkmarks, active classes)
     */
    function updateLanguageUI(langCode) {
        const langInfo = LANGUAGES[langCode] || LANGUAGES['en'];

        // Update Desktop and Mobile dropdown labels
        document.querySelectorAll('.current-lang-name').forEach(el => {
            el.textContent = langInfo.native || langInfo.name;
        });
        document.querySelectorAll('.current-lang-flag').forEach(el => {
            el.textContent = langInfo.flag || '🌐';
        });

        // Update checkmark and active classes in dropdowns
        document.querySelectorAll('.lang-option').forEach(item => {
            const itemLang = item.getAttribute('data-lang');
            const checkIcon = item.querySelector('.check-icon');
            if (itemLang === langCode) {
                item.classList.add('active-lang');
                if (checkIcon) checkIcon.classList.remove('d-none');
            } else {
                item.classList.remove('active-lang');
                if (checkIcon) checkIcon.classList.add('d-none');
            }
        });
    }

    /**
     * Switch website language safely
     */
    window.changeSiteLanguage = function (targetLang) {
        if (!LANGUAGES[targetLang]) {
            targetLang = 'en';
        }

        const currentLang = getCurrentLanguage();
        if (targetLang === currentLang && targetLang !== 'en') {
            return;
        }

        localStorage.setItem('sagar_site_lang', targetLang);

        if (targetLang === 'en') {
            deleteCookie('googtrans');
            setCookie('googtrans', '/en/en', 30);
        } else {
            setCookie('googtrans', '/en/' + targetLang, 30);
        }

        updateLanguageUI(targetLang);

        // Try triggering Google Translate widget without full page reload if possible
        const select = document.querySelector('.goog-te-combo');
        if (select) {
            select.value = targetLang;
            select.dispatchEvent(new Event('change'));
        } else {
            // Reload page to apply new translation cookie
            window.location.reload();
        }
    };

    /**
     * Google Translate Initialization callback
     */
    window.googleTranslateElementInit = function () {
        if (typeof google === 'undefined' || !google.translate || !google.translate.TranslateElement) {
            return;
        }

        new google.translate.TranslateElement({
            pageLanguage: 'en',
            includedLanguages: Object.keys(LANGUAGES).join(','),
            autoDisplay: false,
            layout: google.translate.TranslateElement.InlineLayout.SIMPLE
        }, 'google_translate_element');

        // Check if user has non-English preference and trigger
        const activeLang = getCurrentLanguage();
        if (activeLang !== 'en') {
            setTimeout(function () {
                const select = document.querySelector('.goog-te-combo');
                if (select && select.value !== activeLang) {
                    select.value = activeLang;
                    select.dispatchEvent(new Event('change'));
                }
            }, 300);
        }
    };

    /**
     * DOM Ready Event Handler
     */
    document.addEventListener('DOMContentLoaded', function () {
        const initialLang = getCurrentLanguage();
        updateLanguageUI(initialLang);

        // Bind click events on all language options
        document.querySelectorAll('.lang-option').forEach(option => {
            option.addEventListener('click', function (e) {
                e.preventDefault();
                const lang = this.getAttribute('data-lang');
                if (lang) {
                    window.changeSiteLanguage(lang);
                }
            });
        });

        // Ensure google translate banner removal observer
        const observer = new MutationObserver(function () {
            const body = document.body;
            if (body && body.style.top && body.style.top !== '0px') {
                body.style.top = '0px';
            }
            const banner = document.querySelector('.goog-te-banner-frame');
            if (banner) {
                banner.style.display = 'none';
            }
        });

        observer.observe(document.documentElement, {
            attributes: true,
            childList: true,
            subtree: true
        });
    });
})();
