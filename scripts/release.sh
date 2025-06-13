#!/bin/bash

# Release Management Script
# Handles version bumping, tagging, and release creation

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
DOCKER_REPO="adamchilders/portfolio-tracker"

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

# Get current version from git tags
get_current_version() {
    git tag -l "v*" | sort -V | tail -n1 || echo "v1.0.0"
}

# Bump version based on type
bump_version() {
    local current="$1"
    local bump_type="$2"
    
    # Remove 'v' prefix for processing
    current="${current#v}"
    
    # Split version into parts
    IFS='.' read -ra VERSION_PARTS <<< "$current"
    local major="${VERSION_PARTS[0]:-1}"
    local minor="${VERSION_PARTS[1]:-0}"
    local patch="${VERSION_PARTS[2]:-0}"
    
    case "$bump_type" in
        major)
            major=$((major + 1))
            minor=0
            patch=0
            ;;
        minor)
            minor=$((minor + 1))
            patch=0
            ;;
        patch)
            patch=$((patch + 1))
            ;;
        *)
            log_error "Invalid bump type: $bump_type"
            exit 1
            ;;
    esac
    
    echo "v${major}.${minor}.${patch}"
}

# Validate git state
validate_git_state() {
    log "🔍 Validating git state..."
    
    # Check if we're on main branch
    local current_branch=$(git branch --show-current)
    if [ "$current_branch" != "main" ]; then
        log_error "Must be on main branch for release. Current branch: $current_branch"
        exit 1
    fi
    
    # Check for uncommitted changes
    if ! git diff-index --quiet HEAD --; then
        log_error "Uncommitted changes detected. Please commit or stash changes first."
        exit 1
    fi
    
    # Check if we're up to date with remote
    git fetch origin main
    local local_commit=$(git rev-parse HEAD)
    local remote_commit=$(git rev-parse origin/main)
    
    if [ "$local_commit" != "$remote_commit" ]; then
        log_error "Local branch is not up to date with origin/main"
        exit 1
    fi
    
    log_success "Git state is clean and ready for release"
}

# Update version in files
update_version_files() {
    local version="$1"
    
    log "📝 Updating version in project files..."
    
    # Update deployment.yaml
    if [ -f "rancher/deployment.yaml" ]; then
        sed -i.bak "s|image: ${DOCKER_REPO}:.*|image: ${DOCKER_REPO}:${version}|g" rancher/deployment.yaml
        rm -f rancher/deployment.yaml.bak
        log "Updated rancher/deployment.yaml"
    fi
    
    # Update README.md if it exists
    if [ -f "README.md" ]; then
        sed -i.bak "s|${DOCKER_REPO}:v[0-9]*\.[0-9]*\.[0-9]*|${DOCKER_REPO}:${version}|g" README.md
        rm -f README.md.bak
        log "Updated README.md"
    fi
    
    log_success "Version files updated"
}

# Create release commit
create_release_commit() {
    local version="$1"
    
    log "📦 Creating release commit..."
    
    # Add updated files
    git add .
    
    # Create commit
    git commit -m "chore: release $version

- Update Docker image references to $version
- Multi-architecture support (AMD64 + ARM64)
- Ready for production deployment"
    
    log_success "Release commit created"
}

# Create and push git tag
create_git_tag() {
    local version="$1"
    local message="$2"
    
    log "🏷️  Creating git tag: $version"
    
    # Create annotated tag
    git tag -a "$version" -m "$message"
    
    # Push commit and tag
    git push origin main
    git push origin "$version"
    
    log_success "Git tag $version created and pushed"
}

# Generate changelog
generate_changelog() {
    local version="$1"
    local previous_version="$2"
    
    log "📋 Generating changelog..."
    
    echo "## $version ($(date +%Y-%m-%d))"
    echo ""
    
    if [ "$previous_version" != "v1.0.0" ]; then
        echo "### Changes since $previous_version"
        echo ""
        git log --pretty=format:"- %s" "${previous_version}..HEAD" | head -20
    else
        echo "### Initial Release"
        echo ""
        echo "- Multi-architecture Docker support (AMD64 + ARM64)"
        echo "- Kubernetes deployment configurations"
        echo "- Portfolio tracking application"
    fi
    
    echo ""
    echo "### Docker Images"
    echo ""
    echo "- \`${DOCKER_REPO}:${version}\`"
    echo "- \`${DOCKER_REPO}:latest\`"
    echo ""
    echo "### Supported Architectures"
    echo ""
    echo "- linux/amd64 (Intel/AMD x86_64)"
    echo "- linux/arm64 (Apple Silicon, ARM servers)"
}

# Main release function
release() {
    local bump_type="$1"
    local custom_version="$2"
    
    log "🚀 Starting release process..."
    
    # Validate git state
    validate_git_state
    
    # Determine version
    local current_version=$(get_current_version)
    local new_version
    
    if [ -n "$custom_version" ]; then
        new_version="$custom_version"
        log "📌 Using custom version: $new_version"
    else
        new_version=$(bump_version "$current_version" "$bump_type")
        log "📈 Bumping $bump_type version: $current_version → $new_version"
    fi
    
    # Update version files
    update_version_files "$new_version"
    
    # Create release commit
    create_release_commit "$new_version"
    
    # Generate changelog
    local changelog=$(generate_changelog "$new_version" "$current_version")
    
    # Create git tag
    create_git_tag "$new_version" "Release $new_version

$changelog"
    
    # Show summary
    log_success "🎉 Release $new_version completed!"
    echo ""
    echo "📋 Release Summary:"
    echo "   Previous: $current_version"
    echo "   New: $new_version"
    echo "   Docker: ${DOCKER_REPO}:${new_version}"
    echo ""
    echo "🚀 Next Steps:"
    echo "   1. GitHub Actions will automatically build multi-arch images"
    echo "   2. Images will be pushed to Docker Hub"
    echo "   3. GitHub release will be created"
    echo "   4. Deploy using: kubectl apply -f rancher/"
}

# Show usage
usage() {
    echo "Release Management Script"
    echo ""
    echo "Usage: $0 <bump_type|version> [custom_version]"
    echo ""
    echo "Bump Types:"
    echo "  major     Increment major version (1.0.0 → 2.0.0)"
    echo "  minor     Increment minor version (1.0.0 → 1.1.0)"
    echo "  patch     Increment patch version (1.0.0 → 1.0.1)"
    echo ""
    echo "Custom Version:"
    echo "  $0 custom v1.2.3-beta"
    echo ""
    echo "Examples:"
    echo "  $0 patch              # 1.0.2 → 1.0.3"
    echo "  $0 minor              # 1.0.3 → 1.1.0"
    echo "  $0 major              # 1.1.0 → 2.0.0"
    echo "  $0 custom v2.0.0-rc1  # Custom version"
}

# Main function
main() {
    local action="$1"
    local custom_version="$2"
    
    case "$action" in
        major|minor|patch)
            release "$action"
            ;;
        custom)
            if [ -z "$custom_version" ]; then
                log_error "Custom version required"
                usage
                exit 1
            fi
            release "custom" "$custom_version"
            ;;
        help|--help|-h)
            usage
            exit 0
            ;;
        *)
            log_error "Invalid action: $action"
            echo ""
            usage
            exit 1
            ;;
    esac
}

# Run main function with all arguments
main "$@"
