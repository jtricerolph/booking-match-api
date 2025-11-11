<?php
/**
 * Booking Matcher Class
 *
 * Handles matching NewBook bookings with Resos restaurant bookings
 * Reuses logic from the existing Reservation Management Integration plugin
 *
 * @package BookingMatchAPI
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class BMA_Matcher {

    /**
     * Cache for hotel bookings by date to prevent duplicate API calls
     */
    private $hotel_bookings_cache = array();

    /**
     * Match a booking across all its nights
     *
     * @param array $booking NewBook booking data
     * @return array Match results for all nights
     */
    public function match_booking_all_nights($booking) {
        // Extract arrival and departure dates
        $arrival = $booking['booking_arrival'] ?? '';
        $departure = $booking['booking_departure'] ?? '';

        if (empty($arrival) || empty($departure)) {
            return array(
                'error' => 'Missing arrival or departure date',
                'booking_id' => $booking['booking_id'] ?? null
            );
        }

        // Calculate nights
        $nights = $this->calculate_nights($arrival, $departure);

        // Get booking summary
        $summary = $this->get_booking_summary($booking);

        // Match for each night
        $night_matches = array();
        foreach ($nights as $night) {
            $match = $this->match_single_night($booking, $night);
            $night_matches[] = $match;
        }

        // Extract additional booking info for chrome-sidepanel display
        $occupants = $this->extract_occupants($booking);
        $tariffs = $this->extract_tariffs($booking);
        $booking_status = $booking['booking_status'] ?? 'unknown';
        $booking_source = $this->determine_booking_source($booking);

        // Extract guest contact details from guests array
        $guest_phone = $this->get_primary_guest_phone($booking);
        $guest_email = $this->get_primary_guest_email($booking);

        return array(
            'booking_id' => $booking['booking_id'] ?? null,
            'booking_reference' => $booking['booking_reference_id'] ?? '',
            'guest_name' => $summary['guest_name'],
            'room' => $summary['room'],
            'arrival' => substr($arrival, 0, 10),
            'departure' => substr($departure, 0, 10),
            'nights' => $night_matches,
            'total_nights' => count($night_matches),
            'occupants' => $occupants,
            'tariffs' => $tariffs,
            'booking_status' => $booking_status,
            'booking_source' => $booking_source,
            'phone' => $guest_phone,
            'email' => $guest_email
        );
    }

    /**
     * Match booking for a single night
     */
    private function match_single_night($booking, $date) {
        // Fetch Resos bookings for this date
        $resos_bookings = $this->fetch_resos_bookings($date);

        // Fetch ALL hotel bookings for this date (for "matched elsewhere" checking)
        $all_hotel_bookings = $this->fetch_hotel_bookings_for_date($date);

        // Build array of all hotel booking IDs for this date (excluding current booking)
        $current_booking_id = $booking['booking_id'] ?? '';
        $other_booking_ids = array();
        foreach ($all_hotel_bookings as $hb) {
            $hb_id = $hb['booking_id'] ?? '';
            if (!empty($hb_id) && $hb_id != $current_booking_id) {
                $other_booking_ids[] = strval($hb_id);
            }
        }

        // Check if this booking has a package for this date
        $has_package = $this->check_has_package($booking, $date);

        $result = array(
            'date' => $date,
            'resos_matches' => array(),
            'match_count' => 0,
            'has_package' => $has_package
        );

        if (empty($resos_bookings)) {
            return $result;
        }

        // Find all matches and sort by score
        $all_matches = array();

        foreach ($resos_bookings as $resos_booking) {
            $match_info = $this->match_resos_to_hotel($resos_booking, $booking, $date, $other_booking_ids);

            if ($match_info['matched']) {
                $result['match_count']++;

                // Extract custom fields
                $custom_fields = $resos_booking['customFields'] ?? array();
                $is_hotel_guest = false;
                $is_dbb = false;
                $booking_number = '';

                foreach ($custom_fields as $field) {
                    $field_name = $field['name'] ?? '';
                    $field_value = $field['value'] ?? $field['multipleChoiceValueName'] ?? '';

                    if ($field_name === 'Hotel Guest') {
                        $is_hotel_guest = ($field_value === 'Yes' || $field_value === 'yes' || $field_value === '1');
                    } elseif ($field_name === 'DBB') {
                        $is_dbb = ($field_value === 'Yes' || $field_value === 'yes' || $field_value === '1');
                    } elseif ($field_name === 'Booking #') {
                        $booking_number = $field_value;
                    }
                }

                // Get booking time
                $booking_time = '';
                if (!empty($resos_booking['timeString'])) {
                    $booking_time = $resos_booking['timeString'];
                } elseif (!empty($resos_booking['time'])) {
                    $booking_time = date('H:i', strtotime($resos_booking['time']));
                }

                $match = array(
                    'resos_booking_id' => $resos_booking['_id'] ?? $resos_booking['id'] ?? null,
                    'restaurant_id' => $resos_booking['restaurantId'] ?? '',
                    'guest_name' => $resos_booking['guest']['name'] ?? '',
                    'people' => $resos_booking['people'] ?? 0,
                    'status' => $resos_booking['status'] ?? '',
                    'time' => $booking_time,
                    'is_hotel_guest' => $is_hotel_guest,
                    'is_dbb' => $is_dbb,
                    'booking_number' => $booking_number,
                    'match_info' => $match_info,
                    'score' => $this->calculate_match_score($match_info)
                );

                $all_matches[] = $match;
            }
        }

        // Sort by score (highest first)
        usort($all_matches, function($a, $b) {
            return $b['score'] - $a['score'];
        });

        $result['resos_matches'] = $all_matches;

        return $result;
    }

    /**
     * Match Resos booking to hotel booking (reuse existing logic)
     * Made public so other plugins can use this matcher
     *
     * @param array $resos_booking Resos booking data
     * @param array $hotel_booking Hotel booking data
     * @param string $date Date being matched (YYYY-MM-DD)
     * @param array $other_booking_ids Array of OTHER hotel booking IDs for this date (for "matched elsewhere" checking)
     */
    public function match_resos_to_hotel($resos_booking, $hotel_booking, $date, $other_booking_ids = array()) {
        $hotel_booking_id = $hotel_booking['booking_id'] ?? '';
        $hotel_ref = $hotel_booking['booking_reference_id'] ?? '';
        $hotel_room = $hotel_booking['site_name'] ?? '';

        // Check for exclusion notes FIRST (before any matching logic)
        // If Resos booking has "NOT-#{hotel_booking_id}" note, exclude it from matching
        $notes = $this->get_resos_notes($resos_booking);
        if (!empty($hotel_booking_id)) {
            $exclusion_pattern = 'NOT-#' . $hotel_booking_id;
            if (stripos($notes, $exclusion_pattern) !== false) {
                // This match has been explicitly excluded
                return array(
                    'matched' => false,
                    'excluded' => true,
                    'exclusion_reason' => 'Manual exclusion note found'
                );
            }
        }

        // Check if this Resos booking's "Booking #" field matches ANY OTHER hotel booking ID
        // If so, it's "matched elsewhere" and should not be considered for this booking
        if (!empty($other_booking_ids)) {
            $custom_fields = $resos_booking['customFields'] ?? array();
            foreach ($custom_fields as $field) {
                if (($field['name'] ?? '') === 'Booking #') {
                    $value = $field['value'] ?? $field['multipleChoiceValueName'] ?? '';
                    if (!empty($value) && in_array(strval($value), $other_booking_ids, true)) {
                        // This Resos booking is matched to a different hotel booking
                        return array(
                            'matched' => false,
                            'matched_elsewhere' => true,
                            'matched_to_booking_id' => $value
                        );
                    }
                }
            }
        }

        // Priority 1: Booking ID in custom fields
        $custom_fields = $resos_booking['customFields'] ?? array();
        foreach ($custom_fields as $field) {
            if (($field['name'] ?? '') === 'Booking #') {
                $value = $field['value'] ?? $field['multipleChoiceValueName'] ?? '';
                if (!empty($value) && $value == $hotel_booking_id) {
                    return array(
                        'matched' => true,
                        'match_type' => 'booking_id',
                        'confidence' => 'high',
                        'is_primary' => true,
                        'match_label' => 'Booking ID'
                    );
                }
            }
        }

        // Priority 2: Agent reference match
        foreach ($custom_fields as $field) {
            $value = $field['value'] ?? $field['multipleChoiceValueName'] ?? '';
            if (!empty($hotel_ref) && !empty($value) && $value == $hotel_ref) {
                return array(
                    'matched' => true,
                    'match_type' => 'agent_ref',
                    'confidence' => 'high',
                    'is_primary' => true,
                    'match_label' => 'Agent Reference'
                );
            }
        }

        // Priority 3: Booking ID in notes
        $notes = $this->get_resos_notes($resos_booking);
        if (!empty($hotel_booking_id) && stripos($notes, (string)$hotel_booking_id) !== false) {
            return array(
                'matched' => true,
                'match_type' => 'notes_booking_id',
                'confidence' => 'high',
                'is_primary' => true,
                'match_label' => 'Booking ID in notes'
            );
        }

        // Priority 4: Agent ref in notes
        if (!empty($hotel_ref) && stripos($notes, $hotel_ref) !== false) {
            return array(
                'matched' => true,
                'match_type' => 'notes_agent_ref',
                'confidence' => 'high',
                'is_primary' => true,
                'match_label' => 'Agent ref in notes'
            );
        }

        // Composite matching
        $score = 0;
        $match_count = 0;
        $match_types = array();

        // Room number match
        if (!empty($hotel_room) && stripos($notes, $hotel_room) !== false) {
            $score += 8;
            $match_count++;
            $match_types[] = 'room';
        }

        // Surname match
        $hotel_surname = $this->get_primary_guest_surname($hotel_booking);
        $resos_name = $resos_booking['guest']['name'] ?? '';
        $resos_surname = $this->extract_surname($resos_name);

        if (!empty($hotel_surname) && !empty($resos_surname)) {
            if ($this->normalize_for_matching($hotel_surname) === $this->normalize_for_matching($resos_surname)) {
                $score += 7;
                $match_count++;
                $match_types[] = 'surname';
            }
        }

        // Phone match
        $hotel_phone = $this->get_primary_guest_phone($hotel_booking);
        $resos_phone = $resos_booking['guest']['phone'] ?? '';

        if (!empty($hotel_phone) && !empty($resos_phone)) {
            $hotel_phone_norm = $this->normalize_phone_for_matching($hotel_phone);
            $resos_phone_norm = $this->normalize_phone_for_matching($resos_phone);

            if (strlen($hotel_phone_norm) >= 8 && strlen($resos_phone_norm) >= 8) {
                if (substr($hotel_phone_norm, -8) === substr($resos_phone_norm, -8)) {
                    $score += 9;
                    $match_count++;
                    $match_types[] = 'phone';
                }
            }
        }

        // Email match
        $hotel_email = $this->get_primary_guest_email($hotel_booking);
        $resos_email = $resos_booking['guest']['email'] ?? '';

        if (!empty($hotel_email) && !empty($resos_email)) {
            if ($this->normalize_for_matching($hotel_email) === $this->normalize_for_matching($resos_email)) {
                $score += 10;
                $match_count++;
                $match_types[] = 'email';
            }
        }

        // Determine confidence
        if ($score >= 20 || $match_count >= 3) {
            $confidence = 'high';
        } elseif ($score >= 15 || $match_count >= 2) {
            $confidence = 'medium';
        } elseif ($score > 0) {
            $confidence = 'low';
        } else {
            return array('matched' => false);
        }

        return array(
            'matched' => true,
            'match_type' => implode('_', $match_types),
            'match_label' => implode(' + ', array_map('ucfirst', $match_types)),
            'confidence' => $confidence,
            'is_primary' => false,
            'match_count' => $match_count,
            'score' => $score
        );
    }

    /**
     * Calculate numeric score for match prioritization
     */
    private function calculate_match_score($match_info) {
        if ($match_info['is_primary']) {
            return 1000; // Primary matches always win
        }

        return $match_info['score'] ?? 0;
    }

    /**
     * Calculate nights between arrival and departure
     */
    private function calculate_nights($arrival, $departure) {
        $start = new DateTime(substr($arrival, 0, 10));
        $end = new DateTime(substr($departure, 0, 10));
        $interval = $start->diff($end);
        $num_nights = $interval->days;

        $nights = array();
        for ($i = 0; $i < $num_nights; $i++) {
            $date = clone $start;
            $date->add(new DateInterval('P' . $i . 'D'));
            $nights[] = $date->format('Y-m-d');
        }

        return $nights;
    }

    /**
     * Get booking summary info
     */
    private function get_booking_summary($booking) {
        return array(
            'guest_name' => $this->get_primary_guest_name($booking),
            'room' => $booking['site_name'] ?? '',
            'adults' => $booking['booking_adults'] ?? 0,
            'children' => $booking['booking_children'] ?? 0
        );
    }

    /**
     * Fetch Resos bookings for a date
     */
    public function fetch_resos_bookings($date) {
        // Check cache first (60 second cache to prevent API hammering)
        $cache_key = 'bma_resos_bookings_' . $date;
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $api_key = get_option('hotel_booking_resos_api_key');

        if (empty($api_key)) {
            error_log("BMA ERROR: Resos API key not configured");
            return array();
        }

        $url = 'https://api.resos.com/v1/bookings';
        $url .= '?fromDateTime=' . urlencode($date . 'T00:00:00');
        $url .= '&toDateTime=' . urlencode($date . 'T23:59:59');
        $url .= '&limit=100';

        $args = array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($api_key . ':')
            ),
            'timeout' => 30
        );

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            error_log("BMA ERROR: Resos API call failed for date {$date}: " . $response->get_error_message());

            // Try to return stale cache if available (with _stale suffix)
            $stale_cache = get_transient($cache_key . '_stale');
            if ($stale_cache !== false) {
                error_log("BMA WARNING: Returning stale cache for date {$date} due to API failure");
                return $stale_cache;
            }

            return array();
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            error_log("BMA ERROR: Resos API returned HTTP {$http_code} for date {$date}");

            // Try to return stale cache
            $stale_cache = get_transient($cache_key . '_stale');
            if ($stale_cache !== false) {
                error_log("BMA WARNING: Returning stale cache for date {$date} due to HTTP {$http_code}");
                return $stale_cache;
            }

            return array();
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data)) {
            error_log("BMA ERROR: Resos API returned invalid JSON for date {$date}");

            // Try to return stale cache
            $stale_cache = get_transient($cache_key . '_stale');
            if ($stale_cache !== false) {
                error_log("BMA WARNING: Returning stale cache for date {$date} due to invalid JSON");
                return $stale_cache;
            }

            return array();
        }

        // Filter out canceled/no-show
        $excluded_statuses = array('canceled', 'cancelled', 'no_show', 'no-show', 'deleted');
        $filtered = array();

        foreach ($data as $booking) {
            $status = strtolower($booking['status'] ?? '');
            if (!in_array($status, $excluded_statuses)) {
                $filtered[] = $booking;
            }
        }

        // Cache for 60 seconds
        set_transient($cache_key, $filtered, 60);

        // Also set a stale cache (5 minutes) as fallback
        set_transient($cache_key . '_stale', $filtered, 300);

        return $filtered;
    }

    /**
     * Fetch all restaurant bookings for a date, formatted for Gantt chart display
     *
     * @param string $date Date in YYYY-MM-DD format
     * @return array Array of bookings with time, people, name, is_resident
     */
    public function fetch_all_bookings_for_gantt($date) {
        $raw_bookings = $this->fetch_resos_bookings($date);
        $formatted_bookings = array();

        // Log first booking structure for debugging
        if (!empty($raw_bookings)) {
            error_log('BMA_Matcher: Sample raw Resos booking structure: ' . print_r($raw_bookings[0], true));
        }

        foreach ($raw_bookings as $booking) {
            // Extract Hotel Guest status from customFields
            $is_resident = false;
            if (isset($booking['customFields']) && is_array($booking['customFields'])) {
                foreach ($booking['customFields'] as $field) {
                    $field_name = $field['name'] ?? '';

                    if ($field_name === 'Hotel Guest') {
                        // For multiple choice fields, use multipleChoiceValueName (not value which contains ID)
                        $field_value = $field['multipleChoiceValueName'] ?? $field['value'] ?? '';
                        $is_resident = ($field_value === 'Yes' || $field_value === 'yes' || $field_value === '1');
                        error_log('BMA_Matcher: Found Hotel Guest field, value: ' . $field_value . ', is_resident: ' . ($is_resident ? 'yes' : 'no'));
                        break;
                    }
                }
                if (!$is_resident) {
                    error_log('BMA_Matcher: No Hotel Guest field found or value not Yes');
                }
            } else {
                error_log('BMA_Matcher: No customFields found in booking');
            }

            // Try to extract guest name from various possible fields
            $guest_name = 'Guest';
            if (isset($booking['guestName'])) {
                $guest_name = $booking['guestName'];
            } elseif (isset($booking['guest_name'])) {
                $guest_name = $booking['guest_name'];
            } elseif (isset($booking['name'])) {
                $guest_name = $booking['name'];
            } elseif (isset($booking['firstName']) && isset($booking['lastName'])) {
                $guest_name = trim($booking['firstName'] . ' ' . $booking['lastName']);
            } elseif (isset($booking['firstName'])) {
                $guest_name = $booking['firstName'];
            } elseif (isset($booking['guest']['name'])) {
                $guest_name = $booking['guest']['name'];
            }

            // Try to extract time from various possible fields
            $time = '19:00';
            if (isset($booking['time'])) {
                $time = $booking['time'];
            } elseif (isset($booking['startTime'])) {
                $time = $booking['startTime'];
            } elseif (isset($booking['bookingTime'])) {
                $time = $booking['bookingTime'];
            } elseif (isset($booking['dateTime'])) {
                // Extract time portion from datetime string
                $datetime = $booking['dateTime'];
                if (preg_match('/T(\d{2}:\d{2})/', $datetime, $matches)) {
                    $time = $matches[1];
                }
            }

            // Try to extract people count
            $people = 2;
            if (isset($booking['people'])) {
                $people = $booking['people'];
            } elseif (isset($booking['partySize'])) {
                $people = $booking['partySize'];
            } elseif (isset($booking['guests'])) {
                $people = $booking['guests'];
            } elseif (isset($booking['numberOfGuests'])) {
                $people = $booking['numberOfGuests'];
            }

            error_log('BMA_Matcher: Extracted - time: ' . $time . ', people: ' . $people . ', name: ' . $guest_name . ', is_resident: ' . ($is_resident ? 'yes' : 'no'));

            $formatted_bookings[] = array(
                'time' => $time,
                'people' => $people,
                'name' => $guest_name,
                'is_resident' => $is_resident
            );
        }

        error_log('BMA_Matcher: Fetched ' . count($formatted_bookings) . ' bookings for Gantt chart on ' . $date);
        return $formatted_bookings;
    }

    /**
     * Fetch ALL hotel bookings for a specific date
     * Used for "matched elsewhere" checking
     * Uses caching to prevent duplicate API calls for the same date
     */
    private function fetch_hotel_bookings_for_date($date) {
        // Check cache first
        if (isset($this->hotel_bookings_cache[$date])) {
            error_log('BMA_Matcher: Using cached hotel bookings for date ' . $date);
            return $this->hotel_bookings_cache[$date];
        }

        // Get NewBook API credentials
        $username = get_option('hotel_booking_newbook_username');
        $password = get_option('hotel_booking_newbook_password');
        $api_key = get_option('hotel_booking_newbook_api_key');
        $region = get_option('hotel_booking_newbook_region', 'au');
        $hotel_id = get_option('hotel_booking_default_hotel_id', '1');

        if (empty($username) || empty($password) || empty($api_key)) {
            return array();
        }

        // Build NewBook API URL
        $url = "https://api.{$region}.newbook.cloud/rest/v1/site/{$hotel_id}/bookings";
        $url .= '?from_date=' . urlencode($date);
        $url .= '&to_date=' . urlencode($date);
        $url .= '&expand=guests,guests.contact_details,tariffs_quoted,inventory_items';

        $args = array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
                'x-api-key' => $api_key
            ),
            'timeout' => 30
        );

        error_log('BMA_Matcher: Fetching hotel bookings for date ' . $date . ' from NewBook API');
        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            error_log('BMA_Matcher: Failed to fetch hotel bookings for date ' . $date . ': ' . $response->get_error_message());
            $this->hotel_bookings_cache[$date] = array(); // Cache empty result to prevent retry
            return array();
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data)) {
            error_log('BMA_Matcher: Invalid response when fetching hotel bookings for date ' . $date);
            $this->hotel_bookings_cache[$date] = array(); // Cache empty result to prevent retry
            return array();
        }

        // Cache the result
        $this->hotel_bookings_cache[$date] = $data;
        error_log('BMA_Matcher: Cached ' . count($data) . ' hotel bookings for date ' . $date);

        return $data;
    }

    /**
     * Helper functions
     */
    private function get_resos_notes($resos_booking) {
        $notes = '';
        if (isset($resos_booking['restaurantNotes']) && is_array($resos_booking['restaurantNotes'])) {
            foreach ($resos_booking['restaurantNotes'] as $note) {
                $notes .= ' ' . ($note['restaurantNote'] ?? '');
            }
        }
        return $notes;
    }

    private function get_primary_guest_name($booking) {
        if (!isset($booking['guests']) || !is_array($booking['guests'])) {
            return '';
        }

        foreach ($booking['guests'] as $guest) {
            if (isset($guest['primary_client']) && $guest['primary_client'] == '1') {
                return trim(($guest['firstname'] ?? '') . ' ' . ($guest['lastname'] ?? ''));
            }
        }

        if (!empty($booking['guests'][0])) {
            return trim(($booking['guests'][0]['firstname'] ?? '') . ' ' . ($booking['guests'][0]['lastname'] ?? ''));
        }

        return '';
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

        return $booking['guests'][0]['lastname'] ?? '';
    }

    private function get_primary_guest_phone($booking) {
        if (!isset($booking['guests']) || !is_array($booking['guests'])) {
            return '';
        }

        foreach ($booking['guests'] as $guest) {
            if (isset($guest['primary_client']) && $guest['primary_client'] == '1') {
                if (isset($guest['contact_details']) && is_array($guest['contact_details'])) {
                    foreach ($guest['contact_details'] as $contact) {
                        if ($contact['type'] === 'phone') {
                            return $contact['content'] ?? '';
                        }
                    }
                }
            }
        }

        return '';
    }

    private function get_primary_guest_email($booking) {
        if (!isset($booking['guests']) || !is_array($booking['guests'])) {
            return '';
        }

        foreach ($booking['guests'] as $guest) {
            if (isset($guest['primary_client']) && $guest['primary_client'] == '1') {
                if (isset($guest['contact_details']) && is_array($guest['contact_details'])) {
                    foreach ($guest['contact_details'] as $contact) {
                        if ($contact['type'] === 'email') {
                            return $contact['content'] ?? '';
                        }
                    }
                }
            }
        }

        return '';
    }

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

    /**
     * Check if booking has a package for a specific date
     * Uses the same logic as reservation-management-integration plugin
     */
    private function check_has_package($booking, $date) {
        $package_inventory_name = get_option('hotel_booking_package_inventory_name', '');

        if (empty($package_inventory_name)) {
            return false;
        }

        if (!isset($booking['inventory_items']) || !is_array($booking['inventory_items'])) {
            return false;
        }

        foreach ($booking['inventory_items'] as $item) {
            // Check if this inventory item is for the specified date
            if (isset($item['stay_date']) && $item['stay_date'] == $date) {
                // Check if the description contains the package identifier
                if (isset($item['description']) && stripos($item['description'], $package_inventory_name) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Extract occupants from booking (adults, children, infants)
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
     * Determine booking source
     */
    private function determine_booking_source($booking) {
        // Check for booking source field
        if (isset($booking['booking_source']) && !empty($booking['booking_source'])) {
            return $booking['booking_source'];
        }

        // Check for agent reference as fallback indicator
        if (isset($booking['booking_reference_id']) && !empty($booking['booking_reference_id'])) {
            return 'Online';
        }

        return 'Direct';
    }
}
