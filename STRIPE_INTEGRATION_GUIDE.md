# Stripe Integration Guide for VeriBits

Complete guide for implementing Stripe payment processing in VeriBits.

## Overview

This integration provides:
- ✅ Subscription checkout for Pro and Enterprise plans
- ✅ Customer portal for self-service subscription management
- ✅ Webhook handling for automated subscription updates
- ✅ Database tracking of all Stripe events
- ✅ Secure payment processing (PCI compliant - no card data touches our servers)

## Table of Contents

1. [Installation](#installation)
2. [Configuration](#configuration)
3. [Database Setup](#database-setup)
4. [API Endpoints](#api-endpoints)
5. [Frontend Integration](#frontend-integration)
6. [Webhook Configuration](#webhook-configuration)
7. [Testing](#testing)
8. [Going Live](#going-live)

---

## Installation

### 1. Install Stripe PHP Library

```bash
cd /Users/ryan/development/veribits.com
composer install
```

This will install:
- `stripe/stripe-php: ^13.0`

### 2. Verify Installation

```bash
composer show stripe/stripe-php
```

---

## Configuration

### 1. Environment Variables

The following environment variables are already configured:

**Test Mode (`.env.example` and `.env.production`):**
```bash
# Stripe Test Keys
STRIPE_PUBLISHABLE_KEY=pk_test_your_publishable_key
STRIPE_SECRET_KEY=sk_test_your_secret_key
STRIPE_WEBHOOK_SECRET=whsec_... # Configure after setting up webhook

# Stripe Price IDs (from Stripe Dashboard)
STRIPE_PRICE_PRO=price_1SV4LaRvgCpYKD626y7CemDx
STRIPE_PRICE_ENTERPRISE=price_TBD
```

### 2. Create Stripe Products

1. Go to [Stripe Dashboard → Products](https://dashboard.stripe.com/test/products)
2. Create two products:

**Pro Plan:**
- Name: VeriBits Pro
- Price: $29.00 USD / month (recurring)
- Copy the Price ID and update `STRIPE_PRICE_PRO`

**Enterprise Plan:**
- Name: VeriBits Enterprise
- Price: $299.00 USD / month (recurring)
- Copy the Price ID and update `STRIPE_PRICE_ENTERPRISE`

---

## Database Setup

### Run Migration

```bash
# Using psql
psql -h <DB_HOST> -U <DB_USER> -d veribits_production -f db/migrations/023_stripe_integration.sql

# Or via migration script if you have one
./scripts/run-migrations.sh
```

### What Gets Created

The migration creates:
- `stripe_customer_id` column in `users` table
- `stripe_subscription_id` column in `billing_accounts` table
- `stripe_events` table for webhook audit log
- `stripe_checkout_sessions` table for tracking checkouts
- Indexes for performance
- Helper function `log_stripe_event()`

---

## API Endpoints

### Public Endpoints

#### Get Stripe Publishable Key
```http
GET /api/v1/billing/stripe/publishable-key
```

**Response:**
```json
{
  "success": true,
  "data": {
    "publishable_key": "pk_test_..."
  }
}
```

### Protected Endpoints (Require Authentication)

#### Create Checkout Session
```http
POST /api/v1/billing/stripe/create-checkout-session
Authorization: Bearer <token>
Content-Type: application/json

{
  "plan": "pro",
  "success_url": "https://veribits.com/dashboard?payment=success",
  "cancel_url": "https://veribits.com/pricing?payment=cancelled"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "session_id": "cs_test_...",
    "checkout_url": "https://checkout.stripe.com/c/pay/cs_test_..."
  }
}
```

#### Create Customer Portal Session
```http
POST /api/v1/billing/stripe/create-portal-session
Authorization: Bearer <token>
Content-Type: application/json

{
  "return_url": "https://veribits.com/dashboard"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "portal_url": "https://billing.stripe.com/p/session/..."
  }
}
```

#### Cancel Subscription
```http
POST /api/v1/billing/stripe/cancel-subscription
Authorization: Bearer <token>
Content-Type: application/json

{
  "immediate": false
}
```

**Response:**
```json
{
  "success": true,
  "message": "Subscription cancelled successfully"
}
```

### Webhook Endpoint

```http
POST /api/v1/billing/webhook/stripe
Stripe-Signature: <signature>
```

---

## Frontend Integration

### 1. Include Stripe Checkout Script

Add to your HTML pages:

```html
<script src="/assets/js/stripe-checkout.js"></script>
```

### 2. Add Subscribe Buttons

**Pro Plan:**
```html
<button onclick="createStripeCheckout('pro')" class="btn btn-primary">
  Subscribe to Pro - $29/mo
</button>
```

**Enterprise Plan:**
```html
<button onclick="createStripeCheckout('enterprise')" class="btn btn-primary">
  Subscribe to Enterprise - $299/mo
</button>
```

### 3. Add Subscription Management Button

For users with active subscriptions:

```html
<button onclick="openStripePortal()" class="btn btn-secondary">
  Manage Subscription
</button>
```

### 4. Add Cancel Button

```html
<button onclick="cancelStripeSubscription(false)" class="btn btn-danger">
  Cancel Subscription
</button>
```

### 5. Handle Payment Success

On your success page (e.g., `dashboard.php`):

```html
<div id="payment-success-message" style="display: none;"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    handlePaymentSuccess();
});
</script>
```

### 6. Handle Payment Cancellation

On your pricing/cancel page:

```html
<div id="payment-cancel-message" style="display: none;"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    handlePaymentCancelled();
});
</script>
```

---

## Webhook Configuration

### 1. Create Webhook Endpoint in Stripe

1. Go to [Stripe Dashboard → Webhooks](https://dashboard.stripe.com/test/webhooks)
2. Click "+ Add endpoint"
3. Endpoint URL: `https://api.veribits.com/api/v1/billing/webhook/stripe`
4. Select events to listen to:
   - `checkout.session.completed`
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `invoice.payment_succeeded`
   - `invoice.payment_failed`
5. Click "Add endpoint"
6. Copy the "Signing secret" (starts with `whsec_`)

### 2. Update Environment Variable

Add the webhook secret to your `.env.production`:

```bash
STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxx
```

### 3. Test Webhook

Use Stripe CLI to test locally:

```bash
# Install Stripe CLI
brew install stripe/stripe-cli/stripe

# Login
stripe login

# Forward webhooks to local
stripe listen --forward-to http://localhost:8080/api/v1/billing/webhook/stripe

# Trigger test events
stripe trigger checkout.session.completed
```

---

## Testing

### Test Cards

Use these test cards in Stripe Checkout:

**Success:**
- Card: `4242 4242 4242 4242`
- Expiry: Any future date
- CVC: Any 3 digits

**Decline:**
- Card: `4000 0000 0000 0002`

**Requires Authentication:**
- Card: `4000 0027 6000 3184`

### Testing Flow

1. **Sign Up / Login** to VeriBits
2. **Navigate to Pricing Page**
3. **Click "Subscribe to Pro"**
4. **Complete Stripe Checkout** with test card
5. **Verify Subscription** in database:
   ```sql
   SELECT * FROM billing_accounts WHERE user_id = '<user_id>';
   -- Should show plan='pro' and stripe_subscription_id
   ```
6. **Check Webhook Events:**
   ```sql
   SELECT * FROM stripe_events ORDER BY created_at DESC LIMIT 10;
   ```
7. **Test Customer Portal:**
   - Click "Manage Subscription" button
   - Should redirect to Stripe Customer Portal
   - Try updating payment method
   - Try canceling subscription
8. **Verify Cancellation:**
   ```sql
   SELECT plan, stripe_subscription_id FROM billing_accounts WHERE user_id = '<user_id>';
   -- Should show plan='free' after cancellation webhook
   ```

---

## Going Live

### 1. Get Live API Keys

1. Complete [Stripe account activation](https://dashboard.stripe.com/account/onboarding)
2. Get your live keys from [API Keys page](https://dashboard.stripe.com/apikeys)

### 2. Update Production Environment

Replace test keys in `.env.production`:

```bash
STRIPE_PUBLISHABLE_KEY=pk_live_xxxxxxxxxxxxx
STRIPE_SECRET_KEY=sk_live_xxxxxxxxxxxxx
```

### 3. Create Live Products

Repeat product creation in **live mode**:
1. Switch to "Live mode" in Stripe Dashboard (toggle in top right)
2. Create Pro and Enterprise products
3. Update `STRIPE_PRICE_PRO` and `STRIPE_PRICE_ENTERPRISE` with live price IDs

### 4. Create Live Webhook

1. In live mode, create webhook endpoint
2. URL: `https://api.veribits.com/api/v1/billing/webhook/stripe`
3. Select same events as test mode
4. Update `STRIPE_WEBHOOK_SECRET` with live webhook secret

### 5. Test with Real Card

1. Use a real card (you'll be charged)
2. Complete full subscription flow
3. Immediately cancel to get refund
4. Verify all webhooks processed correctly

---

## Architecture

### Payment Flow

```
User clicks "Subscribe"
    ↓
Frontend calls /api/v1/billing/stripe/create-checkout-session
    ↓
Backend creates Stripe Checkout Session
    ↓
User redirected to Stripe Checkout page
    ↓
User enters payment info (on Stripe's secure page)
    ↓
Payment processed by Stripe
    ↓
Stripe sends webhook to /api/v1/billing/webhook/stripe
    ↓
Backend updates billing_accounts table
    ↓
User redirected to success URL
    ↓
Dashboard shows active subscription
```

### Database Schema

```sql
-- Users table (modified)
users (
  id UUID PRIMARY KEY,
  email VARCHAR,
  stripe_customer_id VARCHAR UNIQUE,  -- NEW
  ...
)

-- Billing accounts (modified)
billing_accounts (
  id BIGSERIAL PRIMARY KEY,
  user_id UUID,
  plan VARCHAR,  -- 'free', 'pro', 'enterprise'
  stripe_customer_id VARCHAR,  -- NEW
  stripe_subscription_id VARCHAR UNIQUE,  -- NEW
  ...
)

-- Stripe events (new)
stripe_events (
  id BIGSERIAL PRIMARY KEY,
  event_id VARCHAR UNIQUE,
  event_type VARCHAR,
  customer_id VARCHAR,
  subscription_id VARCHAR,
  payload JSONB,
  processed_at TIMESTAMP,
  ...
)

-- Checkout sessions (new)
stripe_checkout_sessions (
  id BIGSERIAL PRIMARY KEY,
  user_id UUID,
  session_id VARCHAR UNIQUE,
  plan_key VARCHAR,
  status VARCHAR,
  created_at TIMESTAMP,
  ...
)
```

---

## Security Considerations

1. **Webhook Signature Verification**: All webhooks verify Stripe signature
2. **No Card Data**: Card information never touches our servers (handled by Stripe)
3. **HTTPS Only**: All payment flows require HTTPS
4. **Authentication**: Checkout endpoints require valid JWT token
5. **Idempotency**: Duplicate webhook events are safely ignored
6. **Audit Logging**: All Stripe events logged in `stripe_events` table

---

## Monitoring

### Check Subscription Health

```sql
-- Active subscriptions
SELECT
  u.email,
  ba.plan,
  ba.stripe_subscription_id,
  ba.created_at
FROM billing_accounts ba
JOIN users u ON ba.user_id = u.id
WHERE ba.plan != 'free'
AND ba.stripe_subscription_id IS NOT NULL;

-- Recent webhook events
SELECT
  event_type,
  customer_id,
  processed_at,
  payload->>'status' as status
FROM stripe_events
ORDER BY created_at DESC
LIMIT 20;

-- Failed payments
SELECT * FROM stripe_events
WHERE event_type = 'invoice.payment_failed'
ORDER BY created_at DESC;
```

### Stripe Dashboard

Monitor in real-time:
- [Payments](https://dashboard.stripe.com/payments)
- [Subscriptions](https://dashboard.stripe.com/subscriptions)
- [Customers](https://dashboard.stripe.com/customers)
- [Webhooks](https://dashboard.stripe.com/webhooks) - Check for failures

---

## Troubleshooting

### Webhook Not Receiving Events

1. Check webhook URL is correct and publicly accessible
2. Verify `STRIPE_WEBHOOK_SECRET` matches Stripe Dashboard
3. Check Stripe Dashboard → Webhooks for failed deliveries
4. Check server logs for errors in webhook handler

### Subscription Not Activating

1. Check `stripe_events` table for received webhooks
2. Verify `checkout.session.completed` event processed
3. Check `billing_accounts` for updated `stripe_subscription_id`
4. Review application logs for errors

### Checkout Session Creation Fails

1. Verify `STRIPE_SECRET_KEY` is correct
2. Check price IDs (`STRIPE_PRICE_PRO`, `STRIPE_PRICE_ENTERPRISE`) are valid
3. Ensure user is authenticated (has valid JWT token)
4. Check application logs for detailed error

---

## Support

For issues or questions:
1. Check Stripe Dashboard → Logs for API errors
2. Review `stripe_events` table for webhook history
3. Check application logs
4. Contact Stripe support: https://support.stripe.com/

---

## Summary

✅ **Files Created:**
- `app/src/Services/StripeService.php` - Core Stripe integration
- `app/public/assets/js/stripe-checkout.js` - Frontend integration
- `db/migrations/023_stripe_integration.sql` - Database schema
- `composer.json` - PHP dependencies

✅ **Files Modified:**
- `app/src/Controllers/BillingController.php` - Added Stripe endpoints
- `app/public/index.php` - Added Stripe routes
- `app/config/.env.example` - Added Stripe configuration
- `app/config/.env.production` - Added Stripe test keys

✅ **Ready to Deploy:**
1. Run `composer install`
2. Run migration `023_stripe_integration.sql`
3. Configure webhook in Stripe Dashboard
4. Add webhook secret to `.env.production`
5. Test with test cards
6. Deploy to production

🎉 **You're all set!**
