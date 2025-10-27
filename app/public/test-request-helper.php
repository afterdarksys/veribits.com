<?php
// Test if Request helper can read POST body
require_once __DIR__ . '/../src/Utils/Request.php';

use VeriBits\Utils\Request;

header('Content-Type: application/json');

$body = Request::getJsonBody();
$rawBody = Request::getBody();

echo json_encode([
    'success' => true,
    'raw_body' => $rawBody,
    'raw_body_length' => strlen($rawBody),
    'json_body' => $body,
    'has_email' => isset($body['email']),
    'has_password' => isset($body['password']),
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
    'http_content_type' => $_SERVER['HTTP_CONTENT_TYPE'] ?? 'not set',
    'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 'not set',
], JSON_PRETTY_PRINT);
