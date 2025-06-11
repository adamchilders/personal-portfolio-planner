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
        }
    }
    
    async login(data) {
        try {
            this.showLoading('Signing in...');
            
            const response = await this.apiCall('/auth/login', {
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
            
            const response = await this.apiCall('/auth/register', {
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
    
    async apiCall(endpoint, options = {}) {
        const url = this.apiBase + endpoint;
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                ...(this.authToken && { 'Authorization': `Bearer ${this.authToken}` })
            }
        };
        
        const response = await fetch(url, { ...defaultOptions, ...options });
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
        } catch (error) {
            this.showError('Failed to load dashboard');
            console.error('Dashboard error:', error);
        }
    }
    
    showWelcomeWalkthrough() {
        document.getElementById('app').innerHTML = this.getWelcomeWalkthroughHTML();
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
                                <button class="btn btn-primary">+ New Portfolio</button>
                                <button data-action="logout" class="btn btn-secondary">Sign Out</button>
                            </div>
                        </div>
                    </div>
                </header>

                <!-- Main Content -->
                <main class="py-8">
                    <div class="container">
                        ${portfolios.length === 0 ? this.getEmptyStateHTML() : this.getPortfoliosGridHTML(portfolios)}
                    </div>
                </main>
            </div>
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
                <button class="btn btn-primary btn-lg">Create Your First Portfolio</button>
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

                        <button class="btn btn-secondary" style="width: 100%;">View Details</button>
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
}

// Initialize the app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.portfolioApp = new PortfolioApp();
});
