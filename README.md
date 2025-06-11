# Personal Portfolio Tracker

A comprehensive stock portfolio tracking application built with PHP 8.4, designed to monitor multiple personal portfolios with real-time stock data from various APIs (Yahoo Finance, Polygon.io, etc.).

## Features

- **Multi-user support** with secure authentication
- **Multiple portfolios** per user
- **Smart API integration** with Yahoo Finance, Polygon.io, and Alpha Vantage
- **Background data fetching** with configurable schedules
- **Admin interface** for system management
- **Docker containerization** for easy deployment
- **Secure API key management** stored in database
- **Database upgrade system** for version management

## Technology Stack

- **Backend**: PHP 8.4 with Slim Framework
- **Database**: MySQL 8.0
- **Cache**: Redis
- **Frontend**: Twig templating with Alpine.js
- **CSS**: Tailwind CSS
- **Containerization**: Docker & Docker Compose

## Quick Start

### Prerequisites

- Docker and Docker Compose installed
- Git (for version control)

### Installation

1. Clone the repository:
```bash
git clone <repository-url>
cd personal-portfolio-planner
```

2. Copy environment configuration:
```bash
cp .env.example .env
```

3. Edit `.env` file with your configuration:
```bash
nano .env
```

4. Start the application:
```bash
docker-compose up -d
```

5. Run database migrations:
```bash
docker-compose exec app php bin/migrate.php
```

6. Access the application:
- Web Interface: http://localhost
- Admin Interface: http://localhost/admin

### Default Credentials

On first run, the application will prompt you to create an admin user through the welcome screen.

## Configuration

### Environment Variables

Key environment variables in `.env`:

- `DB_HOST=mysql` - Database host
- `DB_DATABASE=portfolio_tracker` - Database name
- `DB_USERNAME=portfolio_user` - Database username
- `DB_PASSWORD=secure_password` - Database password
- `REDIS_HOST=redis` - Redis host
- `APP_ENV=production` - Application environment
- `APP_DEBUG=false` - Debug mode

### API Keys

API keys are managed through the admin interface and stored securely in the database:

- Yahoo Finance (free tier)
- Polygon.io (paid tier)
- Alpha Vantage (backup/alternative)

## Development

### Local Development Setup

1. Start development environment:
```bash
docker-compose -f docker-compose.dev.yml up -d
```

2. Install PHP dependencies:
```bash
docker-compose exec app composer install
```

3. Run tests:
```bash
docker-compose exec app vendor/bin/phpunit
```

### Project Structure

```
├── app/                    # Application code
│   ├── Controllers/        # HTTP controllers
│   ├── Models/            # Database models
│   ├── Services/          # Business logic services
│   ├── Middleware/        # HTTP middleware
│   └── Jobs/              # Background jobs
├── config/                # Configuration files
├── database/              # Database migrations and seeds
│   ├── migrations/        # Database schema migrations
│   └── seeds/             # Sample data
├── public/                # Web root directory
├── resources/             # Frontend resources
│   ├── views/             # Twig templates
│   ├── css/               # Stylesheets
│   └── js/                # JavaScript files
├── storage/               # Application storage
│   └── logs/              # Log files
├── tests/                 # Test suite
├── docker-compose.yml     # Docker services configuration
├── Dockerfile             # PHP application container
└── nginx/                 # Nginx configuration
```

## Deployment

### Production Deployment

1. Set up production environment variables
2. Configure SSL certificates
3. Set up automated backups
4. Configure monitoring and health checks

See [DEPLOYMENT.md](DEPLOYMENT.md) for detailed deployment instructions.

## API Documentation

The application provides RESTful APIs for portfolio management. See [API.md](API.md) for detailed API documentation.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Submit a pull request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

For support and questions:
- Create an issue in the GitHub repository
- Check the [documentation](docs/)
- Review the [FAQ](docs/FAQ.md)

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history and updates.
