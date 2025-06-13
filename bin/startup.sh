#!/bin/bash

# Portfolio Tracker Startup Script
# This script handles the application startup process including installation checks

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

log_error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

# Wait for database to be ready
wait_for_database() {
    log "Waiting for database to be ready..."
    
    local max_attempts=30
    local attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        if php -r "
            try {
                \$pdo = new PDO(
                    'mysql:host=${DB_HOST:-mysql};port=${DB_PORT:-3306};dbname=${DB_DATABASE:-portfolio_tracker}',
                    '${DB_USERNAME:-portfolio_user}',
                    '${DB_PASSWORD:-password}'
                );
                echo 'Connected';
                exit(0);
            } catch (Exception \$e) {
                exit(1);
            }
        " > /dev/null 2>&1; then
            log_success "Database connection established"
            return 0
        fi
        
        log "Database not ready, attempt $attempt/$max_attempts..."
        sleep 2
        ((attempt++))
    done
    
    log_error "Failed to connect to database after $max_attempts attempts"
    return 1
}

# Wait for Redis to be ready
wait_for_redis() {
    log "Waiting for Redis to be ready..."
    
    local max_attempts=15
    local attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        if redis-cli -h "${REDIS_HOST:-redis}" -p "${REDIS_PORT:-6379}" ping > /dev/null 2>&1; then
            log_success "Redis connection established"
            return 0
        fi
        
        log "Redis not ready, attempt $attempt/$max_attempts..."
        sleep 1
        ((attempt++))
    done
    
    log_error "Failed to connect to Redis after $max_attempts attempts"
    return 1
}

# Check and run installation if needed
check_and_install() {
    log "Checking installation status..."
    
    # Check if installation is needed
    if php /var/www/html/bin/install.php status --quiet | grep -q "Installation status: complete"; then
        log_success "Application is already installed"
        return 0
    fi
    
    log_warning "Installation required, running installer..."
    
    # Run installation
    if php /var/www/html/bin/install.php install; then
        log_success "Installation completed successfully"
        return 0
    else
        log_error "Installation failed"
        return 1
    fi
}

# Set up proper permissions
setup_permissions() {
    log "Setting up file permissions..."

    # Ensure storage directories exist and are writable
    mkdir -p /var/www/html/storage/logs
    mkdir -p /var/www/html/storage/cache
    mkdir -p /var/www/html/storage/sessions
    mkdir -p /var/www/html/bootstrap/cache

    # Test if we can write to the directories (skip chmod if we can't)
    if touch /var/www/html/storage/logs/test.tmp 2>/dev/null; then
        rm -f /var/www/html/storage/logs/test.tmp
        log_success "Storage directories are writable"
    else
        log_warning "Cannot modify storage permissions (running as non-root user)"
        log "Storage directories should be writable by the container runtime"
    fi

    log_success "Permissions check completed"
}

# Validate environment configuration
validate_environment() {
    log "Validating environment configuration..."
    
    local required_vars=(
        "DB_HOST"
        "DB_DATABASE"
        "DB_USERNAME"
        "DB_PASSWORD"
        "REDIS_HOST"
        "APP_ENV"
    )
    
    local missing_vars=()
    
    for var in "${required_vars[@]}"; do
        if [ -z "${!var}" ]; then
            missing_vars+=("$var")
        fi
    done
    
    if [ ${#missing_vars[@]} -gt 0 ]; then
        log_error "Missing required environment variables:"
        for var in "${missing_vars[@]}"; do
            log_error "  - $var"
        done
        return 1
    fi
    
    log_success "Environment validation passed"
    return 0
}

# Clear caches
clear_caches() {
    log "Clearing application caches..."
    
    # Clear PHP opcache if available
    if command -v php > /dev/null; then
        php -r "if (function_exists('opcache_reset')) { opcache_reset(); echo 'OPcache cleared\n'; }"
    fi
    
    # Clear application cache directories
    rm -rf /var/www/html/storage/cache/*
    rm -rf /var/www/html/bootstrap/cache/*
    
    log_success "Caches cleared"
}

# Health check
health_check() {
    log "Running health check..."

    # Check if PHP-FPM is ready to accept connections
    if php -r "echo 'PHP is ready';" > /dev/null 2>&1; then
        log_success "PHP runtime check passed"
        return 0
    else
        log_warning "PHP runtime check failed"
        return 0
    fi
}

# Main startup sequence
main() {
    log "🚀 Starting Portfolio Tracker application..."
    
    # Validate environment
    if ! validate_environment; then
        log_error "Environment validation failed"
        exit 1
    fi
    
    # Set up permissions
    setup_permissions
    
    # Wait for dependencies
    if ! wait_for_database; then
        log_error "Database dependency check failed"
        exit 1
    fi
    
    if ! wait_for_redis; then
        log_error "Redis dependency check failed"
        exit 1
    fi
    
    # Clear caches
    clear_caches
    
    # Check and run installation
    if ! check_and_install; then
        log_error "Installation check failed"
        exit 1
    fi
    
    # Run health check
    health_check
    
    log_success "🎉 Portfolio Tracker startup completed successfully!"
    
    # If this is the main container process, start Apache
    if [ "$1" = "apache2-foreground" ] || [ -z "$1" ]; then
        log "Starting Apache..."
        exec apache2-foreground
    else
        # Execute the provided command
        log "Executing command: $*"
        exec "$@"
    fi
}

# Run main function with all arguments
main "$@"
