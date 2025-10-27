<?php
declare(strict_types=1);

namespace VeriBits\Exceptions;

use VeriBits\Utils\Logger;
use VeriBits\Utils\Response;

/**
 * Global exception handler for the application
 */
class ExceptionHandler {
    /**
     * Handle an uncaught exception
     */
    public static function handle(\Throwable $e): void {
        $context = [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => self::getCleanTrace($e)
        ];

        // Log based on exception type
        if ($e instanceof ValidationException) {
            Logger::info('Validation error', $context);
            Response::validationError($e->getErrors());
        } elseif ($e instanceof UnauthorizedException) {
            Logger::security('Unauthorized access attempt', $context);
            Response::error($e->getMessage(), 401);
        } elseif ($e instanceof ForbiddenException) {
            Logger::security('Forbidden access attempt', $context);
            Response::error($e->getMessage(), 403);
        } elseif ($e instanceof NotFoundException) {
            Logger::info('Resource not found', $context);
            Response::error($e->getMessage(), 404);
        } elseif ($e instanceof RateLimitException) {
            Logger::security('Rate limit exceeded', $context);
            Response::error($e->getMessage(), 429);
        } elseif ($e instanceof QuotaExceededException) {
            Logger::info('Quota exceeded', $context);
            Response::error($e->getMessage(), 402); // Payment Required
        } elseif ($e instanceof \InvalidArgumentException) {
            Logger::warning('Invalid argument', $context);
            Response::error($e->getMessage(), 400);
        } elseif ($e instanceof \PDOException) {
            Logger::error('Database error', $context);
            Response::error('Database error occurred', 500);
        } else {
            Logger::error('Uncaught exception', $context);
            Response::error('Internal server error', 500);
        }
    }

    /**
     * Handle a PHP error (convert to exception and handle)
     */
    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool {
        // Don't throw exception for suppressed errors (@)
        if (!(error_reporting() & $errno)) {
            return false;
        }

        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    /**
     * Handle fatal errors on shutdown
     */
    public static function handleShutdown(): void {
        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            Logger::critical('Fatal error', [
                'type' => $error['type'],
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line']
            ]);

            // Clean output buffer
            if (ob_get_level() > 0) {
                ob_clean();
            }

            Response::error('A fatal error occurred', 500);
        }
    }

    /**
     * Get a clean stack trace without sensitive information
     */
    private static function getCleanTrace(\Throwable $e): array {
        $trace = [];
        $maxTraceItems = 10;

        foreach ($e->getTrace() as $i => $item) {
            if ($i >= $maxTraceItems) {
                break;
            }

            $trace[] = [
                'file' => $item['file'] ?? 'unknown',
                'line' => $item['line'] ?? 0,
                'function' => $item['function'] ?? 'unknown',
                'class' => $item['class'] ?? null
            ];
        }

        return $trace;
    }

    /**
     * Register the exception handler
     */
    public static function register(): void {
        set_exception_handler([self::class, 'handle']);
        set_error_handler([self::class, 'handleError']);
        register_shutdown_function([self::class, 'handleShutdown']);

        Logger::debug('Exception handler registered');
    }
}
