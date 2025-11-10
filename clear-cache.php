<?php
/**
 * Temporary script to clear OPcache and WordPress transients
 *
 * Upload this to your WordPress root directory and access it once via browser:
 * https://admin.hotelnumberfour.com/clear-cache.php
 *
 * DELETE THIS FILE AFTER USE for security!
 */

// Simple security - change this to a random string
define('CLEAR_CACHE_KEY', 'change-this-to-random-string-' . date('Ymd'));

if (!isset($_GET['key']) || $_GET['key'] !== CLEAR_CACHE_KEY) {
    die('Access denied. Set ?key=' . CLEAR_CACHE_KEY);
}

echo "<h1>Cache Clearing Script</h1>";
echo "<pre>";

// Clear OPcache if available
if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "✓ OPcache cleared successfully\n";
    } else {
        echo "✗ OPcache reset failed\n";
    }
} else {
    echo "⚠ OPcache not available or not enabled\n";
}

// Clear realpath cache
clearstatcache(true);
echo "✓ Stat cache cleared\n";

// Load WordPress
$wp_load = dirname(__FILE__) . '/wp-load.php';
if (file_exists($wp_load)) {
    require_once $wp_load;

    // Clear WordPress transients
    delete_transient('bma_opening_hours_all');
    delete_transient('bma_dietary_choices');

    echo "✓ WordPress transients cleared\n";

    // Flush WordPress object cache
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
        echo "✓ WordPress object cache flushed\n";
    }
} else {
    echo "⚠ WordPress not found, skipping transient clearing\n";
}

echo "\n<strong>All caches cleared!</strong>\n";
echo "\n<strong style='color: red;'>IMPORTANT: Delete this file now for security!</strong>\n";
echo "</pre>";

// Show PHP info for debugging
echo "<hr><h2>PHP Configuration</h2>";
echo "<pre>";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "OPcache Enabled: " . (ini_get('opcache.enable') ? 'Yes' : 'No') . "\n";
echo "OPcache CLI Enabled: " . (ini_get('opcache.enable_cli') ? 'Yes' : 'No') . "\n";
echo "</pre>";
