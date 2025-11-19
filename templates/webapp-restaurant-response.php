<?php
/**
 * Web App Restaurant Match Response Template
 * Mobile-optimized template for restaurant booking matches
 *
 * Available variables:
 * - $results: Array of booking match results
 * - $booking_count: Number of bookings
 * - $critical_count: Number of critical issues
 * - $warning_count: Number of warnings
 * - $context: 'webapp-restaurant'
 */

// For now, reuse the Chrome sidepanel template
// The webapp CSS will handle mobile optimization
include BMA_PLUGIN_DIR . 'templates/chrome-sidepanel-response.php';
