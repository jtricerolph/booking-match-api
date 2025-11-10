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

// Variables available: $booking (array), $checks (array of check results)
?>
<div class="bma-checks-tab">
    <!-- Booking Summary Section (matching Restaurant tab) -->
    <div class="bma-booking-summary">
        <!-- Guest Name (Bold, Prominent) -->
        <div class="bma-guest-name">
            <strong><?php echo esc_html($booking['guest_name']); ?></strong>
        </div>

        <!-- Compact Details Section -->
        <div class="bma-compact-details">
            <div class="bma-compact-row">
                <span>Booking ID: #<?php echo esc_html($booking['booking_id']); ?></span>
                <span class="bma-compact-occupants">
                    <?php
                    $occ = $booking['occupants'] ?? array('adults' => 0, 'children' => 0, 'infants' => 0);
                    $parts = array();
                    if ($occ['adults'] > 0) $parts[] = $occ['adults'] . ' Adult' . ($occ['adults'] > 1 ? 's' : '');
                    if ($occ['children'] > 0) $parts[] = $occ['children'] . ' Child' . ($occ['children'] > 1 ? 'ren' : '');
                    if ($occ['infants'] > 0) $parts[] = $occ['infants'] . ' Infant' . ($occ['infants'] > 1 ? 's' : '');
                    echo esc_html(implode(', ', $parts));
                    ?>
                </span>
            </div>
            <div class="bma-compact-row">
                <span>Dates: <?php echo esc_html(date('D d/m', strtotime($booking['arrival']))); ?> - <?php echo esc_html(date('D d/m', strtotime($booking['departure']))); ?></span>
                <span class="bma-nights-badge"><?php echo esc_html($booking['total_nights']); ?> night<?php echo $booking['total_nights'] > 1 ? 's' : ''; ?></span>
            </div>
            <div class="bma-compact-row">
                <span>Tariff: <?php
                    $tariffs = $booking['tariffs'] ?? array();
                    echo esc_html(empty($tariffs) ? 'Standard' : implode(', ', $tariffs));
                ?></span>
            </div>
            <div class="bma-compact-row">
                <span>Status: <?php echo esc_html(ucfirst($booking['booking_status'] ?? 'unknown')); ?></span>
                <span><?php echo esc_html($booking['booking_source'] ?? 'Direct'); ?></span>
            </div>
        </div>

        <!-- Action Button -->
        <div class="bma-top-actions">
            <button class="open-booking-btn" data-booking-id="<?php echo esc_attr($booking['booking_id']); ?>">
                <span class="material-symbols-outlined">arrow_back</span>
                Open Booking in NewBook
            </button>
        </div>
    </div>

    <!-- Checks & Issues Section -->
    <div class="bma-nights">
        <h4>
            <span class="material-symbols-outlined">check_circle</span>
            Checks & Issues
        </h4>

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
    </div> <!-- Close bma-nights -->
</div> <!-- Close bma-checks-tab -->

<style>
/* Checks Tab Styles (matching Restaurant tab) */
.bma-checks-tab {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 14px;
    line-height: 1.5;
    color: #333;
    padding: 16px;
    background: #fff;
}

/* Booking Summary Section (from Restaurant tab) */
.bma-booking-summary {
    margin-bottom: 20px;
}

/* Guest Name - Bold and Prominent */
.bma-guest-name {
    font-size: 14px;
    color: #2d3748;
    margin-bottom: 12px;
}

.bma-guest-name strong {
    font-weight: 600;
}

/* Nights Badge on Dates Row */
.bma-nights-badge {
    background: #edf2f7;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}

/* Compact Details Section */
.bma-compact-details {
    margin-bottom: 16px;
}

.bma-compact-row {
    display: flex;
    justify-content: space-between;
    padding: 3px 0;
    font-size: 12px;
    color: #4a5568;
}

.bma-compact-occupants {
    font-weight: 500;
    color: #2d3748;
}

/* Top Action Button */
.bma-top-actions {
    margin-top: 16px;
}

.open-booking-btn {
    width: 100%;
    padding: 10px;
    background: #3182ce;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.open-booking-btn:hover {
    background: #2c5aa0;
}

.open-booking-btn .material-symbols-outlined {
    font-size: 18px;
}

/* Checks & Issues Section (styled like Restaurant Nights) */
.bma-nights {
    margin-top: 24px;
}

.bma-nights h4 {
    margin: 0 0 16px 0;
    font-size: 16px;
    font-weight: 600;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.bma-nights h4 .material-symbols-outlined {
    font-size: 20px;
}

.bma-no-issues {
    text-align: center;
    padding: 40px 20px;
}

.bma-muted {
    color: #6b7280;
    font-size: 13px;
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
    margin-top: 24px;
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
