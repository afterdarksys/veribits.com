<?php
namespace VeriBits\Utils;

class Request {
    /**
     * Cache for the request body
     * php://input can only be read ONCE per request, so we MUST cache it
     */
    private static ?string $cachedBody = null;
    private static bool $cacheInitialized = false;

    /**
     * Get the raw request body
     *
     * IMPORTANT: php://input can only be read once per request!
     * We cache it in a static variable to allow multiple reads.
     */
    public static function getBody(): string {
        // Return cached body if already initialized
        if (self::$cacheInitialized) {
            return self::$cachedBody ?? '';
        }

        $body = '';

        // PRIORITY 1: Check global cache set in index.php
        // This is set at the very start of the script before Apache can consume it
        if (isset($GLOBALS['__RAW_POST_BODY__'])) {
            $body = $GLOBALS['__RAW_POST_BODY__'];
        }
        // FALLBACK: Try to read from php://input (skip for multipart/form-data)
        else if (!isset($_SERVER['CONTENT_TYPE']) || strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') === false) {
            // Read with error suppression to handle edge cases
            $body = @file_get_contents('php://input');

            // Check if file_get_contents returned false (failure)
            if ($body === false) {
                $body = '';
            }

            // AWS ECS/ALB edge case: check if body is empty but content-length indicates data
            if (empty($body) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
                // Try to open php://input as a stream
                $stream = @fopen('php://input', 'r');
                if ($stream !== false) {
                    $contents = @stream_get_contents($stream);
                    @fclose($stream);
                    if ($contents !== false && $contents !== '') {
                        $body = $contents;
                    }
                }
            }
        }

        // If empty, try to build from $_POST (for form-encoded data)
        if (empty($body) && !empty($_POST)) {
            $body = json_encode($_POST);
        }

        // Cache the result for subsequent calls - ensure it's always a string
        self::$cachedBody = $body;
        self::$cacheInitialized = true;

        return self::$cachedBody;
    }

    /**
     * Get request body as JSON array
     */
    public static function getJsonBody(): array {
        $body = self::getBody();
        if (empty($body)) {
            return [];
        }

        // Strip BOM and whitespace that might interfere with JSON parsing
        $body = ltrim($body, "\xEF\xBB\xBF"); // Remove UTF-8 BOM
        $body = trim($body); // Remove leading/trailing whitespace

        $decoded = json_decode($body, true);

        // Log JSON decode errors for debugging
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            Logger::error('JSON decode failed in Request::getJsonBody', [
                'error' => json_last_error_msg(),
                'error_code' => json_last_error(),
                'body_length' => strlen($body),
                'body_preview' => substr($body, 0, 200),
                'body_hex' => bin2hex(substr($body, 0, 50))
            ]);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Get a specific parameter from the request body
     */
    public static function get(string $key, $default = null) {
        $body = self::getJsonBody();
        return $body[$key] ?? $default;
    }

    /**
     * Check if a key exists in the request body
     */
    public static function has(string $key): bool {
        $body = self::getJsonBody();
        return isset($body[$key]);
    }
}
