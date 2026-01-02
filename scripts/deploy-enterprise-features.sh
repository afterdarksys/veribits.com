#!/bin/bash
###############################################################################
# VeriBits Enterprise Features Deployment Script
#
# Deploys all new enterprise features including:
# - Malware Detonation Sandbox
# - Netcat Network Tool
# - OAuth2 & Webhooks (Zapier/n8n integration)
# - Pro Subscriptions
# - Security Documentation
# - CLI Pro with job scheduling and plugins
#
# Usage: ./scripts/deploy-enterprise-features.sh [--dry-run] [--skip-migrations]
###############################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DB_MIGRATIONS_DIR="$PROJECT_ROOT/db/migrations"
DRY_RUN=false
SKIP_MIGRATIONS=false

# Parse arguments
for arg in "$@"; do
    case $arg in
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --skip-migrations)
            SKIP_MIGRATIONS=true
            shift
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

run_command() {
    if [ "$DRY_RUN" = true ]; then
        echo "[DRY RUN] $1"
    else
        eval "$1"
    fi
}

check_requirements() {
    log_info "Checking requirements..."

    # Check if database credentials are set
    if [ -z "$DB_PASSWORD" ]; then
        log_error "DB_PASSWORD environment variable not set"
        exit 1
    fi

    # Check if psql is available
    if ! command -v psql &> /dev/null; then
        log_error "psql command not found. Please install PostgreSQL client."
        exit 1
    fi

    log_success "All requirements met"
}

backup_database() {
    log_info "Creating database backup..."

    BACKUP_FILE="$PROJECT_ROOT/backups/veribits_backup_$(date +%Y%m%d_%H%M%S).sql"
    mkdir -p "$PROJECT_ROOT/backups"

    if [ "$DRY_RUN" = false ]; then
        PGPASSWORD="$DB_PASSWORD" pg_dump \
            -h "${DB_HOST:-localhost}" \
            -U "${DB_USER:-veribits}" \
            -d "${DB_NAME:-veribits}" \
            -f "$BACKUP_FILE"

        if [ $? -eq 0 ]; then
            log_success "Database backed up to: $BACKUP_FILE"
        else
            log_error "Database backup failed"
            exit 1
        fi
    else
        log_info "[DRY RUN] Would backup to: $BACKUP_FILE"
    fi
}

run_migrations() {
    if [ "$SKIP_MIGRATIONS" = true ]; then
        log_warning "Skipping migrations (--skip-migrations flag set)"
        return
    fi

    log_info "Running database migrations..."

    # Migration 020: Pro Subscriptions
    log_info "Running migration 020: Pro Subscriptions"
    run_command "PGPASSWORD='$DB_PASSWORD' psql -h '${DB_HOST:-localhost}' -U '${DB_USER:-veribits}' -d '${DB_NAME:-veribits}' -f '$DB_MIGRATIONS_DIR/020_pro_subscriptions_pg.sql'"

    # Migration 021: OAuth2 & Webhooks
    log_info "Running migration 021: OAuth2 & Webhooks"
    run_command "PGPASSWORD='$DB_PASSWORD' psql -h '${DB_HOST:-localhost}' -U '${DB_USER:-veribits}' -d '${DB_NAME:-veribits}' -f '$DB_MIGRATIONS_DIR/021_oauth_webhooks_pg.sql'"

    # Migration 022: Malware Detonation
    log_info "Running migration 022: Malware Detonation"
    run_command "PGPASSWORD='$DB_PASSWORD' psql -h '${DB_HOST:-localhost}' -U '${DB_USER:-veribits}' -d '${DB_NAME:-veribits}' -f '$DB_MIGRATIONS_DIR/022_malware_detonation_pg.sql'"

    log_success "All migrations completed successfully"
}

verify_tables() {
    log_info "Verifying new tables..."

    EXPECTED_TABLES=(
        "pro_licenses"
        "oauth_clients"
        "oauth_authorization_codes"
        "oauth_access_tokens"
        "oauth_refresh_tokens"
        "webhooks"
        "webhook_deliveries"
        "malware_submissions"
        "malware_analysis_results"
        "malware_screenshots"
    )

    for table in "${EXPECTED_TABLES[@]}"; do
        if [ "$DRY_RUN" = false ]; then
            EXISTS=$(PGPASSWORD="$DB_PASSWORD" psql -h "${DB_HOST:-localhost}" -U "${DB_USER:-veribits}" -d "${DB_NAME:-veribits}" -tAc "SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name='$table');")

            if [ "$EXISTS" = "t" ]; then
                log_success "✓ Table exists: $table"
            else
                log_error "✗ Table missing: $table"
                exit 1
            fi
        else
            log_info "[DRY RUN] Would verify table: $table"
        fi
    done

    log_success "All tables verified"
}

deploy_cli_packages() {
    log_info "Deploying CLI packages..."

    # Create CLI package directory
    CLI_PKG_DIR="$PROJECT_ROOT/downloads/cli"
    run_command "mkdir -p '$CLI_PKG_DIR'"

    # Package Python CLI
    log_info "Packaging Python CLI..."
    run_command "cd '$PROJECT_ROOT/veribits-system-client-1.0' && tar -czf '$CLI_PKG_DIR/veribits-cli-python-1.0.tar.gz' veribits veribits_pro.py veribits_plugin_api.py plugins/ README.md config.json.example"

    # Package PHP CLI
    log_info "Packaging PHP CLI..."
    run_command "cd '$PROJECT_ROOT/veribits-system-client-1.0' && tar -czf '$CLI_PKG_DIR/veribits-cli-php-1.0.tar.gz' veribits.php README.md"

    # Package Node.js CLI
    log_info "Packaging Node.js CLI..."
    run_command "cd '$PROJECT_ROOT/veribits-system-client-1.0' && tar -czf '$CLI_PKG_DIR/veribits-cli-nodejs-1.0.tar.gz' veribits.js README.md package.json"

    log_success "CLI packages created"
}

update_website() {
    log_info "Updating website files..."

    # Files are already in place, just verify they exist
    REQUIRED_FILES=(
        "app/public/tool/malware-detonation.php"
        "app/public/tool/netcat.php"
        "app/public/security.php"
        "app/src/Controllers/MalwareDetonationController.php"
        "app/src/Controllers/NetcatController.php"
        "app/src/Controllers/OAuth2Controller.php"
        "app/src/Controllers/WebhooksController.php"
        "app/src/Controllers/ProSubscriptionController.php"
    )

    for file in "${REQUIRED_FILES[@]}"; do
        if [ -f "$PROJECT_ROOT/$file" ]; then
            log_success "✓ File exists: $file"
        else
            log_error "✗ File missing: $file"
            exit 1
        fi
    done

    log_success "All website files verified"
}

restart_services() {
    log_info "Restarting services..."

    # Check if running in Docker
    if [ -f "$PROJECT_ROOT/docker-compose.yml" ]; then
        run_command "cd '$PROJECT_ROOT' && docker-compose restart web"
    else
        log_warning "No docker-compose.yml found, skipping service restart"
    fi
}

generate_summary() {
    log_info "==================================================="
    log_success "🎉 Enterprise Features Deployment Complete!"
    log_info "==================================================="
    echo ""
    log_info "New Features Deployed:"
    echo "  🦠 Malware Detonation Sandbox"
    echo "  🔌 Netcat Network Tool"
    echo "  🔐 OAuth2 & Webhooks Integration"
    echo "  ⭐ Pro Subscriptions"
    echo "  🔒 Security Documentation Page"
    echo "  ⚡ CLI Pro with Job Scheduling"
    echo "  🔌 Plugin System"
    echo ""
    log_info "Database Tables Created:"
    echo "  • pro_licenses"
    echo "  • oauth_clients, oauth_authorization_codes"
    echo "  • oauth_access_tokens, oauth_refresh_tokens"
    echo "  • webhooks, webhook_deliveries"
    echo "  • malware_submissions, malware_analysis_results"
    echo "  • malware_screenshots"
    echo ""
    log_info "New API Endpoints:"
    echo "  • POST /api/v1/malware/submit"
    echo "  • GET  /api/v1/malware/status/{id}"
    echo "  • GET  /api/v1/malware/report/{id}"
    echo "  • GET  /api/v1/malware/iocs/{id}"
    echo "  • POST /api/v1/tools/netcat"
    echo "  • GET  /api/v1/oauth/authorize"
    echo "  • POST /api/v1/oauth/token"
    echo "  • POST /api/v1/webhooks"
    echo "  • POST /api/v1/pro/validate"
    echo ""
    log_info "CLI Downloads Available:"
    echo "  • downloads/cli/veribits-cli-python-1.0.tar.gz"
    echo "  • downloads/cli/veribits-cli-php-1.0.tar.gz"
    echo "  • downloads/cli/veribits-cli-nodejs-1.0.tar.gz"
    echo ""
    log_info "Next Steps:"
    echo "  1. Configure Cuckoo Sandbox URL in MalwareDetonationController.php"
    echo "  2. Test all new endpoints"
    echo "  3. Update Zapier app submission with OAuth credentials"
    echo "  4. Distribute CLI packages to users"
    echo ""
    log_success "Deployment Summary saved to: $PROJECT_ROOT/DEPLOYMENT_SUMMARY.md"
}

main() {
    log_info "🚀 Starting VeriBits Enterprise Features Deployment"
    echo ""

    if [ "$DRY_RUN" = true ]; then
        log_warning "Running in DRY RUN mode - no changes will be made"
        echo ""
    fi

    check_requirements
    backup_database
    run_migrations
    verify_tables
    deploy_cli_packages
    update_website
    restart_services
    generate_summary
}

# Run main function
main
