<?php
/**
 * Chrome Checks Response Template
 *
 * HTML template for chrome-checks context (Checks & Issues tab)
 * Displays guest request checks vs room features
 *
 * @package BookingMatchAPI
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Variables available: $booking_id, $checks (array of check results)
?>
<div class="bma-checks-tab">
    <div class="bma-checks-header">
        <h3>Checks & Issues</h3>
        <div class="bma-checks-subtitle">Booking #<?php echo esc_html($booking_id); ?></div>
    </div>

    <?php
    // Calculate total issues count
    $total_issues = 0;
    $total_issues += !empty($checks['twin_bed_request']) ? 1 : 0;
    $total_issues += !empty($checks['sofa_bed_request']) ? 1 : 0;
    $total_issues += !empty($checks['special_requests']) ? count($checks['special_requests']) : 0;
    $total_issues += !empty($checks['room_features_mismatch']) ? count($checks['room_features_mismatch']) : 0;
    ?>

    <?php if ($total_issues === 0): ?>
        <div class="bma-no-issues">
            <div class="bma-icon-large">✓</div>
            <p>No Issues Found</p>
            <div class="bma-muted">All checks passed for this booking</div>
        </div>
    <?php else: ?>
        <div class="bma-checks-list">
            <?php if (!empty($checks['twin_bed_request'])): ?>
                <div class="bma-check-item issue">
                    <div class="bma-check-icon">⚠</div>
                    <div class="bma-check-content">
                        <div class="bma-check-title">Twin Bed Request</div>
                        <div class="bma-check-description">Guest requested twin beds - verify room configuration</div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($checks['sofa_bed_request'])): ?>
                <div class="bma-check-item issue">
                    <div class="bma-check-icon">⚠</div>
                    <div class="bma-check-content">
                        <div class="bma-check-title">Sofa Bed Request</div>
                        <div class="bma-check-description">Guest requested sofa bed - verify room has sofa bed available</div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($checks['room_features_mismatch'])): ?>
                <?php foreach ($checks['room_features_mismatch'] as $mismatch): ?>
                    <div class="bma-check-item issue">
                        <div class="bma-check-icon">⚠</div>
                        <div class="bma-check-content">
                            <div class="bma-check-title">Room Feature Mismatch</div>
                            <div class="bma-check-description"><?php echo esc_html($mismatch); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($checks['special_requests'])): ?>
                <?php foreach ($checks['special_requests'] as $request): ?>
                    <div class="bma-check-item info">
                        <div class="bma-check-icon">ℹ</div>
                        <div class="bma-check-content">
                            <div class="bma-check-title">Special Request</div>
                            <div class="bma-check-description"><?php echo esc_html($request); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="bma-checks-placeholder">
        <div class="bma-placeholder-content">
            <div class="bma-placeholder-icon">🚧</div>
            <div class="bma-placeholder-title">Coming Soon</div>
            <div class="bma-placeholder-text">
                Automated checks for twin bed requests, sofa bed requests, and special request matching are currently in development.
            </div>
        </div>
    </div>
</div>

<style>
/* Checks Tab Styles */
.bma-checks-tab {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 14px;
    line-height: 1.5;
    color: #333;
    padding: 20px;
    background: #fff;
}

.bma-checks-header {
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 2px solid #e5e7eb;
}

.bma-checks-header h3 {
    margin: 0 0 4px 0;
    font-size: 20px;
    font-weight: 600;
    color: #111827;
}

.bma-checks-subtitle {
    font-size: 13px;
    color: #6b7280;
}

.bma-no-issues {
    text-align: center;
    padding: 40px 20px;
}

.bma-icon-large {
    font-size: 64px;
    margin-bottom: 16px;
    color: #10b981;
}

.bma-no-issues p {
    font-size: 16px;
    font-weight: 500;
    color: #374151;
    margin: 0 0 8px 0;
}

.bma-muted {
    font-size: 13px;
    color: #9ca3af;
}

.bma-checks-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 24px;
}

.bma-check-item {
    display: flex;
    gap: 12px;
    padding: 14px;
    border-radius: 6px;
    border-left: 4px solid;
}

.bma-check-item.issue {
    background: #fffbeb;
    border-left-color: #f59e0b;
}

.bma-check-item.info {
    background: #eff6ff;
    border-left-color: #3b82f6;
}

.bma-check-icon {
    font-size: 24px;
    line-height: 1;
}

.bma-check-content {
    flex: 1;
}

.bma-check-title {
    font-weight: 600;
    color: #111827;
    margin-bottom: 4px;
    font-size: 14px;
}

.bma-check-description {
    font-size: 13px;
    color: #4b5563;
}

.bma-checks-placeholder {
    margin-top: 32px;
    padding: 24px;
    background: #f9fafb;
    border: 2px dashed #d1d5db;
    border-radius: 8px;
}

.bma-placeholder-content {
    text-align: center;
}

.bma-placeholder-icon {
    font-size: 48px;
    margin-bottom: 12px;
}

.bma-placeholder-title {
    font-size: 16px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
}

.bma-placeholder-text {
    font-size: 13px;
    color: #6b7280;
    line-height: 1.6;
    max-width: 400px;
    margin: 0 auto;
}
</style>
