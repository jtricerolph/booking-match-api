<?php
/**
 * REST API Controller
 *
 * Handles REST API endpoint registration and request processing
 *
 * @package BookingMatchAPI
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class BMA_REST_Controller extends WP_REST_Controller {

    /**
     * Namespace for REST API
     */
    protected $namespace = 'bma/v1';

    /**
     * Resource name
     */
    protected $rest_base = 'bookings';

    /**
     * Register routes
     */
    public function register_routes() {
        register_rest_route($this->namespace, '/' . $this->rest_base . '/match', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'match_booking'),
                'permission_callback' => array($this, 'permissions_check'),
                'args' => $this->get_match_params(),
            ),
        ));

        register_rest_route($this->namespace, '/summary', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_summary'),
                'permission_callback' => array($this, 'permissions_check'),
                'args' => $this->get_summary_params(),
            ),
        ));

        register_rest_route($this->namespace, '/checks/(?P<booking_id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_checks'),
                'permission_callback' => array($this, 'permissions_check'),
                'args' => $this->get_checks_params(),
            ),
        ));
    }

    /**
     * Check permissions for API access
     *
     * Requires WordPress authentication via Application Passwords.
     * To generate an Application Password:
     * 1. Go to WordPress admin > Users > Profile
     * 2. Scroll to "Application Passwords" section
     * 3. Enter a name (e.g., "Chrome Extension") and click "Add New Application Password"
     * 4. Copy the generated password and use it with your WordPress username for HTTP Basic Auth
     */
    public function permissions_check($request) {
        // Log key request details
        error_log(sprintf(
            'BMA-AUTH: Request [%s %s] Origin: %s | Auth: %s | PHP_AUTH_USER: %s',
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI'],
            isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : 'none',
            isset($_SERVER['HTTP_AUTHORIZATION']) ? 'present' : 'missing',
            isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : 'not set'
        ));

        // If not logged in via cookie, try manual Application Password authentication
        if (!is_user_logged_in() && isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
            $username = $_SERVER['PHP_AUTH_USER'];
            $password = $_SERVER['PHP_AUTH_PW'];

            // Try to authenticate using Application Password
            $user = wp_authenticate_application_password(null, $username, $password);

            if ($user instanceof WP_User) {
                wp_set_current_user($user->ID);

                // Check if user has required capability
                if (!$user->has_cap('read')) {
                    error_log("BMA-AUTH: REJECTED - User '{$user->user_login}' lacks read capability");
                    return new WP_Error(
                        'rest_forbidden',
                        __('You do not have permission to access this resource.', 'booking-match-api'),
                        array('status' => 403)
                    );
                }

                error_log("BMA-AUTH: ACCEPTED - User '{$user->user_login}' authenticated via Application Password");
                return true;
            } else {
                error_log("BMA-AUTH: Manual Application Password auth failed for user: {$username}");
            }
        }

        // Require WordPress authentication (supports Application Passwords)
        if (!is_user_logged_in()) {
            error_log('BMA-AUTH: REJECTED - No valid authentication provided (not logged in, no valid PHP_AUTH)');
            return new WP_Error(
                'rest_forbidden',
                __('Authentication required. Please provide valid WordPress credentials.', 'booking-match-api'),
                array('status' => 401)
            );
        }

        // User must have at least 'read' capability
        if (!current_user_can('read')) {
            $current_user = wp_get_current_user();
            error_log("BMA-AUTH: REJECTED - User '{$current_user->user_login}' lacks read capability");
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access this resource.', 'booking-match-api'),
                array('status' => 403)
            );
        }

        $current_user = wp_get_current_user();
        error_log("BMA-AUTH: ACCEPTED - User '{$current_user->user_login}' authenticated");
        return true;
    }

    /**
     * Get endpoint parameters
     */
    public function get_match_params() {
        return array(
            'booking_id' => array(
                'description' => __('NewBook booking ID', 'booking-match-api'),
                'type' => 'integer',
                'required' => false,
            ),
            'guest_name' => array(
                'description' => __('Guest full name', 'booking-match-api'),
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'email_address' => array(
                'description' => __('Guest email address', 'booking-match-api'),
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_email',
            ),
            'phone_number' => array(
                'description' => __('Guest phone number', 'booking-match-api'),
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'group_id' => array(
                'description' => __('Group or booking group ID', 'booking-match-api'),
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'travelagent_reference' => array(
                'description' => __('Travel agent reference number', 'booking-match-api'),
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'context' => array(
                'description' => __('Response format context (json, chrome-extension, chrome-sidepanel, etc.)', 'booking-match-api'),
                'type' => 'string',
                'required' => false,
                'default' => 'json',
                'enum' => array('json', 'chrome-extension', 'chrome-sidepanel'),
                'sanitize_callback' => 'sanitize_text_field',
            ),
        );
    }

    /**
     * Handle booking match request
     */
    public function match_booking($request) {
        try {
            // Extract parameters
            $booking_id = $request->get_param('booking_id');
            $guest_name = $request->get_param('guest_name');
            $email = $request->get_param('email_address');
            $phone = $request->get_param('phone_number');
            $group_id = $request->get_param('group_id');
            $agent_ref = $request->get_param('travelagent_reference');
            $context = $request->get_param('context') ?: 'json';

            // Validate search criteria
            $validation = $this->validate_search_criteria($booking_id, $guest_name, $email, $phone, $agent_ref);
            if (is_wp_error($validation)) {
                return $validation;
            }

            // Initialize searcher
            $searcher = new BMA_NewBook_Search();

            // Search for booking(s)
            if ($booking_id) {
                // Direct booking ID lookup
                $bookings = $searcher->get_booking_by_id($booking_id);
                if (!$bookings) {
                    return new WP_Error(
                        'booking_not_found',
                        __('Booking not found', 'booking-match-api'),
                        array('status' => 404)
                    );
                }
                $bookings = array($bookings);
                $search_method = 'booking_id';
            } else {
                // Search by guest details
                $search_result = $searcher->search_bookings(array(
                    'guest_name' => $guest_name,
                    'email' => $email,
                    'phone' => $phone,
                    'group_id' => $group_id,
                    'agent_reference' => $agent_ref,
                ));

                if (is_wp_error($search_result)) {
                    return $search_result;
                }

                $bookings = $search_result['bookings'];
                $search_method = $search_result['search_method'];

                if (empty($bookings)) {
                    return new WP_Error(
                        'no_bookings_found',
                        __('No bookings found matching the search criteria', 'booking-match-api'),
                        array('status' => 404)
                    );
                }
            }

            // Match each booking with Resos
            $matcher = new BMA_Matcher();
            $results = array();

            foreach ($bookings as $booking) {
                $match_result = $matcher->match_booking_all_nights($booking);
                $results[] = $match_result;
            }

            // Format response based on context
            $formatter = new BMA_Response_Formatter();
            $response = $formatter->format_response($results, $search_method, $context);

            return rest_ensure_response($response);

        } catch (Exception $e) {
            error_log('BMA: Exception in match_booking: ' . $e->getMessage());
            return new WP_Error(
                'server_error',
                __('An error occurred processing your request', 'booking-match-api'),
                array('status' => 500)
            );
        }
    }

    /**
     * Validate search criteria
     */
    private function validate_search_criteria($booking_id, $guest_name, $email, $phone, $agent_ref) {
        // If booking_id provided, no other validation needed
        if ($booking_id) {
            return true;
        }

        // Check if at least one search field provided
        if (empty($guest_name) && empty($email) && empty($phone) && empty($agent_ref)) {
            return new WP_Error(
                'missing_search_criteria',
                __('At least one search field is required (booking_id, email_address, phone_number, travelagent_reference, or guest_name with another field)', 'booking-match-api'),
                array('status' => 400)
            );
        }

        // Name-only search not allowed (not confident)
        if (!empty($guest_name) && empty($email) && empty($phone) && empty($agent_ref)) {
            return new WP_Error(
                'insufficient_search_criteria',
                __('Guest name alone is not sufficient. Please provide email, phone, or travel agent reference as well', 'booking-match-api'),
                array('status' => 400)
            );
        }

        return true;
    }

    /**
     * Get summary params
     */
    private function get_summary_params() {
        return array(
            'limit' => array(
                'description' => __('Number of recent bookings to return', 'booking-match-api'),
                'type' => 'integer',
                'required' => false,
                'default' => 5,
                'sanitize_callback' => 'absint',
            ),
            'context' => array(
                'description' => __('Response format context', 'booking-match-api'),
                'type' => 'string',
                'required' => false,
                'default' => 'json',
                'enum' => array('json', 'chrome-summary'),
                'sanitize_callback' => 'sanitize_text_field',
            ),
        );
    }

    /**
     * Get checks params
     */
    private function get_checks_params() {
        return array(
            'booking_id' => array(
                'description' => __('NewBook booking ID', 'booking-match-api'),
                'type' => 'integer',
                'required' => true,
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                },
            ),
            'context' => array(
                'description' => __('Response format context', 'booking-match-api'),
                'type' => 'string',
                'required' => false,
                'default' => 'json',
                'enum' => array('json', 'chrome-checks'),
                'sanitize_callback' => 'sanitize_text_field',
            ),
        );
    }

    /**
     * Get summary of recent bookings with actions required
     */
    public function get_summary($request) {
        try {
            $context = $request->get_param('context') ?: 'json';
            $limit = $request->get_param('limit') ?: 5;

            error_log("BMA Summary: Requested limit = {$limit}, context = {$context}");

            // Fetch recently placed bookings from NewBook
            $searcher = new BMA_NewBook_Search();
            $recent_bookings = $searcher->fetch_recent_placed_bookings($limit);

            if (empty($recent_bookings)) {
                // Return empty success response
                if ($context === 'chrome-summary') {
                    $formatter = new BMA_Response_Formatter();
                    return array(
                        'success' => true,
                        'html' => $formatter->format_summary_html(array()),
                        'critical_count' => 0,
                        'warning_count' => 0,
                        'bookings_count' => 0
                    );
                }

                return array(
                    'success' => true,
                    'bookings' => array(),
                    'critical_count' => 0,
                    'warning_count' => 0,
                    'bookings_count' => 0
                );
            }

            // Process each booking
            $summary_bookings = array();
            $total_critical_count = 0;
            $total_warning_count = 0;

            foreach ($recent_bookings as $nb_booking) {
                $processed = $this->process_booking_for_summary($nb_booking);
                $summary_bookings[] = $processed;
                $total_critical_count += $processed['critical_count'];
                $total_warning_count += $processed['warning_count'];
            }

            // Format response based on context
            if ($context === 'chrome-summary') {
                $formatter = new BMA_Response_Formatter();
                return array(
                    'success' => true,
                    'html' => $formatter->format_summary_html($summary_bookings),
                    'critical_count' => $total_critical_count,
                    'warning_count' => $total_warning_count,
                    'bookings_count' => count($summary_bookings)
                );
            }

            // Default JSON response
            return array(
                'success' => true,
                'bookings' => $summary_bookings,
                'critical_count' => $total_critical_count,
                'warning_count' => $total_warning_count,
                'bookings_count' => count($summary_bookings)
            );

        } catch (Exception $e) {
            error_log('BMA Summary Error: ' . $e->getMessage());
            return new WP_Error(
                'summary_error',
                __('Error retrieving summary', 'booking-match-api'),
                array('status' => 500)
            );
        }
    }

    /**
     * Process a booking for summary display
     */
    private function process_booking_for_summary($booking) {
        // Extract basic info
        $booking_id = $booking['booking_id'];
        $guest_name = $this->extract_guest_name($booking);
        $arrival_date = substr($booking['booking_arrival'] ?? '', 0, 10);
        $departure_date = substr($booking['booking_departure'] ?? '', 0, 10);
        $nights = $this->calculate_nights($arrival_date, $departure_date);
        $status = $booking['status'] ?? 'unknown';

        // Match with restaurants
        $matcher = new BMA_Matcher();
        $match_result = $matcher->match_booking_all_nights($booking);

        // Determine booking source (placeholder)
        $source_detector = new BMA_Booking_Source();
        $booking_source = $source_detector->determine_source($booking);

        // Check for issues (placeholder)
        $issue_checker = new BMA_Issue_Checker();
        $issues = $issue_checker->check_booking($booking);

        // Analyze for actions required with severity levels
        $actions_required = array();
        $critical_count = 0;  // Red flags: Package bookings without restaurant
        $warning_count = 0;   // Amber flags: Multiple matches, non-primary matches
        $check_issues = count($issues);

        foreach ($match_result['nights'] as $night) {
            $has_matches = !empty($night['resos_matches']);
            $match_count = count($night['resos_matches']);
            $has_package = $night['has_package'] ?? false;

            // CRITICAL: Package booking without restaurant reservation
            if ($has_package && !$has_matches) {
                $actions_required[] = 'package_alert';
                $critical_count++;
            }
            // WARNING: Multiple matches requiring manual selection
            elseif ($match_count > 1) {
                $actions_required[] = 'multiple_matches';
                $warning_count++;
            }
            // WARNING: Single non-primary (suggested) match
            elseif ($match_count === 1) {
                $is_primary = $night['resos_matches'][0]['match_info']['is_primary'] ?? false;
                if (!$is_primary) {
                    $actions_required[] = 'non_primary_match';
                    $warning_count++;
                }
            }
            // NO ISSUE: Missing restaurant booking when no package
            // (Not flagged as this is normal)
        }

        // Add check issues if any (warnings)
        if ($check_issues > 0) {
            $actions_required[] = 'check_required';
            $warning_count += $check_issues;
        }

        return array(
            'booking_id' => $booking_id,
            'guest_name' => $guest_name,
            'arrival_date' => $arrival_date,
            'departure_date' => $departure_date,
            'nights' => $nights,
            'status' => $status,
            'booking_source' => $booking_source,
            'actions_required' => array_unique($actions_required),
            'critical_count' => $critical_count,
            'warning_count' => $warning_count,
            'check_issues' => $check_issues,
            'match_details' => $match_result
        );
    }

    /**
     * Extract primary guest name from booking
     */
    private function extract_guest_name($booking) {
        if (isset($booking['guests']) && is_array($booking['guests'])) {
            foreach ($booking['guests'] as $guest) {
                if (isset($guest['primary_client']) && $guest['primary_client'] == '1') {
                    return trim(($guest['firstname'] ?? '') . ' ' . ($guest['lastname'] ?? ''));
                }
            }
        }
        return 'Unknown Guest';
    }

    /**
     * Calculate number of nights between dates
     */
    private function calculate_nights($arrival, $departure) {
        if (empty($arrival) || empty($departure)) {
            return 0;
        }
        $start = new DateTime($arrival);
        $end = new DateTime($departure);
        return $start->diff($end)->days;
    }

    /**
     * Get checks for a specific booking (placeholder for future)
     */
    public function get_checks($request) {
        try {
            $booking_id = $request->get_param('booking_id');
            $context = $request->get_param('context') ?: 'json';

            // TODO: Implement actual checks logic
            // For now, return placeholder/stub data

            $checks = array(
                'twin_bed_request' => false,
                'sofa_bed_request' => false,
                'special_requests' => array(),
                'room_features_mismatch' => array(),
            );

            $badge_count = 0; // No issues for now

            if ($context === 'chrome-checks') {
                // Format as HTML for Chrome sidepanel
                $formatter = new BMA_Response_Formatter();
                return array(
                    'success' => true,
                    'html' => $formatter->format_checks_html($booking_id, $checks),
                    'badge_count' => $badge_count,
                );
            }

            // Default JSON response
            return array(
                'success' => true,
                'booking_id' => $booking_id,
                'checks' => $checks,
                'badge_count' => $badge_count,
            );

        } catch (Exception $e) {
            error_log('BMA Checks Error: ' . $e->getMessage());
            return new WP_Error(
                'checks_error',
                __('Error retrieving checks', 'booking-match-api'),
                array('status' => 500)
            );
        }
    }
}
