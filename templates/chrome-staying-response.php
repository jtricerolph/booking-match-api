<?php
/**
 * Chrome Extension Sidepanel - Staying Tab Template
 * Displays bookings staying on a specific date
 *
 * Variables available:
 * - $bookings: Array of staying bookings
 * - $date: Target date (YYYY-MM-DD)
 */

if (empty($bookings)) {
    echo '<div class="staying-empty">';
    echo '<div class="empty-icon">🛏️</div>';
    echo '<p>No bookings staying on this date</p>';
    echo '</div>';
    return;
}
?>

<div class="staying-list">
    <?php foreach ($bookings as $booking):
        // Check if this is a vacant room entry
        if (isset($booking['is_vacant']) && $booking['is_vacant'] === true):
            $room_number = $booking['site_name'] ?? 'N/A';
    ?>
            <!-- Vacant Room Line -->
            <div class="vacant-room-line">
                <span class="room-number"><?php echo esc_html($room_number); ?></span>
                <span class="vacant-label">- Vacant</span>
            </div>
    <?php
            continue; // Skip to next iteration
        endif;

        // Regular booking entry
        $status = strtolower($booking['status'] ?? 'confirmed');
        $group_id = $booking['group_id'] ?? null;
        $room_number = $booking['site_name'] ?? 'N/A';
        $current_night = $booking['current_night'] ?? 1;
        $total_nights = $booking['nights'] ?? 1;
        $matches = $booking['resos_matches'] ?? [];
        $has_package = $booking['has_package'] ?? false;
        $is_stale = $booking['is_stale'] ?? false;
    ?>
        <div class="staying-card"
             data-booking-id="<?php echo esc_attr($booking['booking_id']); ?>"
             data-status="<?php echo esc_attr($status); ?>"
             <?php if ($group_id): ?>data-group-id="<?php echo esc_attr($group_id); ?>"<?php endif; ?>>

            <!-- Card Header (Collapsed View) -->
            <div class="staying-header">
                <div class="staying-main-info">
                    <!-- Line 1: Room + Guest Name + Night Progress -->
                    <div class="staying-guest-line">
                        <span class="room-number"><?php echo esc_html($room_number); ?></span>
                        <span class="guest-name"><?php echo esc_html($booking['guest_name']); ?></span>
                        <span class="night-progress">
                            <span class="material-symbols-outlined">bedtime</span>
                            <?php echo $current_night; ?>/<?php echo $total_nights; ?>
                        </span>
                    </div>

                    <!-- Line 2: Restaurant Status -->
                    <div class="staying-restaurant-line">
                        <span class="material-symbols-outlined">restaurant</span>
                        <?php if (empty($matches)): ?>
                            <span class="restaurant-status <?php echo $has_package ? 'has-package' : 'no-booking'; ?> create-booking-link"
                                  data-booking-id="<?php echo esc_attr($booking['booking_id']); ?>"
                                  data-date="<?php echo esc_attr($date); ?>"
                                  title="Click to create booking in Restaurant tab">
                                No booking
                                <?php if ($is_stale): ?>
                                    <span class="material-symbols-outlined stale-indicator" title="Data from cache - may be outdated">sync_problem</span>
                                <?php else: ?>
                                    <span class="material-symbols-outlined"><?php echo $has_package ? 'flag' : 'add'; ?></span>
                                <?php endif; ?>
                            </span>
                        <?php elseif (count($matches) === 1 && ($matches[0]['match_info']['is_primary'] ?? false)): ?>
                            <?php
                            $match = $matches[0];
                            $time = date('H:i', strtotime($match['time']));
                            $pax = $match['people'] ?? 0;
                            $has_suggestions = $match['has_suggestions'] ?? false;
                            $resos_id = $match['resos_booking_id'] ?? '';
                            ?>
                            <?php if ($has_suggestions): ?>
                                <a href="#" class="restaurant-status has-booking has-updates clickable-status" data-tab="restaurant" data-date="<?php echo esc_attr($night['date']); ?>" data-resos-id="<?php echo esc_attr($resos_id); ?>" title="Has suggested updates - click to review">
                                    <?php echo esc_html($time); ?>, <?php echo esc_html($pax); ?> pax
                                    <span class="material-symbols-outlined" style="color: #3b82f6;">sync</span>
                                    <span class="material-symbols-outlined" style="color: #10b981;">check</span>
                                </a>
                            <?php else: ?>
                                <span class="restaurant-status has-booking">
                                    <?php echo esc_html($time); ?>, <?php echo esc_html($pax); ?> pax
                                    <?php if ($is_stale): ?>
                                        <span class="material-symbols-outlined stale-indicator" title="Data from cache - may be outdated">sync_problem</span>
                                    <?php else: ?>
                                        <span class="material-symbols-outlined">check</span>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php
                            $match = $matches[0];
                            $time = date('H:i', strtotime($match['time']));
                            $pax = $match['people'] ?? 0;
                            $resos_id = $match['resos_booking_id'] ?? '';
                            ?>
                            <a href="#" class="restaurant-status has-issue clickable-status" data-tab="restaurant" data-date="<?php echo esc_attr($night['date']); ?>" data-resos-id="<?php echo esc_attr($resos_id); ?>" title="Suggested match - click to review">
                                <?php echo esc_html($time); ?>, <?php echo esc_html($pax); ?> pax
                                <?php if ($is_stale): ?>
                                    <span class="material-symbols-outlined stale-indicator" title="Data from cache - may be outdated">sync_problem</span>
                                <?php else: ?>
                                    <span class="material-symbols-outlined">search</span>
                                <?php endif; ?>
                            </a>
                        <?php endif; ?>

                        <?php if ($group_id): ?>
                            <span class="group-id-badge">G#<?php echo esc_html($group_id); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Issue Badges -->
                <div class="staying-badges">
                    <?php if ($booking['critical_count'] > 0): ?>
                        <span class="issue-badge critical-badge">
                            <span class="material-symbols-outlined">warning</span>
                            <?php echo esc_html($booking['critical_count']); ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($booking['warning_count'] > 0): ?>
                        <span class="issue-badge warning-badge">
                            <span class="material-symbols-outlined">flag</span>
                            <?php echo esc_html($booking['warning_count']); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <span class="staying-expand-icon">▼</span>
            </div>

            <!-- Card Details (Expanded View - Same as Summary Tab) -->
            <div class="staying-details">
                <!-- Compressed Booking Info -->
                <div class="compact-details">
                    <div class="compact-row">
                        <span>Booking ID: #<?php echo esc_html($booking['booking_id']); ?></span>
                        <span class="compact-occupants">
                            <?php
                            $occ = $booking['occupants'] ?? ['adults' => 0, 'children' => 0, 'infants' => 0];
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
                        <span><?php echo esc_html($booking['booking_source'] ?? 'Unknown'); ?></span>
                    </div>
                </div>

                <!-- Restaurant Section (same as Summary tab) -->
                <div class="detail-separator"></div>
                <div class="detail-section restaurant-section">
                    <h4 class="restaurant-header-link" data-booking-id="<?php echo esc_attr($booking['booking_id']); ?>">
                        <span class="material-symbols-outlined">restaurant</span>
                        Restaurant
                        <span class="material-symbols-outlined arrow-icon">arrow_forward</span>
                    </h4>
                    <div class="restaurant-nights">
                        <!-- Show only the current night's restaurant status -->
                        <?php
                        $match_count = count($matches);
                        $night_date = $date;
                        ?>
                        <?php if ($match_count === 0): ?>
                            <div class="night-row create-booking-link clickable-issue"
                                 data-booking-id="<?php echo esc_attr($booking['booking_id']); ?>"
                                 data-date="<?php echo esc_attr($night_date); ?>"
                                 title="Click to create booking in Restaurant tab">
                                <span class="night-date"><?php echo esc_html(date('D, d/m', strtotime($night_date))); ?>:</span>
                                <span class="night-status">No booking</span>
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
                        <?php else: ?>
                            <?php foreach ($matches as $match):
                                $is_primary = $match['match_info']['is_primary'] ?? false;
                                $time = date('H:i', strtotime($match['time']));
                                $pax = $match['people'] ?? 0;
                                $resos_id = $match['resos_booking_id'] ?? '';
                                $restaurant_id = $match['restaurant_id'] ?? '';
                                $has_suggestions = $match['has_suggestions'] ?? false;
                            ?>
                                <div class="night-row <?php echo ($is_primary && !$has_suggestions) ? 'resos-deep-link' : 'clickable-issue'; ?>"
                                     data-booking-id="<?php echo esc_attr($booking['booking_id']); ?>"
                                     data-date="<?php echo esc_attr($night_date); ?>"
                                     <?php if ($is_primary && !$has_suggestions): ?>
                                         data-resos-id="<?php echo esc_attr($resos_id); ?>"
                                         data-restaurant-id="<?php echo esc_attr($restaurant_id); ?>"
                                         title="Click to view in ResOS"
                                     <?php else: ?>
                                         data-resos-id="<?php echo esc_attr($resos_id); ?>"
                                         title="<?php echo $has_suggestions ? 'Has suggested updates - click to review in Restaurant tab' : 'Click to view in Restaurant tab'; ?>"
                                     <?php endif; ?>>
                                    <span class="night-date"><?php echo esc_html(date('D, d/m', strtotime($night_date))); ?>:</span>
                                    <span class="night-time"><?php echo esc_html($time); ?>, <?php echo esc_html($pax); ?> pax</span>
                                    <span class="status-icon <?php echo ($is_primary && !$has_suggestions) ? 'ok' : (($is_primary && $has_suggestions) ? 'updates' : 'warning'); ?>">
                                        <?php if ($is_stale): ?>
                                            <span class="material-symbols-outlined stale-indicator" title="Data from cache - may be outdated">sync_problem</span>
                                        <?php elseif ($is_primary && $has_suggestions): ?>
                                            <span class="material-symbols-outlined" style="color: #3b82f6;">sync</span>
                                            <span class="material-symbols-outlined" style="color: #10b981;">check</span>
                                        <?php else: ?>
                                            <span class="material-symbols-outlined"><?php echo $is_primary ? 'check' : 'search'; ?></span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($match_count > 1 || !($matches[0]['match_info']['is_primary'] ?? false)): ?>
                                <div class="night-alert warning-alert">
                                    <span class="material-symbols-outlined">warning</span>
                                    <?php echo $match_count > 1 ? 'Multiple matches - needs review' : 'Suggested match - low confidence'; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
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
                <div class="detail-separator"></div>
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
.staying-empty {
    text-align: center;
    padding: 60px 20px;
}

.staying-empty .empty-icon {
    font-size: 64px;
    margin-bottom: 16px;
    opacity: 0.5;
}

.staying-empty p {
    font-size: 16px;
    font-weight: 500;
    color: #374151;
}

/* Staying card layout - match summary tab spacing */
.staying-card {
    background: #fff;
    border: 1px solid #e5e7eb !important;
    border-radius: 8px;
    margin-bottom: 8px;
    transition: all 0.2s;
    position: relative;
}

.staying-card:hover {
    border-color: #cbd5e0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.staying-card.expanded {
    border-color: #3182ce;
}

.staying-header {
    padding: 8px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    position: relative;
}

.staying-main-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.staying-guest-line {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
}

.guest-name {
    font-weight: 500;
    color: #2d3748;
}

.night-progress {
    color: #718096;
    font-size: 12px;
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.night-progress .material-symbols-outlined {
    font-size: 16px;
    vertical-align: middle;
}

.staying-restaurant-line {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #4a5568;
}

.staying-restaurant-line .material-symbols-outlined {
    font-size: 16px;
    color: #718096;
}

.staying-details {
    padding: 0 8px 8px 8px;
    border-top: 1px solid #e2e8f0;
}

.staying-expand-icon {
    font-size: 12px;
    color: #718096;
    transition: transform 0.2s;
    margin-left: 8px;
    align-self: center;
}

.staying-card.expanded .staying-expand-icon {
    transform: rotate(180deg);
}

/* Clickable Headers - ensure arrow is right-aligned */
.restaurant-header-link .arrow-icon,
.checks-header-link .arrow-icon {
    margin-left: auto;
    font-size: 16px;
    opacity: 0.6;
}

.restaurant-header-link:hover .arrow-icon,
.checks-header-link:hover .arrow-icon {
    opacity: 1;
    transform: translateX(4px);
    transition: all 0.2s;
}

/* Make "No booking" status clickable in collapsed view */
.restaurant-status.create-booking-link {
    cursor: pointer;
    transition: all 0.2s;
    padding: 2px 4px;
    border-radius: 3px;
    flex: none;
    display: inline-flex;
    text-decoration: none;
    font-size: 12px;
    align-items: center;
}

.restaurant-status.create-booking-link:hover {
    background-color: rgba(59, 130, 246, 0.1);
    text-decoration: none;
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

/* Position badges at bottom right under Night x/y */
.staying-badges {
    position: absolute;
    bottom: 4px;
    right: 40px;
    display: flex;
    gap: 6px;
    align-items: center;
}

.issue-badge {
    padding: 3px 6px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 3px;
}

.issue-badge .material-symbols-outlined {
    font-size: 12px;
}

.critical-badge {
    background: #fee2e2;
    color: #991b1b;
}

.warning-badge {
    background: #fef3c7;
    color: #92400e;
}

/* Stale cache indicator styling */
.stale-indicator {
    color: #f59e0b !important;
    opacity: 0.9;
}

/* Status badge styling - room number shows booking status */
.staying-card .room-number {
    padding: 4px 8px !important;
    border-radius: 4px !important;
    font-weight: 600 !important;
    font-size: 13px !important;
    display: inline-block !important;
    background-color: #f3f4f6 !important; /* Default gray background */
    color: #4b5563 !important; /* Default gray text */
}

/* Vacant room numbers don't get badge styling */
.vacant-room-line .room-number {
    padding: 0 !important;
    border-radius: 0 !important;
    background-color: transparent !important;
    color: inherit !important;
}

/* Confirmed status - green badge */
.staying-card[data-status="confirmed"] .room-number {
    background-color: #d1fae5 !important;
    color: #065f46 !important;
}

/* Checked-in status - blue badge */
.staying-card[data-status="checked-in"] .room-number,
.staying-card[data-status="checked_in"] .room-number {
    background-color: #dbeafe !important;
    color: #1e40af !important;
}

/* Checked-out status - gray badge */
.staying-card[data-status="checked-out"] .room-number,
.staying-card[data-status="checked_out"] .room-number {
    background-color: #f3f4f6 !important;
    color: #4b5563 !important;
}

/* Cancelled status - red badge */
.staying-card[data-status="cancelled"] .room-number {
    background-color: #fee2e2 !important;
    color: #991b1b !important;
}

/* Provisional status - yellow/orange badge */
.staying-card[data-status="provisional"] .room-number {
    background-color: #fef3c7 !important;
    color: #92400e !important;
}
</style>
