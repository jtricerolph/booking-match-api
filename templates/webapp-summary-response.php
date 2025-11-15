<?php
/**
 * Web App Summary Response Template
 * Mobile-optimized template for booking summary
 *
 * Available variables:
 * - $bookings: Array of summary bookings
 * - $context: 'webapp-summary'
 */

// For now, reuse the Chrome summary template
// The webapp CSS will handle mobile optimization
include BMA_PLUGIN_DIR . 'templates/chrome-summary-response.php';
