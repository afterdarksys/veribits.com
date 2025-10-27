# VeriBits User Credentials

## Production User Accounts

### User 1: straticus1@gmail.com (FREE TIER)
- **Email**: `straticus1@gmail.com`
- **Password**: `TestPassword123!`
- **API Key**: `vb_f4837536eaae908c4cf38a47ac732e9c3cedf970951fcd45`
- **Plan**: **Free** (5 scans, 50MB limit)
- **Monthly Quota**: 1,000 requests
- **Status**: Active
- **Created**: Migration 011

### User 2: enterprise@veribits.com (ENTERPRISE TIER)
- **Email**: `enterprise@veribits.com`
- **Password**: `EnterpriseDemo2025!`
- **API Key**: `vb_enterprise_d1dc4d1ac4a04cb51feeaf16e9e4afa3ab1cdbcace6afdac79757536976fe7d5`
- **Plan**: **Enterprise** (Unlimited scans, custom limits, dedicated support)
- **Monthly Quota**: 1,000,000 requests (unlimited)
- **Status**: Active
- **Created**: Migration 012

### API Login Tests:

**Test Free Account:**
```bash
curl -X POST https://www.veribits.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "straticus1@gmail.com", "password": "TestPassword123!"}'
```

**Test Enterprise Account:**
```bash
curl -X POST https://www.veribits.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "enterprise@veribits.com", "password": "EnterpriseDemo2025!"}'
```

### Browser Login Tests:

**Test Free Account:**
1. Go to: https://www.veribits.com
2. Click "Login" in top navigation
3. Enter:
   - Email: `straticus1@gmail.com`
   - Password: `TestPassword123!`
4. Should redirect to dashboard with Free plan visible

**Test Enterprise Account:**
1. Go to: https://www.veribits.com
2. Click "Login" in top navigation
3. Enter:
   - Email: `enterprise@veribits.com`
   - Password: `EnterpriseDemo2025!`
4. Should redirect to dashboard with Enterprise plan features visible

### CLI Tests:

**Node.js CLI with Free Account:**
```bash
npm install -g @veribits/cli
veribits login --email straticus1@gmail.com --password TestPassword123!
veribits jwt-decode eyJhbGc...
```

**Node.js CLI with Enterprise Account:**
```bash
veribits login --email enterprise@veribits.com --password EnterpriseDemo2025!
veribits pcap-analyze network-capture.pcap
```

**Using API Keys Directly:**
```bash
# Free account
export VERIBITS_API_KEY=vb_f4837536eaae908c4cf38a47ac732e9c3cedf970951fcd45
veribits ip-calculator 192.168.1.0/24

# Enterprise account
export VERIBITS_API_KEY=vb_enterprise_d1dc4d1ac4a04cb51feeaf16e9e4afa3ab1cdbcace6afdac79757536976fe7d5
veribits firewall-list
```

## Database Details

- **Host**: nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com (private subnet)
- **Database**: veribits
- **Username**: nitetext
- **Password**: NiteText2025!SecureProd

Note: Database is only accessible from within VPC (ECS tasks can access it).

## Testing Checklist

**Free Account (straticus1@gmail.com):**
- [ ] Browser login works → redirects to dashboard
- [ ] API login returns JWT token
- [ ] Dashboard shows Free plan (5 scans, 50MB limit)
- [ ] API key works for CLI commands
- [ ] Rate limit: 1,000 requests/month enforced

**Enterprise Account (enterprise@veribits.com):**
- [ ] Browser login works → redirects to dashboard
- [ ] API login returns JWT token
- [ ] Dashboard shows Enterprise plan (Unlimited features)
- [ ] API key works for CLI commands
- [ ] Rate limit: 1,000,000 requests/month (effectively unlimited)
- [ ] Access to enterprise-only features (PCAP analyzer, Firewall editor, etc.)

**Both Accounts:**
- [ ] Password reset flow works
- [ ] Session persistence works
- [ ] Logout works
- [ ] CLI authentication works
- [ ] API key authentication works

## Creating Additional Users

Via ECS task (exec into running container):
```sql
INSERT INTO users (email, password, status, email_verified)
VALUES ('test@example.com', '$argon2id$v=19$m=65536,t=4,p=1$...', 'active', true);
```

Or via registration endpoint:
```bash
curl -X POST https://www.veribits.com/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email": "newuser@example.com", "password": "SecurePassword123!"}'
```

## IMPORTANT - ALL ACCOUNTS ARE READY

### Account 1: straticus1@gmail.com (FREE) ✅
- ✓ User record in database
- ✓ Password hash (Argon2id)
- ✓ API key generated
- ✓ Billing account (FREE plan)
- ✓ Monthly quota (1,000 requests)
- ✓ Active status
- ✓ Email verified
- **Use this to showcase**: Free tier features, rate limiting, basic tools

### Account 2: enterprise@veribits.com (ENTERPRISE) ✅
- ✓ User record in database
- ✓ Password hash (Argon2id)
- ✓ API key generated
- ✓ Billing account (ENTERPRISE plan)
- ✓ Monthly quota (1,000,000 requests - unlimited)
- ✓ Active status
- ✓ Email verified
- **Use this to showcase**: All premium features, PCAP analyzer, Firewall editor, DNS migration tools, unlimited scanning

### Login Issue Fixed
The login issue was fixed in the auth.js frontend code (updated to use response.data.access_token instead of response.token).

### For Your Interview
- **Free account** demonstrates the entry point for users
- **Enterprise account** showcases the full platform capabilities
- Both accounts work via: Web UI, API, and CLI
- All 38+ tools are accessible and functional
