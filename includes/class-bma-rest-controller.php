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

        register_rest_route($this->namespace, '/comparison', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'get_comparison'),
                'permission_callback' => array($this, 'permissions_check'),
                'args' => $this->get_comparison_params(),
            ),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/update', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'update_booking'),
                'permission_callback' => array($this, 'permissions_check'),
                'args' => $this->get_update_booking_params(),
            ),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/exclude', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'exclude_match'),
                'permission_callback' => array($this, 'permissions_check'),
                'args' => $this->get_exclude_match_params(),
            ),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/create', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_booking'),
                'permission_callback' => array($this, 'permissions_check'),
                'args' => $this->get_create_booking_params(),
            ),
        ));

        // New endpoints for booking form data
        register_rest_route($this->namespace, '/opening-hours', array(
            array(
                'methods' => array(WP_REST_Server::READABLE, WP_REST_Server::CREATABLE),
                'callback' => array($this, 'get_opening_hours'),
                'permission_callback' => array($this, 'permissions_check'),
                'args' => array(
                    'date' => array(
                        'required' => false,
                        'type' => 'string',
                        'description' => 'Optional date in YYYY-MM-DD format',
                    ),
                    'context' => array(
                        'required' => false,
                        'type' => 'string',
                        'description' => 'Response format context (e.g., chrome-extension for HTML)',
                    ),
                ),
            ),
        ));

        register_rest_route($this->namespace, '/available-times', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'get_available_times'),
                'permission_callback' => array($this, 'permissions_check'),
                'args' => array(
                    'date' => array(
                        'required' => true,
                        'type' => 'string',
                        'description' => 'Date in YYYY-MM-DD format',
                    ),
                    'people' => array(
                        'required' => true,
                        'type' => 'integer',
                        'description' => 'Number of people',
                    ),
                    'opening_hour_id' => array(
                        'required' => false,
                        'type' => 'string',
                        'description' => 'Optional opening hour period ID filter',
                    ),
                    'context' => array(
                        'required' => false,
                        'type' => 'string',
                        'description' => 'Response format context (e.g., chrome-extension for HTML)',
                    ),
                ),
            ),
        ));

        register_rest_route($this->namespace, '/dietary-choices', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_dietary_choices'),
                'permission_callback' => array($this, 'permissions_check'),
                'args' => array(
                    'context' => array(
                        'required' => false,
                        'type' => 'string',
                        'description' => 'Response format context (e.g., chrome-extension for HTML)',
                    ),
                ),
            ),
        ));

        register_rest_route($this->namespace, '/special-events', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_special_events'),
                'permission_callback' => array($this, 'permissions_check'),
                'args' => array(
                    'date' => array(
                        'required' => true,
                        'type' => 'string',
                        'description' => 'Date in YYYY-MM-DD format',
                    ),
                    'context' => array(
                        'required' => false,
                        'type' => 'string',
                        'description' => 'Response format context (e.g., chrome-extension for HTML)',
                    ),
                ),
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
        $status = $booking['booking_status'] ?? 'unknown';

        // Extract occupants
        $occupants = $this->extract_occupants($booking);

        // Extract tariff types
        $tariffs = $this->extract_tariffs($booking);

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

        // Extract booking placed timestamp
        $booking_placed = $booking['booking_placed'] ?? null;

        return array(
            'booking_id' => $booking_id,
            'guest_name' => $guest_name,
            'arrival_date' => $arrival_date,
            'departure_date' => $departure_date,
            'nights' => $nights,
            'status' => $status,
            'booking_source' => $booking_source,
            'occupants' => $occupants,
            'tariffs' => $tariffs,
            'booking_placed' => $booking_placed,
            'actions_required' => array_unique($actions_required),
            'critical_count' => $critical_count,
            'warning_count' => $warning_count,
            'check_issues' => $check_issues,
            'match_details' => $match_result
        );
    }

    /**
     * Extract occupant counts from booking
     */
    private function extract_occupants($booking) {
        // Use direct booking fields from NewBook
        $adults = intval($booking['booking_adults'] ?? 0);
        $children = intval($booking['booking_children'] ?? 0);
        $infants = intval($booking['booking_infants'] ?? 0);

        return array(
            'adults' => $adults,
            'children' => $children,
            'infants' => $infants
        );
    }

    /**
     * Extract tariff types from booking
     */
    private function extract_tariffs($booking) {
        $tariffs = array();

        if (isset($booking['tariffs_quoted']) && is_array($booking['tariffs_quoted'])) {
            foreach ($booking['tariffs_quoted'] as $tariff) {
                if (isset($tariff['label']) && !empty($tariff['label'])) {
                    $tariffs[] = $tariff['label'];
                }
            }
        }

        return array_unique($tariffs);
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

    /**
     * Get comparison data between hotel and Resos booking
     *
     * @param WP_REST_Request $request Full request data
     * @return WP_REST_Response|WP_Error Response object
     */
    public function get_comparison($request) {
        try {
            $booking_id = $request->get_param('booking_id');
            $date = $request->get_param('date');
            $resos_booking_id = $request->get_param('resos_booking_id');

            // Fetch hotel booking
            $searcher = new BMA_NewBook_Search();
            $hotel_booking = $searcher->get_booking_by_id($booking_id);

            if (!$hotel_booking) {
                return new WP_Error(
                    'booking_not_found',
                    __('Hotel booking not found', 'booking-match-api'),
                    array('status' => 404)
                );
            }

            // Fetch Resos bookings for the date
            $matcher = new BMA_Matcher();

            // Use reflection to access the private fetch_resos_bookings method
            $reflection = new ReflectionClass($matcher);
            $method = $reflection->getMethod('fetch_resos_bookings');
            $method->setAccessible(true);
            $resos_bookings = $method->invoke($matcher, $date);

            if (empty($resos_bookings)) {
                return new WP_Error(
                    'no_resos_bookings',
                    __('No Resos bookings found for this date', 'booking-match-api'),
                    array('status' => 404)
                );
            }

            // If resos_booking_id provided, find that specific booking
            $resos_booking = null;
            if (!empty($resos_booking_id)) {
                foreach ($resos_bookings as $booking) {
                    $id = $booking['_id'] ?? $booking['id'] ?? '';
                    if ($id === $resos_booking_id) {
                        $resos_booking = $booking;
                        break;
                    }
                }

                if (!$resos_booking) {
                    return new WP_Error(
                        'resos_booking_not_found',
                        __('Specified Resos booking not found', 'booking-match-api'),
                        array('status' => 404)
                    );
                }
            } else {
                // No specific Resos booking ID - find best match for this date
                // Use matcher to get matches for this night
                $night_match = $this->get_night_match_from_all($hotel_booking, $date);

                if (empty($night_match['resos_matches'])) {
                    return new WP_Error(
                        'no_matches',
                        __('No matching Resos bookings found for this date', 'booking-match-api'),
                        array('status' => 404)
                    );
                }

                // Get the best match (first one, as matches are sorted by score)
                $best_match = $night_match['resos_matches'][0];
                $best_resos_id = $best_match['resos_booking_id'];

                // Find the full Resos booking data
                foreach ($resos_bookings as $booking) {
                    $id = $booking['_id'] ?? $booking['id'] ?? '';
                    if ($id === $best_resos_id) {
                        $resos_booking = $booking;
                        break;
                    }
                }
            }

            if (!$resos_booking) {
                return new WP_Error(
                    'resos_booking_error',
                    __('Could not retrieve Resos booking data', 'booking-match-api'),
                    array('status' => 500)
                );
            }

            // Generate comparison data
            $comparison = new BMA_Comparison();
            $comparison_data = $comparison->prepare_comparison_data($hotel_booking, $resos_booking, $date);

            // Add metadata
            $response = array(
                'success' => true,
                'booking_id' => $booking_id,
                'date' => $date,
                'resos_booking_id' => $resos_booking['_id'] ?? $resos_booking['id'] ?? '',
                'comparison' => $comparison_data
            );

            return rest_ensure_response($response);

        } catch (Exception $e) {
            error_log('BMA Comparison Error: ' . $e->getMessage());
            return new WP_Error(
                'comparison_error',
                __('Error generating comparison', 'booking-match-api'),
                array('status' => 500)
            );
        }
    }

    /**
     * Helper method to get night match data
     */
    private function get_night_match_from_all($hotel_booking, $date) {
        $matcher = new BMA_Matcher();
        $all_matches = $matcher->match_booking_all_nights($hotel_booking);

        // Find the match for the requested date
        foreach ($all_matches['nights'] as $night) {
            if ($night['date'] === $date) {
                return $night;
            }
        }

        return array('resos_matches' => array());
    }

    /**
     * Get comparison endpoint parameters
     */
    protected function get_comparison_params() {
        return array(
            'booking_id' => array(
                'description' => __('NewBook booking ID', 'booking-match-api'),
                'type' => 'integer',
                'required' => true,
                'sanitize_callback' => 'absint',
            ),
            'date' => array(
                'description' => __('Date to compare (YYYY-MM-DD)', 'booking-match-api'),
                'type' => 'string',
                'required' => true,
                'validate_callback' => function($param, $request, $key) {
                    // Validate date format
                    $date = DateTime::createFromFormat('Y-m-d', $param);
                    return $date && $date->format('Y-m-d') === $param;
                },
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'resos_booking_id' => array(
                'description' => __('Specific Resos booking ID to compare (optional)', 'booking-match-api'),
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
        );
    }

    /**
     * Update a Resos booking with new field values
     *
     * @param WP_REST_Request $request Full request data
     * @return WP_REST_Response|WP_Error Response object
     */
    public function update_booking($request) {
        try {
            $booking_id = $request->get_param('booking_id');
            $updates = $request->get_param('updates');

            $actions = new BMA_Booking_Actions();
            $result = $actions->update_resos_booking($booking_id, $updates);

            if (!$result['success']) {
                return new WP_Error(
                    'update_failed',
                    $result['message'],
                    array('status' => 400)
                );
            }

            return rest_ensure_response($result);

        } catch (Exception $e) {
            error_log('BMA Update Booking Error: ' . $e->getMessage());
            return new WP_Error(
                'update_error',
                __('Error updating booking', 'booking-match-api'),
                array('status' => 500)
            );
        }
    }

    /**
     * Exclude a Resos booking from matching a hotel booking
     *
     * @param WP_REST_Request $request Full request data
     * @return WP_REST_Response|WP_Error Response object
     */
    public function exclude_match($request) {
        try {
            $resos_booking_id = $request->get_param('resos_booking_id');
            $hotel_booking_id = $request->get_param('hotel_booking_id');

            $actions = new BMA_Booking_Actions();
            $result = $actions->exclude_resos_match($resos_booking_id, $hotel_booking_id);

            if (!$result['success']) {
                return new WP_Error(
                    'exclude_failed',
                    $result['message'],
                    array('status' => 400)
                );
            }

            return rest_ensure_response($result);

        } catch (Exception $e) {
            error_log('BMA Exclude Match Error: ' . $e->getMessage());
            return new WP_Error(
                'exclude_error',
                __('Error excluding match', 'booking-match-api'),
                array('status' => 500)
            );
        }
    }

    /**
     * Get update booking endpoint parameters
     */
    protected function get_update_booking_params() {
        return array(
            'booking_id' => array(
                'description' => __('Resos booking ID to update', 'booking-match-api'),
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'updates' => array(
                'description' => __('Field updates (JSON object)', 'booking-match-api'),
                'type' => 'object',
                'required' => true,
            ),
        );
    }

    /**
     * Get exclude match endpoint parameters
     */
    protected function get_exclude_match_params() {
        return array(
            'resos_booking_id' => array(
                'description' => __('Resos booking ID to exclude', 'booking-match-api'),
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'hotel_booking_id' => array(
                'description' => __('Hotel booking ID to exclude from matching', 'booking-match-api'),
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ),
        );
    }

    /**
     * Create a new Resos booking
     *
     * @param WP_REST_Request $request Full request data
     * @return WP_REST_Response|WP_Error Response object
     */
    public function create_booking($request) {
        try {
            // Extract all booking data from request
            $booking_data = array(
                'date' => $request->get_param('date'),
                'time' => $request->get_param('time'),
                'people' => $request->get_param('people'),
                'guest_name' => $request->get_param('guest_name'),
                'guest_phone' => $request->get_param('guest_phone'),
                'guest_email' => $request->get_param('guest_email'),
                'notification_sms' => $request->get_param('notification_sms'),
                'notification_email' => $request->get_param('notification_email'),
                'referrer' => $request->get_param('referrer'),
                'language_code' => $request->get_param('language_code'),
                'opening_hour_id' => $request->get_param('opening_hour_id'),
                'booking_note' => $request->get_param('booking_note'),
                'booking_ref' => $request->get_param('booking_ref'),
                'hotel_guest' => $request->get_param('hotel_guest'),
                'dbb' => $request->get_param('dbb'),
                'dietary_requirements' => $request->get_param('dietary_requirements'),
                'dietary_other' => $request->get_param('dietary_other'),
            );

            $actions = new BMA_Booking_Actions();
            $result = $actions->create_resos_booking($booking_data);

            if (!$result['success']) {
                return new WP_Error(
                    'create_failed',
                    $result['message'],
                    array('status' => 400)
                );
            }

            return rest_ensure_response($result);

        } catch (Exception $e) {
            error_log('BMA Create Booking Error: ' . $e->getMessage());
            return new WP_Error(
                'create_error',
                __('Error creating booking', 'booking-match-api'),
                array('status' => 500)
            );
        }
    }

    /**
     * Get create booking endpoint parameters
     */
    protected function get_create_booking_params() {
        return array(
            'date' => array(
                'description' => __('Booking date (YYYY-MM-DD)', 'booking-match-api'),
                'type' => 'string',
                'required' => true,
                'validate_callback' => function($param, $request, $key) {
                    $date = DateTime::createFromFormat('Y-m-d', $param);
                    return $date && $date->format('Y-m-d') === $param;
                },
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'time' => array(
                'description' => __('Booking time (HH:MM)', 'booking-match-api'),
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'guest_name' => array(
                'description' => __('Guest name', 'booking-match-api'),
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'people' => array(
                'description' => __('Number of guests', 'booking-match-api'),
                'type' => 'integer',
                'required' => false,
                'default' => 2,
                'sanitize_callback' => 'absint',
            ),
            'guest_phone' => array(
                'description' => __('Guest phone number', 'booking-match-api'),
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'guest_email' => array(
                'description' => __('Guest email', 'booking-match-api'),
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_email',
            ),
            'notification_sms' => array(
                'description' => __('Send SMS notification to guest', 'booking-match-api'),
                'type' => 'boolean',
                'required' => false,
                'default' => false,
            ),
            'notification_email' => array(
                'description' => __('Send email notification to guest', 'booking-match-api'),
                'type' => 'boolean',
                'required' => false,
                'default' => false,
            ),
            'referrer' => array(
                'description' => __('Booking referrer URL', 'booking-match-api'),
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'esc_url_raw',
            ),
            'language_code' => array(
                'description' => __('Language code (e.g., en, es)', 'booking-match-api'),
                'type' => 'string',
                'required' => false,
                'default' => 'en',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'opening_hour_id' => array(
                'description' => __('Resos opening hour ID', 'booking-match-api'),
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'booking_note' => array(
                'description' => __('Restaurant note (visible only to restaurant)', 'booking-match-api'),
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_textarea_field',
            ),
            'booking_ref' => array(
                'description' => __('Hotel booking reference number', 'booking-match-api'),
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'hotel_guest' => array(
                'description' => __('Hotel guest status (e.g., "Yes")', 'booking-match-api'),
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'dbb' => array(
                'description' => __('DBB package status (e.g., "Yes")', 'booking-match-api'),
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'dietary_requirements' => array(
                'description' => __('Dietary requirements (comma-separated choice IDs)', 'booking-match-api'),
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'dietary_other' => array(
                'description' => __('Other dietary requirements (free text)', 'booking-match-api'),
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
        );
    }

    /**
     * Get opening hours endpoint
     *
     * @param WP_REST_Request $request Full request object
     * @return array Response data
     */
    public function get_opening_hours($request) {
        $date = $request->get_param('date');
        $context = $request->get_param('context');

        error_log("BMA Opening Hours: date = " . ($date ? $date : 'all') . ", context = " . ($context ? $context : 'json'));

        $actions = new BMA_Booking_Actions();
        $data = $actions->fetch_opening_hours($date);

        if (empty($data)) {
            return array(
                'success' => false,
                'message' => 'No opening hours found',
                'data' => array()
            );
        }

        // If context is chrome-extension, return formatted HTML
        if ($context === 'chrome-extension') {
            $html = $this->format_opening_hours_html($data);
            return array(
                'success' => true,
                'html' => $html,
                'data' => $data
            );
        }

        // Return raw JSON
        return array(
            'success' => true,
            'data' => $data
        );
    }

    /**
     * Get available times endpoint
     *
     * @param WP_REST_Request $request Full request object
     * @return array Response data
     */
    public function get_available_times($request) {
        $date = $request->get_param('date');
        $people = $request->get_param('people');
        $opening_hour_id = $request->get_param('opening_hour_id');
        $context = $request->get_param('context');

        error_log("BMA Available Times: date = $date, people = $people, context = " . ($context ? $context : 'json'));

        $actions = new BMA_Booking_Actions();
        $result = $actions->fetch_available_times($date, $people, $opening_hour_id);

        if (!$result['success']) {
            return $result;
        }

        // If context is chrome-extension, return formatted HTML
        if ($context === 'chrome-extension') {
            // Also fetch opening hours and special events for complete time slot rendering
            $opening_hours = $actions->fetch_opening_hours($date);
            $special_events = $actions->fetch_special_events($date);

            // Filter to specific period if opening_hour_id provided
            if ($opening_hour_id && !empty($opening_hours)) {
                $opening_hours = array_filter($opening_hours, function($period) use ($opening_hour_id) {
                    return isset($period['_id']) && $period['_id'] === $opening_hour_id;
                });
                $opening_hours = array_values($opening_hours); // Re-index
            }

            $html = $this->format_time_slots_html($result['times'], $opening_hours, $special_events, !empty($opening_hour_id));
            return array(
                'success' => true,
                'html' => $html,
                'times' => $result['times'],
                'periods' => $result['periods']
            );
        }

        // Return raw JSON
        return $result;
    }

    /**
     * Get dietary choices endpoint
     *
     * @param WP_REST_Request $request Full request object
     * @return array Response data
     */
    public function get_dietary_choices($request) {
        $context = $request->get_param('context');

        error_log("BMA Dietary Choices: context = " . ($context ? $context : 'json'));

        $actions = new BMA_Booking_Actions();
        $choices = $actions->fetch_dietary_choices();

        // If context is chrome-extension, return formatted HTML
        if ($context === 'chrome-extension') {
            $html = $this->format_dietary_choices_html($choices);
            return array(
                'success' => true,
                'html' => $html,
                'choices' => $choices
            );
        }

        // Return raw JSON
        return array(
            'success' => true,
            'choices' => $choices
        );
    }

    /**
     * Get special events endpoint
     *
     * @param WP_REST_Request $request Full request object
     * @return array Response data
     */
    public function get_special_events($request) {
        $date = $request->get_param('date');
        $context = $request->get_param('context');

        error_log("BMA Special Events: date = $date, context = " . ($context ? $context : 'json'));

        $actions = new BMA_Booking_Actions();
        $events = $actions->fetch_special_events($date);

        // If context is chrome-extension, return formatted HTML
        if ($context === 'chrome-extension') {
            $html = $this->format_special_events_html($events);
            return array(
                'success' => true,
                'html' => $html,
                'events' => $events
            );
        }

        // Return raw JSON
        return array(
            'success' => true,
            'events' => $events
        );
    }

    /**
     * Format opening hours as HTML select options
     *
     * @param array $opening_hours Array of opening hour objects
     * @return string HTML options
     */
    private function format_opening_hours_html($opening_hours) {
        $html = '';

        foreach ($opening_hours as $period) {
            $id = isset($period['_id']) ? $period['_id'] : '';
            $name = isset($period['name']) ? $period['name'] : '';
            $open = isset($period['open']) ? $period['open'] : 1800;
            $close = isset($period['close']) ? $period['close'] : 2200;

            // Format times for display
            $open_hour = floor($open / 100);
            $open_min = $open % 100;
            $close_hour = floor($close / 100);
            $close_min = $close % 100;

            $open_str = sprintf('%d:%02d', $open_hour, $open_min);
            $close_str = sprintf('%d:%02d', $close_hour, $close_min);

            $label = $name ? "$name ($open_str-$close_str)" : "$open_str-$close_str";

            $html .= sprintf(
                '<option value="%s">%s</option>',
                esc_attr($id),
                esc_html($label)
            );
        }

        return $html;
    }

    /**
     * Format time slots as HTML button grid
     *
     * @param array $available_times Array of available time strings
     * @param array $opening_hours Array of opening hour objects
     * @param array $special_events Array of special event objects
     * @param bool $skip_period_headers Whether to skip period section wrappers (true for single period)
     * @return string HTML for time slot grid
     */
    private function format_time_slots_html($available_times, $opening_hours, $special_events, $skip_period_headers = false) {
        // This is a simplified version - full implementation would match the JavaScript buildTimeSlots function
        $html = '';

        // Convert available times to set for fast lookup
        $available_set = array_flip($available_times);

        if (empty($opening_hours)) {
            $html .= '<p>No time slots available</p>';
        } else {
            foreach ($opening_hours as $period) {
                $period_name = isset($period['name']) ? $period['name'] : 'Service';
                $period_start = isset($period['open']) ? $period['open'] : 1800;
                $period_close = isset($period['close']) ? $period['close'] : 2200;
                $interval = isset($period['interval']) ? $period['interval'] : 15;
                $duration = isset($period['duration']) ? $period['duration'] : 120;

                // Calculate last seating time
                $close_hour = floor($period_close / 100);
                $close_min = $period_close % 100;
                $duration_hours = floor($duration / 60);
                $duration_mins = $duration % 60;

                $close_min -= $duration_mins;
                $close_hour -= $duration_hours;
                if ($close_min < 0) {
                    $close_min += 60;
                    $close_hour--;
                }
                $last_seating = $close_hour * 100 + $close_min;

                // Only add period wrappers if not skipping (i.e., when showing all periods)
                if (!$skip_period_headers) {
                    $html .= '<div class="time-slot-period">';
                    $html .= '<div class="time-slot-period-header">' . esc_html($period_name) . '</div>';
                    $html .= '<div class="time-slot-buttons">';
                }

                // Generate time slots
                $current_hour = floor($period_start / 100);
                $current_min = $period_start % 100;

                while (true) {
                    $current_time = $current_hour * 100 + $current_min;
                    if ($current_time > $last_seating) {
                        break;
                    }

                    $time_str = $current_hour . ':' . ($current_min < 10 ? '0' . $current_min : $current_min);
                    $is_available = isset($available_set[$time_str]);

                    $btn_class = 'time-slot-btn' . ($is_available ? '' : ' unavailable');
                    $tooltip = $is_available ? '' : ' data-tooltip="Fully booked - Override allowed"';

                    $html .= sprintf(
                        '<button type="button" class="%s" data-time="%s"%s>%s</button>',
                        esc_attr($btn_class),
                        esc_attr($time_str),
                        $tooltip,
                        esc_html($time_str)
                    );

                    // Increment by interval
                    $current_min += $interval;
                    if ($current_min >= 60) {
                        $current_min -= 60;
                        $current_hour++;
                    }
                }

                // Only close period wrappers if we opened them
                if (!$skip_period_headers) {
                    $html .= '</div></div>';
                }
            }
        }

        return $html;
    }

    /**
     * Format dietary choices as HTML checkboxes
     *
     * @param array $choices Array of dietary choice objects
     * @return string HTML checkboxes
     */
    private function format_dietary_choices_html($choices) {
        $html = '';

        foreach ($choices as $choice) {
            $id = isset($choice['_id']) ? $choice['_id'] : '';
            $name = isset($choice['name']) ? $choice['name'] : '';

            $html .= sprintf(
                '<div class="dietary-checkbox-item"><label><input type="checkbox" class="diet-checkbox" data-choice-id="%s" data-choice-name="%s"> %s</label></div>',
                esc_attr($id),
                esc_attr($name),
                esc_html($name)
            );
        }

        return $html;
    }

    /**
     * Format special events as HTML alert banners
     *
     * @param array $events Array of special event objects
     * @return string HTML alerts
     */
    private function format_special_events_html($events) {
        $html = '';

        foreach ($events as $event) {
            if (isset($event['isOpen']) && $event['isOpen'] === true) {
                continue; // Skip open events
            }

            $name = isset($event['name']) ? $event['name'] : 'Special Event';
            $html .= sprintf(
                '<div class="bma-special-event-alert">%s</div>',
                esc_html($name)
            );
        }

        return $html;
    }
}
