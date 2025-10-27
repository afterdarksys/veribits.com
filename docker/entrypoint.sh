#!/bin/bash
set -e

echo "========================================="
echo "VeriBits Container Starting..."
echo "========================================="

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
