<?php
/**
 * PHP Environment Debug Endpoint
 * SECURITY: Remove this file after debugging is complete
 *
 * This endpoint helps diagnose PHP configuration issues in production ECS
 */

header('Content-Type: application/json');

// Security: Only allow in production with specific header
if ($_SERVER['APP_ENV'] !== 'production' && !isset($_SERVER['HTTP_X_DEBUG_TOKEN'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$info = [
    'php_version' => PHP_VERSION,
    'php_sapi' => PHP_SAPI,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
    'password_algorithms' => [
        'PASSWORD_BCRYPT' => PASSWORD_BCRYPT,
        'PASSWORD_DEFAULT' => PASSWORD_DEFAULT,
        'PASSWORD_ARGON2I' => defined('PASSWORD_ARGON2I') ? PASSWORD_ARGON2I : 'not available',
        'PASSWORD_ARGON2ID' => defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : 'not available',
    ],
    'password_extensions' => [
        'sodium' => extension_loaded('sodium'),
        'hash' => extension_loaded('hash'),
    ],
    'bcrypt_test' => [
        'hash_generation' => function_exists('password_hash'),
        'hash_verification' => function_exists('password_verify'),
    ],
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'opcache_enabled' => function_exists('opcache_get_status') ? opcache_get_status(false) !== false : false,
    'environment' => [
        'APP_ENV' => $_ENV['APP_ENV'] ?? 'not set',
        'DB_HOST' => isset($_ENV['DB_HOST']) ? 'set' : 'not set',
        'REDIS_HOST' => isset($_ENV['REDIS_HOST']) ? 'set' : 'not set',
    ]
];

// Test password_verify with known working pair
$testPassword = 'TestPassword123!';
$testHash = '$2y$12$eKJCykdGXuNZ.k/lJQtHF.f51GG/Uetdhuqm0BU6cGYAlEYkCfAG2';

$info['bcrypt_live_test'] = [
    'hardcoded_verify' => password_verify($testPassword, $testHash),
    'hash_info' => password_get_info($testHash),
    'test_hash_generation' => password_hash($testPassword, PASSWORD_BCRYPT, ['cost' => 12]),
];

// Test if newly generated hash verifies correctly
$newHash = password_hash($testPassword, PASSWORD_BCRYPT, ['cost' => 12]);
$info['bcrypt_live_test']['new_hash_verifies'] = password_verify($testPassword, $newHash);

// Apache/PHP interface diagnostics
$info['request_environment'] = [
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
    'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 'not set',
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'not set',
    'http_authorization' => isset($_SERVER['HTTP_AUTHORIZATION']) ? 'present' : 'not set',
    'php_input_available' => stream_get_wrappers(),
];

echo json_encode($info, JSON_PRETTY_PRINT);
