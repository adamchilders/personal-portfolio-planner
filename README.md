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

- Rancher/Kubernetes cluster
- MySQL database service
- Redis service
- Container registry access
- Docker for building images

### Rancher Deployment

1. Clone the repository:
```bash
git clone <repository-url>
cd personal-portfolio-planner
```

2. Build and push the application image:
```bash
docker build -t your-registry/portfolio-tracker:latest .
docker push your-registry/portfolio-tracker:latest
```

3. Configure and deploy to Rancher:
```bash
# Configure secrets and settings
kubectl create secret generic portfolio-secrets \
  --from-literal=db-username=portfolio_user \
  --from-literal=db-password="your_secure_password" \
  --from-literal=app-key="$(openssl rand -base64 32)" \
  --from-literal=jwt-secret="$(openssl rand -hex 64)"

# Deploy application
kubectl apply -f rancher/configmap.yaml
kubectl apply -f rancher/deployment.yaml
```

4. Access the application:
- Web Interface: http://your-rancher-service
- Admin Interface: http://your-rancher-service/admin

### Default Credentials

The installation automatically creates a default admin user:
- **Username**: `admin`
- **Email**: `admin@portfolio-tracker.local`
- **Password**: `admin123`

**⚠️ Important**: Change these credentials immediately after first login!

### Local Development

For local development with Docker Compose, see [DEVELOPMENT.md](DEVELOPMENT.md).

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

### Rancher/Kubernetes Deployment

The application is designed for cloud-native deployment with:

- **Kubernetes-native configuration** with ConfigMaps and Secrets
- **Automatic database setup** when database is empty
- **Built-in migration system** for schema updates
- **Health checks** and readiness probes
- **Horizontal scaling** support
- **Production-ready configurations** for PHP 8.4

**Rancher Deployment:**
```bash
# Build and push image
docker build -t your-registry/portfolio-tracker:latest .
docker push your-registry/portfolio-tracker:latest

# Deploy to Rancher
kubectl apply -f rancher/configmap.yaml
kubectl apply -f rancher/deployment.yaml
```

**Key Features:**
- Cloud-native architecture
- Automatic database initialization and migration
- Default admin user creation
- Kubernetes health monitoring
- Horizontal pod autoscaling ready
- ConfigMap and Secret management

See [rancher/README.md](rancher/README.md) for detailed Rancher deployment instructions.

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
