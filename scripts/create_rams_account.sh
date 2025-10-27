#!/bin/bash
# Create account for rams3377@gmail.com with annual subscription

# Get DB credentials from AWS Secrets Manager or environment
DB_HOST="${DB_HOST:-nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com}"
DB_NAME="${DB_NAME:-veribits}"
DB_USER="${DB_USER:-nitetext}"
DB_PASS="${DB_PASS:-NiteText2025!SecureProd}"

# Generate password hash using PHP
PASSWORD_HASH=$(php -r "echo password_hash('Password@305', PASSWORD_BCRYPT);")

# Create SQL to insert user
SQL="DO \$\$
DECLARE
    v_user_id UUID;
BEGIN
    -- Check if user exists
    SELECT id INTO v_user_id FROM users WHERE email = 'rams3377@gmail.com';

    IF v_user_id IS NULL THEN
        -- Create new user
        INSERT INTO users (email, password, status, email_verified)
        VALUES ('rams3377@gmail.com', '$PASSWORD_HASH', 'active', true)
        RETURNING id INTO v_user_id;

        RAISE NOTICE 'Created user: rams3377@gmail.com (ID: %)', v_user_id;

        -- Create billing account with annual plan
        INSERT INTO billing_accounts (user_id, plan, currency)
        VALUES (v_user_id, 'annual', 'USD');

        RAISE NOTICE 'Created billing account with annual plan';
    ELSE
        -- Update existing user
        UPDATE users
        SET password = '$PASSWORD_HASH',
            status = 'active',
            email_verified = true
        WHERE id = v_user_id;

        RAISE NOTICE 'Updated existing user: rams3377@gmail.com (ID: %)', v_user_id;

        -- Update billing account
        UPDATE billing_accounts SET plan = 'annual' WHERE user_id = v_user_id;
        IF NOT FOUND THEN
            INSERT INTO billing_accounts (user_id, plan, currency)
            VALUES (v_user_id, 'annual', 'USD');
        END IF;

        RAISE NOTICE 'Updated billing account to annual plan';
    END IF;
END \$\$;"

# Execute SQL
echo "Creating/updating account for rams3377@gmail.com..."
echo "$SQL" | PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME"

if [ $? -eq 0 ]; then
    echo "✓ Account created/updated successfully"
    echo "  Email: rams3377@gmail.com"
    echo "  Password: Password@305"
    echo "  Plan: Annual"
else
    echo "✗ Failed to create account"
    exit 1
fi
