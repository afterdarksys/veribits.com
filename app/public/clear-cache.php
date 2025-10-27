<?php
header('Content-Type: application/json');

$result = [
    'opcache_reset' => false,
    'realpath_cache_cleared' => false,
];

if (function_exists('opcache_reset')) {
    $result['opcache_reset'] = opcache_reset();
}

if (function_exists('clearstatcache')) {
    clearstatcache(true);
    $result['realpath_cache_cleared'] = true;
}

echo json_encode($result, JSON_PRETTY_PRINT);
