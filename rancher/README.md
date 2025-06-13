# Rancher Deployment Guide for Portfolio Tracker

This directory contains Kubernetes manifests for deploying the Portfolio Tracker application in Rancher.

## Prerequisites

- Rancher cluster with Kubernetes
- MySQL database service (can be external or in-cluster)
- Redis service (can be external or in-cluster)
- Container registry access for the application image

## Deployment Steps

### 1. Build and Push Application Image

```bash
# Build the application image
docker build -t your-registry/portfolio-tracker:latest .

# Push to your container registry
docker push your-registry/portfolio-tracker:latest
```

### 2. Configure Secrets

```bash
# Copy the secrets template
cp rancher/secrets-template.yaml rancher/secrets.yaml

# Generate secure passwords
DB_PASSWORD=$(openssl rand -base64 32)
REDIS_PASSWORD=$(openssl rand -base64 32)
APP_KEY=$(openssl rand -base64 32)
JWT_SECRET=$(openssl rand -hex 64)

# Create the secret directly (recommended)
kubectl create secret generic portfolio-secrets \
  --from-literal=db-username=portfolio_user \
  --from-literal=db-password="$DB_PASSWORD" \
  --from-literal=redis-password="$REDIS_PASSWORD" \
  --from-literal=app-key="$APP_KEY" \
  --from-literal=jwt-secret="$JWT_SECRET"

# Or edit rancher/secrets.yaml with base64 encoded values and apply
# kubectl apply -f rancher/secrets.yaml
```

### 3. Configure Application Settings

Edit `rancher/configmap.yaml` to match your environment:

```yaml
data:
  db-host: "your-mysql-service"  # Update with your MySQL service name
  redis-host: "your-redis-service"  # Update with your Redis service name
  app-name: "Your Portfolio Tracker"
  # ... other configuration
```

### 4. Deploy to Rancher

```bash
# Apply configuration
kubectl apply -f rancher/configmap.yaml

# Apply secrets (if using YAML file)
kubectl apply -f rancher/secrets.yaml

# Deploy the application
kubectl apply -f rancher/deployment.yaml
```

### 5. Verify Deployment

```bash
# Check pod status
kubectl get pods -l app=portfolio-tracker

# Check services
kubectl get services -l app=portfolio-tracker

# Check logs
kubectl logs -l app=portfolio-tracker,component=app

# Test health endpoint
kubectl port-forward service/portfolio-tracker-nginx-service 8080:80
curl http://localhost:8080/health
```

## Architecture

The deployment consists of:

- **App Container**: PHP 8.4-FPM application server
- **Nginx Container**: Web server and reverse proxy
- **ConfigMaps**: Non-sensitive configuration
- **Secrets**: Sensitive configuration (passwords, keys)

## Database Setup

The application will automatically:
- Run database migrations on first startup
- Create default admin user (admin/admin123)
- Set up system configuration

**Important**: Change the default admin password immediately after first login!

## Scaling

To scale the application:

```bash
# Scale app pods
kubectl scale deployment portfolio-tracker-app --replicas=3

# Scale nginx pods
kubectl scale deployment portfolio-tracker-nginx --replicas=2
```

## Monitoring

Health checks are configured for:
- **Liveness Probe**: Ensures container is running
- **Readiness Probe**: Ensures container is ready to serve traffic

Access health endpoint: `http://your-service/health`

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   ```bash
   # Check database service
   kubectl get services | grep mysql
   
   # Check database connectivity from app pod
   kubectl exec -it deployment/portfolio-tracker-app -- php -r "
   try {
     \$pdo = new PDO('mysql:host=mysql-service;port=3306', 'user', 'pass');
     echo 'Connected';
   } catch (Exception \$e) {
     echo 'Failed: ' . \$e->getMessage();
   }"
   ```

2. **Redis Connection Failed**
   ```bash
   # Check Redis service
   kubectl get services | grep redis
   
   # Test Redis connectivity
   kubectl exec -it deployment/portfolio-tracker-app -- redis-cli -h redis-service ping
   ```

3. **Application Not Starting**
   ```bash
   # Check pod logs
   kubectl logs -l app=portfolio-tracker,component=app --tail=100
   
   # Check events
   kubectl get events --sort-by=.metadata.creationTimestamp
   ```

4. **Installation Issues**
   ```bash
   # Check installation status
   kubectl exec -it deployment/portfolio-tracker-app -- php bin/install.php status
   
   # Force reinstallation if needed
   kubectl exec -it deployment/portfolio-tracker-app -- php bin/install.php install
   ```

## Security Considerations

1. **Secrets Management**: Use Kubernetes secrets or external secret management
2. **Network Policies**: Implement network policies to restrict traffic
3. **RBAC**: Configure appropriate role-based access control
4. **Image Security**: Scan container images for vulnerabilities
5. **TLS**: Configure TLS termination at ingress level

## Backup and Recovery

For database backups in Kubernetes:

```bash
# Create a backup job
kubectl create job portfolio-backup --from=cronjob/portfolio-backup

# Or manual backup
kubectl exec -it deployment/portfolio-tracker-app -- \
  mysqldump -h mysql-service -u username -p database_name > backup.sql
```

## Updates

To update the application:

```bash
# Build and push new image
docker build -t your-registry/portfolio-tracker:v1.1.0 .
docker push your-registry/portfolio-tracker:v1.1.0

# Update deployment
kubectl set image deployment/portfolio-tracker-app app=your-registry/portfolio-tracker:v1.1.0

# Monitor rollout
kubectl rollout status deployment/portfolio-tracker-app
```
