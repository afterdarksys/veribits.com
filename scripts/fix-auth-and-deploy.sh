#!/bin/bash
set -e

echo "========================================="
echo "CRITICAL FIX: password_verify() Failure"
echo "========================================="

# Configuration
ECR_REPO="014498623950.dkr.ecr.us-east-1.amazonaws.com/veribits"
ECS_CLUSTER="veribits-cluster"
ECS_SERVICE="veribits-api"
REGION="us-east-1"

echo ""
echo "PHASE 1: Build and Test Docker Image Locally"
echo "============================================="

# Build the image
echo "Building Docker image with fixes..."
docker build -t veribits:auth-fix -f docker/Dockerfile .

# Test password verification in local container
echo ""
echo "Testing password verification in local container..."
docker run --rm veribits:auth-fix php -r "
\$password = 'TestPassword123!';
\$hash = '\$2y\$12\$eKJCykdGXuNZ.k/lJQtHF.f51GG/Uetdhuqm0BU6cGYAlEYkCfAG2';

echo 'Testing password_verify()...' . PHP_EOL;
\$result1 = password_verify(\$password, \$hash);
echo 'password_verify() result: ' . (\$result1 ? 'PASS' : 'FAIL') . PHP_EOL;

echo 'Testing crypt() fallback...' . PHP_EOL;
\$result2 = crypt(\$password, \$hash);
\$match = hash_equals(\$hash, \$result2);
echo 'crypt() result: ' . (\$match ? 'PASS' : 'FAIL') . PHP_EOL;

if (!\$result1 && !\$match) {
    echo 'CRITICAL: Both methods failed!' . PHP_EOL;
    exit(1);
}
echo 'Local test: SUCCESS' . PHP_EOL;
"

if [ $? -ne 0 ]; then
    echo "FATAL: Local Docker test failed. Fix will not work in ECS either."
    exit 1
fi

echo ""
echo "PHASE 2: Deploy to AWS ECR"
echo "==========================="

# Login to ECR
echo "Logging in to ECR..."
aws ecr get-login-password --region $REGION | docker login --username AWS --password-stdin $ECR_REPO

# Tag and push
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
IMAGE_TAG="auth-fix-${TIMESTAMP}"
echo "Tagging image as: ${IMAGE_TAG}"

docker tag veribits:auth-fix ${ECR_REPO}:${IMAGE_TAG}
docker tag veribits:auth-fix ${ECR_REPO}:latest

echo "Pushing to ECR..."
docker push ${ECR_REPO}:${IMAGE_TAG}
docker push ${ECR_REPO}:latest

echo ""
echo "PHASE 3: Run Database Migration"
echo "================================"

# Get database credentials from AWS Secrets Manager or environment
DB_HOST="${DB_HOST:-nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com}"
DB_NAME="${DB_NAME:-veribits}"
DB_USER="${DB_USER:-veribits}"

if [ -z "$DB_PASSWORD" ]; then
    echo "WARNING: DB_PASSWORD not set. Skipping database migration."
    echo "Run this manually: psql -h $DB_HOST -U $DB_USER -d $DB_NAME -f db/migrations/011_fix_password_hash_encoding.sql"
else
    echo "Running migration 011 to clean corrupted password hashes..."
    PGPASSWORD=$DB_PASSWORD psql -h $DB_HOST -U $DB_USER -d $DB_NAME -f db/migrations/011_fix_password_hash_encoding.sql
    echo "Migration complete!"
fi

echo ""
echo "PHASE 4: Update ECS Service"
echo "============================"

# Force new deployment with latest image
echo "Forcing new ECS deployment..."
aws ecs update-service \
    --cluster $ECS_CLUSTER \
    --service $ECS_SERVICE \
    --force-new-deployment \
    --region $REGION

echo "Waiting for deployment to stabilize..."
aws ecs wait services-stable \
    --cluster $ECS_CLUSTER \
    --services $ECS_SERVICE \
    --region $REGION

echo ""
echo "PHASE 5: Verify Fix in Production"
echo "=================================="

# Wait a bit for the new tasks to start
sleep 10

echo "Testing authentication in production..."
echo ""

# Test registration (creates new user with clean hash)
TEST_EMAIL="test-$(date +%s)@example.com"
echo "1. Testing registration with email: $TEST_EMAIL"

REGISTER_RESPONSE=$(curl -s -X POST https://veribits.com/api/v1/auth/register \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"$TEST_EMAIL\",\"password\":\"TestPassword123!\"}")

echo "Register response: $REGISTER_RESPONSE"

if echo "$REGISTER_RESPONSE" | grep -q "success"; then
    echo "✓ Registration: PASS"
else
    echo "✗ Registration: FAIL"
fi

# Test login with the newly created user
sleep 2
echo ""
echo "2. Testing login with newly created user..."

LOGIN_RESPONSE=$(curl -s -X POST https://veribits.com/api/v1/auth/login \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"$TEST_EMAIL\",\"password\":\"TestPassword123!\"}")

echo "Login response: $LOGIN_RESPONSE"

if echo "$LOGIN_RESPONSE" | grep -q "access_token"; then
    echo "✓ Login: PASS"
    echo ""
    echo "========================================="
    echo "SUCCESS: Authentication is working!"
    echo "========================================="
else
    echo "✗ Login: FAIL"
    echo ""
    echo "========================================="
    echo "WARNING: Fix may not be complete"
    echo "========================================="
    echo "Check CloudWatch logs for details:"
    echo "aws logs tail /ecs/veribits-api --follow --region $REGION"
fi

echo ""
echo "Deployment complete!"
echo "New image tag: ${IMAGE_TAG}"
echo ""
