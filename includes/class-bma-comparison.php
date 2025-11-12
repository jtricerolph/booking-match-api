<?php
/**
 * Booking Comparison Class
 *
 * Handles comparison of NewBook hotel bookings with Resos restaurant bookings
 * Extracted from Reservation Management Integration plugin for reuse
 *
 * @package BookingMatchAPI
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class BMA_Comparison {

    /**
     * Title-case a name properly
     * Converts "john smith" to "John Smith", handles hyphenated names, etc.
     *
     * @param string $name The name to title-case
     * @return string Title-cased name
     */
    private function title_case_name($name) {
        if (empty($name)) {
            return '';
        }

        // Convert to lowercase first, then use mb_convert_case for proper title casing
        $name = mb_strtolower(trim($name), 'UTF-8');

        // Use MB_CASE_TITLE for multibyte-safe title casing
        $name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');

        // Handle special cases like "O'brien" -> "O'Brien", "Mcdonald" -> "McDonald"
        $name = preg_replace_callback("/\b(mc|mac|o')(\w)/i", function($matches) {
            return $matches[1] . strtoupper($matches[2]);
        }, $name);

        return $name;
    }

    /**
     * Prepare comparison data between hotel booking and Resos booking
     *
     * @param array $hotel_booking NewBook booking data
     * @param array $resos_booking Resos booking data
     * @param string $input_date The specific date to compare (YYYY-MM-DD format)
     * @return array Structured comparison data
     */
    public function prepare_comparison_data($hotel_booking, $resos_booking, $input_date) {
        // Extract hotel guest data from guests array with contact_details
        $hotel_guest_name = '';
        $hotel_phone = '';
        $hotel_email = '';
        $hotel_mobile = '';
        $hotel_landline = '';

        if (isset($hotel_booking['guests']) && is_array($hotel_booking['guests'])) {
            foreach ($hotel_booking['guests'] as $guest) {
                if (isset($guest['primary_client']) && $guest['primary_client'] == '1') {
                    // Get name and apply title casing (NewBook returns lowercase)
                    $hotel_guest_name = $this->title_case_name($guest['firstname'] . ' ' . $guest['lastname']);

                    // Extract phone and email from contact_details array, separating mobile and landline
                    if (isset($guest['contact_details']) && is_array($guest['contact_details'])) {
                        foreach ($guest['contact_details'] as $contact) {
                            if (isset($contact['type']) && isset($contact['content'])) {
                                if ($contact['type'] === 'phone') {
                                    // Check if it's a mobile or landline based on label/subtype if available
                                    $contact_label = isset($contact['label']) ? strtolower($contact['label']) : '';
                                    if (strpos($contact_label, 'mobile') !== false || strpos($contact_label, 'cell') !== false) {
                                        if (empty($hotel_mobile)) {
                                            $hotel_mobile = strval($contact['content']);
                                        }
                                    } else {
                                        if (empty($hotel_landline)) {
                                            $hotel_landline = strval($contact['content']);
                                        }
                                    }
                                    // If no specific type found, use as general phone
                                    if (empty($hotel_phone)) {
                                        $hotel_phone = strval($contact['content']);
                                    }
                                } elseif ($contact['type'] === 'email' && empty($hotel_email)) {
                                    $hotel_email = strval($contact['content']);
                                }
                            }
                        }
                    }
                    break;
                }
            }

            // If no primary client found, use first guest
            if (empty($hotel_guest_name) && count($hotel_booking['guests']) > 0) {
                $guest = $hotel_booking['guests'][0];
                $hotel_guest_name = $this->title_case_name($guest['firstname'] . ' ' . $guest['lastname']);

                // Extract phone and email from contact_details array
                if (isset($guest['contact_details']) && is_array($guest['contact_details'])) {
                    foreach ($guest['contact_details'] as $contact) {
                        if (isset($contact['type']) && isset($contact['content'])) {
                            if ($contact['type'] === 'phone') {
                                $contact_label = isset($contact['label']) ? strtolower($contact['label']) : '';
                                if (strpos($contact_label, 'mobile') !== false || strpos($contact_label, 'cell') !== false) {
                                    if (empty($hotel_mobile)) {
                                        $hotel_mobile = strval($contact['content']);
                                    }
                                } else {
                                    if (empty($hotel_landline)) {
                                        $hotel_landline = strval($contact['content']);
                                    }
                                }
                                if (empty($hotel_phone)) {
                                    $hotel_phone = strval($contact['content']);
                                }
                            } elseif ($contact['type'] === 'email' && empty($hotel_email)) {
                                $hotel_email = strval($contact['content']);
                            }
                        }
                    }
                }
            }
        }

        // Prefer mobile over landline for the main phone
        $hotel_preferred_phone = !empty($hotel_mobile) ? $hotel_mobile : (!empty($hotel_landline) ? $hotel_landline : $hotel_phone);

        $hotel_booking_id = isset($hotel_booking['booking_id']) ? strval($hotel_booking['booking_id']) : '';
        $hotel_room = isset($hotel_booking['site_name']) ? strval($hotel_booking['site_name']) : '';
        $hotel_status = isset($hotel_booking['booking_status']) ? strval($hotel_booking['booking_status']) : '';

        // Extract occupancy (people count) from hotel booking - sum adults, children, and infants
        $hotel_adults = isset($hotel_booking['booking_adults']) ? intval($hotel_booking['booking_adults']) : 0;
        $hotel_children = isset($hotel_booking['booking_children']) ? intval($hotel_booking['booking_children']) : 0;
        $hotel_infants = isset($hotel_booking['booking_infants']) ? intval($hotel_booking['booking_infants']) : 0;
        $hotel_people = $hotel_adults + $hotel_children + $hotel_infants;

        // Extract rate type for the input date
        $hotel_rate_type = '-';
        if (isset($hotel_booking['tariffs_quoted']) && is_array($hotel_booking['tariffs_quoted'])) {
            foreach ($hotel_booking['tariffs_quoted'] as $tariff) {
                if (isset($tariff['stay_date']) && $tariff['stay_date'] == $input_date) {
                    $hotel_rate_type = isset($tariff['label']) ? $tariff['label'] : '-';
                    break;
                }
            }
        }

        // Extract Resos custom fields
        $resos_booking_ref = '';
        $resos_hotel_guest = '';
        $resos_dbb = '';

        if (isset($resos_booking['customFields']) && is_array($resos_booking['customFields'])) {
            foreach ($resos_booking['customFields'] as $field) {
                if (isset($field['name'])) {
                    if ($field['name'] === 'Booking #' && isset($field['value'])) {
                        $resos_booking_ref = trim($field['value']);
                    } elseif ($field['name'] === 'Hotel Guest' && isset($field['multipleChoiceValueName'])) {
                        $resos_hotel_guest = $field['multipleChoiceValueName'];
                    } elseif ($field['name'] === 'DBB' && isset($field['multipleChoiceValueName'])) {
                        $resos_dbb = $field['multipleChoiceValueName'];
                    }
                }
            }
        }

        // Extract Resos notes
        $resos_notes = '';
        if (isset($resos_booking['restaurantNotes']) && is_array($resos_booking['restaurantNotes'])) {
            $notes_array = array();
            foreach ($resos_booking['restaurantNotes'] as $note) {
                if (isset($note['restaurantNote'])) {
                    $notes_array[] = $note['restaurantNote'];
                }
            }
            $resos_notes = implode(' ', $notes_array);
        }

        // Extract Resos data
        $resos_booking_id = isset($resos_booking['_id']) ? $resos_booking['_id'] : (isset($resos_booking['id']) ? $resos_booking['id'] : '');
        $resos_restaurant_id = isset($resos_booking['restaurantId']) ? $resos_booking['restaurantId'] : '';
        $resos_guest_name = isset($resos_booking['guest']['name']) ? trim($resos_booking['guest']['name']) : '';
        $resos_phone = isset($resos_booking['guest']['phone']) ? trim($resos_booking['guest']['phone']) : '';
        $resos_email = isset($resos_booking['guest']['email']) ? trim($resos_booking['guest']['email']) : '';
        $resos_people = isset($resos_booking['people']) ? intval($resos_booking['people']) : 0;
        $resos_status = isset($resos_booking['status']) ? $resos_booking['status'] : 'request';

        // Determine which fields match for highlighting
        $matches = array();

        // Check name match
        if (!empty($hotel_guest_name) && !empty($resos_guest_name)) {
            $hotel_surname = $this->extract_surname($hotel_guest_name);
            $resos_surname = $this->extract_surname($resos_guest_name);
            if ($this->normalize_for_matching($hotel_surname) === $this->normalize_for_matching($resos_surname) && strlen($hotel_surname) > 2) {
                $matches['name'] = true;
            }
        }

        // Check phone match
        if (!empty($hotel_phone) && !empty($resos_phone)) {
            $normalized_hotel = $this->normalize_phone_for_matching($hotel_phone);
            $normalized_resos = $this->normalize_phone_for_matching($resos_phone);

            if (strlen($normalized_hotel) >= 8 && strlen($normalized_resos) >= 8) {
                $hotel_last_8 = substr($normalized_hotel, -8);
                $resos_last_8 = substr($normalized_resos, -8);
                if ($hotel_last_8 === $resos_last_8) {
                    $matches['phone'] = true;
                }
            }
        }

        // Check email match
        if (!empty($hotel_email) && !empty($resos_email)) {
            if ($this->normalize_for_matching($hotel_email) === $this->normalize_for_matching($resos_email)) {
                $matches['email'] = true;
            }
        }

        // Check booking reference match
        if (!empty($hotel_booking_id) && !empty($resos_booking_ref)) {
            if ($hotel_booking_id == $resos_booking_ref) {
                $matches['booking_ref'] = true;
            }
        }

        // Check notes match - look for room number or booking ID in notes
        if (!empty($resos_notes) && (!empty($hotel_room) || !empty($hotel_booking_id))) {
            $notes_normalized = strtolower($resos_notes);
            $room_found = !empty($hotel_room) && stripos($notes_normalized, strtolower($hotel_room)) !== false;
            $booking_found = !empty($hotel_booking_id) && stripos($notes_normalized, $hotel_booking_id) !== false;

            if ($room_found || $booking_found) {
                $matches['notes'] = true;
            }
        }

        // Check people match
        if ($hotel_people > 0 && $resos_people > 0 && $hotel_people == $resos_people) {
            $matches['people'] = true;
        }

        // Check for package inventory item on this date
        $hotel_has_package = false;
        $package_inventory_name = get_option('bma_package_inventory_name', '');

        error_log("BMA DEBUG: Package detection - input_date: '{$input_date}', package_inventory_name option: '{$package_inventory_name}'");
        error_log("BMA DEBUG: Has inventory_items: " . (isset($hotel_booking['inventory_items']) ? 'YES' : 'NO') . ", Count: " . (isset($hotel_booking['inventory_items']) ? count($hotel_booking['inventory_items']) : 0));

        if (!empty($package_inventory_name) && isset($hotel_booking['inventory_items']) && is_array($hotel_booking['inventory_items'])) {
            foreach ($hotel_booking['inventory_items'] as $item) {
                error_log("BMA DEBUG: Inventory item - stay_date: '" . ($item['stay_date'] ?? 'NOT SET') . "', description: '" . ($item['description'] ?? 'NOT SET') . "'");
                if (isset($item['stay_date']) && $item['stay_date'] == $input_date) {
                    if (isset($item['description']) && stripos($item['description'], $package_inventory_name) !== false) {
                        error_log("BMA DEBUG: PACKAGE FOUND! Matched '{$package_inventory_name}' in '{$item['description']}'");
                        $hotel_has_package = true;
                        break;
                    } else {
                        error_log("BMA DEBUG: Date matches but description doesn't contain '{$package_inventory_name}'");
                    }
                } else {
                    error_log("BMA DEBUG: Date doesn't match - item date: '" . ($item['stay_date'] ?? 'NOT SET') . "' vs input: '{$input_date}'");
                }
            }
        } else {
            error_log("BMA DEBUG: Package check skipped - package_inventory_name empty: " . (empty($package_inventory_name) ? 'YES' : 'NO'));
        }

        // Check package/DBB match
        // Match if: (hotel has package AND resos = "Yes") OR (hotel no package AND resos is empty/No)
        if ($hotel_has_package && $resos_dbb === 'Yes') {
            $matches['dbb'] = true;
        } elseif (!$hotel_has_package && (empty($resos_dbb) || $resos_dbb === 'No')) {
            $matches['dbb'] = true;
        }

        // Calculate suggested updates for Resos
        $suggested_updates = array();

        // Guest Name: Suggest if names don't match exactly (case-sensitive)
        if (!empty($hotel_guest_name) && !empty($resos_guest_name)) {
            // Compare case-sensitively to catch lowercase names from NewBook
            if (trim($hotel_guest_name) !== trim($resos_guest_name)) {
                $suggested_updates['name'] = $hotel_guest_name;
            }
        } elseif (!empty($hotel_guest_name) && empty($resos_guest_name)) {
            // If Resos has no name, suggest hotel name
            $suggested_updates['name'] = $hotel_guest_name;
        }

        // Phone: Suggest if Resos doesn't have one or if they don't match
        // Don't suggest if phones already match (using same normalization as matching logic)
        if (!empty($hotel_preferred_phone)) {
            if (empty($resos_phone)) {
                $suggested_updates['phone'] = $hotel_preferred_phone;
            } elseif (!isset($matches['phone']) || !$matches['phone']) {
                // Only suggest if phones don't match (after normalization)
                $suggested_updates['phone'] = $hotel_preferred_phone;
            }
        }

        // Email: Suggest if Resos doesn't have one or if they don't match
        if (!empty($hotel_email)) {
            if (empty($resos_email) || strtolower(trim($hotel_email)) !== strtolower(trim($resos_email))) {
                $suggested_updates['email'] = $hotel_email;
            }
        }

        // Hotel Guest: Suggest "Yes" if not already set to "Yes"
        if ($resos_hotel_guest !== 'Yes') {
            $suggested_updates['hotel_guest'] = 'Yes';
        }

        // Booking #: Always suggest the Newbook booking ID if different
        if ($resos_booking_ref !== $hotel_booking_id) {
            $suggested_updates['booking_ref'] = $hotel_booking_id;
        }

        // Tariff/Package: ONLY suggest if it doesn't match
        // DBB is a "Yes only" radio button (no "No" option, just Yes or empty)
        // To clear: omit the field from customFields array (handled in update action)
        // Don't suggest if already matched
        // DEBUG: Log DBB matching status
        error_log("BMA DEBUG: DBB Check - hotel_has_package: " . ($hotel_has_package ? 'YES' : 'NO') . ", resos_dbb: '{$resos_dbb}', matches[dbb]: " . (isset($matches['dbb']) && $matches['dbb'] ? 'TRUE' : 'FALSE'));
        if (!isset($matches['dbb']) || !$matches['dbb']) {
            if ($hotel_has_package && $resos_dbb !== 'Yes') {
                error_log("BMA DEBUG: Suggesting DBB = 'Yes'");
                $suggested_updates['dbb'] = 'Yes';
            } elseif (!$hotel_has_package && $resos_dbb === 'Yes') {
                error_log("BMA DEBUG: Suggesting DBB = '' (clear)");
                // Suggest clearing DBB (empty string = omit from customFields array when updating)
                $suggested_updates['dbb'] = '';
            }
        } else {
            error_log("BMA DEBUG: DBB already matches, NOT suggesting update");
        }

        // People/Covers: Suggest hotel occupancy if different from Resos covers
        // NOTE: This is intentionally added to suggested_updates but will NOT be checked by default
        // as differences are often legitimate (non-residents joining, meeting others, etc.)
        if ($hotel_people > 0 && $resos_people > 0 && $hotel_people != $resos_people) {
            $suggested_updates['people'] = $hotel_people;
        }

        // Status: Suggest "approved" only if currently in early stages (request, declined, waitlist)
        // Do NOT suggest for arrived, seated, left, no_show, canceled (later stages or final states)
        $early_stage_statuses = array('request', 'declined', 'waitlist');
        if (in_array(strtolower($resos_status), $early_stage_statuses)) {
            $suggested_updates['status'] = 'approved';
        }

        return array(
            'hotel' => array(
                'name' => $hotel_guest_name,
                'phone' => $hotel_preferred_phone,
                'email' => $hotel_email,
                'booking_id' => $hotel_booking_id,
                'room' => $hotel_room,
                'people' => $hotel_people,
                'notes' => $hotel_room . ' / #' . $hotel_booking_id, // Show what we're matching against
                'is_hotel_guest' => true, // Always true for hotel bookings
                'rate_type' => $hotel_rate_type,
                'has_package' => $hotel_has_package,
                'status' => $hotel_status
            ),
            'resos' => array(
                'id' => $resos_booking_id,
                'restaurant_id' => $resos_restaurant_id,
                'name' => $resos_guest_name,
                'phone' => $resos_phone,
                'email' => $resos_email,
                'booking_ref' => $resos_booking_ref,
                'people' => $resos_people,
                'notes' => $resos_notes,
                'hotel_guest' => $resos_hotel_guest,
                'dbb' => $resos_dbb,
                'status' => $resos_status
            ),
            'matches' => $matches,
            'suggested_updates' => $suggested_updates
        );
    }

    /**
     * Normalize string for matching (lowercase, remove special chars)
     */
    private function normalize_for_matching($string) {
        if (empty($string)) {
            return '';
        }

        $normalized = strtolower(trim($string));
        $normalized = str_replace(array('-', "'", ' ', '.'), '', $normalized);

        return $normalized;
    }

    /**
     * Normalize phone number for matching - removes ALL non-numeric characters
     * Handles spaces, brackets, hyphens, plus signs, etc.
     */
    private function normalize_phone_for_matching($phone) {
        if (empty($phone)) {
            return '';
        }

        // Remove all non-digit characters (spaces, brackets, hyphens, plus signs, etc.)
        return preg_replace('/\D/', '', trim($phone));
    }

    /**
     * Extract surname from full name
     */
    private function extract_surname($full_name) {
        if (empty($full_name)) {
            return '';
        }

        $parts = explode(' ', trim($full_name));
        return end($parts); // Return last part as surname
    }
}
