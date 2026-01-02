-- Migration 024: After Dark Systems Central Auth Integration
-- Date: 2025-12-29
-- Description: Add central_auth_id column to users table for OIDC integration

-- Add central_auth_id column to users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS central_auth_id VARCHAR(255);

-- Add unique index for central_auth_id (one Central account per VeriBits account)
CREATE UNIQUE INDEX IF NOT EXISTS idx_users_central_auth_id ON users(central_auth_id)
WHERE central_auth_id IS NOT NULL;

-- Add index for faster lookups
CREATE INDEX IF NOT EXISTS idx_users_central_auth_lookup ON users(central_auth_id);

-- Log the migration
DO $$
BEGIN
    RAISE NOTICE 'Migration 024: Central Auth integration completed';
END $$;
