#!/bin/bash

# EMERGENCY FIX: Add caching back to Request.php
# Root cause: php://input can only be read ONCE per request
# The AuthController was calling Request::getBody() twice, exhausting the stream

set -e

echo "🚨 EMERGENCY FIX: Request.php Caching"
echo "======================================"
echo ""
echo "Root Cause: php://input can only be read once per request"
echo "The bug: AuthController calls Request::getBody() twice:"
echo "  1. Line 96: getJsonBody() for validation"
echo "  2. Line 104: getBody() for debug logging"
echo ""
echo "Fix: Re-enable caching in Request class with proper documentation"
echo ""

cd /Users/ryan/development/veribits.com

# Show the fix
echo "📝 Changes made to Request.php:"
echo "  - Added static cache variable: \$cachedBody"
echo "  - Cache the result after first read"
echo "  - Return cached value on subsequent calls"
echo ""

# Build new Docker image
echo "🔨 Building Docker image..."
docker build -t veribits:emergency-fix -f docker/Dockerfile .

# Tag for ECR
echo "🏷️  Tagging image for ECR..."
AWS_ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
docker tag veribits:emergency-fix $AWS_ACCOUNT_ID.dkr.ecr.us-east-1.amazonaws.com/veribits:latest

# Login to ECR
echo "🔐 Logging into ECR..."
aws ecr get-login-password --region us-east-1 | docker login --username AWS --password-stdin $AWS_ACCOUNT_ID.dkr.ecr.us-east-1.amazonaws.com

# Push to ECR
echo "📤 Pushing to ECR..."
docker push $AWS_ACCOUNT_ID.dkr.ecr.us-east-1.amazonaws.com/veribits:latest

# Force ECS to update
echo "🔄 Forcing ECS service update..."
aws ecs update-service \
    --cluster veribits-cluster \
    --service veribits-api \
    --force-new-deployment \
    --region us-east-1 > /dev/null

echo ""
echo "✅ Deployment initiated!"
echo ""
echo "⏳ The service will:"
echo "   1. Pull the new image (~30 seconds)"
echo "   2. Start new task (~30 seconds)"
echo "   3. Health check pass (~10 seconds)"
echo "   4. Drain old task (~30 seconds)"
echo ""
echo "   Total time: ~2 minutes"
echo ""
echo "🔍 Monitor deployment:"
echo "   https://console.aws.amazon.com/ecs/v2/clusters/veribits-cluster/services/veribits-api"
echo ""
echo "🧪 Test after deployment:"
echo '   curl -X POST https://www.veribits.com/api/v1/auth/login \'
echo '     -H "Content-Type: application/json" \'
echo '     -d '"'"'{"email":"straticus1@gmail.com","password":"TestPassword123!"}'"'"
echo ""
