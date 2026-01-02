# Stripe Integration - Deployment Checklist

## ✅ Completed

### Backend Integration
- [x] Created `StripeService.php` - Core Stripe payment service
- [x] Updated `BillingController.php` - Added Stripe endpoints
- [x] Added routes to `index.php` - 4 new Stripe endpoints
- [x] Created migration `023_stripe_integration.sql`
- [x] Updated `.env.example` with Stripe configuration
- [x] Updated `.env.production` with test keys
- [x] Created `composer.json` for Stripe PHP library

### Frontend Integration
- [x] Created `stripe-checkout.js` - Complete frontend integration
  - `createStripeCheckout()` - Redirect to Stripe Checkout
  - `openStripePortal()` - Manage subscription
  - `cancelStripeSubscription()` - Cancel subscription
  - `handlePaymentSuccess()` - Success page handler
  - `handlePaymentCancelled()` - Cancellation handler

### Documentation
- [x] Created comprehensive integration guide
- [x] Created deployment checklist (this file)

---

## 🚀 Deployment Steps

### 1. Install Dependencies

```bash
cd /Users/ryan/development/veribits.com
composer install
```

Expected output:
```
Installing dependencies from lock file
Package operations: 1 install
  - Installing stripe/stripe-php (v13.x)
```

### 2. Run Database Migration

```bash
# Connect to your database
psql -h <DB_HOST> -U <DB_USER> -d veribits_production

# Run migration
\i db/migrations/023_stripe_integration.sql

# Verify
SELECT version, description FROM schema_migrations WHERE version = 23;
```

Expected result:
```
 version |            description
---------+-----------------------------------
      23 | Add Stripe payment integration support
```

### 3. Create Stripe Products (Test Mode)

1. Go to https://dashboard.stripe.com/test/products
2. Click "+ Add product"

**Pro Plan:**
- Name: `VeriBits Pro`
- Description: `Advanced verification features with webhooks and priority support`
- Pricing: `$29.00 USD / month` (recurring)
- Click "Save product"
- Copy the **Price ID** (starts with `price_`)
- Update `.env.production`: `STRIPE_PRICE_PRO=price_xxxxx`

**Enterprise Plan:**
- Name: `VeriBits Enterprise`
- Description: `Unlimited verification with white-label and SLA`
- Pricing: `$299.00 USD / month` (recurring)
- Click "Save product"
- Copy the **Price ID**
- Update `.env.production`: `STRIPE_PRICE_ENTERPRISE=price_xxxxx`

### 4. Configure Stripe Webhook

1. Go to https://dashboard.stripe.com/test/webhooks
2. Click "+ Add endpoint"
3. Endpoint URL: `https://api.veribits.com/api/v1/billing/webhook/stripe`
4. Description: `VeriBits Subscription Webhooks`
5. Select events:
   - [x] `checkout.session.completed`
   - [x] `customer.subscription.created`
   - [x] `customer.subscription.updated`
   - [x] `customer.subscription.deleted`
   - [x] `invoice.payment_succeeded`
   - [x] `invoice.payment_failed`
6. Click "Add endpoint"
7. Click "Reveal" next to "Signing secret"
8. Copy the secret (starts with `whsec_`)
9. Update `.env.production`: `STRIPE_WEBHOOK_SECRET=whsec_xxxxx`

### 5. Update Frontend Pages

Add Stripe checkout to your pages:

**pricing.php** or **pricing.html**:
```html
<!-- Add before closing </body> tag -->
<script src="/assets/js/stripe-checkout.js"></script>

<!-- Update Pro plan button -->
<button onclick="createStripeCheckout('pro')" class="btn btn-primary">
  Subscribe to Pro - $29/mo
</button>

<!-- Update Enterprise plan button -->
<button onclick="createStripeCheckout('enterprise')" class="btn btn-primary">
  Subscribe to Enterprise - $299/mo
</button>
```

**dashboard.php** or **dashboard.html**:
```html
<!-- Add before closing </body> tag -->
<script src="/assets/js/stripe-checkout.js"></script>

<!-- Add success handler -->
<div id="payment-success-message" style="display: none;"></div>
<script>
document.addEventListener('DOMContentLoaded', handlePaymentSuccess);
</script>

<!-- For subscribed users, add manage button -->
<button onclick="openStripePortal()" class="btn btn-secondary">
  Manage Subscription
</button>
```

### 6. Deploy to Production

```bash
# Deploy backend files
scp -r app/src/Services/StripeService.php user@server:/var/www/veribits/app/src/Services/
scp app/src/Controllers/BillingController.php user@server:/var/www/veribits/app/src/Controllers/
scp app/public/index.php user@server:/var/www/veribits/app/public/
scp composer.json user@server:/var/www/veribits/

# Deploy frontend
scp app/public/assets/js/stripe-checkout.js user@server:/var/www/veribits/app/public/assets/js/

# Deploy migration
scp db/migrations/023_stripe_integration.sql user@server:/var/www/veribits/db/migrations/

# SSH to server
ssh user@server

# Install dependencies
cd /var/www/veribits
composer install

# Run migration
psql -h $DB_HOST -U $DB_USER -d $DB_NAME -f db/migrations/023_stripe_integration.sql

# Restart PHP-FPM (if needed)
sudo systemctl restart php-fpm
```

---

## 🧪 Testing

### Test Subscription Flow

1. **Navigate to pricing page**: https://veribits.com/pricing
2. **Click "Subscribe to Pro"**
3. **Enter test card**:
   - Card: `4242 4242 4242 4242`
   - Expiry: `12/34`
   - CVC: `123`
   - ZIP: `12345`
4. **Click "Subscribe"**
5. **Verify redirect** to dashboard with success message
6. **Check database**:
   ```sql
   SELECT u.email, ba.plan, ba.stripe_subscription_id
   FROM billing_accounts ba
   JOIN users u ON ba.user_id = u.id
   WHERE u.email = 'test@example.com';
   ```
   Should show `plan = 'pro'` and `stripe_subscription_id = 'sub_...'`

### Test Customer Portal

1. **From dashboard, click "Manage Subscription"**
2. **Verify redirect** to Stripe Customer Portal
3. **Try updating payment method** (use another test card)
4. **Try canceling subscription**
5. **Verify webhook** received:
   ```sql
   SELECT event_type, processed_at
   FROM stripe_events
   ORDER BY created_at DESC
   LIMIT 5;
   ```

### Test Webhook Delivery

1. Go to https://dashboard.stripe.com/test/webhooks
2. Click on your webhook endpoint
3. Click "Send test webhook"
4. Select `checkout.session.completed`
5. Click "Send test webhook"
6. Verify it appears in `stripe_events` table

---

## 📊 Monitoring

### Check Subscription Status

```sql
-- Active Pro subscriptions
SELECT COUNT(*) FROM billing_accounts WHERE plan = 'pro';

-- Active Enterprise subscriptions
SELECT COUNT(*) FROM billing_accounts WHERE plan = 'enterprise';

-- Total revenue (approximate)
SELECT
  SUM(CASE WHEN plan = 'pro' THEN 29 WHEN plan = 'enterprise' THEN 299 ELSE 0 END) as mrr
FROM billing_accounts
WHERE plan != 'free';
```

### Monitor Webhook Health

```sql
-- Webhook events today
SELECT event_type, COUNT(*)
FROM stripe_events
WHERE created_at > CURRENT_DATE
GROUP BY event_type;

-- Failed payments
SELECT customer_id, created_at
FROM stripe_events
WHERE event_type = 'invoice.payment_failed'
AND created_at > CURRENT_DATE - INTERVAL '7 days';
```

---

## 🔥 Troubleshooting

### Issue: Checkout button doesn't work

**Check:**
1. Browser console for JavaScript errors
2. Verify `stripe-checkout.js` is loaded
3. Check user is logged in (has auth token)
4. Verify API endpoint returns publishable key

**Fix:**
```javascript
// Open browser console
fetch('/api/v1/billing/stripe/publishable-key')
  .then(r => r.json())
  .then(console.log);
// Should return: {success: true, data: {publishable_key: "pk_test_..."}}
```

### Issue: Webhook not receiving events

**Check:**
1. Stripe Dashboard → Webhooks → Check for failed deliveries
2. Verify webhook URL is publicly accessible
3. Test with `curl`:
   ```bash
   curl -X POST https://api.veribits.com/api/v1/billing/webhook/stripe \
     -H "Content-Type: application/json" \
     -d '{"test": true}'
   ```
4. Check server logs for errors

**Fix:**
- Ensure server allows POST requests
- Verify no firewall blocking Stripe IPs
- Check `STRIPE_WEBHOOK_SECRET` is correct

### Issue: Subscription not activating after payment

**Check:**
```sql
-- Check if checkout session was created
SELECT * FROM stripe_checkout_sessions
WHERE user_id = '<user_id>'
ORDER BY created_at DESC
LIMIT 1;

-- Check if webhook was received
SELECT * FROM stripe_events
WHERE event_type = 'checkout.session.completed'
ORDER BY created_at DESC
LIMIT 5;

-- Check billing account
SELECT * FROM billing_accounts
WHERE user_id = '<user_id>';
```

**Fix:**
- Webhook probably didn't process
- Check application logs
- Manually trigger webhook from Stripe Dashboard

---

## 🎉 Success Criteria

✅ User can subscribe to Pro plan via Stripe Checkout
✅ User can subscribe to Enterprise plan via Stripe Checkout
✅ Subscription activates automatically after payment
✅ User can manage subscription via Customer Portal
✅ User can cancel subscription
✅ Webhooks process successfully
✅ All events logged in `stripe_events` table
✅ Billing account updates in real-time

---

## 📞 Next Steps

1. **Test thoroughly** with test cards
2. **Set up live mode** when ready to accept real payments
3. **Create live webhook** endpoint
4. **Switch to live API keys**
5. **Monitor for first week** to catch any issues
6. **Set up alerts** for failed payments

---

## 📚 Resources

- [Stripe Integration Guide](./STRIPE_INTEGRATION_GUIDE.md) - Full documentation
- [Stripe Dashboard](https://dashboard.stripe.com)
- [Stripe API Docs](https://stripe.com/docs/api)
- [Stripe Webhooks](https://stripe.com/docs/webhooks)
- [Test Cards](https://stripe.com/docs/testing)

---

**Status:** ✅ Ready to Deploy
**Date:** 2025-01-19
**Test Mode:** Active
**Live Mode:** Pending
