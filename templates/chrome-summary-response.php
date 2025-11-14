<?php
/**
 * Chrome Extension Sidepanel - Summary Tab Template
 * Displays recent bookings with expandable details
 */

// $bookings array is passed from formatter
if (empty($bookings)) {
    echo '<div class="bma-summary-empty">';
    echo '<div class="empty-icon">✓</div>';
    echo '<p>No recent bookings found</p>';
    echo '<div class="empty-text">All bookings are up to date</div>';
    echo '</div>';
    return;
}
?>

<div class="bma-summary">
    <?php foreach ($bookings as $booking):
        $group_id = $booking['group_id'] ?? null;
    ?>
        <div class="booking-card" data-booking-id="<?php echo esc_attr($booking['booking_id']); ?>" data-booking-placed="<?php echo esc_attr($booking['booking_placed'] ?? ''); ?>"<?php if ($group_id): ?> data-group-id="<?php echo esc_attr($group_id); ?>"<?php endif; ?>>
            <!-- Collapsed Summary -->
            <div class="booking-header">
                <div class="booking-main-info">
                    <div class="booking-guest">
                        <strong><?php echo esc_html($booking['guest_name']); ?></strong>
                    </div>
                    <div class="booking-dates-compact">
                        <span><?php echo esc_html(date('D, d/m/y', strtotime($booking['arrival_date']))); ?></span>
                        <span class="nights-badge"><?php echo esc_html($booking['nights']); ?> night<?php echo $booking['nights'] > 1 ? 's' : ''; ?></span>
                        <?php if ($group_id): ?>
                            <span class="group-id-badge">G#<?php echo esc_html($group_id); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Issue Badge -->
                <div class="booking-badges">
                    <?php
                    $total_issues = $booking['critical_count'] + $booking['warning_count'];
                    if ($total_issues > 0):
                        $badge_class = $booking['critical_count'] > 0 ? 'critical-badge' : 'warning-badge';
                        $title = $booking['critical_count'] . ' critical, ' . $booking['warning_count'] . ' warnings';
                    ?>
                        <span class="issue-count-badge <?php echo $badge_class; ?>" title="<?php echo esc_attr($title); ?>">
                            <?php echo esc_html($total_issues); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <span class="expand-icon">▼</span>

                <!-- Time Since Placed -->
                <?php if (!empty($booking['booking_placed'])): ?>
                    <div class="time-since-placed" data-placed-time="<?php echo esc_attr($booking['booking_placed']); ?>"></div>
                <?php endif; ?>
            </div>

            <!-- Expanded Details -->
            <div class="booking-details" id="details-<?php echo esc_attr($booking['booking_id']); ?>" style="display: none;">
                <!-- Compressed Booking Info -->
                <div class="compact-details">
                    <div class="compact-row">
                        <span>Booking ID: #<?php echo esc_html($booking['booking_id']); ?></span>
                        <span class="compact-occupants">
                            <?php
                            $occ = $booking['occupants'];
                            $parts = array();
                            if ($occ['adults'] > 0) $parts[] = $occ['adults'] . ' Adult' . ($occ['adults'] > 1 ? 's' : '');
                            if ($occ['children'] > 0) $parts[] = $occ['children'] . ' Child' . ($occ['children'] > 1 ? 'ren' : '');
                            if ($occ['infants'] > 0) $parts[] = $occ['infants'] . ' Infant' . ($occ['infants'] > 1 ? 's' : '');
                            echo esc_html(implode(', ', $parts));
                            ?>
                        </span>
                    </div>
                    <div class="compact-row">
                        <span>Dates: <?php echo esc_html(date('D d/m', strtotime($booking['arrival_date']))); ?> - <?php echo esc_html(date('D d/m', strtotime($booking['departure_date']))); ?></span>
                        <span class="nights-badge"><?php echo esc_html($booking['nights']); ?> night<?php echo $booking['nights'] > 1 ? 's' : ''; ?></span>
                    </div>
                    <div class="compact-row">
                        <span>Tariff: <?php echo esc_html(empty($booking['tariffs']) ? 'Standard' : implode(', ', $booking['tariffs'])); ?></span>
                    </div>
                    <div class="compact-row">
                        <span>Status: <?php echo esc_html(ucfirst($booking['status'])); ?></span>
                        <span><?php echo esc_html($booking['booking_source']); ?></span>
                    </div>
                </div>

                <!-- Restaurant Section -->
                <div class="detail-separator"></div>
                <div class="detail-section restaurant-section">
                    <h4 class="restaurant-header-link" data-booking-id="<?php echo esc_attr($booking['booking_id']); ?>">
                        <span class="material-symbols-outlined">restaurant</span>
                        Restaurant
                        <span class="material-symbols-outlined arrow-icon">arrow_forward</span>
                    </h4>
                    <div class="restaurant-nights">
                        <?php
                        $match_details = $booking['match_details'];
                        foreach ($match_details['nights'] as $night):
                            $night_date = $night['date'];
                            $has_package = $night['has_package'] ?? false;
                            $matches = $night['resos_matches'] ?? array();
                            $match_count = count($matches);
                            $is_stale = $night['is_stale'] ?? false;
                        ?>
                            <?php if ($match_count === 0): ?>
                                <!-- No booking -->
                                <div class="night-row clickable-issue create-booking-link" data-booking-id="<?php echo esc_attr($booking['booking_id']); ?>" data-date="<?php echo esc_attr($night_date); ?>" title="Click to create booking in Restaurant tab">
                                    <span class="night-date"><?php echo esc_html(date('D, d/m', strtotime($night_date))); ?>:</span>
                                    <span class="night-status" style="color: #6b7280;">No booking</span>
                                    <span class="status-icon <?php echo $has_package ? 'critical' : 'ok'; ?>">
                                        <?php if ($is_stale): ?>
                                            <span class="material-symbols-outlined stale-indicator" title="Data from cache - may be outdated">sync_problem</span>
                                        <?php else: ?>
                                            <span class="material-symbols-outlined">add</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php if ($has_package): ?>
                                    <div class="night-alert critical-alert">
                                        <span class="material-symbols-outlined">flag</span>
                                        Package booking - missing reservation
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($match_count === 1): ?>
                                <!-- Single match -->
                                <?php
                                $match = $matches[0];
                                $is_primary = $match['match_info']['is_primary'] ?? false;
                                $is_group_member = $match['match_info']['is_group_member'] ?? false;
                                $has_suggestions = $match['has_suggestions'] ?? false;
                                $time = date('H:i', strtotime($match['time']));
                                $pax = $match['people'] ?? 0;
                                $lead_room = $match['match_info']['lead_booking_room'] ?? 'N/A';
                                $resos_id = $match['resos_booking_id'] ?? '';
                                $restaurant_id = $match['restaurant_id'] ?? '';
                                ?>
                                <div class="night-row <?php echo $is_group_member ? 'resos-deep-link' : 'clickable-issue'; ?>"
                                     data-booking-id="<?php echo esc_attr($booking['booking_id']); ?>"
                                     data-date="<?php echo esc_attr($night_date); ?>"
                                     <?php if ($is_group_member): ?>
                                         data-resos-id="<?php echo esc_attr($resos_id); ?>"
                                         data-restaurant-id="<?php echo esc_attr($restaurant_id); ?>"
                                         title="Group booking - click to view in ResOS"
                                     <?php else: ?>
                                         data-resos-id="<?php echo esc_attr($resos_id); ?>"
                                         title="<?php echo $has_suggestions ? 'Has suggested updates - click to review in Restaurant tab' : 'Click to view in Restaurant tab'; ?>"
                                     <?php endif; ?>>
                                    <span class="night-date"><?php echo esc_html(date('D, d/m', strtotime($night_date))); ?>:</span>
                                    <span class="night-time" style="color: <?php echo $is_group_member ? '#10b981' : ($has_suggestions ? '#3b82f6' : ($is_primary ? '#10b981' : '#f59e0b')); ?>;">
                                        <?php if ($is_group_member): ?>
                                            <?php echo esc_html($time); ?> with <?php echo esc_html($lead_room); ?>
                                        <?php else: ?>
                                            <?php echo esc_html($time); ?>, <?php echo esc_html($pax); ?> pax
                                        <?php endif; ?>
                                    </span>
                                    <span class="status-icon <?php echo (($is_primary && !$has_suggestions) || $is_group_member) ? 'ok' : ($has_suggestions ? 'updates' : 'warning'); ?>">
                                        <?php if ($is_stale): ?>
                                            <span class="material-symbols-outlined stale-indicator" title="Data from cache - may be outdated">sync_problem</span>
                                        <?php elseif ($is_group_member): ?>
                                            <span class="material-symbols-outlined" style="color: #10b981;">groups</span>
                                        <?php elseif ($has_suggestions): ?>
                                            <span class="material-symbols-outlined" style="color: #3b82f6;">sync</span>
                                            <span class="material-symbols-outlined" style="color: #10b981;">check</span>
                                        <?php else: ?>
                                            <span class="material-symbols-outlined" style="color: <?php echo $is_primary ? '#10b981' : '#f59e0b'; ?>;"><?php echo $is_primary ? 'check' : 'search'; ?></span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php if (!$is_primary): ?>
                                    <div class="night-alert warning-alert">
                                        <span class="material-symbols-outlined">warning</span>
                                        Suggested match - low confidence
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <!-- Multiple matches -->
                                <?php foreach ($matches as $match):
                                    $is_primary = $match['match_info']['is_primary'] ?? false;
                                    $time = date('H:i', strtotime($match['time']));
                                    $pax = $match['people'] ?? 0;
                                    $resos_id = $match['resos_booking_id'] ?? '';
                                    $restaurant_id = $match['restaurant_id'] ?? '';
                                ?>
                                    <div class="night-row clickable-issue"
                                         data-booking-id="<?php echo esc_attr($booking['booking_id']); ?>"
                                         data-resos-id="<?php echo esc_attr($resos_id); ?>"
                                         data-date="<?php echo esc_attr($night_date); ?>"
                                         title="Click to view in Restaurant tab">
                                        <span class="night-date"><?php echo esc_html(date('D, d/m', strtotime($night_date))); ?>:</span>
                                        <span class="night-time" style="color: <?php echo $is_primary ? '#10b981' : '#f59e0b'; ?>;"><?php echo esc_html($time); ?>, <?php echo esc_html($pax); ?> pax</span>
                                        <span class="status-icon <?php echo $is_primary ? 'ok' : 'warning'; ?>">
                                            <?php if ($is_stale): ?>
                                                <span class="material-symbols-outlined stale-indicator" title="Data from cache - may be outdated">sync_problem</span>
                                            <?php else: ?>
                                                <span class="material-symbols-outlined"><?php echo $is_primary ? 'check' : 'search'; ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                                <div class="night-alert warning-alert">
                                    <span class="material-symbols-outlined">warning</span>
                                    Multiple matches - needs review
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Issues/Checks Section -->
                <div class="detail-separator"></div>
                <div class="detail-section checks-section">
                    <h4 class="checks-header-link" data-booking-id="<?php echo esc_attr($booking['booking_id']); ?>">
                        <span class="material-symbols-outlined">check_circle</span>
                        Issues/Checks
                        <span class="material-symbols-outlined arrow-icon">arrow_forward</span>
                    </h4>
                    <p class="placeholder-text">Coming soon...</p>
                </div>

                <!-- Action Button -->
                <div class="detail-actions">
                    <button class="open-booking-btn" data-booking-id="<?php echo esc_attr($booking['booking_id']); ?>">
                        <span class="material-symbols-outlined">arrow_back</span>
                        Open Booking in NewBook
                    </button>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<style>
.bma-summary { padding: 0; }

.bma-summary-empty {
    text-align: center;
    padding: 60px 20px;
}

.empty-icon {
    font-size: 64px;
    margin-bottom: 16px;
    opacity: 0.5;
}

.bma-summary-empty p {
    font-size: 16px;
    font-weight: 500;
    color: #374151;
    margin: 0 0 8px 0;
}

.empty-text {
    font-size: 13px;
    color: #9ca3af;
}

.booking-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 8px;
    transition: all 0.2s;
    position: relative;
}

.booking-card:hover {
    border-color: #cbd5e0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.booking-card.expanded {
    border-color: #3182ce;
}

.booking-card.new-booking {
    border: 2px solid #10b981;
    box-shadow: 0 0 12px rgba(16, 185, 129, 0.3);
    animation: glow 2s ease-in-out infinite;
}

@keyframes glow {
    0%, 100% {
        box-shadow: 0 0 12px rgba(16, 185, 129, 0.3);
    }
    50% {
        box-shadow: 0 0 20px rgba(16, 185, 129, 0.5);
    }
}

.booking-header {
    padding: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    position: relative;
}

.booking-main-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
}

.booking-guest strong {
    font-size: 14px;
    color: #2d3748;
    line-height: 1.2;
}

.booking-dates-compact {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: #4a5568;
    line-height: 1.2;
}

.nights-badge {
    background: #edf2f7;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}

.booking-badges {
    display: flex;
    gap: 6px;
    align-self: flex-start;
    position: absolute;
    top: 4px;
    right: 4px;
}

/* Combined circular issue count badge */
.issue-count-badge {
    min-width: 18px;
    height: 18px;
    padding: 0 4px;
    border-radius: 50%;
    font-size: 10px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

.issue-count-badge.critical-badge {
    background: #dc2626;
    color: white;
}

.issue-count-badge.warning-badge {
    background: #f59e0b;
    color: white;
}

.expand-icon {
    font-size: 12px;
    color: #a0aec0;
    transition: transform 0.2s;
}

.booking-details {
    padding: 0 8px 8px 8px;
    border-top: 1px solid #e2e8f0;
}

/* Compact Details Section */
.compact-details {
    margin-top: 8px;
}

.compact-row {
    display: flex;
    justify-content: space-between;
    padding: 3px 0;
    font-size: 12px;
    color: #4a5568;
}

.compact-occupants {
    font-weight: 500;
    color: #2d3748;
}

/* Separator */
.detail-separator {
    height: 1px;
    background: #e2e8f0;
    margin: 8px 0;
}

/* Detail Sections */
.detail-section {
    margin-bottom: 8px;
}

.detail-section h4 {
    margin: 0 0 6px 0;
    font-size: 13px;
    color: #2d3748;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
}

.detail-section h4 .material-symbols-outlined {
    font-size: 16px;
}

/* Clickable Restaurant Header */
.restaurant-header-link {
    cursor: pointer;
    transition: all 0.2s;
    padding: 4px 8px;
    margin: 0 -8px 6px -8px;
    border-radius: 6px;
}

.restaurant-header-link:hover {
    background: #f3f4f6;
    color: #3b82f6;
}

.restaurant-header-link .arrow-icon {
    margin-left: auto;
    font-size: 16px;
    opacity: 0.6;
}

.restaurant-header-link:hover .arrow-icon {
    opacity: 1;
    transform: translateX(4px);
    transition: all 0.2s;
}

/* Clickable Checks Header */
.checks-header-link {
    cursor: pointer;
    transition: all 0.2s;
    padding: 4px 8px;
    margin: 0 -8px 6px -8px;
    border-radius: 6px;
}

.checks-header-link:hover {
    background: #f3f4f6;
    color: #3b82f6;
}

.checks-header-link .arrow-icon {
    margin-left: auto;
    font-size: 16px;
    opacity: 0.6;
}

.checks-header-link:hover .arrow-icon {
    opacity: 1;
    transform: translateX(4px);
    transition: all 0.2s;
}

/* Restaurant Nights */
.restaurant-nights {
    font-size: 12px;
}

.night-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 0;
}

.night-date {
    font-weight: 500;
    font-size: 12px;
    color: #2d3748;
    min-width: 70px;
}

.night-time {
    flex: 1;
    color: #4a5568;
    font-size: 12px;
    font-weight: 400;
}

.night-status {
    flex: 1;
    color: #4a5568;
    font-size: 12px;
    font-weight: 400;
}

.status-icon {
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.status-icon.ok {
    color: #10b981;
}

.status-icon.warning {
    color: #f59e0b;
}

.status-icon.critical {
    color: #dc2626;
}

.status-icon .material-symbols-outlined {
    font-size: 16px;
}

.night-alert {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 8px;
    margin: 4px 0 6px 0;
    border-radius: 4px;
    font-size: 11px;
}

.night-alert .material-symbols-outlined {
    font-size: 14px;
}

.critical-alert {
    background: #fee2e2;
    color: #991b1b;
}

.warning-alert {
    background: #fef3c7;
    color: #92400e;
}

/* Clickable Issue Rows */
.clickable-issue {
    cursor: pointer;
    transition: background-color 0.2s, transform 0.1s;
    text-decoration: none;
}

.clickable-issue:hover {
    background-color: rgba(59, 130, 246, 0.1);
    transform: translateX(2px);
    text-decoration: none;
}

.clickable-issue:active {
    transform: translateX(0);
}

/* Prevent underline on child elements */
.clickable-issue:hover * {
    text-decoration: none;
}

/* ResOS Deep Link Rows */
.resos-deep-link {
    cursor: pointer;
    transition: background-color 0.2s, transform 0.1s;
    text-decoration: none;
}

.resos-deep-link:hover {
    background-color: rgba(16, 185, 129, 0.1);
    transform: translateX(2px);
    text-decoration: none;
}

.resos-deep-link:active {
    transform: translateX(0);
}

/* Prevent underline on child elements */
.resos-deep-link:hover * {
    text-decoration: none;
}

/* Checks Section */
.checks-section .placeholder-text {
    font-size: 11px;
    color: #9ca3af;
    font-style: italic;
    margin: 0;
}

/* Action Button */
.detail-actions {
    margin-top: 12px;
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

/* Time Since Placed */
.time-since-placed {
    position: absolute;
    bottom: 4px;
    right: 8px;
    font-size: 10px;
    color: #9ca3af;
    font-style: italic;
}

.booking-card.new-booking .time-since-placed {
    color: #10b981;
    font-weight: 600;
}

/* Stale cache indicator styling */
.stale-indicator {
    color: #f59e0b !important;
    opacity: 0.9;
}

/* Clickable status links for matches */
.clickable-status {
    color: inherit;
    text-decoration: none !important;
    display: inline-flex !important;
    align-items: center;
    gap: 4px;
    width: fit-content !important;
    max-width: fit-content !important;
    flex: none !important;
    padding: 2px 6px;
    border-radius: 4px;
    transition: background-color 0.2s ease;
}

.clickable-status:hover {
    background-color: rgba(59, 130, 246, 0.1);
    text-decoration: none !important;
}

.clickable-status.has-updates:hover {
    background-color: rgba(59, 130, 246, 0.15);
}

.clickable-status.has-issue:hover {
    background-color: rgba(251, 191, 36, 0.1);
}

.status-icon {
    display: inline-flex;
    align-items: center;
    gap: 2px;
}

/* Group ID badge styling */
.group-id-badge {
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    background-color: #e0e7ff;
    color: #3730a3;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    margin-left: 6px;
}

.group-id-badge:hover {
    background-color: #c7d2fe;
    color: #312e81;
}

/* Highlighted state for grouped bookings - only affect header background */
.booking-header.highlighted {
    background-color: #eef2ff !important;
    /* Keep existing borders and radius - just change background */
    /* Badge highlighting is separate - only on direct hover or when filtering */
}
</style>

<!-- Material Symbols Icons -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
