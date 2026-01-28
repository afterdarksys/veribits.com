<?php

require_once __DIR__ . '/../vendor/autoload.php';

use VeriBits\Auth\OAuth;

session_start();

// Load environment variables
if (file_exists(__DIR__ . '/../.env.production')) {
    $lines = file(__DIR__ . '/../.env.production', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

try {
    // Check for OAuth errors
    if (isset($_GET['error'])) {
        error_log('OAuth error: ' . $_GET['error']);
        header('Location: /login.php?error=' . urlencode($_GET['error']));
        exit;
    }

    // Validate required parameters
    $code = $_GET['code'] ?? null;
    $state = $_GET['state'] ?? null;

    if (!$code || !$state) {
        header('Location: /login.php?error=missing_parameters');
        exit;
    }

    // Verify state (CSRF protection)
    $savedState = $_SESSION['oauth_state'] ?? null;

    if (!$savedState || $savedState !== $state) {
        error_log('State mismatch');
        header('Location: /login.php?error=invalid_state');
        exit;
    }

    // Clear state
    unset($_SESSION['oauth_state']);

    $oauth = new OAuth();

    // Exchange code for tokens
    $tokens = $oauth->exchangeCodeForTokens($code);

    // Get user info from Authentik
    $userInfo = $oauth->getUserInfo($tokens['access_token']);

    // TODO: Find or create user in database
    // For now, store user info in session
    $_SESSION['user'] = [
        'email' => $userInfo['email'],
        'name' => $userInfo['name'] ?? $userInfo['preferred_username'] ?? 'User',
        'username' => $userInfo['preferred_username'] ?? $userInfo['email'],
        'email_verified' => $userInfo['email_verified'] ?? false,
        'oauth' => true,
    ];

    $_SESSION['oauth_tokens'] = [
        'access_token' => $tokens['access_token'],
        'refresh_token' => $tokens['refresh_token'] ?? null,
        'id_token' => $tokens['id_token'] ?? null,
        'expires_at' => time() + ($tokens['expires_in'] ?? 3600),
    ];

    // Redirect to dashboard
    header('Location: /dashboard.php');
    exit;

} catch (Exception $e) {
    error_log('OAuth callback error: ' . $e->getMessage());
    header('Location: /login.php?error=callback_failed');
    exit;
}
