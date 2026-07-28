/**
 * AI Analytics - Chart.js Initialization
 * Renders conversations chart and resolution breakdown
 */
document.addEventListener('DOMContentLoaded', function() {
    const data = window.aiAnalyticsData || {};
    
    // ============================================
    // Conversations Over Time (Line Chart)
    // ============================================
    const convCtx = document.getElementById('aiConversationsChart');
    if (convCtx && data.chart) {
        const chartEntries = data.chart || [];
        const labels = chartEntries.map(d => {
            const date = new Date(d.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });

        new Chart(convCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Total',
                        data: chartEntries.map(d => d.total),
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#667eea',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    },
                    {
                        label: 'AI Resolved',
                        data: chartEntries.map(d => d.ai_resolved),
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.05)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#10b981',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    },
                    {
                        label: 'Human Transfer',
                        data: chartEntries.map(d => d.human_transferred),
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.05)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#f59e0b',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 15,
                            font: { size: 12, family: 'Inter' }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: { size: 13 },
                        bodyFont: { size: 12 },
                        cornerRadius: 8,
                        displayColors: true,
                        usePointStyle: true
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 11, family: 'Inter' },
                            maxRotation: 0
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            font: { size: 11, family: 'Inter' },
                            stepSize: 1,
                            precision: 0
                        }
                    }
                }
            }
        });
    }

    // ============================================
    // Resolution Breakdown (Doughnut Chart)
    // ============================================
    const resCtx = document.getElementById('aiResolutionChart');
    if (resCtx && data.resolution) {
        const res = data.resolution;
        const total = (res.ai || 0) + (res.human || 0) + (res.active || 0);
        
        new Chart(resCtx, {
            type: 'doughnut',
            data: {
                labels: ['AI Resolved', 'Human Transfer', 'Active'],
                datasets: [{
                    data: [res.ai || 0, res.human || 0, res.active || 0],
                    backgroundColor: [
                        '#10b981',
                        '#f59e0b',
                        '#667eea'
                    ],
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 15,
                            font: { size: 11, family: 'Inter' }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed;
                                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                return ` ${context.label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            },
            plugins: [{
                id: 'centerText',
                beforeDraw: function(chart) {
                    const ctx = chart.ctx;
                    const centerX = (chart.chartArea.left + chart.chartArea.right) / 2;
                    const centerY = (chart.chartArea.top + chart.chartArea.bottom) / 2;
                    
                    ctx.save();
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    
                    // Total number
                    ctx.font = 'bold 24px Inter';
                    ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--text-primary') || '#1f2937';
                    ctx.fillText(total, centerX, centerY - 8);
                    
                    // Label
                    ctx.font = '11px Inter';
                    ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--text-muted') || '#9ca3af';
                    ctx.fillText('Total', centerX, centerY + 14);
                    
                    ctx.restore();
                }
            }]
        });
    }
});
