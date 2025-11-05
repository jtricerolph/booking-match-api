<?php
/**
 * Authenticator Class
 *
 * Handles authentication for the Booking Match API
 * Currently relies on WordPress REST API authentication
 * Future: Can be extended to support API keys, JWT tokens, etc.
 *
 * @package BookingMatchAPI
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class BMA_Authenticator {

    /**
     * Check if the current request is authenticated
     *
     * @return bool True if authenticated, false otherwise
     */
    public function is_authenticated() {
        // Currently relies on WordPress authentication
        // The REST controller uses permission_callback to check is_user_logged_in()
        return is_user_logged_in();
    }

    /**
     * Validate API key (stub for future implementation)
     *
     * @param string $api_key API key to validate
     * @return bool True if valid, false otherwise
     */
    public function validate_api_key($api_key) {
        // Stub method for future API key authentication
        // Can be implemented to check against stored API keys in wp_options
        return false;
    }

    /**
     * Validate JWT token (stub for future implementation)
     *
     * @param string $token JWT token to validate
     * @return bool|array False if invalid, user data array if valid
     */
    public function validate_jwt_token($token) {
        // Stub method for future JWT authentication
        // Can be implemented to decode and validate JWT tokens
        return false;
    }

    /**
     * Get current user ID from authentication
     *
     * @return int User ID, or 0 if not authenticated
     */
    public function get_current_user_id() {
        if ($this->is_authenticated()) {
            return get_current_user_id();
        }
        return 0;
    }

    /**
     * Check if user has required capability
     *
     * @param string $capability Required capability
     * @return bool True if user has capability, false otherwise
     */
    public function user_can($capability = 'read') {
        if (!$this->is_authenticated()) {
            return false;
        }
        return current_user_can($capability);
    }
}
