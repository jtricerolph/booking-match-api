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
        $resos_api_key = get_option('hotel_booking_resos_api_key');
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
            'hotel_guest' => 'Hotel Guest'
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
            error_log('BMA: Transformed guest fields to nested structure: ' . json_encode($guest_data));
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

        error_log('BMA: Update Resos Booking - PUT ' . $url);
        error_log('BMA: Request Body: ' . $request_body);

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Resos API request failed: ' . $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        error_log('BMA: Response Code: ' . $response_code);
        error_log('BMA: Response Body: ' . $response_body);

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
     * Adds a "NOT-#{hotel_booking_id}" note to the Resos booking
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
        $resos_api_key = get_option('hotel_booking_resos_api_key');
        if (empty($resos_api_key)) {
            return array(
                'success' => false,
                'message' => 'Resos API key not configured'
            );
        }

        // Use the dedicated restaurant note endpoint
        $note_url = 'https://api.resos.com/v1/bookings/' . urlencode($resos_booking_id) . '/restaurantNote';

        // Prepare the note request body
        $note_text = 'NOT-#' . $hotel_booking_id;
        $request_body = json_encode(array('text' => $note_text));

        $args = array(
            'method' => 'POST',
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($resos_api_key . ':'),
                'Content-Type' => 'application/json'
            ),
            'body' => $request_body
        );

        error_log('BMA: Exclude Match - Adding note "' . $note_text . '" to Resos booking ' . $resos_booking_id);

        $response = wp_remote_post($note_url, $args);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Failed to add note to Resos booking: ' . $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        // Note endpoint typically returns 201 Created for successful POST
        if ($response_code !== 200 && $response_code !== 201) {
            return array(
                'success' => false,
                'message' => 'Failed to add note to Resos booking. Status: ' . $response_code,
                'response_code' => $response_code,
                'response_body' => $response_body
            );
        }

        error_log('BMA: Exclude Match SUCCESS - Added NOT-#' . $hotel_booking_id . ' to Resos booking ' . $resos_booking_id);

        return array(
            'success' => true,
            'message' => 'Match excluded successfully',
            'resos_booking_id' => $resos_booking_id,
            'hotel_booking_id' => $hotel_booking_id,
            'exclusion_note' => 'NOT-#' . $hotel_booking_id
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
                error_log("BMA: WARNING: Could not find customField definition for '$resos_name'");
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
                        error_log("BMA: WARNING: No valid choices found for checkbox field {$field_definition['name']}");
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
                        error_log("BMA: WARNING: Could not find choice ID for {$field_definition['name']} with value '{$new_value}'");
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
        $resos_api_key = get_option('hotel_booking_resos_api_key');
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
            error_log('BMA: Custom fields detected for create, fetching definitions...');

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
                    error_log("BMA: WARNING: Could not find customField definition for '{$resos_name}'");
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
                        error_log("BMA: WARNING: No valid choices found for dietary requirements");
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
                        error_log("BMA: WARNING: Could not find choice ID for {$resos_name} with value '{$value}'");
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

        error_log('BMA: Create Resos Booking - POST ' . $url);
        error_log('BMA: Request Body: ' . $request_body);

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Resos API request failed: ' . $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        error_log('BMA: Response Code: ' . $response_code);
        error_log('BMA: Response Body: ' . $response_body);

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

        error_log('BMA: Create Booking SUCCESS - ' . $guest_name . ' on ' . $date . ' at ' . $time);

        // Extract booking ID from response
        $booking_id = '';
        if (is_string($data)) {
            $booking_id = $data;
        } elseif (is_array($data) && isset($data['_id'])) {
            $booking_id = $data['_id'];
        }

        // If there's a booking note, add it via separate endpoint
        if (!empty($booking_note) && !empty($booking_id)) {
            error_log('BMA: Adding booking note to ' . $booking_id);

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
                error_log('BMA: WARNING: Failed to add note: ' . $note_response->get_error_message());
                // Don't fail the entire booking if note fails
            } else {
                $note_response_code = wp_remote_retrieve_response_code($note_response);
                if ($note_response_code === 200 || $note_response_code === 201) {
                    error_log('BMA: Note added successfully');
                } else {
                    error_log('BMA: WARNING: Failed to add note. Status: ' . $note_response_code);
                }
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
            error_log( 'BMA: Returning cached opening hours (all)' );
            return $cached;
        }

        $resos_api_key = get_option( 'hotel_booking_resos_api_key' );
        if ( empty( $resos_api_key ) ) {
            error_log( 'BMA: Resos API key not configured' );
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

        error_log( 'BMA: Fetching all opening hours from Resos API: ' . $url );
        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            error_log( 'BMA: Opening hours fetch error: ' . $response->get_error_message() );
            return array();
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            error_log( 'BMA: Opening hours fetch failed with status: ' . $response_code );
            return array();
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! is_array( $data ) ) {
            error_log( 'BMA: Invalid opening hours response format' );
            return array();
        }

        // Cache for 1 hour
        set_transient( $cache_key, $data, HOUR_IN_SECONDS );
        error_log( 'BMA: Cached ' . count( $data ) . ' opening hours entries' );

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
            error_log( 'BMA: Found ' . count( $special_hours ) . ' special opening hours for ' . $date );
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
            error_log( 'BMA: Found ' . count( $day_hours ) . ' regular opening hours for ' . $date . ' (day ' . $day_of_week . ')' );
        } else {
            error_log( 'BMA: No opening hours found for ' . $date . ' (day ' . $day_of_week . ')' );
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

        $resos_api_key = get_option( 'hotel_booking_resos_api_key' );
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

        error_log( 'BMA: Fetching available times from Resos API: ' . $url );
        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            error_log( 'BMA: Available times fetch error: ' . $response->get_error_message() );
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
                'times' => array(),
                'periods' => array(),
            );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            error_log( 'BMA: Available times fetch failed with status: ' . $response_code );
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
            error_log( 'BMA: Invalid available times response format' );
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
            error_log( 'BMA: Returning cached special events for: ' . $date );
            return $cached;
        }

        $resos_api_key = get_option( 'hotel_booking_resos_api_key' );
        if ( empty( $resos_api_key ) ) {
            error_log( 'BMA: Resos API key not configured' );
            return array();
        }

        $url = 'https://api.resos.com/v1/specialEvents?date=' . urlencode( $date );

        $args = array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $resos_api_key . ':' ),
                'Content-Type' => 'application/json',
            ),
        );

        error_log( 'BMA: Fetching special events from Resos API: ' . $url );
        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            error_log( 'BMA: Special events fetch error: ' . $response->get_error_message() );
            return array();
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            error_log( 'BMA: Special events fetch failed with status: ' . $response_code );
            return array();
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! is_array( $data ) ) {
            error_log( 'BMA: Invalid special events response format' );
            return array();
        }

        // Cache for 30 minutes
        set_transient( $cache_key, $data, 30 * MINUTE_IN_SECONDS );
        error_log( 'BMA: Cached special events for: ' . $date );

        return $data;
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
            error_log( 'BMA: Returning cached dietary choices' );
            return $cached;
        }

        $resos_api_key = get_option( 'hotel_booking_resos_api_key' );
        if ( empty( $resos_api_key ) ) {
            error_log( 'BMA: Resos API key not configured' );
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

        error_log( 'BMA: Fetching custom fields from Resos API' );
        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            error_log( 'BMA: Custom fields fetch error: ' . $response->get_error_message() );
            return array();
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            error_log( 'BMA: Custom fields fetch failed with status: ' . $response_code );
            return array();
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! is_array( $data ) ) {
            error_log( 'BMA: Invalid custom fields response format' );
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
        error_log( 'BMA: Cached ' . count( $choices ) . ' dietary choices' );

        return $choices;
    }
}
