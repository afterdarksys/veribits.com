<?php
namespace VeriBits\Services;

use VeriBits\Utils\Database;
use VeriBits\Utils\Logger;
use VeriBits\Utils\Config;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\Subscription;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class StripeService {
    private const PLANS = [
        'free' => [
            'name' => 'Free',
            'price_id' => null,
            'price' => 0,
            'quota' => 1000,
            'features' => ['Basic verification', 'Community support']
        ],
        'pro' => [
            'name' => 'Pro',
            'price_id' => null, // Will be set from env: STRIPE_PRICE_PRO
            'price' => 2900, // $29.00 in cents
            'quota' => 10000,
            'features' => ['Advanced verification', 'Webhooks', 'Priority support', 'Custom badges']
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'price_id' => null, // Will be set from env: STRIPE_PRICE_ENTERPRISE
            'price' => 29900, // $299.00 in cents
            'quota' => 100000,
            'features' => ['Unlimited verification', 'White-label', 'SLA', 'Custom integration']
        ]
    ];

    public function __construct() {
        // Initialize Stripe with secret key from SecretManager (with environment fallback)
        $secretManager = SecretManager::getInstance();
        $secretKey = $secretManager->getSecret('STRIPE_SECRET_KEY');
        if ($secretKey) {
            Stripe::setApiKey($secretKey);
            Logger::info('Stripe initialized', [
                'source' => $secretManager->isSecretServerAvailable() ? 'SecretServer' : 'Environment'
            ]);
        } else {
            Logger::warning('STRIPE_SECRET_KEY not configured');
        }
    }

    /**
     * Get Stripe publishable key for frontend
     */
    public function getPublishableKey(): string {
        return Config::get('STRIPE_PUBLISHABLE_KEY') ?? '';
    }

    /**
     * Create a checkout session for subscription
     *
     * @param string $userId User ID
     * @param string $planKey Plan key (pro, enterprise)
     * @param string $successUrl Success redirect URL
     * @param string $cancelUrl Cancel redirect URL
     * @return array ['success' => bool, 'session_id' => string, 'checkout_url' => string, 'error' => string]
     */
    public function createCheckoutSession(
        string $userId,
        string $planKey,
        string $successUrl,
        string $cancelUrl
    ): array {
        try {
            // Validate plan
            if (!isset(self::PLANS[$planKey]) || $planKey === 'free') {
                return ['success' => false, 'error' => 'Invalid plan'];
            }

            $plan = self::PLANS[$planKey];

            // Get price ID from environment or use default
            $priceId = Config::get('STRIPE_PRICE_' . strtoupper($planKey));
            if (!$priceId) {
                return ['success' => false, 'error' => 'Plan not configured in Stripe'];
            }

            // Get or create Stripe customer
            $customerId = $this->getOrCreateCustomer($userId);
            if (!$customerId) {
                return ['success' => false, 'error' => 'Failed to create customer'];
            }

            // Create checkout session
            $session = Session::create([
                'customer' => $customerId,
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => $priceId,
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $cancelUrl,
                'client_reference_id' => $userId,
                'metadata' => [
                    'user_id' => $userId,
                    'plan_key' => $planKey
                ]
            ]);

            Logger::info('Stripe checkout session created', [
                'user_id' => $userId,
                'plan' => $planKey,
                'session_id' => $session->id
            ]);

            return [
                'success' => true,
                'session_id' => $session->id,
                'checkout_url' => $session->url
            ];

        } catch (\Exception $e) {
            Logger::error('Failed to create checkout session', [
                'user_id' => $userId,
                'plan' => $planKey,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create customer portal session for subscription management
     */
    public function createPortalSession(string $userId, string $returnUrl): array {
        try {
            $customerId = $this->getCustomerId($userId);
            if (!$customerId) {
                return ['success' => false, 'error' => 'No Stripe customer found'];
            }

            $session = \Stripe\BillingPortal\Session::create([
                'customer' => $customerId,
                'return_url' => $returnUrl,
            ]);

            return [
                'success' => true,
                'portal_url' => $session->url
            ];

        } catch (\Exception $e) {
            Logger::error('Failed to create portal session', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Handle Stripe webhook events
     */
    public function handleWebhook(string $payload, string $signature): array {
        try {
            $webhookSecret = Config::get('STRIPE_WEBHOOK_SECRET');
            if (!$webhookSecret) {
                throw new \RuntimeException('Webhook secret not configured');
            }

            // Verify webhook signature
            $event = Webhook::constructEvent($payload, $signature, $webhookSecret);

            Logger::info('Stripe webhook received', [
                'event_type' => $event->type,
                'event_id' => $event->id
            ]);

            // Handle different event types
            switch ($event->type) {
                case 'checkout.session.completed':
                    $this->handleCheckoutCompleted($event->data->object);
                    break;

                case 'customer.subscription.created':
                    $this->handleSubscriptionCreated($event->data->object);
                    break;

                case 'customer.subscription.updated':
                    $this->handleSubscriptionUpdated($event->data->object);
                    break;

                case 'customer.subscription.deleted':
                    $this->handleSubscriptionDeleted($event->data->object);
                    break;

                case 'invoice.payment_succeeded':
                    $this->handlePaymentSucceeded($event->data->object);
                    break;

                case 'invoice.payment_failed':
                    $this->handlePaymentFailed($event->data->object);
                    break;
            }

            return ['success' => true, 'event_type' => $event->type];

        } catch (SignatureVerificationException $e) {
            Logger::error('Webhook signature verification failed', [
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'error' => 'Invalid signature'];

        } catch (\Exception $e) {
            Logger::error('Webhook processing failed', [
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Cancel user subscription
     */
    public function cancelSubscription(string $userId, bool $immediate = false): array {
        try {
            // Get user's subscription
            $subscription = $this->getUserSubscription($userId);
            if (!$subscription || !$subscription['stripe_subscription_id']) {
                return ['success' => false, 'error' => 'No active subscription found'];
            }

            $stripeSubId = $subscription['stripe_subscription_id'];

            if ($immediate) {
                // Cancel immediately
                Subscription::update($stripeSubId, ['cancel_at_period_end' => false]);
                \Stripe\Subscription::retrieve($stripeSubId)->cancel();
            } else {
                // Cancel at period end
                Subscription::update($stripeSubId, ['cancel_at_period_end' => true]);
            }

            Logger::info('Subscription cancelled', [
                'user_id' => $userId,
                'subscription_id' => $stripeSubId,
                'immediate' => $immediate
            ]);

            return ['success' => true];

        } catch (\Exception $e) {
            Logger::error('Failed to cancel subscription', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get or create Stripe customer for user
     */
    private function getOrCreateCustomer(string $userId): ?string {
        // Check if customer already exists
        $customerId = $this->getCustomerId($userId);
        if ($customerId) {
            return $customerId;
        }

        try {
            // Get user details
            $user = Database::fetch(
                "SELECT email, username FROM users WHERE id = :user_id",
                ['user_id' => $userId]
            );

            if (!$user) {
                throw new \RuntimeException('User not found');
            }

            // Create Stripe customer
            $customer = Customer::create([
                'email' => $user['email'],
                'name' => $user['username'] ?? $user['email'],
                'metadata' => [
                    'user_id' => $userId
                ]
            ]);

            // Store customer ID
            Database::execute(
                "UPDATE users SET stripe_customer_id = :customer_id WHERE id = :user_id",
                [
                    'customer_id' => $customer->id,
                    'user_id' => $userId
                ]
            );

            Logger::info('Stripe customer created', [
                'user_id' => $userId,
                'customer_id' => $customer->id
            ]);

            return $customer->id;

        } catch (\Exception $e) {
            Logger::error('Failed to create Stripe customer', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get Stripe customer ID for user
     */
    private function getCustomerId(string $userId): ?string {
        $result = Database::fetch(
            "SELECT stripe_customer_id FROM users WHERE id = :user_id",
            ['user_id' => $userId]
        );

        return $result['stripe_customer_id'] ?? null;
    }

    /**
     * Get user's subscription info
     */
    private function getUserSubscription(string $userId): ?array {
        return Database::fetch(
            "SELECT * FROM billing_accounts WHERE user_id = :user_id",
            ['user_id' => $userId]
        );
    }

    /**
     * Handle checkout.session.completed webhook
     */
    private function handleCheckoutCompleted($session): void {
        $userId = $session->metadata->user_id ?? $session->client_reference_id;
        $planKey = $session->metadata->plan_key ?? null;

        if (!$userId || !$planKey) {
            Logger::warning('Missing metadata in checkout session', [
                'session_id' => $session->id
            ]);
            return;
        }

        try {
            Database::beginTransaction();

            // Update or create billing account
            $existing = Database::fetch(
                "SELECT id FROM billing_accounts WHERE user_id = :user_id",
                ['user_id' => $userId]
            );

            if ($existing) {
                Database::execute(
                    "UPDATE billing_accounts
                     SET plan = :plan,
                         stripe_customer_id = :customer_id,
                         stripe_subscription_id = :subscription_id,
                         updated_at = NOW()
                     WHERE user_id = :user_id",
                    [
                        'plan' => $planKey,
                        'customer_id' => $session->customer,
                        'subscription_id' => $session->subscription,
                        'user_id' => $userId
                    ]
                );
            } else {
                Database::execute(
                    "INSERT INTO billing_accounts
                     (user_id, plan, currency, stripe_customer_id, stripe_subscription_id, created_at, updated_at)
                     VALUES (:user_id, :plan, 'USD', :customer_id, :subscription_id, NOW(), NOW())",
                    [
                        'user_id' => $userId,
                        'plan' => $planKey,
                        'customer_id' => $session->customer,
                        'subscription_id' => $session->subscription
                    ]
                );
            }

            // Update user's Stripe customer ID if not set
            Database::execute(
                "UPDATE users SET stripe_customer_id = :customer_id WHERE id = :user_id AND stripe_customer_id IS NULL",
                [
                    'customer_id' => $session->customer,
                    'user_id' => $userId
                ]
            );

            Database::commit();

            Logger::info('Checkout completed', [
                'user_id' => $userId,
                'plan' => $planKey,
                'session_id' => $session->id
            ]);

        } catch (\Exception $e) {
            Database::rollback();
            Logger::error('Failed to handle checkout completion', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle customer.subscription.created webhook
     */
    private function handleSubscriptionCreated($subscription): void {
        Logger::info('Subscription created', [
            'subscription_id' => $subscription->id,
            'customer_id' => $subscription->customer
        ]);

        // Additional logic if needed
    }

    /**
     * Handle customer.subscription.updated webhook
     */
    private function handleSubscriptionUpdated($subscription): void {
        try {
            // Update subscription status in database
            Database::execute(
                "UPDATE billing_accounts
                 SET updated_at = NOW()
                 WHERE stripe_subscription_id = :subscription_id",
                ['subscription_id' => $subscription->id]
            );

            Logger::info('Subscription updated', [
                'subscription_id' => $subscription->id
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to handle subscription update', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle customer.subscription.deleted webhook
     */
    private function handleSubscriptionDeleted($subscription): void {
        try {
            // Downgrade to free plan
            Database::execute(
                "UPDATE billing_accounts
                 SET plan = 'free',
                     stripe_subscription_id = NULL,
                     updated_at = NOW()
                 WHERE stripe_subscription_id = :subscription_id",
                ['subscription_id' => $subscription->id]
            );

            Logger::info('Subscription deleted, downgraded to free', [
                'subscription_id' => $subscription->id
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to handle subscription deletion', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle invoice.payment_succeeded webhook
     */
    private function handlePaymentSucceeded($invoice): void {
        Logger::info('Payment succeeded', [
            'invoice_id' => $invoice->id,
            'subscription_id' => $invoice->subscription ?? null
        ]);

        // Record payment in invoices table if needed
        try {
            if ($invoice->subscription) {
                Database::execute(
                    "INSERT INTO invoices (stripe_invoice_id, stripe_subscription_id, amount_cents, status, paid_at, created_at)
                     VALUES (:invoice_id, :subscription_id, :amount, 'paid', NOW(), NOW())
                     ON CONFLICT (stripe_invoice_id) DO NOTHING",
                    [
                        'invoice_id' => $invoice->id,
                        'subscription_id' => $invoice->subscription,
                        'amount' => $invoice->amount_paid
                    ]
                );
            }
        } catch (\Exception $e) {
            Logger::error('Failed to record invoice', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle invoice.payment_failed webhook
     */
    private function handlePaymentFailed($invoice): void {
        Logger::warning('Payment failed', [
            'invoice_id' => $invoice->id,
            'subscription_id' => $invoice->subscription ?? null,
            'amount_due' => $invoice->amount_due
        ]);

        // TODO: Send email notification to user
        // TODO: Implement retry logic or suspension after multiple failures
    }
}
