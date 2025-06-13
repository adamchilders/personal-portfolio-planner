# Portfolio Tracker Deployment Guide

This guide covers deploying the Personal Portfolio Tracker application with MySQL, Apache (via Nginx), PHP 8.4, and Redis.

## Prerequisites

- Docker and Docker Compose installed
- At least 2GB RAM available
- 10GB disk space for application and database
- Network access for pulling Docker images

## Quick Start

1. **Clone and Setup**
   ```bash
   git clone <repository-url>
   cd personal-portfolio-planner
   ```

2. **Configure Environment**
   ```bash
   cp .env.example .env
   # Edit .env file with your production settings
   ```

3. **Deploy**
   ```bash
   chmod +x bin/deploy.sh
   ./bin/deploy.sh deploy
   ```

## Detailed Deployment Steps

### 1. Environment Configuration

Copy the example environment file and customize it:

```bash
cp .env.example .env
```

**Critical settings to change:**

```env
# Database passwords (REQUIRED)
DB_PASSWORD=your_secure_database_password
DB_ROOT_PASSWORD=your_secure_root_password

# Redis password (RECOMMENDED)
REDIS_PASSWORD=your_secure_redis_password

# Application security keys (REQUIRED)
APP_KEY=base64:your_32_character_app_key_here
JWT_SECRET=your_64_character_jwt_secret_here

# Application URL (REQUIRED for production)
APP_URL=https://your-domain.com

# Email configuration (if using notifications)
MAIL_HOST=your.smtp.server
MAIL_USERNAME=your_email_username
MAIL_PASSWORD=your_email_password
```

### 2. Security Key Generation

Generate secure keys for production:

```bash
# Generate APP_KEY (32 characters, base64 encoded)
openssl rand -base64 32

# Generate JWT_SECRET (64+ characters)
openssl rand -hex 64
```

### 3. Database Setup

The application includes automatic database setup:

- **Fresh Installation**: When the database is empty, the install script automatically:
  - Runs all database migrations
  - Creates system configuration
  - Sets up a default admin user
  - Configures data sources

- **Default Admin Credentials**:
  - Username: `admin`
  - Email: `admin@portfolio-tracker.local`
  - Password: `admin123`
  - **⚠️ Change these immediately after first login!**

### 4. Deployment Commands

Use the deployment script for all operations:

```bash
# Deploy application
./bin/deploy.sh deploy

# Check status
./bin/deploy.sh status

# View logs
./bin/deploy.sh logs

# Restart application
./bin/deploy.sh restart

# Create database backup
./bin/deploy.sh backup

# Stop application
./bin/deploy.sh stop
```

### 5. Manual Installation Check

You can manually check or run the installation:

```bash
# Check installation status
docker-compose exec app php bin/install.php status

# Force reinstallation (if needed)
docker-compose exec app php bin/install.php install
```

## Architecture Overview

The application consists of these services:

- **app**: PHP 8.4-FPM application server
- **nginx**: Web server and reverse proxy
- **mysql**: MySQL 8.0 database
- **redis**: Redis cache and session storage
- **scheduler**: Background task scheduler
- **worker**: Background job processor

## Health Checks

The application includes comprehensive health monitoring:

- **Application Health**: `http://your-domain/health`
- **Container Health**: Built-in Docker health checks
- **Database Health**: Automatic connection testing
- **Redis Health**: Connection and ping tests

## Backup and Recovery

### Automatic Backups

Backups are created automatically:
- Before each deployment
- Stored in `./backups/` directory
- Compressed and timestamped
- Old backups cleaned up (keeps last 10)

### Manual Backup

```bash
# Create immediate backup
./bin/deploy.sh backup

# Restore from backup
gunzip backups/portfolio_tracker_YYYYMMDD_HHMMSS.sql.gz
docker-compose exec -T mysql mysql -u${DB_USERNAME} -p${DB_PASSWORD} ${DB_DATABASE} < backups/portfolio_tracker_YYYYMMDD_HHMMSS.sql
```

## Monitoring and Logs

### View Logs

```bash
# All services
./bin/deploy.sh logs

# Specific service
./bin/deploy.sh logs app
./bin/deploy.sh logs nginx
./bin/deploy.sh logs mysql
```

### Log Files

Application logs are stored in:
- `./storage/logs/` - Application logs
- Docker container logs via `docker-compose logs`

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check database credentials in `.env`
   - Ensure MySQL container is running
   - Verify network connectivity

2. **Redis Connection Failed**
   - Check Redis password in `.env`
   - Ensure Redis container is running
   - Verify Redis configuration

3. **Installation Fails**
   - Check database permissions
   - Verify all migrations are present
   - Review installation logs

4. **Health Check Fails**
   - Wait for services to fully start (can take 1-2 minutes)
   - Check container logs for errors
   - Verify all dependencies are running

### Debug Commands

```bash
# Check container status
docker-compose ps

# Check container health
docker-compose exec app php bin/install.php status

# Test database connection
docker-compose exec mysql mysql -u${DB_USERNAME} -p${DB_PASSWORD} -e "SELECT 1"

# Test Redis connection
docker-compose exec redis redis-cli ping
```

## Production Considerations

### Security

1. **Change Default Passwords**: Update all default passwords
2. **Use HTTPS**: Configure SSL certificates
3. **Firewall**: Restrict access to necessary ports only
4. **Regular Updates**: Keep Docker images updated

### Performance

1. **Resource Allocation**: Adjust container resource limits
2. **Database Tuning**: Optimize MySQL configuration
3. **Caching**: Configure Redis for optimal performance
4. **Monitoring**: Set up application monitoring

### Maintenance

1. **Regular Backups**: Schedule automated backups
2. **Log Rotation**: Configure log rotation
3. **Health Monitoring**: Set up alerting
4. **Updates**: Plan regular update cycles

## Support

For issues and questions:
1. Check the troubleshooting section
2. Review application logs
3. Check Docker container status
4. Verify environment configuration
