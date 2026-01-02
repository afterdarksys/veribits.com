# VeriBits Enterprise Features - Production Deployment Plan

**Deployment Date**: October 28, 2025
**Version**: 2.0.0
**Status**: Ready for Production
**Prepared for**: Ryan (straticus1@gmail.com)

---

## Executive Summary

This document provides a comprehensive, enterprise-grade deployment plan for 5 major features:
1. Malware Detonation Sandbox (Cuckoo integration)
2. Netcat Network Tool (TCP/UDP testing)
3. OAuth2 & Webhooks (Zapier/n8n integration)
4. Pro Subscriptions (license validation)
5. CLI Pro with job scheduling and plugins

**Total New Tables**: 10 PostgreSQL tables
**Total New API Endpoints**: 80+
**Deployment Time Estimate**: 2-3 hours
**Risk Level**: Medium (with proper rollback procedures)

---

## Current Infrastructure Analysis

### AWS Resources Discovered

**RDS Database**:
- Instance: `nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com`
- Engine: PostgreSQL 15.12
- Status: Available
- VPC: `vpc-0c1b813880b3982a5`
- Security Group: `sg-011e3c8ac8f73858b` (nitetext-rds-sg)
- Publicly Accessible: **NO** (VPC-only access)

**EC2 Instances** (3 running):
1. **i-0cdcaeed37df5d284** (nitetext-instance-new)
   - Type: t3.micro
   - VPC: `vpc-0c1b813880b3982a5` (SAME as RDS)
   - Security Group: `sg-029088670092e6501`
   - Private IP: 10.0.10.224
   - Public IP: None
   - **Status**: Same VPC as RDS - Can access database

2. **i-09b72622ae7d82664** (nitetext-instance)
   - Type: t3.medium
   - VPC: `vpc-062b4d9462879a884` (DIFFERENT from RDS)
   - Public IP: 13.217.98.251
   - **Status**: Cannot directly access RDS (different VPC)

3. **i-09f2a819c0140512a** (nitetext-instance)
   - Type: t3.medium
   - VPC: `vpc-062b4d9462879a884` (DIFFERENT from RDS)
   - Public IP: 98.83.218.211
   - **Status**: Cannot directly access RDS (different VPC)

**Security Group Configuration**:
The RDS security group (`sg-011e3c8ac8f73858b`) allows PostgreSQL (port 5432) from:
- VPC CIDR: `10.0.0.0/16` (allows all VPC traffic)
- ECS Security Group: `sg-0c2e8ed602f9886a7`
- VeriBits ECS Security Group: `sg-0422b38f99e7b23ce`

### Critical Insight

**THE DATABASE CONNECTION ISSUE IS NOW CLEAR**:

Your RDS instance is **NOT publicly accessible** and only accepts connections from:
1. Resources within VPC `vpc-0c1b813880b3982a5`
2. Specific ECS security groups

**To run migrations, you MUST use**:
- EC2 instance `i-0cdcaeed37df5d284` (nitetext-instance-new) - it's in the same VPC
- OR configure VPC peering/transit gateway
- OR temporarily modify security group (not recommended)

---

## DEPLOYMENT STRATEGY

### Phase 1: Database Migration Execution

#### Option A: SSH to Bastion EC2 Instance (RECOMMENDED)

This is the **safest and most straightforward** approach.

**Step 1: Connect to the bastion host**

```bash
# SSH to the EC2 instance in the same VPC as RDS
ssh -i ~/.ssh/your-key.pem ec2-user@<public-ip-of-i-0cdcaeed37df5d284>

# If you don't have the public IP, get it:
aws ec2 describe-instances \
  --instance-ids i-0cdcaeed37df5d284 \
  --query 'Reservations[0].Instances[0].PublicIpAddress' \
  --output text

# Alternative: Use AWS Systems Manager Session Manager (no SSH key needed)
aws ssm start-session --target i-0cdcaeed37df5d284
```

**Step 2: Install PostgreSQL client on bastion**

```bash
# Amazon Linux 2023 / Amazon Linux 2
sudo yum install -y postgresql15

# Ubuntu/Debian
sudo apt-get update && sudo apt-get install -y postgresql-client
```

**Step 3: Test database connectivity**

```bash
PGPASSWORD='NiteText2025!SecureProd' psql \
  -h nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com \
  -U nitetext \
  -d veribits \
  -c "SELECT version();"

# Expected output: PostgreSQL 15.12 ...
```

**Step 4: Transfer migration files to bastion**

```bash
# From your local machine
scp -i ~/.ssh/your-key.pem \
  /Users/ryan/development/veribits.com/db/migrations/020_pro_subscriptions_pg.sql \
  /Users/ryan/development/veribits.com/db/migrations/021_oauth_webhooks_pg.sql \
  /Users/ryan/development/veribits.com/db/migrations/022_malware_detonation_pg.sql \
  ec2-user@<bastion-ip>:~/

# Alternative: Use AWS S3 as intermediate storage
aws s3 cp db/migrations/ s3://your-bucket/migrations/ --recursive

# Then on bastion:
aws s3 sync s3://your-bucket/migrations/ ~/migrations/
```

**Step 5: Create database backup**

```bash
# On bastion host
PGPASSWORD='NiteText2025!SecureProd' pg_dump \
  -h nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com \
  -U nitetext \
  -d veribits \
  --format=custom \
  -f ~/veribits_backup_$(date +%Y%m%d_%H%M%S).dump

# Upload backup to S3 for safekeeping
aws s3 cp ~/veribits_backup_*.dump s3://your-backup-bucket/database-backups/

# Or use RDS automated snapshot (instant, recommended)
aws rds create-db-snapshot \
  --db-instance-identifier nitetext-db \
  --db-snapshot-identifier veribits-pre-enterprise-$(date +%Y%m%d-%H%M%S)
```

**Step 6: Run migrations sequentially**

```bash
# Set credentials
export PGPASSWORD='NiteText2025!SecureProd'
export DB_HOST='nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com'
export DB_USER='nitetext'
export DB_NAME='veribits'

# Run migrations in order
echo "Running migration 020: Pro Subscriptions..."
psql -h $DB_HOST -U $DB_USER -d $DB_NAME -f ~/020_pro_subscriptions_pg.sql
if [ $? -ne 0 ]; then
    echo "ERROR: Migration 020 failed!"
    exit 1
fi

echo "Running migration 021: OAuth2 & Webhooks..."
psql -h $DB_HOST -U $DB_USER -d $DB_NAME -f ~/021_oauth_webhooks_pg.sql
if [ $? -ne 0 ]; then
    echo "ERROR: Migration 021 failed!"
    exit 1
fi

echo "Running migration 022: Malware Detonation..."
psql -h $DB_HOST -U $DB_USER -d $DB_NAME -f ~/022_malware_detonation_pg.sql
if [ $? -ne 0 ]; then
    echo "ERROR: Migration 022 failed!"
    exit 1
fi

echo "All migrations completed successfully!"
```

**Step 7: Verify tables were created**

```bash
psql -h $DB_HOST -U $DB_USER -d $DB_NAME -c "
SELECT table_name
FROM information_schema.tables
WHERE table_schema = 'public'
AND table_name IN (
  'pro_licenses',
  'oauth_clients',
  'oauth_authorization_codes',
  'oauth_access_tokens',
  'oauth_refresh_tokens',
  'webhooks',
  'webhook_deliveries',
  'malware_submissions',
  'malware_analysis_results',
  'malware_screenshots'
)
ORDER BY table_name;
"

# Should return all 10 tables
```

**Step 8: Verify test data was inserted**

```bash
# Check Pro license
psql -h $DB_HOST -U $DB_USER -d $DB_NAME -c "
SELECT id, user_id, license_key, plan, status, expires_at
FROM pro_licenses
WHERE license_key = 'VBPRO-DEV-TEST-0000000000000000';
"

# Check OAuth client
psql -h $DB_HOST -U $DB_USER -d $DB_NAME -c "
SELECT id, client_id, name, redirect_uris
FROM oauth_clients
WHERE client_id = 'vb_zapier_test_client_0000000000';
"
```

---

#### Option B: Systems Manager Session Manager (NO SSH KEY REQUIRED)

If you don't have SSH access or prefer not to use SSH:

```bash
# Install Session Manager plugin (one-time)
# macOS
curl "https://s3.amazonaws.com/session-manager-downloads/plugin/latest/mac/sessionmanager-bundle.zip" -o "sessionmanager-bundle.zip"
unzip sessionmanager-bundle.zip
sudo ./sessionmanager-bundle/install -i /usr/local/sessionmanagerplugin -b /usr/local/bin/session-manager-plugin

# Start session
aws ssm start-session --target i-0cdcaeed37df5d284

# Once connected, follow Step 2-8 from Option A
```

---

#### Option C: Temporary Security Group Modification (NOT RECOMMENDED)

Only use this if you cannot access the bastion host and need immediate access.

**WARNING**: This temporarily exposes your database to the internet. Use with extreme caution.

```bash
# Get your current public IP
MY_IP=$(curl -s https://checkip.amazonaws.com)

# Add temporary rule to RDS security group
aws ec2 authorize-security-group-ingress \
  --group-id sg-011e3c8ac8f73858b \
  --protocol tcp \
  --port 5432 \
  --cidr $MY_IP/32 \
  --description "Temporary migration access - REMOVE AFTER USE"

# Run migrations from local machine
export PGPASSWORD='NiteText2025!SecureProd'
psql -h nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com \
  -U nitetext -d veribits \
  -f /Users/ryan/development/veribits.com/db/migrations/020_pro_subscriptions_pg.sql

psql -h nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com \
  -U nitetext -d veribits \
  -f /Users/ryan/development/veribits.com/db/migrations/021_oauth_webhooks_pg.sql

psql -h nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com \
  -U nitetext -d veribits \
  -f /Users/ryan/development/veribits.com/db/migrations/022_malware_detonation_pg.sql

# IMMEDIATELY REMOVE THE RULE AFTER MIGRATION
aws ec2 revoke-security-group-ingress \
  --group-id sg-011e3c8ac8f73858b \
  --protocol tcp \
  --port 5432 \
  --cidr $MY_IP/32
```

---

### Phase 2: Cuckoo Sandbox Deployment

The malware detonation feature requires Cuckoo Sandbox. Here are your deployment options:

#### Option A: Dedicated EC2 Instance (RECOMMENDED FOR PRODUCTION)

**Architecture**:
- Isolated EC2 instance in private subnet
- Security group restricts access to VeriBits application only
- EBS volume for malware samples and analysis results
- CloudWatch monitoring and alerting

**Deployment Steps**:

```bash
# 1. Create dedicated security group for Cuckoo
aws ec2 create-security-group \
  --group-name veribits-cuckoo-sg \
  --description "Cuckoo Sandbox API access" \
  --vpc-id vpc-0c1b813880b3982a5

CUCKOO_SG_ID=$(aws ec2 describe-security-groups \
  --filters "Name=group-name,Values=veribits-cuckoo-sg" \
  --query 'SecurityGroups[0].GroupId' --output text)

# 2. Allow Cuckoo API access (port 8090) from VeriBits ECS tasks
aws ec2 authorize-security-group-ingress \
  --group-id $CUCKOO_SG_ID \
  --protocol tcp \
  --port 8090 \
  --source-group sg-0422b38f99e7b23ce \
  --description "Allow VeriBits ECS tasks to access Cuckoo API"

# 3. Launch EC2 instance for Cuckoo
aws ec2 run-instances \
  --image-id ami-0c55b159cbfafe1f0 \
  --instance-type t3.xlarge \
  --key-name your-key-name \
  --security-group-ids $CUCKOO_SG_ID \
  --subnet-id subnet-0570f1d90393717f1 \
  --block-device-mappings '[{"DeviceName":"/dev/xvda","Ebs":{"VolumeSize":100,"VolumeType":"gp3"}}]' \
  --tag-specifications 'ResourceType=instance,Tags=[{Key=Name,Value=veribits-cuckoo},{Key=Environment,Value=production}]' \
  --user-data file://cuckoo-install-script.sh
```

**Cuckoo Installation Script** (`cuckoo-install-script.sh`):

```bash
#!/bin/bash
# Cuckoo Sandbox Installation on Ubuntu 22.04

# Update system
apt-get update && apt-get upgrade -y

# Install dependencies
apt-get install -y python3 python3-pip python3-dev \
  libffi-dev libssl-dev libjpeg-dev zlib1g-dev \
  swig mongodb postgresql libpq-dev \
  virtualbox virtualbox-ext-pack tcpdump apparmor-utils

# Disable AppArmor for tcpdump
aa-disable /usr/bin/tcpdump

# Install Cuckoo
pip3 install -U pip setuptools
pip3 install -U cuckoo

# Create Cuckoo user
useradd -m -s /bin/bash cuckoo

# Initialize Cuckoo
su - cuckoo -c "cuckoo init"

# Configure Cuckoo
cat > /home/cuckoo/.cuckoo/conf/cuckoo.conf <<EOF
[cuckoo]
version_check = no
machinery = virtualbox
memory_dump = no
[resultserver]
ip = 0.0.0.0
port = 2042
[processing]
analysis_size_limit = 268435456
[database]
connection = postgresql://cuckoo:cuckoo_password@localhost/cuckoo
EOF

# Setup PostgreSQL database
sudo -u postgres psql -c "CREATE USER cuckoo WITH PASSWORD 'cuckoo_password';"
sudo -u postgres psql -c "CREATE DATABASE cuckoo OWNER cuckoo;"

# Configure Cuckoo web interface
cat > /home/cuckoo/.cuckoo/conf/reporting.conf <<EOF
[mongodb]
enabled = yes
host = 127.0.0.1
port = 27017
[singlefile]
enabled = yes
[jsondump]
enabled = yes
EOF

# Start Cuckoo API server
su - cuckoo -c "nohup cuckoo api --host 0.0.0.0 --port 8090 &"

echo "Cuckoo Sandbox installation complete!"
```

**Cost Estimate**:
- t3.xlarge: $0.1664/hour = ~$120/month
- 100GB gp3 EBS: ~$8/month
- **Total**: ~$128/month

#### Option B: ECS/Fargate Container (RECOMMENDED FOR SCALABILITY)

**Benefits**:
- Auto-scaling based on demand
- No server management
- Pay only for actual analysis time
- High availability across AZs

**Deployment**:

```bash
# 1. Create ECR repository for Cuckoo
aws ecr create-repository --repository-name veribits/cuckoo-sandbox

# 2. Build and push Cuckoo Docker image
# (Dockerfile provided below)
docker build -t veribits/cuckoo-sandbox .
docker tag veribits/cuckoo-sandbox:latest \
  515966511618.dkr.ecr.us-east-1.amazonaws.com/veribits/cuckoo-sandbox:latest
aws ecr get-login-password --region us-east-1 | docker login --username AWS \
  --password-stdin 515966511618.dkr.ecr.us-east-1.amazonaws.com
docker push 515966511618.dkr.ecr.us-east-1.amazonaws.com/veribits/cuckoo-sandbox:latest

# 3. Create ECS Task Definition
aws ecs register-task-definition \
  --cli-input-json file://cuckoo-task-definition.json

# 4. Create ECS Service
aws ecs create-service \
  --cluster veribits-cluster \
  --service-name cuckoo-sandbox \
  --task-definition cuckoo-sandbox:1 \
  --desired-count 1 \
  --launch-type FARGATE \
  --network-configuration "awsvpcConfiguration={subnets=[subnet-0570f1d90393717f1],securityGroups=[$CUCKOO_SG_ID]}"
```

**Dockerfile for Cuckoo**:

```dockerfile
FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    python3 python3-pip python3-dev \
    libffi-dev libssl-dev libjpeg-dev zlib1g-dev \
    swig mongodb postgresql-client \
    && pip3 install -U cuckoo

WORKDIR /cuckoo

RUN cuckoo init

COPY cuckoo-config/ /root/.cuckoo/

EXPOSE 8090

CMD ["cuckoo", "api", "--host", "0.0.0.0", "--port", "8090"]
```

**Cost Estimate**:
- Fargate (1 vCPU, 2GB RAM): $0.04048/hour = ~$30/month (if running 24/7)
- For on-demand usage: ~$5-10/month

#### Option C: External Service (EASIEST, RECOMMENDED FOR MVP)

Use a managed malware analysis service:

**Options**:
1. **Hybrid Analysis** - https://www.hybrid-analysis.com/
2. **Any.Run** - https://any.run/
3. **Joe Sandbox** - https://www.joesandbox.com/
4. **VirusTotal** - https://www.virustotal.com/

**Implementation**:

```php
// Update MalwareDetonationController.php
private const CUCKOO_API_URL = 'https://www.hybrid-analysis.com/api/v2';
private const CUCKOO_API_KEY = 'your-api-key';

// Modify submitToCuckoo() to use external service API
```

**Cost**:
- Hybrid Analysis: $99-299/month
- Any.Run: $90-490/month
- **Recommendation**: Start with Hybrid Analysis for MVP

---

### Phase 3: Application Deployment

Now that database and Cuckoo are ready, deploy the application code.

#### Identify Your Deployment Target

Based on your infrastructure, you likely have:
- **ECS/Fargate** containers (referenced in security groups)
- EC2 instances running Docker

Let's check:

```bash
# Check for ECS clusters
aws ecs list-clusters

# Check for running tasks
aws ecs list-tasks --cluster <cluster-name>

# Check for services
aws ecs list-services --cluster <cluster-name>
```

#### Deployment Methods

**Method 1: ECS/Fargate Deployment (RECOMMENDED)**

```bash
# 1. Build Docker image with new code
cd /Users/ryan/development/veribits.com
docker build -t veribits:2.0.0 -f docker/Dockerfile .

# 2. Tag and push to ECR
aws ecr get-login-password --region us-east-1 | docker login --username AWS \
  --password-stdin 515966511618.dkr.ecr.us-east-1.amazonaws.com

docker tag veribits:2.0.0 \
  515966511618.dkr.ecr.us-east-1.amazonaws.com/veribits:2.0.0

docker push 515966511618.dkr.ecr.us-east-1.amazonaws.com/veribits:2.0.0

# 3. Update ECS task definition
aws ecs register-task-definition --cli-input-json file://task-definition-2.0.0.json

# 4. Update ECS service (rolling update)
aws ecs update-service \
  --cluster veribits-cluster \
  --service veribits-web \
  --task-definition veribits:2.0.0 \
  --force-new-deployment

# 5. Monitor deployment
aws ecs describe-services \
  --cluster veribits-cluster \
  --services veribits-web \
  --query 'services[0].deployments'
```

**Method 2: EC2 Direct Deployment**

```bash
# SSH to production EC2 instances
# For i-09b72622ae7d82664 (13.217.98.251) or i-09f2a819c0140512a (98.83.218.211)

ssh -i ~/.ssh/your-key.pem ec2-user@13.217.98.251

# On EC2 instance
cd /var/www/veribits
git pull origin main

# Update Cuckoo URL in config
# Edit app/src/Controllers/MalwareDetonationController.php
# Change: const CUCKOO_API_URL = 'http://<cuckoo-private-ip>:8090';

# Restart services
docker-compose down
docker-compose up -d

# Or if using systemd
sudo systemctl restart veribits-web
```

#### Blue/Green Deployment (ZERO DOWNTIME)

For production-grade zero-downtime deployment:

```bash
# 1. Create new ECS service (green)
aws ecs create-service \
  --cluster veribits-cluster \
  --service-name veribits-web-green \
  --task-definition veribits:2.0.0 \
  --desired-count 2 \
  --launch-type FARGATE \
  --network-configuration "awsvpcConfiguration={subnets=[subnet-0570f1d90393717f1],securityGroups=[sg-0422b38f99e7b23ce]}" \
  --load-balancers "targetGroupArn=arn:aws:elasticloadbalancing:...,containerName=veribits,containerPort=80"

# 2. Wait for green service to be healthy
aws ecs wait services-stable \
  --cluster veribits-cluster \
  --services veribits-web-green

# 3. Test green service
curl https://veribits.com/api/v1/health

# 4. Switch traffic to green (update ALB target group)
aws elbv2 modify-rule \
  --rule-arn <rule-arn> \
  --actions Type=forward,TargetGroupArn=<green-target-group-arn>

# 5. Monitor for issues (wait 30 minutes)

# 6. If all good, delete old blue service
aws ecs delete-service \
  --cluster veribits-cluster \
  --service veribits-web \
  --force
```

---

### Phase 4: Configuration Updates

#### Update Cuckoo API URL

```bash
# SSH to application server or update ECS task definition environment variables

# Option A: Edit file directly (if on EC2)
vim /var/www/veribits/app/src/Controllers/MalwareDetonationController.php
# Change line 12:
# const CUCKOO_API_URL = 'http://<cuckoo-private-ip>:8090';

# Option B: Use environment variable (recommended for containers)
# Add to ECS task definition environment:
CUCKOO_API_URL=http://10.0.x.x:8090
```

#### Update OAuth2 Client Credentials

```bash
# Generate secure client secret
CLIENT_SECRET=$(openssl rand -hex 32)

# Hash the secret
CLIENT_SECRET_HASH=$(php -r "echo password_hash('$CLIENT_SECRET', PASSWORD_BCRYPT);")

# Update database
psql -h $DB_HOST -U $DB_USER -d $DB_NAME -c "
UPDATE oauth_clients
SET client_secret = '$CLIENT_SECRET_HASH'
WHERE client_id = 'vb_zapier_test_client_0000000000';
"

# Save unhashed secret for Zapier configuration
echo "Client ID: vb_zapier_test_client_0000000000"
echo "Client Secret: $CLIENT_SECRET"
```

#### Configure CLI Package Downloads

```bash
# Create downloads directory in S3 or on web server
mkdir -p /var/www/veribits/public/downloads/cli

# Package CLI tools (already done by deployment script)
cd /Users/ryan/development/veribits.com/veribits-system-client-1.0

# Create tarballs
tar -czf veribits-cli-python-1.0.tar.gz veribits veribits_pro.py veribits_plugin_api.py plugins/ README.md config.json.example

# Upload to S3 (recommended)
aws s3 cp veribits-cli-python-1.0.tar.gz s3://veribits-downloads/cli/

# Or copy to web server
scp veribits-cli-python-1.0.tar.gz ec2-user@13.217.98.251:/var/www/veribits/public/downloads/cli/
```

---

## TESTING PROCEDURES

### Pre-Deployment Testing (Local/Staging)

#### 1. Database Migration Testing

```bash
# Create test database
docker run --name postgres-test -e POSTGRES_PASSWORD=test -d postgres:15
docker exec -it postgres-test psql -U postgres -c "CREATE DATABASE veribits_test;"

# Run migrations
export PGPASSWORD='test'
psql -h localhost -U postgres -d veribits_test -f db/migrations/020_pro_subscriptions_pg.sql
psql -h localhost -U postgres -d veribits_test -f db/migrations/021_oauth_webhooks_pg.sql
psql -h localhost -U postgres -d veribits_test -f db/migrations/022_malware_detonation_pg.sql

# Verify
psql -h localhost -U postgres -d veribits_test -c "\dt"
```

#### 2. API Endpoint Testing

Create test script:

```bash
#!/bin/bash
# test-enterprise-features.sh

API_URL="https://veribits.com/api/v1"
API_KEY="your-test-api-key"

# Test Pro License Validation
echo "Testing Pro License Validation..."
curl -X POST "$API_URL/pro/validate" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"license_key":"VBPRO-DEV-TEST-0000000000000000"}' | jq

# Test Netcat Tool
echo "Testing Netcat..."
curl -X POST "$API_URL/tools/netcat" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"host":"google.com","port":80,"protocol":"tcp"}' | jq

# Test OAuth2 Authorization
echo "Testing OAuth2..."
curl -X GET "$API_URL/oauth/authorize?client_id=vb_zapier_test_client_0000000000&response_type=code&redirect_uri=https://zapier.com/oauth/return&scope=read" | jq

# Test Webhook Creation
echo "Testing Webhook Creation..."
curl -X POST "$API_URL/webhooks" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://webhook.site/test","events":["hash.found"],"description":"Test webhook"}' | jq

# Test Malware Submission (requires Cuckoo)
echo "Testing Malware Submission..."
curl -X POST "$API_URL/malware/submit" \
  -H "Authorization: Bearer $API_KEY" \
  -F "file=@test-file.exe" \
  -F "priority=2" \
  -F "timeout=120" | jq
```

### Post-Deployment Testing (Production)

#### Critical Path Tests

```bash
# 1. Health Check
curl https://veribits.com/api/v1/health

# 2. Database Connectivity Test
curl https://veribits.com/api/v1/pro/validate \
  -X POST \
  -H "Content-Type: application/json" \
  -d '{"license_key":"VBPRO-DEV-TEST-0000000000000000"}'

# Expected: {"success":true,"data":{"valid":true,...}}

# 3. OAuth2 Flow Test
# Step 1: Get authorization code
curl "https://veribits.com/api/v1/oauth/authorize?client_id=vb_zapier_test_client_0000000000&response_type=code&redirect_uri=https://httpbin.org/get&scope=read"

# Step 2: Exchange code for token (use code from redirect)
curl -X POST "https://veribits.com/api/v1/oauth/token" \
  -d "grant_type=authorization_code" \
  -d "code=<code-from-step-1>" \
  -d "client_id=vb_zapier_test_client_0000000000" \
  -d "client_secret=<client-secret>" \
  -d "redirect_uri=https://httpbin.org/get"

# 4. Webhook Test
curl -X POST "https://veribits.com/api/v1/webhooks" \
  -H "Authorization: Bearer <access-token>" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://webhook.site/unique-id","events":["hash.found"],"description":"Test"}'

# 5. Netcat Test
curl -X POST "https://veribits.com/api/v1/tools/netcat" \
  -H "Authorization: Bearer <access-token>" \
  -H "Content-Type: application/json" \
  -d '{"host":"google.com","port":80,"protocol":"tcp"}'

# 6. Malware Submission Test (if Cuckoo is ready)
curl -X POST "https://veribits.com/api/v1/malware/submit" \
  -H "Authorization: Bearer <access-token>" \
  -F "file=@eicar.com" \
  -F "priority=1"
```

#### Load Testing

```bash
# Install Apache Bench
sudo yum install -y httpd-tools  # Amazon Linux
# or
sudo apt-get install -y apache2-utils  # Ubuntu

# Test Netcat endpoint (100 concurrent, 1000 total)
ab -n 1000 -c 100 \
  -H "Authorization: Bearer <token>" \
  -p netcat-payload.json \
  -T application/json \
  https://veribits.com/api/v1/tools/netcat

# Test Pro validation (simpler endpoint)
ab -n 5000 -c 50 \
  -H "Authorization: Bearer <token>" \
  -p pro-payload.json \
  -T application/json \
  https://veribits.com/api/v1/pro/validate
```

---

## ROLLBACK PROCEDURES

### Scenario 1: Database Migration Failed

```bash
# Restore from RDS snapshot
aws rds restore-db-instance-from-db-snapshot \
  --db-instance-identifier nitetext-db-restored \
  --db-snapshot-identifier veribits-pre-enterprise-20251028-120000

# Update application to point to restored instance
# Or restore to original instance:
aws rds restore-db-instance-to-point-in-time \
  --source-db-instance-identifier nitetext-db \
  --target-db-instance-identifier nitetext-db \
  --restore-time 2025-10-28T12:00:00Z
```

### Scenario 2: Application Deployment Failed

```bash
# Rollback ECS service to previous task definition
aws ecs update-service \
  --cluster veribits-cluster \
  --service veribits-web \
  --task-definition veribits:1.9.0

# Or rollback Docker container on EC2
ssh ec2-user@13.217.98.251
cd /var/www/veribits
git reset --hard HEAD~1
docker-compose restart
```

### Scenario 3: Partial Feature Rollback

If only specific features need to be disabled:

```bash
# Disable malware detonation (edit index.php)
# Comment out routes:
// $router->post('/api/v1/malware/submit', ...);

# Or use feature flags (add to database)
psql -h $DB_HOST -U $DB_USER -d $DB_NAME -c "
CREATE TABLE IF NOT EXISTS feature_flags (
  feature VARCHAR(50) PRIMARY KEY,
  enabled BOOLEAN DEFAULT TRUE
);

INSERT INTO feature_flags VALUES
  ('malware_detonation', FALSE),
  ('netcat', TRUE),
  ('oauth2', TRUE),
  ('webhooks', TRUE),
  ('pro_subscriptions', TRUE);
"
```

---

## MONITORING & ALERTING

### CloudWatch Metrics to Monitor

```bash
# Create CloudWatch dashboard
aws cloudwatch put-dashboard \
  --dashboard-name VeriBits-Enterprise \
  --dashboard-body file://cloudwatch-dashboard.json
```

**Dashboard JSON** (`cloudwatch-dashboard.json`):

```json
{
  "widgets": [
    {
      "type": "metric",
      "properties": {
        "metrics": [
          ["AWS/RDS", "DatabaseConnections", {"stat": "Average"}],
          ["AWS/RDS", "CPUUtilization", {"stat": "Average"}],
          ["AWS/RDS", "FreeableMemory", {"stat": "Average"}]
        ],
        "period": 300,
        "stat": "Average",
        "region": "us-east-1",
        "title": "RDS Metrics"
      }
    },
    {
      "type": "log",
      "properties": {
        "query": "SOURCE '/aws/ecs/veribits' | fields @timestamp, @message | filter @message like /ERROR|malware|oauth/",
        "region": "us-east-1",
        "title": "Enterprise Feature Errors"
      }
    }
  ]
}
```

### CloudWatch Alarms

```bash
# Alert on high database connections
aws cloudwatch put-metric-alarm \
  --alarm-name veribits-db-high-connections \
  --alarm-description "Alert when DB connections > 80% of max" \
  --metric-name DatabaseConnections \
  --namespace AWS/RDS \
  --statistic Average \
  --period 300 \
  --threshold 80 \
  --comparison-operator GreaterThanThreshold \
  --evaluation-periods 2

# Alert on malware submission failures
aws logs put-metric-filter \
  --log-group-name /aws/ecs/veribits \
  --filter-name MalwareSubmissionErrors \
  --filter-pattern "[time, request_id, level = ERROR, msg = *malware*]" \
  --metric-transformations \
    metricName=MalwareErrors,metricNamespace=VeriBits,metricValue=1

aws cloudwatch put-metric-alarm \
  --alarm-name veribits-malware-errors \
  --alarm-description "Alert on malware submission errors" \
  --metric-name MalwareErrors \
  --namespace VeriBits \
  --statistic Sum \
  --period 300 \
  --threshold 10 \
  --comparison-operator GreaterThanThreshold \
  --evaluation-periods 1 \
  --alarm-actions arn:aws:sns:us-east-1:515966511618:veribits-alerts
```

### Application-Level Monitoring

Add to your PHP application:

```php
// app/src/Utils/Metrics.php
class Metrics {
    public static function recordMalwareSubmission($status, $duration) {
        // Log to CloudWatch
        error_log(json_encode([
            'metric' => 'malware.submission',
            'status' => $status,
            'duration_ms' => $duration,
            'timestamp' => time()
        ]));
    }

    public static function recordOAuthTokenGeneration($client_id) {
        error_log(json_encode([
            'metric' => 'oauth.token_generated',
            'client_id' => $client_id,
            'timestamp' => time()
        ]));
    }
}
```

---

## SECURITY CONSIDERATIONS

### 1. API Rate Limiting

Already implemented in controllers, but verify:

```bash
# Test rate limiting
for i in {1..15}; do
  curl -X POST https://veribits.com/api/v1/malware/submit \
    -H "Authorization: Bearer $API_KEY" \
    -F "file=@test.exe"
  sleep 1
done

# Should see 429 Too Many Requests after 10 requests
```

### 2. OAuth2 Security

```bash
# Verify HTTPS-only redirects
curl -I "http://veribits.com/api/v1/oauth/authorize?..."
# Should redirect to HTTPS

# Verify state parameter requirement
curl "https://veribits.com/api/v1/oauth/authorize?client_id=test&response_type=code"
# Should return error about missing state parameter
```

### 3. File Upload Security

```bash
# Test malicious file rejection
curl -X POST https://veribits.com/api/v1/malware/submit \
  -H "Authorization: Bearer $API_KEY" \
  -F "file=@/etc/passwd"

# Should reject non-executable files (unless PDF/Office)
```

### 4. SQL Injection Protection

Already using prepared statements, but verify:

```bash
# Test OAuth2 endpoint
curl -X POST https://veribits.com/api/v1/oauth/token \
  -d "client_id=test' OR '1'='1" \
  -d "client_secret=test"

# Should not cause SQL error
```

---

## PRODUCTION READINESS CHECKLIST

### Pre-Deployment

- [ ] Database backup created (RDS snapshot)
- [ ] All migration SQL files reviewed and tested
- [ ] Cuckoo Sandbox deployed and accessible
- [ ] Environment variables configured
- [ ] OAuth2 client secrets rotated from test values
- [ ] SSL certificates valid and not expiring soon
- [ ] CloudWatch alarms configured
- [ ] Rollback plan documented and tested
- [ ] Team notified of deployment window

### Deployment

- [ ] Maintenance window scheduled (if downtime expected)
- [ ] Database migrations executed successfully
- [ ] All 10 tables created and verified
- [ ] Test data inserted (Pro license, OAuth client)
- [ ] Application code deployed
- [ ] Cuckoo URL configured correctly
- [ ] Services restarted

### Post-Deployment

- [ ] Health check endpoint returns 200
- [ ] All 5 features tested manually
- [ ] API endpoints respond correctly
- [ ] Database connections stable
- [ ] No errors in application logs
- [ ] CloudWatch metrics normal
- [ ] Load testing passed
- [ ] Rollback procedure documented
- [ ] Team notified of successful deployment
- [ ] Documentation updated

### 48-Hour Monitoring

- [ ] Database connection pool stable
- [ ] No memory leaks in application
- [ ] Malware submissions processing correctly
- [ ] OAuth2 flow working for Zapier
- [ ] Webhook deliveries successful
- [ ] No unexpected errors in logs
- [ ] Performance metrics within SLA
- [ ] Cost metrics reviewed

---

## COST ANALYSIS

### Estimated Monthly Costs

**Database (RDS)**:
- Existing cost (no change)

**Cuckoo Sandbox**:
- Option A (EC2 t3.xlarge): $128/month
- Option B (Fargate on-demand): $5-30/month
- Option C (External service): $99-299/month

**Storage**:
- Malware samples (S3): ~$5/month (100GB @ $0.023/GB)
- Analysis results (S3): ~$2/month

**Data Transfer**:
- Webhook deliveries: ~$5/month
- API traffic: ~$10/month

**Total Additional Cost**: $135-450/month depending on Cuckoo option

### Revenue Potential

**Pro Subscriptions** (new feature):
- Target: 100 Pro users @ $29/month = $2,900/month
- Target: 10 Enterprise users @ $299/month = $2,990/month
- **Total Potential Revenue**: $5,890/month

**ROI**: 1,300% - 4,400% depending on infrastructure choice

---

## ZAPIER INTEGRATION NEXT STEPS

After deployment, configure Zapier:

### 1. Register OAuth2 App

```bash
# Credentials needed for Zapier:
Client ID: vb_zapier_test_client_0000000000
Client Secret: <generated-in-phase-4>
Authorization URL: https://veribits.com/api/v1/oauth/authorize
Token URL: https://veribits.com/api/v1/oauth/token
Refresh URL: https://veribits.com/api/v1/oauth/token
Revoke URL: https://veribits.com/api/v1/oauth/revoke
```

### 2. Submit Zapier App

File location: `/Users/ryan/development/veribits.com/integrations/zapier/veribits-zapier-app.json`

Instructions:
1. Go to https://zapier.com/app/developer
2. Create New Integration: "VeriBits"
3. Configure OAuth2 with credentials above
4. Import triggers/actions from JSON
5. Test with sample data
6. Submit for review

---

## TROUBLESHOOTING GUIDE

### Issue: Cannot connect to RDS

**Symptom**: `Connection refused` or `Connection timed out`

**Solution**:
```bash
# Verify you're connecting from correct VPC
aws ec2 describe-instances --instance-id $(ec2-metadata --instance-id | cut -d' ' -f2) \
  --query 'Reservations[0].Instances[0].VpcId'

# Should return: vpc-0c1b813880b3982a5

# If different VPC, use bastion host i-0cdcaeed37df5d284
```

### Issue: Malware submission fails

**Symptom**: HTTP 500 error on `/api/v1/malware/submit`

**Solution**:
```bash
# Check Cuckoo is running
curl http://<cuckoo-ip>:8090/cuckoo/status

# Check application logs
aws logs tail /aws/ecs/veribits --follow --format short

# Verify Cuckoo URL in controller
grep CUCKOO_API_URL app/src/Controllers/MalwareDetonationController.php
```

### Issue: OAuth2 tokens not validating

**Symptom**: Invalid token errors

**Solution**:
```bash
# Check token in database
psql -h $DB_HOST -U $DB_USER -d $DB_NAME -c "
SELECT token, expires_at, scope
FROM oauth_access_tokens
WHERE token = 'your-token'
AND expires_at > NOW();
"

# Regenerate token if expired
curl -X POST https://veribits.com/api/v1/oauth/token \
  -d "grant_type=refresh_token" \
  -d "refresh_token=<refresh-token>" \
  -d "client_id=vb_zapier_test_client_0000000000" \
  -d "client_secret=<secret>"
```

### Issue: High database connections

**Symptom**: Database connection errors

**Solution**:
```bash
# Check current connections
psql -h $DB_HOST -U $DB_USER -d $DB_NAME -c "
SELECT count(*) FROM pg_stat_activity;
"

# Kill idle connections
psql -h $DB_HOST -U $DB_USER -d $DB_NAME -c "
SELECT pg_terminate_backend(pid)
FROM pg_stat_activity
WHERE state = 'idle'
AND state_change < NOW() - INTERVAL '5 minutes';
"

# Increase RDS max_connections parameter
aws rds modify-db-parameter-group \
  --db-parameter-group-name <group-name> \
  --parameters "ParameterName=max_connections,ParameterValue=200,ApplyMethod=immediate"
```

---

## DEPLOYMENT TIMELINE

### Estimated Duration: 2-3 hours

**Hour 1: Database Migration**
- 0:00-0:15: Create RDS snapshot
- 0:15-0:30: SSH to bastion host, install psql
- 0:30-0:45: Transfer migration files
- 0:45-1:00: Execute migrations, verify tables

**Hour 2: Cuckoo Deployment**
- 1:00-1:30: Launch EC2/ECS for Cuckoo
- 1:30-2:00: Install and configure Cuckoo
- 2:00-2:15: Test Cuckoo API
- 2:15-2:30: Update application configuration

**Hour 3: Application Deployment**
- 2:30-2:45: Build and push Docker image
- 2:45-3:00: Deploy to ECS/EC2
- 3:00-3:15: Test all endpoints
- 3:15-3:30: Monitor for issues
- 3:30-4:00: Load testing and final validation

---

## CONTACT & ESCALATION

**Deployment Lead**: Ryan (straticus1@gmail.com)

**Escalation Path**:
1. Application issues: Check CloudWatch Logs
2. Database issues: Check RDS metrics, contact AWS Support
3. Network issues: Check security groups, VPC configuration
4. Critical failures: Execute rollback procedure immediately

**AWS Support**:
- Console: https://console.aws.amazon.com/support/
- Phone: 1-866-216-HELP
- Account: 515966511618

---

## CONCLUSION

This deployment plan provides a comprehensive, step-by-step approach to deploying VeriBits 2.0.0 enterprise features. The key success factors are:

1. **Database Access**: Use EC2 bastion host `i-0cdcaeed37df5d284` in same VPC as RDS
2. **Cuckoo Deployment**: Start with external service (Hybrid Analysis) for fastest MVP
3. **Rolling Deployment**: Use ECS rolling updates for zero downtime
4. **Comprehensive Testing**: Test each feature individually before full integration
5. **Rollback Readiness**: RDS snapshot created before any changes

**Recommended Deployment Order**:
1. Database migrations (1 hour)
2. Application deployment with Cuckoo disabled (30 minutes)
3. Test other features (OAuth2, webhooks, netcat, pro subscriptions)
4. Deploy Cuckoo integration last (1 hour)

This staged approach minimizes risk and allows for partial rollback if needed.

**Next Steps**:
1. Review this plan
2. Schedule deployment window (recommend off-peak hours)
3. Create RDS snapshot
4. Begin Phase 1: Database Migration

Good luck with the deployment! 🚀
