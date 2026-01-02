#!/bin/bash
###############################################################################
# VeriBits Enterprise Features - Database Migrations
#
# Run this script from a machine that has access to the RDS instance
# (e.g., EC2 instance, bastion host, or with VPN access)
#
# Usage: ./scripts/run-migrations.sh
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
echo -e "${BLUE}║  VeriBits Enterprise Features - Database Migrations           ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Test connection
echo -e "${BLUE}[1/5]${NC} Testing database connection..."
if psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -c "SELECT version();" > /dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} Connected successfully"
else
    echo -e "${RED}✗${NC} Cannot connect to database"
    echo -e "${YELLOW}Make sure you're running this from a host with RDS access${NC}"
    exit 1
fi

# Create backup
echo ""
echo -e "${BLUE}[2/5]${NC} Creating database backup..."
BACKUP_FILE="veribits_backup_$(date +%Y%m%d_%H%M%S).sql"
if pg_dump -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -f "$BACKUP_FILE" 2>/dev/null; then
    echo -e "${GREEN}✓${NC} Backup created: $BACKUP_FILE"
    echo -e "${YELLOW}Keep this file safe for rollback if needed${NC}"
else
    echo -e "${RED}✗${NC} Backup failed"
    exit 1
fi

# Run migrations
echo ""
echo -e "${BLUE}[3/5]${NC} Running database migrations..."

cd "$(dirname "$0")/.."

# Migration 020
echo -e "  ${YELLOW}→${NC} Running migration 020: Pro Subscriptions"
psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -f "db/migrations/020_pro_subscriptions_pg.sql" > /dev/null 2>&1
echo -e "  ${GREEN}✓${NC} Migration 020 completed"

# Migration 021
echo -e "  ${YELLOW}→${NC} Running migration 021: OAuth2 & Webhooks"
psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -f "db/migrations/021_oauth_webhooks_pg.sql" > /dev/null 2>&1
echo -e "  ${GREEN}✓${NC} Migration 021 completed"

# Migration 022
echo -e "  ${YELLOW}→${NC} Running migration 022: Malware Detonation"
psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -f "db/migrations/022_malware_detonation_pg.sql" > /dev/null 2>&1
echo -e "  ${GREEN}✓${NC} Migration 022 completed"

# Migration 023
echo -e "  ${YELLOW}→${NC} Running migration 023: Stripe Integration"
psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -f "db/migrations/023_stripe_integration.sql" > /dev/null 2>&1
echo -e "  ${GREEN}✓${NC} Migration 023 completed"

# Verify tables
echo ""
echo -e "${BLUE}[4/5]${NC} Verifying new tables..."
psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -c "\dt" | grep -E "(pro_licenses|oauth|webhooks|malware|stripe)" | awk '{print "  ✓ " $2}'

echo ""
echo -e "${GREEN}╔════════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║  ✓ Database Migrations Completed Successfully!                 ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}Next Steps:${NC}"
echo "  1. Test API endpoints"
echo "  2. Configure Cuckoo Sandbox"
echo "  3. Monitor for 24-48 hours"
echo ""
