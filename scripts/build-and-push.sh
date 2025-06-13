#!/bin/bash

# Multi-Architecture Docker Build and Push Script
# Builds for both ARM64 and AMD64 architectures with proper versioning

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
DOCKER_REPO="adamchilders/portfolio-tracker"
PLATFORMS="linux/amd64,linux/arm64"

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

# Get version from command line or auto-generate
get_version() {
    if [ -n "$1" ]; then
        echo "$1"
    else
        # Auto-generate version based on git
        local git_hash=$(git rev-parse --short HEAD)
        local timestamp=$(date +%Y%m%d-%H%M%S)
        echo "v1.0.3-${timestamp}-${git_hash}"
    fi
}

# Validate Docker buildx
check_buildx() {
    log "Checking Docker buildx availability..."
    
    if ! docker buildx version &> /dev/null; then
        log_error "Docker buildx is not available"
        exit 1
    fi
    
    # Create/use multiplatform builder
    if ! docker buildx inspect multiplatform &> /dev/null; then
        log "Creating multiplatform builder..."
        docker buildx create --name multiplatform --driver docker-container --use
    else
        log "Using existing multiplatform builder..."
        docker buildx use multiplatform
    fi
    
    log_success "Docker buildx is ready"
}

# Build and push multi-architecture image
build_and_push() {
    local version="$1"
    local push_latest="$2"
    
    log "🏗️  Building multi-architecture image for version: $version"
    log "📦 Platforms: $PLATFORMS"
    
    # Build tags
    local tags="-t ${DOCKER_REPO}:${version}"
    
    if [ "$push_latest" = "true" ]; then
        tags="$tags -t ${DOCKER_REPO}:latest"
        log "📌 Also tagging as 'latest'"
    fi
    
    # Build and push
    log "🚀 Building and pushing..."
    docker buildx build \
        --platform "$PLATFORMS" \
        $tags \
        --push \
        .
    
    log_success "Multi-architecture build completed!"
    log "📋 Available architectures:"
    log "   - linux/amd64 (Intel/AMD x86_64)"
    log "   - linux/arm64 (Apple Silicon, ARM servers)"
}

# Create and push git tag
create_git_tag() {
    local version="$1"
    local message="$2"
    
    log "🏷️  Creating git tag: $version"
    
    # Check if tag already exists
    if git tag -l | grep -q "^${version}$"; then
        log_warning "Git tag $version already exists"
        return 0
    fi
    
    # Create annotated tag
    git tag -a "$version" -m "$message"
    
    # Push tag to remote
    git push origin "$version"
    
    log_success "Git tag $version created and pushed"
}

# Show image information
show_image_info() {
    local version="$1"
    
    log "📊 Image Information:"
    echo ""
    echo "🐳 Docker Hub Repository: https://hub.docker.com/r/${DOCKER_REPO}"
    echo "📦 Image: ${DOCKER_REPO}:${version}"
    echo "🏗️  Architectures: linux/amd64, linux/arm64"
    echo ""
    echo "📋 Pull Commands:"
    echo "   docker pull ${DOCKER_REPO}:${version}"
    echo "   docker pull ${DOCKER_REPO}:latest"
    echo ""
    echo "🎯 Kubernetes Usage:"
    echo "   image: ${DOCKER_REPO}:${version}"
    echo ""
}

# Verify multi-arch manifest
verify_manifest() {
    local version="$1"
    
    log "🔍 Verifying multi-architecture manifest..."
    
    # Check if manifest exists and shows multiple architectures
    if docker buildx imagetools inspect "${DOCKER_REPO}:${version}" | grep -q "linux/amd64\|linux/arm64"; then
        log_success "Multi-architecture manifest verified"
        
        log "📋 Manifest details:"
        docker buildx imagetools inspect "${DOCKER_REPO}:${version}" | grep -E "(Name:|Platform:|Digest:)"
    else
        log_error "Multi-architecture manifest verification failed"
        exit 1
    fi
}

# Main build function
build() {
    local version="$1"
    local push_latest="${2:-true}"
    local create_tag="${3:-true}"
    
    log "🚀 Starting multi-architecture build process..."
    
    # Validate inputs
    if [ -z "$version" ]; then
        version=$(get_version)
        log "📝 Auto-generated version: $version"
    fi
    
    # Pre-flight checks
    check_buildx
    
    # Build and push
    build_and_push "$version" "$push_latest"
    
    # Verify the build
    verify_manifest "$version"
    
    # Create git tag if requested
    if [ "$create_tag" = "true" ]; then
        local tag_message="Release $version - Multi-architecture Docker image with ARM64 and AMD64 support"
        create_git_tag "$version" "$tag_message"
    fi
    
    # Show information
    show_image_info "$version"
    
    log_success "🎉 Build process completed successfully!"
}

# Show usage
usage() {
    echo "Multi-Architecture Docker Build Script"
    echo ""
    echo "Usage: $0 [VERSION] [OPTIONS]"
    echo ""
    echo "Arguments:"
    echo "  VERSION           Version tag (e.g., v1.0.3, v1.1.0-beta)"
    echo "                    If not provided, auto-generates from git"
    echo ""
    echo "Options:"
    echo "  --no-latest       Don't tag as 'latest'"
    echo "  --no-git-tag      Don't create git tag"
    echo "  --help            Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 v1.0.3                    # Build v1.0.3, tag as latest, create git tag"
    echo "  $0 v1.1.0-beta --no-latest   # Build beta, don't tag as latest"
    echo "  $0                           # Auto-generate version from git"
    echo ""
    echo "Supported Architectures:"
    echo "  - linux/amd64 (Intel/AMD x86_64)"
    echo "  - linux/arm64 (Apple Silicon, ARM servers)"
}

# Parse command line arguments
main() {
    local version=""
    local push_latest="true"
    local create_tag="true"
    
    while [[ $# -gt 0 ]]; do
        case $1 in
            --no-latest)
                push_latest="false"
                shift
                ;;
            --no-git-tag)
                create_tag="false"
                shift
                ;;
            --help|-h)
                usage
                exit 0
                ;;
            -*)
                log_error "Unknown option: $1"
                echo ""
                usage
                exit 1
                ;;
            *)
                if [ -z "$version" ]; then
                    version="$1"
                else
                    log_error "Multiple versions specified: $version and $1"
                    exit 1
                fi
                shift
                ;;
        esac
    done
    
    # Run the build
    build "$version" "$push_latest" "$create_tag"
}

# Run main function with all arguments
main "$@"
