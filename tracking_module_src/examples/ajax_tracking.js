/**
 * ajax_tracking.js
 * Handles fetching Tracking Details seamlessly without a page reload.
 * Includes AWB Shipment Tracking support with live courier redirect.
 */

document.addEventListener('DOMContentLoaded', function () {
    const trackingForm = document.getElementById('trackingForm');
    const trackingResultContainer = document.getElementById('trackingResultContainer');

    if (trackingForm) {
        trackingForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const orderId = document.getElementById('track_order_id') ? document.getElementById('track_order_id').value.trim() : '';
            const identifier = document.getElementById('track_email') ? document.getElementById('track_email').value.trim() : '';
            const submitBtn = trackingForm.querySelector('button[type="submit"]');

            if (!identifier && !orderId) {
                showTrackingError('Please enter your Mobile Number or Email to track your order.');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.style.whiteSpace = 'nowrap';
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i><span>Tracking...</span>';

            // Build query params
            const params = new URLSearchParams({
                action: 'get_customer_tracking',
                order_id: orderId,
                email: identifier,
                phone: identifier,
                identifier: identifier
            });

            // Use injected API URL (set by PHP in track_order.php to handle subdirectory deployments)
            const apiBase = window.TRACKING_API_URL || '/tracking_module_src/TrackingAPI.php';

            fetch(apiBase + '?' + params.toString(), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            })
                .then(response => response.json())
                .then(data => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-search me-2"></i>Track Order';

                    if (data.status === 'success') {
                        renderTrackingUI(data.data, identifier);
                    } else if (data.status === 'multiple_orders' && data.orders) {
                        renderMultipleOrdersUI(data.orders, identifier);
                    } else {
                        showTrackingError(data.message || 'Error tracking your order. Please check your details.');
                    }
                })
                .catch(error => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-search me-2"></i>Track Order';
                    showTrackingError('A network error occurred connecting to the tracking service.');
                    console.error('Tracking Error:', error);
                });
        });

        // Auto-track if both Order ID and Identifier (or a complete mobile/email) are already filled from URL
        const initialOrderId = document.getElementById('track_order_id') ? document.getElementById('track_order_id').value.trim() : '';
        const initialIdentifier = document.getElementById('track_email') ? document.getElementById('track_email').value.trim() : '';
        if (initialIdentifier !== '' && (initialOrderId !== '' || initialIdentifier.length >= 10)) {
            setTimeout(() => {
                trackingForm.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
            }, 150);
        }
    }

    function renderTrackingUI(data, email) {
        const info = data.order_info;
        const shipping = data.shipping;
        const timeline = data.timeline;

        // Progress Bar Calculation
        const stageIndex = info.progress_stage_index;
        const steps = ['Pending', 'Processing', 'Shipped', 'Out for Delivery', 'Delivered'];

        let progressHtml = '<div class="tracking-progress-container mb-4"><ul class="tracking-progress">';

        if (stageIndex === -1) {
            progressHtml += '<li class="text-danger w-100 text-center"><i class="fas fa-times-circle fa-2x mb-2"></i><br>Order Cancelled</li>';
        } else {
            for (let i = 0; i < steps.length; i++) {
                let statusClass = '';
                if (i < stageIndex) statusClass = 'completed';
                if (i === stageIndex) statusClass = 'active';

                // Note: Index 2 combines Shipped & Partially Shipped conceptually here
                let label = steps[i];
                if (i === 2 && info.status === 'partially_shipped') label = 'Partially Shipped';

                progressHtml += `<li class="${statusClass}">${label}</li>`;
            }
        }
        progressHtml += '</ul></div>';

        // Tracking Meta details
        let detailsHtml = `
            <div class="row mb-4">
                <div class="col-md-6 border-end">
                    <p class="text-muted mb-1">Order ID</p>
                    <h5 class="fw-bold">#${info.order_id}</h5>
                    <p class="text-muted mb-1 mt-3">Current Status</p>
                    <span class="badge bg-primary fs-6">${info.status_formatted}</span>
                </div>
                <div class="col-md-6 ps-md-4">
                    <p class="text-muted mb-1">Courier Partner</p>
                    <h6 class="fw-bold">${shipping.courier_name || 'Processing...'}</h6>
                    
                    <p class="text-muted mb-1 mt-3">Tracking Number (AWB)</p>
                    <h6 class="fw-bold">
                        ${shipping.tracking_number ? 
                            `<span class="font-monospace">${shipping.tracking_number}</span>` : 
                            '<span class="text-muted fst-italic">Awaiting dispatch</span>'
                        }
                        ${shipping.tracking_url ? `<a href="${shipping.tracking_url}" target="_blank" class="ms-2 fs-6" title="Track on courier website"><i class="fas fa-external-link-alt"></i></a>` : ''}
                    </h6>
                    
                    <p class="text-muted mb-1 mt-3">Estimated Delivery</p>
                    <h6 class="fw-bold text-success">${shipping.estimated_delivery || 'TBD'}</h6>
                </div>
            </div>
        `;

        // --- AWB Shipment Tracking Section ---
        let awbTrackingHtml = '';
        if (shipping.tracking_number) {
            const hasUrl = shipping.tracking_url && shipping.tracking_url.trim() !== '';
            
            awbTrackingHtml = `
                <div class="awb-tracking-card mt-4 mb-4">
                    <div class="awb-tracking-header">
                        <div class="icon-circle">
                            <i class="fas fa-satellite-dish"></i>
                        </div>
                        <div>
                            <h6><i class="fas fa-broadcast-tower me-1"></i> AWB Shipment Tracking</h6>
                            <small>${shipping.courier_name || 'Courier'} • Live Tracking</small>
                        </div>
                    </div>
                    <div class="p-4 text-center">
                        <p class="text-muted mb-2"><small>Your AWB / Tracking Number</small></p>
                        <div class="awb-number-display mb-3" onclick="copyToClipboard('${shipping.tracking_number}')" title="Click to copy">
                            ${shipping.tracking_number}
                            <i class="fas fa-copy ms-2 text-muted" style="font-size: 0.8rem;"></i>
                        </div>
                        <div class="d-flex flex-wrap gap-2 justify-content-center mt-3">
                            <button class="btn-copy-awb" onclick="copyToClipboard('${shipping.tracking_number}')">
                                <i class="fas fa-copy me-2"></i>Copy AWB Number
                            </button>
                            ${hasUrl ? `
                                <a href="${shipping.tracking_url}" target="_blank" rel="noopener noreferrer" class="btn-track-awb text-decoration-none pulse-live">
                                    <i class="fas fa-external-link-alt me-2"></i>Track Live on ${shipping.courier_name}
                                </a>
                            ` : ''}
                        </div>
                        ${hasUrl ? `
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Click "Track Live" to view real-time shipment updates on ${shipping.courier_name}'s website.
                                </small>
                            </div>
                        ` : `
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Copy the AWB number above and track on your courier's website.
                                </small>
                            </div>
                        `}
                    </div>
                </div>
            `;
        }

        // Timeline History Log
        let historyHtml = '<h5 class="fw-bold mb-3 border-bottom pb-2">Tracking History</h5><div class="tracking-timeline">';
        if (timeline && timeline.length > 0) {
            timeline.forEach(log => {
                const dateObj = new Date(log.created_at);
                const dateStr = dateObj.toLocaleDateString() + ' ' + dateObj.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                historyHtml += `
                <div class="timeline-item pb-3 mb-3 border-bottom">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong class="text-primary">${log.status.toUpperCase().replace('_', ' ')}</strong>
                        <small class="text-muted"><i class="far fa-clock me-1"></i>${dateStr}</small>
                    </div>
                    ${log.notes ? `<p class="mb-0 text-muted small">${log.notes}</p>` : ''}
                </div>`;
            });
        } else {
            historyHtml += '<p class="text-muted">No timeline updates available yet.</p>';
        }
        historyHtml += '</div>';

        trackingResultContainer.innerHTML = `
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-4 p-md-5">
                    ${progressHtml}
                    ${detailsHtml}
                    ${awbTrackingHtml}
                    ${historyHtml}
                </div>
            </div>
        `;
        trackingResultContainer.classList.remove('d-none');
        
        // Smooth scroll to results
        trackingResultContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function renderMultipleOrdersUI(orders, identifier) {
        let rowsHtml = '';
        orders.forEach(order => {
            const dateObj = new Date(order.created_at);
            const dateFormatted = !isNaN(dateObj) ? dateObj.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : order.created_at;
            const statusLabel = (order.status || 'Pending').replace('_', ' ').toUpperCase();
            
            rowsHtml += `
                <div class="col-md-6 mb-3">
                    <div class="card h-100 border shadow-sm rounded-3">
                        <div class="card-body p-3 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="fw-bold mb-0 text-primary">Order #${order.id}</h6>
                                    <span class="badge bg-primary">${statusLabel}</span>
                                </div>
                                <p class="text-muted small mb-1"><i class="far fa-calendar-alt me-1"></i> Date: ${dateFormatted}</p>
                                <p class="fw-bold mb-3">Total: ₹${parseFloat(order.total_amount).toFixed(2)}</p>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill w-100" onclick="trackSpecificOrder(${order.id}, '${identifier.replace(/'/g, "\\'")}')">
                                <i class="fas fa-search-location me-1"></i> Track This Order
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });

        trackingResultContainer.innerHTML = `
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-4 p-md-5">
                    <h5 class="fw-bold mb-2"><i class="fas fa-boxes text-primary me-2"></i>Select an Order to Track</h5>
                    <p class="text-muted small mb-4">We found multiple orders associated with <strong>${identifier}</strong>. Click below to view live shipment tracking.</p>
                    <div class="row">
                        ${rowsHtml}
                    </div>
                </div>
            </div>
        `;
        trackingResultContainer.classList.remove('d-none');
        trackingResultContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function showTrackingError(msg) {
        trackingResultContainer.innerHTML = `
            <div class="alert alert-danger py-3">
                <i class="fas fa-exclamation-triangle me-2"></i> ${msg}
            </div>
        `;
        trackingResultContainer.classList.remove('d-none');
    }

    window.trackSpecificOrder = function(orderId, identifier) {
        const orderInput = document.getElementById('track_order_id');
        const identInput = document.getElementById('track_email');
        if (orderInput) orderInput.value = orderId;
        if (identInput) identInput.value = identifier;
        if (trackingForm) {
            trackingForm.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
        }
    };
});

/**
 * Copy text to clipboard with visual feedback
 */
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showCopyToast('AWB number copied: ' + text);
    }).catch(() => {
        // Fallback for older browsers
        const input = document.createElement('input');
        input.value = text;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        showCopyToast('AWB number copied: ' + text);
    });
}

/**
 * Show a brief toast notification for copy action
 */
function showCopyToast(message) {
    // Remove existing toast if any
    const existing = document.getElementById('awbCopyToast');
    if (existing) existing.remove();
    
    const toast = document.createElement('div');
    toast.id = 'awbCopyToast';
    toast.style.cssText = `
        position: fixed; bottom: 20px; right: 20px; z-index: 99999;
        min-width: 280px; padding: 14px 20px;
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white; border-radius: 12px;
        box-shadow: 0 8px 25px rgba(40, 167, 69, 0.35);
        font-weight: 600; font-size: 0.9rem;
        animation: slideInUp 0.3s ease;
    `;
    toast.innerHTML = `<i class="fas fa-check-circle me-2"></i>${message}`;
    
    // Add animation keyframes if not already present
    if (!document.getElementById('awbToastStyles')) {
        const style = document.createElement('style');
        style.id = 'awbToastStyles';
        style.textContent = `
            @keyframes slideInUp {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
        `;
        document.head.appendChild(style);
    }
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 2500);
}
