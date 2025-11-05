<?php
/**
 * Template Helper for Booking Match API
 * Generates HTML for displaying booking matches
 * Based on reservation-management-integration plugin's buildComparisonRow function
 */

class BMA_Template_Helper {

    /**
     * Build a comparison row for a single Resos match
     * Replicates the JavaScript buildComparisonRow function from reservation-management-integration plugin
     *
     * @param array $data Comparison data with hotel, resos, matches, suggested_updates
     * @param string $match_type Type of match: 'primary', 'suggested', or 'duplicate'
     * @return string HTML for the comparison row
     */
    public static function build_comparison_row($data, $match_type = 'suggested') {
        $hotel = isset($data['hotel']) ? $data['hotel'] : array();
        $resos = isset($data['resos']) ? $data['resos'] : array();
        $matches = isset($data['matches']) ? $data['matches'] : array();
        $suggestions = isset($data['suggested_updates']) ? $data['suggested_updates'] : array();

        $html = '<div class="comparison-row-content match-type-' . esc_attr($match_type) . '">';
        $html .= '<div class="comparison-table-wrapper">';
        $html .= '<div class="comparison-header">Match Comparison</div>';
        $html .= '<table class="comparison-table">';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th>Field</th>';
        $html .= '<th>Newbook</th>';
        $html .= '<th>Resos</th>';
        $html .= '<th style="background-color: #fff3cd;">Suggested Updates</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        // Guest Name row
        $name_match = isset($matches['name']) && $matches['name'];
        $hotel_name = isset($hotel['name']) && !empty($hotel['name']) ? esc_html($hotel['name']) : '<em style="color: #adb5bd;">-</em>';
        $resos_name = isset($resos['name']) && !empty($resos['name']) ? esc_html($resos['name']) : '<em style="color: #adb5bd;">-</em>';
        $name_suggestion = self::get_suggestion_display($suggestions, 'name');
        $name_cell_class = isset($suggestions['name']) ? 'suggestion-cell has-suggestion' : 'suggestion-cell';

        $html .= '<tr' . ($name_match ? ' class="match-row"' : '') . '>';
        $html .= '<td><strong>Guest Name</strong></td>';
        $html .= '<td>' . $hotel_name . '</td>';
        $html .= '<td>' . $resos_name . '</td>';
        $html .= '<td class="' . $name_cell_class . '">' . $name_suggestion . '</td>';
        $html .= '</tr>';

        // Phone row
        $phone_match = isset($matches['phone']) && $matches['phone'];
        $hotel_phone = isset($hotel['phone']) && !empty($hotel['phone']) ? esc_html($hotel['phone']) : '<em style="color: #adb5bd;">-</em>';
        $resos_phone = isset($resos['phone']) && !empty($resos['phone']) ? esc_html($resos['phone']) : '<em style="color: #adb5bd;">-</em>';
        $phone_suggestion = self::get_suggestion_display($suggestions, 'phone');
        $phone_cell_class = isset($suggestions['phone']) ? 'suggestion-cell has-suggestion' : 'suggestion-cell';

        $html .= '<tr' . ($phone_match ? ' class="match-row"' : '') . '>';
        $html .= '<td><strong>Phone</strong></td>';
        $html .= '<td>' . $hotel_phone . '</td>';
        $html .= '<td>' . $resos_phone . '</td>';
        $html .= '<td class="' . $phone_cell_class . '">' . $phone_suggestion . '</td>';
        $html .= '</tr>';

        // Email row
        $email_match = isset($matches['email']) && $matches['email'];
        $hotel_email = isset($hotel['email']) && !empty($hotel['email']) ? esc_html($hotel['email']) : '<em style="color: #adb5bd;">-</em>';
        $resos_email = isset($resos['email']) && !empty($resos['email']) ? esc_html($resos['email']) : '<em style="color: #adb5bd;">-</em>';
        $email_suggestion = self::get_suggestion_display($suggestions, 'email');
        $email_cell_class = isset($suggestions['email']) ? 'suggestion-cell has-suggestion' : 'suggestion-cell';

        $html .= '<tr' . ($email_match ? ' class="match-row"' : '') . '>';
        $html .= '<td><strong>Email</strong></td>';
        $html .= '<td>' . $hotel_email . '</td>';
        $html .= '<td>' . $resos_email . '</td>';
        $html .= '<td class="' . $email_cell_class . '">' . $email_suggestion . '</td>';
        $html .= '</tr>';

        // People row
        $people_match = isset($matches['people']) && $matches['people'];
        $hotel_people = isset($hotel['people']) && !empty($hotel['people']) ? esc_html($hotel['people']) : '<em style="color: #adb5bd;">-</em>';
        $resos_people = isset($resos['people']) && !empty($resos['people']) ? esc_html($resos['people']) : '<em style="color: #adb5bd;">-</em>';
        $people_suggestion = self::get_suggestion_display($suggestions, 'people');
        $people_cell_class = isset($suggestions['people']) ? 'suggestion-cell has-suggestion' : 'suggestion-cell';

        $html .= '<tr' . ($people_match ? ' class="match-row"' : '') . '>';
        $html .= '<td><strong>People</strong></td>';
        $html .= '<td>' . $hotel_people . '</td>';
        $html .= '<td>' . $resos_people . '</td>';
        $html .= '<td class="' . $people_cell_class . '">' . $people_suggestion . '</td>';
        $html .= '</tr>';

        // Tariff/Package row (DBB)
        $dbb_match = isset($matches['dbb']) && $matches['dbb'];
        $hotel_tariff = isset($hotel['rate_type']) && !empty($hotel['rate_type']) ? esc_html($hotel['rate_type']) : '<em style="color: #adb5bd;">-</em>';
        $resos_dbb = isset($resos['dbb']) && !empty($resos['dbb']) ? esc_html($resos['dbb']) : '<em style="color: #adb5bd;">-</em>';
        $dbb_suggestion = self::get_suggestion_display($suggestions, 'dbb');
        $dbb_cell_class = isset($suggestions['dbb']) ? 'suggestion-cell has-suggestion' : 'suggestion-cell';

        $html .= '<tr' . ($dbb_match ? ' class="match-row"' : '') . '>';
        $html .= '<td><strong>Tariff/Package</strong></td>';
        $html .= '<td>' . $hotel_tariff . '</td>';
        $html .= '<td>' . $resos_dbb . '</td>';
        $html .= '<td class="' . $dbb_cell_class . '">' . $dbb_suggestion . '</td>';
        $html .= '</tr>';

        // Booking # row
        $booking_ref_match = isset($matches['booking_ref']) && $matches['booking_ref'];
        $hotel_booking_id = isset($hotel['booking_id']) && !empty($hotel['booking_id']) ? esc_html($hotel['booking_id']) : '<em style="color: #adb5bd;">-</em>';
        $resos_booking_ref = isset($resos['booking_ref']) && !empty($resos['booking_ref']) ? esc_html($resos['booking_ref']) : '<em style="color: #adb5bd;">-</em>';
        $booking_ref_suggestion = self::get_suggestion_display($suggestions, 'booking_ref');
        $booking_ref_cell_class = isset($suggestions['booking_ref']) ? 'suggestion-cell has-suggestion' : 'suggestion-cell';

        $html .= '<tr' . ($booking_ref_match ? ' class="match-row"' : '') . '>';
        $html .= '<td><strong>Booking #</strong></td>';
        $html .= '<td>' . $hotel_booking_id . '</td>';
        $html .= '<td>' . $resos_booking_ref . '</td>';
        $html .= '<td class="' . $booking_ref_cell_class . '">' . $booking_ref_suggestion . '</td>';
        $html .= '</tr>';

        // Hotel Guest row
        $hotel_guest_value = (isset($hotel['is_hotel_guest']) && $hotel['is_hotel_guest']) ? 'Yes' : '<em style="color: #adb5bd;">-</em>';
        $resos_hotel_guest = isset($resos['hotel_guest']) && !empty($resos['hotel_guest']) ? esc_html($resos['hotel_guest']) : '<em style="color: #adb5bd;">-</em>';
        $hotel_guest_suggestion = self::get_suggestion_display($suggestions, 'hotel_guest');
        $hotel_guest_cell_class = isset($suggestions['hotel_guest']) ? 'suggestion-cell has-suggestion' : 'suggestion-cell';

        $html .= '<tr>';
        $html .= '<td><strong>Hotel Guest</strong></td>';
        $html .= '<td>' . $hotel_guest_value . '</td>';
        $html .= '<td>' . $resos_hotel_guest . '</td>';
        $html .= '<td class="' . $hotel_guest_cell_class . '">' . $hotel_guest_suggestion . '</td>';
        $html .= '</tr>';

        // Status row
        $hotel_status = isset($hotel['status']) && !empty($hotel['status']) ? esc_html($hotel['status']) : '<em style="color: #adb5bd;">-</em>';
        $resos_status = isset($resos['status']) && !empty($resos['status']) ? strtolower($resos['status']) : 'request';
        $status_icon = self::get_status_icon($resos_status);
        $status_icon_html = '<span class="material-symbols-outlined">' . $status_icon . '</span>';
        $status_suggestion = self::get_suggestion_display($suggestions, 'status');
        $status_cell_class = isset($suggestions['status']) ? 'suggestion-cell has-suggestion' : 'suggestion-cell';

        $html .= '<tr>';
        $html .= '<td><strong>Status</strong></td>';
        $html .= '<td>' . $hotel_status . '</td>';
        $html .= '<td>' . $status_icon_html . ' ' . esc_html(ucfirst($resos_status)) . '</td>';
        $html .= '<td class="' . $status_cell_class . '">' . $status_suggestion . '</td>';
        $html .= '</tr>';

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>'; // comparison-table-wrapper
        $html .= '</div>'; // comparison-row-content

        return $html;
    }

    /**
     * Get suggestion display text
     */
    private static function get_suggestion_display($suggestions, $field) {
        if (!isset($suggestions[$field])) {
            return '<em style="color: #adb5bd;">-</em>';
        }

        $value = $suggestions[$field];
        if ($value === '' || $value === null) {
            return '<em style="color: #999;">(Remove)</em>';
        }

        return esc_html($value);
    }

    /**
     * Get status icon (Material Symbols)
     * Replicates the getStatusIcon function from staying-today.js
     */
    private static function get_status_icon($status) {
        $status_lower = strtolower($status);

        switch ($status_lower) {
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

    /**
     * Build a simple match summary (for compact display)
     * Shows key info: name, time, people, status
     */
    public static function build_match_summary($match, $index = 0) {
        $name = isset($match['guest_name']) && !empty($match['guest_name']) ? esc_html($match['guest_name']) : '<em>No name</em>';
        $time = isset($match['time']) && !empty($match['time']) ? esc_html($match['time']) : '<em>No time</em>';
        $people = isset($match['people']) ? intval($match['people']) : 0;
        $status = isset($match['status']) && !empty($match['status']) ? strtolower($match['status']) : 'request';
        $is_hotel_guest = isset($match['is_hotel_guest']) && $match['is_hotel_guest'];
        $is_dbb = isset($match['is_dbb']) && $match['is_dbb'];
        $score = isset($match['score']) ? intval($match['score']) : 0;

        $status_icon = self::get_status_icon($status);

        $html = '<div class="match-summary' . ($index === 0 ? ' primary-match' : '') . '">';
        $html .= '<div class="match-header">';
        $html .= '<span class="match-name">' . $name . '</span>';
        $html .= '<span class="match-time">' . $time . '</span>';
        $html .= '</div>';
        $html .= '<div class="match-details">';
        $html .= '<span class="match-people">' . $people . ' people</span>';
        $html .= ' &bull; ';
        $html .= '<span class="match-status"><span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">' . $status_icon . '</span> ' . esc_html(ucfirst($status)) . '</span>';

        if ($is_hotel_guest) {
            $html .= ' &bull; <span class="match-badge">Hotel Guest</span>';
        }
        if ($is_dbb) {
            $html .= ' &bull; <span class="match-badge">DBB</span>';
        }

        $html .= '<span class="match-score" style="float:right; color:#6c757d; font-size:12px;">Score: ' . $score . '</span>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
}
