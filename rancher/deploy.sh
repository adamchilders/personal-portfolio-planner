#!/bin/bash

# Portfolio Tracker Rancher Deployment Script
# This script deploys the Portfolio Tracker application to a dedicated namespace

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
NAMESPACE="portfolio-tracker"
APP_NAME="portfolio-tracker"

# Logging functions
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} ✅ $1"
}

log_warning() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} ⚠️  $1"
}

log_error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} ❌ $1"
}

# Check if kubectl is available
check_kubectl() {
    if ! command -v kubectl &> /dev/null; then
        log_error "kubectl is not installed or not in PATH"
        exit 1
    fi
    
    # Test kubectl connection
    if ! kubectl cluster-info &> /dev/null; then
        log_error "Cannot connect to Kubernetes cluster"
        exit 1
    fi
    
    log_success "kubectl is available and connected"
}

# Create namespace
create_namespace() {
    log "Creating namespace: $NAMESPACE"
    
    if kubectl get namespace "$NAMESPACE" &> /dev/null; then
        log_warning "Namespace $NAMESPACE already exists"
    else
        kubectl apply -f namespace.yaml
        log_success "Namespace $NAMESPACE created"
    fi
}

# Create secrets
create_secrets() {
    log "Creating application secrets..."
    
    # Check if secrets already exist
    if kubectl get secret portfolio-secrets -n "$NAMESPACE" &> /dev/null; then
        log_warning "Secrets already exist. Delete them first if you want to recreate:"
        log "kubectl delete secret portfolio-secrets -n $NAMESPACE"
        return 0
    fi
    
    # Generate secure passwords
    DB_PASSWORD=$(openssl rand -base64 32)
    REDIS_PASSWORD=$(openssl rand -base64 32)
    APP_KEY=$(openssl rand -base64 32)
    JWT_SECRET=$(openssl rand -hex 64)
    
    # Create the secret
    kubectl create secret generic portfolio-secrets \
        --namespace="$NAMESPACE" \
        --from-literal=db-username=portfolio_user \
        --from-literal=db-password="$DB_PASSWORD" \
        --from-literal=redis-password="$REDIS_PASSWORD" \
        --from-literal=app-key="$APP_KEY" \
        --from-literal=jwt-secret="$JWT_SECRET"
    
    log_success "Secrets created successfully"
    log "📝 Generated credentials (save these securely):"
    log "   DB Password: $DB_PASSWORD"
    log "   Redis Password: $REDIS_PASSWORD"
    log "   App Key: $APP_KEY"
    log "   JWT Secret: $JWT_SECRET"
}

# Deploy application
deploy_application() {
    log "Deploying Portfolio Tracker application..."
    
    # Apply ConfigMaps
    log "Applying ConfigMaps..."
    kubectl apply -f configmap.yaml
    
    # Apply Deployments and Services
    log "Applying Deployments and Services..."
    kubectl apply -f deployment.yaml
    
    log_success "Application deployed successfully"
}

# Wait for deployment
wait_for_deployment() {
    log "Waiting for deployments to be ready..."
    
    # Wait for app deployment
    log "Waiting for app deployment..."
    kubectl wait --for=condition=available --timeout=300s deployment/portfolio-tracker-app -n "$NAMESPACE"
    
    # Wait for nginx deployment
    log "Waiting for nginx deployment..."
    kubectl wait --for=condition=available --timeout=300s deployment/portfolio-tracker-nginx -n "$NAMESPACE"
    
    log_success "All deployments are ready"
}

# Show status
show_status() {
    log "📊 Deployment Status:"
    echo ""
    
    echo "=== Namespace ==="
    kubectl get namespace "$NAMESPACE"
    echo ""
    
    echo "=== Pods ==="
    kubectl get pods -n "$NAMESPACE"
    echo ""
    
    echo "=== Services ==="
    kubectl get services -n "$NAMESPACE"
    echo ""
    
    echo "=== Deployments ==="
    kubectl get deployments -n "$NAMESPACE"
    echo ""
    
    # Get service details for access information
    NGINX_SERVICE=$(kubectl get service portfolio-tracker-nginx-service -n "$NAMESPACE" -o jsonpath='{.status.loadBalancer.ingress[0].ip}' 2>/dev/null || echo "pending")
    
    if [ "$NGINX_SERVICE" != "pending" ] && [ -n "$NGINX_SERVICE" ]; then
        log_success "🌐 Application is accessible at: http://$NGINX_SERVICE"
    else
        log_warning "🌐 LoadBalancer IP is still pending. Check with:"
        log "kubectl get service portfolio-tracker-nginx-service -n $NAMESPACE"
    fi
    
    log "📋 Default admin credentials:"
    log "   Username: admin"
    log "   Email: admin@portfolio-tracker.local"
    log "   Password: admin123"
    log "   ⚠️  Change these immediately after first login!"
}

# Show logs
show_logs() {
    log "📋 Recent application logs:"
    kubectl logs -l app=portfolio-tracker,component=app -n "$NAMESPACE" --tail=20
}

# Clean up deployment
cleanup() {
    log "🧹 Cleaning up Portfolio Tracker deployment..."
    
    read -p "Are you sure you want to delete the entire $NAMESPACE namespace? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        kubectl delete namespace "$NAMESPACE"
        log_success "Namespace $NAMESPACE deleted"
    else
        log "Cleanup cancelled"
    fi
}

# Main deployment function
deploy() {
    log "🚀 Starting Portfolio Tracker deployment to namespace: $NAMESPACE"
    
    check_kubectl
    create_namespace
    create_secrets
    deploy_application
    wait_for_deployment
    show_status
}

# Show usage
usage() {
    echo "Portfolio Tracker Rancher Deployment Script"
    echo ""
    echo "Usage: $0 [COMMAND]"
    echo ""
    echo "Commands:"
    echo "  deploy    - Deploy the application (default)"
    echo "  status    - Show deployment status"
    echo "  logs      - Show application logs"
    echo "  cleanup   - Delete the entire deployment"
    echo "  help      - Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 deploy"
    echo "  $0 status"
    echo "  $0 logs"
}

# Main command handler
main() {
    local command="${1:-deploy}"
    
    case "$command" in
        deploy)
            deploy
            ;;
        status)
            show_status
            ;;
        logs)
            show_logs
            ;;
        cleanup)
            cleanup
            ;;
        help|--help|-h)
            usage
            ;;
        *)
            log_error "Unknown command: $command"
            echo ""
            usage
            exit 1
            ;;
    esac
}

# Run main function with all arguments
main "$@"
