// Portfolio Tracker - Charts Module
class PortfolioCharts {
    constructor() {
        this.charts = {};
        this.chartColors = {
            primary: '#2563eb',
            success: '#10b981',
            danger: '#ef4444',
            warning: '#f59e0b',
            info: '#06b6d4',
            purple: '#8b5cf6',
            pink: '#ec4899',
            indigo: '#6366f1'
        };
        
        this.sectorColors = [
            '#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
            '#06b6d4', '#ec4899', '#6366f1', '#84cc16', '#f97316'
        ];
    }
    
    /**
     * Create portfolio performance line chart
     */
    createPerformanceChart(canvasId, data, options = {}) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return null;
        
        // Destroy existing chart if it exists
        if (this.charts[canvasId]) {
            this.charts[canvasId].destroy();
        }
        
        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            size: 12,
                            family: 'Inter'
                        }
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#374151',
                    bodyColor: '#6b7280',
                    borderColor: '#e5e7eb',
                    borderWidth: 1,
                    cornerRadius: 8,
                    padding: 12,
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            const label = context.dataset.label || '';
                            const value = context.parsed.y;
                            return `${label}: $${value.toLocaleString('en-US', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            })}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    display: true,
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 11,
                            family: 'Inter'
                        },
                        color: '#9ca3af'
                    }
                },
                y: {
                    display: true,
                    grid: {
                        color: '#f3f4f6',
                        drawBorder: false
                    },
                    ticks: {
                        font: {
                            size: 11,
                            family: 'Inter'
                        },
                        color: '#9ca3af',
                        callback: function(value) {
                            return '$' + value.toLocaleString('en-US', {
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0
                            });
                        }
                    }
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            },
            elements: {
                point: {
                    radius: 4,
                    hoverRadius: 6,
                    borderWidth: 2,
                    hoverBorderWidth: 3
                },
                line: {
                    borderWidth: 3,
                    tension: 0.1
                }
            }
        };
        
        // Add event annotations if provided
        if (options.events && options.events.length > 0) {
            defaultOptions.plugins.annotation = this.createEventAnnotations(options.events, data.labels);
        }

        const config = {
            type: 'line',
            data: data,
            options: { ...defaultOptions, ...options }
        };

        this.charts[canvasId] = new Chart(ctx, config);

        // Add event markers as overlays since Chart.js annotation plugin might not be available
        if (options.events && options.events.length > 0) {
            this.addEventMarkers(canvasId, options.events, data.labels);
        }

        return this.charts[canvasId];
    }
    
    /**
     * Create sector allocation pie chart
     */
    createSectorChart(canvasId, data, options = {}) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return null;
        
        // Destroy existing chart if it exists
        if (this.charts[canvasId]) {
            this.charts[canvasId].destroy();
        }
        
        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'right',
                    labels: {
                        usePointStyle: true,
                        padding: 15,
                        font: {
                            size: 12,
                            family: 'Inter'
                        },
                        generateLabels: function(chart) {
                            const data = chart.data;
                            if (data.labels.length && data.datasets.length) {
                                return data.labels.map((label, i) => {
                                    const dataset = data.datasets[0];
                                    const value = dataset.data[i];
                                    const total = dataset.data.reduce((a, b) => a + b, 0);

                                    // Handle empty data case
                                    if (total === 0 || label === 'No Data') {
                                        return {
                                            text: 'No data available',
                                            fillStyle: dataset.backgroundColor[i],
                                            strokeStyle: dataset.borderColor[i],
                                            lineWidth: dataset.borderWidth,
                                            pointStyle: 'circle',
                                            hidden: false,
                                            index: i
                                        };
                                    }

                                    const percentage = ((value / total) * 100).toFixed(1);

                                    return {
                                        text: `${label} (${percentage}%)`,
                                        fillStyle: dataset.backgroundColor[i],
                                        strokeStyle: dataset.borderColor[i],
                                        lineWidth: dataset.borderWidth,
                                        pointStyle: 'circle',
                                        hidden: false,
                                        index: i
                                    };
                                });
                            }
                            return [];
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#374151',
                    bodyColor: '#6b7280',
                    borderColor: '#e5e7eb',
                    borderWidth: 1,
                    cornerRadius: 8,
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${label}: ${percentage}% ($${value.toLocaleString('en-US', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            })})`;
                        }
                    }
                }
            }
        };
        
        const config = {
            type: 'doughnut',
            data: data,
            options: { ...defaultOptions, ...options }
        };
        
        this.charts[canvasId] = new Chart(ctx, config);
        return this.charts[canvasId];
    }
    
    /**
     * Create holdings performance bar chart
     */
    createHoldingsChart(canvasId, data, options = {}) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return null;
        
        // Destroy existing chart if it exists
        if (this.charts[canvasId]) {
            this.charts[canvasId].destroy();
        }
        
        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#374151',
                    bodyColor: '#6b7280',
                    borderColor: '#e5e7eb',
                    borderWidth: 1,
                    cornerRadius: 8,
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            const value = context.parsed.y;
                            return `Return: ${value >= 0 ? '+' : ''}${value.toFixed(2)}%`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    display: true,
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 11,
                            family: 'Inter'
                        },
                        color: '#9ca3af'
                    }
                },
                y: {
                    display: true,
                    grid: {
                        color: '#f3f4f6',
                        drawBorder: false
                    },
                    ticks: {
                        font: {
                            size: 11,
                            family: 'Inter'
                        },
                        color: '#9ca3af',
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            }
        };
        
        const config = {
            type: 'bar',
            data: data,
            options: { ...defaultOptions, ...options }
        };
        
        this.charts[canvasId] = new Chart(ctx, config);
        return this.charts[canvasId];
    }
    
    /**
     * Generate mock performance data for demonstration
     */
    generateMockPerformanceData(days = 30) {
        const labels = [];
        const portfolioData = [];
        const benchmarkData = [];
        
        const startValue = 10000;
        let currentValue = startValue;
        let benchmarkValue = startValue;
        
        for (let i = days; i >= 0; i--) {
            const date = new Date();
            date.setDate(date.getDate() - i);
            labels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
            
            // Generate realistic portfolio movement
            const change = (Math.random() - 0.5) * 0.04; // ±2% daily change
            currentValue *= (1 + change);
            portfolioData.push(Math.round(currentValue * 100) / 100);
            
            // Generate benchmark movement (slightly more stable)
            const benchmarkChange = (Math.random() - 0.5) * 0.02; // ±1% daily change
            benchmarkValue *= (1 + benchmarkChange);
            benchmarkData.push(Math.round(benchmarkValue * 100) / 100);
        }
        
        return {
            labels,
            datasets: [
                {
                    label: 'Portfolio Value',
                    data: portfolioData,
                    borderColor: this.chartColors.primary,
                    backgroundColor: this.chartColors.primary + '20',
                    fill: true
                },
                {
                    label: 'S&P 500 Benchmark',
                    data: benchmarkData,
                    borderColor: this.chartColors.info,
                    backgroundColor: this.chartColors.info + '20',
                    fill: false,
                    borderDash: [5, 5]
                }
            ]
        };
    }
    
    /**
     * Generate sector allocation data from holdings
     */
    generateSectorData(holdings) {
        const sectors = {};

        holdings.forEach(holding => {
            // Use real sector data if available, fallback to mock
            const sector = holding.sector || this.getMockSector(holding.symbol);
            sectors[sector] = (sectors[sector] || 0) + holding.current_value;
        });

        const labels = Object.keys(sectors);
        const data = Object.values(sectors);
        const colors = labels.map((_, index) => this.sectorColors[index % this.sectorColors.length]);

        return {
            labels,
            datasets: [{
                data,
                backgroundColor: colors,
                borderColor: colors.map(color => color + 'CC'),
                borderWidth: 2
            }]
        };
    }
    
    /**
     * Generate holdings performance data
     */
    generateHoldingsData(holdings) {
        const labels = holdings.map(h => h.symbol);
        const data = holdings.map(h => h.gain_loss_percent);
        const colors = data.map(value => value >= 0 ? this.chartColors.success : this.chartColors.danger);

        return {
            labels,
            datasets: [{
                label: 'Performance',
                data,
                backgroundColor: colors,
                borderColor: colors,
                borderWidth: 1
            }]
        };
    }

    /**
     * Create performance chart from real historical data with events
     */
    createRealPerformanceChart(canvasId, historicalData, options = {}) {
        if (!historicalData || !historicalData.labels || historicalData.labels.length === 0) {
            console.warn('No historical data available for performance chart');
            return this.generateMockPerformanceData(30);
        }

        const data = {
            labels: historicalData.labels,
            datasets: [
                {
                    label: 'Portfolio Value',
                    data: historicalData.portfolio_values,
                    borderColor: this.chartColors.primary,
                    backgroundColor: this.chartColors.primary + '20',
                    fill: true
                },
                {
                    label: 'Cost Basis',
                    data: historicalData.cost_basis_values,
                    borderColor: this.chartColors.info,
                    backgroundColor: this.chartColors.info + '20',
                    fill: false,
                    borderDash: [5, 5]
                }
            ]
        };

        // Add events as annotations if provided
        if (historicalData.events && historicalData.events.length > 0) {
            options.events = historicalData.events;
        }

        return this.createPerformanceChart(canvasId, data, options);
    }

    /**
     * Create holdings chart from real historical performance data
     */
    createRealHoldingsChart(canvasId, stockPerformanceData, options = {}) {
        if (!stockPerformanceData || stockPerformanceData.length === 0) {
            console.warn('No stock performance data available for holdings chart');
            return null;
        }

        const labels = stockPerformanceData.map(stock => stock.symbol);
        const data = stockPerformanceData.map(stock => stock.performance_percent);
        const colors = data.map(value => value >= 0 ? this.chartColors.success : this.chartColors.danger);

        const chartData = {
            labels,
            datasets: [{
                label: 'Performance (%)',
                data,
                backgroundColor: colors,
                borderColor: colors,
                borderWidth: 1
            }]
        };

        return this.createHoldingsChart(canvasId, chartData, options);
    }
    
    /**
     * Mock sector assignment (same as in main app)
     */
    getMockSector(symbol) {
        const sectorMap = {
            'A': 'Technology', 'B': 'Healthcare', 'C': 'Finance', 'D': 'Consumer',
            'E': 'Energy', 'F': 'Finance', 'G': 'Technology', 'H': 'Healthcare',
            'I': 'Industrial', 'J': 'Consumer', 'K': 'Technology', 'L': 'Healthcare',
            'M': 'Technology', 'N': 'Energy', 'O': 'Industrial', 'P': 'Healthcare',
            'Q': 'Technology', 'R': 'Consumer', 'S': 'Technology', 'T': 'Technology',
            'U': 'Utilities', 'V': 'Healthcare', 'W': 'Consumer', 'X': 'Technology',
            'Y': 'Technology', 'Z': 'Technology'
        };
        return sectorMap[symbol.charAt(0)] || 'Other';
    }
    
    /**
     * Destroy a specific chart
     */
    destroyChart(canvasId) {
        if (this.charts[canvasId]) {
            this.charts[canvasId].destroy();
            delete this.charts[canvasId];
        }
    }
    
    /**
     * Destroy all charts
     */
    destroyAllCharts() {
        Object.keys(this.charts).forEach(canvasId => {
            this.destroyChart(canvasId);
        });
    }

    /**
     * Create event annotations for Chart.js annotation plugin
     */
    createEventAnnotations(events, labels) {
        const annotations = {};

        events.forEach((event, index) => {
            const labelIndex = labels.findIndex(label => {
                const eventDate = new Date(event.date);
                const labelDate = new Date(label);
                return eventDate.toDateString() === labelDate.toDateString();
            });

            if (labelIndex !== -1) {
                annotations[`event-${index}`] = {
                    type: 'line',
                    xMin: labelIndex,
                    xMax: labelIndex,
                    borderColor: event.color,
                    borderWidth: 2,
                    borderDash: [5, 5],
                    label: {
                        content: event.icon,
                        enabled: true,
                        position: 'top'
                    }
                };
            }
        });

        return {
            annotations: annotations
        };
    }

    /**
     * Add event markers as DOM overlays
     */
    addEventMarkers(canvasId, events, labels) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        // Remove existing markers
        const existingMarkers = canvas.parentElement.querySelectorAll('.event-marker');
        existingMarkers.forEach(marker => marker.remove());

        // Create container for markers if it doesn't exist
        let markerContainer = canvas.parentElement.querySelector('.event-markers');
        if (!markerContainer) {
            markerContainer = document.createElement('div');
            markerContainer.className = 'event-markers';
            markerContainer.style.cssText = `
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                pointer-events: none;
                z-index: 10;
            `;
            canvas.parentElement.style.position = 'relative';
            canvas.parentElement.appendChild(markerContainer);
        }

        // Add markers for each event
        events.forEach((event, index) => {
            const eventDate = new Date(event.date);
            const labelIndex = labels.findIndex(label => {
                // Parse label date (format: "Jan 15" or similar)
                const currentYear = new Date().getFullYear();
                const labelDate = new Date(`${label} ${currentYear}`);
                return Math.abs(eventDate - labelDate) < 24 * 60 * 60 * 1000; // Within 1 day
            });

            if (labelIndex !== -1) {
                const marker = document.createElement('div');
                marker.className = 'event-marker';
                marker.style.cssText = `
                    position: absolute;
                    width: 24px;
                    height: 24px;
                    background: ${event.color};
                    border: 2px solid white;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 12px;
                    cursor: pointer;
                    pointer-events: auto;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
                    z-index: 20;
                `;

                marker.textContent = event.icon;
                marker.title = event.description;

                // Position the marker (approximate positioning)
                const canvasRect = canvas.getBoundingClientRect();
                const xPosition = (labelIndex / (labels.length - 1)) * canvasRect.width;
                marker.style.left = `${xPosition - 12}px`;
                marker.style.top = '10px';

                // Add click handler for event details
                marker.addEventListener('click', () => {
                    this.showEventTooltip(event, marker);
                });

                markerContainer.appendChild(marker);
            }
        });
    }

    /**
     * Show event tooltip
     */
    showEventTooltip(event, markerElement) {
        // Remove existing tooltips
        document.querySelectorAll('.event-tooltip').forEach(tooltip => tooltip.remove());

        const tooltip = document.createElement('div');
        tooltip.className = 'event-tooltip';
        tooltip.style.cssText = `
            position: absolute;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 1000;
            max-width: 250px;
            font-size: 14px;
            line-height: 1.4;
        `;

        tooltip.innerHTML = `
            <div style="font-weight: 600; margin-bottom: 4px; color: ${event.color};">
                ${event.icon} ${event.type.charAt(0).toUpperCase() + event.type.slice(1)}
            </div>
            <div style="margin-bottom: 4px;">${event.description}</div>
            <div style="font-size: 12px; color: #6b7280;">${new Date(event.date).toLocaleDateString()}</div>
        `;

        // Position tooltip
        const markerRect = markerElement.getBoundingClientRect();
        tooltip.style.left = `${markerRect.left - 100}px`;
        tooltip.style.top = `${markerRect.bottom + 5}px`;

        document.body.appendChild(tooltip);

        // Remove tooltip after 3 seconds or on click elsewhere
        setTimeout(() => tooltip.remove(), 3000);
        document.addEventListener('click', function removeTooltip(e) {
            if (!tooltip.contains(e.target) && e.target !== markerElement) {
                tooltip.remove();
                document.removeEventListener('click', removeTooltip);
            }
        });
    }
}

// Initialize charts module
window.portfolioCharts = new PortfolioCharts();
