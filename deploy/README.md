# VeriBits AWS Deployment Guide

This directory contains all the infrastructure-as-code and deployment scripts needed to deploy the VeriBits platform to AWS.

## Prerequisites

### Required Tools
- **Terraform** >= 1.0 ([Download](https://www.terraform.io/downloads))
- **AWS CLI** >= 2.0 ([Download](https://aws.amazon.com/cli/))
- **Node.js** >= 18 ([Download](https://nodejs.org/))
- **Git** (for version control)

### AWS Requirements
- AWS Account with administrative access
- AWS CLI configured with appropriate credentials
- EC2 Key Pair created in your target region
- Stripe account with API keys (for billing)

### Configure AWS CLI

```bash
aws configure --profile veribits
# Enter your AWS Access Key ID
# Enter your AWS Secret Access Key
# Enter your default region (e.g., us-east-1)
# Enter output format: json

export AWS_PROFILE=veribits
```

## Quick Start Deployment

### 1. Clone and Navigate

```bash
cd /Users/ryan/development/veribits.com/deploy
```

### 2. Review Configuration

Edit `terraform/terraform.tfvars` if it exists, or the script will generate one with secure random passwords.

**Important variables to update:**
- `key_pair_name`: Your EC2 key pair name for SSH access
- `stripe_secret_key`: Your Stripe secret key
- `stripe_publishable_key`: Your Stripe publishable key
- `stripe_webhook_secret`: Your Stripe webhook secret

### 3. Run Deployment

```bash
chmod +x deploy.sh
./deploy.sh
```

The script will:
1. Initialize Terraform
2. Generate secure passwords (if needed)
3. Create S3 bucket for deployment
4. Package the application
5. Upload to S3
6. Plan infrastructure deployment
7. Ask for confirmation
8. Deploy infrastructure
9. Run database migrations
10. Create test accounts
11. Test application health

### 4. Test Deployment

Once deployment completes, test the application:

```bash
# Set the ALB DNS from deployment output
export BASE_URL=http://your-alb-dns-here.amazonaws.com

# Install test dependencies
cd ../tests
npm install

# Run comprehensive tests
npm run test:all
```

## Infrastructure Components

### Network Architecture
- **VPC**: 10.0.0.0/16 CIDR with DNS support
- **Public Subnets**: 2 subnets for ALB and NAT Gateway (10.0.1.0/24, 10.0.2.0/24)
- **Private Subnets**: 2 subnets for EC2, RDS, ElastiCache (10.0.10.0/24, 10.0.11.0/24)
- **Internet Gateway**: For public internet access
- **Route Tables**: Public and private routing

### Compute
- **Application Load Balancer**: Distributes traffic across EC2 instances
- **Auto Scaling Group**: 2-6 instances based on load
- **EC2 Instances**: t3.medium running Amazon Linux 2023
- **Launch Template**: Bootstraps instances with user data script

### Database
- **RDS PostgreSQL 16**: Primary database (db.t3.medium)
- **Multi-AZ**: Enabled in production for high availability
- **Automated Backups**: 7-day retention
- **Storage**: 100GB GP3 encrypted

### Caching & Session Store
- **ElastiCache Redis 7**: Rate limiting and caching
- **Node Type**: cache.t3.medium
- **Replication**: Multi-node in production
- **Encryption**: At-rest and in-transit enabled

### Storage
- **S3 Bucket**: File uploads (with public access blocked)

### Security
- **Security Groups**: Granular access control
  - ALB: HTTP/HTTPS from internet
  - EC2: HTTP from ALB, SSH from specified IPs
  - RDS: PostgreSQL from EC2 only
  - Redis: Port 6379 from EC2 only
- **IAM Roles**: EC2 instance profile with SSM access
- **Encryption**: All data encrypted at rest

## Manual Configuration Steps

### 1. Update Terraform Variables

Edit `terraform/terraform.tfvars`:

```hcl
# Your EC2 key pair name
key_pair_name = "veribits-production"

# Stripe API keys
stripe_secret_key = "sk_live_YOUR_SECRET_KEY"
stripe_publishable_key = "pk_live_YOUR_PUBLIC_KEY"
stripe_webhook_secret = "whsec_YOUR_WEBHOOK_SECRET"

# SSH access (optional)
ssh_allowed_cidrs = ["YOUR_IP/32"]
```

### 2. Create EC2 Key Pair

```bash
aws ec2 create-key-pair \
  --key-name veribits-production \
  --query 'KeyMaterial' \
  --output text > ~/.ssh/veribits-production.pem

chmod 400 ~/.ssh/veribits-production.pem
```

### 3. Deploy Infrastructure

```bash
cd terraform
terraform init
terraform plan
terraform apply
```

### 4. Configure DNS

After deployment, configure your domain DNS:

```
# Get ALB DNS
terraform output alb_dns_name

# Create CNAME record
veribits.com → your-alb-dns.amazonaws.com
www.veribits.com → your-alb-dns.amazonaws.com
```

### 5. Set Up SSL Certificate

```bash
# Request certificate in AWS Certificate Manager
aws acm request-certificate \
  --domain-name veribits.com \
  --subject-alternative-names www.veribits.com \
  --validation-method DNS

# Follow DNS validation instructions in ACM console

# Update ALB listener to use HTTPS (after certificate validated)
# Update terraform/main.tf with certificate ARN
# Run terraform apply
```

## Post-Deployment Tasks

### 1. Verify Application Health

```bash
curl http://YOUR_ALB_DNS/api/v1/health
```

Expected response:
```json
{
  "status": "healthy",
  "timestamp": "2025-10-26T14:51:34+00:00"
}
```

### 2. Test Database Connection

```bash
# SSH into an EC2 instance
ssh -i ~/.ssh/veribits-production.pem ec2-user@EC2_PUBLIC_IP

# Test PostgreSQL connection
psql -h RDS_ENDPOINT -U veribits_admin -d veribits -c "SELECT COUNT(*) FROM users;"
```

### 3. Test Redis Connection

```bash
redis-cli -h REDIS_ENDPOINT -p 6379 -a REDIS_AUTH_TOKEN --tls PING
```

### 4. Run Comprehensive Tests

```bash
cd /Users/ryan/development/veribits.com/tests
npm install

# Set base URL from deployment
export BASE_URL=http://your-alb-dns.amazonaws.com

# Run Puppeteer tests
npm run test:puppeteer

# Run Playwright tests
npm run test:playwright

# Run all tests
npm run test:all
```

### 5. Monitor Logs

```bash
# Application logs
ssh -i ~/.ssh/veribits-production.pem ec2-user@EC2_IP
tail -f /var/log/veribits/app.log

# Apache logs
tail -f /var/log/httpd/veribits_error.log

# PHP errors
tail -f /var/log/veribits/php_errors.log
```

## Test Accounts

The following test accounts are automatically created during deployment:

| Email | Password | Role | Quota | API Key |
|-------|----------|------|-------|---------|
| admin@veribits-test.com | Admin123!@# | admin | Unlimited | vb_admin_test_key_000000000000000000000000000001 |
| developer@veribits-test.com | Dev123!@# | user | 10,000/month | vb_dev_test_key_000000000000000000000000000002 |
| professional@veribits-test.com | Pro123!@# | user | 100,000/month | vb_pro_test_key_000000000000000000000000000003 |
| enterprise@veribits-test.com | Ent123!@# | user | Unlimited | vb_ent_test_key_000000000000000000000000000004 |
| suspended@veribits-test.com | Sus123!@# | user | N/A | None (for testing access control) |

## Cost Estimation

### Monthly AWS Costs (Production)

| Service | Configuration | Estimated Cost |
|---------|--------------|----------------|
| EC2 (2x t3.medium) | 24/7 | ~$60 |
| RDS (db.t3.medium) | Multi-AZ | ~$130 |
| ElastiCache (cache.t3.medium) | Multi-node | ~$100 |
| ALB | 1 ALB | ~$20 |
| Data Transfer | Moderate | ~$50 |
| S3 Storage | Minimal | ~$5 |
| **Total** | | **~$365/month** |

### Cost Optimization Tips
1. Use Reserved Instances for 30-40% savings
2. Enable auto-scaling to reduce instances during low traffic
3. Use CloudFront CDN to reduce data transfer costs
4. Archive old audit logs to S3 Glacier

## Scaling Configuration

### Auto Scaling Policies

Current configuration:
- **Minimum**: 2 instances
- **Maximum**: 6 instances
- **Desired**: 2 instances

To adjust, edit `terraform/variables.tf`:

```hcl
variable "asg_min_size" {
  default = 4  # Increase for more capacity
}

variable "asg_max_size" {
  default = 12  # Allow more scaling
}
```

Then apply:
```bash
terraform apply
```

## Disaster Recovery

### Database Backups

RDS automated backups:
- **Retention**: 7 days
- **Backup Window**: 03:00-04:00 UTC
- **Snapshots**: Manual snapshots available

Restore from backup:
```bash
aws rds restore-db-instance-from-db-snapshot \
  --db-instance-identifier veribits-restored \
  --db-snapshot-identifier snapshot-name
```

### Application Recovery

Application code is stored in S3:
```bash
aws s3 cp s3://DEPLOY_BUCKET/veribits-deploy.tar.gz ./
tar -xzf veribits-deploy.tar.gz
```

## Monitoring & Alerts

### CloudWatch Metrics

Key metrics to monitor:
- **EC2**: CPU, Memory, Disk, Network
- **RDS**: CPU, Connections, Read/Write IOPS
- **Redis**: CPU, Evictions, Connections
- **ALB**: Request Count, Target Response Time, 4xx/5xx errors

### Set Up Alarms

```bash
# High CPU alarm
aws cloudwatch put-metric-alarm \
  --alarm-name veribits-high-cpu \
  --alarm-description "Alert when CPU exceeds 80%" \
  --metric-name CPUUtilization \
  --namespace AWS/EC2 \
  --statistic Average \
  --period 300 \
  --threshold 80 \
  --comparison-operator GreaterThanThreshold \
  --evaluation-periods 2
```

## Security Hardening

### SSL/TLS Configuration

1. Request ACM certificate
2. Update ALB listener to redirect HTTP → HTTPS
3. Update terraform/main.tf:

```hcl
resource "aws_lb_listener" "https" {
  load_balancer_arn = aws_lb.veribits.arn
  port              = "443"
  protocol          = "HTTPS"
  ssl_policy        = "ELBSecurityPolicy-TLS-1-2-2017-01"
  certificate_arn   = "arn:aws:acm:region:account:certificate/xxx"

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.veribits.arn
  }
}

resource "aws_lb_listener" "http_redirect" {
  load_balancer_arn = aws_lb.veribits.arn
  port              = "80"
  protocol          = "HTTP"

  default_action {
    type = "redirect"
    redirect {
      port        = "443"
      protocol    = "HTTPS"
      status_code = "HTTP_301"
    }
  }
}
```

### WAF Configuration

```bash
# Create WAF Web ACL
aws wafv2 create-web-acl \
  --name veribits-waf \
  --scope REGIONAL \
  --default-action Allow={} \
  --rules file://waf-rules.json

# Associate with ALB
aws wafv2 associate-web-acl \
  --web-acl-arn WAF_ARN \
  --resource-arn ALB_ARN
```

## Troubleshooting

### Application Not Responding

1. Check ALB target health:
```bash
aws elbv2 describe-target-health --target-group-arn TG_ARN
```

2. Check EC2 instances:
```bash
aws ec2 describe-instances --filters "Name=tag:Name,Values=veribits-app"
```

3. SSH into instance and check logs:
```bash
ssh -i ~/.ssh/veribits-production.pem ec2-user@EC2_IP
sudo systemctl status httpd
sudo tail -f /var/log/httpd/error_log
```

### Database Connection Issues

1. Check RDS status:
```bash
aws rds describe-db-instances --db-instance-identifier veribits-postgres
```

2. Check security group rules:
```bash
aws ec2 describe-security-groups --group-ids RDS_SG_ID
```

3. Test connection from EC2:
```bash
psql -h RDS_ENDPOINT -U veribits_admin -d veribits
```

### Redis Connection Issues

1. Check ElastiCache status:
```bash
aws elasticache describe-replication-groups --replication-group-id veribits-redis
```

2. Test from EC2:
```bash
redis-cli -h REDIS_ENDPOINT -p 6379 -a AUTH_TOKEN --tls PING
```

## Cleanup / Destroy

**WARNING**: This will delete all resources and data!

```bash
cd terraform
terraform destroy
```

Confirm by typing `yes` when prompted.

Also delete S3 buckets manually:
```bash
aws s3 rb s3://DEPLOY_BUCKET --force
aws s3 rb s3://veribits-uploads-production --force
```

## Support

For deployment issues, contact:
- **Email**: devops@afterdarksystems.com
- **Documentation**: /Users/ryan/development/veribits.com/DEPLOYMENT_SUMMARY.md
- **Architecture**: /Users/ryan/development/veribits.com/VERIBITS_ONE_PAGER.md

## License

Proprietary - After Dark Systems, LLC © 2025
