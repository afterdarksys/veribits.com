# Stripe Migration - Run on Server

## Why Migration Needs to Run on Server

The RDS database is only accessible from within the VPC (security best practice). This means we need to run the migration from:
- An EC2 instance in the same VPC
- A bastion host
- Via AWS Systems Manager Session Manager

## Quick Start - Run Migration on Server

### Option 1: If you have SSH access to an EC2 instance

```bash
# SSH to your server
ssh ubuntu@your-server

# Navigate to project directory
cd /var/www/veribits  # or wherever the project is deployed

# Run the migration
./scripts/run-stripe-migration.sh
```

### Option 2: Using AWS Systems Manager

```bash
# From your local machine
aws ssm start-session --target i-YOUR-INSTANCE-ID

# Once connected to the instance
cd /var/www/veribits
./scripts/run-stripe-migration.sh
```

### Option 3: Manual Migration via AWS Console

1. Go to RDS Console
2. Select your VeriBits database
3. Click "Query Editor" (if enabled)
4. Copy and paste the contents of `db/migrations/023_stripe_integration.sql`
5. Click "Run"

---

## Files Ready for Deployment

All Stripe integration files have been created and are ready:

### Backend Files
```
✓ app/src/Services/StripeService.php
✓ app/src/Controllers/BillingController.php (updated)
✓ app/public/index.php (updated with routes)
✓ db/migrations/023_stripe_integration.sql
✓ composer.json
```

### Frontend Files
```
✓ app/public/assets/js/stripe-checkout.js
```

### Configuration Files
```
✓ app/config/.env.example (updated)
✓ app/config/.env.production (updated with test keys)
```

### Scripts
```
✓ scripts/run-stripe-migration.sh (standalone migration)
✓ scripts/run-migrations.sh (updated with migration 023)
```

### Documentation
```
✓ STRIPE_INTEGRATION_GUIDE.md
✓ STRIPE_DEPLOYMENT_CHECKLIST.md
✓ STRIPE_RUN_ON_SERVER.md (this file)
```

---

## What the Migration Does

The `023_stripe_integration.sql` migration will:

1. **Add Stripe columns to existing tables:**
   - `users.stripe_customer_id` - Links user to Stripe customer
   - `billing_accounts.stripe_customer_id` - Stripe customer ID
   - `billing_accounts.stripe_subscription_id` - Stripe subscription ID
   - `invoices.stripe_invoice_id` - Stripe invoice ID (if invoices table exists)

2. **Create new tables:**
   - `stripe_events` - Audit log of all Stripe webhooks
   - `stripe_checkout_sessions` - Track checkout sessions

3. **Create indexes for performance**

4. **Create helper function:**
   - `log_stripe_event()` - Helper to log webhook events

5. **Grant permissions to application user**

---

## After Running Migration

### 1. Verify Migration Success

```bash
# Connect to database
psql -h nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com \
     -U nitetext \
     -d veribits

# Check for new columns
\d users
\d billing_accounts

# Check for new tables
\dt stripe*

# Should see:
# - stripe_events
# - stripe_checkout_sessions
```

### 2. Install Stripe PHP Library

```bash
cd /var/www/veribits
composer install
```

This will install `stripe/stripe-php` v13.x

### 3. Create Stripe Products

Go to https://dashboard.stripe.com/test/products and create:

**Pro Plan:**
- Name: VeriBits Pro
- Price: $29.00/month
- Copy Price ID → Update `.env`: `STRIPE_PRICE_PRO=price_xxxxx`

**Enterprise Plan:**
- Name: VeriBits Enterprise
- Price: $299.00/month
- Copy Price ID → Update `.env`: `STRIPE_PRICE_ENTERPRISE=price_xxxxx`

### 4. Configure Stripe Webhook

1. Go to https://dashboard.stripe.com/test/webhooks
2. Add endpoint: `https://api.veribits.com/api/v1/billing/webhook/stripe`
3. Select events:
   - checkout.session.completed
   - customer.subscription.created
   - customer.subscription.updated
   - customer.subscription.deleted
   - invoice.payment_succeeded
   - invoice.payment_failed
4. Copy webhook secret → Update `.env`: `STRIPE_WEBHOOK_SECRET=whsec_xxxxx`

### 5. Deploy Frontend Changes

Upload the updated files to your web server:

```bash
# From local machine
scp app/public/assets/js/stripe-checkout.js user@server:/var/www/veribits/app/public/assets/js/
scp app/src/Services/StripeService.php user@server:/var/www/veribits/app/src/Services/
scp app/src/Controllers/BillingController.php user@server:/var/www/veribits/app/src/Controllers/
scp app/public/index.php user@server:/var/www/veribits/app/public/
```

### 6. Update Frontend Pages

Add to your pricing page:

```html
<script src="/assets/js/stripe-checkout.js"></script>

<button onclick="createStripeCheckout('pro')">Subscribe to Pro - $29/mo</button>
<button onclick="createStripeCheckout('enterprise')">Subscribe to Enterprise - $299/mo</button>
```

### 7. Test!

Use test card: `4242 4242 4242 4242`

---

## Troubleshooting

### Can't connect to database

**Issue:** `Cannot connect to database`

**Solution:** The RDS instance is in a VPC and not publicly accessible. You must run the migration from:
- An EC2 instance in the same VPC
- Via AWS Systems Manager Session Manager
- Via a VPN connection to the VPC

### Migration already ran

If you see `Stripe migration appears to have already run`, the migration has been applied. You can safely skip it.

### Permission denied on functions

If you get permission errors, ensure the migration is run as a database superuser or a user with CREATE privilege.

---

## Manual Migration (If Script Fails)

If the automated script doesn't work, you can run the SQL directly:

```bash
# Connect to database
psql -h nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com \
     -U nitetext \
     -d veribits

# Run migration
\i /var/www/veribits/db/migrations/023_stripe_integration.sql

# Or paste the SQL directly
# (copy contents of 023_stripe_integration.sql and paste)
```

---

## Environment Variables Summary

Make sure these are set in `.env.production`:

```bash
# Stripe API Keys (test mode for now)
STRIPE_PUBLISHABLE_KEY=pk_test_your_publishable_key
STRIPE_SECRET_KEY=sk_test_your_secret_key
STRIPE_WEBHOOK_SECRET=whsec_... # Get from Stripe Dashboard after creating webhook

# Stripe Product Price IDs (create in Stripe Dashboard)
STRIPE_PRICE_PRO=price_... # Pro plan $29/mo
STRIPE_PRICE_ENTERPRISE=price_... # Enterprise $299/mo
```

---

## Next Steps After Migration

1. ✅ Migration runs successfully
2. ✅ Composer install completes
3. ✅ Stripe products created
4. ✅ Environment variables updated
5. ✅ Webhook configured
6. ✅ Frontend files deployed
7. 🧪 Test subscription flow
8. 🚀 Go live!

---

## Support

For issues:
1. Check `STRIPE_INTEGRATION_GUIDE.md` for detailed docs
2. Check `STRIPE_DEPLOYMENT_CHECKLIST.md` for step-by-step guide
3. Review application logs for errors
4. Check Stripe Dashboard → Webhooks for delivery status

**Migration file:** `db/migrations/023_stripe_integration.sql`
**Migration script:** `scripts/run-stripe-migration.sh`
