#!/bin/bash

# Portfolio Tracker Git-based Deployment Script
# Usage: ./deploy.sh [branch/tag] [sync-interval]

set -e

NAMESPACE="portfolio-tracker"
BRANCH_OR_TAG="${1:-main}"
SYNC_INTERVAL="${2:-60}"

echo "🚀 Portfolio Tracker Git Deployment Manager"
echo "============================================="

# Function to update git branch/tag
update_git_config() {
    local branch="$1"
    local interval="$2"

    echo "📝 Updating git configuration..."
    echo "   Branch/Tag: $branch"
    echo "   Sync Interval: ${interval}s"

    # Update the ConfigMap
    kubectl patch configmap portfolio-config -n $NAMESPACE --type merge -p "{\"data\":{\"git-branch\":\"$branch\",\"git-sync-interval\":\"$interval\"}}"

    echo "✅ Git configuration updated"
}

# Function to restart deployment
restart_deployment() {
    echo "🔄 Restarting deployment to apply changes..."
    kubectl rollout restart deployment portfolio-tracker-app -n $NAMESPACE

    echo "⏳ Waiting for rollout to complete..."
    kubectl rollout status deployment portfolio-tracker-app -n $NAMESPACE --timeout=300s

    echo "✅ Deployment restarted successfully"
}

# Function to check deployment status
check_status() {
    echo "📊 Current deployment status:"
    echo "----------------------------"

    # Get current git config
    CURRENT_BRANCH=$(kubectl get configmap portfolio-config -n $NAMESPACE -o jsonpath='{.data.git-branch}')
    CURRENT_INTERVAL=$(kubectl get configmap portfolio-config -n $NAMESPACE -o jsonpath='{.data.git-sync-interval}')

    echo "   Git Branch/Tag: $CURRENT_BRANCH"
    echo "   Sync Interval: ${CURRENT_INTERVAL}s"

    # Get pod status
    echo ""
    kubectl get pods -l component=app -n $NAMESPACE

    # Get recent git sync logs
    echo ""
    echo "📋 Recent git-sync activity:"
    POD_NAME=$(kubectl get pods -l component=app -n $NAMESPACE -o jsonpath='{.items[0].metadata.name}')
    if [ ! -z "$POD_NAME" ]; then
        kubectl logs $POD_NAME -n $NAMESPACE -c git-sync --tail=5 2>/dev/null || echo "   Git-sync logs not available yet"
    fi
}

# Function to show help
show_help() {
    echo "Portfolio Tracker Git Deployment Manager"
    echo ""
    echo "USAGE:"
    echo "  ./deploy.sh [COMMAND] [OPTIONS]"
    echo ""
    echo "COMMANDS:"
    echo "  deploy <branch/tag> [interval]  Deploy specific branch or tag"
    echo "  status                          Show current deployment status"
    echo "  logs                           Show git-sync logs"
    echo "  help                           Show this help message"
    echo ""
    echo "EXAMPLES:"
    echo "  ./deploy.sh deploy main         Deploy main branch"
    echo "  ./deploy.sh deploy v2.0.1       Deploy tag v2.0.1"
    echo "  ./deploy.sh deploy dev 30       Deploy dev branch, sync every 30s"
    echo "  ./deploy.sh status              Check current status"
    echo ""
    echo "QUICK DEPLOYMENT EXAMPLES:"
    echo "  Production:  ./deploy.sh deploy v2.0.1 300    # Stable tag, 5min sync"
    echo "  Staging:     ./deploy.sh deploy main 60       # Latest main, 1min sync"
    echo "  Development: ./deploy.sh deploy dev 30        # Dev branch, 30s sync"
}

# Main script logic
case "${1:-help}" in
    "deploy")
        if [ -z "$2" ]; then
            echo "❌ Error: Branch or tag required"
            echo "Usage: ./deploy.sh deploy <branch/tag> [sync-interval]"
            exit 1
        fi
        BRANCH_OR_TAG="$2"
        SYNC_INTERVAL="${3:-60}"
        update_git_config "$BRANCH_OR_TAG" "$SYNC_INTERVAL"
        restart_deployment
        echo ""
        check_status
        echo ""
        echo "🎉 Deployment complete!"
        echo "📱 Visit: https://portfolio.adamchilders.com"
        ;;
    "status")
        check_status
        ;;
    "logs")
        echo "📋 Git-sync logs (last 20 lines):"
        POD_NAME=$(kubectl get pods -l component=app -n $NAMESPACE -o jsonpath='{.items[0].metadata.name}')
        if [ ! -z "$POD_NAME" ]; then
            kubectl logs $POD_NAME -n $NAMESPACE -c git-sync --tail=20 -f
        else
            echo "❌ No pods found"
        fi
        ;;
    "help"|*)
        show_help
        ;;
esac


