<?php
/**
 * Chrome Sidepanel Response Template
 *
 * HTML template for chrome-sidepanel context (wider layout for sidebar)
 *
 * @package BookingMatchAPI
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Variables available: $results, $search_method, $booking_count
?>
<div class="bma-sidepanel-result" data-search-method="<?php echo esc_attr($search_method); ?>">

    <?php if ($booking_count === 0): ?>
        <!-- No bookings found -->
        <div class="bma-no-results">
            <div class="bma-icon">❌</div>
            <h3>No Bookings Found</h3>
            <p>No bookings match your search criteria.</p>
        </div>

    <?php elseif ($booking_count === 1): ?>
        <!-- Single booking found -->
        <?php
        $booking = $results[0];
        $matched_nights = array_filter($booking['nights'], function($n) { return !empty($n['resos_matches']); });
        $unmatched_nights = array_filter($booking['nights'], function($n) { return empty($n['resos_matches']); });

        // Detect overall booking status for header icon
        $overall_has_package_alert = false;
        $overall_has_warnings = false;

        foreach ($booking['nights'] as $night) {
            $has_matches = !empty($night['resos_matches']);
            $match_count = $has_matches ? count($night['resos_matches']) : 0;
            $has_package = isset($night['has_package']) && $night['has_package'];

            // Check for package booking without restaurant reservation (CRITICAL)
            if ($has_package && !$has_matches) {
                $overall_has_package_alert = true;
                break; // Package alert is most critical
            }
            // Check for warnings: multiple matches or non-primary match
            elseif ($has_matches) {
                $primary_match = isset($night['resos_matches'][0]) ? $night['resos_matches'][0] : null;
                $is_primary = $primary_match && isset($primary_match['match_info']['is_primary']) && $primary_match['match_info']['is_primary'];
                if ($match_count > 1 || !$is_primary) {
                    $overall_has_warnings = true;
                }
            }
        }

        // Determine header icon and text based on overall status
        $header_icon = '✓'; // Default success
        $header_text = 'Hotel Booking';

        if ($overall_has_package_alert) {
            $header_icon = '🍽️'; // Critical package alert
            $header_text = 'Package Booking - Action Required';
        } elseif ($overall_has_warnings) {
            $header_icon = '⚠'; // Warning
            $header_text = 'Hotel Booking - Review Matches';
        }
        ?>

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
        </div>

        <div class="bma-nights">
            <h4>Restaurant Bookings by Night</h4>

            <?php foreach ($booking['nights'] as $night): ?>
                <?php
                $has_matches = !empty($night['resos_matches']);
                $match_count = $has_matches ? count($night['resos_matches']) : 0;
                $has_package = isset($night['has_package']) && $night['has_package'];
                $has_warnings = false;
                $has_package_alert = false;

                // Check for package booking without restaurant reservation (CRITICAL - RED)
                if ($has_package && !$has_matches) {
                    $has_package_alert = true;
                }
                // Check for other warnings: multiple matches or non-primary match (WARNING - AMBER)
                elseif ($has_matches) {
                    $primary_match = isset($night['resos_matches'][0]) ? $night['resos_matches'][0] : null;
                    $is_primary = $primary_match && isset($primary_match['match_info']['is_primary']) && $primary_match['match_info']['is_primary'];
                    $has_warnings = $match_count > 1 || !$is_primary;
                }
                ?>

                <div class="bma-night <?php echo $has_matches ? 'matched' : 'unmatched'; ?> <?php echo $has_warnings ? 'has-warnings' : ''; ?> <?php echo $has_package_alert ? 'has-package-alert' : ''; ?>">
                    <div class="bma-night-header">
                        <div class="bma-night-date">
                            <?php echo esc_html(date('l, d/m/y', strtotime($night['date']))); ?>
                        </div>
                        <?php if ($has_package_alert): ?>
                            <span class="bma-package-alert-badge">🍽️ Package</span>
                        <?php elseif ($has_warnings): ?>
                            <span class="bma-warning-badge">⚠ Review</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($has_matches): ?>
                        <?php if ($match_count > 1): ?>
                            <div class="bma-night-status matched multiple">
                                <span class="bma-status-icon">✓</span>
                                <span><?php echo $match_count; ?> restaurant bookings found - review matches below</span>
                            </div>
                        <?php else: ?>
                            <?php $match = $night['resos_matches'][0]; ?>
                            <div class="bma-night-status matched">
                                <span class="bma-status-icon">✓</span>
                                <span>Restaurant booking found</span>
                                <?php if ($match['match_info']['is_primary']): ?>
                                    <span class="bma-badge primary">Primary Match</span>
                                <?php else: ?>
                                    <span class="bma-badge suggested"><?php echo esc_html(ucfirst($match['match_info']['confidence'])); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($night['resos_matches'] as $index => $match): ?>
                            <div class="bma-match-item <?php echo $index === 0 ? 'primary' : 'secondary'; ?>">
                                <?php if ($match_count > 1): ?>
                                    <div class="bma-match-header">
                                        <strong>Match <?php echo $index + 1; ?></strong>
                                        <?php if ($match['match_info']['is_primary']): ?>
                                            <span class="bma-badge-small primary">Primary</span>
                                        <?php else: ?>
                                            <span class="bma-badge-small suggested"><?php echo esc_html(ucfirst($match['match_info']['confidence'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="bma-match-details">
                                    <div class="bma-match-row">
                                        <span class="bma-match-label">Guest:</span>
                                        <span><?php echo esc_html($match['guest_name']); ?></span>
                                    </div>
                                    <div class="bma-match-row">
                                        <span class="bma-match-label">Time:</span>
                                        <span><?php echo esc_html($match['time'] ?? 'Not specified'); ?></span>
                                    </div>
                                    <div class="bma-match-row">
                                        <span class="bma-match-label">People:</span>
                                        <span><?php echo esc_html($match['people']); ?></span>
                                    </div>
                                    <?php if (!empty($match['is_hotel_guest'])): ?>
                                        <div class="bma-match-row">
                                            <span class="bma-badge-small hotel-guest">Hotel Guest</span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($match['is_dbb'])): ?>
                                        <div class="bma-match-row">
                                            <span class="bma-badge-small dbb">DBB</span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="bma-match-actions">
                                    <a href="<?php echo esc_url($this->generate_deep_link($booking['booking_id'], $night['date'], $match['resos_booking_id'])); ?>"
                                       class="bma-action-link" target="_blank">
                                        <?php if ($match['match_info']['is_primary']): ?>
                                            View Match
                                        <?php else: ?>
                                            Check Match
                                        <?php endif; ?>
                                    </a>

                                    <?php if ($match['match_info']['is_primary'] && !empty($match['resos_booking_id']) && !empty($match['restaurant_id'])): ?>
                                        <a href="https://app.resos.com/<?php echo esc_attr($match['restaurant_id']); ?>/bookings/timetable/<?php echo esc_attr($night['date']); ?>/<?php echo esc_attr($match['resos_booking_id']); ?>"
                                           class="bma-action-link bma-resos-link" target="_blank">
                                            View in ResOS
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                    <?php else: ?>
                        <?php if ($has_package_alert): ?>
                            <div class="bma-night-status unmatched package-alert">
                                <span class="bma-status-icon">🍽️</span>
                                <span><strong>PACKAGE BOOKING - No Restaurant Reservation</strong></span>
                            </div>
                            <div class="bma-package-alert-message">
                                This guest has a dinner package but no restaurant booking yet.
                            </div>
                            <a href="<?php echo esc_url($this->generate_deep_link($booking['booking_id'], $night['date'])); ?>"
                               class="bma-action-link create urgent" target="_blank">
                                Create Booking Now
                            </a>
                        <?php else: ?>
                            <div class="bma-night-status unmatched">
                                <span class="bma-status-icon">⚠</span>
                                <span>No restaurant booking</span>
                            </div>
                            <a href="<?php echo esc_url($this->generate_deep_link($booking['booking_id'], $night['date'])); ?>"
                               class="bma-action-link create" target="_blank">
                                Create Booking
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

    <?php else: ?>
        <!-- Multiple bookings found -->
        <div class="bma-multiple-results">
            <div class="bma-icon">📋</div>
            <h3>Multiple Bookings Found (<?php echo esc_html($booking_count); ?>)</h3>
            <p>Please select the correct booking:</p>

            <div class="bma-booking-list">
                <?php foreach ($results as $booking): ?>
                    <div class="bma-booking-item" data-booking-id="<?php echo esc_attr($booking['booking_id']); ?>">
                        <div class="bma-booking-header">
                            <strong><?php echo esc_html($booking['guest_name']); ?></strong>
                            <span class="bma-booking-id">#<?php echo esc_html($booking['booking_id']); ?></span>
                        </div>
                        <div class="bma-booking-details">
                            <span>Room <?php echo esc_html($booking['room']); ?></span>
                            <span>
                                <?php echo esc_html(date('d/m/y', strtotime($booking['arrival']))); ?>
                                -
                                <?php echo esc_html(date('d/m/y', strtotime($booking['departure']))); ?>
                            </span>
                            <?php if (!empty($booking['booking_reference'])): ?>
                                <span class="bma-ref">Ref: <?php echo esc_html($booking['booking_reference']); ?></span>
                            <?php endif; ?>
                        </div>
                        <a href="<?php echo esc_url($this->generate_deep_link($booking['booking_id'])); ?>"
                           class="bma-select-btn" target="_blank">
                            Select This Booking
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<style>
/* Sidepanel-specific styles - wider layout optimized for sidebar (600px+) */
.bma-sidepanel-result {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 14px;
    line-height: 1.5;
    color: #333;
    background: #fff;
    max-width: 100%;
}

.bma-icon {
    font-size: 48px;
    text-align: center;
    margin-bottom: 15px;
}

.bma-icon-small {
    font-size: 18px;
    color: #10b981;
    margin-right: 8px;
}

.bma-booking-summary, .bma-no-results, .bma-multiple-results {
    margin-bottom: 20px;
}

.bma-booking-summary h3, .bma-no-results h3, .bma-multiple-results h3 {
    margin: 0 0 15px 0;
    font-size: 20px;
    font-weight: 600;
    color: #10b981;
}

.bma-no-results h3 {
    color: #ef4444;
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

.bma-muted {
    color: #6b7280;
    font-size: 13px;
}

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
}

.bma-night {
    background: #f9fafb;
    border-left: 4px solid #d1d5db;
    padding: 16px;
    margin-bottom: 16px;
    border-radius: 6px;
}

.bma-night.matched {
    border-left-color: #10b981;
    background: #f0fdf4;
}

.bma-night.unmatched {
    border-left-color: #60a5fa;
    background: #eff6ff;
}

.bma-night.has-warnings {
    border-left-color: #f59e0b;
    background: #fffbeb;
}

.bma-night.has-package-alert {
    border-left-color: #ef4444;
    background: #fee2e2;
}

.bma-night-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.bma-night-date {
    font-weight: 600;
    color: #111827;
    font-size: 15px;
}

.bma-warning-badge, .bma-package-alert-badge {
    font-size: 13px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 12px;
}

.bma-warning-badge {
    background: #fef3c7;
    color: #92400e;
}

.bma-package-alert-badge {
    background: #fecaca;
    color: #991b1b;
}

.bma-package-alert-message {
    padding: 10px 12px;
    margin-bottom: 12px;
    background: #fecaca;
    border-radius: 4px;
    font-size: 13px;
    color: #991b1b;
    font-weight: 500;
}

.bma-night-status {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    font-size: 14px;
}

.bma-night-status.package-alert {
    color: #991b1b;
    font-weight: 600;
}

.bma-status-icon {
    font-size: 18px;
}

.bma-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    margin-left: auto;
}

.bma-badge.primary {
    background: #10b981;
    color: white;
}

.bma-badge.suggested {
    background: #f59e0b;
    color: white;
}

.bma-match-details {
    display: grid;
    grid-template-columns: 80px 1fr;
    gap: 8px;
    margin-bottom: 12px;
    font-size: 13px;
}

.bma-match-row {
    display: contents;
}

.bma-match-label {
    color: #6b7280;
    font-weight: 500;
}

.bma-match-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 12px;
}

.bma-action-link {
    display: inline-block;
    padding: 8px 16px;
    background: #667eea;
    color: white !important;
    text-decoration: none;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    transition: background 0.2s;
}

.bma-action-link:hover {
    background: #5568d3;
}

.bma-action-link.bma-resos-link {
    background: #10b981;
}

.bma-action-link.bma-resos-link:hover {
    background: #059669;
}

.bma-action-link.create {
    background: #10b981;
}

.bma-action-link.create:hover {
    background: #059669;
}

.bma-action-link.create.urgent {
    background: #ef4444;
    font-weight: 600;
}

.bma-action-link.create.urgent:hover {
    background: #dc2626;
}

.bma-booking-list {
    margin-top: 20px;
}

.bma-booking-item {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    padding: 16px;
    margin-bottom: 12px;
    border-radius: 6px;
}

.bma-booking-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.bma-booking-id {
    color: #6b7280;
    font-size: 14px;
}

.bma-booking-details {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    font-size: 14px;
    color: #6b7280;
    margin-bottom: 12px;
}

.bma-ref {
    color: #667eea;
    font-weight: 500;
}

.bma-select-btn {
    display: inline-block;
    padding: 10px 20px;
    background: #667eea;
    color: white !important;
    text-decoration: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    transition: background 0.2s;
    cursor: pointer;
}

.bma-select-btn:hover {
    background: #5568d3;
}

.bma-match-item {
    background: #fff;
    border: 1px solid #e5e7eb;
    padding: 12px;
    margin-bottom: 10px;
    border-radius: 6px;
}

.bma-match-item.primary {
    border-color: #10b981;
    background: #f0fdf4;
}

.bma-match-item.secondary {
    background: #fef3c7;
    border-color: #f59e0b;
}

.bma-match-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e5e7eb;
    font-size: 13px;
}

.bma-badge-small {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.bma-badge-small.primary {
    background: #10b981;
    color: white;
}

.bma-badge-small.suggested {
    background: #f59e0b;
    color: white;
}

.bma-badge-small.hotel-guest {
    background: #667eea;
    color: white;
}

.bma-badge-small.dbb {
    background: #8b5cf6;
    color: white;
}
</style>

<!-- Load Material Symbols font for status icons -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
