document.addEventListener('DOMContentLoaded', () => {
    // CSRF Token Helper
    const getCsrfToken = () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    };

    // Toast Notifications
    const showToast = (message, type = 'success') => {
        // Implement toast notification logic here
        console.log(`[${type.toUpperCase()}] ${message}`);
        alert(message);
    };

    // Queue Actions (AJAX)
    document.querySelectorAll('.queue-action-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const action = e.target.dataset.action;
            const id = e.target.dataset.id;
            
            if (['cancel', 'delete'].includes(action) && !confirm('Are you sure?')) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', action);
                formData.append('id', id);
                formData.append('csrf_token', getCsrfToken());

                const response = await fetch('ajax/ajax_queue_actions.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showToast(`Action ${action} successful!`);
                    location.reload(); // Quick refresh for now
                } else {
                    showToast(result.error, 'error');
                }
            } catch (error) {
                showToast('An error occurred', 'error');
            }
        });
    });

    // Template Live Preview (Debounced)
    const templateInput = document.getElementById('template-body');
    const previewContainer = document.getElementById('template-preview-container');
    
    if (templateInput && previewContainer) {
        let timeout = null;
        templateInput.addEventListener('input', (e) => {
            clearTimeout(timeout);
            timeout = setTimeout(async () => {
                const text = e.target.value;
                try {
                    const response = await fetch(`ajax/ajax_template_preview.php?template_body=${encodeURIComponent(text)}`);
                    const result = await response.json();
                    if (result.success) {
                        previewContainer.innerHTML = result.data.rendered;
                    }
                } catch (error) {
                    console.error('Preview error', error);
                }
            }, 300);
        });
    }

    // Chart.js init (mock)
    const ctx = document.getElementById('analyticsChart');
    if (ctx && typeof Chart !== 'undefined') {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
                datasets: [{
                    label: 'Posts',
                    data: [12, 19, 3, 5, 2],
                    borderColor: 'rgb(75, 192, 192)'
                }]
            }
        });
    }
});
