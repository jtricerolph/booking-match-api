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
$booking_id = $booking['booking_id'];
$guest_name = $booking['guest_name'] ?? 'Unknown Guest';
$room_number = $booking['site_name'] ?? 'N/A';
$arrival_date = $booking['arrival_date'] ?? '';
$departure_date = $booking['departure_date'] ?? '';
$nights = $booking['nights'] ?? 0;
$occupants = $booking['occupants'] ?? ['adults' => 0, 'children' => 0, 'infants' => 0];
$tariffs = $booking['tariffs'] ?? [];
$status = $booking['status'] ?? 'Confirmed';
$booking_source = $booking['booking_source'] ?? 'Unknown';
?>
<div class="bma-checks-tab">
    <!-- Booking Summary Section -->
    <div class="booking-summary-section">
        <div class="booking-summary-header">
            <div class="booking-summary-title">
                <h3><?php echo esc_html($guest_name); ?></h3>
                <span class="booking-id">#<?php echo esc_html($booking_id); ?></span>
            </div>
            <span class="room-badge"><?php echo esc_html($room_number); ?></span>
        </div>

        <div class="booking-summary-details">
            <div class="detail-row">
                <span class="detail-label">Dates:</span>
                <span class="detail-value">
                    <?php echo esc_html(date('D d/m', strtotime($arrival_date))); ?> -
                    <?php echo esc_html(date('D d/m', strtotime($departure_date))); ?>
                    <span class="nights-badge"><?php echo esc_html($nights); ?> night<?php echo $nights > 1 ? 's' : ''; ?></span>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Guests:</span>
                <span class="detail-value">
                    <?php
                    $parts = array();
                    if ($occupants['adults'] > 0) $parts[] = $occupants['adults'] . ' Adult' . ($occupants['adults'] > 1 ? 's' : '');
                    if ($occupants['children'] > 0) $parts[] = $occupants['children'] . ' Child' . ($occupants['children'] > 1 ? 'ren' : '');
                    if ($occupants['infants'] > 0) $parts[] = $occupants['infants'] . ' Infant' . ($occupants['infants'] > 1 ? 's' : '');
                    echo esc_html(implode(', ', $parts));
                    ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Tariff:</span>
                <span class="detail-value"><?php echo esc_html(empty($tariffs) ? 'Standard' : implode(', ', $tariffs)); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Status:</span>
                <span class="detail-value"><?php echo esc_html(ucfirst($status)); ?> • <?php echo esc_html($booking_source); ?></span>
            </div>
        </div>

        <div class="booking-summary-actions">
            <button class="open-booking-btn" data-booking-id="<?php echo esc_attr($booking_id); ?>">
                <span class="material-symbols-outlined">arrow_back</span>
                Open Booking in NewBook
            </button>
        </div>
    </div>

    <!-- Checks & Issues Section -->
    <div class="checks-section-header">
        <h4>
            <span class="material-symbols-outlined">check_circle</span>
            Checks & Issues
        </h4>
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
    padding: 0;
    background: #fff;
}

/* Booking Summary Section */
.booking-summary-section {
    padding: 16px;
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
}

.booking-summary-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.booking-summary-title {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.booking-summary-title h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #111827;
}

.booking-id {
    font-size: 13px;
    color: #6b7280;
    font-weight: 400;
}

.room-badge {
    padding: 4px 10px;
    background: #3b82f6;
    color: white;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
}

.booking-summary-details {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 12px;
}

.detail-row {
    display: flex;
    gap: 8px;
    font-size: 13px;
}

.detail-label {
    color: #6b7280;
    min-width: 55px;
    font-weight: 500;
}

.detail-value {
    color: #111827;
    flex: 1;
}

.nights-badge {
    padding: 2px 6px;
    background: #e5e7eb;
    border-radius: 4px;
    font-size: 12px;
    color: #374151;
    margin-left: 6px;
}

.booking-summary-actions {
    margin-top: 12px;
}

.open-booking-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 10px 14px;
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    color: #374151;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.open-booking-btn:hover {
    background: #f9fafb;
    border-color: #9ca3af;
}

.open-booking-btn .material-symbols-outlined {
    font-size: 18px;
}

/* Checks Section Header */
.checks-section-header {
    padding: 16px 16px 12px 16px;
    border-bottom: 1px solid #e5e7eb;
}

.checks-section-header h4 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #111827;
}

.checks-section-header .material-symbols-outlined {
    font-size: 20px;
    color: #6b7280;
}

.bma-no-issues {
    text-align: center;
    padding: 40px 20px;
    margin: 16px;
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
    margin: 16px;
    margin-bottom: 8px;
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
    margin: 16px;
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
