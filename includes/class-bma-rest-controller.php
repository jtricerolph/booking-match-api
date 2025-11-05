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
        // Require WordPress authentication (supports Application Passwords)
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_forbidden',
                __('Authentication required. Please provide valid WordPress credentials.', 'booking-match-api'),
                array('status' => 401)
            );
        }

        // User must have at least 'read' capability
        if (!current_user_can('read')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access this resource.', 'booking-match-api'),
                array('status' => 403)
            );
        }

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
                'description' => __('Response format context (json, chrome-extension, etc.)', 'booking-match-api'),
                'type' => 'string',
                'required' => false,
                'default' => 'json',
                'enum' => array('json', 'chrome-extension'),
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
}
