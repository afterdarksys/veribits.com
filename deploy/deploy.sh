#!/bin/bash
# VeriBits AWS Deployment Script
# This script deploys the entire VeriBits application to AWS

set -e  # Exit on error

echo "=========================================="
echo "VeriBits AWS Deployment Script"
echo "=========================================="
echo ""

# Check for required environment variables
if [ -z "$AWS_PROFILE" ]; then
    echo "Error: AWS_PROFILE environment variable not set"
    echo "Please set it with: export AWS_PROFILE=your-profile-name"
    exit 1
fi

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Configuration
TERRAFORM_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/terraform" && pwd)"
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEPLOY_BUCKET="${DEPLOY_BUCKET:-veribits-deployment-$(date +%s)}"

echo -e "${YELLOW}Configuration:${NC}"
echo "  Terraform Dir: $TERRAFORM_DIR"
echo "  App Dir: $APP_DIR"
echo "  AWS Profile: $AWS_PROFILE"
echo "  Deploy Bucket: $DEPLOY_BUCKET"
echo ""

# Step 1: Initialize Terraform
echo -e "${YELLOW}Step 1: Initializing Terraform...${NC}"
cd "$TERRAFORM_DIR"
terraform init
echo -e "${GREEN}✓ Terraform initialized${NC}"
echo ""

# Step 2: Create terraform.tfvars if it doesn't exist
if [ ! -f "$TERRAFORM_DIR/terraform.tfvars" ]; then
    echo -e "${YELLOW}Step 2: Creating terraform.tfvars...${NC}"

    # Generate secure passwords
    DB_PASSWORD=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-32)
    REDIS_AUTH_TOKEN=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-32)
    JWT_SECRET=$(openssl rand -base64 64 | tr -d "=+/" | cut -c1-64)

    cat > "$TERRAFORM_DIR/terraform.tfvars" <<EOF
# AWS Configuration
aws_region = "us-east-1"
environment = "production"

# Database Configuration
db_username = "veribits_admin"
db_password = "$DB_PASSWORD"
db_instance_class = "db.t3.medium"

# Redis Configuration
redis_auth_token = "$REDIS_AUTH_TOKEN"
redis_node_type = "cache.t3.medium"

# EC2 Configuration
instance_type = "t3.medium"
key_pair_name = "veribits-production"  # Update this with your key pair name
ami_id = "ami-0c7217cdde317cfec"  # Amazon Linux 2023 - Update for your region

# Auto Scaling
asg_min_size = 2
asg_max_size = 6
asg_desired_capacity = 2

# JWT Configuration
jwt_secret = "$JWT_SECRET"

# Stripe Configuration (Update these with your actual Stripe keys)
stripe_secret_key = "sk_test_REPLACE_WITH_YOUR_KEY"
stripe_publishable_key = "pk_test_REPLACE_WITH_YOUR_KEY"
stripe_webhook_secret = "whsec_REPLACE_WITH_YOUR_SECRET"
EOF

    echo -e "${GREEN}✓ terraform.tfvars created${NC}"
    echo -e "${RED}IMPORTANT: Update Stripe keys in terraform.tfvars before deploying!${NC}"
    echo ""

    # Save passwords to secure location
    cat > "$TERRAFORM_DIR/secrets.txt" <<EOF
VeriBits Production Secrets
Generated: $(date)

Database Password: $DB_PASSWORD
Redis Auth Token: $REDIS_AUTH_TOKEN
JWT Secret: $JWT_SECRET

IMPORTANT: Store these securely and delete this file after storing in a password manager!
EOF
    chmod 600 "$TERRAFORM_DIR/secrets.txt"
    echo -e "${YELLOW}Secrets saved to: $TERRAFORM_DIR/secrets.txt${NC}"
    echo -e "${RED}Remember to store these securely and delete the file!${NC}"
    echo ""
fi

# Step 3: Create S3 bucket for application code
echo -e "${YELLOW}Step 3: Creating S3 bucket for application code...${NC}"
aws s3 mb "s3://$DEPLOY_BUCKET" --region us-east-1 2>/dev/null || echo "Bucket already exists or error creating"
echo -e "${GREEN}✓ S3 bucket ready${NC}"
echo ""

# Step 4: Package application
echo -e "${YELLOW}Step 4: Packaging application...${NC}"
cd "$APP_DIR"

# Create deployment package
rm -f veribits-deploy.tar.gz
tar -czf veribits-deploy.tar.gz \
    --exclude='node_modules' \
    --exclude='.git' \
    --exclude='tests' \
    --exclude='deploy' \
    --exclude='.env' \
    app/ database/

echo -e "${GREEN}✓ Application packaged${NC}"
echo ""

# Step 5: Upload to S3
echo -e "${YELLOW}Step 5: Uploading application to S3...${NC}"
aws s3 cp veribits-deploy.tar.gz "s3://$DEPLOY_BUCKET/veribits-deploy.tar.gz"
echo -e "${GREEN}✓ Application uploaded${NC}"
echo ""

# Step 6: Plan Terraform deployment
echo -e "${YELLOW}Step 6: Planning Terraform deployment...${NC}"
cd "$TERRAFORM_DIR"
terraform plan -out=tfplan
echo -e "${GREEN}✓ Terraform plan created${NC}"
echo ""

# Step 7: Confirm deployment
echo -e "${RED}=========================================${NC}"
echo -e "${RED}IMPORTANT: Review the Terraform plan above${NC}"
echo -e "${RED}=========================================${NC}"
read -p "Do you want to proceed with deployment? (yes/no): " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    echo -e "${YELLOW}Deployment cancelled${NC}"
    exit 0
fi

# Step 8: Apply Terraform
echo -e "${YELLOW}Step 8: Deploying infrastructure...${NC}"
terraform apply tfplan
echo -e "${GREEN}✓ Infrastructure deployed${NC}"
echo ""

# Step 9: Get outputs
echo -e "${YELLOW}Step 9: Retrieving deployment outputs...${NC}"
ALB_DNS=$(terraform output -raw alb_dns_name)
RDS_ENDPOINT=$(terraform output -raw rds_endpoint)
REDIS_ENDPOINT=$(terraform output -raw redis_endpoint)

echo -e "${GREEN}✓ Deployment complete!${NC}"
echo ""
echo "=========================================="
echo "Deployment Information"
echo "=========================================="
echo "Application URL: http://$ALB_DNS"
echo "RDS Endpoint: $RDS_ENDPOINT"
echo "Redis Endpoint: $REDIS_ENDPOINT"
echo ""
echo "Save this information securely!"
echo ""

# Step 10: Wait for instances to be healthy
echo -e "${YELLOW}Step 10: Waiting for instances to be healthy...${NC}"
echo "This may take 5-10 minutes..."
sleep 60

# Test the application
echo -e "${YELLOW}Testing application health...${NC}"
for i in {1..30}; do
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "http://$ALB_DNS/api/v1/health" || echo "000")
    if [ "$HTTP_CODE" == "200" ]; then
        echo -e "${GREEN}✓ Application is healthy!${NC}"
        break
    else
        echo "Attempt $i/30: HTTP $HTTP_CODE (waiting...)"
        sleep 20
    fi
done

echo ""
echo "=========================================="
echo "Next Steps:"
echo "=========================================="
echo "1. Configure DNS to point to: $ALB_DNS"
echo "2. Set up SSL certificate in AWS Certificate Manager"
echo "3. Update ALB listener to use HTTPS"
echo "4. Run comprehensive tests:"
echo "   cd $APP_DIR/tests"
echo "   BASE_URL=http://$ALB_DNS npm run test:all"
echo "5. Review secrets in: $TERRAFORM_DIR/secrets.txt"
echo ""
echo "Test accounts are available:"
echo "  - admin@veribits-test.com / Admin123!@#"
echo "  - developer@veribits-test.com / Dev123!@#"
echo "  - professional@veribits-test.com / Pro123!@#"
echo "  - enterprise@veribits-test.com / Ent123!@#"
echo ""
echo -e "${GREEN}Deployment complete!${NC}"
