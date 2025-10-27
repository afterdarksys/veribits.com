# VeriBits Deployment Instructions

## What Was Added

### 1. Documentation Page (`/docs.php`)
- ✅ Comprehensive documentation covering all aspects of VeriBits
- ✅ Why VeriBits was created
- ✅ Problems it solves with real examples
- ✅ Complete CLI usage guide
- ✅ Most common operations
- ✅ Licensing explained (Free, Starter, Professional, Enterprise)
- ✅ How to obtain and add licenses
- ✅ Terraform provider configuration
- ✅ Ansible module configuration

### 2. Navigation Update
- ✅ Added "Docs" link to main navigation in `home.php`
- Location: Between "CLI" and "Pricing"

### 3. Interactive Console Mode
- ✅ Added to Node.js CLI (v2.0.0)
- ✅ Added to Python CLI (v2.0.0)
- ✅ TAB completion support
- ✅ Command history
- ✅ Persistent authentication

## Deployment Steps

### Option 1: Docker Build & Deploy (Recommended)

```bash
# 1. Start Docker Desktop
open -a Docker

# 2. Wait for Docker to start, then build image
docker build -t veribits-web:latest -f docker/Dockerfile .

# 3. Tag for ECR
AWS_ACCOUNT_ID=515966511618
AWS_REGION=us-east-1
docker tag veribits-web:latest $AWS_ACCOUNT_ID.dkr.ecr.$AWS_REGION.amazonaws.com/veribits:latest

# 4. Login to ECR
aws ecr get-login-password --region $AWS_REGION | docker login --username AWS --password-stdin $AWS_ACCOUNT_ID.dkr.ecr.$AWS_REGION.amazonaws.com

# 5. Push to ECR
docker push $AWS_ACCOUNT_ID.dkr.ecr.$AWS_REGION.amazonaws.com/veribits:latest

# 6. Force ECS to deploy new image
aws ecs update-service \
    --cluster veribits-cluster \
    --service veribits-api \
    --force-new-deployment \
    --region us-east-1

# 7. Monitor deployment
aws ecs describe-services \
    --cluster veribits-cluster \
    --services veribits-api \
    --region us-east-1 | grep runningCount
```

### Option 2: Quick Deploy Script

```bash
# Start Docker first, then run:
chmod +x scripts/quick-deploy.sh
./scripts/quick-deploy.sh
```

### Option 3: Manual File Sync (if EC2-based)

```bash
# Find EC2 instance
aws ec2 describe-instances \
    --filters "Name=tag:Name,Values=veribits-web" \
    --query 'Reservations[*].Instances[*].[PublicIpAddress,State.Name]' \
    --output text

# Sync files
rsync -avz app/ ec2-user@<IP>:/var/www/veribits/app/

# Restart services
ssh ec2-user@<IP> "sudo systemctl restart php-fpm nginx"
```

## Current Infrastructure Status

### ECS Cluster
- **Cluster:** veribits-cluster
- **Service:** veribits-api
- **Tasks Running:** 2
- **Task Definition:** veribits-api:2
- **Launch Type:** Fargate
- **Platform:** 1.4.0

### Load Balancer
- **DNS:** veribits-alb-1472450181.us-east-1.elb.amazonaws.com
- **Domain:** www.veribits.com (should point to ALB)

### ECR Repository
- **Repository:** veribits
- **Account:** 515966511618
- **Region:** us-east-1

## Verification After Deployment

```bash
# 1. Check ECS service is running
aws ecs describe-services --cluster veribits-cluster --services veribits-api --region us-east-1

# 2. Test the docs page
curl -I https://www.veribits.com/docs.php
# Should return: HTTP/1.1 200 OK

# 3. Test navigation
curl https://www.veribits.com/ | grep -o 'href="/docs.php"'
# Should find the docs link

# 4. Full verification
open https://www.veribits.com/docs.php
```

## What the Docs Page Includes

### Section 1: Why VeriBits
- Enterprise-grade security without complexity
- Developer-friendly APIs and CLI
- Speed & reliability (99.9% uptime)
- Automation-ready for CI/CD

### Section 2: Problems It Solves
- ✅ Secrets in code (automated scanning)
- ✅ Overly permissive IAM policies
- ✅ SSL certificate expiration
- ✅ Email deliverability issues
- ✅ Data breaches (HIBP integration)
- ✅ Misconfigured cloud storage

### Section 3: CLI Usage
- Installation for Node.js, Python, PHP
- Basic usage examples
- **NEW:** Interactive console mode with TAB completion
- Authentication methods (env var, inline, console)
- All 48 commands listed with examples

### Section 4: Common Operations
1. Pre-deployment security scan
2. Email domain investigation
3. SSL/TLS certificate management
4. CI/CD integration
5. Data breach checking
6. Network investigation
7. Quick development tools

### Section 5: Licensing
- Pricing table (Free, Starter, Professional, Enterprise)
- What's included in each tier
- License types explained

### Section 6: Obtain License
- Step-by-step account creation
- How to choose a plan
- Getting your API key
- Key regeneration for security

### Section 7: Add License to CLI
- Method 1: Environment variable (recommended)
  - Linux/macOS examples
  - Windows PowerShell examples
  - Windows CMD examples
- Method 2: Command-line flag
- Method 3: Console mode
- Method 4: CI/CD environment
  - GitHub Actions example
  - GitLab CI example
- Best practices for secrets management
- Verification commands

### Section 8: Terraform Provider
- Installation instructions
- Configuration with variables
- Example usage:
  - IAM policy validation
  - SSL certificate checking
  - Secrets scanning
  - Email verification
- Available resources & data sources
- Status: Coming Q1 2026

### Section 9: Ansible Module
- Installation from Ansible Galaxy
- Configuration with vault
- Complete example playbook:
  - Secrets scanning
  - IAM policy validation
  - SSL certificate monitoring
  - Email configuration verification
  - Cloud storage security
- Available modules list
- CI/CD integration
- Status: Coming Q1 2026

## Files Changed

### New Files
- `app/public/docs.php` - Complete documentation page

### Modified Files
- `app/public/home.php` - Navigation updated (line 35)

### CLI Updates (v2.0.0)
- `cli/nodejs/lib/cli.js` - Added console mode (~300 lines)
- `cli/nodejs/bin/veribits.js` - Added console command
- `cli/nodejs/package.json` - Version 2.0.0
- `cli/python/veribits.py` - Added console mode (~200 lines)
- `cli/python/setup.py` - Version 2.0.0

### Documentation Added
- `CONSOLE_MODE_README.md` - Console mode guide
- `TERRAFORM_ANSIBLE_INTEGRATION.md` - IaC integration design
- `CLI_COMPREHENSIVE_STATUS.md` - Complete CLI status
- `DEPLOYMENT_INSTRUCTIONS.md` - This file

## Testing Checklist

After deployment, verify:

- [ ] Homepage loads: https://www.veribits.com
- [ ] Docs link appears in navigation
- [ ] Docs page loads: https://www.veribits.com/docs.php
- [ ] All navigation links work
- [ ] Table of contents navigation works
- [ ] Code examples are properly formatted
- [ ] Responsive design works on mobile
- [ ] All sections are present and readable

## Rollback Plan

If deployment fails:

```bash
# Revert to previous task definition
aws ecs update-service \
    --cluster veribits-cluster \
    --service veribits-api \
    --task-definition veribits-api:1 \
    --force-new-deployment \
    --region us-east-1
```

## Next Steps After Deployment

1. **Test the docs page** thoroughly
2. **Update CLI documentation** to reference new docs page
3. **Create Ansible modules** (1-2 days work)
4. **Create Terraform provider** (3-5 days work)
5. **Add missing API endpoints** to CLI (~15-20 commands)
6. **Publish CLI v2.0.0** to npm and PyPI

## Summary

✅ **Added:** Comprehensive 9-section documentation page
✅ **Updated:** Main navigation with "Docs" link
✅ **Included:** CLI v2.0.0 features (console mode)
✅ **Documented:** Licensing, Terraform, Ansible
✅ **Ready:** For deployment to production

**Estimated deployment time:** 5-10 minutes (with Docker running)

---

© After Dark Systems, LLC
VeriBits Documentation Deployment Guide
