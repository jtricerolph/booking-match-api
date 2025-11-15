<?php
/**
 * NewBook Search Class
 *
 * Handles searching NewBook API for bookings by various criteria
 *
 * @package BookingMatchAPI
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class BMA_NewBook_Search {

    private $api_base_url = 'https://api.newbook.cloud/rest/';

    /**
     * Track when stale cache is used
     */
    private $stale_cache_used = array();

    /**
     * Get single booking by ID
     *
     * @param int $booking_id NewBook booking ID
     * @param bool $force_refresh If true, bypass and clear cache
     * @return array|false Booking data or false on failure
     */
    public function get_booking_by_id($booking_id, $force_refresh = false) {
        $data = array(
            'booking_id' => intval($booking_id)
        );

        $response = $this->call_api('bookings_get', $data, $force_refresh);

        if (!$response || !isset($response['data'])) {
            return false;
        }

        return $response['data'];
    }

    /**
     * Search for bookings by guest details
     *
     * @param array $criteria Search criteria
     * @return array|WP_Error Array with 'bookings' and 'search_method' or WP_Error
     */
    public function search_bookings($criteria) {
        // Determine search strategy based on criteria
        $email = $criteria['email'] ?? '';
        $phone = $criteria['phone'] ?? '';
        $agent_ref = $criteria['agent_reference'] ?? '';
        $guest_name = $criteria['guest_name'] ?? '';

        // Confident search: Agent reference
        if (!empty($agent_ref)) {
            return $this->search_by_agent_reference($agent_ref);
        }

        // Confident search: Email (alone or with name)
        if (!empty($email)) {
            return $this->search_by_email($email, $guest_name);
        }

        // Confident search: Phone (alone or with name)
        if (!empty($phone)) {
            return $this->search_by_phone($phone, $guest_name);
        }

        // Name + other field already validated in controller

        return new WP_Error(
            'invalid_search',
            __('Invalid search criteria', 'booking-match-api'),
            array('status' => 400)
        );
    }

    /**
     * Search by agent reference
     */
    private function search_by_agent_reference($agent_ref) {
        // Fetch recent bookings (±7 days from today)
        $bookings = $this->fetch_recent_bookings();

        if (empty($bookings)) {
            return array('bookings' => array(), 'search_method' => 'agent_reference');
        }

        // Filter by agent reference
        $matches = array();
        foreach ($bookings as $booking) {
            $booking_ref = $booking['booking_reference_id'] ?? '';
            if (!empty($booking_ref) && stripos($booking_ref, $agent_ref) !== false) {
                $matches[] = array(
                    'booking' => $booking,
                    'confidence_score' => 100,
                    'match_reason' => 'Agent reference match'
                );
            }
        }

        // Sort by confidence
        usort($matches, function($a, $b) {
            return $b['confidence_score'] - $a['confidence_score'];
        });

        return array(
            'bookings' => array_column($matches, 'booking'),
            'search_method' => 'agent_reference',
            'match_details' => $matches
        );
    }

    /**
     * Search by email
     */
    private function search_by_email($email, $guest_name = '') {
        $bookings = $this->fetch_recent_bookings();

        if (empty($bookings)) {
            return array('bookings' => array(), 'search_method' => 'email');
        }

        $email_normalized = $this->normalize_for_matching($email);
        $matches = array();

        foreach ($bookings as $booking) {
            $score = 0;
            $reasons = array();

            // Check email in guests
            if (isset($booking['guests']) && is_array($booking['guests'])) {
                foreach ($booking['guests'] as $guest) {
                    if (isset($guest['contact_details']) && is_array($guest['contact_details'])) {
                        foreach ($guest['contact_details'] as $contact) {
                            if (($contact['type'] ?? '') === 'email') {
                                $guest_email = $this->normalize_for_matching($contact['content'] ?? '');
                                if ($guest_email === $email_normalized) {
                                    $score += 100;
                                    $reasons[] = 'Email exact match';
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }

            // If name provided, check for name match too
            if (!empty($guest_name) && $score > 0) {
                $surname = $this->extract_surname($guest_name);
                $guest_surname = $this->get_primary_guest_surname($booking);

                if ($this->normalize_for_matching($surname) === $this->normalize_for_matching($guest_surname)) {
                    $score += 50;
                    $reasons[] = 'Surname also matches';
                }
            }

            if ($score > 0) {
                $matches[] = array(
                    'booking' => $booking,
                    'confidence_score' => $score,
                    'match_reason' => implode(', ', $reasons)
                );
            }
        }

        // Sort by confidence
        usort($matches, function($a, $b) {
            return $b['confidence_score'] - $a['confidence_score'];
        });

        return array(
            'bookings' => array_column($matches, 'booking'),
            'search_method' => !empty($guest_name) ? 'email_and_name' : 'email',
            'match_details' => $matches
        );
    }

    /**
     * Search by phone
     */
    private function search_by_phone($phone, $guest_name = '') {
        $bookings = $this->fetch_recent_bookings();

        if (empty($bookings)) {
            return array('bookings' => array(), 'search_method' => 'phone');
        }

        $phone_normalized = $this->normalize_phone_for_matching($phone);
        $matches = array();

        foreach ($bookings as $booking) {
            $score = 0;
            $reasons = array();

            // Check phone in guests
            if (isset($booking['guests']) && is_array($booking['guests'])) {
                foreach ($booking['guests'] as $guest) {
                    if (isset($guest['contact_details']) && is_array($guest['contact_details'])) {
                        foreach ($guest['contact_details'] as $contact) {
                            if (($contact['type'] ?? '') === 'phone') {
                                $guest_phone = $this->normalize_phone_for_matching($contact['content'] ?? '');
                                // Match last 8 digits
                                if (strlen($guest_phone) >= 8 && strlen($phone_normalized) >= 8) {
                                    if (substr($guest_phone, -8) === substr($phone_normalized, -8)) {
                                        $score += 90;
                                        $reasons[] = 'Phone match (last 8 digits)';
                                        break 2;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // If name provided, check for name match too
            if (!empty($guest_name) && $score > 0) {
                $surname = $this->extract_surname($guest_name);
                $guest_surname = $this->get_primary_guest_surname($booking);

                if ($this->normalize_for_matching($surname) === $this->normalize_for_matching($guest_surname)) {
                    $score += 50;
                    $reasons[] = 'Surname also matches';
                }
            }

            if ($score > 0) {
                $matches[] = array(
                    'booking' => $booking,
                    'confidence_score' => $score,
                    'match_reason' => implode(', ', $reasons)
                );
            }
        }

        // Sort by confidence
        usort($matches, function($a, $b) {
            return $b['confidence_score'] - $a['confidence_score'];
        });

        return array(
            'bookings' => array_column($matches, 'booking'),
            'search_method' => !empty($guest_name) ? 'phone_and_name' : 'phone',
            'match_details' => $matches
        );
    }

    /**
     * Fetch bookings from recent date range
     *
     * @param bool $force_refresh If true, bypass and clear cache
     * @return array Array of bookings
     */
    private function fetch_recent_bookings($force_refresh = false) {
        // Fetch bookings ±7 days from today
        $today = date('Y-m-d');
        $from = date('Y-m-d', strtotime('-7 days'));
        $to = date('Y-m-d', strtotime('+7 days'));

        $period_from = $from . ' 00:00:00';
        $period_to = $to . ' 23:59:59';

        $data = array(
            'period_from' => $period_from,
            'period_to' => $period_to,
            'list_type' => 'staying'
        );

        $response = $this->call_api('bookings_list', $data, $force_refresh);

        if (!$response || !isset($response['data'])) {
            return array();
        }

        return $response['data'];
    }

    /**
     * Fetch recently placed bookings (by booking creation date)
     *
     * @param int $limit Maximum number of bookings to return
     * @param int $hours_back How many hours back to search (default 72)
     * @param bool $force_refresh If true, bypass and clear cache
     * @return array Array of bookings sorted by booking_id descending
     */
    public function fetch_recent_placed_bookings($limit = 5, $hours_back = 72, $force_refresh = false) {
        $to_date = date('Y-m-d\TH:i:s');
        $from_date = date('Y-m-d\TH:i:s', strtotime("-{$hours_back} hours"));

        $data = array(
            'period_from' => $from_date,
            'period_to' => $to_date,
            'list_type' => 'placed'  // Use 'placed' to get bookings by creation date
        );

        $response = $this->call_api('bookings_list', $data, $force_refresh);

        if (!$response || !isset($response['data'])) {
            bma_log('BMA: Error fetching recent placed bookings - no data returned', 'error');
            return array();
        }

        $bookings = $response['data'];

        // Sort by booking_id descending (most recent first)
        usort($bookings, function($a, $b) {
            return ($b['booking_id'] ?? 0) - ($a['booking_id'] ?? 0);
        });

        bma_log("BMA NewBook Search: Fetched " . count($bookings) . " bookings, applying limit = {$limit}", 'debug');

        // Apply limit
        $limited_bookings = array_slice($bookings, 0, $limit);
        bma_log("BMA NewBook Search: Returning " . count($limited_bookings) . " bookings after limit", 'debug');

        return $limited_bookings;
    }

    /**
     * Fetch recently cancelled bookings
     *
     * @param int $days_back Number of days to search back (default 5)
     * @param bool $force_refresh Whether to bypass cache
     * @return array Array of cancelled bookings
     */
    public function fetch_recent_cancelled_bookings($days_back = 5, $force_refresh = false) {
        $to_date = date('Y-m-d\TH:i:s');
        $from_date = date('Y-m-d\TH:i:s', strtotime("-{$days_back} days"));

        $data = array(
            'period_from' => $from_date,
            'period_to' => $to_date,
            'list_type' => 'cancelled'  // Use 'cancelled' to get cancelled bookings
        );

        $response = $this->call_api('bookings_list', $data, $force_refresh);

        if (!$response || !isset($response['data'])) {
            bma_log('BMA: Error fetching recent cancelled bookings - no data returned', 'error');
            return array();
        }

        $bookings = $response['data'];

        // Filter to only cancelled status (in case API returns other statuses)
        $cancelled = array_filter($bookings, function($booking) {
            $status = strtolower($booking['booking_status'] ?? '');
            return in_array($status, array('cancelled', 'canceled'));
        });

        // Sort by booking_modified or booking_id descending (most recent first)
        usort($cancelled, function($a, $b) {
            // Try to sort by modification date if available
            $a_modified = $a['booking_modified'] ?? $a['booking_id'] ?? 0;
            $b_modified = $b['booking_modified'] ?? $b['booking_id'] ?? 0;

            if (is_string($a_modified) && is_string($b_modified)) {
                return strtotime($b_modified) - strtotime($a_modified);
            }
            return ($b_modified ?? 0) - ($a_modified ?? 0);
        });

        bma_log("BMA NewBook Search: Fetched " . count($cancelled) . " cancelled bookings from last {$days_back} days", 'debug');

        return $cancelled;
    }

    /**
     * Fetch all hotel bookings for a specific date
     * Used for GROUP modal to show all bookings on a particular date
     *
     * @param string $date Date in YYYY-MM-DD format
     * @param bool $force_refresh If true, bypass and clear cache
     * @return array Array of bookings for the date
     */
    public function fetch_hotel_bookings_for_date($date, $force_refresh = false) {
        $period_from = $date . ' 00:00:00';
        $period_to = $date . ' 23:59:59';

        $data = array(
            'period_from' => $period_from,
            'period_to' => $period_to,
            'list_type' => 'staying'
        );

        $response = $this->call_api('bookings_list', $data, $force_refresh);

        if (!$response || !isset($response['data'])) {
            bma_log('BMA: Failed to fetch hotel bookings for date ' . $date, 'error');
            return array();
        }

        bma_log('BMA: Fetched ' . count($response['data']) . ' hotel bookings for date ' . $date, 'debug');
        return $response['data'];
    }

    /**
     * Calculate cache TTL based on booking date proximity
     * Tiered approach: bookings arriving soon get shorter stale cache
     *
     * - Within 30 days: 60s fresh, 300s (5min) stale
     * - 30 days to 6 months: 60s fresh, 600s (10min) stale
     * - Over 6 months: 60s fresh, 900s (15min) stale
     * - No date info: 60s fresh, 600s (10min) stale (default)
     *
     * @param string $action API action
     * @param array $data Request parameters
     * @return array [fresh_ttl, stale_ttl]
     */
    private function calculate_cache_ttl($action, $data) {
        // Only apply tiered caching to bookings_list calls
        if ($action !== 'bookings_list') {
            return array(60, 600); // Default: 60s fresh, 10min stale
        }

        // Extract date from request parameters
        $check_date = null;
        if (isset($data['period_from'])) {
            // Extract date from period_from (e.g., "2025-01-15 00:00:00")
            $check_date = substr($data['period_from'], 0, 10);
        } elseif (isset($data['list_type']) && $data['list_type'] === 'placed') {
            // For "placed" bookings, use current date (recent bookings)
            $check_date = date('Y-m-d');
        }

        if (!$check_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_date)) {
            return array(60, 600); // Default if no valid date
        }

        try {
            // Calculate days until/since the date
            $today = new DateTime();
            $target_date = new DateTime($check_date);
            $interval = $today->diff($target_date);
            $days_diff = $interval->days;
            $is_future = ($target_date > $today);

            // Only apply tiers to future dates or recent past (bookings arriving/arrived)
            if (!$is_future && $days_diff > 30) {
                // Past bookings older than 30 days: longest cache
                bma_log("NewBook API: Cache TTL for date {$check_date}: 60s fresh, 900s stale (past > 30 days)", 'debug');
                return array(60, 900); // 15min stale
            }

            // Calculate appropriate tier
            if ($days_diff <= 30) {
                // Within 30 days: shortest stale period (most activity)
                bma_log("NewBook API: Cache TTL for date {$check_date}: 60s fresh, 300s stale (within 30 days)", 'debug');
                return array(60, 300); // 5min stale
            } elseif ($days_diff <= 180) {
                // 30 days to 6 months: medium stale period
                bma_log("NewBook API: Cache TTL for date {$check_date}: 60s fresh, 600s stale (30-180 days)", 'debug');
                return array(60, 600); // 10min stale
            } else {
                // Over 6 months: longest stale period (least activity)
                bma_log("NewBook API: Cache TTL for date {$check_date}: 60s fresh, 900s stale (> 180 days)", 'debug');
                return array(60, 900); // 15min stale
            }
        } catch (Exception $e) {
            bma_log("NewBook API: Error calculating cache TTL for {$check_date}: " . $e->getMessage(), 'error');
            return array(60, 600); // Default on error
        }
    }

    /**
     * Call NewBook API with centralized caching
     *
     * @param string $action API action (e.g., 'bookings_get', 'bookings_list')
     * @param array $data API request parameters
     * @param bool $force_refresh If true, bypass and clear cache
     * @return array|false API response data or false on failure
     */
    private function call_api($action, $data = array(), $force_refresh = false) {
        // ============================================
        // DIAGNOSTIC LOGGING: Track API call source
        // ============================================
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = 'unknown';
        if (isset($backtrace[1])) {
            $caller_class = $backtrace[1]['class'] ?? '';
            $caller_function = $backtrace[1]['function'] ?? '';
            $caller = $caller_class ? "{$caller_class}::{$caller_function}" : $caller_function;
        }

        // Extract key params for logging (without sensitive data)
        $log_params = array();
        if (isset($data['booking_id'])) $log_params['booking_id'] = $data['booking_id'];
        if (isset($data['check_from'])) $log_params['check_from'] = $data['check_from'];
        if (isset($data['check_to'])) $log_params['check_to'] = $data['check_to'];
        if (isset($data['status'])) $log_params['status'] = $data['status'];

        $params_str = !empty($log_params) ? json_encode($log_params) : 'none';
        bma_log("NewBook API CALL: action={$action}, caller={$caller}, params={$params_str}, force_refresh=" . ($force_refresh ? 'TRUE' : 'FALSE'), 'info');
        // ============================================

        // Get NewBook API credentials (check new options first, fallback to old)
        $username = get_option('bma_newbook_username') ?: get_option('hotel_booking_api_username') ?: get_option('hotel_booking_newbook_username');
        $password = get_option('bma_newbook_password') ?: get_option('hotel_booking_api_password') ?: get_option('hotel_booking_newbook_password');
        $api_key = get_option('bma_newbook_api_key') ?: get_option('hotel_booking_api_key') ?: get_option('hotel_booking_newbook_api_key');
        $region = get_option('bma_newbook_region') ?: get_option('hotel_booking_api_region') ?: get_option('hotel_booking_newbook_region', 'au');

        if (empty($username) || empty($password) || empty($api_key)) {
            bma_log('BMA: API credentials not configured', 'error');
            return false;
        }

        // Add required parameters for cache key generation
        $data['region'] = $region;
        $data['api_key'] = $api_key;

        // Generate cache keys based on action and serialized parameters
        $cache_key = 'bma_newbook_' . $action . '_' . md5(serialize($data));
        $stale_key = $cache_key . '_stale';

        // If force refresh, clear both fresh and stale caches
        if ($force_refresh) {
            delete_transient($cache_key);
            delete_transient($stale_key);
            bma_log("NewBook API: Force refresh - cleared cache for {$action}", 'debug');
        }

        // Check fresh cache (60 seconds)
        if (!$force_refresh) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                bma_log("NewBook API: ✓ CACHE HIT - {$action} from {$caller} (using cached data)", 'info');
                return $cached;
            }
        }

        // ============================================
        // LOCK-BASED REQUEST DEDUPLICATION
        // Prevent multiple parallel requests from hitting API for same data
        // ============================================
        $lock_key = $cache_key . '_lock';
        $lock_wait_seconds = 0;
        $max_wait_seconds = 10; // Reduced from 30 to prevent gateway timeouts

        // Check if another request is already fetching this data
        while (get_transient($lock_key) !== false && $lock_wait_seconds < $max_wait_seconds) {
            // Another request is currently fetching - wait for it to complete
            usleep(200000); // Wait 0.2 seconds (reduced from 0.5s for faster polling)
            $lock_wait_seconds += 0.2;

            // Check if cache is now populated by the other request
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                bma_log("NewBook API: ✓ CACHE HIT after lock wait ({$lock_wait_seconds}s) - {$action} from {$caller}", 'info');
                return $cached;
            }
        }

        // If we hit max wait time, proceed anyway to avoid cascade timeouts
        if (get_transient($lock_key) !== false) {
            bma_log("NewBook API: Lock timeout ({$max_wait_seconds}s) - proceeding anyway - {$action} from {$caller}", 'warning');
        }

        // Set lock to indicate this request is fetching data (15 second lock)
        set_transient($lock_key, time(), 15);
        // ============================================

        // Build URL
        $url = $this->api_base_url . $action;

        // Prepare request
        $args = array(
            'method' => 'POST',
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
            ),
            'body' => json_encode($data)
        );

        bma_log("NewBook API: ⚠ CACHE MISS - {$action} from {$caller} - CALLING API ENDPOINT", 'warning');

        // Make request and process response (lock released in finally block)
        try {
            // Make request
            $response = wp_remote_post($url, $args);

            // Handle WP_Error - check stale cache
            if (is_wp_error($response)) {
                $error_msg = $response->get_error_message();
                bma_log('BMA: API request failed: ' . $error_msg, 'error');

                // Try stale cache as fallback
                $stale_cached = get_transient($stale_key);
                if ($stale_cached !== false) {
                    $this->stale_cache_used[] = $action;
                    bma_log("NewBook API: ⚠ STALE CACHE - {$action} from {$caller} (API failed, using old data)", 'warning');
                    return $stale_cached;
                }

                return false;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            // Handle non-200 response - check stale cache
            if ($response_code !== 200) {
                bma_log("BMA: API returned error code: {$response_code} for {$action} from {$caller}", 'error');

                // Try stale cache as fallback
                $stale_cached = get_transient($stale_key);
                if ($stale_cached !== false) {
                    $this->stale_cache_used[] = $action;
                    bma_log("NewBook API: ⚠ STALE CACHE - {$action} from {$caller} (API error {$response_code}, using old data)", 'warning');
                    return $stale_cached;
                }

                return false;
            }

            // Parse JSON response
            $response_data = json_decode($response_body, true);

            // Handle JSON parse error - check stale cache
            if (json_last_error() !== JSON_ERROR_NONE) {
                bma_log('BMA: Failed to parse API response: ' . json_last_error_msg(), 'error');

                // Try stale cache as fallback
                $stale_cached = get_transient($stale_key);
                if ($stale_cached !== false) {
                    $this->stale_cache_used[] = $action;
                    bma_log("NewBook API: Using STALE cache for {$action} (JSON parse failed)", 'warning');
                    return $stale_cached;
                }

                return false;
            }

            // Success - cache the response with dynamic TTL based on date proximity
            list($fresh_ttl, $stale_ttl) = $this->calculate_cache_ttl($action, $data);
            set_transient($cache_key, $response_data, $fresh_ttl);
            set_transient($stale_key, $response_data, $stale_ttl);
            bma_log("NewBook API: ✓ SUCCESS - {$action} from {$caller} - cached ({$fresh_ttl}s fresh, {$stale_ttl}s stale)", 'info');

            return $response_data;

        } finally {
            // Always release lock, even if there was an error
            delete_transient($lock_key);
        }
    }

    /**
     * Check if stale cache was used in this request
     *
     * @return bool True if any API call used stale cache
     */
    public function has_stale_data() {
        return !empty($this->stale_cache_used);
    }

    /**
     * Get list of actions that used stale cache
     *
     * @return array Array of action names that used stale cache
     */
    public function get_stale_actions() {
        return $this->stale_cache_used;
    }

    /**
     * Helper functions (reuse from existing plugin)
     */
    private function normalize_for_matching($string) {
        if (empty($string)) {
            return '';
        }
        $normalized = strtolower(trim($string));
        return str_replace(array('-', "'", ' ', '.'), '', $normalized);
    }

    private function normalize_phone_for_matching($phone) {
        if (empty($phone)) {
            return '';
        }
        return preg_replace('/\D/', '', trim($phone));
    }

    private function extract_surname($full_name) {
        if (empty($full_name)) {
            return '';
        }
        $parts = explode(' ', trim($full_name));
        return end($parts);
    }

    private function get_primary_guest_surname($booking) {
        if (!isset($booking['guests']) || !is_array($booking['guests'])) {
            return '';
        }

        foreach ($booking['guests'] as $guest) {
            if (isset($guest['primary_client']) && $guest['primary_client'] == '1') {
                return $guest['lastname'] ?? '';
            }
        }

        // Fallback to first guest
        if (!empty($booking['guests'][0]['lastname'])) {
            return $booking['guests'][0]['lastname'];
        }

        return '';
    }

    /**
     * Fetch bookings staying on a specific date
     * Uses NewBook's 'staying' list type which filters for bookings where:
     * arrival_date <= $date AND departure_date > $date
     *
     * @param string $date Date in YYYY-MM-DD format
     * @return array Array of booking objects
     */
    public function fetch_staying_bookings($date) {
        // Fetch 3-day window for timeline indicators (previous night, selected date, next night)
        $previous_night = date('Y-m-d', strtotime($date . ' -1 day'));
        $next_night = date('Y-m-d', strtotime($date . ' +1 day'));

        $period_from = $previous_night . ' 00:00:00';
        $period_to = $next_night . ' 23:59:59';

        $data = array(
            'period_from' => $period_from,
            'period_to' => $period_to,
            'list_type' => 'staying'  // NewBook API filters for guests staying on this date
        );

        $response = $this->call_api('bookings_list', $data);

        if (!$response || !isset($response['data'])) {
            return array();
        }

        return $response['data'];
    }

    /**
     * Fetch list of all sites/rooms from NewBook
     * Reusable function for getting room inventory
     *
     * @return array Array of site objects with site_id, site_name, etc.
     */
    public function fetch_sites() {
        // Check cache first (cache for 1 hour since rooms don't change often)
        $cache_key = 'bma_newbook_sites';
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $data = array();
        $response = $this->call_api('sites_list', $data);

        if (!$response || !isset($response['data'])) {
            bma_log('BMA_NewBook_Search: Failed to fetch sites list', 'error');
            return array();
        }

        $sites = $response['data'];

        // Cache for 1 hour
        set_transient($cache_key, $sites, HOUR_IN_SECONDS);

        return $sites;
    }
}
