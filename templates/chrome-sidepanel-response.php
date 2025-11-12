<?php
/**
 * Chrome Sidepanel Response Template
 * TEMPLATE VERSION: 1.4.0-2025-01-11
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

            <!-- Action Button -->
            <div class="bma-top-actions">
                <button class="open-booking-btn" data-booking-id="<?php echo esc_attr($booking['booking_id']); ?>">
                    <span class="material-symbols-outlined">arrow_back</span>
                    Open Booking in NewBook
                </button>
            </div>
        </div>

        <div class="bma-nights">
            <h4>
                <span class="material-symbols-outlined">restaurant</span>
                Restaurant Bookings by Night
            </h4>

            <?php foreach ($booking['nights'] as $night): ?>
                <?php
                $has_matches = !empty($night['resos_matches']);
                $match_count = $has_matches ? count($night['resos_matches']) : 0;
                $has_package = isset($night['has_package']) && $night['has_package'];
                $is_stale = isset($night['is_stale']) && $night['is_stale'];
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

                <!-- Date Section Wrapper for Navigation -->
                <div class="bma-date-section" id="date-section-<?php echo esc_attr($night['date']); ?>" data-date="<?php echo esc_attr($night['date']); ?>">
                <div class="bma-night <?php echo $has_matches ? 'matched' : 'unmatched'; ?> <?php echo $has_warnings ? 'has-warnings' : ''; ?> <?php echo $has_package_alert ? 'has-package-alert' : ''; ?>">
                    <div class="bma-night-header">
                        <div class="bma-night-date">
                            <?php echo esc_html(date('l, d/m/y', strtotime($night['date']))); ?>
                        </div>
                        <?php if ($is_stale): ?>
                            <span class="bma-stale-badge" title="Data from cache - may be outdated">
                                <span class="material-symbols-outlined">sync_problem</span> Cached
                            </span>
                        <?php endif; ?>
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
                            <div class="bma-match-item <?php echo $match['match_info']['is_primary'] ? 'primary' : 'secondary'; ?>">
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

                                <div class="bma-match-details<?php echo !empty($match['match_info']['is_group_member']) ? ' grouped' : ''; ?>">
                                    <!-- Line 1: Guest Name - Time (People pax) OR Time - Group Icon - Lead ID (People pax) -->
                                    <div class="bma-match-primary">
                                        <?php if (!empty($match['match_info']['is_group_member'])): ?>
                                            <?php echo esc_html($match['time'] ?? 'Not specified'); ?> -
                                            <span class="material-symbols-outlined group-icon" title="Group Booking">groups</span>
                                            <?php
                                            // Get lead booking ID from custom fields
                                            $lead_booking_id = '';
                                            if (!empty($match['booking_number'])) {
                                                $lead_booking_id = $match['booking_number'];
                                            }
                                            if (!empty($lead_booking_id)) {
                                                echo '<span class="lead-booking-id">#' . esc_html($lead_booking_id) . '</span>';
                                            }
                                            ?>
                                            (<?php echo esc_html($match['people']); ?> pax)
                                        <?php else: ?>
                                            <?php echo esc_html($match['guest_name']); ?> -
                                            <?php echo esc_html($match['time'] ?? 'Not specified'); ?>
                                            (<?php echo esc_html($match['people']); ?> pax)
                                        <?php endif; ?>
                                    </div>

                                    <!-- Line 2: Status icon + Matched on fields -->
                                    <div class="bma-match-secondary">
                                        <span class="status-icon material-symbols-outlined" data-status="<?php echo esc_attr($match['status'] ?? 'request'); ?>">
                                            <?php
                                            // Get status icon name for Material Symbols
                                            $status = strtolower($match['status'] ?? 'request');
                                            $status_icons = array(
                                                'approved' => 'thumb_up',
                                                'confirmed' => 'thumb_up',
                                                'request' => 'help',
                                                'declined' => 'thumb_down',
                                                'waitlist' => 'pending_actions',
                                                'arrived' => 'directions_walk',
                                                'seated' => 'airline_seat_recline_normal',
                                                'left' => 'flight_takeoff',
                                                'no_show' => 'block',
                                                'no-show' => 'block',
                                                'canceled' => 'cancel',
                                                'cancelled' => 'cancel'
                                            );
                                            echo isset($status_icons[$status]) ? $status_icons[$status] : 'help';
                                            ?>
                                        </span>
                                        <span class="bma-match-on">
                                            Matched: <?php
                                            // Build matched fields text
                                            $match_label = $match['match_info']['match_label'] ?? 'Unknown';
                                            // Replace " + " with " / " for compact format
                                            $match_label = str_replace(' + ', ' / ', $match_label);
                                            echo esc_html($match_label);
                                            ?>
                                        </span>
                                        <?php if (!empty($match['is_hotel_guest'])): ?>
                                            <span class="bma-badge-small hotel-guest">Hotel Guest</span>
                                        <?php endif; ?>
                                        <?php if (!empty($match['is_dbb'])): ?>
                                            <span class="bma-badge-small dbb">DBB</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="bma-match-actions">
                                    <!-- View Comparison Button (primary action) -->
                                    <?php
                                    $has_suggestions = $match['has_suggestions'] ?? false;
                                    $button_class = $has_suggestions ? 'has-updates' : ($match['match_info']['is_primary'] ? '' : 'suggested');
                                    ?>
                                    <button class="bma-action-btn view-comparison <?php echo $button_class; ?>"
                                            data-action="view-comparison"
                                            data-date="<?php echo esc_attr($night['date']); ?>"
                                            data-booking-id="<?php echo esc_attr($booking['booking_id']); ?>"
                                            data-resos-booking-id="<?php echo esc_attr($match['resos_booking_id']); ?>"
                                            data-is-confirmed="<?php echo isset($match['match_info']['match_type']) && $match['match_info']['match_type'] === 'booking_id' ? '1' : '0'; ?>"
                                            data-is-matched-elsewhere="<?php echo isset($match['match_info']['matched_elsewhere']) && $match['match_info']['matched_elsewhere'] ? '1' : '0'; ?>"
                                            data-hotel-booking-id="<?php echo esc_attr($booking['booking_id']); ?>"
                                            data-guest-name="<?php echo esc_attr($match['guest_name']); ?>">
                                        <?php if ($has_suggestions): ?>
                                            <span class="material-symbols-outlined">sync</span> Check Updates
                                        <?php elseif ($match['match_info']['is_primary']): ?>
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
                            <div class="bma-night-status unmatched package-alert" id="status-<?php echo esc_attr($night['date']); ?>">
                                <span class="bma-status-icon">🍽️</span>
                                <span><strong>PACKAGE BOOKING - No Restaurant Reservation</strong></span>
                            </div>
                            <div class="bma-package-alert-message">
                                This guest has a dinner package but no restaurant booking yet.
                            </div>
                        <?php else: ?>
                            <div class="bma-night-status unmatched" id="status-<?php echo esc_attr($night['date']); ?>">
                                <span class="bma-status-icon">⚠</span>
                                <span>No restaurant booking</span>
                            </div>
                        <?php endif; ?>

                        <!-- Create Booking Button -->
                        <button class="bma-action-btn create <?php echo $has_package_alert ? 'urgent' : ''; ?>"
                                id="create-btn-<?php echo esc_attr($night['date']); ?>"
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

                            <!-- Gantt Chart (Compact Mode) -->
                            <div class="bma-gantt-container" id="gantt-container-<?php echo esc_attr($night['date']); ?>">
                                <div class="gantt-viewport" id="gantt-<?php echo esc_attr($night['date']); ?>" style="overflow-x: auto; overflow-y: hidden; min-height: 120px; position: relative;">
                                    <p style="padding: 20px; text-align: center; color: #666;">Loading timeline...</p>
                                </div>
                            </div>

                            <!-- Service Period Accordion Sections -->
                            <div id="service-period-sections-<?php echo esc_attr($night['date']); ?>" class="service-period-sections">
                                <p style="padding: 10px; text-align: center; color: #666;">Loading service periods...</p>
                            </div>

                            <!-- Hidden fields for selected time and period -->
                            <input type="hidden" class="form-time-selected" id="time-selected-<?php echo esc_attr($night['date']); ?>">
                            <input type="hidden" class="form-opening-hour-id" id="opening-hour-id-<?php echo esc_attr($night['date']); ?>">

                            <!-- Collapsible Section 1: Booking Details -->
                            <div class="bma-expandable-section">
                                <button type="button" class="bma-section-toggle" data-target="booking-details-<?php echo esc_attr($night['date']); ?>" data-section-type="details">
                                    <span class="material-symbols-outlined">expand_more</span>
                                    <span class="section-title">Booking Details</span>
                                    <span class="material-symbols-outlined section-indicator" data-indicator="details-<?php echo esc_attr($night['date']); ?>">draw</span>
                                </button>
                                <div id="booking-details-<?php echo esc_attr($night['date']); ?>"
                                     class="bma-section-content" style="display:none;">

                                    <!-- Hidden date field (already defined by which day's create button was clicked) -->
                                    <input type="hidden" class="form-date" value="<?php echo esc_attr($night['date']); ?>">

                                    <div class="bma-form-row">
                                        <label>Guest Name *</label>
                                        <input type="text" class="form-guest-name" value="<?php echo esc_attr($booking['guest_name']); ?>" required>
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
                                        <label>
                                            <input type="checkbox" class="form-hotel-guest" checked>
                                            Hotel Guest
                                        </label>
                                    </div>

                                    <div class="bma-form-row">
                                        <label>
                                            <input type="checkbox" class="form-dbb" <?php echo ($has_package ? 'checked' : ''); ?>>
                                            DBB/Package
                                        </label>
                                    </div>

                                    <div class="bma-form-row">
                                        <label>
                                            <input type="checkbox" class="form-notification-sms" <?php echo (!empty($booking['phone']) ? 'checked' : ''); ?>>
                                            Allow SMS
                                        </label>
                                    </div>

                                    <div class="bma-form-row">
                                        <label>
                                            <input type="checkbox" class="form-notification-email" <?php echo (!empty($booking['email']) ? 'checked' : ''); ?>>
                                            Allow Email
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Collapsible Section 2: Allergies & Dietary -->
                            <div class="bma-expandable-section">
                                <button type="button" class="bma-section-toggle" data-target="allergies-<?php echo esc_attr($night['date']); ?>" data-section-type="allergies">
                                    <span class="material-symbols-outlined">expand_more</span>
                                    <span class="section-title">Allergies & Dietary</span>
                                    <span class="material-symbols-outlined section-indicator" data-indicator="allergies-<?php echo esc_attr($night['date']); ?>">draw</span>
                                </button>
                                <div id="allergies-<?php echo esc_attr($night['date']); ?>"
                                     class="bma-section-content" style="display:none;">

                                    <div id="dietary-checkboxes-<?php echo esc_attr($night['date']); ?>">
                                        <p style="padding: 10px; text-align: center; color: #666;">Loading dietary options...</p>
                                    </div>

                                    <div class="bma-form-row">
                                        <label>Other Dietary Requirements</label>
                                        <input type="text" class="form-diet-other" placeholder="Additional dietary notes...">
                                    </div>
                                </div>
                            </div>

                            <!-- Collapsible Section 3: Add Note -->
                            <div class="bma-expandable-section">
                                <button type="button" class="bma-section-toggle" data-target="note-<?php echo esc_attr($night['date']); ?>" data-section-type="note">
                                    <span class="material-symbols-outlined">expand_more</span>
                                    <span class="section-title">Add Note</span>
                                    <span class="material-symbols-outlined section-indicator" data-indicator="note-<?php echo esc_attr($night['date']); ?>">draw</span>
                                </button>
                                <div id="note-<?php echo esc_attr($night['date']); ?>"
                                     class="bma-section-content" style="display:none;">
                                    <textarea class="form-booking-note" rows="3"
                                              placeholder="Internal restaurant notes..."></textarea>
                                </div>
                            </div>

                            <!-- Booking Summary Header -->
                            <div class="bma-booking-summary-header" id="booking-summary-<?php echo esc_attr($night['date']); ?>">
                                <strong><?php echo esc_html($booking['guest_name']); ?></strong> -
                                <span class="booking-time-display" id="booking-time-display-<?php echo esc_attr($night['date']); ?>">SELECT TIME</span>
                                (<?php
                                    $occ = $booking['occupants'] ?? array('adults' => 0, 'children' => 0, 'infants' => 0);
                                    echo esc_html($occ['adults'] + $occ['children'] + $occ['infants']);
                                ?>pax)
                            </div>

                            <div class="bma-form-actions">
                                <button type="button" class="bma-btn-cancel"
                                        data-action="toggle-create"
                                        data-date="<?php echo esc_attr($night['date']); ?>">Cancel</button>
                                <button type="button" class="bma-btn-submit"
                                        data-action="submit-create"
                                        data-date="<?php echo esc_attr($night['date']); ?>">Create Booking</button>
                            </div>

                            <div class="bma-form-feedback" id="feedback-create-<?php echo esc_attr($night['date']); ?>"></div>

                            <script>
                            (function() {
                                const formId = 'create-form-<?php echo esc_js($night['date']); ?>';
                                const date = '<?php echo esc_js($night['date']); ?>';
                                const form = document.getElementById(formId);
                                let initialized = false;

                                <?php
                                // Extract restaurant bookings for this date
                                $bookings_for_date = array();

                                // Loop through all results to find bookings for this specific date
                                foreach ($results as $result) {
                                    foreach ($result['nights'] as $result_night) {
                                        if ($result_night['date'] === $night['date']) {
                                            // Extract restaurant bookings from resos_matches
                                            if (!empty($result_night['resos_matches'])) {
                                                foreach ($result_night['resos_matches'] as $match) {
                                                    // Extract room number if available
                                                    $room = 'Unknown';
                                                    if (isset($match['room']) && !empty($match['room'])) {
                                                        $room = $match['room'];
                                                    } elseif (isset($result['room']) && !empty($result['room'])) {
                                                        $room = $result['room'];
                                                    }

                                                    $bookings_for_date[] = array(
                                                        'time' => $match['time'] ?? '19:00',
                                                        'people' => $match['people'] ?? 2,
                                                        'name' => $match['guest_name'] ?? 'Guest',
                                                        'room' => $room
                                                    );
                                                }
                                            }
                                        }
                                    }
                                }
                                ?>

                                // Restaurant bookings data for Gantt chart
                                const bookingsForDate = <?php echo wp_json_encode($bookings_for_date); ?>;
                                console.log('DEBUG: Bookings for date ' + date + ':', bookingsForDate);

                                // Watch for form visibility and initialize on first show
                                const observer = new MutationObserver(function(mutations) {
                                    mutations.forEach(function(mutation) {
                                        if (mutation.attributeName === 'style') {
                                            const isVisible = form.style.display !== 'none';
                                            if (isVisible && !initialized) {
                                                initialized = true;
                                                initializeCreateForm(date);
                                            }
                                        }
                                    });
                                });

                                observer.observe(form, { attributes: true, attributeFilter: ['style'] });

                                async function initializeCreateForm(date) {
                                    console.log('Initializing create form for date:', date);

                                    // Fetch and populate opening hours
                                    try {
                                        const openingHoursData = await fetchOpeningHours(date);
                                        const tabsContainer = document.getElementById('service-period-tabs-' + date);
                                        const sectionsContainer = document.getElementById('time-slots-sections-' + date);

                                        if (openingHoursData.success && openingHoursData.data && openingHoursData.data.length > 0) {
                                            const periods = openingHoursData.data;

                                            // Generate tab buttons
                                            let tabsHtml = '';
                                            periods.forEach((period, index) => {
                                                const isLast = index === periods.length - 1;
                                                const activeClass = isLast ? ' active' : '';
                                                const tabLabel = `${period.name || 'Service'} (${formatTime(period.open)}-${formatTime(period.close)})`;

                                                tabsHtml += `<button type="button" class="time-tab${activeClass}" data-tab-index="${index}" data-period-id="${period._id}" onclick="switchTimeTab('${date}', ${index})">
                                                    ${tabLabel}
                                                </button>`;
                                            });
                                            tabsContainer.innerHTML = tabsHtml;

                                            // Generate sections for each period
                                            let sectionsHtml = '';
                                            periods.forEach((period, index) => {
                                                const isLast = index === periods.length - 1;
                                                const activeClass = isLast ? ' active' : '';
                                                const displayStyle = isLast ? 'flex' : 'none';

                                                sectionsHtml += `<div class="time-tab-content${activeClass}" data-tab-index="${index}" data-period-id="${period._id}" style="display: ${displayStyle};">
                                                    <p style="padding: 10px; text-align: center; color: #666;">Loading available times...</p>
                                                </div>`;
                                            });
                                            sectionsContainer.innerHTML = sectionsHtml;

                                            // Generate Gantt chart with bookings
                                            if (typeof buildGanttChart === 'function') {
                                                const ganttViewport = document.getElementById('gantt-' + date);
                                                if (ganttViewport) {
                                                    // Fetch special events and available times for grey overlays
                                                    const specialEventsPromise = fetchSpecialEvents(date);
                                                    const people = parseInt(form.querySelector('.form-people').value) || 2;
                                                    const availableTimesPromise = fetchAvailableTimes(date, people, null);

                                                    Promise.all([specialEventsPromise, availableTimesPromise]).then(([specialEventsData, availableTimesData]) => {
                                                        const specialEvents = specialEventsData.success ? specialEventsData.data : [];
                                                        const availableTimes = availableTimesData.success ? availableTimesData.times : [];

                                                        const ganttHtml = buildGanttChart(
                                                            periods,            // opening hours
                                                            specialEvents,      // special events/closures
                                                            availableTimes,     // available time slots
                                                            bookingsForDate,    // existing restaurant bookings
                                                            'compact',          // display mode
                                                            'gantt-' + date     // chart ID
                                                        );
                                                        ganttViewport.innerHTML = ganttHtml;
                                                        console.log('Gantt chart generated for date:', date, 'bookingsForDate:', bookingsForDate);
                                                    }).catch(error => {
                                                        console.error('Error fetching Gantt chart data:', error);
                                                        // Fallback: render chart without special events/available times
                                                        const ganttHtml = buildGanttChart(
                                                            periods,
                                                            [],
                                                            [],
                                                            bookingsForDate,
                                                            'compact',
                                                            'gantt-' + date
                                                        );
                                                        ganttViewport.innerHTML = ganttHtml;
                                                        console.log('Gantt chart generated (fallback) for date:', date, 'bookingsForDate:', bookingsForDate);
                                                    });
                                                }
                                            }

                                            // Load available times for the default (last) period
                                            const defaultPeriodIndex = periods.length - 1;
                                            const defaultPeriod = periods[defaultPeriodIndex];
                                            const people = parseInt(form.querySelector('.form-people').value) || 2;
                                            await loadAvailableTimesForPeriod(date, people, defaultPeriod._id, defaultPeriodIndex);

                                            console.log('Opening hours loaded, default period:', defaultPeriod.name);
                                        } else {
                                            tabsContainer.innerHTML = '<p style="color: #ef4444;">No service periods available</p>';
                                        }
                                    } catch (error) {
                                        console.error('Error loading opening hours:', error);
                                        const tabsContainer = document.getElementById('service-period-tabs-' + date);
                                        tabsContainer.innerHTML = '<p style="color: #ef4444;">Error loading service periods</p>';
                                    }

                                    // Fetch and populate dietary choices
                                    try {
                                        const dietaryData = await fetchDietaryChoices();
                                        const container = document.getElementById('dietary-checkboxes-' + date);

                                        if (dietaryData.success && dietaryData.html) {
                                            container.innerHTML = dietaryData.html;
                                        } else if (dietaryData.success && dietaryData.choices) {
                                            container.innerHTML = '';
                                            dietaryData.choices.forEach(choice => {
                                                const label = document.createElement('label');
                                                label.style.display = 'block';
                                                label.style.marginBottom = '8px';
                                                label.innerHTML = `<input type="checkbox" class="diet-checkbox" data-choice-id="${choice._id}" data-choice-name="${choice.name}"> ${choice.name}`;
                                                container.appendChild(label);
                                            });
                                        }
                                    } catch (error) {
                                        console.error('Error loading dietary choices:', error);
                                        const container = document.getElementById('dietary-checkboxes-' + date);
                                        container.innerHTML = '<p style="color: #ef4444;">Error loading dietary options</p>';
                                    }

                                    // Mark form as initialized
                                    form.dataset.initialized = 'true';
                                    console.log('Form initialization complete for date:', date);
                                }

                                async function loadAvailableTimesForPeriod(date, people, periodId, periodIndex) {
                                    try {
                                        const timesData = await fetchAvailableTimes(date, people, periodId);
                                        const sectionsContainer = document.getElementById('time-slots-sections-' + date);
                                        const section = sectionsContainer.querySelector(`.time-tab-content[data-tab-index="${periodIndex}"]`);

                                        if (!section) {
                                            console.warn('Section not found for period index:', periodIndex);
                                            return;
                                        }

                                        if (timesData.success && timesData.html) {
                                            section.innerHTML = timesData.html;

                                            // Add click handlers to time slot buttons
                                            const timeButtons = section.querySelectorAll('.time-slot-btn');
                                            timeButtons.forEach(btn => {
                                                btn.addEventListener('click', function() {
                                                    // Remove selected class from ALL buttons in ALL sections
                                                    const allButtons = sectionsContainer.querySelectorAll('.time-slot-btn');
                                                    allButtons.forEach(b => b.classList.remove('selected'));
                                                    // Add selected class to clicked button
                                                    this.classList.add('selected');
                                                    // Update hidden time field
                                                    const timeValue = this.dataset.time || this.textContent.trim();
                                                    document.getElementById('time-selected-' + date).value = timeValue;

                                                    // Update hidden opening hour ID field
                                                    const openingHourIdField = document.getElementById('opening-hour-id-' + date);
                                                    if (openingHourIdField) {
                                                        openingHourIdField.value = periodId;
                                                    }

                                                    // Update booking time display in summary header
                                                    const bookingTimeDisplay = document.getElementById('booking-time-display-' + date);
                                                    if (bookingTimeDisplay) {
                                                        const displayTime = this.textContent.trim();
                                                        bookingTimeDisplay.textContent = displayTime;
                                                    }
                                                });
                                            });

                                            console.log('Loaded available times for period index:', periodIndex);
                                        } else {
                                            section.innerHTML = '<p style="padding: 10px; text-align: center; color: #666;">No available times</p>';
                                        }
                                    } catch (error) {
                                        console.error('Error loading available times:', error);
                                        const sectionsContainer = document.getElementById('time-slots-sections-' + date);
                                        const section = sectionsContainer.querySelector(`.time-tab-content[data-tab-index="${periodIndex}"]`);
                                        if (section) {
                                            section.innerHTML = '<p style="color: #ef4444;">Error loading times</p>';
                                        }
                                    }
                                }

                                function formatTime(hhmm) {
                                    const hours = Math.floor(hhmm / 100);
                                    const minutes = hhmm % 100;
                                    return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
                                }
                            })();
                            </script>
                        </div>
                    <?php endif; ?>
                </div>
                </div><!-- Close bma-date-section -->
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

.bma-warning-badge, .bma-package-alert-badge, .bma-stale-badge {
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

.bma-stale-badge {
    background: #fef3c7;
    color: #f59e0b;
    display: flex;
    align-items: center;
    gap: 4px;
}

.bma-stale-badge .material-symbols-outlined {
    font-size: 16px;
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
    margin-bottom: 12px;
    font-size: 13px;
}

/* Compact format: Line 1 - Guest Name - Time (People pax) */
.bma-match-primary {
    font-size: 15px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 4px;
    line-height: 1.4;
}

/* Compact format: Line 2 - Status icon + Matched fields */
.bma-match-secondary {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #6b7280;
    flex-wrap: wrap;
}

.bma-match-secondary .status-icon {
    font-size: 16px;
    vertical-align: middle;
    flex-shrink: 0;
}

.bma-match-secondary .bma-match-on {
    color: #4b5563;
    flex-shrink: 0;
}

/* Legacy styles for grid layout (no longer used but kept for compatibility) */
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
    height: 36px;
    background: #667eea;
    color: white !important;
    text-decoration: none;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    line-height: 1.5;
    transition: background 0.2s;
    box-sizing: border-box;
    margin-right: 8px;
    margin-top: 8px;
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
    height: 36px;
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
    box-sizing: border-box;
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

.bma-action-btn.suggested {
    background: #f59e0b;
}

.bma-action-btn.suggested:hover {
    background: #d97706;
}

/* Material Symbols icons in buttons and links */
.bma-action-btn .material-symbols-outlined,
.bma-action-link .material-symbols-outlined {
    font-size: 16px;
    vertical-align: middle;
    margin-right: 4px;
    line-height: 1;
    display: inline-block;
    width: 16px;
    height: 16px;
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

/* Gantt Chart Placeholder */
.bma-gantt-placeholder {
    background: #f0f9ff;
    border: 2px dashed #3b82f6;
    border-radius: 6px;
    padding: 12px;
    margin: 12px 0;
}

.bma-gantt-placeholder .gantt-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #1e40af;
}

.bma-gantt-placeholder .gantt-header .material-symbols-outlined {
    font-size: 20px;
}

.bma-gantt-placeholder .gantt-content {
    text-align: center;
    padding: 16px;
}

.bma-gantt-placeholder .gantt-placeholder-text {
    margin: 0 0 4px 0;
    color: #3b82f6;
    font-size: 13px;
    font-weight: 500;
}

.bma-gantt-placeholder small {
    color: #6b7280;
    font-size: 11px;
}

/* Booking Forms */
.bma-booking-form {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 12px 8px;
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
    margin: 0 0 12px 0;
    font-size: 14px;
    font-weight: 600;
    color: #374151;
}

.bma-form-row {
    margin-bottom: 10px;
}

.bma-form-row label {
    display: block;
    font-size: 12px;
    font-weight: 500;
    color: #4b5563;
    margin-bottom: 3px;
}

.bma-form-row input[type="text"],
.bma-form-row input[type="email"],
.bma-form-row input[type="tel"],
.bma-form-row input[type="time"],
.bma-form-row input[type="number"],
.bma-form-row textarea {
    width: 100%;
    padding: 6px 10px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    font-size: 13px;
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
    gap: 6px;
    margin-top: 12px;
}

.bma-btn-cancel {
    padding: 8px 16px;
    background: #6b7280;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s;
}

.bma-btn-cancel:hover {
    background: #4b5563;
}

.bma-btn-submit {
    padding: 8px 16px;
    background: #10b981;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 13px;
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
    margin-top: 10px;
    padding: 10px;
    border-radius: 4px;
    font-size: 12px;
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

.bma-action-btn.view-comparison.has-updates {
    background: #3b82f6;
}

.bma-action-btn.view-comparison.has-updates:hover {
    background: #2563eb;
}

.updates-link {
    color: inherit;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.updates-link:hover {
    text-decoration: underline;
}

.updates-icon {
    vertical-align: middle;
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
    font-size: 10px;
}

.comparison-table thead th {
    background: #f3f4f6;
    padding: 6px 8px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    border-bottom: 2px solid #d1d5db;
    font-size: 8px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.comparison-table tbody td {
    padding: 6px 8px;
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
    width: 25%;
    font-size: 8px;
}

.comparison-table tbody td:nth-child(2),
.comparison-table tbody td:nth-child(3) {
    width: 37.5%;
    font-size: 10px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 0; /* Force ellipsis to work with percentage widths */
}

/* Match highlighting - green background */
.comparison-table tbody tr.match-row {
    background: #d4edda;
}

/* Suggestion row styling */
.comparison-table tbody tr.suggestion-row {
    background: linear-gradient(to right, rgba(251, 191, 36, 0.3), rgba(251, 191, 36, 0.15));
}

.comparison-table tbody tr.suggestion-row td {
    padding: 6px 8px;
    border-bottom: 1px solid #e5e7eb;
}

.comparison-table .suggestion-content {
    display: flex;
    align-items: center;
    font-size: 10px;
}

.comparison-table .suggestion-content label {
    display: flex;
    align-items: center;
    cursor: pointer;
    margin: 0;
}

.comparison-table .suggestion-checkbox {
    margin: 0 6px 0 0;
    cursor: pointer;
    width: 14px;
    height: 14px;
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

/* Resos value strikethrough when suggestion is checked */
.comparison-table .resos-value.overwriting {
    text-decoration: line-through;
    color: #9ca3af;
}

/* Suggestion text greyed out when unchecked */
.comparison-table .suggestion-text.inactive {
    color: #9ca3af;
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
    height: 36px;
    font-size: 13px;
    font-weight: 500;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
    line-height: 1.5;
    box-sizing: border-box;
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
    height: 36px;
    background: #6b7280;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s;
    line-height: 1.5;
    box-sizing: border-box;
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
    background: #f59e0b;
    color: white;
}

.btn-confirm-match:hover {
    background: #d97706;
}

.btn-confirm-match.btn-update-confirmed {
    background: #8b5cf6;
}

.btn-confirm-match.btn-update-confirmed:hover {
    background: #7c3aed;
}

/* Custom Modal for Confirmations */
#bma-custom-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}

#bma-custom-modal.show {
    display: flex;
}

.bma-modal-content {
    background: white;
    border-radius: 8px;
    padding: 24px;
    max-width: 400px;
    width: 90%;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    animation: modalSlideIn 0.2s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.bma-modal-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.bma-modal-icon {
    font-size: 32px;
    color: #f59e0b;
}

.bma-modal-title {
    font-size: 18px;
    font-weight: 600;
    color: #111827;
    margin: 0;
}

.bma-modal-message {
    font-size: 14px;
    color: #4b5563;
    margin-bottom: 24px;
    line-height: 1.5;
}

.bma-modal-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.bma-modal-btn {
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}

.bma-modal-btn.cancel {
    background: #f3f4f6;
    color: #374151;
}

.bma-modal-btn.cancel:hover {
    background: #e5e7eb;
}

.bma-modal-btn.confirm {
    background: #ef4444;
    color: white;
}

.bma-modal-btn.confirm:hover {
    background: #dc2626;
}

.bma-modal-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Toast Notifications */
#bma-toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10001;
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-width: 350px;
}

.bma-toast {
    background: white;
    border-radius: 8px;
    padding: 16px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: flex-start;
    gap: 12px;
    animation: toastSlideIn 0.3s ease-out;
    border-left: 4px solid;
}

@keyframes toastSlideIn {
    from {
        opacity: 0;
        transform: translateX(100px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.bma-toast.success {
    border-left-color: #10b981;
}

.bma-toast.error {
    border-left-color: #ef4444;
}

.bma-toast.info {
    border-left-color: #3b82f6;
}

.bma-toast-icon {
    font-size: 24px;
    flex-shrink: 0;
}

.bma-toast.success .bma-toast-icon {
    color: #10b981;
}

.bma-toast.error .bma-toast-icon {
    color: #ef4444;
}

.bma-toast.info .bma-toast-icon {
    color: #3b82f6;
}

.bma-toast-content {
    flex: 1;
}

.bma-toast-message {
    font-size: 14px;
    color: #374151;
    margin: 0;
    line-height: 1.5;
}

/* Group Booking Styles */
.bma-match-details.grouped {
    border-left: 3px solid #3b82f6;
    padding-left: 8px;
}

.group-icon {
    color: #3b82f6;
    font-size: 1.25rem;
    vertical-align: middle;
    margin: 0 4px;
}

.lead-booking-id {
    font-weight: 600;
    color: #1f2937;
    font-family: monospace;
    background: #e5e7eb;
    padding: 2px 6px;
    border-radius: 4px;
    margin: 0 4px;
}

.btn-manage-group {
    background: #3b82f6;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: background 0.2s;
}

.btn-manage-group:hover {
    background: #2563eb;
}

.btn-manage-group .material-symbols-outlined {
    font-size: 18px;
}
</style>

<!-- Custom Modal Structure -->
<div id="bma-custom-modal">
    <div class="bma-modal-content">
        <div class="bma-modal-header">
            <span class="material-symbols-outlined bma-modal-icon">warning</span>
            <h3 class="bma-modal-title" id="bma-modal-title">Confirm Action</h3>
        </div>
        <p class="bma-modal-message" id="bma-modal-message"></p>
        <div class="bma-modal-actions">
            <button class="bma-modal-btn cancel" id="bma-modal-cancel">Cancel</button>
            <button class="bma-modal-btn confirm" id="bma-modal-confirm">Confirm</button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="bma-toast-container"></div>

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

// Custom Modal System
function showModal(title, message, confirmText = 'Confirm', cancelText = 'Cancel') {
    return new Promise((resolve) => {
        const modal = document.getElementById('bma-custom-modal');
        const titleEl = document.getElementById('bma-modal-title');
        const messageEl = document.getElementById('bma-modal-message');
        const confirmBtn = document.getElementById('bma-modal-confirm');
        const cancelBtn = document.getElementById('bma-modal-cancel');

        titleEl.textContent = title;
        messageEl.textContent = message;
        confirmBtn.textContent = confirmText;
        cancelBtn.textContent = cancelText;

        modal.classList.add('show');

        const handleConfirm = () => {
            cleanup();
            resolve(true);
        };

        const handleCancel = () => {
            cleanup();
            resolve(false);
        };

        const cleanup = () => {
            modal.classList.remove('show');
            confirmBtn.removeEventListener('click', handleConfirm);
            cancelBtn.removeEventListener('click', handleCancel);
        };

        confirmBtn.addEventListener('click', handleConfirm);
        cancelBtn.addEventListener('click', handleCancel);
    });
}

// Toast Notification System
function showToast(message, type = 'success', duration = 4000) {
    const container = document.getElementById('bma-toast-container');

    const toast = document.createElement('div');
    toast.className = `bma-toast ${type}`;

    const iconMap = {
        success: 'check_circle',
        error: 'error',
        info: 'info'
    };

    toast.innerHTML = `
        <span class="material-symbols-outlined bma-toast-icon">${iconMap[type] || 'info'}</span>
        <div class="bma-toast-content">
            <p class="bma-toast-message">${message}</p>
        </div>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'toastSlideIn 0.3s ease-out reverse';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// Toggle create booking form
function toggleCreateForm(date) {
    const formId = 'create-form-' + date;
    const btnId = 'create-btn-' + date;
    const statusId = 'status-' + date;
    const form = document.getElementById(formId);
    const btn = document.getElementById(btnId);
    const status = document.getElementById(statusId);

    if (form && btn) {
        if (form.style.display === 'none' || !form.style.display) {
            form.style.display = 'block';
            btn.style.display = 'none'; // Hide button when form is open
            if (status) status.style.display = 'none'; // Hide status message
            form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            form.style.display = 'none';
            btn.style.display = ''; // Show button when form is closed
            if (status) status.style.display = ''; // Show status message
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
            // Re-enable button on error
            submitBtn.disabled = false;
            submitBtn.textContent = 'Create Booking';
        }
    } catch (error) {
        showFeedback(feedback, `Error: ${error.message}`, 'error');
        // Re-enable button on error
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
async function confirmExcludeMatch(resosBookingId, hotelBookingId, guestName) {
    const confirmed = await showModal(
        'Exclude This Match?',
        `This will add a "NOT-#${hotelBookingId}" note to the ResOS booking for ${guestName}, marking it as excluded from this hotel booking.`,
        'Exclude Match',
        'Cancel'
    );

    if (confirmed) {
        await executeExcludeMatch(resosBookingId, hotelBookingId);
    }
}

// Execute exclude match
async function executeExcludeMatch(resosBookingId, hotelBookingId) {
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
            showToast(`Match excluded successfully! NOT-#${hotelBookingId} note added.`, 'success');
            // Reload the tab
            if (window.parent && window.parent.reloadRestaurantTab) {
                window.parent.reloadRestaurantTab();
            }
        } else {
            showToast(`Error: ${result.message || 'Failed to exclude match'}`, 'error');
        }
    } catch (error) {
        showToast(`Error: ${error.message}`, 'error');
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
async function loadComparisonView(date, bookingId, resosBookingId, buttonElement) {
    const containerId = 'comparison-' + date + '-' + resosBookingId;
    const container = document.getElementById(containerId);

    if (!container) return;

    // If already visible, hide it
    if (container.style.display === 'block') {
        container.style.display = 'none';
        return;
    }

    // Get button data attributes
    const isConfirmed = buttonElement && buttonElement.dataset.isConfirmed === '1';
    const isMatchedElsewhere = buttonElement && buttonElement.dataset.isMatchedElsewhere === '1';
    const hotelBookingId = buttonElement ? buttonElement.dataset.hotelBookingId : '';
    const guestName = buttonElement ? buttonElement.dataset.guestName : '';

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
            const comparisonHTML = buildComparisonHTML(result.comparison, date, resosBookingId, isConfirmed, isMatchedElsewhere, hotelBookingId, guestName);
            container.innerHTML = comparisonHTML;

            // Attach event listeners to suggestion checkboxes for visual feedback
            attachSuggestionCheckboxListeners(container);
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
function buildComparisonHTML(data, date, resosBookingId, isConfirmed, isMatchedElsewhere, hotelBookingId, guestName) {
    const hotel = data.hotel || {};
    const resos = data.resos || {};
    const matches = data.matches || {};
    const suggestions = data.suggested_updates || {};

    let html = '<!-- TEMPLATE VERSION 1.4.0-2025-01-11 -->';
    html += '<div class="comparison-row-content">';
    html += '<div class="comparison-table-wrapper">';
    html += '<div class="comparison-header">Match Comparison [v1.4.0-TEST]</div>';
    html += '<table class="comparison-table">';
    html += '<thead><tr>';
    html += '<th>Field</th>';
    html += '<th>Newbook</th>';
    html += '<th>ResOS</th>';
    html += '</tr></thead>';
    html += '<tbody>';

    // Guest Name row
    html += buildComparisonRow('Name', 'name', hotel.name, resos.name, matches.name, suggestions.name, false);

    // Phone row
    html += buildComparisonRow('Phone', 'phone', hotel.phone, resos.phone, matches.phone, suggestions.phone, false);

    // Email row
    html += buildComparisonRow('Email', 'email', hotel.email, resos.email, matches.email, suggestions.email, false);

    // People row
    html += buildComparisonRow('People', 'people', hotel.people, resos.people, matches.people, suggestions.people, false);

    // Tariff/Package row
    html += buildComparisonRow('Package', 'dbb', hotel.rate_type, resos.dbb, matches.dbb, suggestions.dbb, false);

    // Booking # row
    html += buildComparisonRow('#', 'booking_ref', hotel.booking_id, resos.booking_ref, matches.booking_ref, suggestions.booking_ref, false);

    // Hotel Guest row
    const hotelGuestValue = hotel.is_hotel_guest ? 'Yes' : '-';
    html += buildComparisonRow('Resident', 'hotel_guest', hotelGuestValue, resos.hotel_guest, false, suggestions.hotel_guest, false);

    // Status row
    const statusIcon = getStatusIcon(resos.status || 'request');
    const resosStatusHTML = `<span class="material-symbols-outlined">${statusIcon}</span> ${escapeHTML((resos.status || 'request').charAt(0).toUpperCase() + (resos.status || 'request').slice(1))}`;
    html += buildComparisonRow('Status', 'status', hotel.status, resosStatusHTML, false, suggestions.status, true);

    html += '</tbody>';
    html += '</table>';
    html += '</div>'; // comparison-table-wrapper

    // Add action buttons section
    const hasSuggestions = suggestions && Object.keys(suggestions).length > 0;
    const containerId = 'comparison-' + date + '-' + resosBookingId;

    html += '<div class="comparison-actions-buttons">';

    // 1. Close button (always shown, first)
    html += `<button class="btn-close-comparison" data-action="close-comparison" data-container-id="${containerId}">`;
    html += '<span class="material-symbols-outlined">close</span> Close';
    html += '</button>';

    // 2. Manage Group button (always shown for matched bookings)
    if (resosBookingId) {
        html += `<button class="btn-manage-group" data-action="manage-group" data-resos-booking-id="${resosBookingId}" data-hotel-booking-id="${hotelBookingId}" data-date="${date}">`;
        html += '<span class="material-symbols-outlined">groups</span> Manage Group';
        html += '</button>';
    }

    // 3. Exclude Match button (only for non-confirmed, non-matched-elsewhere matches)
    if (!isConfirmed && !isMatchedElsewhere && resosBookingId && hotelBookingId) {
        html += `<button class="btn-exclude-match" data-action="exclude-match" data-resos-booking-id="${resosBookingId}" data-hotel-booking-id="${hotelBookingId}" data-guest-name="${escapeHTML(guestName || 'Guest')}">`;
        html += '<span class="material-symbols-outlined">close</span> Exclude Match';
        html += '</button>';
    }

    // 4. Update button (only if there are suggested updates)
    if (hasSuggestions) {
        const buttonLabel = isConfirmed ? 'Update Selected' : 'Update Selected & Match';
        const buttonClass = isConfirmed ? 'btn-confirm-match btn-update-confirmed' : 'btn-confirm-match';
        html += `<button class="${buttonClass}" data-action="submit-suggestions" data-date="${date}" data-resos-booking-id="${resos.id}" data-hotel-booking-id="${hotelBookingId}" data-is-confirmed="${isConfirmed}">`;
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

    let hotelDisplay = hotelValue !== undefined && hotelValue !== null && hotelValue !== ''
        ? (isHTML ? hotelValue : escapeHTML(String(hotelValue)))
        : '<em style="color: #adb5bd;">-</em>';

    let resosDisplay = resosValue !== undefined && resosValue !== null && resosValue !== ''
        ? (isHTML ? resosValue : escapeHTML(String(resosValue)))
        : '<em style="color: #adb5bd;">-</em>';

    // Get plain text values for title attributes (tooltips)
    const hotelTitle = hotelValue !== undefined && hotelValue !== null && hotelValue !== ''
        ? String(hotelValue)
        : '';
    const resosTitle = resosValue !== undefined && resosValue !== null && resosValue !== ''
        ? String(resosValue)
        : '';

    // Main comparison row (3 columns: Field, Newbook, ResOS)
    let html = `<tr${matchClass}>`;
    html += `<td><strong>${escapeHTML(label)}</strong></td>`;
    html += hotelTitle ? `<td title="${escapeHTML(hotelTitle)}">${hotelDisplay}</td>` : `<td>${hotelDisplay}</td>`;
    html += resosTitle ? `<td class="resos-value" data-field="${field}" title="${escapeHTML(resosTitle)}">${resosDisplay}</td>` : `<td class="resos-value" data-field="${field}">${resosDisplay}</td>`;
    html += '</tr>';

    // If there's a suggestion, add a suggestion row below
    if (hasSuggestion) {
        const isCheckedByDefault = field !== 'people'; // Uncheck "people" by default, check all others
        const checkedAttr = isCheckedByDefault ? ' checked' : '';

        let suggestionDisplay;
        if (suggestionValue === '') {
            suggestionDisplay = '<em style="color: #999;">(Remove)</em>';
        } else {
            suggestionDisplay = escapeHTML(String(suggestionValue));
        }

        html += `<tr class="suggestion-row">`;
        html += `<td colspan="3">`;
        html += `<div class="suggestion-content">`;
        html += `<label>`;
        html += `<input type="checkbox" class="suggestion-checkbox" name="suggestion_${field}" data-field="${field}" value="${escapeHTML(String(suggestionValue))}"${checkedAttr}> `;
        html += `<span class="suggestion-text" data-field="${field}">Update to: ${suggestionDisplay}</span>`;
        html += `</label>`;
        html += `</div>`;
        html += `</td>`;
        html += `</tr>`;
    }

    return html;
}

// Attach event listeners to suggestion checkboxes for visual feedback
function attachSuggestionCheckboxListeners(container) {
    const checkboxes = container.querySelectorAll('.suggestion-checkbox');

    checkboxes.forEach(checkbox => {
        const field = checkbox.dataset.field;

        // Apply initial state
        updateSuggestionVisualState(container, field, checkbox.checked);

        // Add change listener
        checkbox.addEventListener('change', function() {
            updateSuggestionVisualState(container, field, this.checked);
        });
    });
}

// Update visual state based on checkbox state
function updateSuggestionVisualState(container, field, isChecked) {
    // Find the Resos value cell for this field
    const resosCell = container.querySelector(`.resos-value[data-field="${field}"]`);
    // Find the suggestion text for this field
    const suggestionText = container.querySelector(`.suggestion-text[data-field="${field}"]`);

    if (resosCell) {
        if (isChecked) {
            // Add strikethrough to Resos value
            resosCell.classList.add('overwriting');
        } else {
            // Remove strikethrough from Resos value
            resosCell.classList.remove('overwriting');
        }
    }

    if (suggestionText) {
        if (isChecked) {
            // Normal color for suggestion text
            suggestionText.classList.remove('inactive');
        } else {
            // Grey out suggestion text
            suggestionText.classList.add('inactive');
        }
    }
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

// Submit selected suggestions from comparison checkboxes
async function submitSuggestions(date, resosBookingId, hotelBookingId, isConfirmed) {
    const containerId = 'comparison-' + date + '-' + resosBookingId;
    const container = document.getElementById(containerId);
    if (!container) return;

    // Find all checked suggestion checkboxes in this comparison container
    const checkboxes = container.querySelectorAll('.suggestion-checkbox:checked');

    if (checkboxes.length === 0) {
        showToast('Please select at least one suggestion to update', 'error');
        return;
    }

    // Build updates object from checked checkboxes
    const updates = {};
    checkboxes.forEach(checkbox => {
        const name = checkbox.name.replace('suggestion_', '');
        let value = checkbox.value;

        // Handle special mappings
        if (name === 'name') {
            updates.guest_name = value;
        } else if (name === 'booking_ref') {
            updates.booking_ref = value;
        } else if (name === 'hotel_guest') {
            updates.hotel_guest = value;
        } else if (name === 'dbb') {
            updates.dbb = value; // Empty string means remove
        } else if (name === 'people') {
            updates.people = parseInt(value);
        } else if (name === 'status') {
            updates.status = value;
        } else {
            updates[name] = value;
        }
    });

    // Find the submit button to show loading state
    const submitBtn = container.querySelector('.btn-confirm-match');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Updating...';
    }

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
            // Show success message
            showToast('✓ Booking updated successfully!', 'success');

            // Reload the tab after 1 second to show updated data
            setTimeout(() => {
                if (window.parent && window.parent.reloadRestaurantTab) {
                    window.parent.reloadRestaurantTab();
                }
            }, 1000);
        } else {
            showToast(`Error: ${result.message || 'Failed to update booking'}`, 'error');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = isConfirmed ? 'Update Selected' : 'Update Selected & Match';
            }
        }
    } catch (error) {
        showToast(`Error: ${error.message}`, 'error');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = isConfirmed ? 'Update Selected' : 'Update Selected & Match';
        }
    }
}

// Attach event listeners using event delegation
// Note: Attach immediately since this template is injected after page load
(function() {
    // Event listener for "Open Booking in NewBook" buttons
    document.body.addEventListener('click', function(event) {
        const button = event.target.closest('.open-booking-btn');
        if (button && button.dataset.bookingId) {
            const bookingId = button.dataset.bookingId;
            const newbookUrl = `https://appeu.newbook.cloud/bookings_view/${bookingId}`;

            // Send message to background script to open in current tab
            if (window.parent && window.parent.chrome && window.parent.chrome.runtime) {
                window.parent.chrome.runtime.sendMessage({
                    action: 'openNewBookBooking',
                    url: newbookUrl
                });
            } else {
                // Fallback: open in current window/tab
                window.open(newbookUrl, '_self');
            }
        }
    });

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

            case 'manage-group':
                if (window.parent && window.parent.openGroupManagementModal) {
                    window.parent.openGroupManagementModal(
                        button.dataset.resosBookingId,
                        button.dataset.hotelBookingId,
                        button.dataset.date
                    );
                } else if (window.openGroupManagementModal) {
                    window.openGroupManagementModal(
                        button.dataset.resosBookingId,
                        button.dataset.hotelBookingId,
                        button.dataset.date
                    );
                }
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
                    button.dataset.resosBookingId,
                    button
                );
                break;

            case 'close-comparison':
                closeComparison(button.dataset.containerId);
                break;

            case 'submit-suggestions':
                submitSuggestions(
                    button.dataset.date,
                    button.dataset.resosBookingId,
                    button.dataset.hotelBookingId,
                    button.dataset.isConfirmed === 'true'
                );
                break;
        }
    });

    console.log('BMA: Event listeners attached to document.body');
})();
</script>
