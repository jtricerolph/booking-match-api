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
                                    <!-- View Comparison Button (primary action) -->
                                    <button class="bma-action-btn view-comparison"
                                            data-action="view-comparison"
                                            data-date="<?php echo esc_attr($night['date']); ?>"
                                            data-booking-id="<?php echo esc_attr($booking['booking_id']); ?>"
                                            data-resos-booking-id="<?php echo esc_attr($match['resos_booking_id']); ?>">
                                        <?php if ($match['match_info']['is_primary']): ?>
                                            <span class="material-symbols-outlined">bar_chart</span> View Match
                                        <?php else: ?>
                                            <span class="material-symbols-outlined">search</span> Check Match
                                        <?php endif; ?>
                                    </button>

                                    <?php if ($match['match_info']['is_primary'] && !empty($match['resos_booking_id']) && !empty($match['restaurant_id'])): ?>
                                        <a href="https://app.resos.com/<?php echo esc_attr($match['restaurant_id']); ?>/bookings/timetable/<?php echo esc_attr($night['date']); ?>/<?php echo esc_attr($match['resos_booking_id']); ?>"
                                           class="bma-action-link bma-resos-link" target="_blank">
                                            <span class="material-symbols-outlined">visibility</span> View in ResOS
                                        </a>
                                    <?php endif; ?>

                                    <!-- Exclude Match Button (for non-confirmed, non-matched-elsewhere matches) -->
                                    <?php
                                    // Don't show Exclude button for:
                                    // 1. Confirmed matches (booking_id match type)
                                    // 2. Matches that are matched to another booking (matched_elsewhere)
                                    $is_confirmed = isset($match['match_info']['match_type']) && $match['match_info']['match_type'] === 'booking_id';
                                    $is_matched_elsewhere = isset($match['match_info']['matched_elsewhere']) && $match['match_info']['matched_elsewhere'];
                                    if (!$is_confirmed && !$is_matched_elsewhere):
                                    ?>
                                        <button class="bma-action-btn exclude"
                                                data-action="exclude-match"
                                                data-resos-booking-id="<?php echo esc_attr($match['resos_booking_id']); ?>"
                                                data-hotel-booking-id="<?php echo esc_attr($booking['booking_id']); ?>"
                                                data-guest-name="<?php echo esc_attr($match['guest_name']); ?>">
                                            <span class="material-symbols-outlined">close</span> Exclude
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <!-- Comparison View Container (Initially Hidden) -->
                                <div id="comparison-<?php echo esc_attr($night['date']); ?>-<?php echo esc_attr($match['resos_booking_id']); ?>"
                                     class="bma-comparison-container"
                                     style="display:none;">
                                    <div class="bma-comparison-loading">Loading comparison...</div>
                                </div>

                                <!-- Update Booking Form (Initially Hidden) -->
                                <div id="update-form-<?php echo esc_attr($night['date']); ?>-<?php echo esc_attr($match['resos_booking_id']); ?>" class="bma-booking-form" style="display:none;"
                                     data-resos-booking-id="<?php echo esc_attr($match['resos_booking_id']); ?>"
                                     data-guest-name="<?php echo esc_attr($match['guest_name']); ?>"
                                     data-time="<?php echo esc_attr($match['time'] ?? ''); ?>"
                                     data-people="<?php echo esc_attr($match['people']); ?>"
                                     data-guest-phone="<?php echo esc_attr($booking['phone'] ?? ''); ?>"
                                     data-guest-email="<?php echo esc_attr($booking['email'] ?? ''); ?>">

                                    <h5>Update Restaurant Booking</h5>

                                    <div class="bma-form-row">
                                        <label>Guest Name *</label>
                                        <input type="text" class="update-guest-name" value="<?php echo esc_attr($match['guest_name']); ?>" required>
                                    </div>

                                    <div class="bma-form-row">
                                        <label>Time</label>
                                        <input type="time" class="update-time" value="<?php echo esc_attr($match['time'] ?? ''); ?>">
                                    </div>

                                    <div class="bma-form-row">
                                        <label>People</label>
                                        <input type="number" class="update-people" min="1" value="<?php echo esc_attr($match['people']); ?>">
                                    </div>

                                    <div class="bma-form-row">
                                        <label>Phone</label>
                                        <input type="tel" class="update-phone" value="<?php echo esc_attr($booking['phone'] ?? ''); ?>">
                                    </div>

                                    <div class="bma-form-row">
                                        <label>Email</label>
                                        <input type="email" class="update-email" value="<?php echo esc_attr($booking['email'] ?? ''); ?>">
                                    </div>

                                    <div class="bma-form-row">
                                        <label>
                                            <input type="checkbox" class="update-hotel-guest" <?php echo !empty($match['is_hotel_guest']) ? 'checked' : ''; ?>>
                                            Mark as Hotel Guest
                                        </label>
                                    </div>

                                    <div class="bma-form-row">
                                        <label>
                                            <input type="checkbox" class="update-dbb" <?php echo !empty($match['is_dbb']) ? 'checked' : ''; ?>>
                                            Mark as DBB/Package
                                        </label>
                                    </div>

                                    <div class="bma-form-actions">
                                        <button type="button" class="bma-btn-cancel"
                                                data-action="toggle-update"
                                                data-date="<?php echo esc_attr($night['date']); ?>"
                                                data-resos-booking-id="<?php echo esc_attr($match['resos_booking_id']); ?>">Cancel</button>
                                        <button type="button" class="bma-btn-submit"
                                                data-action="submit-update"
                                                data-date="<?php echo esc_attr($night['date']); ?>"
                                                data-resos-booking-id="<?php echo esc_attr($match['resos_booking_id']); ?>">Update Booking</button>
                                    </div>

                                    <div class="bma-form-feedback"></div>
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
                        <?php else: ?>
                            <div class="bma-night-status unmatched">
                                <span class="bma-status-icon">⚠</span>
                                <span>No restaurant booking</span>
                            </div>
                        <?php endif; ?>

                        <!-- Create Booking Button -->
                        <button class="bma-action-btn create <?php echo $has_package_alert ? 'urgent' : ''; ?>"
                                data-action="toggle-create"
                                data-date="<?php echo esc_attr($night['date']); ?>">
                            <?php echo $has_package_alert ? '+ Create Booking Now' : '+ Create Booking'; ?>
                        </button>

                        <!-- Create Booking Form (Initially Hidden) -->
                        <div id="create-form-<?php echo esc_attr($night['date']); ?>" class="bma-booking-form" style="display:none;"
                             data-date="<?php echo esc_attr($night['date']); ?>"
                             data-guest-name="<?php echo esc_attr($booking['guest_name']); ?>"
                             data-guest-phone="<?php echo esc_attr($booking['phone'] ?? ''); ?>"
                             data-guest-email="<?php echo esc_attr($booking['email'] ?? ''); ?>"
                             data-booking-id="<?php echo esc_attr($booking['booking_id']); ?>"
                             data-people="<?php
                                 $occ = $booking['occupants'] ?? array('adults' => 0, 'children' => 0, 'infants' => 0);
                                 echo esc_attr($occ['adults'] + $occ['children'] + $occ['infants']);
                             ?>">

                            <h5>Create Restaurant Booking</h5>

                            <div class="bma-form-row">
                                <label>Date</label>
                                <input type="text" class="form-date" value="<?php echo esc_attr($night['date']); ?>" readonly>
                            </div>

                            <div class="bma-form-row">
                                <label>Guest Name *</label>
                                <input type="text" class="form-guest-name" value="<?php echo esc_attr($booking['guest_name']); ?>" required>
                            </div>

                            <div class="bma-form-row">
                                <label>Time *</label>
                                <input type="time" class="form-time" value="19:00" required>
                            </div>

                            <div class="bma-form-row">
                                <label>People *</label>
                                <input type="number" class="form-people" min="1" value="<?php
                                    $occ = $booking['occupants'] ?? array('adults' => 0, 'children' => 0, 'infants' => 0);
                                    echo esc_attr($occ['adults'] + $occ['children'] + $occ['infants']);
                                ?>" required>
                            </div>

                            <div class="bma-form-row">
                                <label>Phone</label>
                                <input type="tel" class="form-phone" value="<?php echo esc_attr($booking['phone'] ?? ''); ?>">
                            </div>

                            <div class="bma-form-row">
                                <label>Email</label>
                                <input type="email" class="form-email" value="<?php echo esc_attr($booking['email'] ?? ''); ?>">
                            </div>

                            <div class="bma-form-row">
                                <label>Notes</label>
                                <textarea class="form-notes" rows="2" placeholder="e.g., Room <?php echo esc_attr($booking['room'] ?? ''); ?>, Booking #<?php echo esc_attr($booking['booking_id']); ?>"></textarea>
                            </div>

                            <div class="bma-form-actions">
                                <button type="button" class="bma-btn-cancel"
                                        data-action="toggle-create"
                                        data-date="<?php echo esc_attr($night['date']); ?>">Cancel</button>
                                <button type="button" class="bma-btn-submit"
                                        data-action="submit-create"
                                        data-date="<?php echo esc_attr($night['date']); ?>">Create Booking</button>
                            </div>

                            <div class="bma-form-feedback"></div>
                        </div>
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
    border-top: 4px solid #d1d5db;
    padding: 12px 4px;
    margin-bottom: 16px;
    border-radius: 6px;
}

.bma-night.matched {
    border-top-color: #10b981;
    background: #f0fdf4;
}

.bma-night.unmatched {
    border-top-color: #60a5fa;
    background: #eff6ff;
}

.bma-night.has-warnings {
    border-top-color: #f59e0b;
    background: #fffbeb;
}

.bma-night.has-package-alert {
    border-top-color: #ef4444;
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
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 16px;
    min-height: 36px;
    background: #667eea;
    color: white !important;
    text-decoration: none;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    line-height: 1.5;
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
    padding: 12px 6px;
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

/* Action Buttons */
.bma-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 16px;
    min-height: 36px;
    background: #667eea;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    line-height: 1.5;
    cursor: pointer;
    transition: background 0.2s;
    margin-right: 8px;
    margin-top: 8px;
}

.bma-action-btn:hover {
    background: #5568d3;
}

.bma-action-btn.create {
    background: #10b981;
}

.bma-action-btn.create:hover {
    background: #059669;
}

.bma-action-btn.create.urgent {
    background: #ef4444;
    font-weight: 600;
    animation: pulse 2s infinite;
}

.bma-action-btn.create.urgent:hover {
    background: #dc2626;
}

.bma-action-btn.update {
    background: #f59e0b;
}

.bma-action-btn.update:hover {
    background: #d97706;
}

.bma-action-btn.exclude {
    background: #ef4444;
}

.bma-action-btn.exclude:hover {
    background: #dc2626;
}

/* Material Symbols icons in buttons and links */
.bma-action-btn .material-symbols-outlined,
.bma-action-link .material-symbols-outlined {
    font-size: 16px;
    vertical-align: middle;
    margin-right: 4px;
    line-height: 1;
}

/* Ensure consistent heights for all action buttons and links */
.bma-action-btn,
.bma-action-link {
    min-height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1.5;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.85; }
}

/* Booking Forms */
.bma-booking-form {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 16px;
    margin-top: 12px;
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.bma-booking-form h5 {
    margin: 0 0 16px 0;
    font-size: 15px;
    font-weight: 600;
    color: #374151;
}

.bma-form-row {
    margin-bottom: 12px;
}

.bma-form-row label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: #4b5563;
    margin-bottom: 4px;
}

.bma-form-row input[type="text"],
.bma-form-row input[type="email"],
.bma-form-row input[type="tel"],
.bma-form-row input[type="time"],
.bma-form-row input[type="number"],
.bma-form-row textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    font-size: 14px;
    font-family: inherit;
    box-sizing: border-box;
}

.bma-form-row input[type="text"]:focus,
.bma-form-row input[type="email"]:focus,
.bma-form-row input[type="tel"]:focus,
.bma-form-row input[type="time"]:focus,
.bma-form-row input[type="number"]:focus,
.bma-form-row textarea:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.bma-form-row input[type="checkbox"] {
    margin-right: 6px;
}

.bma-form-actions {
    display: flex;
    gap: 8px;
    margin-top: 16px;
}

.bma-btn-cancel {
    padding: 10px 20px;
    background: #6b7280;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s;
}

.bma-btn-cancel:hover {
    background: #4b5563;
}

.bma-btn-submit {
    padding: 10px 20px;
    background: #10b981;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s;
}

.bma-btn-submit:hover {
    background: #059669;
}

.bma-btn-submit:disabled {
    background: #9ca3af;
    cursor: not-allowed;
}

.bma-form-feedback {
    margin-top: 12px;
    padding: 12px;
    border-radius: 4px;
    font-size: 13px;
    display: none;
}

.bma-form-feedback.success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #10b981;
    display: block;
}

.bma-form-feedback.error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #ef4444;
    display: block;
}

/* Confirmation Modal */
.bma-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    animation: fadeIn 0.2s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.bma-modal {
    background: white;
    border-radius: 8px;
    padding: 24px;
    max-width: 400px;
    width: 90%;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    animation: scaleIn 0.2s ease-out;
}

@keyframes scaleIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.bma-modal h4 {
    margin: 0 0 12px 0;
    font-size: 18px;
    font-weight: 600;
    color: #111827;
}

.bma-modal p {
    margin: 0 0 20px 0;
    font-size: 14px;
    color: #6b7280;
    line-height: 1.5;
}

.bma-modal-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

/* Comparison View Styles */
.bma-action-btn.view-comparison {
    background: #667eea;
}

.bma-action-btn.view-comparison:hover {
    background: #5568d3;
}

.bma-comparison-container {
    margin-top: 16px;
    animation: slideDown 0.3s ease-out;
}

.bma-comparison-loading {
    text-align: center;
    padding: 20px;
    color: #6b7280;
    font-style: italic;
}

.bma-comparison-error {
    background: #fee2e2;
    color: #991b1b;
    padding: 12px;
    border-radius: 4px;
    margin: 12px 0;
    border: 1px solid #ef4444;
}

.comparison-row-content {
    background: #fff;
    border: 2px solid #667eea;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 16px;
}

.comparison-table-wrapper {
    overflow-x: auto;
}

.comparison-header {
    background: #667eea;
    color: white;
    padding: 12px 16px;
    font-size: 15px;
    font-weight: 600;
    text-align: center;
}

.comparison-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.comparison-table thead th {
    background: #f3f4f6;
    padding: 10px 12px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    border-bottom: 2px solid #d1d5db;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.comparison-table tbody td {
    padding: 10px 12px;
    border-bottom: 1px solid #e5e7eb;
    vertical-align: top;
}

.comparison-table tbody tr:last-child td {
    border-bottom: none;
}

.comparison-table tbody td:first-child {
    font-weight: 600;
    color: #374151;
    background: #f9fafb;
    width: 20%;
}

.comparison-table tbody td:nth-child(2),
.comparison-table tbody td:nth-child(3) {
    width: 27%;
}

.comparison-table tbody td:nth-child(4) {
    width: 26%;
    background: #fffbeb;
}

/* Match highlighting - green background */
.comparison-table tbody tr.match-row {
    background: #d4edda;
}

.comparison-table tbody tr.match-row td:nth-child(4) {
    background: #d4edda;
}

/* Suggestion cell highlighting */
.comparison-table .suggestion-cell.has-suggestion {
    background: #fff3cd;
    font-weight: 500;
}

/* Material Symbols icons in comparison table */
.comparison-table .material-symbols-outlined {
    font-size: 16px;
    vertical-align: middle;
    margin-right: 4px;
}

/* Empty values styling */
.comparison-table em {
    color: #adb5bd;
    font-style: italic;
}

/* Comparison action buttons */
.comparison-actions-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    padding: 12px;
    background: #f9fafb;
    border-top: 1px solid #e5e7eb;
}

.comparison-actions-buttons button {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 8px 16px;
    min-height: 36px;
    font-size: 13px;
    font-weight: 500;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
    line-height: 1.5;
}

.comparison-actions-buttons button .material-symbols-outlined {
    font-size: 16px;
}

.btn-close-comparison,
.bma-close-comparison {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 8px 16px;
    min-height: 36px;
    background: #6b7280;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s;
    line-height: 1.5;
}

.btn-close-comparison:hover,
.bma-close-comparison:hover {
    background: #4b5563;
}

.btn-exclude-match {
    background: #ef4444;
    color: white;
}

.btn-exclude-match:hover {
    background: #dc2626;
}

.btn-view-resos {
    background: #3b82f6;
    color: white;
}

.btn-view-resos:hover {
    background: #2563eb;
}

.btn-confirm-match {
    background: #10b981;
    color: white;
}

.btn-confirm-match:hover {
    background: #059669;
}

.btn-confirm-match.btn-update-confirmed {
    background: #8b5cf6;
}

.btn-confirm-match.btn-update-confirmed:hover {
    background: #7c3aed;
}
</style>

<!-- Load Material Symbols font for status icons -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

<script>
// Get API configuration from parent window or use defaults
function getAPIConfig() {
    // Try to get from parent window's APIClient if available
    if (window.parent && window.parent.apiClient) {
        return {
            baseUrl: window.parent.apiClient.baseUrl,
            authHeader: window.parent.apiClient.authHeader
        };
    }

    // Fallback: construct from current location
    const protocol = window.location.protocol;
    const host = window.location.hostname;
    const port = window.location.port ? `:${window.location.port}` : '';
    return {
        baseUrl: `${protocol}//${host}${port}/wp-json/bma/v1`,
        authHeader: '' // Will be set by parent context
    };
}

// Toggle create booking form
function toggleCreateForm(date) {
    const formId = 'create-form-' + date;
    const form = document.getElementById(formId);
    if (form) {
        if (form.style.display === 'none') {
            form.style.display = 'block';
            form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            form.style.display = 'none';
        }
    }
}

// Toggle update booking form
function toggleUpdateForm(date, resosBookingId) {
    const formId = 'update-form-' + date + '-' + resosBookingId;
    const form = document.getElementById(formId);
    if (form) {
        if (form.style.display === 'none') {
            form.style.display = 'block';
            form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            form.style.display = 'none';
        }
    }
}

// Submit create booking
async function submitCreateBooking(date) {
    const formId = 'create-form-' + date;
    const form = document.getElementById(formId);
    if (!form) return;

    const feedback = form.querySelector('.bma-form-feedback');
    const submitBtn = form.querySelector('.bma-btn-submit');

    // Get form values
    const formData = {
        date: form.querySelector('.form-date').value,
        time: form.querySelector('.form-time').value,
        guest_name: form.querySelector('.form-guest-name').value,
        people: parseInt(form.querySelector('.form-people').value),
        phone: form.querySelector('.form-phone').value,
        email: form.querySelector('.form-email').value,
        restaurant_note: form.querySelector('.form-notes').value,
        booking_ref: form.dataset.bookingId,
        hotel_guest: 'Yes'
    };

    // Validate required fields
    if (!formData.date || !formData.time || !formData.guest_name || !formData.people) {
        showFeedback(feedback, 'Please fill in all required fields', 'error');
        return;
    }

    // Disable submit button
    submitBtn.disabled = true;
    submitBtn.textContent = 'Creating...';

    try {
        const config = getAPIConfig();
        const response = await fetch(`${config.baseUrl}/bookings/create`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': config.authHeader
            },
            body: JSON.stringify(formData)
        });

        const result = await response.json();

        if (result.success) {
            showFeedback(feedback, `✓ Booking created successfully! Booking ID: ${result.booking_id}`, 'success');
            // Reload the tab after 2 seconds to show updated data
            setTimeout(() => {
                if (window.parent && window.parent.reloadRestaurantTab) {
                    window.parent.reloadRestaurantTab();
                }
            }, 2000);
        } else {
            showFeedback(feedback, `Error: ${result.message || 'Failed to create booking'}`, 'error');
        }
    } catch (error) {
        showFeedback(feedback, `Error: ${error.message}`, 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Create Booking';
    }
}

// Submit update booking
async function submitUpdateBooking(date, resosBookingId) {
    const formId = 'update-form-' + date + '-' + resosBookingId;
    const form = document.getElementById(formId);
    if (!form) return;

    const feedback = form.querySelector('.bma-form-feedback');
    const submitBtn = form.querySelector('.bma-btn-submit');

    // Get form values
    const updates = {};

    const guestName = form.querySelector('.update-guest-name').value;
    if (guestName) updates.guest_name = guestName;

    const time = form.querySelector('.update-time').value;
    if (time) updates.time = time;

    const people = form.querySelector('.update-people').value;
    if (people) updates.people = parseInt(people);

    const phone = form.querySelector('.update-phone').value;
    if (phone) updates.phone = phone;

    const email = form.querySelector('.update-email').value;
    if (email) updates.email = email;

    const hotelGuest = form.querySelector('.update-hotel-guest').checked;
    updates.hotel_guest = hotelGuest ? 'Yes' : '';

    const dbb = form.querySelector('.update-dbb').checked;
    updates.dbb = dbb ? 'Yes' : '';

    // Disable submit button
    submitBtn.disabled = true;
    submitBtn.textContent = 'Updating...';

    try {
        const config = getAPIConfig();
        const response = await fetch(`${config.baseUrl}/bookings/update`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': config.authHeader
            },
            body: JSON.stringify({
                booking_id: resosBookingId,
                updates: updates
            })
        });

        const result = await response.json();

        if (result.success) {
            showFeedback(feedback, '✓ Booking updated successfully!', 'success');
            // Reload the tab after 2 seconds to show updated data
            setTimeout(() => {
                if (window.parent && window.parent.reloadRestaurantTab) {
                    window.parent.reloadRestaurantTab();
                }
            }, 2000);
        } else {
            showFeedback(feedback, `Error: ${result.message || 'Failed to update booking'}`, 'error');
        }
    } catch (error) {
        showFeedback(feedback, `Error: ${error.message}`, 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Update Booking';
    }
}

// Confirm and exclude match
function confirmExcludeMatch(resosBookingId, hotelBookingId, guestName) {
    // Create modal overlay
    const overlay = document.createElement('div');
    overlay.className = 'bma-modal-overlay';

    const modal = document.createElement('div');
    modal.className = 'bma-modal';
    modal.innerHTML = `
        <h4>Exclude This Match?</h4>
        <p>This will add a "NOT-#${hotelBookingId}" note to the ResOS booking for <strong>${guestName}</strong>, marking it as excluded from this hotel booking.</p>
        <div class="bma-modal-actions">
            <button class="bma-btn-cancel" data-action="modal-cancel">Cancel</button>
            <button class="bma-action-btn exclude" data-action="modal-exclude" data-resos-booking-id="${resosBookingId}" data-hotel-booking-id="${hotelBookingId}">Exclude Match</button>
        </div>
    `;

    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    // Attach event listeners to modal buttons
    const cancelBtn = modal.querySelector('[data-action="modal-cancel"]');
    const excludeBtn = modal.querySelector('[data-action="modal-exclude"]');

    cancelBtn.addEventListener('click', () => overlay.remove());
    excludeBtn.addEventListener('click', () => executeExcludeMatch(resosBookingId, hotelBookingId));

    // Close on overlay click
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            overlay.remove();
        }
    });
}

// Execute exclude match
async function executeExcludeMatch(resosBookingId, hotelBookingId) {
    const modal = document.querySelector('.bma-modal');
    const submitBtn = modal.querySelector('.bma-action-btn.exclude');

    submitBtn.disabled = true;
    submitBtn.textContent = 'Excluding...';

    try {
        const config = getAPIConfig();
        const response = await fetch(`${config.baseUrl}/bookings/exclude`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': config.authHeader
            },
            body: JSON.stringify({
                resos_booking_id: resosBookingId,
                hotel_booking_id: hotelBookingId
            })
        });

        const result = await response.json();

        if (result.success) {
            // Show success message and reload
            modal.innerHTML = `
                <h4>✓ Match Excluded</h4>
                <p>The NOT-#${hotelBookingId} note has been added to the ResOS booking.</p>
                <div class="bma-modal-actions">
                    <button class="bma-btn-submit" data-action="modal-close-reload">Close</button>
                </div>
            `;
            // Attach event listener to close button
            const closeBtn = modal.querySelector('[data-action="modal-close-reload"]');
            closeBtn.addEventListener('click', () => {
                document.querySelector('.bma-modal-overlay').remove();
                if (window.parent && window.parent.reloadRestaurantTab) {
                    window.parent.reloadRestaurantTab();
                }
            });
        } else {
            modal.innerHTML = `
                <h4>Error</h4>
                <p>${result.message || 'Failed to exclude match'}</p>
                <div class="bma-modal-actions">
                    <button class="bma-btn-cancel" data-action="modal-close">Close</button>
                </div>
            `;
            // Attach event listener to close button
            const closeBtn = modal.querySelector('[data-action="modal-close"]');
            closeBtn.addEventListener('click', () => document.querySelector('.bma-modal-overlay').remove());
        }
    } catch (error) {
        modal.innerHTML = `
            <h4>Error</h4>
            <p>${error.message}</p>
            <div class="bma-modal-actions">
                <button class="bma-btn-cancel" data-action="modal-close">Close</button>
            </div>
        `;
        // Attach event listener to close button
        const closeBtn = modal.querySelector('[data-action="modal-close"]');
        closeBtn.addEventListener('click', () => document.querySelector('.bma-modal-overlay').remove());
    }
}

// Show feedback message
function showFeedback(feedbackElement, message, type) {
    if (!feedbackElement) return;
    feedbackElement.textContent = message;
    feedbackElement.className = `bma-form-feedback ${type}`;
    feedbackElement.style.display = 'block';
}

// Load and display comparison view
async function loadComparisonView(date, bookingId, resosBookingId) {
    const containerId = 'comparison-' + date + '-' + resosBookingId;
    const container = document.getElementById(containerId);

    if (!container) return;

    // If already visible, hide it
    if (container.style.display === 'block') {
        container.style.display = 'none';
        return;
    }

    // Show loading state
    container.innerHTML = '<div class="bma-comparison-loading">Loading comparison data...</div>';
    container.style.display = 'block';
    container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    try {
        const config = getAPIConfig();
        const response = await fetch(`${config.baseUrl}/comparison`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': config.authHeader
            },
            body: JSON.stringify({
                booking_id: bookingId,
                resos_booking_id: resosBookingId,
                date: date
            })
        });

        const result = await response.json();

        if (result.success && result.comparison) {
            const comparisonHTML = buildComparisonHTML(result.comparison, date, resosBookingId);
            container.innerHTML = comparisonHTML;
        } else {
            container.innerHTML = `
                <div class="bma-comparison-error">
                    Error loading comparison: ${result.message || 'Unknown error'}
                </div>
                <button class="bma-close-comparison" data-action="close-comparison" data-container-id="${containerId}">Close</button>
            `;
        }
    } catch (error) {
        container.innerHTML = `
            <div class="bma-comparison-error">
                Error: ${error.message}
            </div>
            <button class="bma-close-comparison" data-action="close-comparison" data-container-id="${containerId}">Close</button>
        `;
    }
}

// Build comparison HTML from comparison data
function buildComparisonHTML(data, date, resosBookingId) {
    const hotel = data.hotel || {};
    const resos = data.resos || {};
    const matches = data.matches || {};
    const suggestions = data.suggested_updates || {};

    let html = '<div class="comparison-row-content">';
    html += '<div class="comparison-table-wrapper">';
    html += '<div class="comparison-header">Match Comparison</div>';
    html += '<table class="comparison-table">';
    html += '<thead><tr>';
    html += '<th>Field</th>';
    html += '<th>Newbook</th>';
    html += '<th>ResOS</th>';
    html += '<th style="background-color: #fff3cd;">Suggested Updates</th>';
    html += '</tr></thead>';
    html += '<tbody>';

    // Guest Name row
    html += buildComparisonRow('Guest Name', 'name', hotel.name, resos.name, matches.name, suggestions.name);

    // Phone row
    html += buildComparisonRow('Phone', 'phone', hotel.phone, resos.phone, matches.phone, suggestions.phone);

    // Email row
    html += buildComparisonRow('Email', 'email', hotel.email, resos.email, matches.email, suggestions.email);

    // People row
    html += buildComparisonRow('People', 'people', hotel.people, resos.people, matches.people, suggestions.people);

    // Tariff/Package row
    html += buildComparisonRow('Tariff/Package', 'dbb', hotel.rate_type, resos.dbb, matches.dbb, suggestions.dbb);

    // Booking # row
    html += buildComparisonRow('Booking #', 'booking_ref', hotel.booking_id, resos.booking_ref, matches.booking_ref, suggestions.booking_ref);

    // Hotel Guest row
    const hotelGuestValue = hotel.is_hotel_guest ? 'Yes' : '-';
    html += buildComparisonRow('Hotel Guest', 'hotel_guest', hotelGuestValue, resos.hotel_guest, false, suggestions.hotel_guest);

    // Status row
    const statusIcon = getStatusIcon(resos.status || 'request');
    const resosStatusHTML = `<span class="material-symbols-outlined">${statusIcon}</span> ${escapeHTML((resos.status || 'request').charAt(0).toUpperCase() + (resos.status || 'request').slice(1))}`;
    html += buildComparisonRow('Status', 'status', hotel.status, resosStatusHTML, false, suggestions.status, true);

    html += '</tbody>';
    html += '</table>';
    html += '</div>'; // comparison-table-wrapper

    // Add action buttons section
    // Determine match type: confirmed match = booking_ref matches
    const isConfirmedMatch = matches && matches.booking_ref;
    const hasSuggestions = suggestions && Object.keys(suggestions).length > 0;
    const containerId = 'comparison-' + date + '-' + resosBookingId;

    html += '<div class="comparison-actions-buttons">';

    // 1. Close button (always shown, first)
    html += `<button class="btn-close-comparison" data-action="close-comparison" data-container-id="${containerId}">`;
    html += '<span class="material-symbols-outlined">close</span> Close';
    html += '</button>';

    // 2. Exclude Match button (only for suggested matches, not confirmed)
    if (!isConfirmedMatch && resos.id && hotel.booking_id) {
        html += `<button class="btn-exclude-match" data-action="exclude-match" data-resos-booking-id="${resos.id}" data-hotel-booking-id="${hotel.booking_id}" data-guest-name="${escapeHTML(resos.name || 'Guest')}">`;
        html += '<span class="material-symbols-outlined">close</span> Exclude Match';
        html += '</button>';
    }

    // 3. View in Resos button (always shown if we have IDs)
    if (resos.id && resos.restaurant_id) {
        const resosUrl = `https://app.resos.com/${resos.restaurant_id}/bookings/timetable/${date}/${resos.id}`;
        html += `<button class="btn-view-resos" onclick="window.open('${resosUrl}', '_blank')">`;
        html += '<span class="material-symbols-outlined">visibility</span> View in Resos';
        html += '</button>';
    }

    // 4. Update button (only if there are suggested updates)
    if (hasSuggestions) {
        const buttonLabel = isConfirmedMatch ? 'Update Selected' : 'Update Selected & Match';
        const buttonClass = isConfirmedMatch ? 'btn-confirm-match btn-update-confirmed' : 'btn-confirm-match';
        html += `<button class="${buttonClass}" data-action="toggle-update" data-date="${date}" data-resos-booking-id="${resos.id}">`;
        html += `<span class="material-symbols-outlined">check_circle</span> ${buttonLabel}`;
        html += '</button>';
    }

    html += '</div>'; // comparison-actions-buttons
    html += '</div>'; // comparison-row-content

    return html;
}

// Build a single comparison table row
function buildComparisonRow(label, field, hotelValue, resosValue, isMatch, suggestionValue, isHTML = false) {
    const matchClass = isMatch ? ' class="match-row"' : '';
    const hasSuggestion = suggestionValue !== undefined && suggestionValue !== null;
    const suggestionCellClass = hasSuggestion ? 'suggestion-cell has-suggestion' : 'suggestion-cell';

    let hotelDisplay = hotelValue !== undefined && hotelValue !== null && hotelValue !== ''
        ? (isHTML ? hotelValue : escapeHTML(String(hotelValue)))
        : '<em style="color: #adb5bd;">-</em>';

    let resosDisplay = resosValue !== undefined && resosValue !== null && resosValue !== ''
        ? (isHTML ? resosValue : escapeHTML(String(resosValue)))
        : '<em style="color: #adb5bd;">-</em>';

    let suggestionDisplay;
    if (!hasSuggestion) {
        suggestionDisplay = '<em style="color: #adb5bd;">-</em>';
    } else if (suggestionValue === '') {
        suggestionDisplay = '<em style="color: #999;">(Remove)</em>';
    } else {
        suggestionDisplay = escapeHTML(String(suggestionValue));
    }

    let html = `<tr${matchClass}>`;
    html += `<td><strong>${escapeHTML(label)}</strong></td>`;
    html += `<td>${hotelDisplay}</td>`;
    html += `<td>${resosDisplay}</td>`;
    html += `<td class="${suggestionCellClass}">${suggestionDisplay}</td>`;
    html += '</tr>';

    return html;
}

// Get status icon for Material Symbols
function getStatusIcon(status) {
    const statusLower = status.toLowerCase();
    switch (statusLower) {
        case 'approved':
        case 'confirmed':
            return 'check_circle';
        case 'request':
            return 'help';
        case 'declined':
            return 'cancel';
        case 'waitlist':
            return 'schedule';
        case 'arrived':
            return 'login';
        case 'seated':
            return 'event_seat';
        case 'left':
            return 'logout';
        case 'no_show':
        case 'no-show':
            return 'person_off';
        case 'canceled':
        case 'cancelled':
            return 'block';
        default:
            return 'help';
    }
}

// Escape HTML to prevent XSS
function escapeHTML(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Close comparison view
function closeComparison(containerId) {
    const container = document.getElementById(containerId);
    if (container) {
        container.style.display = 'none';
    }
}

// Attach event listeners using event delegation
// Note: Attach immediately since this template is injected after page load
(function() {
    // Use event delegation on document body for all button clicks
    document.body.addEventListener('click', function(event) {
        const button = event.target.closest('button[data-action]');
        if (!button) return;

        const action = button.dataset.action;

        switch(action) {
            case 'toggle-create':
                toggleCreateForm(button.dataset.date);
                break;

            case 'toggle-update':
                toggleUpdateForm(button.dataset.date, button.dataset.resosBookingId);
                break;

            case 'submit-create':
                submitCreateBooking(button.dataset.date);
                break;

            case 'submit-update':
                submitUpdateBooking(button.dataset.date, button.dataset.resosBookingId);
                break;

            case 'exclude-match':
                confirmExcludeMatch(
                    button.dataset.resosBookingId,
                    button.dataset.hotelBookingId,
                    button.dataset.guestName
                );
                break;

            case 'view-comparison':
                loadComparisonView(
                    button.dataset.date,
                    button.dataset.bookingId,
                    button.dataset.resosBookingId
                );
                break;

            case 'close-comparison':
                closeComparison(button.dataset.containerId);
                break;
        }
    });

    console.log('BMA: Event listeners attached to document.body');
})();
</script>
