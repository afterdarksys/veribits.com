#!/bin/bash
set -e

# Update system
yum update -y

# Install PHP 8.4 and required extensions
amazon-linux-extras install php8.4 -y
yum install -y php php-cli php-fpm php-pgsql php-mbstring php-xml php-json \
  php-curl php-gd php-zip php-bcmath php-intl php-opcache php-redis

# Install Apache
yum install -y httpd mod_ssl

# Install PostgreSQL client
yum install -y postgresql15

# Install Redis CLI
yum install -y redis6

# Install OpenSSL and security tools
yum install -y openssl keytool gpg unzip tar file clamav clamav-update

# Update ClamAV virus definitions
freshclam

# Install Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# Create application directory
mkdir -p /var/www/veribits
cd /var/www/veribits

# Download application code from S3
echo "Downloading application code from S3..."
DEPLOY_BUCKET="veribits-deployment-production-20251026"

# Try to download from S3 (will be uploaded by deployment script)
aws s3 cp s3://$DEPLOY_BUCKET/veribits-deploy.tar.gz /tmp/veribits-deploy.tar.gz 2>/dev/null || {
    echo "Warning: Could not download from S3, creating directory structure..."
    mkdir -p public src database
}

# Extract if downloaded successfully
if [ -f /tmp/veribits-deploy.tar.gz ]; then
    echo "Extracting application code..."
    tar -xzf /tmp/veribits-deploy.tar.gz -C /var/www/veribits/
    rm /tmp/veribits-deploy.tar.gz
else
    echo "Skipping extraction - no deployment package found"
fi

# Create .env file
cat > .env <<'EOF'
APP_ENV=${environment}
APP_DEBUG=false

# Database
DB_HOST=${db_host}
DB_PORT=${db_port}
DB_DATABASE=${db_name}
DB_USERNAME=${db_username}
DB_PASSWORD=${db_password}
DB_DRIVER=pgsql

# Redis
REDIS_HOST=${redis_host}
REDIS_PORT=${redis_port}
REDIS_PASSWORD=${redis_password}

# JWT
JWT_SECRET=${jwt_secret}
JWT_EXPIRATION=3600
JWT_REFRESH_EXPIRATION=2592000

# Stripe
STRIPE_SECRET_KEY=${stripe_secret}
STRIPE_PUBLISHABLE_KEY=${stripe_public}
STRIPE_WEBHOOK_SECRET=${stripe_webhook}

# Rate Limiting
RATE_LIMIT_ENABLED=true
RATE_LIMIT_WHITELIST=/tmp/rate_limit_whitelist.json

# Logging
LOG_LEVEL=info
LOG_PATH=/var/log/veribits
EOF

# Create log directory
mkdir -p /var/log/veribits
chown apache:apache /var/log/veribits

# Set permissions
chown -R apache:apache /var/www/veribits
chmod -R 755 /var/www/veribits
chmod 600 /var/www/veribits/.env

# Configure Apache
cat > /etc/httpd/conf.d/veribits.conf <<'APACHE_CONF'
<VirtualHost *:80>
    ServerName veribits.com
    ServerAlias www.veribits.com
    DocumentRoot /var/www/veribits/public

    <Directory /var/www/veribits/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Security headers
    Header always set X-Frame-Options "DENY"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"

    ErrorLog /var/log/httpd/veribits_error.log
    CustomLog /var/log/httpd/veribits_access.log combined
</VirtualHost>
APACHE_CONF

# Enable Apache modules
cat >> /etc/httpd/conf/httpd.conf <<'APACHE_MODULES'
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule headers_module modules/mod_headers.so
APACHE_MODULES

# Configure PHP
cat >> /etc/php.ini <<'PHP_INI'
; Security settings
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/veribits/php_errors.log

; Performance
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2

; Upload limits
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
memory_limit = 512M

; Session security
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
PHP_INI

# Wait for RDS to be available
echo "Waiting for database to be available..."
for i in {1..30}; do
    if psql -h ${db_host} -U ${db_username} -d ${db_name} -c "SELECT 1" > /dev/null 2>&1; then
        echo "Database is available"
        break
    fi
    echo "Waiting for database... attempt $i/30"
    sleep 10
done

# Run database migrations
echo "Running database migrations..."
export PGPASSWORD='${db_password}'

# Run migrations in order
for migration in /var/www/veribits/database/migrations/*.sql; do
    if [ -f "$migration" ]; then
        echo "Running migration: $(basename $migration)"
        psql -h ${db_host} -U ${db_username} -d ${db_name} -f "$migration"
    fi
done

# Create test accounts
if [ -f /var/www/veribits/database/seeds/create_test_accounts.sql ]; then
    echo "Creating test accounts..."
    psql -h ${db_host} -U ${db_username} -d ${db_name} -f /var/www/veribits/database/seeds/create_test_accounts.sql
fi

unset PGPASSWORD

# Test Redis connection
echo "Testing Redis connection..."
redis-cli -h ${redis_host} -p ${redis_port} -a ${redis_password} --tls PING || echo "Redis connection failed"

# IMPORTANT: Clear all caches before starting services
echo "Clearing all caches..."

# Clear PHP opcache (will be cleared on restart, but also clear cache directory)
rm -rf /var/cache/php-fpm/*
rm -rf /tmp/php-fpm-cache/*

# Clear Apache cache
rm -rf /var/cache/httpd/*

# Clear Redis cache (flush all)
echo "Flushing Redis cache..."
redis-cli -h ${redis_host} -p ${redis_port} -a ${redis_password} --tls FLUSHALL || echo "Redis flush failed"

# Clear system package cache
yum clean all

# Clear temp files
rm -rf /tmp/veribits-*
rm -rf /var/tmp/php*

echo "All caches cleared!"

# Enable and start services
systemctl enable httpd
systemctl start httpd
systemctl enable php-fpm
systemctl start php-fpm

# Verify opcache is enabled and clear it via PHP
echo "Verifying PHP opcache status..."
php -r "if(function_exists('opcache_reset')) { opcache_reset(); echo 'OpCache cleared\n'; } else { echo 'OpCache not enabled\n'; }"

# Health check endpoint
cat > /var/www/veribits/public/health.php <<'HEALTH_PHP'
<?php
header('Content-Type: application/json');
echo json_encode([
    'status' => 'healthy',
    'timestamp' => date('c'),
    'hostname' => gethostname()
]);
HEALTH_PHP

echo "VeriBits application deployment complete!"
