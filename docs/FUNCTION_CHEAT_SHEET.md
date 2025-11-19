# Function Cheat Sheet

Quick reference guide for common functions, methods, and use cases in the Booking Match API plugin.

## Table of Contents

- [Search & Matching](#search--matching)
- [Data Retrieval](#data-retrieval)
- [Booking Operations](#booking-operations)
- [Data Formatting](#data-formatting)
- [Utility Functions](#utility-functions)
- [Common Workflows](#common-workflows)
- [Error Handling](#error-handling)
- [Quick Examples](#quick-examples)

---

## Search & Matching

### Search for Booking by Email

```php
$searcher = new BMA_NewBook_Search();
$results = $searcher->search_bookings(array(
    'email' => 'john@example.com'
));

// Returns: ['bookings' => [...], 'search_method' => 'email']
```

### Search by Phone Number

```php
$results = $searcher->search_bookings(array(
    'phone' => '+61412345678'
));
```

### Search by Name + Email (Confident)

```php
$results = $searcher->search_bookings(array(
    'guest_name' => 'John Smith',
    'email' => 'john@example.com'
));
```

### Get Single Booking by ID

```php
$booking = $searcher->get_booking_by_id(12345);
```

### Match Booking Across All Nights

```php
$matcher = new BMA_Matcher();
$match_result = $matcher->match_booking_all_nights($booking);

// Returns array with 'nights' containing match info for each night
```

### Match Single Night

```php
$match_info = $matcher->match_resos_to_hotel(
    $resos_booking,    // ResOS booking data
    $hotel_booking,    // Hotel booking data
    '2025-11-20',      // Date
    array(12346, 12347), // Other booking IDs on this date
    $all_hotel_bookings  // All hotel bookings for date
);

// Returns: ['matched' => true, 'match_type' => 'booking_id', 'confidence' => 'high', ...]
```

---

## Data Retrieval

### Get Recent Bookings (Last 72 Hours)

```php
$searcher = new BMA_NewBook_Search();
$recent = $searcher->fetch_recent_placed_bookings(5, 72);

// Returns: Array of 5 most recent bookings
```

### Get Bookings Staying on Date

```php
$date = '2025-11-20';
$bookings = $searcher->fetch_hotel_bookings_for_date($date);

// Returns: Array of all bookings staying on this date
```

### Fetch ResOS Bookings for Date

```php
$matcher = new BMA_Matcher();
$resos_bookings = $matcher->fetch_resos_bookings('2025-11-20');

// Returns: Array of restaurant bookings
```

---

## Booking Operations

### Create ResOS Booking

```php
$actions = new BMA_Booking_Actions();
$result = $actions->create_resos_booking(array(
    'hotel_booking_id' => 12345,
    'date' => '2025-11-20',
    'time' => '19:00',
    'people' => 2,
    'notes' => 'Anniversary dinner'
));

// Returns: ['success' => true, 'resos_booking_id' => '...']
```

### Update ResOS Booking

```php
$result = $actions->update_resos_booking('5f8a7b...', array(
    'Booking #' => '12345',
    'people' => 3
));

// Returns: ['success' => true, 'updated_fields' => [...]]
```

### Exclude Match

```php
$result = $actions->exclude_match(
    12345,              // Hotel booking ID
    '5f8a7b...',       // ResOS booking ID
    'Different guest'   // Reason
);

// Returns: ['success' => true]
```

---

## Data Formatting

### Format Response as JSON

```php
$formatter = new BMA_Response_Formatter();
$response = $formatter->format_response(
    $match_results,
    'email',
    'json'
);

// Returns: JSON array with bookings, match details, badge counts
```

### Format Response as HTML

```php
$html = $formatter->format_response(
    $match_results,
    'email',
    'chrome-sidepanel'
);

// Returns: HTML string from template
```

### Prepare Comparison Data

```php
$comparison = new BMA_Comparison();
$data = $comparison->prepare_comparison_data(
    $hotel_booking,
    $resos_booking,
    '2025-11-20'
);

// Returns: Array with matches, suggested_updates, discrepancies
```

---

## Utility Functions

### Normalize String for Matching

```php
$comparison = new BMA_Comparison();
$normalized = $comparison->normalize_for_matching('  John SMITH  ');
// Returns: 'john smith'
```

### Normalize Phone Number

```php
$normalized = $comparison->normalize_phone_for_matching('+61 412 345 678');
// Returns: '61412345678'
```

### Extract Surname

```php
$surname = $comparison->extract_surname('John Smith');
// Returns: 'Smith'
```

### Title Case Name

```php
$name = $comparison->title_case_name('john smith');
// Returns: 'John Smith'
```

### Log Message

```php
bma_log('Processing booking 12345', 'info');
bma_log('Error: API timeout', 'error');
```

### Get Request Context

```php
$context = bma_get_request_context($request);
// Returns: Array with user, IP, route, timestamp, etc.
```

### Anonymize IP Address

```php
$anon_ip = bma_anonymize_ip('192.168.1.123');
// Returns: '192.168.1.0'
```

---

## Common Workflows

### Complete Match Flow

```php
// 1. Search for booking
$searcher = new BMA_NewBook_Search();
$search_result = $searcher->search_bookings(array(
    'email' => 'john@example.com'
));

if (is_wp_error($search_result)) {
    return $search_result; // Error
}

$bookings = $search_result['bookings'];

// 2. Match each booking
$matcher = new BMA_Matcher();
$results = array();

foreach ($bookings as $booking) {
    $match_result = $matcher->match_booking_all_nights($booking);
    $results[] = $match_result;
}

// 3. Format response
$formatter = new BMA_Response_Formatter();
$response = $formatter->format_response($results, 'email', 'json');

return $response;
```

### Check Booking for Issues

```php
$checker = new BMA_Issue_Checker();
$issues = $checker->check_booking($booking, $match_results);

// Returns:
// [
//     'critical' => [array of critical issues],
//     'warnings' => [array of warnings],
//     'info' => [array of info messages]
// ]
```

### Get Comparison and Suggested Updates

```php
$comparison = new BMA_Comparison();
$comp_data = $comparison->prepare_comparison_data(
    $hotel_booking,
    $resos_booking,
    '2025-11-20'
);

// Check for suggested updates
if (!empty($comp_data['suggested_updates'])) {
    foreach ($comp_data['suggested_updates'] as $update) {
        echo "Update {$update['field']} to {$update['suggested_value']}\n";
        echo "Reason: {$update['reason']}\n";
    }
}
```

### Warm Caches Before Matching

```php
// Collect all unique dates
$dates = array();
foreach ($bookings as $booking) {
    $arrival = substr($booking['booking_arrival'], 0, 10);
    $departure = substr($booking['booking_departure'], 0, 10);

    $current = $arrival;
    while ($current < $departure) {
        $dates[] = $current;
        $current = date('Y-m-d', strtotime($current . ' +1 day'));
    }
}
$dates = array_unique($dates);

// Warm caches
$matcher = new BMA_Matcher();
$searcher = new BMA_NewBook_Search();

foreach ($dates as $date) {
    $matcher->fetch_resos_bookings($date);
    $searcher->fetch_hotel_bookings_for_date($date);
}

// Now matching will use cached data
```

---

## Error Handling

### Check if Search is Confident

```php
// Name alone - NOT confident
$criteria = array('guest_name' => 'John Smith');

// Email alone - IS confident
$criteria = array('email' => 'john@example.com');

// Name + Email - IS confident
$criteria = array(
    'guest_name' => 'John Smith',
    'email' => 'john@example.com'
);
```

### Handle Search Errors

```php
$results = $searcher->search_bookings($criteria);

if (is_wp_error($results)) {
    $error_code = $results->get_error_code();
    $error_message = $results->get_error_message();

    if ($error_code === 'not_confident') {
        // Search criteria not confident enough
    }

    return new WP_Error($error_code, $error_message);
}
```

### Handle API Failures with Stale Cache

```php
$resos_bookings = $matcher->fetch_resos_bookings($date);

// Check if using stale cache
if ($matcher->is_date_using_stale_cache($date)) {
    // Show warning in UI
    echo "⚠️ Data may be outdated";
}
```

---

## Quick Examples

### REST API Call (cURL)

```bash
# Match by email
curl -X POST https://example.com/wp-json/bma/v1/bookings/match \
  -u "admin:app_password" \
  -H "Content-Type: application/json" \
  -d '{"email_address": "john@example.com"}'

# Get staying bookings
curl -X GET https://example.com/wp-json/bma/v1/staying?date=2025-11-20 \
  -u "admin:app_password"

# Get summary
curl -X GET https://example.com/wp-json/bma/v1/summary?hours_back=48 \
  -u "admin:app_password"
```

### JavaScript Fetch

```javascript
const auth = 'Basic ' + btoa('admin:app_password');

// Match booking
const response = await fetch('https://example.com/wp-json/bma/v1/bookings/match', {
    method: 'POST',
    headers: {
        'Authorization': auth,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        email_address: 'john@example.com',
        context: 'json'
    })
});

const data = await response.json();
console.log('Found:', data.bookings_found, 'bookings');
```

### Python Requests

```python
import requests
from requests.auth import HTTPBasicAuth

auth = HTTPBasicAuth('admin', 'app_password')
base_url = 'https://example.com/wp-json/bma/v1'

# Match booking
response = requests.post(
    f'{base_url}/bookings/match',
    auth=auth,
    json={'email_address': 'john@example.com'}
)

if response.status_code == 200:
    data = response.json()
    print(f"Found {data['bookings_found']} bookings")
```

### PHP WordPress Plugin

```php
// In your plugin/theme
add_action('init', function() {
    // Get booking match API results
    $api = new BMA_REST_Controller();

    // Create fake request
    $request = new WP_REST_Request('POST', '/bma/v1/bookings/match');
    $request->set_param('email_address', 'john@example.com');

    // Process
    $response = $api->match_booking($request);

    // Get data
    if (!is_wp_error($response)) {
        $data = $response->get_data();
        // Use $data...
    }
});
```

---

## Function Signatures Quick Reference

### BMA_NewBook_Search

```php
// Get booking by ID
$booking = $searcher->get_booking_by_id(
    int $booking_id,
    bool $force_refresh = false,
    array $request_context = null
): array|false

// Search bookings
$results = $searcher->search_bookings(
    array $criteria,
    array $request_context = null
): array|WP_Error

// Fetch recent bookings
$bookings = $searcher->fetch_recent_placed_bookings(
    int $limit = 5,
    int $hours_back = 72,
    bool $force_refresh = false,
    array $request_context = null
): array

// Fetch bookings for date
$bookings = $searcher->fetch_hotel_bookings_for_date(
    string $date,
    bool $force_refresh = false,
    array $request_context = null
): array
```

### BMA_Matcher

```php
// Match all nights
$result = $matcher->match_booking_all_nights(
    array $booking,
    bool $force_refresh = false,
    array $request_context = null
): array

// Match single booking to hotel
$match = $matcher->match_resos_to_hotel(
    array $resos_booking,
    array $hotel_booking,
    string $date,
    array $other_booking_ids = array(),
    array $all_hotel_bookings = array()
): array

// Fetch ResOS bookings
$bookings = $matcher->fetch_resos_bookings(
    string $date,
    bool $force_refresh = false
): array

// Check for stale cache
$is_stale = $matcher->is_date_using_stale_cache(
    string $date
): bool
```

### BMA_Comparison

```php
// Prepare comparison
$data = $comparison->prepare_comparison_data(
    array $hotel_booking,
    array $resos_booking,
    string $input_date
): array

// Normalize string
$normalized = $comparison->normalize_for_matching(
    string $string
): string

// Normalize phone
$normalized = $comparison->normalize_phone_for_matching(
    string $phone
): string

// Extract surname
$surname = $comparison->extract_surname(
    string $full_name
): string
```

### BMA_Response_Formatter

```php
// Format response
$response = $formatter->format_response(
    array $results,
    string $search_method,
    string $context = 'json'
): array|string
```

### BMA_Booking_Actions

```php
// Create booking
$result = $actions->create_resos_booking(
    array $data
): array|WP_Error

// Update booking
$result = $actions->update_resos_booking(
    string $booking_id,
    array $data
): array|WP_Error

// Exclude match
$result = $actions->exclude_match(
    int $hotel_booking_id,
    string $resos_booking_id,
    string $reason = ''
): bool|WP_Error
```

### Global Functions

```php
// Logging
bma_log(string $message, string $level = 'debug'): void

// Get request context
bma_get_request_context(WP_REST_Request $request): array

// Identify client type
bma_identify_client_type(WP_REST_Request $request): string

// Anonymize IP
bma_anonymize_ip(string $ip): string
```

---

## Constants

```php
BMA_VERSION           // Plugin version: '1.5.0'
BMA_PLUGIN_DIR        // Absolute path to plugin directory
BMA_PLUGIN_URL        // URL to plugin directory
```

---

## Common Data Structures

### Search Criteria Array

```php
array(
    'guest_name' => string,
    'email' => string,
    'phone' => string,
    'group_id' => int,
    'agent_reference' => string
)
```

### Match Result Array

```php
array(
    'booking_id' => int,
    'guest_name' => string,
    'room' => string,
    'arrival' => string,      // YYYY-MM-DD
    'departure' => string,    // YYYY-MM-DD
    'total_nights' => int,
    'nights' => array(
        array(
            'date' => string,
            'resos_matches' => array,
            'match_count' => int,
            'has_package' => bool,
            'is_stale' => bool
        )
    )
)
```

### Match Info Array

```php
array(
    'matched' => bool,
    'match_type' => string,        // 'booking_id', 'agent_ref', 'composite'
    'confidence' => string,        // 'high', 'medium', 'low'
    'is_primary' => bool,
    'match_label' => string,
    'score' => int,                // Composite score
    'is_group_member' => bool,
    'lead_booking_room' => string|null
)
```

### Comparison Data Array

```php
array(
    'hotel_booking_id' => string,
    'hotel_guest_name' => string,
    'hotel_phone' => string,
    'hotel_email' => string,
    'hotel_people' => int,
    'resos_booking_id' => string,
    'resos_guest_name' => string,
    'resos_phone' => string,
    'resos_email' => string,
    'resos_people' => int,
    'matches' => array(
        'name' => bool,
        'phone' => bool,
        'email' => bool,
        'people' => bool
    ),
    'suggested_updates' => array(
        array(
            'field' => string,
            'current_value' => mixed,
            'suggested_value' => mixed,
            'reason' => string
        )
    ),
    'discrepancies' => array(
        array(
            'field' => string,
            'hotel_value' => mixed,
            'resos_value' => mixed,
            'severity' => string
        )
    )
)
```

---

## Performance Tips

1. **Use force_refresh sparingly** - Only when you need fresh data
2. **Pre-warm caches** - Fetch all dates before matching multiple bookings
3. **Batch operations** - Process multiple bookings in one request
4. **Cache client-side** - Store results in browser storage
5. **Use context parameter** - Request only the format you need

---

## Debugging

### Enable Debug Logging

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Or via plugin settings
update_option('bma_enable_debug_logging', true);
```

### View Logs

```bash
tail -f wp-content/debug.log
```

### Test Authentication

```bash
curl -v -X GET https://example.com/wp-json/bma/v1/summary \
  -u "admin:app_password"
```

### Clear Caches

```php
// Clear all NewBook cache
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_newbook_api_%'");

// Clear specific ResOS date
delete_transient('resos_bookings_2025-11-20');
```

---

## Related Documentation

- [API_REFERENCE.md](API_REFERENCE.md) - Complete class documentation
- [REST_API_ENDPOINTS.md](REST_API_ENDPOINTS.md) - Endpoint reference
- [TEMPLATE_REFERENCE.md](TEMPLATE_REFERENCE.md) - Template documentation
- [ARCHITECTURE.md](ARCHITECTURE.md) - System architecture
