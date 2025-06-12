// Portfolio Tracker - Frontend Application
class PortfolioApp {
    constructor() {
        this.apiBase = '/api';
        this.authToken = localStorage.getItem('auth_token');
        this.currentUser = null;
        
        this.init();
    }
    
    async init() {
        // Check if user is logged in
        if (this.authToken) {
            try {
                await this.getCurrentUser();
                this.showDashboard();
            } catch (error) {
                console.error('Auth check failed:', error);
                this.logout();
            }
        } else {
            this.showHomepage();
        }

        this.setupEventListeners();
    }
    
    setupEventListeners() {
        // Navigation
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-action]')) {
                e.preventDefault();
                e.stopPropagation();
                const action = e.target.dataset.action;
                this.handleAction(action, e.target);
            }
        });
        
        // Forms
        document.addEventListener('submit', (e) => {
            if (e.target.matches('[data-form]')) {
                e.preventDefault();
                const formType = e.target.dataset.form;
                this.handleForm(formType, e.target);
            }
        });

        // Stock search input
        document.addEventListener('input', (e) => {
            if (e.target.matches('[data-stock-search]')) {
                this.handleStockSearch(e.target);
            }
        });

        // Close stock search results when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.stock-search-results') && !e.target.matches('[data-stock-search]')) {
                this.hideAllStockSearchResults();
            }
        });

        // Handle escape key to close stock search results
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.hideAllStockSearchResults();
            }
        });
    }
    
    async handleAction(action, element) {
        switch (action) {
            case 'show-login':
                this.showLogin();
                break;
            case 'show-register':
                this.showRegister();
                break;
            case 'show-homepage':
                this.showHomepage();
                break;
            case 'logout':
                this.logout();
                break;
            case 'show-dashboard':
                this.showDashboard();
                break;
            case 'show-create-portfolio':
                this.showCreatePortfolioModal();
                break;
            case 'view-portfolio':
                const portfolioId = element.dataset.portfolioId;
                this.showPortfolioDetail(portfolioId);
                break;
            case 'close-modal':
                this.closeModal();
                break;
            case 'show-add-holding':
                const portfolioIdForHolding = element.dataset.portfolioId;
                this.showAddHoldingModal(portfolioIdForHolding);
                break;
            case 'show-add-trade':
                const portfolioIdForTrade = element.dataset.portfolioId;
                this.showAddTradeModal(portfolioIdForTrade);
                break;
            case 'show-trade-history':
                const portfolioIdForHistory = element.dataset.portfolioId;
                this.showTradeHistoryModal(portfolioIdForHistory);
                break;
            case 'show-stock-detail':
                const symbol = element.dataset.symbol;
                this.showStockDetailModal(symbol);
                break;
            case 'select-stock':
                this.selectStock(element.dataset.symbol, element.dataset.name);
                break;
            case 'refresh-portfolio':
                const portfolioIdToRefresh = element.dataset.portfolioId;
                this.refreshPortfolio(portfolioIdToRefresh);
                break;
            case 'change-chart-period':
                this.changeChartPeriod(element.dataset.period);
                break;
        }
    }
    
    async handleForm(formType, form) {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        switch (formType) {
            case 'login':
                await this.login(data);
                break;
            case 'register':
                await this.register(data);
                break;
            case 'create-portfolio':
                await this.createPortfolio(data);
                break;
            case 'add-holding':
                await this.addHolding(data);
                break;
            case 'add-trade':
                await this.addTrade(data);
                break;
        }
    }
    
    async login(data) {
        try {
            this.showLoading('Signing in...');

            const response = await this.authCall('/auth/login', {
                method: 'POST',
                body: JSON.stringify({
                    identifier: data.email,
                    password: data.password
                })
            });
            
            if (response.success) {
                this.authToken = response.token;
                localStorage.setItem('auth_token', this.authToken);
                this.currentUser = response.user;
                
                this.showSuccess('Welcome back!');
                
                // Show welcome walkthrough for new users
                if (response.user.is_first_login) {
                    this.showWelcomeWalkthrough();
                } else {
                    this.showDashboard();
                }
            } else {
                this.showError(response.error || 'Login failed');
            }
        } catch (error) {
            this.showError('Login failed. Please try again.');
            console.error('Login error:', error);
        }
    }
    
    async register(data) {
        try {
            this.showLoading('Creating account...');

            const response = await this.authCall('/auth/register', {
                method: 'POST',
                body: JSON.stringify({
                    username: data.username,
                    email: data.email,
                    password: data.password,
                    first_name: data.first_name,
                    last_name: data.last_name
                })
            });
            
            if (response.success) {
                this.authToken = response.token;
                localStorage.setItem('auth_token', this.authToken);
                this.currentUser = response.user;
                
                this.showSuccess('Account created successfully!');
                this.showWelcomeWalkthrough();
            } else {
                this.showError(response.error || 'Registration failed');
            }
        } catch (error) {
            this.showError('Registration failed. Please try again.');
            console.error('Registration error:', error);
        }
    }
    
    async getCurrentUser() {
        const response = await this.apiCall('/auth/me');
        if (response.success) {
            this.currentUser = response.user;
            return this.currentUser;
        }
        throw new Error('Failed to get current user');
    }
    
    logout() {
        this.authToken = null;
        this.currentUser = null;
        localStorage.removeItem('auth_token');
        this.showHomepage();
    }

    async createPortfolio(data) {
        try {
            this.showLoading('Creating portfolio...');

            const response = await this.apiCall('/portfolios', {
                method: 'POST',
                body: JSON.stringify(data)
            });

            if (response.success) {
                this.showSuccess('Portfolio created successfully!');
                this.closeModal();
                this.showDashboard(); // Refresh dashboard
            } else {
                this.showError(response.error || 'Failed to create portfolio');
            }
        } catch (error) {
            this.showError('Failed to create portfolio. Please try again.');
            console.error('Create portfolio error:', error);
        }
    }

    async addHolding(data) {
        try {
            this.showLoading('Adding holding...');

            const response = await this.apiCall(`/portfolios/${data.portfolio_id}/holdings`, {
                method: 'POST',
                body: JSON.stringify({
                    stock_symbol: data.stock_symbol.toUpperCase(),
                    quantity: parseFloat(data.quantity),
                    avg_cost_basis: parseFloat(data.avg_cost_basis),
                    notes: data.notes
                })
            });

            if (response.success) {
                this.showSuccess('Holding added successfully!');
                this.closeModal();
                this.showPortfolioDetail(data.portfolio_id); // Refresh portfolio view
            } else {
                this.showError(response.error || 'Failed to add holding');
            }
        } catch (error) {
            this.showError('Failed to add holding. Please try again.');
            console.error('Add holding error:', error);
        }
    }

    async addTrade(data) {
        try {
            this.showLoading('Recording trade...');

            const tradeData = {
                stock_symbol: data.stock_symbol.toUpperCase(),
                transaction_type: data.transaction_type,
                quantity: parseFloat(data.quantity),
                price: parseFloat(data.price),
                fees: parseFloat(data.fees || 0),
                transaction_date: data.transaction_date,
                notes: data.notes
            };



            const response = await this.apiCall(`/portfolios/${data.portfolio_id}/transactions`, {
                method: 'POST',
                body: JSON.stringify(tradeData)
            });

            if (response.success) {
                this.showSuccess(`${data.transaction_type === 'buy' ? 'Buy' : 'Sell'} trade recorded successfully!`);
                this.closeModal();
                this.showPortfolioDetail(data.portfolio_id); // Refresh portfolio view
            } else {
                this.showError(response.error || 'Failed to record trade');
            }
        } catch (error) {
            this.showError('Failed to record trade. Please try again.');
            console.error('Add trade error:', error);
        }
    }

    async showTradeHistoryModal(portfolioId) {
        try {
            this.showLoading('Loading trade history...');

            const response = await this.apiCall(`/portfolios/${portfolioId}/transactions`);
            this.showModal(this.getTradeHistoryModalHTML(response.transactions || [], portfolioId));
        } catch (error) {
            this.showError('Failed to load trade history');
            console.error('Trade history error:', error);
        }
    }

    async handleStockSearch(input) {
        const query = input.value.trim();
        const resultsContainer = input.parentElement.querySelector('.stock-search-results');

        if (query.length < 1) {
            if (resultsContainer) {
                resultsContainer.style.display = 'none';
            }
            return;
        }

        try {
            // Debounce the search
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(async () => {
                const results = await this.searchStocks(query);
                this.displayStockSearchResults(results, resultsContainer, input);
            }, 300);
        } catch (error) {
            console.error('Stock search error:', error);
            if (resultsContainer) {
                resultsContainer.style.display = 'none';
            }
        }
    }

    async searchStocks(query) {
        try {
            const response = await this.apiCall(`/stocks/search?q=${encodeURIComponent(query)}&limit=8`);
            return response.results || [];
        } catch (error) {
            console.error('Search stocks error:', error);
            return [];
        }
    }

    displayStockSearchResults(results, container, input) {
        if (!container) return;

        if (results.length === 0) {
            container.innerHTML = '<div class="stock-search-item">No stocks found</div>';
            container.style.display = 'block';
            return;
        }

        container.innerHTML = results.map(stock => `
            <div class="stock-search-item" data-action="select-stock" data-symbol="${stock.symbol}" data-name="${stock.name}">
                <div class="flex justify-between items-center">
                    <div>
                        <div style="font-weight: 600; color: var(--gray-900);">${stock.symbol}</div>
                        <div style="font-size: var(--font-size-sm); color: var(--gray-600);">${stock.name}</div>
                    </div>
                    <div style="font-size: var(--font-size-sm); color: var(--gray-500);">${stock.exchange || ''}</div>
                </div>
            </div>
        `).join('');

        container.style.display = 'block';
    }

    selectStock(symbol, name) {
        // Find the stock search input that has visible results
        const visibleResultsContainer = document.querySelector('.stock-search-results[style*="block"]');
        if (!visibleResultsContainer) return;

        const activeInput = visibleResultsContainer.parentElement.querySelector('[data-stock-search]');
        if (activeInput) {
            activeInput.value = symbol;
            activeInput.dataset.selectedSymbol = symbol;
            activeInput.dataset.selectedName = name;

            // Hide search results
            visibleResultsContainer.style.display = 'none';

            // Trigger change event
            activeInput.dispatchEvent(new Event('change'));

            // Focus back on the input
            activeInput.focus();
        }
    }

    hideAllStockSearchResults() {
        const allResultsContainers = document.querySelectorAll('.stock-search-results');
        allResultsContainers.forEach(container => {
            container.style.display = 'none';
        });
    }

    async showStockDetailModal(symbol) {
        try {
            this.showLoading('Loading stock information...');

            const stockData = await this.apiCall(`/stocks/${symbol}`);
            this.showModal(this.getStockDetailModalHTML(stockData));
        } catch (error) {
            this.showError('Failed to load stock information');
            console.error('Stock detail error:', error);
        }
    }

    async refreshPortfolio(portfolioId) {
        try {
            // Get all stock symbols in the portfolio
            const portfolio = await this.apiCall(`/portfolios/${portfolioId}`);
            const symbols = portfolio.holdings.map(h => h.symbol);

            if (symbols.length > 0) {
                this.showLoading('Updating stock prices...');

                // Update quotes for all stocks in the portfolio
                await this.apiCall('/stocks/quotes', {
                    method: 'POST',
                    body: JSON.stringify({ symbols })
                });

                this.showSuccess('Portfolio updated with latest prices!');

                // Refresh the portfolio view
                this.showPortfolioDetail(portfolioId);
            } else {
                this.showSuccess('Portfolio is up to date!');
            }
        } catch (error) {
            this.showError('Failed to refresh portfolio');
            console.error('Refresh portfolio error:', error);
        }
    }

    changeChartPeriod(period) {
        // Update active period button
        document.querySelectorAll('[data-action="change-chart-period"]').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector(`[data-period="${period}"]`).classList.add('active');

        // Update charts with new period
        this.updatePortfolioCharts(period);
    }

    updatePortfolioCharts(period = '1M') {
        const days = this.getPeriodDays(period);

        // Update performance chart
        const performanceData = window.portfolioCharts.generateMockPerformanceData(days);
        window.portfolioCharts.createPerformanceChart('performanceChart', performanceData);

        this.showSuccess(`Charts updated for ${period} period`);
    }

    getPeriodDays(period) {
        const periodMap = {
            '1D': 1,
            '1W': 7,
            '1M': 30,
            '3M': 90,
            '1Y': 365
        };
        return periodMap[period] || 30;
    }
    
    async apiCall(endpoint, options = {}) {
        const url = this.apiBase + endpoint;
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                ...(this.authToken && { 'Authorization': `Bearer ${this.authToken}` })
            }
        };

        const response = await fetch(url, { ...defaultOptions, ...options });

        if (!response.ok) {
            // Try to get error details from response
            let errorMessage = `HTTP error! status: ${response.status}`;
            try {
                const errorData = await response.json();
                if (errorData.error) {
                    errorMessage = errorData.error;
                }
            } catch (e) {
                // If we can't parse JSON, use the status text
                errorMessage = response.statusText || errorMessage;
            }
            throw new Error(errorMessage);
        }

        return await response.json();
    }

    async authCall(endpoint, options = {}) {
        const url = endpoint; // Auth endpoints don't need /api prefix
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json'
            }
        };

        const response = await fetch(url, { ...defaultOptions, ...options });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    }
    
    showHomepage() {
        document.getElementById('app').innerHTML = this.getHomepageHTML();
    }
    
    showLogin() {
        document.getElementById('app').innerHTML = this.getLoginHTML();
    }
    
    showRegister() {
        document.getElementById('app').innerHTML = this.getRegisterHTML();
    }
    
    async showDashboard() {
        try {
            this.showLoading('Loading your portfolios...');
            
            const portfolios = await this.apiCall('/portfolios');
            document.getElementById('app').innerHTML = this.getDashboardHTML(portfolios);

            // Initialize dashboard charts if there are portfolios
            if (portfolios.portfolios && portfolios.portfolios.length > 0) {
                setTimeout(() => {
                    this.initializeDashboardCharts(portfolios.portfolios);
                }, 100);
            }
        } catch (error) {
            this.showError('Failed to load dashboard');
            console.error('Dashboard error:', error);
        }
    }
    
    showWelcomeWalkthrough() {
        document.getElementById('app').innerHTML = this.getWelcomeWalkthroughHTML();
    }

    async showPortfolioDetail(portfolioId) {
        try {
            this.showLoading('Loading portfolio details...');

            const portfolio = await this.apiCall(`/portfolios/${portfolioId}`);
            document.getElementById('app').innerHTML = this.getPortfolioDetailHTML(portfolio);

            // Initialize charts after DOM is ready
            setTimeout(() => {
                this.initializePortfolioCharts(portfolio);
            }, 100);
        } catch (error) {
            this.showError('Failed to load portfolio details');
            console.error('Portfolio detail error:', error);
        }
    }

    initializePortfolioCharts(portfolioData) {
        const holdings = portfolioData.holdings || [];

        if (holdings.length === 0) return;

        // Performance Chart
        const performanceData = window.portfolioCharts.generateMockPerformanceData(30);
        window.portfolioCharts.createPerformanceChart('performanceChart', performanceData);

        // Sector Chart
        const sectorData = window.portfolioCharts.generateSectorData(holdings);
        window.portfolioCharts.createSectorChart('sectorChart', sectorData);

        // Holdings Performance Chart
        const holdingsData = window.portfolioCharts.generateHoldingsData(holdings);
        window.portfolioCharts.createHoldingsChart('holdingsChart', holdingsData);
    }

    initializeDashboardCharts(portfolios) {
        // Check if portfolios have any value
        const totalValue = portfolios.reduce((sum, p) => sum + (p.total_value || 0), 0);

        if (totalValue === 0) {
            // Show empty state for charts
            this.showEmptyChartState('dashboardOverviewChart', 'No portfolio data yet');
            this.showEmptyChartState('portfolioAllocationChart', 'Create portfolios and add holdings to see allocation');
            return;
        }

        // Create overview performance chart
        const overviewData = this.generateDashboardOverviewData(portfolios);
        window.portfolioCharts.createPerformanceChart('dashboardOverviewChart', overviewData);

        // Create portfolio allocation chart
        const allocationData = this.generatePortfolioAllocationData(portfolios);
        window.portfolioCharts.createSectorChart('portfolioAllocationChart', allocationData);
    }

    generateDashboardOverviewData(portfolios) {
        // Generate mock data for total portfolio value over time
        const totalValue = portfolios.reduce((sum, p) => sum + p.total_value, 0);
        return window.portfolioCharts.generateMockPerformanceData(30);
    }

    generatePortfolioAllocationData(portfolios) {
        // Filter out portfolios with zero value
        const portfoliosWithValue = portfolios.filter(p => (p.total_value || 0) > 0);

        if (portfoliosWithValue.length === 0) {
            // Return empty data for empty state
            return {
                labels: ['No Data'],
                datasets: [{
                    data: [1],
                    backgroundColor: ['#e5e7eb'],
                    borderColor: ['#d1d5db'],
                    borderWidth: 2
                }]
            };
        }

        const labels = portfoliosWithValue.map(p => p.name);
        const data = portfoliosWithValue.map(p => p.total_value || 0);
        const colors = portfoliosWithValue.map((_, index) => window.portfolioCharts.sectorColors[index % window.portfolioCharts.sectorColors.length]);

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

    showEmptyChartState(canvasId, message) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        const container = canvas.parentElement;
        container.innerHTML = `
            <div class="chart-loading">
                <div class="text-center">
                    <div style="font-size: 2rem; margin-bottom: var(--space-3); opacity: 0.3;">📊</div>
                    <div style="color: var(--gray-500); font-size: var(--font-size-sm);">${message}</div>
                </div>
            </div>
        `;
    }

    showCreatePortfolioModal() {
        this.showModal(this.getCreatePortfolioModalHTML());
    }

    showAddHoldingModal(portfolioId) {
        this.showModal(this.getAddHoldingModalHTML(portfolioId));
    }

    showAddTradeModal(portfolioId) {
        this.showModal(this.getAddTradeModalHTML(portfolioId));
    }

    showModal(content) {
        const modal = document.createElement('div');
        modal.id = 'modal';
        modal.className = 'modal-overlay';
        modal.innerHTML = content;
        document.body.appendChild(modal);

        // Add click outside to close
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.closeModal();
            }
        });

        // Add escape key to close
        document.addEventListener('keydown', this.handleEscapeKey.bind(this));
    }

    closeModal() {
        const modal = document.getElementById('modal');
        if (modal) {
            modal.remove();
        }
        document.removeEventListener('keydown', this.handleEscapeKey.bind(this));
    }

    handleEscapeKey(e) {
        if (e.key === 'Escape') {
            this.closeModal();
        }
    }
    
    showLoading(message = 'Loading...') {
        document.getElementById('app').innerHTML = `
            <div class="container py-20">
                <div class="flex flex-col items-center justify-center">
                    <div class="spinner mb-4"></div>
                    <p class="text-muted">${message}</p>
                </div>
            </div>
        `;
    }
    
    showError(message) {
        this.showNotification(message, 'error');
    }
    
    showSuccess(message) {
        this.showNotification(message, 'success');
    }
    
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="container">
                <div class="card" style="position: fixed; top: 20px; right: 20px; z-index: 1000; max-width: 400px;">
                    <div class="flex items-center gap-4">
                        <div class="flex-1">
                            <p class="mb-0 ${type === 'error' ? 'text-danger' : type === 'success' ? 'text-success' : ''}">${message}</p>
                        </div>
                        <button onclick="this.parentElement.parentElement.parentElement.remove()" class="btn btn-secondary" style="padding: 0.25rem 0.5rem;">×</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }
    
    getHomepageHTML() {
        return `
            <div class="homepage">
                <!-- Hero Section -->
                <section class="py-20">
                    <div class="container">
                        <div class="grid grid-cols-1 gap-12 items-center" style="grid-template-columns: 1fr 1fr;">
                            <div class="fade-in">
                                <h1 class="mb-6">Track Your Investment Portfolio with Confidence</h1>
                                <p class="mb-8" style="font-size: 1.125rem; color: var(--gray-600);">
                                    Professional portfolio tracking with real-time stock data, 
                                    performance analytics, and intelligent insights to help you 
                                    make better investment decisions.
                                </p>
                                <div class="flex gap-4">
                                    <button data-action="show-register" class="btn btn-primary btn-lg">
                                        Get Started Free
                                    </button>
                                    <button data-action="show-login" class="btn btn-secondary btn-lg">
                                        Sign In
                                    </button>
                                </div>
                            </div>
                            <div class="fade-in" style="animation-delay: 0.2s;">
                                <div class="card card-lg">
                                    <h3 class="mb-4">Portfolio Overview</h3>
                                    <div class="grid grid-cols-2 gap-4 mb-6">
                                        <div class="text-center">
                                            <div style="font-size: 1.5rem; font-weight: 600; color: var(--success-green);">+12.4%</div>
                                            <div class="text-muted" style="font-size: 0.875rem;">Total Return</div>
                                        </div>
                                        <div class="text-center">
                                            <div style="font-size: 1.5rem; font-weight: 600; color: var(--gray-900);">$127,450</div>
                                            <div class="text-muted" style="font-size: 0.875rem;">Portfolio Value</div>
                                        </div>
                                    </div>
                                    <div class="text-muted" style="font-size: 0.875rem; text-align: center;">
                                        Real-time tracking • Smart analytics • Secure & private
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
                
                <!-- Features Section -->
                <section class="py-16" style="background: white;">
                    <div class="container">
                        <div class="text-center mb-12">
                            <h2>Everything you need to track your investments</h2>
                            <p class="text-muted">Professional-grade tools for individual investors</p>
                        </div>
                        
                        <div class="grid grid-cols-3 gap-8">
                            <div class="card text-center fade-in">
                                <div style="width: 48px; height: 48px; background: var(--primary-blue); border-radius: var(--radius-lg); margin: 0 auto var(--space-4); display: flex; align-items: center; justify-content: center;">
                                    <span style="color: white; font-size: 1.5rem;">📊</span>
                                </div>
                                <h4>Real-time Data</h4>
                                <p class="text-muted">Live stock prices and market data updated throughout the trading day.</p>
                            </div>
                            
                            <div class="card text-center fade-in" style="animation-delay: 0.1s;">
                                <div style="width: 48px; height: 48px; background: var(--success-green); border-radius: var(--radius-lg); margin: 0 auto var(--space-4); display: flex; align-items: center; justify-content: center;">
                                    <span style="color: white; font-size: 1.5rem;">📈</span>
                                </div>
                                <h4>Performance Analytics</h4>
                                <p class="text-muted">Detailed insights into your portfolio performance and investment returns.</p>
                            </div>
                            
                            <div class="card text-center fade-in" style="animation-delay: 0.2s;">
                                <div style="width: 48px; height: 48px; background: var(--warning-yellow); border-radius: var(--radius-lg); margin: 0 auto var(--space-4); display: flex; align-items: center; justify-content: center;">
                                    <span style="color: white; font-size: 1.5rem;">🔒</span>
                                </div>
                                <h4>Secure & Private</h4>
                                <p class="text-muted">Your financial data is encrypted and never shared with third parties.</p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        `;
    }
    
    getLoginHTML() {
        return `
            <div class="container-sm py-20">
                <div class="card card-lg fade-in">
                    <div class="text-center mb-8">
                        <h2>Welcome Back</h2>
                        <p class="text-muted">Sign in to your portfolio dashboard</p>
                    </div>

                    <form data-form="login">
                        <div class="form-group">
                            <label class="form-label" for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-input" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-input" required>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-bottom: var(--space-4);">
                            Sign In
                        </button>
                    </form>

                    <div class="text-center">
                        <p class="text-muted">
                            Don't have an account?
                            <a href="#" data-action="show-register">Create one here</a>
                        </p>
                        <p class="text-muted">
                            <a href="#" data-action="show-homepage">← Back to homepage</a>
                        </p>
                    </div>
                </div>
            </div>
        `;
    }

    getRegisterHTML() {
        return `
            <div class="container-sm py-20">
                <div class="card card-lg fade-in">
                    <div class="text-center mb-8">
                        <h2>Create Your Account</h2>
                        <p class="text-muted">Start tracking your investments today</p>
                    </div>

                    <form data-form="register">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="form-label" for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" class="form-input" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name" class="form-input" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="username">Username</label>
                            <input type="text" id="username" name="username" class="form-input" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-input" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-input" required>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-bottom: var(--space-4);">
                            Create Account
                        </button>
                    </form>

                    <div class="text-center">
                        <p class="text-muted">
                            Already have an account?
                            <a href="#" data-action="show-login">Sign in here</a>
                        </p>
                        <p class="text-muted">
                            <a href="#" data-action="show-homepage">← Back to homepage</a>
                        </p>
                    </div>
                </div>
            </div>
        `;
    }

    getWelcomeWalkthroughHTML() {
        return `
            <div class="container py-20">
                <div class="card card-lg text-center fade-in" style="max-width: 600px; margin: 0 auto;">
                    <div style="width: 64px; height: 64px; background: var(--success-green); border-radius: var(--radius-full); margin: 0 auto var(--space-6); display: flex; align-items: center; justify-content: center;">
                        <span style="color: white; font-size: 2rem;">🎉</span>
                    </div>

                    <h2>Welcome to Portfolio Tracker!</h2>
                    <p class="mb-8" style="font-size: 1.125rem; color: var(--gray-600);">
                        You're all set! Let's get you started with tracking your first portfolio.
                    </p>

                    <div class="grid grid-cols-1 gap-6 mb-8 text-left">
                        <div class="flex gap-4">
                            <div style="width: 32px; height: 32px; background: var(--primary-blue); border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <span style="color: white; font-weight: 600; font-size: 0.875rem;">1</span>
                            </div>
                            <div>
                                <h5 style="margin-bottom: var(--space-2);">Create Your First Portfolio</h5>
                                <p class="text-muted" style="margin-bottom: 0;">Organize your investments by creating portfolios for different goals.</p>
                            </div>
                        </div>

                        <div class="flex gap-4">
                            <div style="width: 32px; height: 32px; background: var(--primary-blue); border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <span style="color: white; font-weight: 600; font-size: 0.875rem;">2</span>
                            </div>
                            <div>
                                <h5 style="margin-bottom: var(--space-2);">Add Your Holdings</h5>
                                <p class="text-muted" style="margin-bottom: 0;">Search for stocks and add them to track your investments.</p>
                            </div>
                        </div>

                        <div class="flex gap-4">
                            <div style="width: 32px; height: 32px; background: var(--primary-blue); border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <span style="color: white; font-weight: 600; font-size: 0.875rem;">3</span>
                            </div>
                            <div>
                                <h5 style="margin-bottom: var(--space-2);">Track Performance</h5>
                                <p class="text-muted" style="margin-bottom: 0;">Monitor real-time performance and gain insights into your investments.</p>
                            </div>
                        </div>
                    </div>

                    <button data-action="show-dashboard" class="btn btn-primary btn-lg">
                        Go to Dashboard
                    </button>
                </div>
            </div>
        `;
    }

    getDashboardHTML(portfoliosData) {
        const portfolios = portfoliosData.portfolios || [];

        return `
            <div class="dashboard">
                <!-- Header -->
                <header style="background: white; border-bottom: 1px solid var(--gray-200); padding: var(--space-4) 0;">
                    <div class="container">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 style="margin-bottom: 0;">Portfolio Dashboard</h3>
                                <p class="text-muted" style="margin-bottom: 0;">Welcome back, ${this.currentUser?.first_name || 'Investor'}!</p>
                            </div>
                            <div class="flex gap-4">
                                <button data-action="show-create-portfolio" class="btn btn-primary">+ New Portfolio</button>
                                <button data-action="logout" class="btn btn-secondary">Sign Out</button>
                            </div>
                        </div>
                    </div>
                </header>

                <!-- Main Content -->
                <main class="py-8">
                    <div class="container">
                        ${portfolios.length === 0 ? this.getEmptyStateHTML() : this.getDashboardContentHTML(portfolios)}
                    </div>
                </main>
            </div>
        `;
    }

    getDashboardContentHTML(portfolios) {
        const hasHoldings = portfolios.some(p => (p.total_value || 0) > 0);

        return `
            <!-- Dashboard Overview Charts -->
            <section class="mb-8">
                ${hasHoldings ? `
                    <div class="grid grid-cols-2 gap-6">
                        <!-- Total Portfolio Performance -->
                        <div class="card">
                            <h3 class="mb-4">Total Portfolio Performance</h3>
                            <div style="height: 250px; position: relative;">
                                <canvas id="dashboardOverviewChart"></canvas>
                            </div>
                        </div>

                        <!-- Portfolio Allocation -->
                        <div class="card">
                            <h3 class="mb-4">Portfolio Allocation</h3>
                            <div style="height: 250px; position: relative;">
                                <canvas id="portfolioAllocationChart"></canvas>
                            </div>
                        </div>
                    </div>
                ` : `
                    <div class="card text-center py-12">
                        <div style="font-size: 3rem; margin-bottom: var(--space-4); opacity: 0.3;">📊</div>
                        <h3 class="mb-4">Start Building Your Portfolio</h3>
                        <p class="text-muted mb-6">Add holdings to your portfolios to see performance charts and analytics.</p>
                        <div class="flex gap-4 justify-center">
                            <button data-action="show-create-portfolio" class="btn btn-primary">Create Portfolio</button>
                        </div>
                    </div>
                `}
            </section>

            <!-- Portfolios Grid -->
            ${this.getPortfoliosGridHTML(portfolios)}
        `;
    }

    getEmptyStateHTML() {
        return `
            <div class="text-center py-16">
                <div style="width: 64px; height: 64px; background: var(--gray-100); border-radius: var(--radius-full); margin: 0 auto var(--space-6); display: flex; align-items: center; justify-content: center;">
                    <span style="color: var(--gray-400); font-size: 2rem;">📊</span>
                </div>
                <h3>No Portfolios Yet</h3>
                <p class="text-muted mb-8">Create your first portfolio to start tracking your investments.</p>
                <button data-action="show-create-portfolio" class="btn btn-primary btn-lg">Create Your First Portfolio</button>
            </div>
        `;
    }

    getPortfoliosGridHTML(portfolios) {
        return `
            <div class="grid grid-cols-3 gap-6">
                ${portfolios.map(portfolio => `
                    <div class="card fade-in">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h4 style="margin-bottom: var(--space-2);">${portfolio.name}</h4>
                                <p class="text-muted" style="margin-bottom: 0; font-size: var(--font-size-sm);">${portfolio.holdings_count} holdings</p>
                            </div>
                            <span class="text-muted" style="font-size: var(--font-size-sm);">${portfolio.type}</span>
                        </div>

                        <div class="mb-4">
                            <div style="font-size: var(--font-size-2xl); font-weight: 600; margin-bottom: var(--space-2);">
                                $${this.formatNumber(portfolio.total_value)}
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted" style="font-size: var(--font-size-sm);">Total Value</span>
                                <span class="${portfolio.total_gain_loss >= 0 ? 'text-success' : 'text-danger'}" style="font-size: var(--font-size-sm); font-weight: 500;">
                                    ${portfolio.total_gain_loss >= 0 ? '+' : ''}${this.formatNumber(portfolio.total_gain_loss)} (${portfolio.total_gain_loss_percent.toFixed(2)}%)
                                </span>
                            </div>
                        </div>

                        <button data-action="view-portfolio" data-portfolio-id="${portfolio.id}" class="btn btn-secondary" style="width: 100%;">View Details</button>
                    </div>
                `).join('')}
            </div>
        `;
    }

    formatNumber(num) {
        return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(num);
    }

    getCreatePortfolioModalHTML() {
        return `
            <div class="modal-content">
                <div class="card card-lg" style="max-width: 500px; margin: 0 auto;">
                    <div class="flex justify-between items-center mb-6">
                        <h3 style="margin-bottom: 0;">Create New Portfolio</h3>
                        <button data-action="close-modal" class="btn btn-secondary" style="padding: 0.25rem 0.5rem;">×</button>
                    </div>

                    <form data-form="create-portfolio">
                        <div class="form-group">
                            <label class="form-label" for="name">Portfolio Name</label>
                            <input type="text" id="name" name="name" class="form-input" placeholder="e.g., Retirement Portfolio" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="description">Description (Optional)</label>
                            <textarea id="description" name="description" class="form-input" rows="3" placeholder="Brief description of this portfolio's purpose"></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="form-label" for="portfolio_type">Portfolio Type</label>
                                <select id="portfolio_type" name="portfolio_type" class="form-input" required>
                                    <option value="personal">Personal</option>
                                    <option value="retirement">Retirement</option>
                                    <option value="trading">Trading</option>
                                    <option value="savings">Savings</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="currency">Currency</label>
                                <select id="currency" name="currency" class="form-input" required>
                                    <option value="USD">USD - US Dollar</option>
                                    <option value="EUR">EUR - Euro</option>
                                    <option value="GBP">GBP - British Pound</option>
                                    <option value="CAD">CAD - Canadian Dollar</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <input type="checkbox" name="is_public" value="1" style="margin-right: var(--space-2);">
                                Make this portfolio public (visible to others)
                            </label>
                        </div>

                        <div class="flex gap-4">
                            <button type="submit" class="btn btn-primary btn-lg" style="flex: 1;">Create Portfolio</button>
                            <button type="button" data-action="close-modal" class="btn btn-secondary btn-lg">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
    }

    getAddHoldingModalHTML(portfolioId) {
        return `
            <div class="modal-content">
                <div class="card card-lg" style="max-width: 500px; margin: 0 auto;">
                    <div class="flex justify-between items-center mb-6">
                        <h3 style="margin-bottom: 0;">Add Stock Holding</h3>
                        <button data-action="close-modal" class="btn btn-secondary" style="padding: 0.25rem 0.5rem;">×</button>
                    </div>

                    <form data-form="add-holding">
                        <input type="hidden" name="portfolio_id" value="${portfolioId}">

                        <div class="form-group">
                            <label class="form-label" for="stock_symbol">Stock Symbol</label>
                            <div style="position: relative;">
                                <input type="text" id="stock_symbol" name="stock_symbol" class="form-input" placeholder="Search for a stock..." data-stock-search autocomplete="off" required>
                                <div class="stock-search-results" style="display: none;"></div>
                            </div>
                            <small class="text-muted">Start typing to search for stocks (e.g., "Apple" or "AAPL")</small>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="form-label" for="quantity">Quantity</label>
                                <input type="number" id="quantity" name="quantity" class="form-input" step="0.000001" min="0" placeholder="10" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="avg_cost_basis">Average Cost per Share</label>
                                <input type="number" id="avg_cost_basis" name="avg_cost_basis" class="form-input" step="0.01" min="0" placeholder="150.00" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="notes">Notes (Optional)</label>
                            <textarea id="notes" name="notes" class="form-input" rows="2" placeholder="Any additional notes about this holding"></textarea>
                        </div>

                        <div class="flex gap-4">
                            <button type="submit" class="btn btn-primary btn-lg" style="flex: 1;">Add Holding</button>
                            <button type="button" data-action="close-modal" class="btn btn-secondary btn-lg">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
    }

    getAddTradeModalHTML(portfolioId) {
        const today = new Date().toISOString().split('T')[0];

        return `
            <div class="modal-content">
                <div class="card card-lg" style="max-width: 500px; margin: 0 auto;">
                    <div class="flex justify-between items-center mb-6">
                        <h3 style="margin-bottom: 0;">Record Trade</h3>
                        <button data-action="close-modal" class="btn btn-secondary" style="padding: 0.25rem 0.5rem;">×</button>
                    </div>

                    <form data-form="add-trade">
                        <input type="hidden" name="portfolio_id" value="${portfolioId}">

                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="form-label" for="stock_symbol">Stock Symbol</label>
                                <div style="position: relative;">
                                    <input type="text" id="stock_symbol" name="stock_symbol" class="form-input" placeholder="Search for a stock..." data-stock-search autocomplete="off" required>
                                    <div class="stock-search-results" style="display: none;"></div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="transaction_type">Trade Type</label>
                                <select id="transaction_type" name="transaction_type" class="form-input" required>
                                    <option value="buy">Buy</option>
                                    <option value="sell">Sell</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div class="form-group">
                                <label class="form-label" for="quantity">Shares</label>
                                <input type="number" id="quantity" name="quantity" class="form-input" step="0.000001" min="0.000001" placeholder="10" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="price">Price per Share</label>
                                <input type="number" id="price" name="price" class="form-input" step="0.01" min="0.01" placeholder="150.00" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="fees">Commission/Fees</label>
                                <input type="number" id="fees" name="fees" class="form-input" step="0.01" min="0" placeholder="0.00" value="0">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="transaction_date">Trade Date</label>
                            <input type="date" id="transaction_date" name="transaction_date" class="form-input" value="${today}" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="notes">Notes (Optional)</label>
                            <textarea id="notes" name="notes" class="form-input" rows="2" placeholder="Any notes about this trade (e.g., strategy, market conditions)"></textarea>
                        </div>

                        <div class="alert alert-info mb-4" style="background: var(--primary-blue-bg); border: 1px solid var(--primary-blue-light); border-radius: var(--radius-md); padding: var(--space-3);">
                            <small style="color: var(--primary-blue);">
                                💡 <strong>Trade-based tracking:</strong> Your holdings are automatically calculated from all your buy/sell trades using FIFO cost basis.
                            </small>
                        </div>

                        <div class="flex gap-4">
                            <button type="submit" class="btn btn-primary btn-lg" style="flex: 1;">Record Trade</button>
                            <button type="button" data-action="close-modal" class="btn btn-secondary btn-lg">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
    }

    getPortfolioDetailHTML(portfolioData) {
        const portfolio = portfolioData.portfolio;
        const performance = portfolioData.performance;
        const holdings = portfolioData.holdings || [];

        return `
            <div class="portfolio-detail">
                <!-- Header -->
                <header style="background: white; border-bottom: 1px solid var(--gray-200); padding: var(--space-4) 0;">
                    <div class="container">
                        <div class="flex justify-between items-center">
                            <div>
                                <div class="flex items-center gap-4 mb-2">
                                    <button data-action="show-dashboard" class="btn btn-secondary" style="padding: 0.5rem;">← Back</button>
                                    <h2 style="margin-bottom: 0;">${portfolio.name}</h2>
                                    <span class="text-muted" style="font-size: var(--font-size-sm); text-transform: capitalize;">${portfolio.type}</span>
                                </div>
                                <p class="text-muted" style="margin-bottom: 0;">${holdings.length} holdings • ${portfolio.currency}</p>
                            </div>
                            <div class="flex gap-4">
                                <button data-action="refresh-portfolio" data-portfolio-id="${portfolio.id}" class="btn btn-secondary" title="Refresh stock prices">
                                    🔄 Refresh
                                </button>
                                <button data-action="show-add-trade" data-portfolio-id="${portfolio.id}" class="btn btn-primary">+ Add Trade</button>
                                <button data-action="show-trade-history" data-portfolio-id="${portfolio.id}" class="btn btn-secondary">📈 Trade History</button>
                            </div>
                        </div>
                    </div>
                </header>

                <!-- Performance Summary -->
                <section class="py-8" style="background: white;">
                    <div class="container">
                        <div class="grid grid-cols-4 gap-6">
                            <div class="card text-center">
                                <div style="font-size: var(--font-size-3xl); font-weight: 600; margin-bottom: var(--space-2);">
                                    $${this.formatNumber(performance.total_value)}
                                </div>
                                <div class="text-muted">Total Value</div>
                            </div>

                            <div class="card text-center">
                                <div style="font-size: var(--font-size-3xl); font-weight: 600; margin-bottom: var(--space-2);">
                                    $${this.formatNumber(performance.total_cost_basis)}
                                </div>
                                <div class="text-muted">Cost Basis</div>
                            </div>

                            <div class="card text-center">
                                <div style="font-size: var(--font-size-3xl); font-weight: 600; margin-bottom: var(--space-2); color: ${performance.total_gain_loss >= 0 ? 'var(--success-green)' : 'var(--danger-red)'};">
                                    ${performance.total_gain_loss >= 0 ? '+' : ''}$${this.formatNumber(performance.total_gain_loss)}
                                </div>
                                <div class="text-muted">Total Gain/Loss</div>
                            </div>

                            <div class="card text-center">
                                <div style="font-size: var(--font-size-3xl); font-weight: 600; margin-bottom: var(--space-2); color: ${performance.total_gain_loss_percent >= 0 ? 'var(--success-green)' : 'var(--danger-red)'};">
                                    ${performance.total_gain_loss_percent >= 0 ? '+' : ''}${performance.total_gain_loss_percent.toFixed(2)}%
                                </div>
                                <div class="text-muted">Return</div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Portfolio Charts -->
                ${holdings.length > 0 ? this.getPortfolioChartsHTML(holdings, performance) : ''}

                <!-- Portfolio Analytics -->
                ${holdings.length > 0 ? this.getPortfolioAnalyticsHTML(holdings, performance) : ''}

                <!-- Holdings Table -->
                <section class="py-8">
                    <div class="container">
                        <div class="card">
                            <div class="flex justify-between items-center mb-6">
                                <h3 style="margin-bottom: 0;">Current Holdings</h3>
                                <div class="text-muted" style="font-size: var(--font-size-sm);">
                                    ${holdings.length} positions • Computed from trades
                                </div>
                            </div>

                            ${holdings.length === 0 ? this.getEmptyHoldingsHTML(portfolio.id) : this.getHoldingsTableHTML(holdings)}
                        </div>
                    </div>
                </section>
            </div>
        `;
    }

    getEmptyHoldingsHTML(portfolioId) {
        return `
            <div class="text-center py-12">
                <div style="width: 48px; height: 48px; background: var(--gray-100); border-radius: var(--radius-full); margin: 0 auto var(--space-4); display: flex; align-items: center; justify-content: center;">
                    <span style="color: var(--gray-400); font-size: 1.5rem;">📈</span>
                </div>
                <h4>No Trades Yet</h4>
                <p class="text-muted mb-6">Record your first buy or sell trade to start building this portfolio.</p>
                <div class="flex gap-4 justify-center">
                    <button data-action="show-add-trade" data-portfolio-id="${portfolioId}" class="btn btn-primary">Record Your First Trade</button>
                    <button data-action="show-trade-history" data-portfolio-id="${portfolioId}" class="btn btn-secondary">View Trade History</button>
                </div>
            </div>
        `;
    }

    getHoldingsTableHTML(holdings) {
        return `
            <div style="overflow-x: auto;">
                <table class="holdings-table">
                    <thead>
                        <tr>
                            <th style="text-align: left;">Symbol</th>
                            <th style="text-align: left;">Name</th>
                            <th style="text-align: right;">Shares</th>
                            <th style="text-align: right;">Avg Cost</th>
                            <th style="text-align: right;">Current Price</th>
                            <th style="text-align: right;">Market Value</th>
                            <th style="text-align: right;">Gain/Loss</th>
                            <th style="text-align: right;">Weight</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${holdings.map(holding => `
                            <tr>
                                <td>
                                    <div class="stock-symbol" data-action="show-stock-detail" data-symbol="${holding.symbol}">${holding.symbol}</div>
                                </td>
                                <td>
                                    <div style="color: var(--gray-700);">${holding.name || holding.symbol}</div>
                                </td>
                                <td style="text-align: right;">
                                    <div>${this.formatNumber(holding.quantity)}</div>
                                </td>
                                <td style="text-align: right;">
                                    <div>$${this.formatNumber(holding.avg_cost_basis)}</div>
                                </td>
                                <td style="text-align: right;">
                                    <div>$${this.formatNumber(holding.current_price)}</div>
                                </td>
                                <td style="text-align: right;">
                                    <div style="font-weight: 600;">$${this.formatNumber(holding.current_value)}</div>
                                </td>
                                <td style="text-align: right;">
                                    <div class="performance-indicator ${holding.gain_loss >= 0 ? 'positive' : 'negative'}">
                                        ${holding.gain_loss >= 0 ? '+' : ''}$${this.formatNumber(holding.gain_loss)}
                                    </div>
                                    <div style="font-size: var(--font-size-sm); color: ${holding.gain_loss_percent >= 0 ? 'var(--success-green)' : 'var(--danger-red)'};">
                                        ${holding.gain_loss_percent >= 0 ? '+' : ''}${holding.gain_loss_percent.toFixed(2)}%
                                    </div>
                                </td>
                                <td style="text-align: right;">
                                    <div>${holding.weight.toFixed(1)}%</div>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    getPortfolioChartsHTML(holdings, performance) {
        return `
            <section class="py-8" style="background: white;">
                <div class="container">
                    <div class="flex justify-between items-center mb-6">
                        <h3 style="margin-bottom: 0;">Portfolio Performance</h3>
                        <div class="chart-period-selector">
                            <button data-action="change-chart-period" data-period="1D" class="btn btn-secondary btn-sm">1D</button>
                            <button data-action="change-chart-period" data-period="1W" class="btn btn-secondary btn-sm">1W</button>
                            <button data-action="change-chart-period" data-period="1M" class="btn btn-secondary btn-sm active">1M</button>
                            <button data-action="change-chart-period" data-period="3M" class="btn btn-secondary btn-sm">3M</button>
                            <button data-action="change-chart-period" data-period="1Y" class="btn btn-secondary btn-sm">1Y</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-6">
                        <!-- Performance Chart -->
                        <div class="chart-container" style="grid-column: span 2;">
                            <div class="card">
                                <h4 class="mb-4">Portfolio Value Over Time</h4>
                                <div style="height: 300px; position: relative;">
                                    <canvas id="performanceChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Sector Allocation -->
                        <div class="chart-container">
                            <div class="card">
                                <h4 class="mb-4">Sector Allocation</h4>
                                <div style="height: 300px; position: relative;">
                                    <canvas id="sectorChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Holdings Performance Chart -->
                    <div class="card mt-6">
                        <h4 class="mb-4">Individual Stock Performance</h4>
                        <div style="height: 250px; position: relative;">
                            <canvas id="holdingsChart"></canvas>
                        </div>
                    </div>
                </div>
            </section>
        `;
    }

    getPortfolioAnalyticsHTML(holdings, performance) {
        // Calculate sector allocation (simplified - using first letter of symbol as mock sector)
        const sectorData = this.calculateSectorAllocation(holdings);
        const topHoldings = holdings.slice(0, 5); // Top 5 holdings by value

        return `
            <section class="py-8" style="background: var(--gray-50);">
                <div class="container">
                    <h3 class="mb-6">Portfolio Analytics</h3>

                    <div class="grid grid-cols-2 gap-6">
                        <!-- Top Holdings -->
                        <div class="analytics-card">
                            <h4 class="mb-4">Top Holdings</h4>
                            <div class="space-y-3">
                                ${topHoldings.map(holding => `
                                    <div class="flex justify-between items-center">
                                        <div class="flex items-center gap-3">
                                            <div class="stock-symbol" data-action="show-stock-detail" data-symbol="${holding.symbol}" style="font-size: var(--font-size-sm);">${holding.symbol}</div>
                                            <div style="font-size: var(--font-size-sm); color: var(--gray-600);">${holding.name || holding.symbol}</div>
                                        </div>
                                        <div class="text-right">
                                            <div style="font-weight: 600; font-size: var(--font-size-sm);">${holding.weight.toFixed(1)}%</div>
                                            <div style="font-size: var(--font-size-xs); color: var(--gray-500);">$${this.formatNumber(holding.current_value)}</div>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>

                        <!-- Performance Metrics -->
                        <div class="analytics-card">
                            <h4 class="mb-4">Performance Metrics</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="metric-card">
                                    <div class="metric-value" style="color: ${performance.total_gain_loss >= 0 ? 'var(--success-green)' : 'var(--danger-red)'};">
                                        ${performance.total_gain_loss >= 0 ? '+' : ''}${performance.total_gain_loss_percent.toFixed(2)}%
                                    </div>
                                    <div class="metric-label">Total Return</div>
                                </div>

                                <div class="metric-card">
                                    <div class="metric-value">
                                        ${this.calculateBestPerformer(holdings).symbol || 'N/A'}
                                    </div>
                                    <div class="metric-label">Best Performer</div>
                                </div>

                                <div class="metric-card">
                                    <div class="metric-value">
                                        ${holdings.length}
                                    </div>
                                    <div class="metric-label">Holdings</div>
                                </div>

                                <div class="metric-card">
                                    <div class="metric-value">
                                        ${this.calculateDiversificationScore(holdings).toFixed(1)}
                                    </div>
                                    <div class="metric-label">Diversification</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        `;
    }

    calculateSectorAllocation(holdings) {
        // Simplified sector calculation - in a real app, this would use actual sector data
        const sectors = {};
        holdings.forEach(holding => {
            const sector = this.getMockSector(holding.symbol);
            sectors[sector] = (sectors[sector] || 0) + holding.weight;
        });
        return sectors;
    }

    getMockSector(symbol) {
        // Mock sector assignment based on symbol
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

    calculateBestPerformer(holdings) {
        return holdings.reduce((best, holding) => {
            return holding.gain_loss_percent > (best.gain_loss_percent || -Infinity) ? holding : best;
        }, {});
    }

    calculateDiversificationScore(holdings) {
        // Simple diversification score based on number of holdings and weight distribution
        if (holdings.length === 0) return 0;

        const maxWeight = Math.max(...holdings.map(h => h.weight));
        const baseScore = Math.min(holdings.length * 10, 70); // Up to 70 points for number of holdings
        const concentrationPenalty = maxWeight > 50 ? (maxWeight - 50) : 0; // Penalty for concentration

        return Math.max(0, Math.min(100, baseScore - concentrationPenalty));
    }

    getTradeHistoryModalHTML(transactions, portfolioId) {
        return `
            <div class="modal-content">
                <div class="card card-lg" style="max-width: 800px; margin: 0 auto;">
                    <div class="flex justify-between items-center mb-6">
                        <h3 style="margin-bottom: 0;">Trade History</h3>
                        <div class="flex gap-2">
                            <button data-action="show-add-trade" data-portfolio-id="${portfolioId}" class="btn btn-primary btn-sm">+ Add Trade</button>
                            <button data-action="close-modal" class="btn btn-secondary" style="padding: 0.25rem 0.5rem;">×</button>
                        </div>
                    </div>

                    ${transactions.length === 0 ? `
                        <div class="text-center py-8">
                            <div style="font-size: 2rem; margin-bottom: var(--space-3); opacity: 0.3;">📈</div>
                            <h4>No Trades Yet</h4>
                            <p class="text-muted mb-4">Start by recording your first buy or sell trade.</p>
                            <button data-action="show-add-trade" data-portfolio-id="${portfolioId}" class="btn btn-primary">Record First Trade</button>
                        </div>
                    ` : `
                        <div style="max-height: 400px; overflow-y: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead style="position: sticky; top: 0; background: white; z-index: 1;">
                                    <tr style="border-bottom: 2px solid var(--gray-200);">
                                        <th style="text-align: left; padding: var(--space-3); font-weight: 600; color: var(--gray-700);">Date</th>
                                        <th style="text-align: left; padding: var(--space-3); font-weight: 600; color: var(--gray-700);">Symbol</th>
                                        <th style="text-align: center; padding: var(--space-3); font-weight: 600; color: var(--gray-700);">Type</th>
                                        <th style="text-align: right; padding: var(--space-3); font-weight: 600; color: var(--gray-700);">Shares</th>
                                        <th style="text-align: right; padding: var(--space-3); font-weight: 600; color: var(--gray-700);">Price</th>
                                        <th style="text-align: right; padding: var(--space-3); font-weight: 600; color: var(--gray-700);">Total</th>
                                        <th style="text-align: right; padding: var(--space-3); font-weight: 600; color: var(--gray-700);">Fees</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${transactions.map(trade => {
                                        const total = trade.quantity * trade.price;
                                        const tradeDate = new Date(trade.transaction_date).toLocaleDateString();
                                        const isBuy = trade.transaction_type === 'buy';

                                        return `
                                            <tr style="border-bottom: 1px solid var(--gray-100);">
                                                <td style="padding: var(--space-3);">
                                                    <div style="font-size: var(--font-size-sm);">${tradeDate}</div>
                                                </td>
                                                <td style="padding: var(--space-3);">
                                                    <div style="font-weight: 600; color: var(--primary-blue);">${trade.stock_symbol}</div>
                                                </td>
                                                <td style="padding: var(--space-3); text-align: center;">
                                                    <span class="badge ${isBuy ? 'badge-success' : 'badge-danger'}" style="text-transform: uppercase;">
                                                        ${trade.transaction_type}
                                                    </span>
                                                </td>
                                                <td style="padding: var(--space-3); text-align: right;">
                                                    <div>${this.formatNumber(trade.quantity)}</div>
                                                </td>
                                                <td style="padding: var(--space-3); text-align: right;">
                                                    <div>$${this.formatNumber(trade.price)}</div>
                                                </td>
                                                <td style="padding: var(--space-3); text-align: right;">
                                                    <div style="font-weight: 600; color: ${isBuy ? 'var(--danger-red)' : 'var(--success-green)'};">
                                                        ${isBuy ? '-' : '+'}$${this.formatNumber(total)}
                                                    </div>
                                                </td>
                                                <td style="padding: var(--space-3); text-align: right;">
                                                    <div style="color: var(--gray-600);">$${this.formatNumber(trade.fees || 0)}</div>
                                                </td>
                                            </tr>
                                        `;
                                    }).join('')}
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 pt-4" style="border-top: 1px solid var(--gray-200);">
                            <div class="grid grid-cols-3 gap-4 text-center">
                                <div>
                                    <div style="font-size: var(--font-size-lg); font-weight: 600; color: var(--success-green);">
                                        ${transactions.filter(t => t.transaction_type === 'buy').length}
                                    </div>
                                    <div style="font-size: var(--font-size-sm); color: var(--gray-600);">Buy Trades</div>
                                </div>
                                <div>
                                    <div style="font-size: var(--font-size-lg); font-weight: 600; color: var(--danger-red);">
                                        ${transactions.filter(t => t.transaction_type === 'sell').length}
                                    </div>
                                    <div style="font-size: var(--font-size-sm); color: var(--gray-600);">Sell Trades</div>
                                </div>
                                <div>
                                    <div style="font-size: var(--font-size-lg); font-weight: 600; color: var(--gray-800);">
                                        ${transactions.length}
                                    </div>
                                    <div style="font-size: var(--font-size-sm); color: var(--gray-600);">Total Trades</div>
                                </div>
                            </div>
                        </div>
                    `}
                </div>
            </div>
        `;
    }

    getStockDetailModalHTML(stockData) {
        const quote = stockData.quote;

        return `
            <div class="modal-content">
                <div class="card card-lg" style="max-width: 600px; margin: 0 auto;">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 style="margin-bottom: var(--space-2);">${stockData.symbol}</h3>
                            <p class="text-muted" style="margin-bottom: 0;">${stockData.name}</p>
                        </div>
                        <button data-action="close-modal" class="btn btn-secondary" style="padding: 0.25rem 0.5rem;">×</button>
                    </div>

                    ${quote ? `
                        <div class="grid grid-cols-2 gap-6 mb-6">
                            <div class="text-center">
                                <div style="font-size: var(--font-size-3xl); font-weight: 600; margin-bottom: var(--space-2);">
                                    ${quote.formatted_price}
                                </div>
                                <div class="text-muted">Current Price</div>
                            </div>

                            <div class="text-center">
                                <div style="font-size: var(--font-size-xl); font-weight: 600; margin-bottom: var(--space-2); color: ${quote.change_direction === 'up' ? 'var(--success-green)' : quote.change_direction === 'down' ? 'var(--danger-red)' : 'var(--gray-600)'};">
                                    ${quote.formatted_change} (${quote.formatted_change_percent})
                                </div>
                                <div class="text-muted">Today's Change</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-4 mb-6">
                            <div class="text-center p-4" style="background: var(--gray-50); border-radius: var(--radius-md);">
                                <div style="font-weight: 600; margin-bottom: var(--space-1);">${quote.formatted_volume}</div>
                                <div class="text-muted" style="font-size: var(--font-size-sm);">Volume</div>
                            </div>

                            <div class="text-center p-4" style="background: var(--gray-50); border-radius: var(--radius-md);">
                                <div style="font-weight: 600; margin-bottom: var(--space-1);">$${this.formatNumber(quote.fifty_two_week_high || 0)}</div>
                                <div class="text-muted" style="font-size: var(--font-size-sm);">52W High</div>
                            </div>

                            <div class="text-center p-4" style="background: var(--gray-50); border-radius: var(--radius-md);">
                                <div style="font-weight: 600; margin-bottom: var(--space-1);">$${this.formatNumber(quote.fifty_two_week_low || 0)}</div>
                                <div class="text-muted" style="font-size: var(--font-size-sm);">52W Low</div>
                            </div>
                        </div>

                        <div class="flex justify-between items-center mb-4">
                            <span class="text-muted">Market State:</span>
                            <span class="badge ${quote.market_state === 'REGULAR' ? 'badge-success' : 'badge-warning'}">${quote.market_state_label || quote.market_state}</span>
                        </div>

                        <div class="flex justify-between items-center">
                            <span class="text-muted">Exchange:</span>
                            <span>${stockData.exchange || 'N/A'}</span>
                        </div>
                    ` : `
                        <div class="text-center py-8">
                            <div class="text-muted">No current quote data available</div>
                        </div>
                    `}

                    <div class="flex gap-4 mt-6">
                        <button data-action="close-modal" class="btn btn-secondary btn-lg" style="flex: 1;">Close</button>
                    </div>
                </div>
            </div>
        `;
    }
}

// Initialize the app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.portfolioApp = new PortfolioApp();
});
