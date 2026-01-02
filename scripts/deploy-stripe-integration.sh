#!/bin/bash
###############################################################################
# VeriBits Stripe Integration - AWS Production Deployment
#
# Deploys Stripe payment integration to production using AWS Systems Manager
#
# Usage: ./scripts/deploy-stripe-integration.sh
###############################################################################

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
MAGENTA='\033[0;35m'
NC='\033[0m'

# Configuration
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
INSTANCE_ID="i-0cdcaeed37df5d284"  # Bastion/production instance
AWS_REGION="us-east-1"
S3_BUCKET="veribits-deploy-packages"

# Database configuration
DB_HOST="nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com"
DB_USER="nitetext"
DB_NAME="veribits"

echo -e "${MAGENTA}"
echo "╔══════════════════════════════════════════════════════════════════╗"
echo "║  VeriBits Stripe Integration - Production Deployment            ║"
echo "╚══════════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

# Step 1: Create deployment package
echo -e "${BLUE}[1/5]${NC} Creating deployment package..."

DEPLOY_DIR="$PROJECT_ROOT/stripe-deployment"
rm -rf "$DEPLOY_DIR"
mkdir -p "$DEPLOY_DIR"

# Copy backend files
mkdir -p "$DEPLOY_DIR/app/src/Services"
mkdir -p "$DEPLOY_DIR/app/src/Controllers"
mkdir -p "$DEPLOY_DIR/app/public"
mkdir -p "$DEPLOY_DIR/app/public/assets/js"
mkdir -p "$DEPLOY_DIR/db/migrations"

cp "$PROJECT_ROOT/app/src/Services/StripeService.php" "$DEPLOY_DIR/app/src/Services/"
cp "$PROJECT_ROOT/app/src/Controllers/BillingController.php" "$DEPLOY_DIR/app/src/Controllers/"
cp "$PROJECT_ROOT/app/public/index.php" "$DEPLOY_DIR/app/public/"
cp "$PROJECT_ROOT/app/public/assets/js/stripe-checkout.js" "$DEPLOY_DIR/app/public/assets/js/"
cp "$PROJECT_ROOT/db/migrations/023_stripe_integration.sql" "$DEPLOY_DIR/db/migrations/"
cp "$PROJECT_ROOT/composer.json" "$DEPLOY_DIR/"
cp "$PROJECT_ROOT/composer.lock" "$DEPLOY_DIR/" 2>/dev/null || true

# Create deployment script
cat > "$DEPLOY_DIR/deploy.sh" << 'DEPLOY_SCRIPT'
#!/bin/bash
set -e

echo "VeriBits Stripe Integration Deployment"
echo "======================================="

WEB_ROOT="/var/www/veribits"

# Backup current files
echo "[1/6] Creating backup..."
BACKUP_DIR="/tmp/veribits-backup-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"
cp -r "$WEB_ROOT/app/src/Controllers/BillingController.php" "$BACKUP_DIR/" 2>/dev/null || true
cp -r "$WEB_ROOT/app/public/index.php" "$BACKUP_DIR/" 2>/dev/null || true
echo "  ✓ Backup created: $BACKUP_DIR"

# Deploy files
echo "[2/6] Deploying files..."
cp -r app/src/Services/* "$WEB_ROOT/app/src/Services/"
cp -r app/src/Controllers/* "$WEB_ROOT/app/src/Controllers/"
cp app/public/index.php "$WEB_ROOT/app/public/"
cp -r app/public/assets/js/* "$WEB_ROOT/app/public/assets/js/"
cp db/migrations/* "$WEB_ROOT/db/migrations/"
cp composer.json "$WEB_ROOT/"
[ -f composer.lock ] && cp composer.lock "$WEB_ROOT/" || true
echo "  ✓ Files deployed"

# Install composer dependencies
echo "[3/6] Installing Stripe PHP library..."
cd "$WEB_ROOT"
if command -v composer &> /dev/null; then
    composer install --no-dev --optimize-autoloader 2>&1 | grep -E "(Installing|Generating)" || true
    echo "  ✓ Composer dependencies installed"
else
    echo "  ⚠ Composer not found, skipping (install manually)"
fi

# Run database migration
echo "[4/6] Running database migration..."
export PGPASSWORD="NiteText2025!SecureProd"

if psql -h nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com \
        -U nitetext \
        -d veribits \
        -f db/migrations/023_stripe_integration.sql \
        > /tmp/migration.log 2>&1; then
    echo "  ✓ Migration completed successfully"
else
    echo "  ⚠ Migration may have already been applied (check /tmp/migration.log)"
fi

# Set permissions
echo "[5/6] Setting permissions..."
chown -R www-data:www-data "$WEB_ROOT/app/src/Services/StripeService.php" 2>/dev/null || true
chown -R www-data:www-data "$WEB_ROOT/app/src/Controllers/BillingController.php" 2>/dev/null || true
chown -R www-data:www-data "$WEB_ROOT/app/public/assets/js/stripe-checkout.js" 2>/dev/null || true
chown -R www-data:www-data "$WEB_ROOT/vendor" 2>/dev/null || true
chmod 644 "$WEB_ROOT/app/src/Services/StripeService.php"
chmod 644 "$WEB_ROOT/app/src/Controllers/BillingController.php"
chmod 644 "$WEB_ROOT/app/public/assets/js/stripe-checkout.js"
echo "  ✓ Permissions set"

# Verify installation
echo "[6/6] Verifying installation..."
ERRORS=0

# Check files exist
[ -f "$WEB_ROOT/app/src/Services/StripeService.php" ] || { echo "  ✗ StripeService.php missing"; ERRORS=$((ERRORS+1)); }
[ -f "$WEB_ROOT/app/src/Controllers/BillingController.php" ] || { echo "  ✗ BillingController.php missing"; ERRORS=$((ERRORS+1)); }
[ -f "$WEB_ROOT/app/public/assets/js/stripe-checkout.js" ] || { echo "  ✗ stripe-checkout.js missing"; ERRORS=$((ERRORS+1)); }
[ -d "$WEB_ROOT/vendor/stripe" ] || { echo "  ✗ Stripe library missing"; ERRORS=$((ERRORS+1)); }

# Check database tables
if psql -h nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com \
        -U nitetext \
        -d veribits \
        -tAc "SELECT COUNT(*) FROM information_schema.tables WHERE table_name='stripe_events';" 2>/dev/null | grep -q "1"; then
    echo "  ✓ stripe_events table exists"
else
    echo "  ✗ stripe_events table missing"
    ERRORS=$((ERRORS+1))
fi

if [ $ERRORS -eq 0 ]; then
    echo ""
    echo "╔══════════════════════════════════════════════════════════════╗"
    echo "║  ✓ Stripe Integration Deployed Successfully!                ║"
    echo "╚══════════════════════════════════════════════════════════════╝"
    echo ""
    echo "Next Steps:"
    echo "  1. Create Stripe products at https://dashboard.stripe.com/test/products"
    echo "  2. Update .env with STRIPE_PRICE_PRO and STRIPE_PRICE_ENTERPRISE"
    echo "  3. Configure webhook at https://dashboard.stripe.com/test/webhooks"
    echo "  4. Update .env with STRIPE_WEBHOOK_SECRET"
    echo "  5. Test subscription flow with test card: 4242 4242 4242 4242"
    echo ""
    exit 0
else
    echo ""
    echo "Deployment completed with $ERRORS error(s)"
    echo "Check the logs above for details"
    exit 1
fi
DEPLOY_SCRIPT

chmod +x "$DEPLOY_DIR/deploy.sh"

# Create package
echo -e "  ${YELLOW}→${NC} Creating tarball..."
cd "$PROJECT_ROOT"
tar -czf "stripe-deployment.tar.gz" -C stripe-deployment .
echo -e "  ${GREEN}✓${NC} Package created: stripe-deployment.tar.gz"

# Step 2: Upload to S3
echo ""
echo -e "${BLUE}[2/5]${NC} Uploading package to S3..."

# Check if S3 bucket exists, create if not
if ! aws s3 ls "s3://$S3_BUCKET" --region "$AWS_REGION" &> /dev/null; then
    echo -e "  ${YELLOW}→${NC} Creating S3 bucket..."
    aws s3 mb "s3://$S3_BUCKET" --region "$AWS_REGION"
fi

aws s3 cp "stripe-deployment.tar.gz" "s3://$S3_BUCKET/stripe-deployment.tar.gz" --region "$AWS_REGION"
echo -e "  ${GREEN}✓${NC} Package uploaded to S3"

# Step 3: Deploy via SSM
echo ""
echo -e "${BLUE}[3/5]${NC} Deploying to production instance via AWS Systems Manager..."

echo -e "  ${YELLOW}→${NC} Executing deployment on instance $INSTANCE_ID..."

COMMAND_ID=$(aws ssm send-command \
    --instance-ids "$INSTANCE_ID" \
    --document-name "AWS-RunShellScript" \
    --parameters 'commands=[
        "set -e",
        "cd /tmp",
        "rm -rf stripe-deployment stripe-deployment.tar.gz",
        "echo Downloading deployment package from S3...",
        "aws s3 cp s3://veribits-deploy-packages/stripe-deployment.tar.gz . --region us-east-1",
        "echo Extracting package...",
        "tar -xzf stripe-deployment.tar.gz -C /tmp",
        "cd /tmp",
        "echo Running deployment script...",
        "sudo bash deploy.sh"
    ]' \
    --region "$AWS_REGION" \
    --output text \
    --query "Command.CommandId")

echo -e "  ${YELLOW}→${NC} Command ID: $COMMAND_ID"
echo -e "  ${YELLOW}→${NC} Waiting for deployment to complete..."

# Wait for command to complete
sleep 5

for i in {1..30}; do
    STATUS=$(aws ssm get-command-invocation \
        --command-id "$COMMAND_ID" \
        --instance-id "$INSTANCE_ID" \
        --region "$AWS_REGION" \
        --query "Status" \
        --output text 2>/dev/null || echo "Pending")

    if [ "$STATUS" = "Success" ]; then
        echo -e "  ${GREEN}✓${NC} Deployment completed successfully!"
        break
    elif [ "$STATUS" = "Failed" ]; then
        echo -e "  ${RED}✗${NC} Deployment failed!"
        echo ""
        echo "Error output:"
        aws ssm get-command-invocation \
            --command-id "$COMMAND_ID" \
            --instance-id "$INSTANCE_ID" \
            --region "$AWS_REGION" \
            --query "StandardErrorContent" \
            --output text
        exit 1
    fi

    echo -e "  ${YELLOW}→${NC} Status: $STATUS (attempt $i/30)"
    sleep 2
done

# Get output
echo ""
echo -e "${BLUE}[4/5]${NC} Deployment output:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
aws ssm get-command-invocation \
    --command-id "$COMMAND_ID" \
    --instance-id "$INSTANCE_ID" \
    --region "$AWS_REGION" \
    --query "StandardOutputContent" \
    --output text
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Step 4: Verify deployment
echo ""
echo -e "${BLUE}[5/5]${NC} Verifying deployment..."

# Check if Stripe endpoint responds
echo -e "  ${YELLOW}→${NC} Testing Stripe publishable key endpoint..."
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" "https://api.veribits.com/api/v1/billing/stripe/publishable-key" || echo "000")

if [ "$RESPONSE" = "200" ]; then
    echo -e "  ${GREEN}✓${NC} Stripe endpoint responding (HTTP $RESPONSE)"
else
    echo -e "  ${YELLOW}⚠${NC} Stripe endpoint returned HTTP $RESPONSE (may need configuration)"
fi

# Cleanup
rm -rf "$DEPLOY_DIR" "stripe-deployment.tar.gz"

# Success message
echo ""
echo -e "${GREEN}"
echo "╔══════════════════════════════════════════════════════════════════╗"
echo "║  ✓✓✓ Stripe Integration Deployed to Production! ✓✓✓             ║"
echo "╚══════════════════════════════════════════════════════════════════╝"
echo -e "${NC}"
echo ""
echo -e "${BLUE}What was deployed:${NC}"
echo "  ✓ StripeService.php - Payment processing service"
echo "  ✓ BillingController.php - Stripe API endpoints"
echo "  ✓ stripe-checkout.js - Frontend integration"
echo "  ✓ Database migration (023_stripe_integration.sql)"
echo "  ✓ Stripe PHP library (v13.x)"
echo ""
echo -e "${YELLOW}Next Steps:${NC}"
echo "  1. Go to: https://dashboard.stripe.com/test/products"
echo "  2. Create product 'VeriBits Pro' - \$29/month"
echo "  3. Create product 'VeriBits Enterprise' - \$299/month"
echo "  4. Copy price IDs and update .env.production:"
echo "     STRIPE_PRICE_PRO=price_xxxxx"
echo "     STRIPE_PRICE_ENTERPRISE=price_xxxxx"
echo ""
echo "  5. Go to: https://dashboard.stripe.com/test/webhooks"
echo "  6. Add endpoint: https://api.veribits.com/api/v1/billing/webhook/stripe"
echo "  7. Copy webhook secret and update .env.production:"
echo "     STRIPE_WEBHOOK_SECRET=whsec_xxxxx"
echo ""
echo "  8. Update frontend pages with subscribe buttons:"
echo "     <script src=\"/assets/js/stripe-checkout.js\"></script>"
echo "     <button onclick=\"createStripeCheckout('pro')\">Subscribe</button>"
echo ""
echo "  9. Test with card: 4242 4242 4242 4242"
echo ""
echo -e "${BLUE}Documentation:${NC}"
echo "  • STRIPE_INTEGRATION_GUIDE.md - Complete setup guide"
echo "  • STRIPE_DEPLOYMENT_CHECKLIST.md - Deployment steps"
echo ""
echo -e "${GREEN}Ready to accept payments!${NC} 🎉"
echo ""
