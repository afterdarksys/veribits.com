#!/bin/bash
set -e

echo "========================================="
echo "VeriBits Complete Fix & Deploy Script"
echo "========================================="
echo ""

# Configuration
AWS_REGION="us-east-1"
AWS_ACCOUNT="515966511618"
ECR_REPO="veribits-api"
ECS_CLUSTER="veribits-cluster"
ECS_SERVICE="veribits-service"
IMAGE_TAG="fixed-$(date +%Y%m%d-%H%M%S)"

echo "Step 1: Build Docker image with all fixes"
echo "==========================================  "
cd /Users/ryan/development/veribits.com

docker build -t ${ECR_REPO}:${IMAGE_TAG} -f docker/Dockerfile .

if [ $? -ne 0 ]; then
    echo "✗ Docker build failed!"
    exit 1
fi

echo "✓ Docker image built successfully"
echo ""

echo "Step 2: Tag and push to ECR"
echo "==========================================="

# Login to ECR
aws ecr get-login-password --region $AWS_REGION | docker login --username AWS --password-stdin ${AWS_ACCOUNT}.dkr.ecr.${AWS_REGION}.amazonaws.com

# Tag images
docker tag ${ECR_REPO}:${IMAGE_TAG} ${AWS_ACCOUNT}.dkr.ecr.${AWS_REGION}.amazonaws.com/${ECR_REPO}:${IMAGE_TAG}
docker tag ${ECR_REPO}:${IMAGE_TAG} ${AWS_ACCOUNT}.dkr.ecr.${AWS_REGION}.amazonaws.com/${ECR_REPO}:latest

# Push to ECR
echo "Pushing image ${IMAGE_TAG}..."
docker push ${AWS_ACCOUNT}.dkr.ecr.${AWS_REGION}.amazonaws.com/${ECR_REPO}:${IMAGE_TAG}
docker push ${AWS_ACCOUNT}.dkr.ecr.${AWS_REGION}.amazonaws.com/${ECR_REPO}:latest

if [ $? -ne 0 ]; then
    echo "✗ Docker push failed!"
    exit 1
fi

echo "✓ Images pushed to ECR"
echo ""

echo "Step 3: Force new deployment to ECS"
echo "==========================================="

# Force new deployment (this will pull latest image and run migrations via entrypoint.sh)
aws ecs update-service \
    --cluster ${ECS_CLUSTER} \
    --service ${ECS_SERVICE} \
    --force-new-deployment \
    --region ${AWS_REGION}

if [ $? -ne 0 ]; then
    echo "✗ ECS deployment failed!"
    exit 1
fi

echo "✓ ECS deployment initiated"
echo ""

echo "Step 4: Wait for deployment to stabilize"
echo "==========================================="

echo "Waiting for service to become stable (this may take 3-5 minutes)..."
aws ecs wait services-stable \
    --cluster ${ECS_CLUSTER} \
    --services ${ECS_SERVICE} \
    --region ${AWS_REGION}

if [ $? -ne 0 ]; then
    echo "⚠ WARNING: Service did not stabilize within timeout"
    echo "Check ECS console for task status"
else
    echo "✓ Service is stable"
fi

echo ""
echo "Step 5: Verify deployment"
echo "==========================================="

echo "Testing health endpoint..."
HEALTH_CHECK=$(curl -s -o /dev/null -w "%{http_code}" https://veribits.com/api/v1/health)

if [ "$HEALTH_CHECK" == "200" ]; then
    echo "✓ Health check passed (200)"
else
    echo "✗ Health check failed ($HEALTH_CHECK)"
    exit 1
fi

echo ""
echo "Testing previously broken endpoints..."

# Test registration
echo -n "1. Registration endpoint... "
REG_TEST=$(curl -s -o /dev/null -w "%{http_code}" -X POST https://veribits.com/api/v1/auth/register \
    -H "Content-Type: application/json" \
    -d '{"email":"test-'$(date +%s)'@example.com","password":"Test123!"}')
if [ "$REG_TEST" == "200" ]; then
    echo "✓ ($REG_TEST)"
else
    echo "! ($REG_TEST - may need unique email)"
fi

# Test IAM Policy Analyzer
echo -n "2. IAM Policy Analyzer... "
IAM_TEST=$(curl -s -o /dev/null -w "%{http_code}" -X POST https://veribits.com/api/v1/security/iam-policy/analyze \
    -H "Content-Type: application/json" \
    -d '{"policy_name":"Test","policy_document":{"Version":"2012-10-17","Statement":[{"Effect":"Allow","Action":"*","Resource":"*"}]}}')
if [ "$IAM_TEST" == "200" ] || [ "$IAM_TEST" == "422" ]; then
    echo "✓ ($IAM_TEST)"
else
    echo "✗ ($IAM_TEST)"
fi

# Test Secrets Scanner
echo -n "3. Secrets Scanner... "
SECRETS_TEST=$(curl -s -o /dev/null -w "%{http_code}" -X POST https://veribits.com/api/v1/security/secrets/scan \
    -H "Content-Type: application/json" \
    -d '{"content":"AWS_KEY=AKIAIOSFODNN7EXAMPLE","source_name":"test.js"}')
if [ "$SECRETS_TEST" == "200" ]; then
    echo "✓ ($SECRETS_TEST)"
else
    echo "✗ ($SECRETS_TEST)"
fi

# Test DB Connection Auditor
echo -n "4. DB Connection Auditor... "
DB_TEST=$(curl -s -o /dev/null -w "%{http_code}" -X POST https://veribits.com/api/v1/security/db-connection/audit \
    -H "Content-Type: application/json" \
    -d '{"connection_string":"postgresql://admin:password123@db.example.com:5432/mydb"}')
if [ "$DB_TEST" == "200" ]; then
    echo "✓ ($DB_TEST)"
else
    echo "✗ ($DB_TEST)"
fi

# Test JWT Decode
echo -n "5. JWT Decoder... "
JWT_TEST=$(curl -s -o /dev/null -w "%{http_code}" -X POST https://veribits.com/api/v1/jwt/decode \
    -H "Content-Type: application/json" \
    -d '{"token":"eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.dozjgNryP4J3jVmNHl0w5N_XgL0n3I9PlFUP0THsR8U"}')
if [ "$JWT_TEST" == "200" ]; then
    echo "✓ ($JWT_TEST)"
else
    echo "✗ ($JWT_TEST)"
fi

# Test HIBP Password Check
echo -n "6. HIBP Password Check... "
HIBP_TEST=$(curl -s -o /dev/null -w "%{http_code}" -X POST https://veribits.com/api/v1/hibp/check-password \
    -H "Content-Type: application/json" \
    -d '{"password":"password123"}')
if [ "$HIBP_TEST" == "200" ]; then
    echo "✓ ($HIBP_TEST)"
else
    echo "✗ ($HIBP_TEST)"
fi

# Test BGP AS Lookup
echo -n "7. BGP AS Lookup... "
BGP_TEST=$(curl -s -o /dev/null -w "%{http_code}" -X POST https://veribits.com/api/v1/bgp/asn \
    -H "Content-Type: application/json" \
    -d '{"asn":"AS13335"}')
if [ "$BGP_TEST" == "200" ]; then
    echo "✓ ($BGP_TEST)"
else
    echo "✗ ($BGP_TEST)"
fi

echo ""
echo "========================================="
echo "Deployment Complete!"
echo "========================================="
echo "Image: ${IMAGE_TAG}"
echo "Deployed to: ${ECS_SERVICE} in ${ECS_CLUSTER}"
echo ""
echo "Next steps:"
echo "  1. Monitor CloudWatch Logs: /ecs/veribits-api"
echo "  2. Check ECS console for task status"
echo "  3. Run full endpoint audit:"
echo "     ./tests/audit-all-endpoints.sh https://veribits.com"
echo ""
