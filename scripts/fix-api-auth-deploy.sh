#!/bin/bash
# Fix API Authentication POST Data Issue - Deployment Script
# Created: 2025-10-27
# Purpose: Deploy critical fix for php://input empty issue in AWS ECS

set -e

echo "========================================="
echo "VeriBits API Authentication Fix Deployment"
echo "========================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get AWS account ID
AWS_ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
AWS_REGION="us-east-1"
ECR_REPO="${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com/veribits-api"

echo -e "${YELLOW}Step 1: Building Docker image with fixes...${NC}"
docker build -t veribits-api:latest -f docker/Dockerfile .

echo ""
echo -e "${YELLOW}Step 2: Tagging image...${NC}"
docker tag veribits-api:latest ${ECR_REPO}:latest
docker tag veribits-api:latest ${ECR_REPO}:fix-php-input-$(date +%Y%m%d-%H%M%S)

echo ""
echo -e "${YELLOW}Step 3: Logging into ECR...${NC}"
aws ecr get-login-password --region ${AWS_REGION} | docker login --username AWS --password-stdin ${ECR_REPO}

echo ""
echo -e "${YELLOW}Step 4: Pushing image to ECR...${NC}"
docker push ${ECR_REPO}:latest
docker push ${ECR_REPO}:fix-php-input-$(date +%Y%m%d-%H%M%S)

echo ""
echo -e "${YELLOW}Step 5: Forcing ECS service update...${NC}"
aws ecs update-service \
    --cluster veribits-cluster \
    --service veribits-api \
    --force-new-deployment \
    --region ${AWS_REGION}

echo ""
echo -e "${GREEN}=========================================${NC}"
echo -e "${GREEN}Deployment initiated successfully!${NC}"
echo -e "${GREEN}=========================================${NC}"
echo ""
echo "Monitor deployment status with:"
echo "  aws ecs describe-services --cluster veribits-cluster --services veribits-api --region ${AWS_REGION}"
echo ""
echo "Watch CloudWatch logs:"
echo "  aws logs tail /ecs/veribits-api --follow --region ${AWS_REGION}"
echo ""
echo -e "${YELLOW}Testing instructions:${NC}"
echo "  curl -X POST https://www.veribits.com/api/v1/auth/login \\"
echo "    -H 'Content-Type: application/json' \\"
echo "    -d '{\"email\":\"straticus1@gmail.com\",\"password\":\"TestPassword123!\"}'"
echo ""
