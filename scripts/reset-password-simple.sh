#!/bin/bash
#
# Simple Password Reset Tool for VeriBits
# Usage: ./reset-password-simple.sh <email> [new_password]
#
# If no password provided, generates a secure random one

set -e

EMAIL="${1:-}"
NEW_PASSWORD="${2:-}"

if [ -z "$EMAIL" ]; then
    echo "Usage: $0 <email> [new_password]"
    echo ""
    echo "Examples:"
    echo "  $0 straticus1@gmail.com"
    echo "  $0 rams3377@gmail.com MyNewPass123!"
    exit 1
fi

# Database connection details
DB_HOST="${DB_HOST:-nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com}"
DB_USER="${DB_USER:-nitetext}"
DB_PASS="${DB_PASS:-NiteText2025!SecureProd}"
DB_NAME="${DB_NAME:-veribits}"

# Generate secure password if not provided
if [ -z "$NEW_PASSWORD" ]; then
    NEW_PASSWORD=$(openssl rand -base64 12 | tr -d '/+=' | cut -c1-16)
    echo "🔐 Generated password: $NEW_PASSWORD"
fi

# Generate BCrypt hash using PHP (cost=10)
echo "🔨 Generating BCrypt hash (cost=10)..."
HASH=$(php -r "echo password_hash('$NEW_PASSWORD', PASSWORD_BCRYPT, ['cost' => 10]);")

if [ -z "$HASH" ]; then
    echo "❌ Failed to generate hash"
    exit 1
fi

echo "✅ Hash generated: ${HASH:0:30}..."

# Verify hash locally
echo "🔍 Verifying hash..."
php -r "exit(password_verify('$NEW_PASSWORD', '$HASH') ? 0 : 1);"
if [ $? -eq 0 ]; then
    echo "✅ Hash verified locally"
else
    echo "❌ Hash verification failed!"
    exit 1
fi

# Update database via ECS task (production environment)
echo "💾 Updating password in production database..."

# Use docker to connect to RDS (since local connection may be blocked)
UPDATE_SQL="UPDATE users SET password_hash = '$HASH', updated_at = CURRENT_TIMESTAMP WHERE email = '$EMAIL' RETURNING id, email, status;"

docker run --rm \
    -e PGPASSWORD="$DB_PASS" \
    postgres:15 \
    psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" \
    -c "$UPDATE_SQL" 2>&1

if [ $? -eq 0 ]; then
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "✅ Password reset complete!"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "📋 Login Credentials:"
    echo "   Email:    $EMAIL"
    echo "   Password: $NEW_PASSWORD"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    echo "🧪 Test login:"
    echo "curl -X POST https://veribits.com/api/v1/auth/login \\"
    echo "  -H 'Content-Type: application/json' \\"
    echo "  -d '{\"email\":\"$EMAIL\",\"password\":\"$NEW_PASSWORD\"}'"
else
    echo "❌ Failed to update database"
    exit 1
fi
