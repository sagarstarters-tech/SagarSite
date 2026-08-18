/**
 * main.js - Core UI & Form Validation Script (100% Native Vanilla JS)
 * High performance, zero external dependencies (no jQuery required)
 */
document.addEventListener('DOMContentLoaded', function () {
    // Universal Form Validation via Event Delegation
    document.addEventListener('submit', function (e) {
        if (!e.target || e.target.tagName !== 'FORM') return;
        const form = e.target;
        
        // Skip forms marked as no-validate
        if (form.classList.contains('no-validate') || form.hasAttribute('novalidate')) {
            return;
        }

        let valid = true;
        const requiredElements = form.querySelectorAll('input[required], select[required], textarea[required]');
        
        requiredElements.forEach(function (el) {
            if (el.value.trim() === '') {
                el.classList.add('is-invalid');
                valid = false;
            } else {
                el.classList.remove('is-invalid');
            }
        });

        if (!valid) {
            e.preventDefault();
            showToast('danger', 'Please fill in all required fields.');
        }
    });

    // Clear validation error on input
    document.addEventListener('input', function (e) {
        if (e.target && e.target.hasAttribute('required') && e.target.value.trim() !== '') {
            e.target.classList.remove('is-invalid');
        }
    });
});

/**
 * Universal Toast Notification
 * @param {string} type - 'success', 'danger', or 'primary'
 * @param {string} message - Text or HTML message
 */
function showToast(type, message) {
    const bgClass = type === 'success' ? 'bg-success' : (type === 'danger' ? 'bg-danger' : 'bg-primary');
    const toastDiv = document.createElement('div');
    toastDiv.className = `toast anim-slide-in align-items-center text-white ${bgClass} border-0 show`;
    toastDiv.setAttribute('role', 'alert');
    toastDiv.setAttribute('aria-live', 'assertive');
    toastDiv.setAttribute('aria-atomic', 'true');
    toastDiv.style.position = 'fixed';
    toastDiv.style.top = '20px';
    toastDiv.style.right = '20px';
    toastDiv.style.zIndex = '1055';
    toastDiv.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close" onclick="this.closest('.toast').remove()"></button>
        </div>
    `;
    document.body.appendChild(toastDiv);
    
    setTimeout(function () {
        toastDiv.classList.remove('show');
        setTimeout(function () {
            if (toastDiv.parentNode) {
                toastDiv.remove();
            }
        }, 300);
    }, 3000);
}
