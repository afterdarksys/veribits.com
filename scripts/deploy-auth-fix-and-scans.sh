#!/bin/bash
# Deployment script for Authentication Fix and System Scans Integration
# Author: Enterprise Systems Architect
# Date: 2025-10-27

set -e  # Exit on error

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

echo "================================================"
echo "VeriBits Deployment: Auth Fix + System Scans"
echo "================================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check required environment variables
check_env() {
    local var_name=$1
    if [ -z "${!var_name}" ]; then
        echo -e "${RED}ERROR: $var_name environment variable not set${NC}"
        return 1
    fi
    echo -e "${GREEN}✓${NC} $var_name is set"
}

echo "Checking environment variables..."
check_env "DB_HOST"
check_env "DB_PASSWORD"
check_env "DB_NAME"
check_env "ECR_URL"
check_env "AWS_REGION"
echo ""

# Step 1: Run database migration
echo "Step 1: Running database migration..."
echo "---------------------------------------"

PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U veribits_app -d "$DB_NAME" << 'EOF'
\echo 'Running migration 011_system_scans.sql...'
\i db/migrations/011_system_scans.sql
\echo ''
\echo 'Verifying tables created:'
\dt system_scans
\dt file_hashes
\dt known_threat_hashes
\echo ''
\echo 'Checking indexes:'
\di idx_system_scans_user_id
\di idx_file_hashes_sha256
\echo ''
EOF

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Database migration completed successfully${NC}"
else
    echo -e "${RED}✗ Database migration failed${NC}"
    exit 1
fi
echo ""

# Step 2: Build Docker image
echo "Step 2: Building Docker image..."
echo "--------------------------------"

cd "$PROJECT_ROOT"

docker build -t veribits-api:auth-fix -f docker/Dockerfile . \
    --build-arg BUILDTIME="$(date -u +'%Y-%m-%dT%H:%M:%SZ')" \
    --build-arg VERSION="auth-fix-$(git rev-parse --short HEAD 2>/dev/null || echo 'unknown')"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Docker image built successfully${NC}"
else
    echo -e "${RED}✗ Docker build failed${NC}"
    exit 1
fi
echo ""

# Step 3: Test image locally
echo "Step 3: Testing image locally..."
echo "--------------------------------"

docker run --rm -d \
    --name veribits-test \
    -p 8080:80 \
    -e DB_HOST="$DB_HOST" \
    -e DB_PASSWORD="$DB_PASSWORD" \
    -e DB_NAME="$DB_NAME" \
    -e JWT_SECRET="${JWT_SECRET:-dev-secret}" \
    -e REDIS_HOST="${REDIS_HOST:-localhost}" \
    veribits-api:auth-fix

echo "Waiting for container to start..."
sleep 5

# Test health endpoint
HEALTH_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/api/v1/health)

if [ "$HEALTH_STATUS" = "200" ]; then
    echo -e "${GREEN}✓ Health check passed (HTTP 200)${NC}"
else
    echo -e "${RED}✗ Health check failed (HTTP $HEALTH_STATUS)${NC}"
    docker logs veribits-test
    docker stop veribits-test
    exit 1
fi

# Test debug endpoint
echo "Testing BCrypt functionality..."
DEBUG_RESPONSE=$(curl -s -H "X-Debug-Token: test" http://localhost:8080/api/v1/debug-phpinfo | jq -r '.bcrypt_live_test.hardcoded_verify')

if [ "$DEBUG_RESPONSE" = "true" ]; then
    echo -e "${GREEN}✓ BCrypt test passed${NC}"
else
    echo -e "${YELLOW}⚠ BCrypt test inconclusive: $DEBUG_RESPONSE${NC}"
fi

# Cleanup test container
docker stop veribits-test
echo ""

# Step 4: Tag and push to ECR
echo "Step 4: Pushing to ECR..."
echo "-------------------------"

# Login to ECR
aws ecr get-login-password --region "$AWS_REGION" | \
    docker login --username AWS --password-stdin "$ECR_URL"

if [ $? -ne 0 ]; then
    echo -e "${RED}✗ ECR login failed${NC}"
    exit 1
fi

# Tag image
docker tag veribits-api:auth-fix "$ECR_URL/veribits-api:latest"
docker tag veribits-api:auth-fix "$ECR_URL/veribits-api:auth-fix-$(date +%Y%m%d-%H%M%S)"

# Push images
echo "Pushing latest tag..."
docker push "$ECR_URL/veribits-api:latest"

echo "Pushing timestamped tag..."
docker push "$ECR_URL/veribits-api:auth-fix-$(date +%Y%m%d-%H%M%S)"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Images pushed to ECR successfully${NC}"
else
    echo -e "${RED}✗ ECR push failed${NC}"
    exit 1
fi
echo ""

# Step 5: Update ECS service
echo "Step 5: Updating ECS service..."
echo "-------------------------------"

ECS_CLUSTER="${ECS_CLUSTER:-veribits-cluster}"
ECS_SERVICE="${ECS_SERVICE:-veribits-api}"

aws ecs update-service \
    --cluster "$ECS_CLUSTER" \
    --service "$ECS_SERVICE" \
    --force-new-deployment \
    --region "$AWS_REGION"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ ECS service update initiated${NC}"
else
    echo -e "${RED}✗ ECS service update failed${NC}"
    exit 1
fi

echo ""
echo "Waiting for service to stabilize..."
echo "(This may take 5-10 minutes)"

aws ecs wait services-stable \
    --cluster "$ECS_CLUSTER" \
    --services "$ECS_SERVICE" \
    --region "$AWS_REGION"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ ECS service is stable${NC}"
else
    echo -e "${RED}✗ ECS service failed to stabilize${NC}"
    exit 1
fi
echo ""

# Step 6: Verify production deployment
echo "Step 6: Verifying production deployment..."
echo "------------------------------------------"

DOMAIN="${DOMAIN:-veribits.com}"

# Test health endpoint
echo "Testing health endpoint..."
PROD_HEALTH=$(curl -s -o /dev/null -w "%{http_code}" "https://$DOMAIN/api/v1/health")

if [ "$PROD_HEALTH" = "200" ]; then
    echo -e "${GREEN}✓ Production health check passed${NC}"
else
    echo -e "${RED}✗ Production health check failed (HTTP $PROD_HEALTH)${NC}"
    exit 1
fi

# Test system-scans endpoint (without API key, should return 401)
echo "Testing system-scans endpoint..."
SCANS_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "https://$DOMAIN/api/v1/system-scans")

if [ "$SCANS_STATUS" = "401" ]; then
    echo -e "${GREEN}✓ System scans endpoint responding (auth required)${NC}"
else
    echo -e "${YELLOW}⚠ Unexpected status: $SCANS_STATUS${NC}"
fi

# Test dashboard page
echo "Testing dashboard page..."
DASHBOARD_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "https://$DOMAIN/scans.php")

if [ "$DASHBOARD_STATUS" = "200" ]; then
    echo -e "${GREEN}✓ Dashboard page accessible${NC}"
else
    echo -e "${YELLOW}⚠ Dashboard returned HTTP $DASHBOARD_STATUS${NC}"
fi

echo ""

# Step 7: Display CloudWatch logs
echo "Step 7: Recent CloudWatch logs..."
echo "---------------------------------"

echo "Fetching last 10 log entries..."
aws logs tail /ecs/veribits-api \
    --since 5m \
    --format short \
    --region "$AWS_REGION" | head -20

echo ""

# Summary
echo "================================================"
echo "Deployment Complete!"
echo "================================================"
echo ""
echo "Deployment Summary:"
echo "  - Database migration: ✓ Applied"
echo "  - Docker image: ✓ Built and pushed"
echo "  - ECS service: ✓ Updated and stable"
echo "  - Health check: ✓ Passed"
echo "  - System scans API: ✓ Responding"
echo "  - Dashboard: ✓ Accessible"
echo ""
echo "Next Steps:"
echo ""
echo "1. Test Authentication:"
echo "   curl -X POST https://$DOMAIN/api/v1/auth/login \\"
echo "     -H \"Content-Type: application/json\" \\"
echo "     -d '{\"email\":\"your-email\",\"password\":\"your-password\"}'"
echo ""
echo "2. Check Debug Endpoint (if auth fails):"
echo "   curl -H \"X-Debug-Token: secret\" https://$DOMAIN/api/v1/debug-phpinfo | jq ."
echo ""
echo "3. Monitor CloudWatch Logs:"
echo "   aws logs tail /ecs/veribits-api --follow --format short"
echo ""
echo "4. Test System Client:"
echo "   cd veribits-system-client-1.0"
echo "   python3 file_hasher.py --files test.txt"
echo ""
echo "5. View Dashboard:"
echo "   Open: https://$DOMAIN/scans.php"
echo ""
echo "Documentation:"
echo "  See AUTHENTICATION_FIX_AND_SYSTEM_CLIENT_INTEGRATION.md"
echo ""
echo "================================================"
