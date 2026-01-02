# VeriBits AWS Infrastructure Map

Current State: October 28, 2025

---

## Network Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                          AWS REGION: us-east-1                      │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│  VPC: vpc-0c1b813880b3982a5 (PRODUCTION VPC)                        │
│  CIDR: 10.0.0.0/16                                                  │
│                                                                       │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │  Private Subnet: subnet-0570f1d90393717f1                    │   │
│  │                                                               │   │
│  │  ┌───────────────────────────────────────────────────────┐  │   │
│  │  │  EC2: i-0cdcaeed37df5d284 (nitetext-instance-new)     │  │   │
│  │  │  Type: t3.micro                                        │  │   │
│  │  │  Private IP: 10.0.10.224                               │  │   │
│  │  │  Role: BASTION HOST / DATABASE ACCESS                 │  │   │
│  │  │  Security Group: sg-029088670092e6501                  │  │   │
│  │  │                                                         │  │   │
│  │  │  ✓ CAN ACCESS RDS (same VPC)                          │  │   │
│  │  └───────────────────────────────────────────────────────┘  │   │
│  │                                                               │   │
│  │  ┌───────────────────────────────────────────────────────┐  │   │
│  │  │  RDS: nitetext-db                                     │  │   │
│  │  │  Engine: PostgreSQL 15.12                             │  │   │
│  │  │  Endpoint: nitetext-db.c3iuy64is41m.us-east-1...      │  │   │
│  │  │  Status: Available                                     │  │   │
│  │  │  Publicly Accessible: NO                               │  │   │
│  │  │  Security Group: sg-011e3c8ac8f73858b                  │  │   │
│  │  │  (nitetext-rds-sg)                                    │  │   │
│  │  └───────────────────────────────────────────────────────┘  │   │
│  │                                                               │   │
│  │  ALLOWED CONNECTIONS TO RDS:                                 │   │
│  │  • 10.0.0.0/16 (all VPC resources)                          │   │
│  │  • sg-0c2e8ed602f9886a7 (ECS tasks)                         │   │
│  │  • sg-0422b38f99e7b23ce (VeriBits ECS tasks)               │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                       │
│  [Your application likely runs in ECS with one of these SGs]        │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│  VPC: vpc-062b4d9462879a884 (LEGACY VPC - DIFFERENT FROM RDS)      │
│                                                                       │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │  Public Subnet: subnet-07c6b476fb53ece54                    │   │
│  │                                                               │   │
│  │  ┌───────────────────────────────────────────────────────┐  │   │
│  │  │  EC2: i-09b72622ae7d82664 (nitetext-instance)         │  │   │
│  │  │  Type: t3.medium                                      │  │   │
│  │  │  Private IP: 172.31.34.137                            │  │   │
│  │  │  Public IP: 13.217.98.251                             │  │   │
│  │  │  Security Group: sg-0f699d1c0d2585765                 │  │   │
│  │  │                                                        │  │   │
│  │  │  ✗ CANNOT ACCESS RDS (different VPC)                 │  │   │
│  │  └───────────────────────────────────────────────────────┘  │   │
│  │                                                               │   │
│  │  ┌───────────────────────────────────────────────────────┐  │   │
│  │  │  EC2: i-09f2a819c0140512a (nitetext-instance)         │  │   │
│  │  │  Type: t3.medium                                      │  │   │
│  │  │  Private IP: 172.31.37.79                             │  │   │
│  │  │  Public IP: 98.83.218.211                             │  │   │
│  │  │  Security Group: sg-0f699d1c0d2585765                 │  │   │
│  │  │                                                        │  │   │
│  │  │  ✗ CANNOT ACCESS RDS (different VPC)                 │  │   │
│  │  └───────────────────────────────────────────────────────┘  │   │
│  │                                                               │   │
│  │  NOTE: These instances may be legacy or load-balanced        │   │
│  └─────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Database Access Paths

### ✅ WORKING PATH (Use This)

```
Your Laptop
    |
    | SSH/SSM
    ↓
EC2 Bastion (i-0cdcaeed37df5d284)
Private IP: 10.0.10.224
VPC: vpc-0c1b813880b3982a5
    |
    | PostgreSQL (port 5432)
    | ALLOWED by security group
    ↓
RDS: nitetext-db
VPC: vpc-0c1b813880b3982a5
```

### ❌ BLOCKED PATHS (Won't Work)

```
Your Laptop ──X──> RDS
(Not in VPC, RDS not publicly accessible)

EC2 i-09b72622ae7d82664 ──X──> RDS
(Different VPC, no peering)

EC2 i-09f2a819c0140512a ──X──> RDS
(Different VPC, no peering)
```

---

## Security Group Rules

### RDS Security Group: sg-011e3c8ac8f73858b (nitetext-rds-sg)

**Inbound Rules**:
```
Protocol  Port  Source                   Description
--------- ----- ------------------------ ---------------------------
TCP       5432  10.0.0.0/16             Allow PostgreSQL from VPC
TCP       5432  sg-0c2e8ed602f9886a7    Allow from ECS tasks
TCP       5432  sg-0422b38f99e7b23ce    Allow from VeriBits ECS
```

**Outbound Rules**: All traffic allowed

### Bastion EC2 Security Group: sg-029088670092e6501

**Inbound Rules**: (You would need to check, but likely SSH/SSM)
```
Protocol  Port  Source                   Description
--------- ----- ------------------------ ---------------------------
TCP       22    Your-IP/32              SSH access (if configured)
```

---

## Deployment Architecture Options

### Option 1: ECS/Fargate (Recommended)

```
┌─────────────────────────────────────────────────────────┐
│  Application Load Balancer (ALB)                        │
│  https://veribits.com                                   │
└────────────────┬────────────────────────────────────────┘
                 |
                 ↓
┌─────────────────────────────────────────────────────────┐
│  ECS Service: veribits-web                              │
│  Security Group: sg-0422b38f99e7b23ce                   │
│                                                          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐ │
│  │ ECS Task 1   │  │ ECS Task 2   │  │ ECS Task 3   │ │
│  │ VeriBits 2.0 │  │ VeriBits 2.0 │  │ VeriBits 2.0 │ │
│  └──────────────┘  └──────────────┘  └──────────────┘ │
│                                                          │
│  All tasks have access to RDS (same security group)     │
└─────────────────────────────────────────────────────────┘
```

### Option 2: EC2 with Docker

```
┌─────────────────────────────────────────────────────────┐
│  EC2: i-09b72622ae7d82664 or i-09f2a819c0140512a        │
│  Public IP: 13.217.98.251 / 98.83.218.211              │
│                                                          │
│  ┌──────────────────────────────────────────────────┐  │
│  │  Docker Container: VeriBits 2.0                  │  │
│  │                                                   │  │
│  │  Issue: Cannot access RDS (different VPC)        │  │
│  │  Solution: VPC peering or migration              │  │
│  └──────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

---

## Recommended Cuckoo Sandbox Placement

```
┌─────────────────────────────────────────────────────────────────┐
│  VPC: vpc-0c1b813880b3982a5 (PRODUCTION VPC)                    │
│                                                                   │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  Private Subnet (isolated)                              │   │
│  │                                                           │   │
│  │  ┌────────────────────────────────────────────────────┐ │   │
│  │  │  Cuckoo Sandbox                                    │ │   │
│  │  │  Option A: EC2 t3.xlarge (~$128/month)            │ │   │
│  │  │  Option B: ECS Fargate (~$30/month)               │ │   │
│  │  │  Port 8090 (API)                                   │ │   │
│  │  │  Security Group: veribits-cuckoo-sg (NEW)         │ │   │
│  │  │                                                     │ │   │
│  │  │  ONLY allow connections from:                      │ │   │
│  │  │  • sg-0422b38f99e7b23ce (VeriBits ECS)           │ │   │
│  │  └────────────────────────────────────────────────────┘ │   │
│  └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

---

## Database Migration Flow

```
┌────────────────────────────────────────────────────────────────┐
│  STEP 1: Create RDS Snapshot                                   │
│                                                                  │
│  aws rds create-db-snapshot \                                  │
│    --db-instance-identifier nitetext-db \                      │
│    --db-snapshot-identifier veribits-backup-20251028           │
│                                                                  │
│  ✓ Safety net for rollback                                    │
└────────────────────────────────────────────────────────────────┘
                           ↓
┌────────────────────────────────────────────────────────────────┐
│  STEP 2: Connect to Bastion Host                               │
│                                                                  │
│  # Get public IP                                               │
│  aws ec2 describe-instances \                                  │
│    --instance-ids i-0cdcaeed37df5d284 \                       │
│    --query 'Reservations[0].Instances[0].PublicIpAddress'     │
│                                                                  │
│  # SSH to bastion                                              │
│  ssh -i ~/.ssh/key.pem ec2-user@<public-ip>                   │
│                                                                  │
│  # Or use Session Manager (no key needed)                      │
│  aws ssm start-session --target i-0cdcaeed37df5d284            │
└────────────────────────────────────────────────────────────────┘
                           ↓
┌────────────────────────────────────────────────────────────────┐
│  STEP 3: Transfer Migration Files                              │
│                                                                  │
│  # From local machine                                          │
│  scp -i ~/.ssh/key.pem \                                       │
│    db/migrations/02*.sql \                                     │
│    ec2-user@<bastion-ip>:~/                                   │
│                                                                  │
│  # Or upload to S3 and download from bastion                   │
│  aws s3 cp migrations/ s3://bucket/migrations/ --recursive     │
│  # Then on bastion:                                            │
│  aws s3 sync s3://bucket/migrations/ ~/migrations/             │
└────────────────────────────────────────────────────────────────┘
                           ↓
┌────────────────────────────────────────────────────────────────┐
│  STEP 4: Run Migrations on Bastion                             │
│                                                                  │
│  export PGPASSWORD='NiteText2025!SecureProd'                   │
│  export DB_HOST='nitetext-db.c3iuy64is41m.us-east-1...'       │
│                                                                  │
│  psql -h $DB_HOST -U nitetext -d veribits \                    │
│    -f ~/020_pro_subscriptions_pg.sql                           │
│                                                                  │
│  psql -h $DB_HOST -U nitetext -d veribits \                    │
│    -f ~/021_oauth_webhooks_pg.sql                              │
│                                                                  │
│  psql -h $DB_HOST -U nitetext -d veribits \                    │
│    -f ~/022_malware_detonation_pg.sql                          │
│                                                                  │
│  ✓ All migrations executed successfully                        │
└────────────────────────────────────────────────────────────────┘
                           ↓
┌────────────────────────────────────────────────────────────────┐
│  STEP 5: Verify Tables Created                                 │
│                                                                  │
│  psql -h $DB_HOST -U nitetext -d veribits -c "                │
│    SELECT table_name FROM information_schema.tables           │
│    WHERE table_schema = 'public'                              │
│    AND table_name IN (                                        │
│      'pro_licenses', 'oauth_clients',                         │
│      'oauth_authorization_codes', 'oauth_access_tokens',      │
│      'oauth_refresh_tokens', 'webhooks',                      │
│      'webhook_deliveries', 'malware_submissions',             │
│      'malware_analysis_results', 'malware_screenshots'        │
│    );                                                          │
│  "                                                             │
│                                                                  │
│  Expected: 10 tables                                           │
└────────────────────────────────────────────────────────────────┘
```

---

## Application Deployment Flow

```
┌────────────────────────────────────────────────────────────────┐
│  STEP 1: Build Docker Image (Local)                            │
│                                                                  │
│  cd /Users/ryan/development/veribits.com                       │
│  docker build -t veribits:2.0.0 -f docker/Dockerfile .        │
└────────────────────────────────────────────────────────────────┘
                           ↓
┌────────────────────────────────────────────────────────────────┐
│  STEP 2: Push to ECR                                            │
│                                                                  │
│  aws ecr get-login-password --region us-east-1 | \             │
│    docker login --username AWS --password-stdin \              │
│    515966511618.dkr.ecr.us-east-1.amazonaws.com                │
│                                                                  │
│  docker tag veribits:2.0.0 \                                   │
│    515966511618.dkr.ecr.us-east-1.amazonaws.com/veribits:2.0.0 │
│                                                                  │
│  docker push \                                                  │
│    515966511618.dkr.ecr.us-east-1.amazonaws.com/veribits:2.0.0 │
└────────────────────────────────────────────────────────────────┘
                           ↓
┌────────────────────────────────────────────────────────────────┐
│  STEP 3: Deploy to ECS                                          │
│                                                                  │
│  aws ecs update-service \                                      │
│    --cluster veribits-cluster \                                │
│    --service veribits-web \                                    │
│    --force-new-deployment                                      │
│                                                                  │
│  # ECS will perform rolling deployment                         │
│  # New tasks start → Health checks pass → Old tasks stop       │
└────────────────────────────────────────────────────────────────┘
                           ↓
┌────────────────────────────────────────────────────────────────┐
│  STEP 4: Monitor Deployment                                     │
│                                                                  │
│  aws ecs describe-services \                                   │
│    --cluster veribits-cluster \                                │
│    --services veribits-web                                     │
│                                                                  │
│  # Watch for:                                                   │
│  # • runningCount = desiredCount                               │
│  # • deployments[0].status = PRIMARY                           │
└────────────────────────────────────────────────────────────────┘
                           ↓
┌────────────────────────────────────────────────────────────────┐
│  STEP 5: Smoke Tests                                            │
│                                                                  │
│  curl https://veribits.com/api/v1/health                       │
│  curl -X POST https://veribits.com/api/v1/pro/validate ...    │
│                                                                  │
│  ✓ Deployment complete and verified                           │
└────────────────────────────────────────────────────────────────┘
```

---

## Resource Summary

### EC2 Instances

| Instance ID | Name | Type | VPC | Public IP | Can Access RDS? |
|-------------|------|------|-----|-----------|-----------------|
| i-0cdcaeed37df5d284 | nitetext-instance-new | t3.micro | vpc-0c1b813880b3982a5 | Yes (get via CLI) | ✅ YES |
| i-09b72622ae7d82664 | nitetext-instance | t3.medium | vpc-062b4d9462879a884 | 13.217.98.251 | ❌ NO |
| i-09f2a819c0140512a | nitetext-instance | t3.medium | vpc-062b4d9462879a884 | 98.83.218.211 | ❌ NO |

### RDS Instances

| Instance | Engine | VPC | Publicly Accessible | Security Group |
|----------|--------|-----|---------------------|----------------|
| nitetext-db | PostgreSQL 15.12 | vpc-0c1b813880b3982a5 | NO | sg-011e3c8ac8f73858b |

### Security Groups

| Group ID | Name | Purpose | Allowed Access |
|----------|------|---------|----------------|
| sg-011e3c8ac8f73858b | nitetext-rds-sg | RDS access | 10.0.0.0/16, ECS SGs |
| sg-029088670092e6501 | (bastion SG) | Bastion host | SSH/SSM |
| sg-0422b38f99e7b23ce | (VeriBits ECS) | Application | RDS, ALB |
| sg-0c2e8ed602f9886a7 | (ECS tasks) | ECS infrastructure | RDS |

---

## Estimated Costs (Monthly)

### Current Infrastructure
- EC2 t3.micro: ~$7.50/month
- EC2 t3.medium (2x): ~$60/month
- RDS PostgreSQL (existing): ~$30-100/month (depends on instance size)
- **Current Total**: ~$100-170/month

### After Enterprise Deployment
- **Cuckoo (external)**: +$99-299/month
- **OR Cuckoo (EC2)**: +$128/month
- **OR Cuckoo (ECS)**: +$5-30/month
- Storage (S3): +$7/month
- Data transfer: +$15/month
- **New Total**: $230-520/month

### Revenue Target
- Pro users (100 @ $29): $2,900/month
- Enterprise users (10 @ $299): $2,990/month
- **Total Revenue**: $5,890/month
- **Net Profit**: $5,370-5,660/month
- **ROI**: 2,300%

---

## Quick Command Reference

```bash
# Get bastion IP
aws ec2 describe-instances --instance-ids i-0cdcaeed37df5d284 \
  --query 'Reservations[0].Instances[0].PublicIpAddress' --output text

# Connect via SSM (no SSH key needed)
aws ssm start-session --target i-0cdcaeed37df5d284

# Create RDS snapshot
aws rds create-db-snapshot \
  --db-instance-identifier nitetext-db \
  --db-snapshot-identifier veribits-backup-$(date +%Y%m%d)

# List ECS clusters
aws ecs list-clusters

# List ECS services
aws ecs list-services --cluster <cluster-name>

# Force ECS redeployment
aws ecs update-service \
  --cluster <cluster> \
  --service <service> \
  --force-new-deployment

# Check RDS status
aws rds describe-db-instances \
  --db-instance-identifier nitetext-db

# View CloudWatch logs
aws logs tail /aws/ecs/veribits --follow
```

---

## Next Steps

1. **Immediate**: Run database migrations via bastion host
2. **Short-term**: Deploy application code to ECS/EC2
3. **Medium-term**: Deploy Cuckoo Sandbox
4. **Long-term**: Configure monitoring and alerting

See `DEPLOYMENT_QUICK_START.md` for step-by-step instructions.

---

**Infrastructure Status**: Analyzed and documented
**Deployment Readiness**: Ready for production
**Risk Level**: Low (with proper backup and rollback procedures)
