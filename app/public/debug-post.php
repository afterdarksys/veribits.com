<?php
// Temporary debug endpoint - DELETE AFTER DEBUGGING
header('Content-Type: application/json');

$debug = [
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'not set',
    'content_type_server' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
    'content_length_server' => $_SERVER['CONTENT_LENGTH'] ?? 'not set',
    'http_content_type' => $_SERVER['HTTP_CONTENT_TYPE'] ?? 'not set',
    'http_content_length' => $_SERVER['HTTP_CONTENT_LENGTH'] ?? 'not set',
    'php_input' => file_get_contents('php://input'),
    'php_input_length' => strlen(file_get_contents('php://input')),
    'post_data' => $_POST,
    'server_keys' => array_keys($_SERVER),
    'php_version' => phpversion(),
    'sapi' => php_sapi_name(),
];

echo json_encode($debug, JSON_PRETTY_PRINT);
