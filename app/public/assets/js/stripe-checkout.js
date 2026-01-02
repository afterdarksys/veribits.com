/**
 * Stripe Checkout Integration for VeriBits
 * Handles subscription checkout and customer portal redirects
 */

// Stripe publishable key (will be loaded from API)
let stripePublishableKey = null;

/**
 * Initialize Stripe integration
 */
async function initStripe() {
    try {
        const response = await fetch('/api/v1/billing/stripe/publishable-key');
        const data = await response.json();

        if (data.success && data.data.publishable_key) {
            stripePublishableKey = data.data.publishable_key;
            console.log('Stripe initialized successfully');
        } else {
            console.error('Failed to load Stripe publishable key');
        }
    } catch (error) {
        console.error('Error initializing Stripe:', error);
    }
}

/**
 * Create a Stripe checkout session and redirect to payment
 *
 * @param {string} planKey - Plan identifier ('pro' or 'enterprise')
 * @param {string} successUrl - URL to redirect after successful payment
 * @param {string} cancelUrl - URL to redirect if user cancels
 */
async function createStripeCheckout(planKey, successUrl = null, cancelUrl = null) {
    try {
        // Get auth token
        const token = localStorage.getItem('auth_token');
        if (!token) {
            window.location.href = '/login.php?redirect=' + encodeURIComponent(window.location.pathname);
            return;
        }

        // Default URLs
        const defaultSuccessUrl = window.location.origin + '/dashboard.php?payment=success';
        const defaultCancelUrl = window.location.origin + '/pricing.php?payment=cancelled';

        // Show loading state
        const button = event?.target;
        if (button) {
            button.disabled = true;
            button.textContent = 'Loading...';
        }

        // Create checkout session
        const response = await fetch('/api/v1/billing/stripe/create-checkout-session', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({
                plan: planKey,
                success_url: successUrl || defaultSuccessUrl,
                cancel_url: cancelUrl || defaultCancelUrl
            })
        });

        const data = await response.json();

        if (data.success && data.data.checkout_url) {
            // Redirect to Stripe Checkout
            window.location.href = data.data.checkout_url;
        } else {
            throw new Error(data.error || 'Failed to create checkout session');
        }
    } catch (error) {
        console.error('Checkout error:', error);
        alert('Failed to start checkout process. Please try again.');

        // Reset button state
        if (button) {
            button.disabled = false;
            button.textContent = 'Subscribe Now';
        }
    }
}

/**
 * Open Stripe Customer Portal for subscription management
 *
 * @param {string} returnUrl - URL to return to after managing subscription
 */
async function openStripePortal(returnUrl = null) {
    try {
        // Get auth token
        const token = localStorage.getItem('auth_token');
        if (!token) {
            window.location.href = '/login.php?redirect=' + encodeURIComponent(window.location.pathname);
            return;
        }

        // Default return URL
        const defaultReturnUrl = window.location.origin + '/dashboard.php';

        // Show loading state
        const button = event?.target;
        if (button) {
            button.disabled = true;
            button.textContent = 'Loading...';
        }

        // Create portal session
        const response = await fetch('/api/v1/billing/stripe/create-portal-session', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({
                return_url: returnUrl || defaultReturnUrl
            })
        });

        const data = await response.json();

        if (data.success && data.data.portal_url) {
            // Redirect to Stripe Customer Portal
            window.location.href = data.data.portal_url;
        } else {
            throw new Error(data.error || 'Failed to open customer portal');
        }
    } catch (error) {
        console.error('Portal error:', error);
        alert('Failed to open subscription management. Please try again.');

        // Reset button state
        if (button) {
            button.disabled = false;
            button.textContent = 'Manage Subscription';
        }
    }
}

/**
 * Cancel Stripe subscription
 *
 * @param {boolean} immediate - Cancel immediately (true) or at period end (false)
 */
async function cancelStripeSubscription(immediate = false) {
    if (!confirm('Are you sure you want to cancel your subscription?')) {
        return;
    }

    try {
        // Get auth token
        const token = localStorage.getItem('auth_token');
        if (!token) {
            window.location.href = '/login.php?redirect=' + encodeURIComponent(window.location.pathname);
            return;
        }

        // Show loading state
        const button = event?.target;
        if (button) {
            button.disabled = true;
            button.textContent = 'Cancelling...';
        }

        // Cancel subscription
        const response = await fetch('/api/v1/billing/stripe/cancel-subscription', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({
                immediate: immediate
            })
        });

        const data = await response.json();

        if (data.success) {
            alert(immediate
                ? 'Your subscription has been cancelled immediately.'
                : 'Your subscription will be cancelled at the end of the current billing period.');
            window.location.reload();
        } else {
            throw new Error(data.error || 'Failed to cancel subscription');
        }
    } catch (error) {
        console.error('Cancellation error:', error);
        alert('Failed to cancel subscription. Please try again or contact support.');

        // Reset button state
        if (button) {
            button.disabled = false;
            button.textContent = 'Cancel Subscription';
        }
    }
}

/**
 * Handle payment success callback
 * Call this on the success page to verify payment
 */
async function handlePaymentSuccess() {
    const urlParams = new URLSearchParams(window.location.search);
    const sessionId = urlParams.get('session_id');

    if (sessionId) {
        // Show success message
        const successMessage = document.getElementById('payment-success-message');
        if (successMessage) {
            successMessage.style.display = 'block';
            successMessage.innerHTML = `
                <div class="alert alert-success">
                    <h3>Payment Successful!</h3>
                    <p>Your subscription is now active. You can start using all premium features.</p>
                    <a href="/dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                </div>
            `;
        }

        // Clear session_id from URL
        window.history.replaceState({}, document.title, window.location.pathname);
    }
}

/**
 * Handle payment cancellation
 * Call this on the cancel page
 */
function handlePaymentCancelled() {
    const urlParams = new URLSearchParams(window.location.search);
    const payment = urlParams.get('payment');

    if (payment === 'cancelled') {
        const cancelMessage = document.getElementById('payment-cancel-message');
        if (cancelMessage) {
            cancelMessage.style.display = 'block';
            cancelMessage.innerHTML = `
                <div class="alert alert-warning">
                    <h3>Payment Cancelled</h3>
                    <p>Your payment was cancelled. You can try again anytime.</p>
                    <a href="/pricing.php" class="btn btn-primary">View Plans</a>
                </div>
            `;
        }
    }
}

// Initialize Stripe when page loads
document.addEventListener('DOMContentLoaded', function() {
    initStripe();
});

// Export functions for use in HTML
window.createStripeCheckout = createStripeCheckout;
window.openStripePortal = openStripePortal;
window.cancelStripeSubscription = cancelStripeSubscription;
window.handlePaymentSuccess = handlePaymentSuccess;
window.handlePaymentCancelled = handlePaymentCancelled;
