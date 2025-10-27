#!/bin/bash
set -e

# Production database connection details
DB_HOST="nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com"
DB_PORT="5432"
DB_USER="nitetext"
DB_NAME="veribits"
DB_PASSWORD="NiteText2025!SecureProd"

echo "========================================="
echo "Running Migration 010: Security Scanning Tools"
echo "========================================="
echo "Database: $DB_NAME @ $DB_HOST"
echo ""

# Check if psql is available
if ! command -v psql &> /dev/null; then
    echo "ERROR: psql is not installed. Please install PostgreSQL client."
    echo "macOS: brew install postgresql"
    echo "Ubuntu: apt-get install postgresql-client"
    exit 1
fi

# Test connection first
echo "Testing database connection..."
PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -c "SELECT version();" > /dev/null 2>&1

if [ $? -eq 0 ]; then
    echo "✓ Database connection successful"
else
    echo "✗ Database connection failed"
    exit 1
fi

echo ""
echo "Running migration file: 010_security_scanning_tools.sql"
echo ""

# Run the migration
PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -f "/Users/ryan/development/veribits.com/db/migrations/010_security_scanning_tools.sql"

if [ $? -eq 0 ]; then
    echo ""
    echo "========================================="
    echo "✓ Migration completed successfully!"
    echo "========================================="
    echo ""
    echo "Verifying tables were created..."
    PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -c "
        SELECT
            table_name
        FROM information_schema.tables
        WHERE table_schema = 'public'
        AND table_name IN (
            'security_scans',
            'iam_policy_scans',
            'secret_scans',
            'db_connection_scans',
            'docker_image_scans',
            'terraform_scans',
            'k8s_manifest_scans',
            'api_security_scans',
            'sbom_scans',
            'security_header_scans',
            'scan_statistics'
        )
        ORDER BY table_name;
    "
    echo ""
    echo "All tables created successfully! ✓"
else
    echo ""
    echo "✗ Migration failed!"
    exit 1
fi
