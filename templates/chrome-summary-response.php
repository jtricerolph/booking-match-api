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
    <?php foreach ($bookings as $booking): ?>
        <div class="booking-card" data-booking-id="<?php echo esc_attr($booking['booking_id']); ?>">
            <!-- Collapsed Summary -->
            <div class="booking-header">
                <div class="booking-main-info">
                    <div class="booking-guest">
                        <strong><?php echo esc_html($booking['guest_name']); ?></strong>
                        <span class="booking-id">#<?php echo esc_html($booking['booking_id']); ?></span>
                    </div>
                    <div class="booking-dates">
                        <span><?php echo esc_html(date('D, d/m/y', strtotime($booking['arrival_date']))); ?></span>
                        <span class="nights-badge"><?php echo esc_html($booking['nights']); ?> night<?php echo $booking['nights'] > 1 ? 's' : ''; ?></span>
                    </div>
                    <div class="booking-status status-<?php echo esc_attr(strtolower($booking['status'])); ?>">
                        <?php echo esc_html(ucfirst($booking['status'])); ?>
                    </div>
                </div>

                <!-- Issue Badges -->
                <div class="booking-badges">
                    <?php if ($booking['critical_count'] > 0): ?>
                        <span class="issue-badge critical-badge" title="Critical issues">
                            <span class="material-symbols-outlined">flag</span>
                            <?php echo esc_html($booking['critical_count']); ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($booking['warning_count'] > 0): ?>
                        <span class="issue-badge warning-badge" title="Warnings">
                            <span class="material-symbols-outlined">warning</span>
                            <?php echo esc_html($booking['warning_count']); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <span class="expand-icon">▼</span>
            </div>

            <!-- Expanded Details -->
            <div class="booking-details" id="details-<?php echo esc_attr($booking['booking_id']); ?>" style="display: none;">
                <div class="detail-section">
                    <div class="detail-row">
                        <span class="detail-label">Source:</span>
                        <span class="detail-value"><?php echo esc_html($booking['booking_source']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Check-out:</span>
                        <span class="detail-value"><?php echo esc_html(date('D, d/m/y', strtotime($booking['departure_date']))); ?></span>
                    </div>
                </div>

                <!-- Restaurant Summary -->
                <?php if ($booking['critical_count'] > 0 || $booking['warning_count'] > 0): ?>
                    <div class="detail-section restaurant-summary">
                        <h4><span class="material-symbols-outlined">restaurant</span> Restaurant Matches</h4>
                        <ul class="issue-list">
                            <?php
                            $actions = $booking['actions_required'];
                            $action_messages = array(
                                'package_alert' => array('text' => 'Package booking - missing reservation', 'level' => 'critical'),
                                'multiple_matches' => array('text' => 'Multiple matches - needs review', 'level' => 'warning'),
                                'non_primary_match' => array('text' => 'Suggested match - low confidence', 'level' => 'warning')
                            );

                            foreach ($actions as $action) {
                                if (isset($action_messages[$action])) {
                                    $msg = $action_messages[$action];
                                    $icon = $msg['level'] === 'critical' ? 'flag' : 'warning';
                                    $class = $msg['level'] === 'critical' ? 'critical-item' : 'warning-item';
                                    echo '<li class="' . esc_attr($class) . '">';
                                    echo '<span class="material-symbols-outlined">' . esc_html($icon) . '</span>';
                                    echo esc_html($msg['text']);
                                    echo '</li>';
                                }
                            }
                            ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Checks Summary -->
                <?php if ($booking['check_issues'] > 0): ?>
                    <div class="detail-section checks-summary">
                        <h4>⚠ Booking Checks</h4>
                        <p class="placeholder-text">Issue checking coming soon...</p>
                    </div>
                <?php endif; ?>

                <!-- Action Button -->
                <div class="detail-actions">
                    <button class="open-booking-btn" data-booking-id="<?php echo esc_attr($booking['booking_id']); ?>">
                        Open Booking in NewBook →
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
    margin-bottom: 12px;
    transition: all 0.2s;
}

.booking-card:hover {
    border-color: #cbd5e0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.booking-card.expanded {
    border-color: #3182ce;
}

.booking-header {
    padding: 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 12px;
}

.booking-main-info {
    flex: 1;
}

.booking-guest {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
}

.booking-guest strong {
    font-size: 14px;
    color: #2d3748;
}

.booking-id {
    font-size: 12px;
    color: #718096;
}

.booking-dates {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #4a5568;
}

.nights-badge {
    background: #edf2f7;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}

.booking-status {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    margin-top: 4px;
}

.status-confirmed { background: #c6f6d5; color: #22543d; }
.status-provisional { background: #fef5e7; color: #975a16; }
.status-cancelled { background: #fed7d7; color: #742a2a; }
.status-unknown { background: #e2e8f0; color: #4a5568; }

.booking-badges {
    display: flex;
    gap: 6px;
}

.issue-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 4px;
}

.issue-badge .material-symbols-outlined {
    font-size: 14px;
}

.critical-badge {
    background: #fee2e2;
    color: #991b1b;
}

.warning-badge {
    background: #fef3c7;
    color: #92400e;
}

.expand-icon {
    font-size: 12px;
    color: #a0aec0;
    transition: transform 0.2s;
}

.booking-details {
    padding: 0 12px 12px 12px;
    border-top: 1px solid #e2e8f0;
}

.detail-section {
    margin-top: 12px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    font-size: 13px;
}

.detail-label {
    color: #718096;
    font-weight: 500;
}

.detail-value {
    color: #2d3748;
}

.restaurant-summary, .checks-summary {
    background: #f7fafc;
    padding: 12px;
    border-radius: 6px;
    margin-top: 12px;
}

.restaurant-summary h4, .checks-summary h4 {
    margin: 0 0 8px 0;
    font-size: 13px;
    color: #2d3748;
    display: flex;
    align-items: center;
    gap: 6px;
}

.restaurant-summary h4 .material-symbols-outlined {
    font-size: 16px;
}

.issue-list {
    margin: 0;
    padding-left: 0;
    list-style: none;
    font-size: 12px;
    color: #4a5568;
}

.issue-list li {
    margin: 6px 0;
    display: flex;
    align-items: center;
    gap: 6px;
}

.issue-list li .material-symbols-outlined {
    font-size: 16px;
}

.issue-list li.critical-item {
    color: #991b1b;
}

.issue-list li.critical-item .material-symbols-outlined {
    color: #dc2626;
}

.issue-list li.warning-item {
    color: #92400e;
}

.issue-list li.warning-item .material-symbols-outlined {
    color: #f59e0b;
}

.placeholder-text {
    font-size: 12px;
    color: #a0aec0;
    font-style: italic;
    margin: 0;
}

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
}

.open-booking-btn:hover {
    background: #2c5aa0;
}
</style>

<!-- Material Symbols Icons -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
