<?php
namespace VeriBits\Utils;

class Response {
    public static function json(array $data, int $code = 200): void {
        http_response_code($code);

        self::setSecurityHeaders();

        header('Content-Type: application/json; charset=utf-8');

        if ($code >= 400) {
            Logger::warning('HTTP error response', [
                'code' => $code,
                'data' => $data,
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'method' => $_SERVER['REQUEST_METHOD'] ?? ''
            ]);
        }

        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function success(array $data = [], string $message = 'Success'): void {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ]);
    }

    /**
     * Send a cacheable JSON response with ETag support
     * Use this for GET endpoints that return relatively static data
     *
     * @param array $data Response data
     * @param string $message Success message
     * @param int $maxAge Cache-Control max-age in seconds (default: 300 = 5 minutes)
     * @param bool $private Whether cache should be private (default: true for authenticated)
     */
    public static function cached(array $data, string $message = 'Success', int $maxAge = 300, bool $private = true): void {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ];

        $jsonContent = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $etag = '"' . md5($jsonContent) . '"';

        // Check If-None-Match header
        $clientEtag = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        if ($clientEtag === $etag) {
            http_response_code(304);
            header("ETag: $etag");
            return;
        }

        // Set caching headers
        $cacheControl = $private ? 'private' : 'public';
        $cacheControl .= ", max-age=$maxAge, must-revalidate";

        http_response_code(200);
        self::setSecurityHeaders();
        header('Content-Type: application/json; charset=utf-8');
        header("ETag: $etag");
        header("Cache-Control: $cacheControl");
        header('Vary: Accept, Authorization');

        echo $jsonContent;
    }

    /**
     * Send a response that should never be cached
     */
    public static function noCache(array $data, string $message = 'Success'): void {
        http_response_code(200);
        self::setSecurityHeaders();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function error(string $message, int $code = 400, array $details = []): void {
        self::json([
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $code,
                'details' => $details
            ],
            'timestamp' => date('c')
        ], $code);
    }

    public static function validationError(array $errors): void {
        self::json([
            'success' => false,
            'error' => [
                'message' => 'Validation failed',
                'code' => 422,
                'validation_errors' => $errors
            ],
            'timestamp' => date('c')
        ], 422);
    }

    public static function paginated(array $data, int $total, int $page, int $perPage): void {
        $totalPages = ceil($total / $perPage);

        self::json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ],
            'timestamp' => date('c')
        ]);
    }

    private static function setSecurityHeaders(): void {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                   ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        if ($isHttps) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }

        // CORS handling with proper validation
        $corsConfig = Config::get('CORS_ALLOWED_ORIGINS', '');
        $allowedOrigins = array_filter(
            array_map('trim', explode(',', $corsConfig)),
            fn($o) => !empty($o)
        );
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (Config::isDevelopment()) {
            // Allow all origins in development
            header('Access-Control-Allow-Origin: *');
        } elseif (!empty($origin) && filter_var($origin, FILTER_VALIDATE_URL)) {
            // Validate and sanitize origin
            $parsedOrigin = parse_url($origin);
            if (isset($parsedOrigin['scheme']) && isset($parsedOrigin['host'])) {
                $reconstructed = $parsedOrigin['scheme'] . '://' . $parsedOrigin['host'];
                if (isset($parsedOrigin['port'])) {
                    $reconstructed .= ':' . $parsedOrigin['port'];
                }

                if (in_array($reconstructed, $allowedOrigins, true)) {
                    header("Access-Control-Allow-Origin: $reconstructed");
                    header('Vary: Origin');
                    header('Access-Control-Allow-Credentials: true');
                } else {
                    Logger::security('CORS origin blocked', [
                        'origin' => $origin,
                        'allowed' => $allowedOrigins
                    ]);
                    // Don't set CORS headers for disallowed origins
                }
            }
        }

        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Request-ID');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Max-Age: 86400');

        if (!Config::isDevelopment()) {
            $csp = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self'; connect-src 'self'; frame-src 'none'; object-src 'none';";
            header("Content-Security-Policy: $csp");
        }
    }
}
