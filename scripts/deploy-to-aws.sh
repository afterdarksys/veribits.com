#!/bin/bash

# Deploy VeriBits to AWS ECS
# This script builds the Docker image, pushes to ECR, and updates the ECS service

set -e

echo "🚀 VeriBits AWS Deployment"
echo "=========================="
echo ""

# Configuration
AWS_ACCOUNT_ID="515966511618"
AWS_REGION="us-east-1"
ECR_REPO="veribits"
ECS_CLUSTER="veribits-cluster"
ECS_SERVICE="veribits-api"
IMAGE_TAG="latest"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}📋 Deployment Configuration:${NC}"
echo "  AWS Account: $AWS_ACCOUNT_ID"
echo "  Region: $AWS_REGION"
echo "  ECR Repository: $ECR_REPO"
echo "  ECS Cluster: $ECS_CLUSTER"
echo "  ECS Service: $ECS_SERVICE"
echo "  Image Tag: $IMAGE_TAG"
echo ""

# Check prerequisites
echo -e "${BLUE}🔍 Checking prerequisites...${NC}"

# Check Docker
if ! command -v docker &> /dev/null; then
    echo -e "${RED}❌ Docker is not installed${NC}"
    exit 1
fi

# Check if Docker daemon is running
if ! docker info > /dev/null 2>&1; then
    echo -e "${RED}❌ Docker daemon is not running${NC}"
    echo -e "${YELLOW}💡 Please start Docker Desktop first${NC}"
    echo ""
    echo "Run: open -a Docker"
    exit 1
fi

echo -e "${GREEN}✓ Docker is running${NC}"

# Check AWS CLI
if ! command -v aws &> /dev/null; then
    echo -e "${RED}❌ AWS CLI is not installed${NC}"
    exit 1
fi
echo -e "${GREEN}✓ AWS CLI is installed${NC}"

# Verify AWS credentials
echo ""
echo -e "${BLUE}🔐 Verifying AWS credentials...${NC}"
if ! aws sts get-caller-identity > /dev/null 2>&1; then
    echo -e "${RED}❌ AWS credentials not configured${NC}"
    echo "Run: aws configure"
    exit 1
fi

ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
if [ "$ACCOUNT_ID" != "$AWS_ACCOUNT_ID" ]; then
    echo -e "${YELLOW}⚠️  Warning: Expected account $AWS_ACCOUNT_ID but found $ACCOUNT_ID${NC}"
    echo -e "${YELLOW}   Continuing with current account...${NC}"
fi
echo -e "${GREEN}✓ AWS Account: $ACCOUNT_ID${NC}"

# Build Docker image
echo ""
echo -e "${BLUE}🔨 Building Docker image...${NC}"
echo "This may take a few minutes..."

if ! docker build -t $ECR_REPO:$IMAGE_TAG -f docker/Dockerfile . ; then
    echo -e "${RED}❌ Docker build failed${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Docker image built successfully${NC}"

# Tag for ECR
ECR_URI="$AWS_ACCOUNT_ID.dkr.ecr.$AWS_REGION.amazonaws.com/$ECR_REPO:$IMAGE_TAG"
echo ""
echo -e "${BLUE}🏷️  Tagging image for ECR...${NC}"
docker tag $ECR_REPO:$IMAGE_TAG $ECR_URI
echo -e "${GREEN}✓ Tagged as: $ECR_URI${NC}"

# Login to ECR
echo ""
echo -e "${BLUE}🔑 Logging in to ECR...${NC}"
aws ecr get-login-password --region $AWS_REGION | \
    docker login --username AWS --password-stdin $AWS_ACCOUNT_ID.dkr.ecr.$AWS_REGION.amazonaws.com

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ ECR login failed${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Logged in to ECR${NC}"

# Push to ECR
echo ""
echo -e "${BLUE}📤 Pushing image to ECR...${NC}"
echo "This may take a few minutes..."

if ! docker push $ECR_URI ; then
    echo -e "${RED}❌ Docker push failed${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Image pushed to ECR successfully${NC}"

# Get current task definition
echo ""
echo -e "${BLUE}📋 Checking current ECS task definition...${NC}"

TASK_FAMILY="veribits-api"
CURRENT_TASK_DEF=$(aws ecs describe-task-definition --task-definition $TASK_FAMILY --region $AWS_REGION)

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Failed to get task definition${NC}"
    exit 1
fi

CURRENT_REVISION=$(echo $CURRENT_TASK_DEF | jq -r '.taskDefinition.revision')
echo -e "${GREEN}✓ Current task definition: $TASK_FAMILY:$CURRENT_REVISION${NC}"

# Force new ECS deployment
echo ""
echo -e "${BLUE}🔄 Updating ECS service...${NC}"
echo "Forcing new deployment with latest image..."

UPDATE_OUTPUT=$(aws ecs update-service \
    --cluster $ECS_CLUSTER \
    --service $ECS_SERVICE \
    --force-new-deployment \
    --region $AWS_REGION 2>&1)

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ ECS service update failed${NC}"
    echo "$UPDATE_OUTPUT"
    exit 1
fi

echo -e "${GREEN}✓ ECS service update initiated${NC}"

# Check service status
echo ""
echo -e "${BLUE}📊 Checking service status...${NC}"

SERVICE_INFO=$(aws ecs describe-services \
    --cluster $ECS_CLUSTER \
    --services $ECS_SERVICE \
    --region $AWS_REGION \
    --query 'services[0]' 2>&1)

if [ $? -eq 0 ]; then
    DESIRED_COUNT=$(echo $SERVICE_INFO | jq -r '.desiredCount')
    RUNNING_COUNT=$(echo $SERVICE_INFO | jq -r '.runningCount')
    PENDING_COUNT=$(echo $SERVICE_INFO | jq -r '.pendingCount')

    echo "  Desired: $DESIRED_COUNT"
    echo "  Running: $RUNNING_COUNT"
    echo "  Pending: $PENDING_COUNT"
fi

# Get load balancer DNS
echo ""
echo -e "${BLUE}🌐 Getting load balancer URL...${NC}"

ALB_DNS=$(aws elbv2 describe-load-balancers --region $AWS_REGION \
    --query "LoadBalancers[?contains(LoadBalancerName, 'veribits')].DNSName" \
    --output text 2>&1)

if [ $? -eq 0 ] && [ -n "$ALB_DNS" ]; then
    echo -e "${GREEN}✓ Load Balancer: $ALB_DNS${NC}"
fi

# Clear CloudFront cache
echo ""
echo -e "${BLUE}🗑️  Clearing CloudFront cache...${NC}"

# Get CloudFront distribution ID
DISTRIBUTION_ID=$(aws cloudfront list-distributions --region $AWS_REGION \
    --query "DistributionList.Items[?contains(Aliases.Items[0], 'veribits.com')].Id" \
    --output text 2>&1)

if [ $? -eq 0 ] && [ -n "$DISTRIBUTION_ID" ]; then
    echo "Found distribution: $DISTRIBUTION_ID"

    # Create invalidation
    INVALIDATION=$(aws cloudfront create-invalidation \
        --distribution-id $DISTRIBUTION_ID \
        --paths "/*" \
        --region $AWS_REGION 2>&1)

    if [ $? -eq 0 ]; then
        INVALIDATION_ID=$(echo $INVALIDATION | jq -r '.Invalidation.Id')
        echo -e "${GREEN}✓ Cache invalidation created: $INVALIDATION_ID${NC}"
        echo "  Cache will be cleared in 1-2 minutes"
    else
        echo -e "${YELLOW}⚠️  Could not create cache invalidation${NC}"
    fi
else
    echo -e "${YELLOW}⚠️  CloudFront distribution not found${NC}"
fi

# Summary
echo ""
echo -e "${GREEN}═══════════════════════════════════════════${NC}"
echo -e "${GREEN}✅ Deployment Complete!${NC}"
echo -e "${GREEN}═══════════════════════════════════════════${NC}"
echo ""
echo -e "${BLUE}📋 Summary:${NC}"
echo "  • Docker image built and pushed to ECR"
echo "  • ECS service updated with new image"
echo "  • CloudFront cache invalidation initiated"
echo ""
echo -e "${BLUE}⏳ Deployment Status:${NC}"
echo "  • ECS will deploy new tasks (~2-3 minutes)"
echo "  • Health checks must pass before going live"
echo "  • CloudFront cache will clear (~1-2 minutes)"
echo ""
echo -e "${BLUE}🌐 URLs:${NC}"
echo "  • Website: https://www.veribits.com"
echo "  • Docs: https://www.veribits.com/docs.php"
if [ -n "$ALB_DNS" ]; then
    echo "  • Load Balancer: http://$ALB_DNS"
fi
echo ""
echo -e "${BLUE}🔍 Monitoring:${NC}"
echo "  • ECS Console: https://console.aws.amazon.com/ecs/v2/clusters/$ECS_CLUSTER/services/$ECS_SERVICE"
echo "  • CloudWatch Logs: https://console.aws.amazon.com/cloudwatch/home?region=$AWS_REGION#logsV2:log-groups"
echo ""
echo -e "${YELLOW}💡 Tip: Wait 3-5 minutes before testing changes${NC}"
echo ""

# Wait for deployment (optional)
read -p "Would you like to wait for deployment to complete? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo ""
    echo -e "${BLUE}⏳ Waiting for deployment to stabilize...${NC}"
    echo "This may take 3-5 minutes..."

    aws ecs wait services-stable \
        --cluster $ECS_CLUSTER \
        --services $ECS_SERVICE \
        --region $AWS_REGION

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Deployment is stable!${NC}"
        echo ""
        echo "Testing endpoints..."

        # Test homepage
        if curl -s -o /dev/null -w "%{http_code}" https://www.veribits.com | grep -q "200"; then
            echo -e "${GREEN}✓ Homepage: https://www.veribits.com${NC}"
        else
            echo -e "${YELLOW}⚠️  Homepage returned non-200 status${NC}"
        fi

        # Test docs page
        if curl -s -o /dev/null -w "%{http_code}" https://www.veribits.com/docs.php | grep -q "200"; then
            echo -e "${GREEN}✓ Docs page: https://www.veribits.com/docs.php${NC}"
        else
            echo -e "${YELLOW}⚠️  Docs page returned non-200 status${NC}"
        fi
    else
        echo -e "${YELLOW}⚠️  Deployment is taking longer than expected${NC}"
        echo "Check ECS console for status"
    fi
fi

echo ""
echo -e "${GREEN}🎉 All done!${NC}"
