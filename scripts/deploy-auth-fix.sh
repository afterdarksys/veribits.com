#!/bin/bash
# Deploy API Authentication Fix
# This script builds, pushes, and deploys the auth fix to production
# Fix: Apache .htaccess rewrite rule now includes [PT] flag

set -e

cd /Users/ryan/development/veribits.com

echo "🔧 Deploying API Authentication Fix"
echo "===================================="
echo ""

# Verify the fix is present
if grep -q "\[PT\]" app/public/.htaccess; then
    echo "✅ Verified: .htaccess contains [PT] flag"
else
    echo "❌ ERROR: .htaccess missing [PT] flag!"
    echo "The fix may not be applied. Please verify app/public/.htaccess"
    exit 1
fi
echo ""

# Build Docker image
echo "📦 Building Docker image..."
docker build -t veribits-app:auth-fix -f docker/Dockerfile .

# Tag for ECR - Using veribits-app repository (correct one)
AWS_ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
echo "📋 AWS Account: $AWS_ACCOUNT_ID"

docker tag veribits-app:auth-fix $AWS_ACCOUNT_ID.dkr.ecr.us-east-1.amazonaws.com/veribits-app:auth-fix
docker tag veribits-app:auth-fix $AWS_ACCOUNT_ID.dkr.ecr.us-east-1.amazonaws.com/veribits-app:latest

# Login to ECR
echo "🔐 Logging into ECR..."
aws ecr get-login-password --region us-east-1 | \
  docker login --username AWS --password-stdin $AWS_ACCOUNT_ID.dkr.ecr.us-east-1.amazonaws.com

# Push to ECR
echo "📤 Pushing to ECR (veribits-app repository)..."
docker push $AWS_ACCOUNT_ID.dkr.ecr.us-east-1.amazonaws.com/veribits-app:auth-fix
docker push $AWS_ACCOUNT_ID.dkr.ecr.us-east-1.amazonaws.com/veribits-app:latest

# Get new image digest
echo "🔍 Getting new image digest..."
NEW_DIGEST=$(aws ecr describe-images \
  --repository-name veribits-app \
  --image-ids imageTag=latest \
  --query 'imageDetails[0].imageDigest' \
  --output text \
  --region us-east-1)

echo "✅ New image: $AWS_ACCOUNT_ID.dkr.ecr.us-east-1.amazonaws.com/veribits-app@$NEW_DIGEST"
echo ""

# Force ECS service update
echo "🚀 Forcing ECS service update..."
aws ecs update-service \
  --cluster veribits-cluster \
  --service veribits-api-service \
  --force-new-deployment \
  --region us-east-1 > /dev/null

echo "✅ Deployment initiated!"
echo ""
echo "⏳ Waiting for service to stabilize (this takes 2-3 minutes)..."

# Wait for service stability
ATTEMPTS=0
MAX_ATTEMPTS=40
while [ $ATTEMPTS -lt $MAX_ATTEMPTS ]; do
    RUNNING_COUNT=$(aws ecs describe-services \
        --cluster veribits-cluster \
        --services veribits-api-service \
        --region us-east-1 \
        --query 'services[0].runningCount' \
        --output text)

    DESIRED_COUNT=$(aws ecs describe-services \
        --cluster veribits-cluster \
        --services veribits-api-service \
        --region us-east-1 \
        --query 'services[0].desiredCount' \
        --output text)

    DEPLOYMENTS=$(aws ecs describe-services \
        --cluster veribits-cluster \
        --services veribits-api-service \
        --region us-east-1 \
        --query 'length(services[0].deployments)' \
        --output text)

    echo -ne "\r   Running: ${RUNNING_COUNT}/${DESIRED_COUNT} | Active Deployments: ${DEPLOYMENTS} | Check: $((ATTEMPTS+1))/${MAX_ATTEMPTS}   "

    # Stable when only one deployment and running count matches desired
    if [ "$DEPLOYMENTS" -eq 1 ] && [ "$RUNNING_COUNT" -eq "$DESIRED_COUNT" ]; then
        echo ""
        echo "✅ Service stabilized!"
        break
    fi

    sleep 5
    ATTEMPTS=$((ATTEMPTS + 1))
done

echo ""
echo "⏳ Waiting 15 seconds for ALB health checks..."
sleep 15
echo ""

# Test health endpoint
echo "🩺 Testing health endpoint..."
HEALTH_STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://api.veribits.com/api/v1/health)
if [ "$HEALTH_STATUS" = "200" ]; then
    echo "✅ Health check passed (HTTP 200)"
else
    echo "⚠️  Health check returned HTTP ${HEALTH_STATUS}"
fi
echo ""

# Test login endpoint
echo "🧪 Testing login endpoint..."
LOGIN_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST https://api.veribits.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"straticus1@gmail.com","password":"TestPassword123!"}')

LOGIN_CODE=$(echo "$LOGIN_RESPONSE" | tail -n 1)
LOGIN_BODY=$(echo "$LOGIN_RESPONSE" | head -n -1)

echo "Status: HTTP ${LOGIN_CODE}"
echo "$LOGIN_BODY" | python3 -m json.tool 2>/dev/null || echo "$LOGIN_BODY"
echo ""

if [ "$LOGIN_CODE" = "200" ] && echo "$LOGIN_BODY" | grep -q "access_token"; then
  echo "════════════════════════════════════════"
  echo "🎉 SUCCESS! Authentication is working!"
  echo "════════════════════════════════════════"
  echo ""
  echo "✅ Login endpoint operational"
  echo "✅ JWT tokens being generated"
  echo "✅ Ready for production use"
  echo ""
  echo "Next steps:"
  echo "  1. Run comprehensive tests: ./scripts/test-auth-fix.sh"
  echo "  2. Test CLI authentication"
  echo "  3. Test web dashboard login"
else
  echo "════════════════════════════════════════"
  echo "⚠️  Login test did not return expected result"
  echo "════════════════════════════════════════"
  echo ""
  echo "Status code: ${LOGIN_CODE}"
  echo ""
  echo "Check CloudWatch logs:"
  echo "  aws logs tail /aws/ecs/veribits-app --follow --region us-east-1"
  echo ""
  echo "Check service status:"
  echo "  aws ecs describe-services --cluster veribits-cluster --services veribits-api-service --region us-east-1"
fi
