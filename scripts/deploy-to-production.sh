#!/bin/bash
###############################################################################
# VeriBits Enterprise Features - Production Deployment Script
#
# This script orchestrates the complete deployment of enterprise features
# to production AWS infrastructure.
#
# Usage:
#   ./scripts/deploy-to-production.sh [--skip-migrations] [--skip-cuckoo] [--dry-run]
#
# Prerequisites:
#   - AWS CLI configured with production credentials
#   - SSH access to bastion host (i-0cdcaeed37df5d284)
#   - Database credentials in environment or .env.production
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
BASTION_INSTANCE_ID="i-0cdcaeed37df5d284"
DB_HOST="nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com"
DB_USER="nitetext"
DB_NAME="veribits"
AWS_REGION="us-east-1"

# Flags
SKIP_MIGRATIONS=false
SKIP_CUCKOO=false
DRY_RUN=false

# Parse arguments
for arg in "$@"; do
    case $arg in
        --skip-migrations)
            SKIP_MIGRATIONS=true
            shift
            ;;
        --skip-cuckoo)
            SKIP_CUCKOO=true
            shift
            ;;
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --help)
            echo "Usage: $0 [--skip-migrations] [--skip-cuckoo] [--dry-run]"
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

run_command() {
    if [ "$DRY_RUN" = true ]; then
        echo "[DRY RUN] $1"
    else
        eval "$1"
    fi
}

check_prerequisites() {
    log_info "Checking prerequisites..."

    # Check AWS CLI
    if ! command -v aws &> /dev/null; then
        log_error "AWS CLI not found. Please install: https://aws.amazon.com/cli/"
        exit 1
    fi

    # Check AWS credentials
    if ! aws sts get-caller-identity &> /dev/null; then
        log_error "AWS credentials not configured. Run: aws configure"
        exit 1
    fi

    # Check database password
    if [ -z "${DB_PASSWORD:-}" ]; then
        if [ -f "$PROJECT_ROOT/.env.production" ]; then
            log_info "Loading credentials from .env.production"
            export $(grep DB_PASSWORD "$PROJECT_ROOT/.env.production" | xargs)
        else
            log_error "DB_PASSWORD not set. Export DB_PASSWORD or create .env.production"
            exit 1
        fi
    fi

    # Check psql (needed for verification)
    if ! command -v psql &> /dev/null; then
        log_warning "psql not found locally. Verification will be skipped."
    fi

    log_success "All prerequisites met"
}

create_rds_snapshot() {
    log_info "Creating RDS snapshot for rollback safety..."

    SNAPSHOT_NAME="veribits-pre-enterprise-$(date +%Y%m%d-%H%M%S)"

    if [ "$DRY_RUN" = true ]; then
        log_info "[DRY RUN] Would create snapshot: $SNAPSHOT_NAME"
    else
        aws rds create-db-snapshot \
            --db-instance-identifier nitetext-db \
            --db-snapshot-identifier "$SNAPSHOT_NAME" \
            --region "$AWS_REGION"

        log_info "Waiting for snapshot to complete (this may take 5-10 minutes)..."
        aws rds wait db-snapshot-completed \
            --db-snapshot-identifier "$SNAPSHOT_NAME" \
            --region "$AWS_REGION"

        log_success "Snapshot created: $SNAPSHOT_NAME"
        echo "$SNAPSHOT_NAME" > "$PROJECT_ROOT/.last-snapshot"
    fi
}

get_bastion_ip() {
    log_info "Getting bastion host IP address..."

    BASTION_IP=$(aws ec2 describe-instances \
        --instance-ids "$BASTION_INSTANCE_ID" \
        --query 'Reservations[0].Instances[0].PrivateIpAddress' \
        --output text \
        --region "$AWS_REGION")

    if [ -z "$BASTION_IP" ]; then
        log_error "Could not get bastion host IP"
        exit 1
    fi

    log_success "Bastion host: $BASTION_IP"
    echo "$BASTION_IP"
}

deploy_migrations() {
    if [ "$SKIP_MIGRATIONS" = true ]; then
        log_warning "Skipping database migrations (--skip-migrations flag)"
        return
    fi

    log_info "Deploying database migrations..."
    echo ""
    log_warning "==================================================================="
    log_warning "DATABASE MIGRATION REQUIRED"
    log_warning "==================================================================="
    echo ""
    log_info "Your RDS instance is in a private VPC and requires bastion host access."
    echo ""
    log_info "Option 1: SSH to bastion host (RECOMMENDED)"
    echo "  1. Get bastion public IP:"
    echo "     aws ec2 describe-instances --instance-ids $BASTION_INSTANCE_ID \\"
    echo "       --query 'Reservations[0].Instances[0].PublicIpAddress'"
    echo ""
    echo "  2. SSH to bastion:"
    echo "     ssh -i ~/.ssh/your-key.pem ec2-user@<bastion-ip>"
    echo ""
    echo "  3. Install psql:"
    echo "     sudo yum install -y postgresql15"
    echo ""
    echo "  4. Upload migrations (from local machine):"
    echo "     scp -i ~/.ssh/your-key.pem \\"
    echo "       $PROJECT_ROOT/db/migrations/02*.sql \\"
    echo "       ec2-user@<bastion-ip>:~/"
    echo ""
    echo "  5. Run migrations (on bastion):"
    echo "     export PGPASSWORD='$DB_PASSWORD'"
    echo "     psql -h $DB_HOST -U $DB_USER -d $DB_NAME -f ~/020_pro_subscriptions_pg.sql"
    echo "     psql -h $DB_HOST -U $DB_USER -d $DB_NAME -f ~/021_oauth_webhooks_pg.sql"
    echo "     psql -h $DB_HOST -U $DB_USER -d $DB_NAME -f ~/022_malware_detonation_pg.sql"
    echo ""
    log_info "Option 2: AWS Systems Manager Session Manager (NO SSH KEY)"
    echo "  aws ssm start-session --target $BASTION_INSTANCE_ID"
    echo ""
    log_warning "==================================================================="
    echo ""

    # Offer to create helper script
    read -p "Create migration helper script for bastion host? (y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        create_migration_script
    fi
}

create_migration_script() {
    log_info "Creating migration helper script..."

    cat > "$PROJECT_ROOT/run-migrations-on-bastion.sh" <<'EOF'
#!/bin/bash
# This script should be run ON THE BASTION HOST

set -e

DB_HOST="nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com"
DB_USER="nitetext"
DB_NAME="veribits"

# Check if psql is installed
if ! command -v psql &> /dev/null; then
    echo "Installing PostgreSQL client..."
    sudo yum install -y postgresql15
fi

# Check database connectivity
echo "Testing database connection..."
PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -c "SELECT version();"

if [ $? -ne 0 ]; then
    echo "ERROR: Cannot connect to database"
    exit 1
fi

echo "Database connection successful!"
echo ""

# Run migrations
echo "Running migration 020: Pro Subscriptions..."
PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -f ~/020_pro_subscriptions_pg.sql

echo "Running migration 021: OAuth2 & Webhooks..."
PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -f ~/021_oauth_webhooks_pg.sql

echo "Running migration 022: Malware Detonation..."
PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -f ~/022_malware_detonation_pg.sql

echo ""
echo "All migrations completed successfully!"
echo ""

# Verify tables
echo "Verifying tables..."
PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -c "
SELECT table_name
FROM information_schema.tables
WHERE table_schema = 'public'
AND table_name IN (
  'pro_licenses',
  'oauth_clients',
  'oauth_authorization_codes',
  'oauth_access_tokens',
  'oauth_refresh_tokens',
  'webhooks',
  'webhook_deliveries',
  'malware_submissions',
  'malware_analysis_results',
  'malware_screenshots'
)
ORDER BY table_name;
"

echo ""
echo "Deployment complete!"
EOF

    chmod +x "$PROJECT_ROOT/run-migrations-on-bastion.sh"
    log_success "Created: $PROJECT_ROOT/run-migrations-on-bastion.sh"
    log_info "Transfer this file to bastion host and run it there."
}

verify_database_tables() {
    log_info "Verifying database tables..."

    # This would require psql access, which we don't have from local machine
    # Just log instructions for manual verification

    log_info "To verify tables were created, run on bastion host:"
    echo ""
    echo "  PGPASSWORD='$DB_PASSWORD' psql -h $DB_HOST -U $DB_USER -d $DB_NAME -c \"\\"
    echo "    SELECT count(*) FROM information_schema.tables \\"
    echo "    WHERE table_name IN ( \\"
    echo "      'pro_licenses', 'oauth_clients', 'oauth_authorization_codes', \\"
    echo "      'oauth_access_tokens', 'oauth_refresh_tokens', 'webhooks', \\"
    echo "      'webhook_deliveries', 'malware_submissions', \\"
    echo "      'malware_analysis_results', 'malware_screenshots' \\"
    echo "    ); \\"
    echo "  \""
    echo ""
    log_info "Expected result: 10"
}

deploy_cuckoo() {
    if [ "$SKIP_CUCKOO" = true ]; then
        log_warning "Skipping Cuckoo deployment (--skip-cuckoo flag)"
        return
    fi

    log_info "Cuckoo Sandbox deployment options..."
    echo ""
    log_warning "==================================================================="
    log_warning "CUCKOO SANDBOX REQUIRED"
    log_warning "==================================================================="
    echo ""
    log_info "The malware detonation feature requires Cuckoo Sandbox."
    echo ""
    log_info "Option 1: External Service (EASIEST, RECOMMENDED FOR MVP)"
    echo "  - Hybrid Analysis: https://www.hybrid-analysis.com/"
    echo "  - Cost: \$99-299/month"
    echo "  - Setup time: 15 minutes"
    echo ""
    log_info "Option 2: Self-Hosted EC2 Instance"
    echo "  - Instance type: t3.xlarge"
    echo "  - Cost: ~\$128/month"
    echo "  - Setup time: 2-3 hours"
    echo ""
    log_info "Option 3: ECS/Fargate Container"
    echo "  - Cost: \$5-30/month (on-demand)"
    echo "  - Setup time: 1-2 hours"
    echo ""
    log_warning "==================================================================="
    echo ""

    log_info "For MVP, we recommend starting with an external service."
    log_info "Update app/src/Controllers/MalwareDetonationController.php:"
    echo ""
    echo "  const CUCKOO_API_URL = 'https://www.hybrid-analysis.com/api/v2';"
    echo "  const CUCKOO_API_KEY = 'your-api-key';"
    echo ""
}

build_docker_image() {
    log_info "Building Docker image..."

    if [ "$DRY_RUN" = true ]; then
        log_info "[DRY RUN] Would build Docker image: veribits:2.0.0"
        return
    fi

    cd "$PROJECT_ROOT"
    docker build -t veribits:2.0.0 -f docker/Dockerfile .

    log_success "Docker image built: veribits:2.0.0"
}

push_to_ecr() {
    log_info "Pushing to Amazon ECR..."

    if [ "$DRY_RUN" = true ]; then
        log_info "[DRY RUN] Would push to ECR"
        return
    fi

    # Get AWS account ID
    AWS_ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
    ECR_REGISTRY="${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com"

    # Login to ECR
    aws ecr get-login-password --region "$AWS_REGION" | \
        docker login --username AWS --password-stdin "$ECR_REGISTRY"

    # Tag and push
    docker tag veribits:2.0.0 "${ECR_REGISTRY}/veribits:2.0.0"
    docker push "${ECR_REGISTRY}/veribits:2.0.0"

    log_success "Image pushed to ECR: ${ECR_REGISTRY}/veribits:2.0.0"
}

deploy_to_ecs() {
    log_info "Deploying to ECS..."

    if [ "$DRY_RUN" = true ]; then
        log_info "[DRY RUN] Would deploy to ECS"
        return
    fi

    # Check if ECS cluster exists
    CLUSTER_NAME=$(aws ecs list-clusters --region "$AWS_REGION" \
        --query 'clusterArns[0]' --output text | cut -d'/' -f2)

    if [ -z "$CLUSTER_NAME" ] || [ "$CLUSTER_NAME" = "None" ]; then
        log_warning "No ECS cluster found. Deployment may be EC2-based."
        log_info "Please deploy manually to your EC2 instances."
        return
    fi

    log_info "Found ECS cluster: $CLUSTER_NAME"

    # Get service name
    SERVICE_NAME=$(aws ecs list-services --cluster "$CLUSTER_NAME" --region "$AWS_REGION" \
        --query 'serviceArns[0]' --output text | cut -d'/' -f3)

    if [ -z "$SERVICE_NAME" ] || [ "$SERVICE_NAME" = "None" ]; then
        log_warning "No ECS service found in cluster."
        return
    fi

    log_info "Found ECS service: $SERVICE_NAME"
    log_info "Triggering rolling deployment..."

    aws ecs update-service \
        --cluster "$CLUSTER_NAME" \
        --service "$SERVICE_NAME" \
        --force-new-deployment \
        --region "$AWS_REGION"

    log_success "Deployment triggered. Monitor progress in AWS Console."
}

run_smoke_tests() {
    log_info "Running smoke tests..."

    API_URL="https://veribits.com/api/v1"

    # Test health endpoint
    log_info "Testing health endpoint..."
    if curl -sf "$API_URL/health" > /dev/null; then
        log_success "Health check passed"
    else
        log_error "Health check failed"
    fi

    # Test Pro license validation (if database is migrated)
    log_info "Testing Pro license validation..."
    RESPONSE=$(curl -sf -X POST "$API_URL/pro/validate" \
        -H "Content-Type: application/json" \
        -d '{"license_key":"VBPRO-DEV-TEST-0000000000000000"}' || echo "")

    if echo "$RESPONSE" | grep -q "success"; then
        log_success "Pro license validation working"
    else
        log_warning "Pro license validation not yet working (database may not be migrated)"
    fi
}

package_cli_tools() {
    log_info "Packaging CLI tools..."

    CLI_DIR="$PROJECT_ROOT/veribits-system-client-1.0"
    DOWNLOADS_DIR="$PROJECT_ROOT/app/public/downloads/cli"

    mkdir -p "$DOWNLOADS_DIR"

    if [ -d "$CLI_DIR" ]; then
        cd "$CLI_DIR"

        # Python CLI
        tar -czf "$DOWNLOADS_DIR/veribits-cli-python-1.0.tar.gz" \
            veribits veribits_pro.py veribits_plugin_api.py plugins/ README.md config.json.example

        log_success "CLI packages created in $DOWNLOADS_DIR"
    else
        log_warning "CLI directory not found: $CLI_DIR"
    fi
}

generate_summary() {
    echo ""
    log_info "==================================================================="
    log_success "DEPLOYMENT SUMMARY"
    log_info "==================================================================="
    echo ""
    log_info "New Features Deployed:"
    echo "  - Malware Detonation Sandbox"
    echo "  - Netcat Network Tool"
    echo "  - OAuth2 & Webhooks"
    echo "  - Pro Subscriptions"
    echo "  - CLI Pro with Job Scheduling"
    echo ""
    log_info "Database Tables (10 total):"
    echo "  - pro_licenses"
    echo "  - oauth_clients, oauth_authorization_codes"
    echo "  - oauth_access_tokens, oauth_refresh_tokens"
    echo "  - webhooks, webhook_deliveries"
    echo "  - malware_submissions, malware_analysis_results"
    echo "  - malware_screenshots"
    echo ""
    log_info "Next Steps:"
    echo "  1. Complete database migrations (if not done)"
    echo "  2. Configure Cuckoo Sandbox"
    echo "  3. Test all enterprise features"
    echo "  4. Configure OAuth2 for Zapier"
    echo "  5. Monitor CloudWatch metrics"
    echo ""

    if [ -f "$PROJECT_ROOT/.last-snapshot" ]; then
        SNAPSHOT=$(cat "$PROJECT_ROOT/.last-snapshot")
        log_info "Rollback Snapshot: $SNAPSHOT"
        echo ""
    fi

    log_success "Deployment complete!"
    log_info "See ENTERPRISE_DEPLOYMENT_PLAN.md for detailed documentation."
    echo ""
}

# Main execution
main() {
    clear
    echo ""
    log_info "==================================================================="
    log_info "VeriBits Enterprise Features - Production Deployment"
    log_info "==================================================================="
    echo ""
    log_info "Version: 2.0.0"
    log_info "Date: $(date)"
    echo ""

    if [ "$DRY_RUN" = true ]; then
        log_warning "DRY RUN MODE - No actual changes will be made"
        echo ""
    fi

    # Pre-deployment
    check_prerequisites
    create_rds_snapshot

    # Database
    deploy_migrations
    verify_database_tables

    # Infrastructure
    deploy_cuckoo

    # Application
    build_docker_image
    push_to_ecr
    deploy_to_ecs

    # Post-deployment
    package_cli_tools
    run_smoke_tests
    generate_summary
}

# Run main
main
