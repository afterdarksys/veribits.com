#!/bin/bash
###############################################################################
# VeriBits Stripe Integration - Database Migration
#
# Run this script to add Stripe payment processing support
#
# Usage: ./scripts/run-stripe-migration.sh
###############################################################################

set -e  # Exit on error

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Database configuration
DB_HOST="nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com"
DB_USER="nitetext"
DB_NAME="veribits"
PGPASSWORD="NiteText2025!SecureProd"

export PGPASSWORD

echo -e "${BLUE}╔════════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║  VeriBits Stripe Integration - Database Migration             ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Test connection
echo -e "${BLUE}[1/4]${NC} Testing database connection..."
if psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -c "SELECT version();" > /dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} Connected successfully"
else
    echo -e "${RED}✗${NC} Cannot connect to database"
    echo -e "${YELLOW}Make sure you're running this from a host with RDS access${NC}"
    exit 1
fi

# Check if migration already ran
echo ""
echo -e "${BLUE}[2/4]${NC} Checking migration status..."
ALREADY_RAN=$(psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -tAc "SELECT COUNT(*) FROM information_schema.columns WHERE table_name='users' AND column_name='stripe_customer_id';" 2>/dev/null || echo "0")

if [ "$ALREADY_RAN" != "0" ]; then
    echo -e "${YELLOW}⚠${NC} Stripe migration appears to have already run"
    read -p "Continue anyway? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo -e "${BLUE}Migration cancelled${NC}"
        exit 0
    fi
fi

# Create backup
echo ""
echo -e "${BLUE}[3/4]${NC} Creating database backup..."
BACKUP_FILE="veribits_backup_stripe_$(date +%Y%m%d_%H%M%S).sql"
if pg_dump -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -f "$BACKUP_FILE" 2>/dev/null; then
    echo -e "${GREEN}✓${NC} Backup created: $BACKUP_FILE"
else
    echo -e "${YELLOW}⚠${NC} Backup skipped (pg_dump not available)"
fi

# Run migration
echo ""
echo -e "${BLUE}[4/4]${NC} Running Stripe integration migration..."

cd "$(dirname "$0")/.."

if [ ! -f "db/migrations/023_stripe_integration.sql" ]; then
    echo -e "${RED}✗${NC} Migration file not found: db/migrations/023_stripe_integration.sql"
    exit 1
fi

# Run the migration with verbose output
echo -e "  ${YELLOW}→${NC} Executing migration 023..."
if psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -f "db/migrations/023_stripe_integration.sql" 2>&1 | grep -v "^$"; then
    echo -e "  ${GREEN}✓${NC} Migration 023 completed"
else
    echo -e "  ${RED}✗${NC} Migration 023 failed"
    exit 1
fi

# Verify changes
echo ""
echo -e "${BLUE}Verifying changes...${NC}"

# Check for new columns
echo -e "  ${YELLOW}→${NC} Checking users.stripe_customer_id column..."
if psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -tAc "SELECT COUNT(*) FROM information_schema.columns WHERE table_name='users' AND column_name='stripe_customer_id';" | grep -q "1"; then
    echo -e "  ${GREEN}✓${NC} Column added successfully"
else
    echo -e "  ${RED}✗${NC} Column not found"
fi

echo -e "  ${YELLOW}→${NC} Checking billing_accounts.stripe_subscription_id column..."
if psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -tAc "SELECT COUNT(*) FROM information_schema.columns WHERE table_name='billing_accounts' AND column_name='stripe_subscription_id';" | grep -q "1"; then
    echo -e "  ${GREEN}✓${NC} Column added successfully"
else
    echo -e "  ${RED}✗${NC} Column not found"
fi

# Check for new tables
echo -e "  ${YELLOW}→${NC} Checking stripe_events table..."
if psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -tAc "SELECT COUNT(*) FROM information_schema.tables WHERE table_name='stripe_events';" | grep -q "1"; then
    echo -e "  ${GREEN}✓${NC} Table created successfully"
else
    echo -e "  ${RED}✗${NC} Table not found"
fi

echo -e "  ${YELLOW}→${NC} Checking stripe_checkout_sessions table..."
if psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -tAc "SELECT COUNT(*) FROM information_schema.tables WHERE table_name='stripe_checkout_sessions';" | grep -q "1"; then
    echo -e "  ${GREEN}✓${NC} Table created successfully"
else
    echo -e "  ${RED}✗${NC} Table not found"
fi

# List new tables
echo ""
echo -e "${BLUE}New Stripe tables:${NC}"
psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -c "\dt stripe*" 2>/dev/null | tail -n +4 | head -n -2 | awk '{print "  ✓ " $2}'

echo ""
echo -e "${GREEN}╔════════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║  ✓ Stripe Integration Migration Completed Successfully!        ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}Next Steps:${NC}"
echo "  1. Run: composer install (to install Stripe PHP library)"
echo "  2. Create Stripe products in Dashboard"
echo "  3. Update STRIPE_PRICE_PRO and STRIPE_PRICE_ENTERPRISE in .env"
echo "  4. Configure webhook in Stripe Dashboard"
echo "  5. Test subscription flow with test cards"
echo ""
echo -e "${YELLOW}Backup file:${NC} $BACKUP_FILE"
echo ""
