#!/bin/bash
# Remove sensitive files from git and regenerate secrets
# Run this script to fix BUG #1 and #2

set -e

echo "========================================="
echo "VeriBits Security Cleanup"
echo "========================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Remove .env files from git
echo -e "${YELLOW}Step 1: Removing .env files from git...${NC}"
git rm --cached app/.env 2>/dev/null || echo "app/.env not in git"
git rm --cached .env.production 2>/dev/null || echo ".env.production not in git"

# Update .gitignore
echo -e "${YELLOW}Step 2: Updating .gitignore...${NC}"
if ! grep -q "^app/.env$" .gitignore 2>/dev/null; then
    echo "app/.env" >> .gitignore
    echo "Added app/.env to .gitignore"
fi

if ! grep -q "^.env.production$" .gitignore 2>/dev/null; then
    echo ".env.production" >> .gitignore
    echo "Added .env.production to .gitignore"
fi

# Generate new secrets
echo ""
echo -e "${YELLOW}Step 3: Generating new secrets...${NC}"

# Generate JWT secret (64 characters, base64)
JWT_SECRET=$(openssl rand -base64 48)
echo -e "${GREEN}New JWT_SECRET generated${NC}"

# Generate admin secret (32 characters)
ADMIN_SECRET=$(openssl rand -hex 32)
echo -e "${GREEN}New ADMIN_SECRET generated${NC}"

# Generate database password (32 characters, alphanumeric)
DB_PASSWORD=$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-32)
echo -e "${GREEN}New DB_PASSWORD generated${NC}"

echo ""
echo -e "${YELLOW}Step 4: Updating environment variables...${NC}"

# Create secure .env file
cat > app/.env.secure << EOF
# Generated on $(date)
APP_ENV=production

# Security - ROTATE THESE IN PRODUCTION
JWT_SECRET=${JWT_SECRET}
ADMIN_SECRET=${ADMIN_SECRET}

# Database - UPDATE WITH YOUR RDS CREDENTIALS
DB_HOST=your-rds-endpoint.us-east-1.rds.amazonaws.com
DB_PORT=5432
DB_DATABASE=veribits
DB_USERNAME=veribits_admin
DB_PASSWORD=${DB_PASSWORD}
DB_DRIVER=pgsql

# Redis - UPDATE WITH YOUR ELASTICACHE ENDPOINT
REDIS_HOST=your-redis-cluster.cache.amazonaws.com
REDIS_PORT=6379

# OpenAI API Key (optional)
OPENAI_API_KEY=

# Third-party API keys (optional)
VIRUS_TOTAL_API_KEY=
BLOCKCHAIN_API_KEY=
EOF

echo -e "${GREEN}Created app/.env.secure${NC}"

# Remove dangerous admin file
echo ""
echo -e "${YELLOW}Step 5: Removing dangerous admin file...${NC}"
if [ -f "app/public/admin/create_user.php" ]; then
    git rm app/public/admin/create_user.php 2>/dev/null || rm app/public/admin/create_user.php
    echo -e "${GREEN}Removed app/public/admin/create_user.php${NC}"
fi

echo ""
echo -e "${GREEN}=========================================${NC}"
echo -e "${GREEN}Security cleanup complete!${NC}"
echo -e "${GREEN}=========================================${NC}"
echo ""
echo -e "${YELLOW}IMPORTANT NEXT STEPS:${NC}"
echo ""
echo "1. Review app/.env.secure and update with actual credentials"
echo "2. Deploy new secrets to AWS Parameter Store or Secrets Manager:"
echo ""
echo "   aws ssm put-parameter \\"
echo "     --name /veribits/production/JWT_SECRET \\"
echo "     --value '${JWT_SECRET}' \\"
echo "     --type SecureString \\"
echo "     --overwrite"
echo ""
echo "   aws ssm put-parameter \\"
echo "     --name /veribits/production/ADMIN_SECRET \\"
echo "     --value '${ADMIN_SECRET}' \\"
echo "     --type SecureString \\"
echo "     --overwrite"
echo ""
echo "3. Update ECS task definition with new environment variables"
echo ""
echo "4. Commit and push changes:"
echo "   git add .gitignore"
echo "   git commit -m 'Security: Remove secrets from git, update .gitignore'"
echo "   git push"
echo ""
echo "5. ROTATE ALL PRODUCTION SECRETS IMMEDIATELY"
echo ""
echo -e "${RED}WARNING: All existing JWT tokens will be invalidated${NC}"
echo -e "${RED}         Users will need to log in again${NC}"
echo ""
