#!/bin/bash
set -e

echo "========================================="
echo "VeriBits Container Starting..."
echo "========================================="

# CRITICAL FIX: Force OPcache reset on container startup
# This prevents corrupted cache from previous deployments
echo "Clearing PHP OPcache..."
rm -rf /tmp/php* /var/tmp/php* 2>/dev/null || true
mkdir -p /tmp/opcache && chown www-data:www-data /tmp/opcache

# Verify password hashing works in this environment
echo "Testing password verification..."
php -r "
\$hash = password_hash('TestPassword123!', PASSWORD_BCRYPT, ['cost' => 10]);
if (password_verify('TestPassword123!', \$hash)) {
    echo 'password_verify() test: PASS' . PHP_EOL;
} else {
    echo 'password_verify() test: FAIL - CRITICAL ERROR' . PHP_EOL;
    exit(1);
}

// Test crypt() fallback
\$cryptHash = crypt('test', '\$2y\$10\$1234567890123456789012euJGowAe.cP7QKOJGZrOGMbXJkHf.e');
\$cryptResult = crypt('test', \$cryptHash);
if (hash_equals(\$cryptHash, \$cryptResult)) {
    echo 'crypt() fallback test: PASS' . PHP_EOL;
} else {
    echo 'crypt() fallback test: FAIL' . PHP_EOL;
}
"

if [ $? -ne 0 ]; then
    echo "CRITICAL: Password verification is broken in this environment!"
    echo "Container will continue but authentication will fail."
fi

# Run database migrations if DB is accessible
if [ -n "$DB_HOST" ] && [ -n "$DB_PASSWORD" ]; then
    echo "Running database migrations..."
    /var/www/scripts/run-migrations.sh || echo "WARNING: Migrations failed or already applied"
fi

# Start ClamAV daemon in background
echo "Starting ClamAV daemon..."
freshclam || echo "WARNING: FreshClam update failed (will use existing signatures)"
clamd &

# Start Apache in foreground
echo "Starting Apache web server..."
exec apache2-foreground
