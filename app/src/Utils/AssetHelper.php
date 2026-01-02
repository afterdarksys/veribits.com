<?php
/**
 * AssetHelper - Cache Busting Utility
 *
 * Automatically appends file modification timestamps to asset URLs
 * to force browser cache invalidation when files change.
 */

namespace App\Utils;

class AssetHelper
{
    /**
     * Generate cache-busted asset URL
     *
     * @param string $path Asset path relative to /assets (e.g., 'css/main.css' or '/assets/css/main.css')
     * @return string Asset URL with version timestamp
     */
    public static function asset(string $path): string
    {
        // Strip leading /assets if present
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'assets/')) {
            $path = substr($path, 7); // Remove 'assets/' prefix
        }

        // Construct full filesystem path
        $publicDir = $_SERVER['DOCUMENT_ROOT'] ?? '/var/www/html';
        $filePath = $publicDir . '/assets/' . $path;

        // Get file modification time (fallback to current time if file doesn't exist)
        $version = file_exists($filePath) ? filemtime($filePath) : time();

        // Return versioned URL
        return '/assets/' . $path . '?v=' . $version;
    }
}

/**
 * Global helper function for templates
 *
 * Usage in PHP templates:
 *   <link rel="stylesheet" href="<?= asset('css/main.css') ?>">
 *   <script src="<?= asset('js/main.js') ?>"></script>
 */
function asset(string $path): string
{
    return \App\Utils\AssetHelper::asset($path);
}
