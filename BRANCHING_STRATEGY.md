# Branching Strategy for Portfolio Tracker

This document outlines the branching strategy and development workflow for the Personal Portfolio Tracker project.

## Branch Structure

### Main Branches

- **`main`** - Production-ready code, always deployable
- **`develop`** - Integration branch for features (optional, can merge directly to main for smaller teams)

### Feature Branches

We've created dedicated feature branches for major application components:

#### 🎨 **`feature/frontend-dashboard`**
**Purpose**: User interface and dashboard development
- Homepage design and layout
- Portfolio dashboard with charts and graphs
- Responsive design implementation
- User experience improvements
- Tailwind CSS styling and components

**Key Components**:
- Dashboard layout and navigation
- Portfolio overview widgets
- Stock performance charts
- Mobile-responsive design
- User preferences interface

#### 🔌 **`feature/api-integration`**
**Purpose**: External API integrations for stock data
- Yahoo Finance API integration
- Polygon.io API implementation
- Alpha Vantage backup integration
- API rate limiting and error handling
- Data fetching optimization

**Key Components**:
- Stock price fetching services
- Historical data retrieval
- Dividend and split data
- API key management
- Caching strategies

#### 📊 **`feature/portfolio-management`**
**Purpose**: Core portfolio functionality
- Portfolio CRUD operations
- Holdings management (add, edit, delete)
- Transaction tracking (buy, sell, dividend)
- Performance calculations
- Portfolio analytics

**Key Components**:
- Portfolio models and controllers
- Transaction management
- Cost basis calculations
- Gain/loss tracking
- Portfolio comparison tools

#### 🔐 **`feature/user-authentication`**
**Purpose**: User management and security
- User registration and login
- Session management
- Password reset functionality
- Email verification
- Multi-user support

**Key Components**:
- Authentication middleware
- User registration flow
- Login/logout functionality
- Password security
- Session handling

#### ⚙️ **`feature/admin-interface`**
**Purpose**: Administrative functionality
- System configuration management
- User management for admins
- API key configuration
- System monitoring and health checks
- Application settings

**Key Components**:
- Admin dashboard
- User management interface
- System settings configuration
- API key management
- Health monitoring tools

#### 🔄 **`feature/background-jobs`**
**Purpose**: Scheduled tasks and background processing
- Stock data fetching scheduler
- Portfolio snapshot generation
- Email notifications
- Data cleanup tasks
- Performance optimization

**Key Components**:
- Job scheduling system
- Queue management
- Data fetching workers
- Notification services
- Cleanup routines

## Development Workflow

### 1. Starting New Work

```bash
# Switch to main and pull latest changes
git checkout main
git pull origin main

# Create or switch to feature branch
git checkout feature/frontend-dashboard

# Pull latest changes for the feature branch
git pull origin feature/frontend-dashboard
```

### 2. Working on Features

```bash
# Make your changes
# ... code, test, commit ...

# Commit changes with descriptive messages
git add .
git commit -m "feat(dashboard): add portfolio overview widget

- Add portfolio value chart component
- Implement responsive grid layout
- Add loading states and error handling"

# Push to feature branch
git push origin feature/frontend-dashboard
```

### 3. Merging Features

```bash
# When feature is complete, create pull request
# Review code, run tests, get approval

# Merge to main (via GitHub PR or locally)
git checkout main
git pull origin main
git merge feature/frontend-dashboard
git push origin main
```

## Commit Message Convention

Use conventional commits for clear history:

```
<type>(<scope>): <description>

[optional body]

[optional footer]
```

**Types**:
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

**Scopes** (match feature areas):
- `dashboard`: Frontend dashboard
- `api`: API integrations
- `portfolio`: Portfolio management
- `auth`: Authentication
- `admin`: Admin interface
- `jobs`: Background jobs
- `deploy`: Deployment/infrastructure

**Examples**:
```bash
git commit -m "feat(dashboard): add real-time portfolio updates"
git commit -m "fix(api): handle Yahoo Finance rate limiting"
git commit -m "docs(readme): update deployment instructions"
```

## Feature Development Priority

Suggested development order based on dependencies:

### Phase 1: Foundation
1. **`feature/user-authentication`** - Core user system
2. **`feature/portfolio-management`** - Basic portfolio CRUD

### Phase 2: Core Features
3. **`feature/api-integration`** - Stock data fetching
4. **`feature/background-jobs`** - Automated data updates

### Phase 3: User Interface
5. **`feature/frontend-dashboard`** - User-facing interface
6. **`feature/admin-interface`** - Administrative tools

## Branch Protection Rules

Recommended GitHub branch protection for `main`:

- Require pull request reviews
- Require status checks to pass
- Require branches to be up to date
- Restrict pushes to main branch

## Testing Strategy

Each feature branch should include:

- Unit tests for new functionality
- Integration tests for API endpoints
- Frontend component tests
- Documentation updates

```bash
# Run tests before merging
composer test
npm run test
```

## Deployment Strategy

- **`main`** branch automatically deploys to production (Rancher)
- Feature branches can be deployed to staging environments
- Use semantic versioning for releases

## Quick Reference Commands

```bash
# List all branches
git branch -a

# Switch to feature branch
git checkout feature/frontend-dashboard

# Create new feature branch
git checkout -b feature/new-feature
git push -u origin feature/new-feature

# Update feature branch with main
git checkout feature/frontend-dashboard
git merge main

# Delete merged feature branch
git branch -d feature/completed-feature
git push origin --delete feature/completed-feature
```

## Getting Started

Choose a feature to work on based on your interests and the project priorities:

1. **Frontend Developer**: Start with `feature/frontend-dashboard`
2. **Backend Developer**: Start with `feature/api-integration` or `feature/portfolio-management`
3. **DevOps/Admin**: Start with `feature/admin-interface` or `feature/background-jobs`
4. **Full-Stack**: Start with `feature/user-authentication` (foundational)

Each feature branch is independent and can be developed in parallel, making it easy for multiple developers to contribute simultaneously.
