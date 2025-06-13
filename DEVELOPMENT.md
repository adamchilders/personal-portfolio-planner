# Development Guide

This guide covers local development setup for the Portfolio Tracker application.

## Local Development Options

### Option 1: Docker Compose (Recommended)

Create a local `docker-compose.dev.yml` for development:

```yaml
services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: portfolio_app_dev
    volumes:
      - ./:/var/www/html
      - ./storage/logs:/var/www/html/storage/logs
    environment:
      - DB_HOST=mysql
      - DB_PORT=3306
      - DB_DATABASE=portfolio_tracker
      - DB_USERNAME=portfolio_user
      - DB_PASSWORD=dev_password
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - APP_ENV=development
      - APP_DEBUG=true
      - APP_KEY=base64:dev_key_here
    depends_on:
      - mysql
      - redis
    ports:
      - "9000:9000"

  nginx:
    image: nginx:alpine
    container_name: portfolio_nginx_dev
    ports:
      - "8080:80"
    volumes:
      - ./public:/var/www/html/public:ro
      - ./dev/nginx.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      - app

  mysql:
    image: mysql:8.0
    container_name: portfolio_mysql_dev
    environment:
      MYSQL_ROOT_PASSWORD: root_password
      MYSQL_DATABASE: portfolio_tracker
      MYSQL_USER: portfolio_user
      MYSQL_PASSWORD: dev_password
    volumes:
      - mysql_dev_data:/var/lib/mysql
    ports:
      - "3306:3306"

  redis:
    image: redis:7-alpine
    container_name: portfolio_redis_dev
    ports:
      - "6379:6379"
    volumes:
      - redis_dev_data:/data

volumes:
  mysql_dev_data:
  redis_dev_data:
```

### Option 2: Native PHP Development

Requirements:
- PHP 8.4 with extensions: pdo_mysql, redis, mbstring, zip, gd, intl
- MySQL 8.0+
- Redis 7+
- Composer

Setup:
```bash
# Install dependencies
composer install

# Copy environment file
cp .env.example .env.dev

# Edit .env.dev with local database settings
# Run migrations
php bin/migrate.php run

# Start PHP development server
php -S localhost:8000 -t public/
```

## Development Workflow

### 1. Initial Setup

```bash
# Clone repository
git clone <repository-url>
cd personal-portfolio-planner

# Create development environment file
cp .env.example .env.dev

# Install PHP dependencies
composer install

# Install development dependencies
composer install --dev
```

### 2. Database Setup

```bash
# Start database (if using Docker)
docker-compose -f docker-compose.dev.yml up -d mysql redis

# Run migrations
php bin/migrate.php run

# Check installation status
php bin/install.php status
```

### 3. Running the Application

```bash
# Option A: Full Docker environment
docker-compose -f docker-compose.dev.yml up -d

# Option B: Native PHP with external services
php -S localhost:8000 -t public/
```

### 4. Development Tools

```bash
# Run tests
vendor/bin/phpunit

# Code style checking
vendor/bin/php-cs-fixer fix --dry-run

# Static analysis
vendor/bin/phpstan analyse

# Database migrations
php bin/migrate.php run
php bin/migrate.php status
```

## Testing

### Unit Tests

```bash
# Run all tests
vendor/bin/phpunit

# Run specific test file
vendor/bin/phpunit tests/Unit/UserTest.php

# Run with coverage
vendor/bin/phpunit --coverage-html coverage/
```

### Integration Tests

```bash
# Run integration tests
vendor/bin/phpunit tests/Integration/

# Test database connectivity
php bin/install.php status
```

## Code Quality

### PHP CS Fixer

```bash
# Check code style
vendor/bin/php-cs-fixer fix --dry-run --diff

# Fix code style
vendor/bin/php-cs-fixer fix
```

### PHPStan

```bash
# Run static analysis
vendor/bin/phpstan analyse

# Run with specific level
vendor/bin/phpstan analyse --level=8
```

## Database Management

### Migrations

```bash
# Create new migration
touch database/migrations/$(date +%Y%m%d_%H%M%S)_your_migration_name.sql

# Run migrations
php bin/migrate.php run

# Check migration status
php bin/migrate.php status
```

### Sample Migration File

```sql
-- Migration: Add new feature
-- Created: 2024-01-01

CREATE TABLE example_table (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO system_info (key_name, value) VALUES 
    ('migration_example', 'completed');
```

## Frontend Development

### Assets

```bash
# Install frontend dependencies (if using npm)
npm install

# Build assets for development
npm run dev

# Watch for changes
npm run watch

# Build for production
npm run build
```

### Styling

The application uses Tailwind CSS. To customize:

1. Edit `resources/css/app.css`
2. Rebuild assets: `npm run build`
3. Refresh browser

## API Development

### Testing API Endpoints

```bash
# Health check
curl http://localhost:8080/health

# Test authentication
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@portfolio-tracker.local","password":"admin123"}'
```

### API Documentation

Generate API documentation:

```bash
# Generate OpenAPI spec
php bin/generate-api-docs.php

# Serve documentation
php -S localhost:8001 -t docs/api/
```

## Debugging

### Xdebug Setup

Add to your development PHP configuration:

```ini
[xdebug]
xdebug.mode=debug
xdebug.start_with_request=yes
xdebug.client_host=host.docker.internal
xdebug.client_port=9003
```

### Logging

```bash
# View application logs
tail -f storage/logs/app.log

# View PHP errors
tail -f storage/logs/php_errors.log

# View installation logs
tail -f storage/logs/install.log
```

## Performance Testing

### Load Testing

```bash
# Install Apache Bench
apt-get install apache2-utils

# Basic load test
ab -n 1000 -c 10 http://localhost:8080/

# Test specific endpoint
ab -n 100 -c 5 http://localhost:8080/api/portfolios
```

### Profiling

```bash
# Enable profiling in .env.dev
APP_DEBUG=true
PROFILING_ENABLED=true

# Use Xdebug profiler
# Results in storage/profiling/
```

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   ```bash
   # Check MySQL is running
   docker-compose -f docker-compose.dev.yml ps mysql
   
   # Test connection
   mysql -h 127.0.0.1 -P 3306 -u portfolio_user -p
   ```

2. **Redis Connection Failed**
   ```bash
   # Check Redis is running
   docker-compose -f docker-compose.dev.yml ps redis
   
   # Test connection
   redis-cli -h 127.0.0.1 -p 6379 ping
   ```

3. **Permission Issues**
   ```bash
   # Fix storage permissions
   chmod -R 755 storage/
   chmod -R 755 bootstrap/cache/
   ```

4. **Composer Issues**
   ```bash
   # Clear composer cache
   composer clear-cache
   
   # Reinstall dependencies
   rm -rf vendor/
   composer install
   ```

## Contributing

1. Create feature branch: `git checkout -b feature/your-feature`
2. Make changes and add tests
3. Run code quality checks: `composer test`
4. Commit changes: `git commit -m "Add your feature"`
5. Push branch: `git push origin feature/your-feature`
6. Create pull request

## Environment Variables

Development-specific environment variables:

```env
# Development settings
APP_ENV=development
APP_DEBUG=true
LOG_LEVEL=debug

# Database (local)
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=portfolio_tracker_dev

# Redis (local)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Development features
PROFILING_ENABLED=true
QUERY_LOG_ENABLED=true
```
