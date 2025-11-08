<?php
/**
 * Chrome Summary Response Template
 *
 * HTML template for chrome-summary context (Summary tab)
 * Displays recent bookings with actions required
 *
 * @package BookingMatchAPI
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Variables available: $bookings (array of recent bookings)
?>
<div class="bma-summary-tab">
    <div class="bma-summary-header">
        <h3>Recent Bookings</h3>
        <div class="bma-summary-subtitle">Last 5 bookings requiring attention</div>
    </div>

    <?php if (empty($bookings)): ?>
        <div class="bma-no-bookings">
            <div class="bma-icon-large">✓</div>
            <p>No bookings requiring attention</p>
            <div class="bma-muted">All recent bookings are up to date</div>
        </div>
    <?php else: ?>
        <div class="bma-bookings-list">
            <?php foreach ($bookings as $booking): ?>
                <?php
                $actions_count = is_array($booking['actions_required']) ? count($booking['actions_required']) : 0;
                $has_critical = in_array('missing_restaurant', $booking['actions_required']) ||
                               in_array('package_alert', $booking['actions_required']);
                $badge_class = $has_critical ? 'critical' : 'warning';
                ?>
                <div class="bma-summary-booking <?php echo $has_critical ? 'critical' : ''; ?>">
                    <div class="bma-summary-booking-header">
                        <div class="bma-summary-booking-guest">
                            <strong><?php echo esc_html($booking['guest_name']); ?></strong>
                            <span class="bma-booking-id-small">#<?php echo esc_html($booking['booking_id']); ?></span>
                        </div>
                        <?php if ($actions_count > 0): ?>
                            <span class="bma-action-badge <?php echo $badge_class; ?>">
                                <?php echo esc_html($actions_count); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="bma-summary-booking-info">
                        <div class="bma-summary-info-row">
                            <span class="bma-info-icon">📅</span>
                            <span>Arrives: <?php echo esc_html(date('D, d/m/y', strtotime($booking['arrival_date']))); ?></span>
                        </div>
                    </div>

                    <?php if (!empty($booking['actions_required'])): ?>
                        <div class="bma-actions-required">
                            <?php foreach ($booking['actions_required'] as $action): ?>
                                <?php
                                // Convert action codes to user-friendly messages
                                $action_messages = array(
                                    'missing_restaurant' => '🍽️ Missing restaurant booking',
                                    'package_alert' => '🍽️ Package booking - no reservation',
                                    'multiple_matches' => '⚠ Multiple restaurant matches',
                                    'non_primary_match' => '⚠ Review suggested match',
                                    'check_required' => '⚠ Manual check required',
                                );
                                $message = isset($action_messages[$action]) ? $action_messages[$action] : $action;
                                $is_critical = in_array($action, array('missing_restaurant', 'package_alert'));
                                ?>
                                <div class="bma-action-item <?php echo $is_critical ? 'critical' : ''; ?>">
                                    <?php echo esc_html($message); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="bma-summary-actions">
                            <a href="<?php echo esc_url(home_url('/bookings/?booking_id=' . $booking['booking_id'])); ?>"
                               class="bma-action-btn" target="_blank">
                                View Details
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
/* Summary Tab Styles */
.bma-summary-tab {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 14px;
    line-height: 1.5;
    color: #333;
    padding: 20px;
    background: #fff;
}

.bma-summary-header {
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 2px solid #e5e7eb;
}

.bma-summary-header h3 {
    margin: 0 0 4px 0;
    font-size: 20px;
    font-weight: 600;
    color: #111827;
}

.bma-summary-subtitle {
    font-size: 13px;
    color: #6b7280;
}

.bma-no-bookings {
    text-align: center;
    padding: 40px 20px;
}

.bma-icon-large {
    font-size: 64px;
    margin-bottom: 16px;
}

.bma-no-bookings p {
    font-size: 16px;
    font-weight: 500;
    color: #374151;
    margin: 0 0 8px 0;
}

.bma-muted {
    font-size: 13px;
    color: #9ca3af;
}

.bma-bookings-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.bma-summary-booking {
    background: #f9fafb;
    border-left: 4px solid #60a5fa;
    padding: 16px;
    border-radius: 6px;
    transition: all 0.2s;
}

.bma-summary-booking.critical {
    border-left-color: #ef4444;
    background: #fef2f2;
}

.bma-summary-booking:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.bma-summary-booking-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}

.bma-summary-booking-guest {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.bma-summary-booking-guest strong {
    font-size: 15px;
    color: #111827;
}

.bma-booking-id-small {
    font-size: 12px;
    color: #6b7280;
    font-weight: 500;
}

.bma-action-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
    height: 24px;
    padding: 0 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 700;
    color: white;
}

.bma-action-badge.warning {
    background: #f59e0b;
}

.bma-action-badge.critical {
    background: #ef4444;
}

.bma-summary-booking-info {
    margin-bottom: 12px;
}

.bma-summary-info-row {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #4b5563;
}

.bma-info-icon {
    font-size: 16px;
}

.bma-actions-required {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 12px;
}

.bma-action-item {
    font-size: 13px;
    color: #92400e;
    background: #fef3c7;
    padding: 6px 10px;
    border-radius: 4px;
    font-weight: 500;
}

.bma-action-item.critical {
    color: #991b1b;
    background: #fecaca;
    font-weight: 600;
}

.bma-summary-actions {
    display: flex;
    gap: 8px;
    margin-top: 12px;
}

.bma-action-btn {
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

.bma-action-btn:hover {
    background: #5568d3;
}
</style>
