<?php
/**
 * Response Formatter Class
 *
 * Handles formatting API responses based on context
 *
 * @package BookingMatchAPI
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class BMA_Response_Formatter {

    /**
     * Format response based on context
     *
     * @param array $results Match results
     * @param string $search_method How the search was performed
     * @param string $context Response format context
     * @return array|string Formatted response
     */
    public function format_response($results, $search_method, $context = 'json') {
        if ($context === 'chrome-extension') {
            return $this->format_html_response($results, $search_method, 'chrome-extension');
        }

        if ($context === 'chrome-sidepanel') {
            return $this->format_html_response($results, $search_method, 'chrome-sidepanel');
        }

        // Default JSON response
        return $this->format_json_response($results, $search_method);
    }

    /**
     * Format JSON response (default)
     */
    private function format_json_response($results, $search_method) {
        $formatted_bookings = array();
        $should_auto_open = false;
        $badge_count = 0;

        foreach ($results as $result) {
            $booking_data = array(
                'booking_id' => $result['booking_id'],
                'booking_reference' => $result['booking_reference'],
                'guest_name' => $result['guest_name'],
                'room' => $result['room'],
                'arrival' => $result['arrival'],
                'departure' => $result['departure'],
                'total_nights' => $result['total_nights'],
                'nights' => array()
            );

            // Format each night
            foreach ($result['nights'] as $night) {
                $has_matches = !empty($night['resos_matches']);
                $match_count = $has_matches ? count($night['resos_matches']) : 0;
                $has_package = isset($night['has_package']) && $night['has_package'];

                // Check if this night should trigger auto-open
                // (has package but no restaurant booking)
                if ($has_package && !$has_matches) {
                    $should_auto_open = true;
                    $badge_count++; // Critical issue
                }

                // Count multiple matches as issues
                if ($match_count > 1) {
                    $badge_count++;
                }

                // Count non-primary matches as issues
                if ($has_matches && $match_count === 1) {
                    $primary_match = isset($night['resos_matches'][0]) ? $night['resos_matches'][0] : null;
                    $is_primary = $primary_match && isset($primary_match['match_info']['is_primary']) && $primary_match['match_info']['is_primary'];
                    if (!$is_primary) {
                        $badge_count++;
                    }
                }

                $night_data = array(
                    'date' => $night['date'],
                    'date_formatted' => date('d/m/y', strtotime($night['date'])),
                    'has_match' => $has_matches,
                    'match_count' => $match_count,
                    'has_package' => $has_package,
                    'resos_bookings' => array()
                );

                if ($has_matches) {
                    foreach ($night['resos_matches'] as $match) {
                        $night_data['resos_bookings'][] = array(
                            'id' => $match['resos_booking_id'],
                            'resos_booking_id' => $match['resos_booking_id'],
                            'restaurant_id' => isset($match['restaurant_id']) ? $match['restaurant_id'] : '',
                            'guest_name' => $match['guest_name'],
                            'people' => $match['people'],
                            'time' => isset($match['time']) ? $match['time'] : null,
                            'status' => $match['status'],
                            'is_hotel_guest' => isset($match['is_hotel_guest']) ? $match['is_hotel_guest'] : false,
                            'is_dbb' => isset($match['is_dbb']) ? $match['is_dbb'] : false,
                            'booking_number' => isset($match['booking_number']) ? $match['booking_number'] : null,
                            'match_type' => $match['match_info']['match_type'],
                            'match_label' => $match['match_info']['match_label'],
                            'confidence' => $match['match_info']['confidence'],
                            'is_primary' => $match['match_info']['is_primary'],
                            'score' => isset($match['score']) ? $match['score'] : 0,
                            'deep_link' => $this->generate_deep_link($result['booking_id'], $night['date'], $match['resos_booking_id'])
                        );
                    }
                }

                // Generate deep link (for create action when no matches)
                $night_data['deep_link'] = $this->generate_deep_link($result['booking_id'], $night['date']);
                $night_data['action'] = $has_matches ? 'update' : 'create';

                $booking_data['nights'][] = $night_data;
            }

            $formatted_bookings[] = $booking_data;
        }

        return array(
            'success' => true,
            'search_method' => $search_method,
            'bookings_found' => count($formatted_bookings),
            'bookings' => $formatted_bookings,
            'should_auto_open' => $should_auto_open,
            'badge_count' => $badge_count
        );
    }

    /**
     * Format HTML response for Chrome extension or sidepanel
     */
    private function format_html_response($results, $search_method, $context = 'chrome-extension') {
        $booking_count = count($results);
        $critical_count = 0;  // Red flags: Package bookings without restaurant
        $warning_count = 0;   // Amber flags: Multiple matches, non-primary matches

        // Calculate badge counts with severity levels
        foreach ($results as $result) {
            foreach ($result['nights'] as $night) {
                $has_matches = !empty($night['resos_matches']);
                $match_count = $has_matches ? count($night['resos_matches']) : 0;
                $has_package = isset($night['has_package']) && $night['has_package'];

                // CRITICAL: Package booking without restaurant reservation
                if ($has_package && !$has_matches) {
                    $critical_count++;
                }
                // WARNING: Multiple matches requiring manual selection
                elseif ($match_count > 1) {
                    $warning_count++;
                }
                // WARNING: Single non-primary (suggested) match
                elseif ($has_matches && $match_count === 1) {
                    $primary_match = isset($night['resos_matches'][0]) ? $night['resos_matches'][0] : null;
                    $is_primary = $primary_match && isset($primary_match['match_info']['is_primary']) && $primary_match['match_info']['is_primary'];
                    if (!$is_primary) {
                        $warning_count++;
                    }
                }
                // NO ISSUE: Missing restaurant booking when no package
                // (Not flagged as this is normal)
            }
        }

        $badge_count = $critical_count + $warning_count;

        // Start HTML output
        ob_start();

        // Load appropriate template based on context
        if ($context === 'chrome-sidepanel') {
            $template_file = BMA_PLUGIN_DIR . 'templates/chrome-sidepanel-response.php';
        } else {
            $template_file = BMA_PLUGIN_DIR . 'templates/chrome-extension-response.php';
        }

        // Check if template exists, fallback to default if not
        if (!file_exists($template_file)) {
            error_log("BMA: Template not found: {$template_file}, using default chrome-extension template");
            $template_file = BMA_PLUGIN_DIR . 'templates/chrome-extension-response.php';
        }

        include $template_file;

        $html = ob_get_clean();

        // Extract restaurant bookings by date for Gantt chart
        $bookings_by_date = array();
        foreach ($results as $result) {
            foreach ($result['nights'] as $night) {
                $date = $night['date'];
                if (!isset($bookings_by_date[$date])) {
                    $bookings_by_date[$date] = array();
                }

                // Add all restaurant bookings for this date
                if (!empty($night['resos_matches'])) {
                    foreach ($night['resos_matches'] as $match) {
                        // Extract room number if available
                        $room = 'Unknown';
                        if (isset($match['room']) && !empty($match['room'])) {
                            $room = $match['room'];
                        } elseif (isset($result['room']) && !empty($result['room'])) {
                            $room = $result['room'];
                        }

                        $bookings_by_date[$date][] = array(
                            'time' => $match['time'] ?? '19:00',
                            'people' => $match['people'] ?? 2,
                            'name' => $match['guest_name'] ?? 'Guest',
                            'room' => $room
                        );
                    }
                }
            }
        }

        // Return as data object (REST API will handle encoding)
        return array(
            'success' => true,
            'context' => $context,
            'html' => $html,
            'bookings_found' => $booking_count,
            'search_method' => $search_method,
            'badge_count' => $badge_count,
            'critical_count' => $critical_count,
            'warning_count' => $warning_count,
            'bookings_by_date' => $bookings_by_date
        );
    }

    /**
     * Generate deep link to booking page
     */
    private function generate_deep_link($booking_id, $date = null, $resos_booking_id = null) {
        // Get the page with the shortcode (configurable via option)
        $page_url = get_option('bma_booking_page_url', home_url('/bookings/'));

        $url = add_query_arg('booking_id', $booking_id, $page_url);

        if ($date) {
            $url = add_query_arg('date', $date, $url);
        }

        if ($resos_booking_id) {
            // Existing match - auto-action is to expand the comparison row
            $url = add_query_arg('resos_id', $resos_booking_id, $url);
            $url = add_query_arg('auto-action', 'match', $url);
        } else if ($date) {
            // No match for this date - auto-action is to open create booking form
            $url = add_query_arg('auto-action', 'create', $url);
        }

        return $url;
    }

    /**
     * Format summary HTML for Chrome sidepanel
     */
    public function format_summary_html($bookings) {
        ob_start();
        $template_file = BMA_PLUGIN_DIR . 'templates/chrome-summary-response.php';

        if (file_exists($template_file)) {
            include $template_file;
        } else {
            // Fallback simple HTML
            echo '<div class="bma-summary">';
            echo '<h2>Recent Bookings</h2>';
            foreach ($bookings as $booking) {
                echo '<div class="booking-item">';
                echo '<strong>' . esc_html($booking['guest_name']) . '</strong><br>';
                echo 'Booking #' . esc_html($booking['booking_id']) . '<br>';
                echo 'Actions: ' . count($booking['actions_required']);
                echo '</div>';
            }
            echo '</div>';
        }

        return ob_get_clean();
    }

    /**
     * Format checks HTML for Chrome sidepanel
     */
    public function format_checks_html($booking_id, $checks) {
        ob_start();
        $template_file = BMA_PLUGIN_DIR . 'templates/chrome-checks-response.php';

        if (file_exists($template_file)) {
            include $template_file;
        } else {
            // Fallback simple HTML
            echo '<div class="bma-checks">';
            echo '<h2>Checks for Booking #' . esc_html($booking_id) . '</h2>';
            echo '<p>No issues found</p>';
            echo '<p><em>Twin bed checks, sofa bed checks, and special request matching coming soon...</em></p>';
            echo '</div>';
        }

        return ob_get_clean();
    }

    /**
     * Format staying bookings response for Chrome extension
     *
     * @param array $bookings Staying bookings with matches
     * @param string $date Target date (YYYY-MM-DD)
     * @return string HTML content
     */
    public function format_staying_response($bookings, $date) {
        ob_start();
        $template_file = BMA_PLUGIN_DIR . 'templates/chrome-staying-response.php';

        if (file_exists($template_file)) {
            include $template_file;
        } else {
            // Fallback simple HTML
            echo '<div class="staying-empty">';
            echo '<div class="empty-icon">🛏️</div>';
            echo '<p>Template not found</p>';
            echo '</div>';
        }

        return ob_get_clean();
    }
}
