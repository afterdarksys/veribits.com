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

    // Get ID token for logout
    $idToken = $_SESSION['oauth_tokens']['id_token'] ?? null;

    // Clear session
    session_destroy();

    // Redirect to Authentik logout
    $logoutUrl = $oauth->getLogoutUrl($idToken);
    header('Location: ' . $logoutUrl);
    exit;

} catch (Exception $e) {
    error_log('OAuth logout error: ' . $e->getMessage());
    session_destroy();
    header('Location: /');
    exit;
}
