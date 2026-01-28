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
    $oauth = new OAuth();

    // Generate state for CSRF protection
    $state = $oauth->generateState();
    $_SESSION['oauth_state'] = $state;

    // Redirect to Authentik for authentication
    $authUrl = $oauth->getAuthorizationUrl($state);
    header('Location: ' . $authUrl);
    exit;

} catch (Exception $e) {
    error_log('OAuth login error: ' . $e->getMessage());
    header('Location: /login.php?error=oauth_init_failed');
    exit;
}
