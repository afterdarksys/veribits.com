#!/bin/bash
set -e

echo "==========================================="
echo "VeriBits - Fix API POST & Deploy"
echo "==========================================="
echo ""

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
AWS_REGION="us-east-1"
ECR_REPO="992382474804.dkr.ecr.us-east-1.amazonaws.com/veribits-api"
ECS_CLUSTER="veribits-cluster"
ECS_SERVICE="veribits-api"

echo -e "${YELLOW}Step 1: Building Docker image with Apache POST fix...${NC}"
cd "$(dirname "$0")/.."
docker build -t veribits-api:latest -f docker/Dockerfile .

if [ $? -ne 0 ]; then
    echo -e "${RED}Docker build failed!${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Docker image built successfully${NC}"
echo ""

echo -e "${YELLOW}Step 2: Testing image locally...${NC}"
# Start container in background
CONTAINER_ID=$(docker run -d -p 8080:80 \
    -e APP_ENV=development \
    -e JWT_SECRET=test-secret-key \
    -e DB_HOST=localhost \
    -e DB_PORT=5432 \
    -e DB_NAME=veribits \
    -e DB_USER=postgres \
    -e DB_PASSWORD=password \
    veribits-api:latest)

echo "Container started: $CONTAINER_ID"
echo "Waiting for Apache to start..."
sleep 5

# Test health endpoint
echo "Testing health endpoint..."
HEALTH_RESPONSE=$(curl -s http://localhost:8080/api/v1/health)
echo "Health check response: $HEALTH_RESPONSE"

# Test POST endpoint with actual JSON body
echo ""
echo "Testing POST endpoint with JSON body..."
POST_RESPONSE=$(curl -s -X POST http://localhost:8080/api/v1/auth/login \
    -H "Content-Type: application/json" \
    -d '{"email":"test@example.com","password":"TestPassword123!"}')

echo "POST response: $POST_RESPONSE"

# Check if validation error mentions missing email (would mean body is empty)
if echo "$POST_RESPONSE" | grep -q "email field is required"; then
    echo -e "${RED}✗ POST body still empty! Fix did not work.${NC}"
    docker stop $CONTAINER_ID
    docker rm $CONTAINER_ID
    exit 1
elif echo "$POST_RESPONSE" | grep -q "Invalid credentials\|email already registered"; then
    echo -e "${GREEN}✓ POST body is being received correctly!${NC}"
else
    echo -e "${YELLOW}⚠ Unexpected response, but body might be working${NC}"
fi

# Cleanup
docker stop $CONTAINER_ID
docker rm $CONTAINER_ID

echo ""
echo -e "${GREEN}✓ Local tests passed${NC}"
echo ""

read -p "Deploy to AWS? (y/n) " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Deployment cancelled"
    exit 0
fi

echo -e "${YELLOW}Step 3: Logging into AWS ECR...${NC}"
aws ecr get-login-password --region $AWS_REGION | docker login --username AWS --password-stdin $ECR_REPO

if [ $? -ne 0 ]; then
    echo -e "${RED}ECR login failed!${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Logged into ECR${NC}"
echo ""

echo -e "${YELLOW}Step 4: Tagging and pushing image...${NC}"
docker tag veribits-api:latest $ECR_REPO:latest
docker tag veribits-api:latest $ECR_REPO:$(date +%Y%m%d-%H%M%S)

docker push $ECR_REPO:latest
docker push $ECR_REPO:$(date +%Y%m%d-%H%M%S)

echo -e "${GREEN}✓ Image pushed to ECR${NC}"
echo ""

echo -e "${YELLOW}Step 5: Updating ECS service...${NC}"
aws ecs update-service \
    --cluster $ECS_CLUSTER \
    --service $ECS_SERVICE \
    --force-new-deployment \
    --region $AWS_REGION

echo -e "${GREEN}✓ ECS service update initiated${NC}"
echo ""

echo -e "${YELLOW}Step 6: Waiting for deployment to complete...${NC}"
aws ecs wait services-stable \
    --cluster $ECS_CLUSTER \
    --services $ECS_SERVICE \
    --region $AWS_REGION

echo -e "${GREEN}✓ Deployment completed${NC}"
echo ""

echo "==========================================="
echo "Testing production endpoint..."
echo "==========================================="
sleep 10  # Give ALB a moment to route to new containers

# Test production
echo "Testing health endpoint..."
PROD_HEALTH=$(curl -s https://www.veribits.com/api/v1/health)
echo "Production health: $PROD_HEALTH"

echo ""
echo "Testing POST endpoint..."
PROD_POST=$(curl -s -X POST https://www.veribits.com/api/v1/auth/login \
    -H "Content-Type: application/json" \
    -d '{"email":"test@example.com","password":"TestPassword123!"}')

echo "Production POST response: $PROD_POST"

if echo "$PROD_POST" | grep -q "email field is required"; then
    echo -e "${RED}✗ POST body still empty in production!${NC}"
    echo -e "${YELLOW}Additional troubleshooting needed${NC}"
    exit 1
elif echo "$PROD_POST" | grep -q "Invalid credentials\|email already registered"; then
    echo -e "${GREEN}✓✓✓ SUCCESS! POST body working in production!${NC}"
else
    echo -e "${YELLOW}⚠ Check response manually${NC}"
fi

echo ""
echo "==========================================="
echo -e "${GREEN}Deployment complete!${NC}"
echo "==========================================="
