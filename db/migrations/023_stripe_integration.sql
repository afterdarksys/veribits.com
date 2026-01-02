-- Migration 023: Stripe Payment Integration
-- Add Stripe-related fields and tables for subscription management
-- Created: 2025-01-19

-- Add Stripe customer ID to users table
ALTER TABLE users
ADD COLUMN IF NOT EXISTS stripe_customer_id VARCHAR(255) UNIQUE;

CREATE INDEX IF NOT EXISTS idx_users_stripe_customer_id
ON users(stripe_customer_id);

COMMENT ON COLUMN users.stripe_customer_id IS 'Stripe customer ID for payment processing';

-- Add Stripe subscription ID to billing_accounts table
ALTER TABLE billing_accounts
ADD COLUMN IF NOT EXISTS stripe_customer_id VARCHAR(255),
ADD COLUMN IF NOT EXISTS stripe_subscription_id VARCHAR(255) UNIQUE;

CREATE INDEX IF NOT EXISTS idx_billing_accounts_stripe_subscription_id
ON billing_accounts(stripe_subscription_id);

CREATE INDEX IF NOT EXISTS idx_billing_accounts_stripe_customer_id
ON billing_accounts(stripe_customer_id);

COMMENT ON COLUMN billing_accounts.stripe_customer_id IS 'Stripe customer ID';
COMMENT ON COLUMN billing_accounts.stripe_subscription_id IS 'Stripe subscription ID';

-- Add Stripe invoice ID to invoices table (if it exists)
DO $$
BEGIN
    IF EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'invoices') THEN
        ALTER TABLE invoices
        ADD COLUMN IF NOT EXISTS stripe_invoice_id VARCHAR(255) UNIQUE,
        ADD COLUMN IF NOT EXISTS stripe_subscription_id VARCHAR(255);

        CREATE INDEX IF NOT EXISTS idx_invoices_stripe_invoice_id
        ON invoices(stripe_invoice_id);

        CREATE INDEX IF NOT EXISTS idx_invoices_stripe_subscription_id
        ON invoices(stripe_subscription_id);

        COMMENT ON COLUMN invoices.stripe_invoice_id IS 'Stripe invoice ID';
        COMMENT ON COLUMN invoices.stripe_subscription_id IS 'Associated Stripe subscription ID';
    END IF;
END $$;

-- Create stripe_events table to log all webhook events
CREATE TABLE IF NOT EXISTS stripe_events (
    id BIGSERIAL PRIMARY KEY,
    event_id VARCHAR(255) UNIQUE NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    livemode BOOLEAN DEFAULT false,
    customer_id VARCHAR(255),
    subscription_id VARCHAR(255),
    invoice_id VARCHAR(255),
    payload JSONB NOT NULL,
    processed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_stripe_events_event_type ON stripe_events(event_type);
CREATE INDEX IF NOT EXISTS idx_stripe_events_customer_id ON stripe_events(customer_id);
CREATE INDEX IF NOT EXISTS idx_stripe_events_subscription_id ON stripe_events(subscription_id);
CREATE INDEX IF NOT EXISTS idx_stripe_events_created_at ON stripe_events(created_at);

COMMENT ON TABLE stripe_events IS 'Audit log of all Stripe webhook events';
COMMENT ON COLUMN stripe_events.event_id IS 'Unique Stripe event ID';
COMMENT ON COLUMN stripe_events.livemode IS 'Whether event came from live or test mode';
COMMENT ON COLUMN stripe_events.payload IS 'Full JSON payload from Stripe webhook';
COMMENT ON COLUMN stripe_events.processed_at IS 'When the event was processed by our application';

-- Create stripe_checkout_sessions table to track checkout sessions
CREATE TABLE IF NOT EXISTS stripe_checkout_sessions (
    id BIGSERIAL PRIMARY KEY,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    session_id VARCHAR(255) UNIQUE NOT NULL,
    customer_id VARCHAR(255),
    subscription_id VARCHAR(255),
    plan_key VARCHAR(50),
    amount_total INTEGER,
    currency VARCHAR(3) DEFAULT 'usd',
    status VARCHAR(50),
    payment_status VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP,
    expires_at TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_stripe_checkout_sessions_user_id ON stripe_checkout_sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_stripe_checkout_sessions_session_id ON stripe_checkout_sessions(session_id);
CREATE INDEX IF NOT EXISTS idx_stripe_checkout_sessions_status ON stripe_checkout_sessions(status);

COMMENT ON TABLE stripe_checkout_sessions IS 'Tracking table for Stripe checkout sessions';
COMMENT ON COLUMN stripe_checkout_sessions.plan_key IS 'Plan identifier (pro, enterprise)';

-- Create function to log Stripe events
CREATE OR REPLACE FUNCTION log_stripe_event(
    p_event_id VARCHAR(255),
    p_event_type VARCHAR(100),
    p_payload JSONB,
    p_customer_id VARCHAR(255) DEFAULT NULL,
    p_subscription_id VARCHAR(255) DEFAULT NULL,
    p_invoice_id VARCHAR(255) DEFAULT NULL
)
RETURNS VOID AS $$
BEGIN
    INSERT INTO stripe_events (
        event_id,
        event_type,
        customer_id,
        subscription_id,
        invoice_id,
        payload,
        livemode,
        processed_at
    ) VALUES (
        p_event_id,
        p_event_type,
        p_customer_id,
        p_subscription_id,
        p_invoice_id,
        p_payload,
        (p_payload->>'livemode')::boolean,
        CURRENT_TIMESTAMP
    )
    ON CONFLICT (event_id) DO UPDATE
    SET processed_at = CURRENT_TIMESTAMP;
END;
$$ LANGUAGE plpgsql;

COMMENT ON FUNCTION log_stripe_event IS 'Helper function to log Stripe webhook events';

-- Grant permissions
GRANT SELECT, INSERT, UPDATE ON stripe_events TO veribits_app;
GRANT SELECT, INSERT, UPDATE ON stripe_checkout_sessions TO veribits_app;
GRANT USAGE, SELECT ON SEQUENCE stripe_events_id_seq TO veribits_app;
GRANT USAGE, SELECT ON SEQUENCE stripe_checkout_sessions_id_seq TO veribits_app;
GRANT EXECUTE ON FUNCTION log_stripe_event TO veribits_app;

-- Migration metadata
INSERT INTO schema_migrations (version, description, applied_at)
VALUES (23, 'Add Stripe payment integration support', CURRENT_TIMESTAMP)
ON CONFLICT (version) DO NOTHING;
