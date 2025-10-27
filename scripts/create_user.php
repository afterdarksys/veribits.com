#!/usr/bin/env php
<?php
/**
 * Create user account and assign subscription
 */

require_once __DIR__ . '/../app/src/Utils/Database.php';

use VeriBits\Utils\Database;

// Load config
$_ENV['DB_HOST'] = getenv('DB_HOST') ?: 'localhost';
$_ENV['DB_NAME'] = getenv('DB_NAME') ?: 'veribits';
$_ENV['DB_USER'] = getenv('DB_USER') ?: 'veribits_admin';
$_ENV['DB_PASS'] = getenv('DB_PASS') ?: '';

if ($argc < 4) {
    echo "Usage: php create_user.php <email> <password> <plan>\n";
    echo "Plans: free, monthly, annual\n";
    exit(1);
}

$email = $argv[1];
$password = $argv[2];
$plan = $argv[3];

try {
    $conn = Database::getConnection();

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // Check if user exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = $1");
    $stmt->execute([$email]);

    if ($stmt->fetch()) {
        echo "User with email $email already exists.\n";

        // Update password and plan
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = $1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $userId = $user['id'];

        $stmt = $conn->prepare("UPDATE users SET password = $1 WHERE id = $2");
        $stmt->execute([$hashedPassword, $userId]);

        echo "Updated password for $email\n";

        // Update billing plan
        $stmt = $conn->prepare("UPDATE billing_accounts SET plan = $1 WHERE user_id = $2");
        $stmt->execute([$plan, $userId]);

        echo "Updated plan to $plan for $email\n";
        exit(0);
    }

    // Create new user
    $stmt = $conn->prepare("
        INSERT INTO users (email, password, status, email_verified)
        VALUES ($1, $2, 'active', true)
        RETURNING id
    ");

    $stmt->execute([$email, $hashedPassword]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $userId = $result['id'];

    echo "Created user: $email (ID: $userId)\n";

    // Create billing account
    $stmt = $conn->prepare("
        INSERT INTO billing_accounts (user_id, plan, currency)
        VALUES ($1, $2, 'USD')
    ");

    $stmt->execute([$userId, $plan]);

    echo "Created billing account with plan: $plan\n";
    echo "✓ Account setup complete for $email\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
