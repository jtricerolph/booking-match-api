<?php
/**
 * Booking Actions Class
 *
 * Handles Resos booking update and exclude operations
 * Extracted from Reservation Management Integration plugin for reuse
 *
 * @package BookingMatchAPI
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class BMA_Booking_Actions {

    /**
     * Update a Resos booking with new field values
     *
     * @param string $booking_id Resos booking ID
     * @param array $updates Array of field updates
     * @return array Success/error response
     */
    public function update_resos_booking($booking_id, $updates) {
        bma_log('BMA: update_resos_booking called with booking_id=' . $booking_id . ', updates=' . json_encode($updates), 'debug');

        // Validate parameters
        if (empty($booking_id)) {
            return array(
                'success' => false,
                'message' => 'Booking ID is required'
            );
        }

        if (empty($updates) || !is_array($updates)) {
            return array(
                'success' => false,
                'message' => 'No updates provided'
            );
        }

        // Get Resos API key
        // Get Resos API key (check new option first, fallback to old)
        $resos_api_key = get_option('bma_resos_api_key') ?: get_option('hotel_booking_resos_api_key');
        if (empty($resos_api_key)) {
            return array(
                'success' => false,
                'message' => 'Resos API key not configured'
            );
        }

        // Map of our internal field names to Resos customField names
        $custom_field_map = array(
            'dbb' => 'DBB',
            'booking_ref' => 'Booking #',
            'hotel_guest' => 'Hotel Guest',
            'group_exclude' => 'GROUP/EXCLUDE'
        );

        // Check if any special fields need to be converted to customFields
        $needs_custom_field_conversion = false;
        foreach ($custom_field_map as $internal_name => $resos_name) {
            if (isset($updates[$internal_name])) {
                $needs_custom_field_conversion = true;
                break;
            }
        }

        // Process custom field conversions if needed
        if ($needs_custom_field_conversion) {
            $result = $this->process_custom_field_updates($booking_id, $updates, $custom_field_map, $resos_api_key);
            if (!$result['success']) {
                return $result;
            }
            $updates = $result['updates'];
        }

        // Transform guest fields to nested structure
        $guest_fields = array('name', 'email', 'phone');
        $guest_data = array();
        foreach ($guest_fields as $field) {
            if (isset($updates[$field])) {
                $value = $updates[$field];
                // Format phone for Resos API (requires + and country code)
                if ($field === 'phone') {
                    $value = $this->format_phone_for_resos($value);
                }
                $guest_data[$field] = $value;
                unset($updates[$field]); // Remove from top level
            }
        }
        if (!empty($guest_data)) {
            $updates['guest'] = $guest_data;
            bma_log('BMA: Transformed guest fields to nested structure: ' . json_encode($guest_data), 'debug');
        }

        // Make PUT request to Resos API
        $url = 'https://api.resos.com/v1/bookings/' . urlencode($booking_id);
        $request_body = json_encode($updates);
        $args = array(
            'method' => 'PUT',
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($resos_api_key . ':'),
                'Content-Type' => 'application/json'
            ),
            'body' => $request_body
        );

        bma_log('BMA: Update Resos Booking - PUT ' . $url, 'debug');
        bma_log('BMA: Request Body: ' . $request_body, 'debug');

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Resos API request failed: ' . $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        bma_log('BMA: Response Code: ' . $response_code, 'debug');
        bma_log('BMA: Response Body: ' . $response_body, 'debug');

        if ($response_code !== 200) {
            return array(
                'success' => false,
                'message' => 'Resos API returned error code: ' . $response_code,
                'response_code' => $response_code,
                'response_body' => $response_body
            );
        }

        $data = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'message' => 'Failed to parse Resos API response: ' . json_last_error_msg()
            );
        }

        return array(
            'success' => true,
            'message' => 'Booking updated successfully',
            'booking_id' => $booking_id,
            'updates' => $updates,
            'booking_data' => $data
        );
    }

    /**
     * Exclude a Resos booking from matching a hotel booking
     * Updates the GROUP/EXCLUDE custom field with NOT-#{hotel_booking_id}
     *
     * @param string $resos_booking_id Resos booking ID
     * @param string $hotel_booking_id Hotel booking ID to exclude
     * @return array Success/error response
     */
    public function exclude_resos_match($resos_booking_id, $hotel_booking_id) {
        // Validate parameters
        if (empty($resos_booking_id)) {
            return array(
                'success' => false,
                'message' => 'Resos booking ID is required'
            );
        }

        if (empty($hotel_booking_id)) {
            return array(
                'success' => false,
                'message' => 'Hotel booking ID is required'
            );
        }

        // Get Resos API key
        // Get Resos API key (check new option first, fallback to old)
        $resos_api_key = get_option('bma_resos_api_key') ?: get_option('hotel_booking_resos_api_key');
        if (empty($resos_api_key)) {
            return array(
                'success' => false,
                'message' => 'Resos API key not configured'
            );
        }

        // Fetch current booking to get existing GROUP/EXCLUDE value
        $booking_url = 'https://api.resos.com/v1/bookings/' . urlencode($resos_booking_id) . '?expand=customFields';
        $fetch_args = array(
            'method' => 'GET',
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($resos_api_key . ':'),
                'Accept' => 'application/json'
            )
        );

        bma_log('BMA: Exclude Match - Fetching current booking ' . $resos_booking_id, 'debug');

        $response = wp_remote_get($booking_url, $fetch_args);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Failed to fetch booking: ' . $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return array(
                'success' => false,
                'message' => 'Failed to fetch booking. Status: ' . $response_code
            );
        }

        $booking_data = json_decode(wp_remote_retrieve_body($response), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'message' => 'Failed to parse booking data'
            );
        }

        // Get current GROUP/EXCLUDE data
        $current_data = $this->get_group_exclude_data($booking_data);

        // Add the booking ID to excludes if not already there
        if (!in_array(strval($hotel_booking_id), $current_data['excludes'], true)) {
            $current_data['excludes'][] = strval($hotel_booking_id);
        }

        // Build new field value
        $new_field_value = $this->build_group_exclude_value(
            $current_data['groups'],
            $current_data['individuals'],
            $current_data['excludes']
        );

        bma_log('BMA: Exclude Match - Updating GROUP/EXCLUDE to: ' . $new_field_value, 'debug');

        // Update the booking with new GROUP/EXCLUDE value
        $update_result = $this->update_resos_booking($resos_booking_id, array(
            'group_exclude' => $new_field_value
        ));

        if (!$update_result['success']) {
            return $update_result;
        }

        bma_log('BMA: Exclude Match SUCCESS - Added NOT-#' . $hotel_booking_id . ' to Resos booking ' . $resos_booking_id, 'debug');

        return array(
            'success' => true,
            'message' => 'Match excluded successfully',
            'resos_booking_id' => $resos_booking_id,
            'hotel_booking_id' => $hotel_booking_id,
            'exclusion_field' => $new_field_value
        );
    }

    /**
     * Process custom field updates (DBB, Booking #, Hotel Guest)
     *
     * @param string $booking_id Resos booking ID
     * @param array $updates Updates array
     * @param array $custom_field_map Mapping of internal to Resos field names
     * @param string $resos_api_key Resos API key
     * @return array Result with updated $updates array
     */
    private function process_custom_field_updates($booking_id, $updates, $custom_field_map, $resos_api_key) {
        // Fetch customField definitions from Resos
        $custom_fields_url = 'https://api.resos.com/v1/customFields';
        $cf_args = array(
            'method' => 'GET',
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($resos_api_key . ':'),
                'Accept' => 'application/json'
            )
        );

        $cf_response = wp_remote_get($custom_fields_url, $cf_args);
        if (is_wp_error($cf_response)) {
            return array(
                'success' => false,
                'message' => 'Failed to fetch customField definitions: ' . $cf_response->get_error_message()
            );
        }

        $cf_code = wp_remote_retrieve_response_code($cf_response);
        if ($cf_code !== 200) {
            return array(
                'success' => false,
                'message' => 'Failed to fetch customField definitions. Status: ' . $cf_code
            );
        }

        $custom_field_definitions = json_decode(wp_remote_retrieve_body($cf_response), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'message' => 'Failed to parse customField definitions'
            );
        }

        // Fetch current booking to get existing customFields
        $booking_url = 'https://api.resos.com/v1/bookings/' . urlencode($booking_id) . '?expand=customFields';
        $booking_args = array(
            'method' => 'GET',
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($resos_api_key . ':'),
                'Accept' => 'application/json'
            )
        );

        $booking_response = wp_remote_get($booking_url, $booking_args);
        if (is_wp_error($booking_response)) {
            return array(
                'success' => false,
                'message' => 'Failed to fetch current booking: ' . $booking_response->get_error_message()
            );
        }

        $booking_code = wp_remote_retrieve_response_code($booking_response);
        if ($booking_code !== 200) {
            return array(
                'success' => false,
                'message' => 'Failed to fetch current booking. Status: ' . $booking_code
            );
        }

        $current_booking = json_decode(wp_remote_retrieve_body($booking_response), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'message' => 'Failed to parse current booking'
            );
        }

        // Get existing customFields
        $existing_custom_fields = isset($current_booking['customFields']) ? $current_booking['customFields'] : array();
        $updated_custom_fields = $existing_custom_fields;

        // Process each special field
        foreach ($custom_field_map as $internal_name => $resos_name) {
            if (!isset($updates[$internal_name])) {
                continue; // This field wasn't updated
            }

            $new_value = $updates[$internal_name];

            // If value is empty, mark field for removal
            if (empty($new_value) && $new_value !== 0 && $new_value !== '0') {
                foreach ($updated_custom_fields as $index => $existing_field) {
                    if (isset($existing_field['name']) && $existing_field['name'] === $resos_name) {
                        $updated_custom_fields[$index]['value'] = '';
                        break;
                    }
                }
                unset($updates[$internal_name]);
                continue;
            }

            // Find the customField definition
            $field_definition = null;
            foreach ($custom_field_definitions as $def) {
                if ($def['name'] === $resos_name) {
                    $field_definition = $def;
                    break;
                }
            }

            if (!$field_definition) {
                bma_log("BMA: WARNING: Could not find customField definition for '$resos_name'", 'warning');
                continue;
            }

            // Determine if this is a multiple choice field
            $field_type = isset($field_definition['type']) ? $field_definition['type'] : '';
            $is_multiple_choice = in_array($field_type, array('radio', 'dropdown', 'checkbox'));

            // Prepare the field value structure
            $field_value_data = array(
                '_id' => $field_definition['_id'],
                'name' => $field_definition['name']
            );

            if ($is_multiple_choice) {
                // Special handling for multiselect checkboxes (e.g., dietary requirements)
                if ($field_type === 'checkbox') {
                    // Expect comma-separated choice IDs
                    $selected_ids = array_filter(array_map('trim', explode(',', $new_value)));
                    $choice_objects = array();

                    if (isset($field_definition['multipleChoiceSelections']) && is_array($field_definition['multipleChoiceSelections'])) {
                        foreach ($selected_ids as $selected_id) {
                            // Match by choice ID
                            foreach ($field_definition['multipleChoiceSelections'] as $choice) {
                                if (isset($choice['_id']) && $choice['_id'] === $selected_id) {
                                    $choice_objects[] = array(
                                        '_id' => $choice['_id'],
                                        'name' => $choice['name'],
                                        'value' => true
                                    );
                                    break;
                                }
                            }
                        }
                    }

                    if (!empty($choice_objects)) {
                        $field_value_data['value'] = $choice_objects;  // Array of objects for multiselect
                    } else {
                        bma_log("BMA: WARNING: No valid choices found for checkbox field {$field_definition['name']}", 'warning');
                        continue;
                    }
                } else {
                    // For single choice fields (radio/dropdown), find the choice ID
                    $choice_id = null;
                    if (isset($field_definition['multipleChoiceSelections']) && is_array($field_definition['multipleChoiceSelections'])) {
                        foreach ($field_definition['multipleChoiceSelections'] as $choice) {
                            if (isset($choice['name']) && $choice['name'] === $new_value) {
                                $choice_id = $choice['_id'];
                                break;
                            }
                        }
                    }

                    if ($choice_id) {
                        $field_value_data['value'] = $choice_id;
                        $field_value_data['multipleChoiceValueName'] = $new_value;
                    } else {
                        bma_log("BMA: WARNING: Could not find choice ID for {$field_definition['name']} with value '{$new_value}'", 'warning');
                        continue;
                    }
                }
            } else {
                // For regular fields, just set the value
                $field_value_data['value'] = $new_value;
            }

            // Check if this customField already exists in the booking
            $field_exists = false;
            foreach ($updated_custom_fields as $index => $existing_field) {
                if (isset($existing_field['_id']) && $existing_field['_id'] === $field_definition['_id']) {
                    $updated_custom_fields[$index] = $field_value_data;
                    $field_exists = true;
                    break;
                }
            }

            // If field doesn't exist, add it
            if (!$field_exists) {
                $updated_custom_fields[] = $field_value_data;
            }

            // Remove the simple field from updates
            unset($updates[$internal_name]);
        }

        // Filter out fields with empty values (to clear them)
        $updated_custom_fields = array_filter($updated_custom_fields, function($field) {
            $value = isset($field['value']) ? $field['value'] : (isset($field['values']) ? $field['values'] : null);
            return !empty($value) || $value === 0 || $value === '0';
        });

        // Re-index array
        $updated_custom_fields = array_values($updated_custom_fields);

        // Add customFields to updates
        $updates['customFields'] = $updated_custom_fields;

        return array(
            'success' => true,
            'updates' => $updates
        );
    }

    /**
     * Parse GROUP/EXCLUDE field value into structured arrays
     *
     * Format: G#{group_id},#{booking_id},NOT-#{booking_id}
     * Example: "G#5678,#12345,NOT-#12346"
     *
     * @param string $value The GROUP/EXCLUDE field value
     * @return array Parsed data: ['groups' => [], 'individuals' => [], 'excludes' => []]
     */
    private function parse_group_exclude_field($value) {
        $result = array(
            'groups' => array(),
            'individuals' => array(),
            'excludes' => array()
        );

        if (empty($value) || !is_string($value)) {
            return $result;
        }

        // Split by comma and process each entry
        $entries = array_map('trim', explode(',', $value));

        foreach ($entries as $entry) {
            if (empty($entry)) {
                continue;
            }

            // Check for exclusion: NOT-#12345
            if (stripos($entry, 'NOT-#') === 0) {
                $booking_id = substr($entry, 5); // Remove "NOT-#"
                if (!empty($booking_id)) {
                    $result['excludes'][] = $booking_id;
                }
            }
            // Check for group: G#5678
            elseif (stripos($entry, 'G#') === 0) {
                $group_id = substr($entry, 2); // Remove "G#"
                if (!empty($group_id)) {
                    $result['groups'][] = $group_id;
                }
            }
            // Check for individual: #12345
            elseif (strpos($entry, '#') === 0) {
                $booking_id = substr($entry, 1); // Remove "#"
                if (!empty($booking_id)) {
                    $result['individuals'][] = $booking_id;
                }
            }
        }

        return $result;
    }

    /**
     * Build GROUP/EXCLUDE field value from structured arrays
     *
     * @param array $groups Array of group IDs
     * @param array $individuals Array of individual booking IDs
     * @param array $excludes Array of excluded booking IDs
     * @return string Formatted field value
     */
    public function build_group_exclude_value($groups = array(), $individuals = array(), $excludes = array()) {
        $entries = array();

        // Add group entries: G#5678
        if (is_array($groups)) {
            foreach ($groups as $group_id) {
                if (!empty($group_id)) {
                    $entries[] = 'G#' . $group_id;
                }
            }
        }

        // Add individual booking entries: #12345
        if (is_array($individuals)) {
            foreach ($individuals as $booking_id) {
                if (!empty($booking_id)) {
                    $entries[] = '#' . $booking_id;
                }
            }
        }

        // Add exclusion entries: NOT-#12345
        if (is_array($excludes)) {
            foreach ($excludes as $booking_id) {
                if (!empty($booking_id)) {
                    $entries[] = 'NOT-#' . $booking_id;
                }
            }
        }

        return implode(',', $entries);
    }

    /**
     * Get and parse GROUP/EXCLUDE field data from a Resos booking
     *
     * @param array $resos_booking Resos booking data
     * @return array Parsed data: ['groups' => [], 'individuals' => [], 'excludes' => [], 'raw' => '']
     */
    public function get_group_exclude_data($resos_booking) {
        $custom_fields = isset($resos_booking['customFields']) ? $resos_booking['customFields'] : array();

        $field_value = '';
        foreach ($custom_fields as $field) {
            if (isset($field['name']) && $field['name'] === 'GROUP/EXCLUDE') {
                $field_value = isset($field['value']) ? $field['value'] : '';
                break;
            }
        }

        $parsed = $this->parse_group_exclude_field($field_value);
        $parsed['raw'] = $field_value;

        return $parsed;
    }

    /**
     * Format phone number for Resos API (requires + and country code)
     */
    private function format_phone_for_resos($phone) {
        if (empty($phone)) {
            return '';
        }

        $phone = trim($phone);

        // If already has + prefix, assume it's properly formatted
        if (strpos($phone, '+') === 0) {
            return $phone;
        }

        // Strip all non-digit characters
        $digits = preg_replace('/\D/', '', $phone);

        if (empty($digits)) {
            return '';
        }

        // If starts with 0 (UK local format), strip the leading 0
        if (strpos($digits, '0') === 0) {
            $digits = substr($digits, 1);
        }

        // Add UK country code (+44) as default
        return '+44' . $digits;
    }

    /**
     * Create a new Resos booking
     *
     * @param array $booking_data Booking details
     * @return array Success/error response
     */
    public function create_resos_booking($booking_data) {
        // Validate required parameters
        if (empty($booking_data['date']) || empty($booking_data['time'])) {
            return array(
                'success' => false,
                'message' => 'Date and time are required'
            );
        }

        if (empty($booking_data['guest_name'])) {
            return array(
                'success' => false,
                'message' => 'Guest name is required'
            );
        }

        // Get Resos API key
        // Get Resos API key (check new option first, fallback to old)
        $resos_api_key = get_option('bma_resos_api_key') ?: get_option('hotel_booking_resos_api_key');
        if (empty($resos_api_key)) {
            return array(
                'success' => false,
                'message' => 'Resos API key not configured'
            );
        }

        // Extract and set defaults
        $date = $booking_data['date'];
        $time = $booking_data['time'];
        $people = isset($booking_data['people']) ? intval($booking_data['people']) : 2;
        $guest_name = $booking_data['guest_name'];
        $guest_phone = isset($booking_data['guest_phone']) ? $booking_data['guest_phone'] : '';
        $guest_email = isset($booking_data['guest_email']) ? $booking_data['guest_email'] : '';
        $notification_sms = isset($booking_data['notification_sms']) && $booking_data['notification_sms'];
        $notification_email = isset($booking_data['notification_email']) && $booking_data['notification_email'];
        $referrer = isset($booking_data['referrer']) ? $booking_data['referrer'] : '';
        $language_code = isset($booking_data['language_code']) ? $booking_data['language_code'] : 'en';
        $opening_hour_id = isset($booking_data['opening_hour_id']) ? $booking_data['opening_hour_id'] : '';
        $booking_note = isset($booking_data['booking_note']) ? $booking_data['booking_note'] : '';

        // Custom field values
        $hotel_booking_ref = isset($booking_data['booking_ref']) ? $booking_data['booking_ref'] : '';
        $is_hotel_guest = isset($booking_data['hotel_guest']) ? $booking_data['hotel_guest'] : '';
        $has_dbb = isset($booking_data['dbb']) ? $booking_data['dbb'] : '';
        $dietary_requirements = isset($booking_data['dietary_requirements']) ? $booking_data['dietary_requirements'] : '';
        $dietary_other = isset($booking_data['dietary_other']) ? $booking_data['dietary_other'] : '';

        // Format phone for Resos API
        if (!empty($guest_phone)) {
            $guest_phone = $this->format_phone_for_resos($guest_phone);
        }

        // Build the base booking data
        $resos_booking_data = array(
            'date' => $date,
            'time' => $time,
            'people' => $people,
            'guest' => array(
                'name' => $guest_name,
                'phone' => $guest_phone,
                'email' => $guest_email,
                'notificationSms' => $notification_sms,
                'notificationEmail' => $notification_email
            ),
            'source' => 'api',
            'status' => 'approved',
            'languageCode' => $language_code
        );

        // Add optional fields
        if (!empty($referrer)) {
            $resos_booking_data['referrer'] = $referrer;
        }

        if (!empty($opening_hour_id)) {
            $resos_booking_data['openingHourId'] = $opening_hour_id;
        }

        // Handle customFields if any are provided
        $needs_custom_fields = !empty($hotel_booking_ref) || !empty($is_hotel_guest) ||
                               !empty($has_dbb) || !empty($dietary_requirements) ||
                               !empty($dietary_other);

        if ($needs_custom_fields) {
            bma_log('BMA: Custom fields detected for create, fetching definitions...', 'debug');

            // Fetch customField definitions from Resos
            $custom_fields_url = 'https://api.resos.com/v1/customFields';
            $cf_args = array(
                'method' => 'GET',
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode($resos_api_key . ':'),
                    'Accept' => 'application/json'
                )
            );

            $cf_response = wp_remote_get($custom_fields_url, $cf_args);
            if (is_wp_error($cf_response)) {
                return array(
                    'success' => false,
                    'message' => 'Failed to fetch customField definitions: ' . $cf_response->get_error_message()
                );
            }

            $cf_code = wp_remote_retrieve_response_code($cf_response);
            if ($cf_code !== 200) {
                return array(
                    'success' => false,
                    'message' => 'Failed to fetch customField definitions. Status: ' . $cf_code
                );
            }

            $custom_field_definitions = json_decode(wp_remote_retrieve_body($cf_response), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return array(
                    'success' => false,
                    'message' => 'Failed to parse customField definitions'
                );
            }

            // Map of internal field names to Resos customField names
            $custom_field_map = array(
                'booking_ref' => 'Booking #',
                'hotel_guest' => 'Hotel Guest',
                'dbb' => 'DBB',
                'dietary_requirements' => ' Dietary Requirements',  // Note: has leading space in Resos!
                'dietary_other' => 'Other Dietary Requirements'
            );

            // Process each custom field
            $custom_fields_to_add = array();
            $field_values = array(
                'booking_ref' => $hotel_booking_ref,
                'hotel_guest' => $is_hotel_guest,
                'dbb' => $has_dbb,
                'dietary_requirements' => $dietary_requirements,
                'dietary_other' => $dietary_other
            );

            foreach ($field_values as $internal_name => $value) {
                if (empty($value)) {
                    continue; // Skip empty values
                }

                $resos_name = $custom_field_map[$internal_name];

                // Find the customField definition
                $field_definition = null;
                foreach ($custom_field_definitions as $def) {
                    if ($def['name'] === $resos_name) {
                        $field_definition = $def;
                        break;
                    }
                }

                if (!$field_definition) {
                    bma_log("BMA: WARNING: Could not find customField definition for '{$resos_name}'", 'warning');
                    continue;
                }

                // Determine if this is a multiple choice field
                $field_type = isset($field_definition['type']) ? $field_definition['type'] : '';
                $is_multiple_choice = in_array($field_type, array('radio', 'dropdown', 'checkbox'));

                // Prepare the field value structure
                $field_value_data = array(
                    '_id' => $field_definition['_id'],
                    'name' => $field_definition['name']
                );

                // Special handling for multiselect checkbox fields (dietary requirements)
                if ($internal_name === 'dietary_requirements' && $field_type === 'checkbox') {
                    // Split comma-separated choice IDs
                    $selected_ids = array_filter(array_map('trim', explode(',', $value)));
                    $choice_objects = array();

                    if (isset($field_definition['multipleChoiceSelections']) && is_array($field_definition['multipleChoiceSelections'])) {
                        foreach ($selected_ids as $selected_id) {
                            // Match by choice ID
                            foreach ($field_definition['multipleChoiceSelections'] as $choice) {
                                if (isset($choice['_id']) && $choice['_id'] === $selected_id) {
                                    $choice_objects[] = array(
                                        '_id' => $choice['_id'],
                                        'name' => $choice['name'],
                                        'value' => true
                                    );
                                    break;
                                }
                            }
                        }
                    }

                    if (!empty($choice_objects)) {
                        $field_value_data['value'] = $choice_objects;  // Array of objects for multiselect
                    } else {
                        bma_log("BMA: WARNING: No valid choices found for dietary requirements", 'warning');
                        continue;
                    }
                } elseif ($is_multiple_choice) {
                    // For single choice fields, find the choice ID
                    $choice_id = null;
                    if (isset($field_definition['multipleChoiceSelections']) && is_array($field_definition['multipleChoiceSelections'])) {
                        foreach ($field_definition['multipleChoiceSelections'] as $choice) {
                            if (isset($choice['name']) && $choice['name'] === $value) {
                                $choice_id = $choice['_id'];
                                break;
                            }
                        }
                    }

                    if ($choice_id) {
                        $field_value_data['value'] = $choice_id;
                        $field_value_data['multipleChoiceValueName'] = $value;
                    } else {
                        bma_log("BMA: WARNING: Could not find choice ID for {$resos_name} with value '{$value}'", 'warning');
                        continue;
                    }
                } else {
                    // For regular text fields, just set the value
                    $field_value_data['value'] = $value;
                }

                $custom_fields_to_add[] = $field_value_data;
            }

            // Add customFields to booking data if we have any
            if (!empty($custom_fields_to_add)) {
                $resos_booking_data['customFields'] = $custom_fields_to_add;
            }
        }

        // Make POST request to create booking
        $url = 'https://api.resos.com/v1/bookings';
        $request_body = json_encode($resos_booking_data);
        $args = array(
            'method' => 'POST',
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($resos_api_key . ':'),
                'Content-Type' => 'application/json'
            ),
            'body' => $request_body
        );

        bma_log('BMA: Create Resos Booking - POST ' . $url, 'debug');
        bma_log('BMA: Request Body: ' . $request_body, 'debug');

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Resos API request failed: ' . $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        bma_log('BMA: Response Code: ' . $response_code, 'debug');
        bma_log('BMA: Response Body: ' . $response_body, 'debug');

        if ($response_code !== 200 && $response_code !== 201) {
            return array(
                'success' => false,
                'message' => 'Resos API returned error code: ' . $response_code,
                'response_code' => $response_code,
                'response_body' => $response_body
            );
        }

        $data = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'message' => 'Failed to parse Resos API response: ' . json_last_error_msg()
            );
        }

        bma_log('BMA: Create Booking SUCCESS - ' . $guest_name . ' on ' . $date . ' at ' . $time, 'debug');

        // Extract booking ID from response
        $booking_id = '';
        if (is_string($data)) {
            $booking_id = $data;
        } elseif (is_array($data) && isset($data['_id'])) {
            $booking_id = $data['_id'];
        }

        // If there's a booking note, add it via separate endpoint
        if (!empty($booking_note) && !empty($booking_id)) {
            bma_log('BMA: Adding booking note to ' . $booking_id, 'debug');

            $note_url = 'https://api.resos.com/v1/bookings/' . urlencode($booking_id) . '/restaurantNote';
            $note_data = array('text' => $booking_note);
            $note_request_body = json_encode($note_data);

            $note_args = array(
                'method' => 'POST',
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode($resos_api_key . ':'),
                    'Content-Type' => 'application/json'
                ),
                'body' => $note_request_body
            );

            $note_response = wp_remote_request($note_url, $note_args);

            if (is_wp_error($note_response)) {
                bma_log('BMA: WARNING: Failed to add note: ' . $note_response->get_error_message(), 'warning');
                // Don't fail the entire booking if note fails
            } else {
                $note_response_code = wp_remote_retrieve_response_code($note_response);
                if ($note_response_code === 200 || $note_response_code === 201) {
                    bma_log('BMA: Note added successfully', 'debug');
                } else {
                    bma_log('BMA: WARNING: Failed to add note. Status: ' . $note_response_code, 'warning');
                }
            }
        }

        // Update GROUP/EXCLUDE custom field if group members were provided
        $group_members = isset($booking_data['group_members']) ? $booking_data['group_members'] : '';
        $lead_booking_id = isset($booking_data['lead_booking_id']) ? $booking_data['lead_booking_id'] : '';

        if (!empty($group_members) && !empty($booking_id)) {
            bma_log('BMA: Updating GROUP/EXCLUDE field for new booking ' . $booking_id, 'debug');

            // Parse group_members string into array of individual booking IDs
            $individual_ids = array_filter(array_map('trim', explode(',', $group_members)));

            bma_log('BMA: Parsed individual IDs: ' . json_encode($individual_ids), 'debug');

            // Use build_group_exclude_value to format correctly (adds # prefix)
            $group_field_value = $this->build_group_exclude_value(array(), $individual_ids, array());

            bma_log('BMA: GROUP/EXCLUDE field value: ' . $group_field_value, 'debug');

            // Update the custom field using update_resos_booking
            $update_result = $this->update_resos_booking($booking_id, array(
                'group_exclude' => $group_field_value
            ));

            if ($update_result['success']) {
                bma_log('BMA: GROUP/EXCLUDE field updated successfully for booking ' . $booking_id, 'info');
            } else {
                bma_log('BMA: WARNING: Failed to update GROUP/EXCLUDE field: ' . $update_result['message'], 'warning');
                // Don't fail the entire booking if GROUP field update fails
            }
        }

        return array(
            'success' => true,
            'message' => 'Booking created successfully',
            'booking_id' => $booking_id,
            'booking_data' => $data
        );
    }

    /**
     * Fetch opening hours from Resos API with caching
     *
     * @param string|null $date Optional date in YYYY-MM-DD format
     * @return array Opening hours data or empty array on failure
     */
    public function fetch_opening_hours( $date = null ) {
        // First, get all opening hours (cached separately)
        $all_hours = $this->get_all_opening_hours();

        if ( empty( $all_hours ) ) {
            return array();
        }

        // If no date specified, return all hours
        if ( ! $date ) {
            return $all_hours;
        }

        // Filter hours for specific date
        return $this->filter_opening_hours_for_date( $all_hours, $date );
    }

    /**
     * Get all opening hours from Resos API (cached)
     *
     * @return array All opening hours or empty array on failure
     */
    private function get_all_opening_hours() {
        $cache_key = 'bma_opening_hours_all';
        $cached = get_transient( $cache_key );

        if ( $cached !== false ) {
            bma_log( 'BMA: Returning cached opening hours (all)', 'debug' );
            return $cached;
        }

        // Get Resos API key (check new option first, fallback to old)
        $resos_api_key = get_option('bma_resos_api_key') ?: get_option('hotel_booking_resos_api_key');
        if ( empty( $resos_api_key ) ) {
            bma_log( 'BMA: Resos API key not configured', 'error' );
            return array();
        }

        $url = 'https://api.resos.com/v1/openingHours?showDeleted=false&onlySpecial=false&type=restaurant';

        $args = array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $resos_api_key . ':' ),
                'Content-Type' => 'application/json',
            ),
        );

        bma_log( 'BMA: Fetching all opening hours from Resos API: ' . $url, 'debug' );
        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            bma_log( 'BMA: Opening hours fetch error: ' . $response->get_error_message(), 'error' );
            return array();
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            bma_log( 'BMA: Opening hours fetch failed with status: ' . $response_code, 'error' );
            return array();
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! is_array( $data ) ) {
            bma_log( 'BMA: Invalid opening hours response format', 'error' );
            return array();
        }

        // Cache for 1 hour
        set_transient( $cache_key, $data, HOUR_IN_SECONDS );
        bma_log( 'BMA: Cached ' . count( $data ) . ' opening hours entries', 'debug' );

        return $data;
    }

    /**
     * Filter opening hours for a specific date
     *
     * @param array $all_hours All opening hours from Resos
     * @param string $date Date in YYYY-MM-DD format
     * @return array Filtered opening hours for the date
     */
    private function filter_opening_hours_for_date( $all_hours, $date ) {
        // Get day of week (1=Monday through 7=Sunday, matching Resos format)
        $day_of_week = date( 'N', strtotime( $date ) );
        $target_date = date( 'Y-m-d', strtotime( $date ) );

        // First, check for special date overrides
        $special_hours = array();
        foreach ( $all_hours as $hours ) {
            $is_special = isset( $hours['special'] ) && $hours['special'] === true;

            if ( $is_special && isset( $hours['date'] ) ) {
                $event_date = date( 'Y-m-d', strtotime( $hours['date'] ) );

                if ( $event_date === $target_date ) {
                    // Check if it's an OPEN special event (not a closure)
                    $is_open = isset( $hours['isOpen'] ) && ! empty( $hours['isOpen'] );

                    if ( $is_open && isset( $hours['open'] ) && isset( $hours['close'] ) ) {
                        $special_hours[] = $hours;
                    }
                }
            }
        }

        // If special hours found for this date, return those
        if ( ! empty( $special_hours ) ) {
            usort( $special_hours, function( $a, $b ) {
                return intval( $a['open'] ) - intval( $b['open'] );
            });
            bma_log( 'BMA: Found ' . count( $special_hours ) . ' special opening hours for ' . $date, 'debug' );
            return $special_hours;
        }

        // No special hours, filter by day of week
        $day_hours = array();
        foreach ( $all_hours as $hours ) {
            // Skip special events - only want regular recurring hours
            $is_special = isset( $hours['special'] ) && $hours['special'] === true;
            if ( $is_special ) {
                continue;
            }

            // Match day of week
            if ( isset( $hours['day'] ) && intval( $hours['day'] ) === intval( $day_of_week ) ) {
                $day_hours[] = $hours;
            }
        }

        // Sort by opening time
        if ( ! empty( $day_hours ) ) {
            usort( $day_hours, function( $a, $b ) {
                return intval( $a['open'] ) - intval( $b['open'] );
            });
            bma_log( 'BMA: Found ' . count( $day_hours ) . ' regular opening hours for ' . $date . ' (day ' . $day_of_week . ')', 'debug' );
        } else {
            bma_log( 'BMA: No opening hours found for ' . $date . ' (day ' . $day_of_week . ')', 'debug' );
        }

        return $day_hours;
    }

    /**
     * Fetch available times from Resos API
     *
     * @param string $date Date in YYYY-MM-DD format
     * @param int $people Number of people
     * @param string|null $area_id Optional area/table ID filter
     * @return array Response with success status, times array, and periods
     */
    public function fetch_available_times( $date, $people, $area_id = null ) {
        if ( empty( $date ) || empty( $people ) ) {
            return array(
                'success' => false,
                'message' => 'Date and people are required',
                'times' => array(),
                'periods' => array(),
            );
        }

        // Get Resos API key (check new option first, fallback to old)
        $resos_api_key = get_option('bma_resos_api_key') ?: get_option('hotel_booking_resos_api_key');
        if ( empty( $resos_api_key ) ) {
            return array(
                'success' => false,
                'message' => 'Resos API key not configured',
                'times' => array(),
                'periods' => array(),
            );
        }

        // Use bookingFlow/times endpoint (NOT openingHours/{date})
        $url = 'https://api.resos.com/v1/bookingFlow/times';
        $url .= '?date=' . urlencode( $date );
        $url .= '&people=' . intval( $people );

        if ( $area_id ) {
            $url .= '&areaId=' . urlencode( $area_id );
        }

        $args = array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $resos_api_key . ':' ),
                'Content-Type' => 'application/json',
            ),
        );

        bma_log( 'BMA: Fetching available times from Resos API: ' . $url, 'debug' );
        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            bma_log( 'BMA: Available times fetch error: ' . $response->get_error_message(), 'error' );
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
                'times' => array(),
                'periods' => array(),
            );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            bma_log( 'BMA: Available times fetch failed with status: ' . $response_code, 'error' );
            return array(
                'success' => false,
                'message' => 'API request failed with status: ' . $response_code,
                'times' => array(),
                'periods' => array(),
            );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! is_array( $data ) ) {
            bma_log( 'BMA: Invalid available times response format', 'error' );
            return array(
                'success' => false,
                'message' => 'Invalid response format',
                'times' => array(),
                'periods' => array(),
            );
        }

        // Extract all available times from all opening hour periods
        $all_available_times = array();
        foreach ( $data as $opening_hour ) {
            if ( isset( $opening_hour['availableTimes'] ) && is_array( $opening_hour['availableTimes'] ) ) {
                $all_available_times = array_merge( $all_available_times, $opening_hour['availableTimes'] );
            }
        }

        return array(
            'success' => true,
            'times' => $all_available_times,
            'periods' => $data,
        );
    }

    /**
     * Fetch special events from Resos API with caching
     *
     * @param string $date Date in YYYY-MM-DD format
     * @return array Special events data or empty array on failure
     */
    public function fetch_special_events( $date ) {
        if ( empty( $date ) ) {
            return array();
        }

        $cache_key = 'bma_special_events_' . $date;
        $cached = get_transient( $cache_key );

        if ( $cached !== false ) {
            bma_log( 'BMA: Returning cached special events for: ' . $date, 'debug' );
            return $cached;
        }

        // Get ALL opening hours (includes special events)
        $all_hours = $this->get_all_opening_hours();

        if ( empty( $all_hours ) ) {
            bma_log( 'BMA: No opening hours data available for filtering special events', 'debug' );
            return array();
        }

        // Filter for special events matching this date
        $special_events = array();
        $target_date = date( 'Y-m-d', strtotime( $date ) );

        foreach ( $all_hours as $hours ) {
            // Only process special events
            if ( ! isset( $hours['special'] ) || $hours['special'] !== true ) {
                continue;
            }

            // Check if this special event matches the requested date
            if ( isset( $hours['date'] ) ) {
                $event_date = date( 'Y-m-d', strtotime( $hours['date'] ) );

                if ( $event_date === $target_date ) {
                    $special_events[] = array(
                        'name'   => isset( $hours['name'] ) ? $hours['name'] : '',
                        'isOpen' => isset( $hours['isOpen'] ) ? $hours['isOpen'] : false,
                        'open'   => isset( $hours['open'] ) ? $hours['open'] : null,
                        'close'  => isset( $hours['close'] ) ? $hours['close'] : null,
                    );
                }
            }
        }

        bma_log( 'BMA: Found ' . count( $special_events ) . ' special event(s) for date: ' . $date, 'debug' );

        // Cache for 30 minutes
        set_transient( $cache_key, $special_events, 30 * MINUTE_IN_SECONDS );
        bma_log( 'BMA: Cached ' . count( $special_events ) . ' special event(s) for: ' . $date, 'debug' );

        return $special_events;
    }

    /**
     * Fetch dietary choices custom field with caching
     *
     * @return array Array of dietary choice objects
     */
    public function fetch_dietary_choices() {
        $cache_key = 'bma_dietary_choices';
        $cached = get_transient( $cache_key );

        if ( $cached !== false ) {
            bma_log( 'BMA: Returning cached dietary choices', 'debug' );
            return $cached;
        }

        // Get Resos API key (check new option first, fallback to old)
        $resos_api_key = get_option('bma_resos_api_key') ?: get_option('hotel_booking_resos_api_key');
        if ( empty( $resos_api_key ) ) {
            bma_log( 'BMA: Resos API key not configured', 'error' );
            return array();
        }

        $url = 'https://api.resos.com/v1/customFields';

        $args = array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $resos_api_key . ':' ),
                'Content-Type' => 'application/json',
            ),
        );

        bma_log( 'BMA: Fetching custom fields from Resos API', 'debug' );
        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            bma_log( 'BMA: Custom fields fetch error: ' . $response->get_error_message(), 'error' );
            return array();
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            bma_log( 'BMA: Custom fields fetch failed with status: ' . $response_code, 'error' );
            return array();
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! is_array( $data ) ) {
            bma_log( 'BMA: Invalid custom fields response format', 'error' );
            return array();
        }

        // Find the " Dietary Requirements" field (note leading space!)
        $dietary_field = null;
        foreach ( $data as $field ) {
            if ( isset( $field['name'] ) && $field['name'] === ' Dietary Requirements' ) {
                $dietary_field = $field;
                break;
            }
        }

        $choices = array();
        if ( $dietary_field && isset( $dietary_field['multipleChoiceSelections'] ) && is_array( $dietary_field['multipleChoiceSelections'] ) ) {
            $choices = $dietary_field['multipleChoiceSelections'];
        }

        // Cache for 24 hours
        set_transient( $cache_key, $choices, DAY_IN_SECONDS );
        bma_log( 'BMA: Cached ' . count( $choices ) . ' dietary choices', 'debug' );

        return $choices;
    }

    /**
     * Check if online booking is available for a specific date
     *
     * @param string $from_date From date in YYYY-MM-DD format
     * @param string $to_date To date in YYYY-MM-DD format (exclusive)
     * @return bool True if online booking is available, false otherwise
     */
    public function check_online_booking_available( $from_date, $to_date ) {
        // Get Resos API key (check new option first, fallback to old)
        $resos_api_key = get_option('bma_resos_api_key') ?: get_option('hotel_booking_resos_api_key');

        if ( empty( $resos_api_key ) ) {
            return false;
        }

        // Build URL for bookingFlow/dates endpoint
        $url = 'https://api.resos.com/v1/bookingFlow/dates';
        $url .= '?fromDate=' . urlencode( $from_date );
        $url .= '&toDate=' . urlencode( $to_date );

        $args = array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $resos_api_key . ':' ),
                'Content-Type' => 'application/json',
            ),
        );

        bma_log( 'BMA: Checking online booking availability for ' . $from_date, 'debug' );
        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            bma_log( 'BMA: Online booking check failed: ' . $response->get_error_message(), 'error' );
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            bma_log( 'BMA: Online booking check failed with status: ' . $response_code, 'error' );
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! is_array( $data ) ) {
            bma_log( 'BMA: Invalid online booking availability response format', 'error' );
            return false;
        }

        // Check if the from_date is in the available dates array
        $is_available = in_array( $from_date, $data, true );
        bma_log( 'BMA: Online booking available for ' . $from_date . ': ' . ( $is_available ? 'yes' : 'no' ), 'debug' );

        return $is_available;
    }
}
