/**
 * WAPI SaaS Platform - Main JavaScript
 * Theme toggle, navbar scroll, FAQ accordion, pricing toggle, demo chat
 */

document.addEventListener('DOMContentLoaded', function() {
    // ===== Theme Toggle =====
    initTheme();
    
    // ===== Navbar Scroll Effect =====
    initNavbarScroll();
    
    // ===== FAQ Accordion =====
    // (handled via onclick in HTML)
    
    // ===== Pricing Toggle =====
    initPricingToggle();
    
    // ===== Demo Chat =====
    initDemoChat();
    
    // ===== Fade-in Animation on Scroll =====
    initScrollAnimations();
    
    // ===== CSRF Token for AJAX =====
    initAjaxCSRF();
    
    // ===== Dashboard Sidebar Toggle =====
    initDashboardSidebar();
});

// ===== Dashboard Sidebar Toggle =====
function initDashboardSidebar() {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebarToggle');
    const mobileToggle = document.getElementById('mobileSidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');

    if (!sidebar) return;

    if (toggle) {
        toggle.addEventListener('click', function() {
            if (window.innerWidth >= 992) {
                sidebar.classList.toggle('collapsed');
                document.body.classList.toggle('sidebar-collapsed', sidebar.classList.contains('collapsed'));
                localStorage.setItem('sidebar_collapsed', sidebar.classList.contains('collapsed'));
            } else {
                sidebar.classList.toggle('mobile-open');
                if (overlay) overlay.classList.toggle('show');
            }
        });
    }

    if (mobileToggle) {
        mobileToggle.addEventListener('click', function() {
            sidebar.classList.toggle('mobile-open');
            if (overlay) overlay.classList.toggle('show');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('show');
        });
    }

    // Restore sidebar collapsed state on desktop
    if (window.innerWidth >= 992 && localStorage.getItem('sidebar_collapsed') === 'true') {
        sidebar.classList.add('collapsed');
        document.body.classList.add('sidebar-collapsed');
    }
}

// ===== Theme System =====
function initTheme() {
    const toggle = document.getElementById('themeToggle');
    if (!toggle) return;
    
    // Check saved preference or system preference
    const savedTheme = localStorage.getItem('wapi_theme');
    const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const theme = savedTheme || (systemDark ? 'dark' : 'light');
    
    document.documentElement.setAttribute('data-theme', theme);
    updateThemeIcon(theme);
    
    toggle.addEventListener('click', function() {
        const current = document.documentElement.getAttribute('data-theme');
        const newTheme = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('wapi_theme', newTheme);
        updateThemeIcon(newTheme);
    });
    
    // Listen for system theme changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
        if (!localStorage.getItem('wapi_theme')) {
            const theme = e.matches ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', theme);
            updateThemeIcon(theme);
        }
    });
}

function updateThemeIcon(theme) {
    const toggle = document.getElementById('themeToggle');
    if (!toggle) return;
    const icon = toggle.querySelector('i');
    if (icon) {
        icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
    }
}

// ===== Navbar Scroll =====
function initNavbarScroll() {
    const navbar = document.getElementById('mainNav');
    if (!navbar) return;
    
    window.addEventListener('scroll', function() {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
}

// ===== FAQ Accordion =====
function toggleFaq(element) {
    const item = element.parentElement;
    const answer = item.querySelector('.faq-answer');
    const isActive = item.classList.contains('active');
    
    // Close all
    document.querySelectorAll('.faq-item').forEach(function(faqItem) {
        faqItem.classList.remove('active');
        faqItem.querySelector('.faq-answer').style.maxHeight = '0';
    });
    
    // Open clicked (if wasn't active)
    if (!isActive) {
        item.classList.add('active');
        answer.style.maxHeight = answer.scrollHeight + 'px';
    }
}

// ===== Pricing Toggle =====
function initPricingToggle() {
    const toggle = document.getElementById('pricingToggle');
    if (!toggle) return;
    
    let isYearly = false;
    
    toggle.addEventListener('click', function() {
        isYearly = !isYearly;
        toggle.classList.toggle('active', isYearly);
        
        document.getElementById('monthlyLabel').classList.toggle('active', !isYearly);
        document.getElementById('yearlyLabel').classList.toggle('active', isYearly);
        
        // Update prices
        document.querySelectorAll('.price-value').forEach(function(el) {
            const monthly = parseFloat(el.dataset.monthly);
            const yearly = parseFloat(el.dataset.yearly);
            const price = isYearly ? yearly : monthly;
            el.textContent = Math.round(price).toLocaleString('en-IN');
        });
        
        // Update period text
        document.querySelectorAll('.price-period').forEach(function(el) {
            el.textContent = isYearly ? '/year' : '/month';
        });
    });
}

// ===== Demo Chat =====
function initDemoChat() {
    const input = document.getElementById('demoInput');
    const sendBtn = document.getElementById('demoSendBtn');
    const messagesDiv = document.getElementById('demoMessages');
    
    if (!input || !sendBtn || !messagesDiv) return;
    
    const autoReplies = [
        "Thanks for trying our demo! 🎉 Our API can send messages like this automatically.",
        "You can send text, images, videos, and documents through our API. 📎",
        "Our platform supports bulk messaging to thousands of contacts! 📨",
        "Need help? Our support team is available 24/7. 💬",
        "Sign up now and get 100 free messages to try! 🚀",
        "Our support team is here to help you 24/7! 🤖"
    ];
    
    let replyIndex = 0;
    
    function sendMessage() {
        const text = input.value.trim();
        if (!text) return;
        
        // Add sent message
        addBubble(text, 'sent');
        input.value = '';
        
        // Show typing indicator
        const typingBubble = document.createElement('div');
        typingBubble.className = 'wa-bubble received typing-indicator';
        typingBubble.innerHTML = '<div class="typing-dots"><span>.</span><span>.</span><span>.</span></div>';
        messagesDiv.appendChild(typingBubble);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
        
        // Auto-reply after a short delay
        setTimeout(function() {
            // Remove typing indicator
            if (typingBubble.parentNode) {
                typingBubble.remove();
            }
            // Show auto-reply
            const reply = autoReplies[replyIndex % autoReplies.length];
            addBubble(reply, 'received');
            replyIndex++;
        }, 1200);
    }
    
    function addBubble(text, type) {
        const bubble = document.createElement('div');
        bubble.className = 'wa-bubble ' + type;
        
        const now = new Date();
        const time = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
        
        bubble.innerHTML = '<div>' + escapeHtml(text) + '</div><div class="wa-time">' + time + (type === 'sent' ? ' ✓✓' : '') + '</div>';
        messagesDiv.appendChild(bubble);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }
    
    sendBtn.addEventListener('click', sendMessage);
    input.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') sendMessage();
    });
}

// ===== Scroll Animations =====
function initScrollAnimations() {
    const elements = document.querySelectorAll('.feature-card, .pricing-card, .testimonial-card');
    
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });
        
        elements.forEach(function(el) {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });
    }
}

// ===== AJAX CSRF =====
function initAjaxCSRF() {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) {
        window.csrfToken = csrfMeta.getAttribute('content');
    }
}

// ===== Utility Functions =====
function escapeHtml(text) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}

function showAlert(container, type, message) {
    const icons = { success: 'check-circle-fill', danger: 'exclamation-circle-fill', warning: 'exclamation-triangle-fill', info: 'info-circle-fill' };
    const html = '<div class="alert alert-' + type + ' fade-in"><i class="bi bi-' + (icons[type] || 'info-circle-fill') + '"></i>' + message + '</div>';
    
    const el = document.querySelector(container);
    if (el) {
        el.innerHTML = html;
        setTimeout(function() { el.innerHTML = ''; }, 5000);
    }
}

// ===== AJAX Helper =====
async function apiRequest(url, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': window.csrfToken || ''
        }
    };
    
    if (data) {
        if (data instanceof FormData) {
            options.body = data;
        } else {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(data);
        }
    }
    
    try {
        const response = await fetch(url, options);
        const json = await response.json();
        return json;
    } catch (error) {
        console.error('API Error:', error);
        return { success: false, message: 'Network error. Please try again.' };
    }
}

// ===== Password Toggle =====
function togglePassword(btn) {
    const input = btn.parentElement.querySelector('input');
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
