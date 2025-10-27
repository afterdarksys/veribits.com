<?php
header('Content-Type: application/json');

$result = [
    'opcache_enabled' => function_exists('opcache_reset'),
    'opcache_reset_result' => false,
    'timestamp' => date('Y-m-d H:i:s'),
];

if (function_exists('opcache_reset')) {
    $result['opcache_reset_result'] = opcache_reset();
}

if (function_exists('apc_clear_cache')) {
    $result['apc_cleared'] = apc_clear_cache();
}

echo json_encode($result, JSON_PRETTY_PRINT);
