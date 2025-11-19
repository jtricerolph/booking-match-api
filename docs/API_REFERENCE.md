# API Reference

Complete reference documentation for all classes and methods in the Booking Match API plugin.

## Table of Contents

- [BMA_REST_Controller](#bma_rest_controller)
- [BMA_Matcher](#bma_matcher)
- [BMA_Comparison](#bma_comparison)
- [BMA_NewBook_Search](#bma_newbook_search)
- [BMA_Response_Formatter](#bma_response_formatter)
- [BMA_Template_Helper](#bma_template_helper)
- [BMA_Booking_Actions](#bma_booking_actions)
- [JavaScript Client (Extension)](#javascript-client-extension)

---

## BMA_REST_Controller

**File:** `includes/class-bma-rest-controller.php`

**Purpose:** Handles REST API endpoint registration and request processing. This is the main entry point for all API requests.

### Properties

- `$namespace` (string) - REST API namespace: `bma/v1`
- `$rest_base` (string) - Resource name: `bookings`

### Public Methods

#### `register_routes()`

Registers all REST API routes for the plugin.

**Parameters:** None

**Returns:** `void`

**Registered Routes:**
- `POST /bma/v1/bookings/match` - Match bookings
- `GET /bma/v1/summary` - Get booking summary
- `GET /bma/v1/checks/{booking_id}` - Get booking checks
- `POST /bma/v1/comparison` - Get booking comparison
- `POST /bma/v1/bookings/update` - Update booking
- `POST /bma/v1/bookings/exclude` - Exclude match
- `POST /bma/v1/bookings/group` - Update group
- `GET /bma/v1/bookings/for-date` - Get bookings for date
- `POST /bma/v1/bookings/create` - Create booking
- `GET|POST /bma/v1/opening-hours` - Get opening hours
- `POST /bma/v1/available-times` - Get available times
- `GET /bma/v1/dietary-choices` - Get dietary choices
- `GET /bma/v1/all-bookings-for-date` - Get all bookings for date
- `GET /bma/v1/special-events` - Get special events
- `GET /bma/v1/staying` - Get staying bookings

**Example:**
```php
$controller = new BMA_REST_Controller();
$controller->register_routes();
```

#### `permissions_check($request)`

Checks if the user has permission to access the API.

**Parameters:**
- `$request` (WP_REST_Request) - The REST request object

**Returns:** `bool|WP_Error` - True if authorized, WP_Error otherwise

**Authentication Methods:**
- WordPress session cookies
- Application Passwords (HTTP Basic Auth)
- Requires `read` capability

**Example:**
```php
if (!$controller->permissions_check($request)) {
    return new WP_Error('rest_forbidden', 'Access denied', array('status' => 403));
}
```

#### `match_booking($request)`

Main endpoint for searching and matching hotel bookings with restaurant reservations.

**Parameters:**
- `$request` (WP_REST_Request) - Request object with search parameters

**Request Parameters:**
- `booking_id` (int, optional) - NewBook booking ID
- `guest_name` (string, optional) - Guest's full name
- `email_address` (string, optional) - Guest's email address
- `phone_number` (string, optional) - Guest's phone number
- `group_id` (int, optional) - Group ID
- `travelagent_reference` (string, optional) - Travel agent reference
- `context` (string, optional) - Response format: `json`, `chrome-extension`, `chrome-sidepanel`
- `force_refresh` (bool, optional) - Force cache refresh

**Returns:** `WP_REST_Response|WP_Error` - Formatted response or error

**Example:**
```bash
curl -X POST https://example.com/wp-json/bma/v1/bookings/match \
  -H "Authorization: Basic base64(username:app_password)" \
  -H "Content-Type: application/json" \
  -d '{
    "email_address": "guest@example.com",
    "context": "json"
  }'
```

#### `get_summary($request)`

Gets summary of recent bookings.

**Parameters:**
- `$request` (WP_REST_Request) - Request object

**Request Parameters:**
- `hours_back` (int, optional, default: 72) - How many hours back to search
- `limit` (int, optional, default: 5) - Maximum number of bookings
- `context` (string, optional) - Response format

**Returns:** `WP_REST_Response|WP_Error`

**Example:**
```bash
curl -X GET https://example.com/wp-json/bma/v1/summary?hours_back=48&limit=10 \
  -H "Authorization: Basic base64(username:app_password)"
```

#### `get_staying_bookings($request)`

Gets all bookings staying on a specific date.

**Parameters:**
- `$request` (WP_REST_Request) - Request object

**Request Parameters:**
- `date` (string, optional) - Date in YYYY-MM-DD format (defaults to today)
- `force_refresh` (bool, optional) - Force cache refresh

**Returns:** `WP_REST_Response|WP_Error`

**Example:**
```bash
curl -X GET https://example.com/wp-json/bma/v1/staying?date=2025-11-20 \
  -H "Authorization: Basic base64(username:app_password)"
```

### Private Methods

#### `validate_search_criteria($booking_id, $guest_name, $email, $phone, $agent_ref)`

Validates search criteria to ensure confident matches.

**Parameters:**
- `$booking_id` (int) - Booking ID
- `$guest_name` (string) - Guest name
- `$email` (string) - Email address
- `$phone` (string) - Phone number
- `$agent_ref` (string) - Agent reference

**Returns:** `bool|WP_Error` - True if valid, WP_Error if not confident

**Confidence Rules:**
- Booking ID alone: Always confident
- Email alone: Confident
- Phone alone: Confident
- Agent reference alone: Confident
- Name + email: Confident
- Name + phone: Confident
- Name alone: NOT confident (returns error)

---

## BMA_Matcher

**File:** `includes/class-bma-matcher.php`

**Purpose:** Handles matching NewBook hotel bookings with Resos restaurant bookings for each night of stay.

### Public Methods

#### `match_booking_all_nights($booking, $force_refresh = false, $request_context = null)`

Matches a hotel booking across all its nights with restaurant reservations.

**Parameters:**
- `$booking` (array) - NewBook booking data
- `$force_refresh` (bool, optional) - Force cache refresh
- `$request_context` (array, optional) - Request context for logging

**Returns:** `array` - Match results for all nights

**Return Structure:**
```php
array(
    'booking_id' => 12345,
    'booking_reference' => 'ABC123',
    'guest_name' => 'John Smith',
    'room' => '101',
    'arrival' => '2025-11-20',
    'departure' => '2025-11-22',
    'total_nights' => 2,
    'phone' => '+61412345678',
    'email' => 'john@example.com',
    'occupants' => array(
        'adults' => 2,
        'children' => 1,
        'infants' => 0
    ),
    'tariffs' => array('BB', 'Dinner'),
    'booking_status' => 'confirmed',
    'booking_source' => 'Booking.com',
    'nights' => array(
        array(
            'date' => '2025-11-20',
            'resos_matches' => array(...),
            'match_count' => 1,
            'has_package' => true,
            'is_stale' => false
        ),
        ...
    )
)
```

**Example:**
```php
$matcher = new BMA_Matcher();
$booking = array(
    'booking_id' => 12345,
    'booking_arrival' => '2025-11-20',
    'booking_departure' => '2025-11-22',
    // ... other booking data
);
$result = $matcher->match_booking_all_nights($booking);
```

#### `match_resos_to_hotel($resos_booking, $hotel_booking, $date, $other_booking_ids = array(), $all_hotel_bookings = array())`

Matches a single Resos booking to a hotel booking for a specific date.

**Parameters:**
- `$resos_booking` (array) - Resos booking data
- `$hotel_booking` (array) - Hotel booking data
- `$date` (string) - Date in YYYY-MM-DD format
- `$other_booking_ids` (array, optional) - Other hotel booking IDs for the date
- `$all_hotel_bookings` (array, optional) - All hotel bookings for the date

**Returns:** `array` - Match information

**Return Structure:**
```php
array(
    'matched' => true,
    'match_type' => 'booking_id',      // or 'agent_ref', 'composite'
    'confidence' => 'high',             // or 'medium', 'low'
    'is_primary' => true,               // Primary vs suggested match
    'match_label' => 'Booking ID',
    'score' => 100,                     // Composite match score
    'is_group_member' => false,         // Part of group booking
    'lead_booking_room' => null         // Room number of lead booking in group
)
```

**Matching Priority:**
1. Booking ID in custom fields (PRIMARY - highest confidence)
2. Group/Individual match in GROUP/EXCLUDE field
3. Agent reference in custom fields (PRIMARY)
4. Booking ID in notes (SUGGESTED)
5. Agent reference in notes (SUGGESTED)
6. Composite scoring (SUGGESTED - based on multiple factors)

**Composite Scoring Factors:**
- Room number in notes: +8 points
- Surname match: +7 points
- Phone match (last 8 digits): +9 points
- Email match: +10 points

**Confidence Levels:**
- High: Score ≥20 or ≥3 matches
- Medium: Score ≥15 or ≥2 matches
- Low: Score >0

**Example:**
```php
$matcher = new BMA_Matcher();
$match_info = $matcher->match_resos_to_hotel(
    $resos_booking,
    $hotel_booking,
    '2025-11-20',
    array(12346, 12347), // Other booking IDs
    $all_hotel_bookings
);

if ($match_info['matched']) {
    echo "Match type: " . $match_info['match_type'];
    echo "Confidence: " . $match_info['confidence'];
}
```

#### `fetch_resos_bookings($date, $force_refresh = false)`

Fetches Resos restaurant bookings for a specific date.

**Parameters:**
- `$date` (string) - Date in YYYY-MM-DD format
- `$force_refresh` (bool, optional) - Force cache refresh

**Returns:** `array` - Array of Resos bookings

**Example:**
```php
$matcher = new BMA_Matcher();
$resos_bookings = $matcher->fetch_resos_bookings('2025-11-20');
foreach ($resos_bookings as $booking) {
    echo $booking['guest']['name'] . " at " . $booking['time'];
}
```

---

## BMA_Comparison

**File:** `includes/class-bma-comparison.php`

**Purpose:** Handles detailed comparison between hotel bookings and restaurant bookings, identifying discrepancies and suggested updates.

### Public Methods

#### `prepare_comparison_data($hotel_booking, $resos_booking, $input_date)`

Prepares detailed comparison data between a hotel booking and Resos booking.

**Parameters:**
- `$hotel_booking` (array) - NewBook booking data
- `$resos_booking` (array) - Resos booking data
- `$input_date` (string) - Date in YYYY-MM-DD format

**Returns:** `array` - Comparison data structure

**Return Structure:**
```php
array(
    // Hotel data
    'hotel_booking_id' => '12345',
    'hotel_guest_name' => 'John Smith',
    'hotel_phone' => '+61412345678',
    'hotel_email' => 'john@example.com',
    'hotel_room' => '101',
    'hotel_people' => 2,
    'hotel_rate_type' => 'BB',
    'hotel_has_package' => true,

    // Resos data
    'resos_booking_id' => '5f8a7b2c3d1e4f5g6h7i8j9k',
    'resos_guest_name' => 'John Smith',
    'resos_phone' => '+61412345678',
    'resos_email' => 'john@example.com',
    'resos_people' => 2,
    'resos_time' => '19:00',
    'resos_status' => 'confirmed',
    'resos_booking_ref' => '12345',
    'resos_hotel_guest' => 'Yes',
    'resos_dbb' => 'No',
    'resos_notes' => 'Window seat requested',

    // Matches
    'matches' => array(
        'name' => true,
        'phone' => true,
        'email' => true,
        'booking_ref' => true,
        'people' => true,
        'notes' => false
    ),

    // Suggested updates
    'suggested_updates' => array(
        array(
            'field' => 'Booking #',
            'current_value' => '',
            'suggested_value' => '12345',
            'reason' => 'Missing booking reference in custom field'
        ),
        array(
            'field' => 'people',
            'current_value' => 1,
            'suggested_value' => 2,
            'reason' => 'People count mismatch'
        )
    ),

    // Discrepancies
    'discrepancies' => array(
        array(
            'field' => 'guest_name',
            'hotel_value' => 'John Smith',
            'resos_value' => 'J Smith',
            'severity' => 'warning'
        )
    )
)
```

**Example:**
```php
$comparison = new BMA_Comparison();
$data = $comparison->prepare_comparison_data(
    $hotel_booking,
    $resos_booking,
    '2025-11-20'
);

if (!empty($data['suggested_updates'])) {
    foreach ($data['suggested_updates'] as $update) {
        echo "Update {$update['field']} to {$update['suggested_value']}";
    }
}
```

#### `normalize_for_matching($string)`

Normalizes a string for comparison (lowercase, trim, etc.).

**Parameters:**
- `$string` (string) - String to normalize

**Returns:** `string` - Normalized string

**Example:**
```php
$comparison = new BMA_Comparison();
$normalized = $comparison->normalize_for_matching('  John SMITH  ');
// Returns: 'john smith'
```

#### `normalize_phone_for_matching($phone)`

Normalizes a phone number for comparison (removes non-digits).

**Parameters:**
- `$phone` (string) - Phone number

**Returns:** `string` - Digits only

**Example:**
```php
$comparison = new BMA_Comparison();
$normalized = $comparison->normalize_phone_for_matching('+61 412 345 678');
// Returns: '61412345678'
```

---

## BMA_NewBook_Search

**File:** `includes/class-bma-newbook-search.php`

**Purpose:** Handles searching the NewBook PMS API for hotel bookings using various criteria.

### Public Methods

#### `get_booking_by_id($booking_id, $force_refresh = false, $request_context = null)`

Retrieves a single booking by its ID.

**Parameters:**
- `$booking_id` (int) - NewBook booking ID
- `$force_refresh` (bool, optional) - Bypass cache
- `$request_context` (array, optional) - Context for logging

**Returns:** `array|false` - Booking data or false on failure

**Example:**
```php
$searcher = new BMA_NewBook_Search();
$booking = $searcher->get_booking_by_id(12345);

if ($booking) {
    echo "Guest: " . $booking['guests'][0]['firstname'];
}
```

#### `search_bookings($criteria, $request_context = null)`

Searches for bookings using guest details.

**Parameters:**
- `$criteria` (array) - Search criteria
  - `guest_name` (string, optional)
  - `email` (string, optional)
  - `phone` (string, optional)
  - `group_id` (int, optional)
  - `agent_reference` (string, optional)
- `$request_context` (array, optional) - Context for logging

**Returns:** `array|WP_Error` - Search results or error

**Return Structure:**
```php
array(
    'bookings' => array(...),
    'search_method' => 'email',     // or 'phone', 'agent_reference', etc.
    'match_details' => array(
        array(
            'booking' => array(...),
            'confidence_score' => 100,
            'match_reason' => 'Email exact match'
        )
    )
)
```

**Example:**
```php
$searcher = new BMA_NewBook_Search();
$results = $searcher->search_bookings(array(
    'email' => 'john@example.com'
));

if (!is_wp_error($results)) {
    foreach ($results['bookings'] as $booking) {
        echo "Found booking: " . $booking['booking_id'];
    }
}
```

#### `fetch_recent_placed_bookings($limit = 5, $hours_back = 72, $force_refresh = false, $request_context = null)`

Fetches recently created bookings.

**Parameters:**
- `$limit` (int, optional) - Maximum number of bookings
- `$hours_back` (int, optional) - How many hours back to search
- `$force_refresh` (bool, optional) - Bypass cache
- `$request_context` (array, optional) - Context for logging

**Returns:** `array` - Array of bookings sorted by booking_id descending

**Example:**
```php
$searcher = new BMA_NewBook_Search();
$recent = $searcher->fetch_recent_placed_bookings(10, 48);

foreach ($recent as $booking) {
    $placed_time = strtotime($booking['booking_placed']);
    echo "Booking #{$booking['booking_id']} placed " . human_time_diff($placed_time) . " ago";
}
```

#### `fetch_hotel_bookings_for_date($date, $force_refresh = false, $request_context = null)`

Fetches all hotel bookings staying on a specific date.

**Parameters:**
- `$date` (string) - Date in YYYY-MM-DD format
- `$force_refresh` (bool, optional) - Bypass cache
- `$request_context` (array, optional) - Context for logging

**Returns:** `array` - Array of bookings

**Example:**
```php
$searcher = new BMA_NewBook_Search();
$bookings = $searcher->fetch_hotel_bookings_for_date('2025-11-20');

echo "Found " . count($bookings) . " bookings staying on this date";
```

---

## BMA_Response_Formatter

**File:** `includes/class-bma-response-formatter.php`

**Purpose:** Formats API responses based on context (JSON, HTML, etc.).

### Public Methods

#### `format_response($results, $search_method, $context = 'json')`

Formats response based on the requested context.

**Parameters:**
- `$results` (array) - Match results
- `$search_method` (string) - How the search was performed
- `$context` (string, optional) - Response format: `json`, `chrome-extension`, `chrome-sidepanel`

**Returns:** `array|string` - Formatted response (JSON array or HTML string)

**Example:**
```php
$formatter = new BMA_Response_Formatter();

// JSON response
$json = $formatter->format_response($results, 'email', 'json');

// HTML response for Chrome extension
$html = $formatter->format_response($results, 'email', 'chrome-extension');
```

---

## BMA_Template_Helper

**File:** `includes/class-bma-template-helper.php`

**Purpose:** Helper functions for rendering templates and formatting data.

### Public Methods

#### `format_date($date, $format = 'd/m/Y')`

Formats a date string.

**Parameters:**
- `$date` (string) - Date in YYYY-MM-DD format
- `$format` (string, optional) - PHP date format

**Returns:** `string` - Formatted date

#### `escape_html($value)`

Escapes HTML for safe output.

**Parameters:**
- `$value` (string|array) - Value to escape

**Returns:** `string|array` - Escaped value

---

## BMA_Booking_Actions

**File:** `includes/class-bma-booking-actions.php`

**Purpose:** Handles actions on bookings (create, update, exclude).

### Public Methods

#### `create_resos_booking($data)`

Creates a new Resos restaurant booking.

**Parameters:**
- `$data` (array) - Booking data

**Returns:** `array|WP_Error` - Created booking or error

#### `update_resos_booking($booking_id, $data)`

Updates an existing Resos booking.

**Parameters:**
- `$booking_id` (string) - Resos booking ID
- `$data` (array) - Updated data

**Returns:** `array|WP_Error` - Updated booking or error

#### `exclude_match($hotel_booking_id, $resos_booking_id, $reason = '')`

Excludes a Resos booking from matching with a hotel booking.

**Parameters:**
- `$hotel_booking_id` (int) - Hotel booking ID
- `$resos_booking_id` (string) - Resos booking ID
- `$reason` (string, optional) - Reason for exclusion

**Returns:** `bool|WP_Error` - Success or error

---

## JavaScript Client (Extension)

**Note:** The plugin does not include a JavaScript client itself, but here's documentation for how client applications should interact with the API.

### Authentication

Use WordPress Application Passwords for authentication:

```javascript
const username = 'admin';
const appPassword = 'xxxx xxxx xxxx xxxx xxxx xxxx';
const auth = 'Basic ' + btoa(username + ':' + appPassword);

fetch('https://example.com/wp-json/bma/v1/bookings/match', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': auth
    },
    body: JSON.stringify({
        email_address: 'guest@example.com',
        context: 'json'
    })
});
```

### Example API Client

```javascript
class BookingMatchAPI {
    constructor(baseUrl, username, appPassword) {
        this.baseUrl = baseUrl;
        this.auth = 'Basic ' + btoa(username + ':' + appPassword);
    }

    async matchBooking(params) {
        const response = await fetch(`${this.baseUrl}/wp-json/bma/v1/bookings/match`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': this.auth
            },
            body: JSON.stringify(params)
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return response.json();
    }

    async getSummary(hoursBack = 72, limit = 5) {
        const response = await fetch(
            `${this.baseUrl}/wp-json/bma/v1/summary?hours_back=${hoursBack}&limit=${limit}`,
            {
                headers: {
                    'Authorization': this.auth
                }
            }
        );

        return response.json();
    }

    async getStayingBookings(date = null) {
        const url = date
            ? `${this.baseUrl}/wp-json/bma/v1/staying?date=${date}`
            : `${this.baseUrl}/wp-json/bma/v1/staying`;

        const response = await fetch(url, {
            headers: {
                'Authorization': this.auth
            }
        });

        return response.json();
    }
}

// Usage
const api = new BookingMatchAPI(
    'https://admin.hotelnumberfour.com',
    'admin',
    'xxxx xxxx xxxx xxxx xxxx xxxx'
);

// Search by email
const results = await api.matchBooking({
    email_address: 'john@example.com',
    context: 'json'
});

// Get recent bookings
const summary = await api.getSummary(48, 10);

// Get bookings for today
const staying = await api.getStayingBookings();
```

### Error Handling

```javascript
try {
    const results = await api.matchBooking({
        guest_name: 'John Smith'  // Name alone - not confident
    });
} catch (error) {
    if (error.response) {
        const data = await error.response.json();
        if (data.code === 'not_confident') {
            console.log('Search not confident enough:', data.message);
            console.log('Provided fields:', data.provided_fields);
        }
    }
}
```

---

## Global Helper Functions

### `bma_log($message, $level = 'debug')`

Logs a message to the WordPress debug log.

**Parameters:**
- `$message` (string) - Log message
- `$level` (string, optional) - Log level: `debug`, `info`, `warning`, `error`

**Example:**
```php
bma_log('Searching for booking by email', 'info');
bma_log('API error: ' . $error->getMessage(), 'error');
```

### `bma_get_request_context($request)`

Extracts comprehensive request context for logging.

**Parameters:**
- `$request` (WP_REST_Request) - REST request object

**Returns:** `array` - Context data

**Return Structure:**
```php
array(
    'user_id' => 1,
    'username' => 'admin',
    'ip_address' => '192.168.1.0',
    'user_agent' => 'Mozilla/5.0...',
    'route' => '/bma/v1/bookings/match',
    'method' => 'POST',
    'referrer' => 'https://example.com',
    'origin' => 'https://example.com',
    'timestamp' => '2025-11-19 12:00:00',
    'client_type' => 'chrome-extension',
    'response_format' => 'json'
)
```

### `bma_identify_client_type($request)`

Identifies the type of client making the request.

**Parameters:**
- `$request` (WP_REST_Request) - REST request object

**Returns:** `string` - Client type: `chrome-extension`, `webapp`, `curl`, `postman`, `browser`, `unknown`

### `bma_anonymize_ip($ip)`

Anonymizes an IP address for GDPR compliance.

**Parameters:**
- `$ip` (string) - IP address

**Returns:** `string` - Anonymized IP address

**Example:**
```php
bma_anonymize_ip('192.168.1.123');  // Returns: '192.168.1.0'
bma_anonymize_ip('2001:db8::1');    // Returns: '2001:db8::'
```

---

## Constants

### `BMA_VERSION`

Plugin version number.

**Value:** `1.5.0`

### `BMA_PLUGIN_DIR`

Absolute path to plugin directory.

**Value:** `/path/to/wp-content/plugins/booking-match-api/`

### `BMA_PLUGIN_URL`

URL to plugin directory.

**Value:** `https://example.com/wp-content/plugins/booking-match-api/`

---

## Hooks and Filters

### Actions

#### `bma_init`

Fired when the plugin is initialized.

**Example:**
```php
add_action('bma_init', function() {
    // Custom initialization code
});
```

### Filters

#### `bma_search_confidence`

Filters search confidence validation.

**Parameters:**
- `$is_confident` (bool) - Whether search is confident
- `$criteria` (array) - Search criteria

**Example:**
```php
add_filter('bma_search_confidence', function($is_confident, $criteria) {
    // Custom confidence logic
    return $is_confident;
}, 10, 2);
```

---

## Data Structures

### Hotel Booking Structure

```php
array(
    'booking_id' => 12345,
    'booking_reference_id' => 'ABC123',
    'booking_arrival' => '2025-11-20 15:00:00',
    'booking_departure' => '2025-11-22 11:00:00',
    'booking_status' => 'confirmed',
    'booking_placed' => '2025-11-01 10:30:00',
    'booking_adults' => 2,
    'booking_children' => 1,
    'booking_infants' => 0,
    'site_name' => '101',
    'guests' => array(
        array(
            'primary_client' => '1',
            'firstname' => 'John',
            'lastname' => 'Smith',
            'contact_details' => array(
                array('type' => 'email', 'content' => 'john@example.com'),
                array('type' => 'phone', 'content' => '+61412345678')
            )
        )
    ),
    'tariffs_quoted' => array(
        array(
            'stay_date' => '2025-11-20',
            'label' => 'BB'
        )
    ),
    'inventory_items' => array(
        array(
            'stay_date' => '2025-11-20',
            'description' => 'Dinner Package'
        )
    )
)
```

### Resos Booking Structure

```php
array(
    '_id' => '5f8a7b2c3d1e4f5g6h7i8j9k',
    'restaurantId' => 'restaurant123',
    'guest' => array(
        'name' => 'John Smith',
        'email' => 'john@example.com',
        'phone' => '+61412345678'
    ),
    'people' => 2,
    'time' => '2025-11-20T19:00:00',
    'timeString' => '19:00',
    'status' => 'confirmed',
    'customFields' => array(
        array(
            'name' => 'Booking #',
            'value' => '12345'
        ),
        array(
            'name' => 'Hotel Guest',
            'multipleChoiceValueName' => 'Yes'
        ),
        array(
            'name' => 'DBB',
            'multipleChoiceValueName' => 'No'
        )
    ),
    'restaurantNotes' => array(
        array(
            'restaurantNote' => 'Window seat requested'
        )
    )
)
```
