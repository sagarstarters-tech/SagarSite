/**
 * WAPI SaaS - Admin Panel JavaScript
 * Charts, sidebar toggle, data management
 */

document.addEventListener('DOMContentLoaded', function() {
    // Sidebar is initialized by app.js (initDashboardSidebar) — no duplicate init here
    initCharts();
    initDataTables();
});


// ===== Charts =====
function initCharts() {
    // Message Analytics Chart
    const msgCtx = document.getElementById('messagesChart');
    if (msgCtx && window.chartData) {
        const data = window.chartData.messages || [];
        const last7Days = [];
        for (let i = 6; i >= 0; i--) {
            const date = new Date();
            date.setDate(date.getDate() - i);
            last7Days.push(date.toISOString().split('T')[0]);
        }

        const sentData = last7Days.map(date => {
            const items = data.filter(d => d.date === date && d.status === 'sent');
            return items.reduce((sum, item) => sum + parseInt(item.count), 0);
        });
        
        const deliveredData = last7Days.map(date => {
            const items = data.filter(d => d.date === date && d.status === 'delivered');
            return items.reduce((sum, item) => sum + parseInt(item.count), 0);
        });

        const failedData = last7Days.map(date => {
            const items = data.filter(d => d.date === date && d.status === 'failed');
            return items.reduce((sum, item) => sum + parseInt(item.count), 0);
        });

        const labels = last7Days.map(d => {
            const date = new Date(d);
            return date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short' });
        });

        new Chart(msgCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Sent',
                        data: sentData,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2
                    },
                    {
                        label: 'Delivered',
                        data: deliveredData,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2
                    },
                    {
                        label: 'Failed',
                        data: failedData,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { usePointStyle: true, padding: 20 }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // Status Donut Chart
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx && window.chartData) {
        const totals = window.chartData.totals || { sent: 30, delivered: 45, failed: 5, queued: 10 };
        
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Sent', 'Delivered', 'Failed', 'Queued'],
                datasets: [{
                    data: [totals.sent, totals.delivered, totals.failed, totals.queued],
                    backgroundColor: ['#3b82f6', '#10b981', '#ef4444', '#f59e0b'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { usePointStyle: true, padding: 15 }
                    }
                }
            }
        });
    }
}

// ===== Data Table Helpers =====
function initDataTables() {
    // Search functionality
    document.querySelectorAll('.search-box input').forEach(function(input) {
        input.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const table = this.closest('.data-table').querySelector('tbody');
            if (table) {
                table.querySelectorAll('tr').forEach(function(row) {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(query) ? '' : 'none';
                });
            }
        });
    });
}

// ===== Delete Confirm =====
function confirmDelete(url, name) {
    if (confirm('Are you sure you want to delete "' + name + '"? This action cannot be undone.')) {
        window.location.href = url;
    }
}

// ===== Bulk Actions =====
function toggleSelectAll(checkbox) {
    document.querySelectorAll('.row-checkbox').forEach(function(cb) {
        cb.checked = checkbox.checked;
    });
}

// ===== AJAX Form Submit =====
async function submitForm(formId, callback) {
    const form = document.getElementById(formId);
    if (!form) return;

    const formData = new FormData(form);
    const submitBtn = form.querySelector('[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.innerHTML = '<span class="spinner" style="width:20px;height:20px;border-width:2px;"></span> Saving...';
    submitBtn.disabled = true;

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await response.json();
        
        if (callback) callback(result);
        
        if (result.success) {
            showAlert('#alertContainer', 'success', result.message);
        } else {
            showAlert('#alertContainer', 'danger', result.message);
        }
    } catch (error) {
        showAlert('#alertContainer', 'danger', 'An error occurred. Please try again.');
    } finally {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
}
