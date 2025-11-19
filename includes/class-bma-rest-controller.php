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

        register_rest_route($this->namespace, '/' . $this->rest_base . '/group', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'update_group'),
                'permission_callback' => array($this, 'permissions_check'),
                'args' => $this->get_update_group_params(),
            ),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/for-date', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_bookings_for_date'),
                'permission_callback' => array($this, 'permissions_check'),
                'args' => $this->get_bookings_for_date_params(),
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

        register_rest_route($this->namespace, '/all-bookings-for-date', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_all_bookings_for_date'),
                'permission_callback' => array($this, 'permissions_check'),
                'args' => array(
                    'date' => array(
                        'required' => true,
                        'type' => 'string',
                        'description' => 'Date in YYYY-MM-DD format',
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

        register_rest_route($this->namespace, '/staying', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_staying_bookings'),
                'permission_callback' => array($this, 'permissions_check'),
                'args' => array(
                    'date' => array(
                        'required' => false,
                        'type' => 'string',
                        'description' => 'Date in YYYY-MM-DD format (defaults to today)',
                    ),
                    'force_refresh' => array(
                        'required' => false,
                        'type' => 'boolean',
                        'description' => 'Force refresh of Resos cache',
                        'default' => false,
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
        bma_log(sprintf(
            'BMA-AUTH: Request [%s %s] Origin: %s | Auth: %s | PHP_AUTH_USER: %s',
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI'],
            isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : 'none',
            isset($_SERVER['HTTP_AUTHORIZATION']) ? 'present' : 'missing',
            isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : 'not set'
        ), 'debug');

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
                    bma_log("BMA-AUTH: REJECTED - User '{$user->user_login}' lacks read capability", 'warning');
                    return new WP_Error(
                        'rest_forbidden',
                        __('You do not have permission to access this resource.', 'booking-match-api'),
                        array('status' => 403)
                    );
                }

                bma_log("BMA-AUTH: ACCEPTED - User '{$user->user_login}' authenticated via Application Password", 'debug');
                return true;
            } else {
                bma_log("BMA-AUTH: Manual Application Password auth failed for user: {$username}", 'warning');
            }
        }

        // Require WordPress authentication (supports Application Passwords)
        if (!is_user_logged_in()) {
            bma_log('BMA-AUTH: REJECTED - No valid authentication provided (not logged in, no valid PHP_AUTH)', 'warning');
            return new WP_Error(
                'rest_forbidden',
                __('Authentication required. Please provide valid WordPress credentials.', 'booking-match-api'),
                array('status' => 401)
            );
        }

        // User must have at least 'read' capability
        if (!current_user_can('read')) {
            $current_user = wp_get_current_user();
            bma_log("BMA-AUTH: REJECTED - User '{$current_user->user_login}' lacks read capability", 'warning');
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access this resource.', 'booking-match-api'),
                array('status' => 403)
            );
        }

        $current_user = wp_get_current_user();
        bma_log("BMA-AUTH: ACCEPTED - User '{$current_user->user_login}' authenticated", 'debug');
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
            'force_refresh' => array(
                'description' => __('Force refresh of Resos cache', 'booking-match-api'),
                'type' => 'boolean',
                'required' => false,
                'default' => false,
            ),
        );
    }

    /**
     * Handle booking match request
     */
    public function match_booking($request) {
        try {
            // Capture request context for comprehensive logging
            $request_context = bma_get_request_context($request);

            // Extract parameters
            $booking_id = $request->get_param('booking_id');
            $guest_name = $request->get_param('guest_name');
            $email = $request->get_param('email_address');
            $phone = $request->get_param('phone_number');
            $group_id = $request->get_param('group_id');
            $agent_ref = $request->get_param('travelagent_reference');
            $context = $request->get_param('context') ?: 'json';
            $force_refresh = $request->get_param('force_refresh') ?: false;

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
                $bookings = $searcher->get_booking_by_id($booking_id, $force_refresh, $request_context);
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
                ), $request_context);

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

            // PRE-CACHE: Collect all unique dates from all bookings to prevent duplicate API calls
            $all_dates = array();
            foreach ($bookings as $booking) {
                $dates = $this->extract_booking_dates($booking);
                $all_dates = array_merge($all_dates, $dates);
            }
            $all_dates = array_unique($all_dates);

            // Pre-populate cache for all dates BEFORE matching bookings
            $matcher = new BMA_Matcher();
            $searcher = new BMA_NewBook_Search();

            if (!empty($all_dates)) {
                bma_log("Restaurant: Pre-caching " . count($all_dates) . " unique dates for booking", 'info');

                foreach ($all_dates as $date) {
                    // Warm ResOS cache
                    $matcher->fetch_resos_bookings($date, $force_refresh);

                    // Warm NewBook cache
                    $searcher->fetch_hotel_bookings_for_date($date, $force_refresh, $request_context);
                }
            }

            // Match each booking with Resos
            $results = array();

            foreach ($bookings as $booking) {
                $match_result = $matcher->match_booking_all_nights($booking, $force_refresh, $request_context);
                $results[] = $match_result;
            }

            // Format response based on context
            $formatter = new BMA_Response_Formatter();
            $response = $formatter->format_response($results, $search_method, $context);

            return rest_ensure_response($response);

        } catch (Exception $e) {
            bma_log('BMA: Exception in match_booking: ' . $e->getMessage(), 'error');
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
            'force_refresh' => array(
                'description' => __('Force refresh bookings list (not matching data)', 'booking-match-api'),
                'type' => 'boolean',
                'required' => false,
                'default' => false,
            ),
            'force_refresh_matches' => array(
                'description' => __('Force refresh matching/Resos data', 'booking-match-api'),
                'type' => 'boolean',
                'required' => false,
                'default' => false,
            ),
            'cancelled_hours' => array(
                'description' => __('Show cancelled bookings from last X hours (24/48/72)', 'booking-match-api'),
                'type' => 'integer',
                'required' => false,
                'default' => 24,
                'sanitize_callback' => 'absint',
            ),
            'include_flagged_cancelled' => array(
                'description' => __('Include older cancelled bookings (within 5 days) that have flags/issues', 'booking-match-api'),
                'type' => 'boolean',
                'required' => false,
                'default' => true,
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
            'force_refresh' => array(
                'description' => __('Force refresh of Resos cache', 'booking-match-api'),
                'type' => 'boolean',
                'required' => false,
                'default' => false,
            ),
        );
    }

    /**
     * Get summary of recent bookings with actions required
     */
    public function get_summary($request) {
        try {
            // Capture request context for comprehensive logging
            $request_context = bma_get_request_context($request);

            $context = $request->get_param('context') ?: 'json';
            $limit = $request->get_param('limit') ?: 5;

            // Cancelled bookings parameters
            $cancelled_hours = $request->get_param('cancelled_hours') ?: 24;
            $include_flagged_cancelled = $request->get_param('include_flagged_cancelled') !== false ? $request->get_param('include_flagged_cancelled') : true;

            // Separate force refresh controls
            $force_refresh_bookings = $request->get_param('force_refresh') ?: false;
            $force_refresh_matches = $request->get_param('force_refresh_matches') ?: false;

            bma_log("BMA Summary: Requested limit = {$limit}, cancelled_hours = {$cancelled_hours}, context = {$context}, force_refresh_bookings = " . ($force_refresh_bookings ? 'true' : 'false') . ", force_refresh_matches = " . ($force_refresh_matches ? 'true' : 'false'), 'debug');

            // Fetch recently placed bookings from NewBook (use force_refresh_bookings for THIS call only)
            $searcher = new BMA_NewBook_Search();
            $recent_bookings = $searcher->fetch_recent_placed_bookings($limit, 72, $force_refresh_bookings, $request_context);

            // Fetch recently cancelled bookings (last 5 days)
            $cancelled_bookings = $searcher->fetch_recent_cancelled_bookings(5, $force_refresh_bookings, $request_context);

            // PRE-CACHE: Collect all unique dates from all bookings to prevent duplicate API calls
            $all_dates = array();
            foreach ($recent_bookings as $nb_booking) {
                $dates = $this->extract_booking_dates($nb_booking);
                $all_dates = array_merge($all_dates, $dates);
            }
            foreach ($cancelled_bookings as $nb_booking) {
                $dates = $this->extract_booking_dates($nb_booking);
                $all_dates = array_merge($all_dates, $dates);
            }
            $all_dates = array_unique($all_dates);

            // Pre-populate cache for all dates BEFORE processing bookings
            $matcher = new BMA_Matcher();

            if (!empty($all_dates)) {
                bma_log("Summary: Pre-caching " . count($all_dates) . " unique dates", 'info');

                foreach ($all_dates as $date) {
                    // Warm ResOS cache
                    $matcher->fetch_resos_bookings($date, $force_refresh_bookings);

                    // Warm NewBook cache
                    $searcher->fetch_hotel_bookings_for_date($date, $force_refresh_bookings, $request_context);
                }
            }

            // Process placed bookings
            $summary_bookings = array();
            $total_critical_count = 0;
            $total_warning_count = 0;

            foreach ($recent_bookings as $nb_booking) {
                // Check if booking is cancelled (even in "placed" section, it may have been cancelled after placement)
                $booking_status = strtolower($nb_booking['booking_status'] ?? '');
                $is_cancelled = ($booking_status === 'cancelled');

                // Pass force_refresh_matches to matching operations (NOT force_refresh_bookings)
                $processed = $this->process_booking_for_summary($nb_booking, $force_refresh_matches, $matcher, $is_cancelled, $request_context);
                $summary_bookings[] = $processed;
                $total_critical_count += $processed['critical_count'];
                $total_warning_count += $processed['warning_count'];
            }

            // Process cancelled bookings
            $summary_cancelled = array();
            $cancelled_critical_count = 0;
            $cancelled_warning_count = 0;

            // Calculate cutoff time for cancelled bookings filter
            $cancelled_cutoff = date('Y-m-d H:i:s', strtotime("-{$cancelled_hours} hours"));

            foreach ($cancelled_bookings as $nb_booking) {
                // Process cancelled booking (orphaned ResOS bookings = CRITICAL)
                $processed = $this->process_booking_for_summary($nb_booking, $force_refresh_matches, $matcher, true, $request_context);

                // Get cancellation time (use booking_modified if available, else booking_id as proxy)
                $cancelled_time = $nb_booking['booking_modified'] ?? null;

                // Determine if booking should be included
                $include_booking = false;

                // Include if within time window
                if ($cancelled_time && $cancelled_time >= $cancelled_cutoff) {
                    $include_booking = true;
                }

                // Include if has flags (orphaned ResOS bookings) and include_flagged option is true
                if ($include_flagged_cancelled && ($processed['critical_count'] > 0 || $processed['warning_count'] > 0)) {
                    $include_booking = true;
                }

                if ($include_booking) {
                    $summary_cancelled[] = $processed;
                    $cancelled_critical_count += $processed['critical_count'];
                    $cancelled_warning_count += $processed['warning_count'];
                }
            }

            // Total counts (placed + cancelled)
            $total_all_critical = $total_critical_count + $cancelled_critical_count;
            $total_all_warning = $total_warning_count + $cancelled_warning_count;

            // Merge placed and cancelled bookings into single activity array
            $activity_bookings = array_merge($summary_bookings, $summary_cancelled);

            // Remove duplicates (keep cancelled version if booking appears in both arrays)
            $seen_ids = array();
            $activity_bookings = array_filter($activity_bookings, function($booking) use (&$seen_ids) {
                $booking_id = $booking['booking_id'];

                // If we haven't seen this ID yet, mark it as seen
                if (!isset($seen_ids[$booking_id])) {
                    $seen_ids[$booking_id] = $booking;
                    return true;
                }

                // If we've seen this ID before, keep the cancelled version (more current state)
                if ($booking['is_cancelled'] && !$seen_ids[$booking_id]['is_cancelled']) {
                    $seen_ids[$booking_id] = $booking;
                    return true;
                }

                // Otherwise skip this duplicate
                return false;
            });

            // Re-index array after filtering
            $activity_bookings = array_values($activity_bookings);

            // Sort by timestamp (most recent first)
            usort($activity_bookings, function($a, $b) {
                // Use booking_cancelled for cancelled, booking_placed for placed
                $time_a = $a['is_cancelled'] ? ($a['booking_cancelled'] ?? $a['booking_placed']) : $a['booking_placed'];
                $time_b = $b['is_cancelled'] ? ($b['booking_cancelled'] ?? $b['booking_placed']) : $b['booking_placed'];
                return strcmp($time_b, $time_a); // DESC order (most recent first)
            });

            // Format response based on context
            if ($context === 'chrome-summary' || $context === 'webapp-summary') {
                $formatter = new BMA_Response_Formatter();
                return array(
                    'success' => true,
                    'html_activity' => $formatter->format_summary_html($activity_bookings, $context),
                    'activity_bookings' => $activity_bookings,
                    'activity_count' => count($activity_bookings),
                    'placed_count' => count($summary_bookings),
                    'cancelled_count' => count($summary_cancelled),
                    'critical_count' => $total_all_critical,
                    'warning_count' => $total_all_warning,
                    'badge_count' => $total_all_critical + $total_all_warning,
                );
            }

            // Default JSON response
            return array(
                'success' => true,
                'activity_bookings' => $activity_bookings,
                'placed_bookings' => $summary_bookings,
                'cancelled_bookings' => $summary_cancelled,
                'activity_count' => count($activity_bookings),
                'placed_count' => count($summary_bookings),
                'cancelled_count' => count($summary_cancelled),
                'critical_count' => $total_all_critical,
                'warning_count' => $total_all_warning,
            );

        } catch (Exception $e) {
            bma_log('BMA Summary Error: ' . $e->getMessage(), 'error');
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
    private function process_booking_for_summary($booking, $force_refresh = false, $matcher = null, $is_cancelled = false, $request_context = null) {
        // Extract basic info
        $booking_id = $booking['booking_id'];
        $guest_name = $this->extract_guest_name($booking);
        $arrival_date = substr($booking['booking_arrival'] ?? '', 0, 10);
        $departure_date = substr($booking['booking_departure'] ?? '', 0, 10);
        $nights = $this->calculate_nights($arrival_date, $departure_date);
        $status = $booking['booking_status'] ?? 'unknown';

        // Try multiple possible field names for group ID
        $group_id = $booking['booking_group_id']
                 ?? $booking['group_id']
                 ?? $booking['bookings_group_id']
                 ?? null;

        // Extract occupants
        $occupants = $this->extract_occupants($booking);

        // Extract tariff types
        $tariffs = $this->extract_tariffs($booking);

        // Match with restaurants - reuse matcher if provided, otherwise create new one
        if ($matcher === null) {
            $matcher = new BMA_Matcher();
        }
        $match_result = $matcher->match_booking_all_nights($booking, $force_refresh, $request_context);

        // Determine booking source (placeholder)
        $source_detector = new BMA_Booking_Source();
        $booking_source = $source_detector->determine_source($booking);

        // Check for issues (placeholder)
        $issue_checker = new BMA_Issue_Checker();
        $issues = $issue_checker->check_booking($booking);

        // Analyze for actions required with severity levels
        $actions_required = array();
        $critical_count = 0;  // Red flags: Package bookings without restaurant, orphaned ResOS bookings for cancelled
        $warning_count = 0;   // Amber flags: Multiple matches, non-primary matches
        $check_issues = count($issues);

        foreach ($match_result['nights'] as &$night) {
            $has_matches = !empty($night['resos_matches']);
            $match_count = count($night['resos_matches']);
            $has_package = $night['has_package'] ?? false;

            // For CANCELLED bookings: Orphaned ResOS bookings
            if ($is_cancelled && $has_matches) {
                $actions_required[] = 'orphaned_resos';

                // Flag each match as orphaned and determine severity based on match confidence
                foreach ($night['resos_matches'] as &$match) {
                    $match['is_orphaned'] = true;
                    $is_primary = $match['match_info']['is_primary'] ?? false;

                    // Primary match = definitely orphaned (CRITICAL - needs cancellation)
                    // Suggested match = potentially orphaned (WARNING - needs review)
                    if ($is_primary) {
                        $critical_count++;
                    } else {
                        $warning_count++;
                    }
                }
            }
            // For PLACED bookings: Normal logic
            elseif (!$is_cancelled) {
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
        }

        // Add check issues if any (warnings)
        if ($check_issues > 0) {
            $actions_required[] = 'check_required';
            $warning_count += $check_issues;
        }

        // Extract booking timestamps
        $booking_placed = $booking['booking_placed'] ?? null;
        $booking_cancelled = $booking['booking_cancelled'] ?? null;

        return array(
            'booking_id' => $booking_id,
            'guest_name' => $guest_name,
            'arrival_date' => $arrival_date,
            'departure_date' => $departure_date,
            'nights' => $nights,
            'status' => $status,
            'group_id' => $group_id,
            'booking_source' => $booking_source,
            'occupants' => $occupants,
            'tariffs' => $tariffs,
            'booking_placed' => $booking_placed,
            'booking_cancelled' => $booking_cancelled,
            'actions_required' => array_unique($actions_required),
            'critical_count' => $critical_count,
            'warning_count' => $warning_count,
            'check_issues' => $check_issues,
            'match_details' => $match_result,
            'is_cancelled' => $is_cancelled
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
            // Capture request context for comprehensive logging
            $request_context = bma_get_request_context($request);

            $booking_id = $request->get_param('booking_id');
            $context = $request->get_param('context') ?: 'json';
            $force_refresh = $request->get_param('force_refresh') ?: false;

            // Fetch booking details from NewBook
            $searcher = new BMA_NewBook_Search();
            $nb_booking = $searcher->get_booking_by_id($booking_id, $force_refresh, $request_context);

            if (!$nb_booking) {
                return new WP_Error(
                    'booking_not_found',
                    __('Booking not found', 'booking-match-api'),
                    array('status' => 404)
                );
            }

            // PRE-CACHE: Extract all dates from booking to prevent duplicate API calls
            $dates = $this->extract_booking_dates($nb_booking);

            // Pre-populate cache for all dates BEFORE processing
            $matcher = new BMA_Matcher();
            $searcher = new BMA_NewBook_Search();

            if (!empty($dates)) {
                bma_log("Checks: Pre-caching " . count($dates) . " dates for booking", 'info');

                foreach ($dates as $date) {
                    // Warm ResOS cache
                    $matcher->fetch_resos_bookings($date, $force_refresh);

                    // Warm NewBook cache
                    $searcher->fetch_hotel_bookings_for_date($date, $force_refresh, $request_context);
                }
            }

            // Process booking through matcher to get normalized fields (same as Restaurant tab)
            $processed_booking = $matcher->match_booking_all_nights($nb_booking, $force_refresh, $request_context);

            // TODO: Implement actual checks logic
            // For now, return placeholder/stub data
            $checks = array(
                'twin_bed_request' => false,
                'sofa_bed_request' => false,
                'special_requests' => array(),
                'room_features_mismatch' => array(),
            );

            $badge_count = 0; // No issues for now

            if ($context === 'chrome-checks' || $context === 'webapp-checks') {
                // Format as HTML for Chrome sidepanel or webapp
                $formatter = new BMA_Response_Formatter();
                return array(
                    'success' => true,
                    'html' => $formatter->format_checks_html($processed_booking, $checks, $context),
                    'badge_count' => $badge_count,
                );
            }

            // Default JSON response
            return array(
                'success' => true,
                'booking_id' => $booking_id,
                'booking' => $processed_booking,
                'checks' => $checks,
                'badge_count' => $badge_count,
            );

        } catch (Exception $e) {
            bma_log('BMA Checks Error: ' . $e->getMessage(), 'error');
            return new WP_Error(
                'checks_error',
                __('Error retrieving checks', 'booking-match-api'),
                array('status' => 500)
            );
        }
    }

    /**
     * Get all bookings staying on a specific date
     *
     * @param WP_REST_Request $request Full request data
     * @return WP_REST_Response|WP_Error Response object
     */
    public function get_staying_bookings($request) {
        try {
            // Capture request context for comprehensive logging
            $request_context = bma_get_request_context($request);

            $date = $request->get_param('date');
            $force_refresh = $request->get_param('force_refresh') ?: false;
            $context = $request->get_param('context') ?: '';

            // Default to today if no date provided
            if (empty($date)) {
                $date = date('Y-m-d');
            }

            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return new WP_Error(
                    'invalid_date',
                    __('Invalid date format. Use YYYY-MM-DD', 'booking-match-api'),
                    array('status' => 400)
                );
            }

            // Handle chrome-restaurant context - return ResOS bookings with hotel room numbers
            if ($context === 'chrome-restaurant') {
                bma_log("BMA Restaurant: Fetching ResOS bookings for date = {$date} (chrome-restaurant context)", 'debug');

                $matcher = new BMA_Matcher();
                $resos_bookings = $matcher->fetch_resos_bookings($date, $force_refresh);

                bma_log("BMA Restaurant: Fetched " . count($resos_bookings) . " ResOS bookings for {$date}", 'debug');

                // Fetch hotel bookings to match room numbers
                $searcher = new BMA_NewBook_Search();
                $hotel_bookings = $searcher->fetch_hotel_bookings_for_date($date, $force_refresh, $request_context);

                bma_log("BMA Restaurant: Fetched " . count($hotel_bookings) . " hotel bookings for {$date}", 'debug');

                // Create hotel bookings lookup by booking_id for fast matching
                $hotel_bookings_by_id = array();
                foreach ($hotel_bookings as $hotel_booking) {
                    $booking_id = $hotel_booking['booking_id'] ?? null;
                    if ($booking_id) {
                        $hotel_bookings_by_id[$booking_id] = $hotel_booking;
                    }
                }

                // Enhance each ResOS booking with room number from hotel booking match
                $enhanced_bookings = array();
                foreach ($resos_bookings as $resos_booking) {
                    // Extract "Booking #" and "GROUP/EXCLUDE" custom fields
                    $hotel_booking_id = null;
                    $group_exclude_field = null;
                    $custom_fields = $resos_booking['customFields'] ?? array();

                    foreach ($custom_fields as $field) {
                        if (($field['name'] ?? '') === 'Booking #') {
                            $hotel_booking_id = $field['value'] ?? null;
                        } elseif (($field['name'] ?? '') === 'GROUP/EXCLUDE') {
                            $group_exclude_field = $field['value'] ?? null;
                        }
                    }

                    // If we have a hotel booking ID, look up the room number
                    if ($hotel_booking_id && isset($hotel_bookings_by_id[$hotel_booking_id])) {
                        $hotel_booking = $hotel_bookings_by_id[$hotel_booking_id];
                        $resos_booking['room_number'] = $hotel_booking['site_name'] ?? '';
                        $resos_booking['is_hotel_guest'] = true;
                        $resos_booking['hotel_booking_id'] = $hotel_booking_id;

                        bma_log("BMA Restaurant: Matched ResOS booking {$resos_booking['_id']} to hotel booking {$hotel_booking_id}, room: {$resos_booking['room_number']}", 'debug');
                    } else {
                        $resos_booking['room_number'] = '';
                        $resos_booking['is_hotel_guest'] = false;
                    }

                    // Parse GROUP/EXCLUDE field to find grouped booking rooms
                    $grouped_rooms = array();
                    if ($group_exclude_field) {
                        $parts = explode(',', $group_exclude_field);
                        foreach ($parts as $part) {
                            $part = trim($part);
                            // Extract booking ID from G-{id}, #${id}, etc.
                            $grouped_booking_id = null;
                            if (strpos($part, 'G-') === 0) {
                                $grouped_booking_id = substr($part, 2);
                            } elseif (strpos($part, '#') === 0) {
                                $grouped_booking_id = substr($part, 1);
                            }

                            // Look up room number for this grouped booking
                            if ($grouped_booking_id && isset($hotel_bookings_by_id[$grouped_booking_id])) {
                                $grouped_hotel_booking = $hotel_bookings_by_id[$grouped_booking_id];
                                $grouped_room = $grouped_hotel_booking['site_name'] ?? '';
                                // Don't include the primary room in the grouped rooms list
                                if ($grouped_room && $grouped_booking_id !== $hotel_booking_id) {
                                    $grouped_rooms[] = $grouped_room;
                                }
                            }
                        }
                    }
                    $resos_booking['grouped_rooms'] = $grouped_rooms;

                    $enhanced_bookings[] = $resos_booking;
                }

                bma_log("BMA Restaurant: Enhanced " . count($enhanced_bookings) . " bookings with room numbers", 'debug');

                // Return enhanced bookings in format expected by client-side JavaScript
                return array(
                    'success' => true,
                    'bookings_by_date' => array(
                        $date => $enhanced_bookings
                    ),
                    'bookings' => $enhanced_bookings,
                    'date' => $date,
                    'booking_count' => count($enhanced_bookings)
                );
            }

            bma_log("BMA Staying: Fetching bookings for date = {$date}", 'debug');

            // Calculate adjacent dates
            $previous_date = date('Y-m-d', strtotime($date . ' -1 day'));
            $next_date = date('Y-m-d', strtotime($date . ' +1 day'));

            // Fetch each date individually for per-date cache reuse
            $searcher = new BMA_NewBook_Search();
            $previous_bookings = $searcher->fetch_hotel_bookings_for_date($previous_date, $force_refresh, $request_context);
            $current_bookings = $searcher->fetch_hotel_bookings_for_date($date, $force_refresh, $request_context);
            $next_bookings = $searcher->fetch_hotel_bookings_for_date($next_date, $force_refresh, $request_context);

            // PRE-CACHE: Pre-populate ResOS cache for target date to prevent duplicate API calls
            // (All bookings will call match_single_night for same target date)
            $matcher = new BMA_Matcher();
            bma_log("Staying: Pre-caching ResOS data for dates: $previous_date, $date, $next_date", 'info');
            $matcher->fetch_resos_bookings($date, $force_refresh);

            // Merge and deduplicate by booking_id
            $all_bookings = array();
            $booking_ids_seen = array();

            foreach (array_merge($previous_bookings, $current_bookings, $next_bookings) as $booking) {
                $booking_id = $booking['booking_id'] ?? null;
                if ($booking_id && !isset($booking_ids_seen[$booking_id])) {
                    $all_bookings[] = $booking;
                    $booking_ids_seen[$booking_id] = true;
                }
            }

            bma_log('BMA Staying: Fetched ' . count($all_bookings) . ' unique bookings across 3 dates (prev: ' . count($previous_bookings) . ', curr: ' . count($current_bookings) . ', next: ' . count($next_bookings) . ')', 'debug');

            if (empty($all_bookings)) {
                // Return empty success response
                $formatter = new BMA_Response_Formatter();
                return array(
                    'success' => true,
                    'html' => $formatter->format_staying_response(array(), $date),
                    'date' => $date,
                    'booking_count' => 0,
                    'critical_count' => 0,
                    'warning_count' => 0
                );
            }

            // Group bookings by room and date
            $bookings_by_room = array();
            foreach ($all_bookings as $booking) {
                $room = $booking['site_name'] ?? 'N/A';
                $arrival = substr($booking['booking_arrival'] ?? '', 0, 10);
                $departure = substr($booking['booking_departure'] ?? '', 0, 10);
                $booking_id = $booking['booking_id'];

                // Determine which dates this booking covers
                $arrival_dt = new DateTime($arrival);
                $departure_dt = new DateTime($departure);
                $target_dt = new DateTime($date);
                $previous_dt = new DateTime($previous_date);
                $next_dt = new DateTime($next_date);

                // Check if booking is staying on each date (arrival <= date < departure)
                if ($arrival_dt <= $previous_dt && $departure_dt > $previous_dt) {
                    $bookings_by_room[$room]['previous'] = $booking;
                }
                if ($arrival_dt <= $target_dt && $departure_dt > $target_dt) {
                    $bookings_by_room[$room]['current'] = $booking;
                }
                if ($arrival_dt <= $next_dt && $departure_dt > $next_dt) {
                    $bookings_by_room[$room]['next'] = $booking;
                }
            }

            // Process bookings staying on target date
            $processed_bookings = array();
            $departing_bookings = array(); // Separate array for bookings departing on target date
            $total_critical_count = 0;
            $total_warning_count = 0;

            // Matcher already created above for pre-caching, reuse for all bookings to share cache

            foreach ($bookings_by_room as $room => $dates) {
                // Check if room has a booking staying on target date
                $current_booking = $dates['current'] ?? null;

                // Also check if there's a booking departing on target date (in previous slot)
                // These are tracked separately for stats only, not displayed as cards
                $previous_booking_slot = $dates['previous'] ?? null;
                if ($previous_booking_slot) {
                    $prev_departure = substr($previous_booking_slot['booking_departure'] ?? '', 0, 10);
                    if ($prev_departure === $date) {
                        // This booking is departing today - add to departing array for stats
                        $departing_bookings[] = $previous_booking_slot;
                    }
                }

                if (!$current_booking) {
                    continue; // Not staying on target date
                }

                // Determine timeline indicators
                $timeline_data = array(
                    'previous_night_status' => null,
                    'next_night_status' => null,
                    'spans_from_previous' => false,
                    'spans_to_next' => false,
                    'previous_vacant' => false,
                    'next_vacant' => false,
                );

                // All bookings in main loop are staying bookings
                $previous_booking = $dates['previous'] ?? null;
                $next_booking = $dates['next'] ?? null;

                // Check previous night
                if ($previous_booking) {
                    if ($previous_booking['booking_id'] === $current_booking['booking_id']) {
                        // Same booking continuing
                        $timeline_data['spans_from_previous'] = true;
                    } else {
                        // Different booking
                        $timeline_data['previous_night_status'] = strtolower($previous_booking['booking_status'] ?? 'confirmed');
                    }
                } else {
                    // Room was vacant on previous night
                    $timeline_data['previous_vacant'] = true;
                }

                // Check next night
                if ($next_booking) {
                    if ($next_booking['booking_id'] === $current_booking['booking_id']) {
                        // Same booking continuing
                        $timeline_data['spans_to_next'] = true;
                    } else {
                        // Different booking
                        $timeline_data['next_night_status'] = strtolower($next_booking['booking_status'] ?? 'confirmed');
                    }
                } else {
                    // Room will be vacant on next night
                    $timeline_data['next_vacant'] = true;
                }

                $processed = $this->process_booking_for_staying($current_booking, $date, $force_refresh, $timeline_data, $matcher, $request_context);
                $processed_bookings[] = $processed;
                $total_critical_count += $processed['critical_count'];
                $total_warning_count += $processed['warning_count'];
            }

            // Get list of all sites/rooms
            $all_sites = $searcher->fetch_sites($request_context);

            // Create a map of occupied room names
            $occupied_rooms = array();
            foreach ($processed_bookings as $booking) {
                $room_name = $booking['site_name'] ?? '';
                if (!empty($room_name) && $room_name !== 'N/A') {
                    $occupied_rooms[] = $room_name;
                }
            }

            // Add vacant rooms to the list
            foreach ($all_sites as $site) {
                $site_name = $site['site_name'] ?? '';
                if (!empty($site_name) && !in_array($site_name, $occupied_rooms)) {
                    // Calculate timeline indicators for vacant room
                    $vacant_timeline = array(
                        'previous_night_status' => null,
                        'next_night_status' => null,
                        'spans_from_previous' => false,
                        'spans_to_next' => false,
                        'previous_vacant' => false,
                        'next_vacant' => false,
                    );

                    // Check if room was occupied on previous/next nights
                    if (isset($bookings_by_room[$site_name])) {
                        $room_dates = $bookings_by_room[$site_name];

                        // Check previous night
                        if (isset($room_dates['previous'])) {
                            $prev_booking = $room_dates['previous'];
                            $vacant_timeline['previous_night_status'] = strtolower($prev_booking['booking_status'] ?? 'confirmed');
                        } else {
                            // Room was also vacant on previous night
                            $vacant_timeline['spans_from_previous'] = true;
                            $vacant_timeline['previous_vacant'] = true;
                        }

                        // Check next night
                        if (isset($room_dates['next'])) {
                            $next_booking = $room_dates['next'];
                            $vacant_timeline['next_night_status'] = strtolower($next_booking['booking_status'] ?? 'confirmed');
                        } else {
                            // Room will also be vacant on next night
                            $vacant_timeline['spans_to_next'] = true;
                            $vacant_timeline['next_vacant'] = true;
                        }
                    } else {
                        // No bookings at all for this room in the 3-day window
                        $vacant_timeline['spans_from_previous'] = true;
                        $vacant_timeline['spans_to_next'] = true;
                        $vacant_timeline['previous_vacant'] = true;
                        $vacant_timeline['next_vacant'] = true;
                    }

                    // Add vacant room entry
                    $processed_bookings[] = array_merge(
                        array(
                            'is_vacant' => true,
                            'site_name' => $site_name,
                            'site_id' => $site['site_id'] ?? null
                        ),
                        $vacant_timeline
                    );
                }
            }

            // Sort by room number (site_name) using natural sort
            usort($processed_bookings, function($a, $b) {
                $room_a = $a['site_name'] ?? 'N/A';
                $room_b = $b['site_name'] ?? 'N/A';

                // Put N/A entries at the end
                if ($room_a === 'N/A' && $room_b !== 'N/A') return 1;
                if ($room_a !== 'N/A' && $room_b === 'N/A') return -1;

                // Natural sort for room numbers (handles "101", "102", "Suite A", etc.)
                return strnatcasecmp($room_a, $room_b);
            });

            // Format response
            $formatter = new BMA_Response_Formatter();
            return array(
                'success' => true,
                'html' => $formatter->format_staying_response($processed_bookings, $date, $departing_bookings),
                'date' => $date,
                'booking_count' => count($processed_bookings),
                'critical_count' => $total_critical_count,
                'warning_count' => $total_warning_count
            );

        } catch (Exception $e) {
            bma_log('BMA Staying Error: ' . $e->getMessage(), 'error');
            return new WP_Error(
                'staying_error',
                __('Error retrieving staying bookings: ' . $e->getMessage(), 'booking-match-api'),
                array('status' => 500)
            );
        }
    }

    /**
     * Process a booking for staying display
     */
    private function process_booking_for_staying($booking, $target_date, $force_refresh = false, $timeline_data = array(), $matcher = null, $request_context = null) {
        // Extract basic info
        $booking_id = $booking['booking_id'];
        $guest_name = $this->extract_guest_name($booking);
        $arrival_date = substr($booking['booking_arrival'] ?? '', 0, 10);
        $departure_date = substr($booking['booking_departure'] ?? '', 0, 10);
        $nights = $this->calculate_nights($arrival_date, $departure_date);
        $status = $booking['booking_status'] ?? 'unknown';
        $room_number = $booking['site_name'] ?? 'N/A';

        // Try multiple possible field names for group ID
        $group_id = $booking['booking_group_id']
                 ?? $booking['group_id']
                 ?? $booking['bookings_group_id']
                 ?? null;

        // Debug log to see if group ID is found
        if ($group_id) {
            bma_log("BMA Staying: Found group_id = {$group_id} for booking {$booking_id}", 'debug');
        } else {
            // Log available fields to help debug
            bma_log("BMA Staying: No group_id found for booking {$booking_id}. Available fields: " . implode(', ', array_keys($booking)), 'debug');
        }

        // Calculate which night this is
        $arrival = new DateTime($arrival_date);
        $target = new DateTime($target_date);
        $current_night = $arrival->diff($target)->days + 1;

        // Extract occupants
        $occupants = $this->extract_occupants($booking);

        // Extract tariff types
        $tariffs = $this->extract_tariffs($booking);

        // Match with restaurant for this specific date - reuse matcher if provided, otherwise create new one
        if ($matcher === null) {
            $matcher = new BMA_Matcher();
        }

        // Check if has package using matcher's method
        $has_package = $matcher->check_has_package($booking, $target_date);

        // Use reflection to access private match_single_night method
        $reflection = new ReflectionClass($matcher);
        $method = $reflection->getMethod('match_single_night');
        $method->setAccessible(true);
        $night_result = $method->invoke($matcher, $booking, $target_date, $force_refresh, $request_context);

        $matches = $night_result['resos_matches'] ?? array();

        // Determine booking source
        $source_detector = new BMA_Booking_Source();
        $booking_source = $source_detector->determine_source($booking);

        // Calculate issue counts
        $critical_count = 0;
        $warning_count = 0;

        if ($has_package && empty($matches)) {
            $critical_count++;
        } elseif (!empty($matches)) {
            foreach ($matches as $match) {
                if (!($match['match_info']['is_primary'] ?? false)) {
                    $warning_count++;
                }
            }
        }

        return array(
            'booking_id' => $booking_id,
            'guest_name' => $guest_name,
            'site_name' => $room_number,
            'arrival_date' => $arrival_date,
            'departure_date' => $departure_date,
            'nights' => $nights,
            'current_night' => $current_night,
            'status' => $status,
            'group_id' => $group_id,
            'booking_source' => $booking_source,
            'occupants' => $occupants,
            'tariffs' => $tariffs,
            'has_package' => $has_package,
            'resos_matches' => $matches,
            'critical_count' => $critical_count,
            'warning_count' => $warning_count,
            'custom_fields' => $booking['custom_fields'] ?? array(),
            // Timeline indicators for Gantt-style visualization
            'previous_night_status' => $timeline_data['previous_night_status'] ?? null,
            'next_night_status' => $timeline_data['next_night_status'] ?? null,
            'spans_from_previous' => $timeline_data['spans_from_previous'] ?? false,
            'spans_to_next' => $timeline_data['spans_to_next'] ?? false,
            'previous_vacant' => $timeline_data['previous_vacant'] ?? false,
            'next_vacant' => $timeline_data['next_vacant'] ?? false,
        );
    }

    /**
     * Get comparison data between hotel and Resos booking
     *
     * @param WP_REST_Request $request Full request data
     * @return WP_REST_Response|WP_Error Response object
     */
    public function get_comparison($request) {
        try {
            // Capture request context for comprehensive logging
            $request_context = bma_get_request_context($request);

            $booking_id = $request->get_param('booking_id');
            $date = $request->get_param('date');
            $resos_booking_id = $request->get_param('resos_booking_id');
            $force_refresh = $request->get_param('force_refresh') ?: false;
            $context = $request->get_param('context') ?: 'json';

            // Fetch hotel booking
            $searcher = new BMA_NewBook_Search();
            $hotel_booking = $searcher->get_booking_by_id($booking_id, $force_refresh, $request_context);

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
                $night_match = $this->get_night_match_from_all($hotel_booking, $date, $force_refresh, $request_context);

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

            // If context is chrome-sidepanel, return HTML
            if ($context === 'chrome-sidepanel') {
                $html = $this->build_comparison_html(
                    $comparison_data,
                    $date,
                    $resos_booking['_id'] ?? $resos_booking['id'] ?? '',
                    $hotel_booking['booking_id'] ?? $booking_id,
                    $hotel_booking['guest_name'] ?? '',
                    $resos_booking
                );

                $response = array(
                    'success' => true,
                    'html' => $html
                );

                return rest_ensure_response($response);
            }

            // Default: return JSON data (backward compatible)
            $response = array(
                'success' => true,
                'booking_id' => $booking_id,
                'date' => $date,
                'resos_booking_id' => $resos_booking['_id'] ?? $resos_booking['id'] ?? '',
                'comparison' => $comparison_data
            );

            return rest_ensure_response($response);

        } catch (Exception $e) {
            bma_log('BMA Comparison Error: ' . $e->getMessage(), 'error');
            return new WP_Error(
                'comparison_error',
                __('Error generating comparison', 'booking-match-api'),
                array('status' => 500)
            );
        }
    }

    /**
     * Build comparison HTML for chrome-sidepanel context
     */
    private function build_comparison_html($comparison_data, $date, $resos_booking_id, $hotel_booking_id, $guest_name, $resos_booking = array()) {
        $hotel = $comparison_data['hotel'] ?? array();
        $resos = $comparison_data['resos'] ?? array();
        $matches = $comparison_data['matches'] ?? array();
        $suggested_updates = $comparison_data['suggested_updates'] ?? array();

        // Extract GROUP/EXCLUDE field from custom fields
        $group_exclude_field = '';
        if (!empty($resos_booking['customFields'])) {
            foreach ($resos_booking['customFields'] as $field) {
                if (isset($field['name']) && $field['name'] === 'GROUP/EXCLUDE') {
                    $group_exclude_field = $field['value'] ?? '';
                    break;
                }
            }
        }

        // Determine if confirmed and matched elsewhere
        $is_confirmed = !empty($hotel['is_primary_match']);
        $is_matched_elsewhere = !empty($hotel['matched_elsewhere']);

        // Show suggested updates for all matches
        $suggestions = $suggested_updates;
        $has_suggestions = !empty($suggestions);

        $container_id = 'comparison-' . $date . '-' . $resos_booking_id;

        ob_start();
        ?>
        <!-- TEMPLATE VERSION 1.4.0-PHP -->
        <div class="comparison-row-content">
            <div class="comparison-table-wrapper">
                <div class="comparison-header">Match Comparison</div>
                <table class="comparison-table">
                    <thead><tr>
                        <th>Field</th>
                        <th>Newbook</th>
                        <th>ResOS</th>
                    </tr></thead>
                    <tbody>
                        <?php echo $this->build_comparison_row('Name', 'name', $hotel['name'] ?? '', $resos['name'] ?? '', $matches['name'] ?? false, $suggestions['name'] ?? null); ?>
                        <?php echo $this->build_comparison_row('Phone', 'phone', $hotel['phone'] ?? '', $resos['phone'] ?? '', $matches['phone'] ?? false, $suggestions['phone'] ?? null); ?>
                        <?php echo $this->build_comparison_row('Email', 'email', $hotel['email'] ?? '', $resos['email'] ?? '', $matches['email'] ?? false, $suggestions['email'] ?? null); ?>
                        <?php echo $this->build_comparison_row('People', 'people', $hotel['people'] ?? '', $resos['people'] ?? '', $matches['people'] ?? false, $suggestions['people'] ?? null); ?>
                        <?php echo $this->build_comparison_row('Package', 'dbb', $hotel['rate_type'] ?? '', $resos['dbb'] ?? '', $matches['dbb'] ?? false, $suggestions['dbb'] ?? null); ?>
                        <?php echo $this->build_comparison_row('#', 'booking_ref', $hotel['booking_id'] ?? '', $resos['booking_ref'] ?? '', $matches['booking_ref'] ?? false, $suggestions['booking_ref'] ?? null); ?>
                        <?php
                        $hotel_guest_value = !empty($hotel['is_hotel_guest']) ? 'Yes' : '-';
                        echo $this->build_comparison_row('Resident', 'hotel_guest', $hotel_guest_value, $resos['hotel_guest'] ?? '', false, $suggestions['hotel_guest'] ?? null);
                        ?>
                        <?php
                        $status = $resos['status'] ?? 'request';
                        $status_icon = $this->get_status_icon($status);
                        $status_display = '<span class="material-symbols-outlined">' . $status_icon . '</span> ' . ucfirst($status);
                        echo $this->build_comparison_row('Status', 'status', $hotel['status'] ?? '', $status_display, false, $suggestions['status'] ?? null, true);
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="comparison-actions-buttons">
                <!-- 1. Close button -->
                <button class="btn-close-comparison" data-action="close-comparison" data-container-id="<?php echo esc_attr($container_id); ?>">
                    <span class="material-symbols-outlined">close</span> Close
                </button>

                <!-- 2. Manage Group button (ALWAYS SHOWN for matched bookings) -->
                <?php if (!empty($resos_booking_id)): ?>
                    <button class="btn-manage-group" data-action="manage-group"
                            data-resos-booking-id="<?php echo esc_attr($resos_booking_id); ?>"
                            data-hotel-booking-id="<?php echo esc_attr($hotel_booking_id); ?>"
                            data-date="<?php echo esc_attr($date); ?>"
                            data-resos-time="<?php echo esc_attr($resos['time'] ?? ''); ?>"
                            data-resos-guest="<?php echo esc_attr($resos['name'] ?? ''); ?>"
                            data-resos-people="<?php echo esc_attr($resos['people'] ?? '0'); ?>"
                            data-resos-booking-ref="<?php echo esc_attr($resos['booking_ref'] ?? ''); ?>"
                            data-group-exclude="<?php echo esc_attr($group_exclude_field); ?>"
                            title="Manage Group">
                        <span class="material-symbols-outlined">groups</span>
                    </button>
                <?php endif; ?>

                <!-- 3. Exclude Match button -->
                <?php if (!$is_confirmed && !$is_matched_elsewhere && !empty($resos_booking_id) && !empty($hotel_booking_id)): ?>
                    <button class="btn-exclude-match" data-action="exclude-match"
                            data-resos-booking-id="<?php echo esc_attr($resos_booking_id); ?>"
                            data-hotel-booking-id="<?php echo esc_attr($hotel_booking_id); ?>"
                            data-guest-name="<?php echo esc_attr($guest_name); ?>"
                            title="Exclude Match">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                <?php endif; ?>

                <!-- 4. Update button -->
                <?php if ($has_suggestions): ?>
                    <?php
                    $button_label = $is_confirmed ? 'Update Selected' : 'Update Selected & Match';
                    $button_class = $is_confirmed ? 'btn-confirm-match btn-update-confirmed' : 'btn-confirm-match';
                    ?>
                    <button class="<?php echo esc_attr($button_class); ?>" data-action="submit-suggestions"
                            data-date="<?php echo esc_attr($date); ?>"
                            data-resos-booking-id="<?php echo esc_attr($resos['id'] ?? ''); ?>"
                            data-hotel-booking-id="<?php echo esc_attr($hotel_booking_id); ?>"
                            data-is-confirmed="<?php echo $is_confirmed ? 'true' : 'false'; ?>">
                        <span class="material-symbols-outlined">check_circle</span> <?php echo esc_html($button_label); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Build a single comparison table row
     */
    private function build_comparison_row($label, $field, $hotel_value, $resos_value, $is_match, $suggestion_value, $is_html = false) {
        $match_class = $is_match ? ' class="match-row"' : '';
        $has_suggestion = $suggestion_value !== null && $suggestion_value !== '';

        // Main comparison row
        $html = '<tr' . $match_class . '>';
        $html .= '<td><strong>' . esc_html($label) . '</strong></td>';
        $html .= '<td>' . ($is_html ? $hotel_value : esc_html($hotel_value)) . '</td>';
        $html .= '<td class="resos-value" data-field="' . esc_attr($field) . '">' . ($is_html ? $resos_value : esc_html($resos_value)) . '</td>';
        $html .= '</tr>';

        // If there's a suggestion, add a suggestion row below
        if ($has_suggestion) {
            $is_checked_by_default = ($field !== 'people'); // Uncheck "people" by default, check all others
            $checked_attr = $is_checked_by_default ? ' checked' : '';

            $suggestion_display = $suggestion_value === '' ? '<em style="color: #999;">(Remove)</em>' : ($is_html ? $suggestion_value : esc_html($suggestion_value));

            $html .= '<tr class="suggestion-row">';
            $html .= '<td colspan="3">';
            $html .= '<div class="suggestion-content">';
            $html .= '<label>';
            $html .= '<input type="checkbox" class="suggestion-checkbox" name="suggestion_' . esc_attr($field) . '" data-field="' . esc_attr($field) . '" value="' . esc_attr($suggestion_value) . '"' . $checked_attr . '> ';
            $html .= '<span class="suggestion-text" data-field="' . esc_attr($field) . '">Update to: ' . $suggestion_display . '</span>';
            $html .= '</label>';
            $html .= '</div>';
            $html .= '</td>';
            $html .= '</tr>';
        }

        return $html;
    }

    /**
     * Get status icon for a given status
     */
    private function get_status_icon($status) {
        $icons = array(
            'request' => 'pending',
            'confirmed' => 'check_circle',
            'declined' => 'thumb_down',
            'waitlist' => 'pending_actions',
            'arrived' => 'directions_walk',
            'seated' => 'airline_seat_recline_normal',
            'left' => 'flight_takeoff',
            'no_show' => 'block',
            'no-show' => 'block',
            'canceled' => 'cancel',
            'cancelled' => 'cancel',
        );

        return $icons[$status] ?? 'help';
    }

    /**
     * Helper method to get night match data
     */
    private function get_night_match_from_all($hotel_booking, $date, $force_refresh = false, $request_context = null) {
        $matcher = new BMA_Matcher();
        $all_matches = $matcher->match_booking_all_nights($hotel_booking, $force_refresh, $request_context);

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
            'force_refresh' => array(
                'description' => __('Force refresh of Resos cache', 'booking-match-api'),
                'type' => 'boolean',
                'required' => false,
                'default' => false,
            ),
            'context' => array(
                'description' => __('Response format context (json, chrome-sidepanel, etc.)', 'booking-match-api'),
                'type' => 'string',
                'required' => false,
                'default' => 'json',
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

            // Clear all Resos bookings caches since we don't know the booking's date
            // Updates can affect matching across dates
            $matcher = new BMA_Matcher();
            $matcher->clear_all_resos_caches();

            return rest_ensure_response($result);

        } catch (Exception $e) {
            bma_log('BMA Update Booking Error: ' . $e->getMessage(), 'error');
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

            // Clear all Resos bookings caches since exclusion affects matching
            $matcher = new BMA_Matcher();
            $matcher->clear_all_resos_caches();

            return rest_ensure_response($result);

        } catch (Exception $e) {
            bma_log('BMA Exclude Match Error: ' . $e->getMessage(), 'error');
            return new WP_Error(
                'exclude_error',
                __('Error excluding match', 'booking-match-api'),
                array('status' => 500)
            );
        }
    }

    /**
     * Update group relationships for a Resos booking
     *
     * @param WP_REST_Request $request Full request data
     * @return WP_REST_Response|WP_Error Response object
     */
    public function update_group($request) {
        try {
            $resos_booking_id = $request->get_param('resos_booking_id');
            $lead_booking_id = $request->get_param('lead_booking_id');
            $group_id = $request->get_param('group_id');
            $individual_ids = $request->get_param('individual_ids') ?: array();
            $exclude_ids = $request->get_param('exclude_ids') ?: array();

            $actions = new BMA_Booking_Actions();

            // Prepare updates array
            $updates = array();

            // Update lead booking ID (Booking # field)
            if (!empty($lead_booking_id)) {
                $updates['booking_ref'] = $lead_booking_id;
            }

            // Build GROUP/EXCLUDE field value
            $groups = !empty($group_id) ? array($group_id) : array();
            $group_exclude_value = $actions->build_group_exclude_value($groups, $individual_ids, $exclude_ids);

            if (!empty($group_exclude_value)) {
                $updates['group_exclude'] = $group_exclude_value;
            } else {
                // Empty value means clear the field
                $updates['group_exclude'] = '';
            }

            // Update the booking
            $result = $actions->update_resos_booking($resos_booking_id, $updates);

            if (!$result['success']) {
                return new WP_Error(
                    'update_group_failed',
                    $result['message'],
                    array('status' => 400)
                );
            }

            // Clear all Resos bookings caches since group changes affect matching
            $matcher = new BMA_Matcher();
            $matcher->clear_all_resos_caches();

            return rest_ensure_response(array(
                'success' => true,
                'message' => 'Group updated successfully',
                'resos_booking_id' => $resos_booking_id,
                'lead_booking_id' => $lead_booking_id,
                'group_id' => $group_id,
                'individual_ids' => $individual_ids,
                'exclude_ids' => $exclude_ids,
                'group_exclude_field' => $group_exclude_value
            ));

        } catch (Exception $e) {
            bma_log('BMA Update Group Error: ' . $e->getMessage(), 'error');
            return new WP_Error(
                'update_group_error',
                __('Error updating group', 'booking-match-api'),
                array('status' => 500)
            );
        }
    }

    /**
     * Get NewBook bookings for a specific date (for group management modal)
     *
     * @param WP_REST_Request $request Full request data
     * @return WP_REST_Response|WP_Error Response object
     */
    public function get_bookings_for_date($request) {
        try {
            // Capture request context for comprehensive logging
            $request_context = bma_get_request_context($request);

            $date = $request->get_param('date');
            $exclude_booking_id = $request->get_param('exclude_booking_id');

            $search = new BMA_NewBook_Search();

            // Fetch all hotel bookings for this date
            $bookings = $search->fetch_hotel_bookings_for_date($date, false, $request_context);

            if (empty($bookings)) {
                return rest_ensure_response(array(
                    'success' => true,
                    'date' => $date,
                    'bookings' => array(),
                    'groups' => array()
                ));
            }

            // Filter out excluded booking if specified
            if (!empty($exclude_booking_id)) {
                $bookings = array_filter($bookings, function($booking) use ($exclude_booking_id) {
                    return strval($booking['booking_id']) !== strval($exclude_booking_id);
                });
            }

            // Format bookings for response
            $formatted_bookings = array();
            $groups = array();

            foreach ($bookings as $booking) {
                $booking_id = $booking['booking_id'] ?? '';
                $bookings_group_id = $booking['bookings_group_id'] ?? '';
                $guest_name = '';

                // Extract guest name
                if (!empty($booking['guests']) && is_array($booking['guests'])) {
                    foreach ($booking['guests'] as $guest) {
                        if (isset($guest['primary_client']) && $guest['primary_client'] === '1') {
                            $guest_name = trim(($guest['firstname'] ?? '') . ' ' . ($guest['lastname'] ?? ''));
                            break;
                        }
                    }
                }

                $formatted_booking = array(
                    'booking_id' => $booking_id,
                    'bookings_group_id' => $bookings_group_id,
                    'guest_name' => $guest_name,
                    'site_name' => $booking['site_name'] ?? '',
                    'arrival' => substr($booking['booking_arrival'] ?? '', 0, 10),
                    'departure' => substr($booking['booking_departure'] ?? '', 0, 10),
                    'adults' => $booking['booking_adults'] ?? 0,
                    'children' => $booking['booking_children'] ?? 0
                );

                $formatted_bookings[] = $formatted_booking;

                // Group bookings by bookings_group_id
                if (!empty($bookings_group_id)) {
                    if (!isset($groups[$bookings_group_id])) {
                        $groups[$bookings_group_id] = array();
                    }
                    $groups[$bookings_group_id][] = $booking_id;
                }
            }

            // Sort by site_name (room number)
            usort($formatted_bookings, function($a, $b) {
                return strcmp($a['site_name'], $b['site_name']);
            });

            return rest_ensure_response(array(
                'success' => true,
                'date' => $date,
                'bookings' => $formatted_bookings,
                'groups' => $groups
            ));

        } catch (Exception $e) {
            bma_log('BMA Get Bookings for Date Error: ' . $e->getMessage(), 'error');
            return new WP_Error(
                'get_bookings_error',
                __('Error fetching bookings for date', 'booking-match-api'),
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
     * Get update group endpoint parameters
     */
    protected function get_update_group_params() {
        return array(
            'resos_booking_id' => array(
                'description' => __('Resos booking ID to update', 'booking-match-api'),
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'lead_booking_id' => array(
                'description' => __('Lead hotel booking ID (for Booking # field)', 'booking-match-api'),
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'group_id' => array(
                'description' => __('NewBook group ID for G# format', 'booking-match-api'),
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'individual_ids' => array(
                'description' => __('Array of individual booking IDs for # format', 'booking-match-api'),
                'type' => 'array',
                'required' => false,
            ),
            'exclude_ids' => array(
                'description' => __('Array of excluded booking IDs for NOT-# format', 'booking-match-api'),
                'type' => 'array',
                'required' => false,
            ),
        );
    }

    /**
     * Get bookings for date endpoint parameters
     */
    protected function get_bookings_for_date_params() {
        return array(
            'date' => array(
                'description' => __('Date in YYYY-MM-DD format', 'booking-match-api'),
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function($param, $request, $key) {
                    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
                },
            ),
            'exclude_booking_id' => array(
                'description' => __('Booking ID to exclude from results', 'booking-match-api'),
                'type' => 'string',
                'required' => false,
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

                // Group data
                'group_members' => $request->get_param('group_members'),
                'lead_booking_id' => $request->get_param('lead_booking_id'),
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

            // Clear cache for the created booking's date to ensure fresh data on next fetch
            if (!empty($booking_data['date'])) {
                $matcher = new BMA_Matcher();
                $matcher->clear_resos_cache_for_date($booking_data['date']);
            }

            return rest_ensure_response($result);

        } catch (Exception $e) {
            bma_log('BMA Create Booking Error: ' . $e->getMessage(), 'error');
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
            'group_members' => array(
                'description' => __('Group member booking IDs (G- prefixed, comma-separated)', 'booking-match-api'),
                'type' => 'string',
                'required' => false,
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'lead_booking_id' => array(
                'description' => __('Lead booking ID for the group', 'booking-match-api'),
                'type' => 'string',
                'required' => false,
                'default' => '',
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

        bma_log("BMA Opening Hours: date = " . ($date ? $date : 'all') . ", context = " . ($context ? $context : 'json'), 'debug');

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

        bma_log("BMA Available Times: date = $date, people = $people, context = " . ($context ? $context : 'json'), 'debug');

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

        bma_log("BMA Dietary Choices: context = " . ($context ? $context : 'json'), 'debug');

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
     * Get all bookings for a specific date endpoint
     *
     * @param WP_REST_Request $request Full request object
     * @return array Response data
     */
    public function get_all_bookings_for_date($request) {
        $date = $request->get_param('date');

        if (empty($date)) {
            return new WP_Error('missing_date', 'Date parameter is required', array('status' => 400));
        }

        bma_log("BMA All Bookings: Fetching all bookings for date = $date", 'debug');

        $matcher = new BMA_Matcher();
        $bookings = $matcher->fetch_all_bookings_for_gantt($date);

        // Get special events for this date
        $actions = new BMA_Booking_Actions();
        $special_events = $actions->fetch_special_events($date);

        // Check if online booking is available for this date
        $next_day = date('Y-m-d', strtotime($date . ' +1 day'));
        $online_booking_available = $actions->check_online_booking_available($date, $next_day);

        return array(
            'success' => true,
            'date' => $date,
            'bookings' => $bookings,
            'count' => count($bookings),
            'specialEvents' => $special_events,
            'onlineBookingAvailable' => $online_booking_available
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

        bma_log("BMA Special Events: date = $date, context = " . ($context ? $context : 'json'), 'debug');

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
        // Generate time slots from opening hours, grey out based on available times AND special events
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

                    // Check if time is in available times from API
                    $is_available = isset($available_set[$time_str]);

                    // Check if time is restricted by special events (closures/limitations)
                    $restriction_reason = $this->check_time_restriction($time_str, $current_time, $special_events, $period_name);
                    $is_restricted = $restriction_reason !== null;

                    // Mark as unavailable if not in available set OR if restricted by special event
                    $btn_class = 'time-slot-btn';
                    $tooltip = '';

                    if (!$is_available || $is_restricted) {
                        $btn_class .= ' time-slot-unavailable';

                        // Tooltip shows restriction reason (special event) OR "Fully booked"
                        if ($is_restricted) {
                            $tooltip = ' data-restriction="' . esc_attr($restriction_reason) . '"';
                        } else {
                            $tooltip = ' data-restriction="No availability"';
                        }
                    }

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
     * Check if a time is restricted by special events
     *
     * @param string $time_str Time in H:MM or HH:MM format
     * @param int $time_value Time in HHMM format (e.g., 1830)
     * @param array $special_events Array of special event objects
     * @param string $period_name Fallback name if event has no name
     * @return string|null Restriction reason or null if not restricted
     */
    private function check_time_restriction($time_str, $time_value, $special_events, $period_name = '') {
        if (empty($special_events) || !is_array($special_events)) {
            return null; // No restrictions
        }

        foreach ($special_events as $event) {
            // Skip events that are OPEN (isOpen = true) - these are special open hours, not restrictions
            if (isset($event['isOpen']) && $event['isOpen'] === true) {
                continue; // Not a restriction
            }

            // Check if this is a full-day closure (no open/close times)
            if (empty($event['open']) && empty($event['close'])) {
                return isset($event['name']) && !empty($event['name']) ? $event['name'] : 'Service unavailable';
            }

            // Check if time falls within restricted period
            if (isset($event['open']) && isset($event['close'])) {
                if ($time_value >= $event['open'] && $time_value < $event['close']) {
                    return isset($event['name']) && !empty($event['name']) ? $event['name'] : ($period_name ? $period_name . ' closed' : 'Service unavailable');
                }
            }
        }

        return null; // Not restricted
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

    /**
     * Extract all dates (nights) from a booking
     *
     * @param array $booking Booking array with booking_arrival and booking_departure
     * @return array Array of dates in Y-m-d format
     */
    private function extract_booking_dates($booking) {
        $arrival = substr($booking['booking_arrival'] ?? '', 0, 10);
        $departure = substr($booking['booking_departure'] ?? '', 0, 10);

        if (empty($arrival) || empty($departure)) {
            return array();
        }

        $dates = array();
        $current = new DateTime($arrival);
        $end = new DateTime($departure);

        while ($current < $end) {
            $dates[] = $current->format('Y-m-d');
            $current->modify('+1 day');
        }

        return $dates;
    }
}
