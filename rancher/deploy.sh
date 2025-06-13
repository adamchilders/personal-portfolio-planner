#!/bin/bash

# Portfolio Tracker Git-based Deployment Script
# Usage: ./deploy.sh [branch/tag] [sync-interval]

set -e

NAMESPACE="portfolio-tracker"
BRANCH_OR_TAG="${1:-main}"
SYNC_INTERVAL="${2:-60}"

echo "🚀 Portfolio Tracker Git Deployment Manager"
echo "============================================="

# Function to update git ref (branch/tag/commit)
update_git_ref() {
    local git_ref="$1"

    echo "📝 Updating deployment configuration..."
    echo "   Git Ref: $git_ref"

    # Update the ConfigMap
    kubectl patch configmap portfolio-config -n $NAMESPACE --type merge -p "{\"data\":{\"git-ref\":\"$git_ref\"}}"

    echo "✅ Git reference updated to: $git_ref"
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
    CURRENT_REF=$(kubectl get configmap portfolio-config -n $NAMESPACE -o jsonpath='{.data.git-ref}')

    echo "   Deployed Git Ref: $CURRENT_REF"

    # Get pod status
    echo ""
    kubectl get pods -l component=app -n $NAMESPACE

    # Get deployment info
    echo ""
    echo "📋 Deployment info:"
    POD_NAME=$(kubectl get pods -l component=app -n $NAMESPACE -o jsonpath='{.items[0].metadata.name}')
    if [ ! -z "$POD_NAME" ]; then
        echo "   Pod: $POD_NAME"
        DEPLOYED_REF=$(kubectl exec $POD_NAME -n $NAMESPACE -c app -- printenv DEPLOYED_GIT_REF 2>/dev/null || echo "unknown")
        echo "   Running Git Ref: $DEPLOYED_REF"
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
    echo "  deploy <git-ref>               Deploy specific branch, tag, or commit"
    echo "  status                         Show current deployment status"
    echo "  logs                          Show deployment logs"
    echo "  help                          Show this help message"
    echo ""
    echo "EXAMPLES:"
    echo "  ./deploy.sh deploy main        Deploy main branch"
    echo "  ./deploy.sh deploy v2.0.1      Deploy tag v2.0.1"
    echo "  ./deploy.sh deploy feature/ui  Deploy feature branch"
    echo "  ./deploy.sh deploy abc123      Deploy specific commit"
    echo "  ./deploy.sh status             Check current status"
    echo ""
    echo "DEPLOYMENT STRATEGY:"
    echo "  Production:  ./deploy.sh deploy v2.0.1      # Stable release tag"
    echo "  Staging:     ./deploy.sh deploy main        # Latest main branch"
    echo "  Development: ./deploy.sh deploy feature/x   # Feature branch"
    echo ""
    echo "WORKFLOW:"
    echo "  1. Update git-ref in ConfigMap"
    echo "  2. Restart deployment to pull new code"
    echo "  3. No automatic syncing - deploy on demand only"
}

# Main script logic
case "${1:-help}" in
    "deploy")
        if [ -z "$2" ]; then
            echo "❌ Error: Git reference required"
            echo "Usage: ./deploy.sh deploy <git-ref>"
            echo "Examples: main, v2.0.1, feature/ui, abc123"
            exit 1
        fi
        GIT_REF="$2"
        update_git_ref "$GIT_REF"
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
        echo "📋 Deployment logs (last 20 lines):"
        POD_NAME=$(kubectl get pods -l component=app -n $NAMESPACE -o jsonpath='{.items[0].metadata.name}')
        if [ ! -z "$POD_NAME" ]; then
            echo "Init container (git-clone) logs:"
            kubectl logs $POD_NAME -n $NAMESPACE -c git-clone --tail=10 2>/dev/null || echo "   No init logs available"
            echo ""
            echo "App container logs:"
            kubectl logs $POD_NAME -n $NAMESPACE -c app --tail=10 -f
        else
            echo "❌ No pods found"
        fi
        ;;
    "help"|*)
        show_help
        ;;
esac


