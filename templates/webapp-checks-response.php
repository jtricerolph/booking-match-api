<?php
/**
 * Web App Checks Response Template
 * Mobile-optimized template for booking validation checks
 *
 * Available variables:
 * - $booking: Booking data
 * - $checks: Array of check results
 * - $context: 'webapp-checks'
 */

// For now, reuse the Chrome checks template
// The webapp CSS will handle mobile optimization
include BMA_PLUGIN_DIR . 'templates/chrome-checks-response.php';
