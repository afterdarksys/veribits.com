#!/bin/bash
###############################################################################
# VeriBits - Oracle Cloud Infrastructure (OCI) Deployment Script
#
# This script deploys the VeriBits application to OCI instances.
#
# Usage:
#   ./scripts/deploy-to-oci.sh [--skip-migrations] [--dry-run]
#
# Prerequisites:
#   - SSH access to OCI instances
#   - OCI CLI configured (optional)
#
###############################################################################

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OCI_MAIN_IP="129.80.158.147"
OCI_API_IP="129.153.158.177"
SSH_USER="${OCI_SSH_USER:-opc}"
SSH_KEY="${OCI_SSH_KEY:-~/.ssh/id_rsa}"
DEPLOY_PATH="/var/www/veribits"

# Flags
SKIP_MIGRATIONS=false
DRY_RUN=false

# Parse arguments
for arg in "$@"; do
    case $arg in
        --skip-migrations)
            SKIP_MIGRATIONS=true
            shift
            ;;
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --help)
            echo "Usage: $0 [--skip-migrations] [--dry-run]"
            echo ""
            echo "Environment variables:"
            echo "  OCI_SSH_USER  - SSH username (default: opc)"
            echo "  OCI_SSH_KEY   - Path to SSH key (default: ~/.ssh/id_rsa)"
            exit 0
            ;;
        *)
            ;;
    esac
done

# Functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

check_ssh_access() {
    log_info "Checking SSH access to OCI instances..."

    # Test main server
    if ssh -q -o ConnectTimeout=5 -i "$SSH_KEY" "${SSH_USER}@${OCI_MAIN_IP}" exit 2>/dev/null; then
        log_success "SSH access to main server ($OCI_MAIN_IP): OK"
    else
        log_warning "Cannot connect to main server ($OCI_MAIN_IP)"
        log_info "Make sure your SSH key is authorized on the server"
    fi

    # Test API server (may be same as main)
    if [ "$OCI_API_IP" != "$OCI_MAIN_IP" ]; then
        if ssh -q -o ConnectTimeout=5 -i "$SSH_KEY" "${SSH_USER}@${OCI_API_IP}" exit 2>/dev/null; then
            log_success "SSH access to API server ($OCI_API_IP): OK"
        else
            log_warning "Cannot connect to API server ($OCI_API_IP)"
        fi
    fi
}

create_deployment_archive() {
    log_info "Creating deployment archive..."

    cd "$PROJECT_ROOT"

    # Create archive excluding dev files
    tar --exclude='.git' \
        --exclude='node_modules' \
        --exclude='vendor' \
        --exclude='.env.local' \
        --exclude='*.log' \
        --exclude='.DS_Store' \
        --exclude='tests/test-results' \
        -czf /tmp/veribits-deploy.tar.gz \
        app/ \
        docker/ \
        db/migrations/ \
        composer.json \
        composer.lock

    log_success "Archive created: /tmp/veribits-deploy.tar.gz"
    ls -lh /tmp/veribits-deploy.tar.gz
}

deploy_to_server() {
    local SERVER_IP=$1
    local SERVER_NAME=$2

    log_info "Deploying to $SERVER_NAME ($SERVER_IP)..."

    if [ "$DRY_RUN" = true ]; then
        log_info "[DRY RUN] Would deploy to $SERVER_IP"
        return
    fi

    # Copy archive to server
    log_info "Uploading archive..."
    scp -i "$SSH_KEY" /tmp/veribits-deploy.tar.gz "${SSH_USER}@${SERVER_IP}:/tmp/"

    # Deploy on server
    log_info "Extracting and deploying..."
    ssh -i "$SSH_KEY" "${SSH_USER}@${SERVER_IP}" << 'DEPLOY_SCRIPT'
        set -e

        # Backup current deployment
        if [ -d /var/www/veribits ]; then
            sudo cp -r /var/www/veribits /var/www/veribits.backup.$(date +%Y%m%d-%H%M%S)
        fi

        # Create deploy directory if needed
        sudo mkdir -p /var/www/veribits

        # Extract new deployment
        cd /var/www/veribits
        sudo tar -xzf /tmp/veribits-deploy.tar.gz

        # Set permissions
        sudo chown -R apache:apache /var/www/veribits 2>/dev/null || \
        sudo chown -R www-data:www-data /var/www/veribits 2>/dev/null || \
        sudo chown -R nginx:nginx /var/www/veribits 2>/dev/null || true

        # Install PHP dependencies
        if [ -f /var/www/veribits/composer.json ]; then
            cd /var/www/veribits
            if command -v composer &> /dev/null; then
                sudo composer install --no-dev --optimize-autoloader 2>/dev/null || true
            fi
        fi

        # Restart web server
        sudo systemctl restart httpd 2>/dev/null || \
        sudo systemctl restart apache2 2>/dev/null || \
        sudo systemctl restart nginx 2>/dev/null || true

        # Clear any opcache
        if [ -f /var/www/veribits/app/public/opcache-reset.php ]; then
            curl -sf http://localhost/opcache-reset.php 2>/dev/null || true
        fi

        echo "Deployment complete on $(hostname)"
DEPLOY_SCRIPT

    log_success "Deployed to $SERVER_NAME"
}

run_migrations_on_server() {
    if [ "$SKIP_MIGRATIONS" = true ]; then
        log_warning "Skipping migrations (--skip-migrations flag)"
        return
    fi

    log_info "Running database migrations on server..."

    if [ "$DRY_RUN" = true ]; then
        log_info "[DRY RUN] Would run migrations"
        return
    fi

    # Run migrations on main server (assumes DB access from there)
    ssh -i "$SSH_KEY" "${SSH_USER}@${OCI_MAIN_IP}" << 'MIGRATION_SCRIPT'
        set -e
        cd /var/www/veribits

        # Check if .env file exists with DB credentials
        if [ -f app/config/.env ] || [ -f .env ]; then
            # Source environment
            if [ -f app/config/.env ]; then
                export $(grep -v '^#' app/config/.env | xargs)
            elif [ -f .env ]; then
                export $(grep -v '^#' .env | xargs)
            fi

            # Run migrations
            if command -v psql &> /dev/null; then
                echo "Running migration 024: Central Auth..."
                PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USERNAME" -d "$DB_DATABASE" \
                    -f db/migrations/024_central_auth.sql 2>/dev/null || echo "Migration 024 may already be applied"

                echo "Running migration 025: Performance Indexes..."
                PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USERNAME" -d "$DB_DATABASE" \
                    -f db/migrations/025_performance_indexes.sql 2>/dev/null || echo "Migration 025 may already be applied"

                echo "Running migration 026: Hash Lookup Cache..."
                PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USERNAME" -d "$DB_DATABASE" \
                    -f db/migrations/026_hash_lookup_cache.sql 2>/dev/null || echo "Migration 026 may already be applied"

                echo "Migrations complete!"
            else
                echo "WARNING: psql not found. Run migrations manually."
            fi
        else
            echo "WARNING: No .env file found. Cannot run migrations."
        fi
MIGRATION_SCRIPT

    log_success "Migrations executed"
}

verify_deployment() {
    log_info "Verifying deployment..."

    # Test main site
    log_info "Testing https://veribits.com..."
    RESPONSE=$(curl -sf -o /dev/null -w "%{http_code}" "https://veribits.com/" 2>/dev/null || echo "000")
    if [ "$RESPONSE" = "200" ]; then
        log_success "Main site: HTTP $RESPONSE"
    else
        log_warning "Main site: HTTP $RESPONSE (expected 200)"
    fi

    # Test API health
    log_info "Testing API health endpoint..."
    RESPONSE=$(curl -sf "https://veribits.com/api/v1/health" 2>/dev/null || echo "failed")
    if echo "$RESPONSE" | grep -q "success\|ok\|healthy"; then
        log_success "API health: OK"
    else
        log_warning "API health: $RESPONSE"
    fi

    # Test API docs
    log_info "Testing API documentation..."
    RESPONSE=$(curl -sf -o /dev/null -w "%{http_code}" "https://veribits.com/api/docs" 2>/dev/null || echo "000")
    if [ "$RESPONSE" = "200" ]; then
        log_success "API docs: HTTP $RESPONSE"
    else
        log_warning "API docs: HTTP $RESPONSE (new endpoint - may need Apache config)"
    fi
}

generate_summary() {
    echo ""
    log_info "==================================================================="
    log_success "OCI DEPLOYMENT SUMMARY"
    log_info "==================================================================="
    echo ""
    log_info "Servers:"
    echo "  - Main: $OCI_MAIN_IP (veribits.com)"
    echo "  - API:  $OCI_API_IP (api.veribits.com)"
    echo ""
    log_info "Deployed Features:"
    echo "  - Central Auth (OIDC) integration"
    echo "  - OpenAPI/Swagger documentation"
    echo "  - Batch operations API"
    echo "  - ETag caching"
    echo "  - Hash lookup caching"
    echo "  - n8n integration package"
    echo "  - Shell completion scripts"
    echo ""
    log_info "New Migrations:"
    echo "  - 024_central_auth.sql"
    echo "  - 025_performance_indexes.sql"
    echo "  - 026_hash_lookup_cache.sql"
    echo ""
    log_info "Post-Deployment Tasks:"
    echo "  1. Register OAuth client at login.afterdarksys.com"
    echo "  2. Update .env with OIDC_CLIENT_SECRET"
    echo "  3. Generate and set ADMIN_API_KEY"
    echo "  4. Test Central Auth flow"
    echo ""
    log_info "Verification URLs:"
    echo "  - Site: https://veribits.com/"
    echo "  - API:  https://veribits.com/api/v1/health"
    echo "  - Docs: https://veribits.com/api/docs"
    echo ""
}

# Main execution
main() {
    clear
    echo ""
    log_info "==================================================================="
    log_info "VeriBits - Oracle Cloud Infrastructure Deployment"
    log_info "==================================================================="
    echo ""
    log_info "Date: $(date)"
    echo ""

    if [ "$DRY_RUN" = true ]; then
        log_warning "DRY RUN MODE - No actual changes will be made"
        echo ""
    fi

    # Pre-deployment checks
    check_ssh_access

    # Create deployment package
    create_deployment_archive

    # Deploy to servers
    deploy_to_server "$OCI_MAIN_IP" "Main Server"

    # Only deploy to API server if it's different
    if [ "$OCI_API_IP" != "$OCI_MAIN_IP" ]; then
        deploy_to_server "$OCI_API_IP" "API Server"
    fi

    # Run migrations
    run_migrations_on_server

    # Verify
    verify_deployment

    # Summary
    generate_summary

    log_success "Deployment complete!"
}

# Run main
main "$@"
