<?php
namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\Validator;
use VeriBits\Utils\Logger;
use VeriBits\Utils\Config;
use VeriBits\Services\BillingService;
use VeriBits\Services\StripeService;

class BillingController {
    private BillingService $billingService;
    private StripeService $stripeService;

    public function __construct() {
        $this->billingService = new BillingService();
        $this->stripeService = new StripeService();
    }

    public function getAccount(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        try {
            $account = $this->billingService->getUserBillingAccount($userId);

            if (!$account) {
                Response::error('Billing account not found', 404);
                return;
            }

            Response::success($account);
        } catch (\Exception $e) {
            Logger::error('Failed to get billing account', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to retrieve billing account', 500);
        }
    }

    public function getPlans(): void {
        try {
            $plans = $this->billingService->getAllPlans();
            Response::success(['plans' => $plans]);
        } catch (\Exception $e) {
            Logger::error('Failed to get plans', ['error' => $e->getMessage()]);
            Response::error('Failed to retrieve plans', 500);
        }
    }

    public function upgradePlan(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $validator = new Validator($body);

        $validator->required('plan')->in('plan', ['free', 'pro', 'enterprise']);

        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
            return;
        }

        $newPlan = $validator->sanitize('plan');

        try {
            $success = $this->billingService->upgradePlan($userId, $newPlan);

            if ($success) {
                Logger::info('Plan upgrade successful', [
                    'user_id' => $userId,
                    'new_plan' => $newPlan
                ]);

                Response::success([
                    'plan' => $newPlan,
                    'message' => 'Plan upgraded successfully'
                ]);
            } else {
                Response::error('Failed to upgrade plan', 500);
            }
        } catch (\Exception $e) {
            Logger::error('Plan upgrade failed', [
                'user_id' => $userId,
                'new_plan' => $newPlan,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to upgrade plan: ' . $e->getMessage(), 500);
        }
    }

    public function cancelSubscription(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        try {
            $success = $this->billingService->cancelSubscription($userId);

            if ($success) {
                Logger::info('Subscription cancelled', ['user_id' => $userId]);
                Response::success([], 'Subscription cancelled successfully');
            } else {
                Response::error('Failed to cancel subscription', 500);
            }
        } catch (\Exception $e) {
            Logger::error('Subscription cancellation failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to cancel subscription', 500);
        }
    }

    public function getUsage(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        try {
            $usage = $this->billingService->getUsageStats($userId);
            Response::success($usage);
        } catch (\Exception $e) {
            Logger::error('Failed to get usage stats', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to retrieve usage statistics', 500);
        }
    }

    public function getInvoices(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        try {
            $invoices = $this->billingService->getInvoiceHistory($userId);
            Response::success(['invoices' => $invoices]);
        } catch (\Exception $e) {
            Logger::error('Failed to get invoice history', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to retrieve invoice history', 500);
        }
    }

    public function processPayment(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $validator = new Validator($body);

        $validator->required('invoice_id')->string('invoice_id')
                  ->required('payment_method')->string('payment_method')
                  ->string('card_token');

        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
            return;
        }

        $invoiceId = $validator->sanitize('invoice_id');
        $paymentData = [
            'payment_method' => $validator->sanitize('payment_method'),
            'card_token' => $validator->sanitize('card_token')
        ];

        try {
            $success = $this->billingService->processPayment($invoiceId, $paymentData);

            if ($success) {
                Logger::info('Payment processed successfully', [
                    'user_id' => $userId,
                    'invoice_id' => $invoiceId
                ]);
                Response::success([], 'Payment processed successfully');
            } else {
                Response::error('Payment processing failed', 400);
            }
        } catch (\Exception $e) {
            Logger::error('Payment processing failed', [
                'user_id' => $userId,
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage()
            ]);
            Response::error('Payment processing failed', 500);
        }
    }

    public function getPlanRecommendation(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        try {
            $recommendation = $this->billingService->calculatePlanRecommendation($userId);
            Response::success($recommendation);
        } catch (\Exception $e) {
            Logger::error('Failed to get plan recommendation', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to generate plan recommendation', 500);
        }
    }

    // ============================================================================
    // Stripe Integration Endpoints
    // ============================================================================

    /**
     * Get Stripe publishable key for frontend
     * GET /api/billing/stripe/publishable-key
     */
    public function getStripePublishableKey(): void {
        try {
            $key = $this->stripeService->getPublishableKey();
            Response::success(['publishable_key' => $key]);
        } catch (\Exception $e) {
            Logger::error('Failed to get Stripe publishable key', [
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to get Stripe key', 500);
        }
    }

    /**
     * Create Stripe checkout session
     * POST /api/billing/stripe/create-checkout-session
     * Body: { "plan": "pro" | "enterprise", "success_url": "...", "cancel_url": "..." }
     */
    public function createStripeCheckout(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $validator = new Validator($body);

        $validator->required('plan')->in('plan', ['pro', 'enterprise'])
                  ->required('success_url')->string('success_url')
                  ->required('cancel_url')->string('cancel_url');

        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
            return;
        }

        try {
            $result = $this->stripeService->createCheckoutSession(
                $userId,
                $validator->sanitize('plan'),
                $validator->sanitize('success_url'),
                $validator->sanitize('cancel_url')
            );

            if ($result['success']) {
                Response::success([
                    'session_id' => $result['session_id'],
                    'checkout_url' => $result['checkout_url']
                ]);
            } else {
                Response::error($result['error'] ?? 'Failed to create checkout session', 400);
            }
        } catch (\Exception $e) {
            Logger::error('Failed to create Stripe checkout', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to create checkout session', 500);
        }
    }

    /**
     * Create customer portal session
     * POST /api/billing/stripe/create-portal-session
     * Body: { "return_url": "..." }
     */
    public function createStripePortal(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $validator = new Validator($body);

        $validator->required('return_url')->string('return_url');

        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
            return;
        }

        try {
            $result = $this->stripeService->createPortalSession(
                $userId,
                $validator->sanitize('return_url')
            );

            if ($result['success']) {
                Response::success(['portal_url' => $result['portal_url']]);
            } else {
                Response::error($result['error'] ?? 'Failed to create portal session', 400);
            }
        } catch (\Exception $e) {
            Logger::error('Failed to create Stripe portal', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to create portal session', 500);
        }
    }

    /**
     * Cancel Stripe subscription
     * POST /api/billing/stripe/cancel-subscription
     * Body: { "immediate": true|false } (optional)
     */
    public function cancelStripeSubscription(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $immediate = $body['immediate'] ?? false;

        try {
            $result = $this->stripeService->cancelSubscription($userId, $immediate);

            if ($result['success']) {
                Response::success([], 'Subscription cancelled successfully');
            } else {
                Response::error($result['error'] ?? 'Failed to cancel subscription', 400);
            }
        } catch (\Exception $e) {
            Logger::error('Failed to cancel Stripe subscription', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to cancel subscription', 500);
        }
    }

    /**
     * Stripe webhook handler
     * POST /api/billing/stripe/webhook
     */
    public function webhookStripe(): void {
        $payload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        try {
            $result = $this->stripeService->handleWebhook($payload, $signature);

            if ($result['success']) {
                Response::success([], 'Webhook processed');
            } else {
                Response::error($result['error'] ?? 'Webhook processing failed', 400);
            }
        } catch (\Exception $e) {
            Logger::error('Stripe webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => substr($payload, 0, 500)
            ]);
            Response::error('Webhook processing failed', 400);
        }
    }
}