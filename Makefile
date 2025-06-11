# Portfolio Tracker Makefile
# Provides convenient commands for development and deployment

.PHONY: help build up down restart logs shell test clean install

# Default target
help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Targets:'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  %-15s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

# Development commands
setup: ## Quick setup without composer install
	@echo "Quick setup of Portfolio Tracker..."
	@if [ ! -f .env ]; then cp .env.example .env; echo "Created .env file from .env.example"; fi
	@echo "Building and starting development containers..."
	docker-compose -f docker-compose.dev.yml up -d --build
	@echo "Setup complete! Access the app at http://localhost:8080"

install: ## Install dependencies and set up the project
	@echo "Setting up Portfolio Tracker..."
	@if [ ! -f .env ]; then cp .env.example .env; echo "Created .env file from .env.example"; fi
	@echo "Building development containers..."
	docker-compose -f docker-compose.dev.yml build
	@echo "Starting development environment..."
	docker-compose -f docker-compose.dev.yml up -d
	@echo "Waiting for containers to be ready..."
	@sleep 10
	@echo "Installing PHP dependencies..."
	docker-compose -f docker-compose.dev.yml exec app composer install
	@echo ""
	@echo "Setup complete! 🎉"
	@echo "Access the app at: http://localhost:8080"
	@echo "PHPMyAdmin at: http://localhost:8081"
	@echo "Redis Commander at: http://localhost:8082"

dev-up: ## Start development environment
	docker-compose -f docker-compose.dev.yml up -d

dev-down: ## Stop development environment
	docker-compose -f docker-compose.dev.yml down

dev-build: ## Build development containers
	docker-compose -f docker-compose.dev.yml build

dev-logs: ## Show development logs
	docker-compose -f docker-compose.dev.yml logs -f

dev-shell: ## Access development app container shell
	docker-compose -f docker-compose.dev.yml exec app bash

# Production commands
build: ## Build production containers
	docker-compose build

up: ## Start production environment
	docker-compose up -d

down: ## Stop production environment
	docker-compose down

restart: ## Restart production environment
	docker-compose restart

logs: ## Show production logs
	docker-compose logs -f

shell: ## Access production app container shell
	docker-compose exec app bash

# Database commands
migrate: ## Run database migrations
	docker-compose exec app php bin/migrate.php

seed: ## Seed database with sample data
	docker-compose exec app php bin/seed.php

db-shell: ## Access MySQL shell
	docker-compose exec mysql mysql -u portfolio_user -p portfolio_tracker

# Testing commands
test: ## Run tests
	docker-compose exec app vendor/bin/phpunit

test-coverage: ## Run tests with coverage
	docker-compose exec app vendor/bin/phpunit --coverage-html coverage

phpstan: ## Run static analysis
	docker-compose exec app vendor/bin/phpstan analyse

cs-check: ## Check code style
	docker-compose exec app vendor/bin/phpcs

cs-fix: ## Fix code style
	docker-compose exec app vendor/bin/phpcbf

quality: ## Run all quality checks
	docker-compose exec app composer quality

# Utility commands
clean: ## Clean up containers and volumes
	docker-compose down -v
	docker system prune -f

backup: ## Create database backup
	@echo "Creating database backup..."
	docker-compose exec mysql mysqldump -u portfolio_user -p portfolio_tracker > backup_$(shell date +%Y%m%d_%H%M%S).sql

status: ## Show container status
	docker-compose ps

# Monitoring commands
health: ## Check application health
	@echo "Checking application health..."
	@curl -s http://localhost/health || echo "Application not responding"

monitor: ## Show real-time container stats
	docker stats
