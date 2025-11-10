# Booking Match API - Developer Notes

## Overview

The Booking Match API plugin provides REST API endpoints for the Chrome NewBook Assistant extension. It handles restaurant booking matching, creation, updates, and provides context-aware responses for both JSON and HTML formats.

## Architecture

### Core Components

1. **REST Controller** (`class-bma-rest-controller.php`)
   - Registers all REST API endpoints
   - Handles authentication and permissions
   - Routes requests to appropriate handlers
   - Provides context-aware formatting (JSON vs HTML)

2. **Booking Actions** (`class-bma-booking-actions.php`)
   - Core business logic for booking operations
   - Resos API integration methods
   - Caching strategies for performance
   - Custom field mapping and processing

3. **Gantt Chart Generator** (`class-bma-gantt-chart.php`)
   - Reusable chart generation
   - Multiple display modes (full/medium/compact)
   - Windowed viewport support
   - Grid-based positioning algorithm

4. **Response Formatter** (`class-bma-response-formatter.php`)
   - HTML template rendering
   - Chrome extension UI generation

## API Endpoints

### Base URL
```
https://your-site.com/wp-json/bma/v1/
```

### Authentication
All endpoints require WordPress authentication via Application Passwords.

**Setup:**
1. WordPress Admin → Users → Profile
2. Scroll to "Application Passwords"
3. Enter name (e.g., "Chrome Extension")
4. Copy generated password
5. Use with HTTP Basic Auth (username + app password)

### Endpoints

#### 1. Opening Hours
```
GET/POST /bma/v1/opening-hours?date={date}&context={context}
```

**Parameters:**
- `date` (optional): YYYY-MM-DD format - Get hours for specific date
- `context` (optional): "chrome-extension" for HTML, omit for JSON

**Response (JSON):**
```json
{
  "success": true,
  "data": [
    {
      "_id": "507f1f77bcf86cd799439011",
      "name": "Dinner Service",
      "open": 1800,
      "close": 2200,
      "interval": 15,
      "duration": 120,
      "isSpecial": false
    }
  ]
}
```

**Response (HTML - context=chrome-extension):**
```json
{
  "success": true,
  "html": "<option value=\"507f...\">Dinner Service (18:00-22:00)</option>...",
  "data": [...]
}
```

**Caching:** 1 hour transient cache

---

#### 2. Available Times
```
POST /bma/v1/available-times
Body: {date, people, opening_hour_id?, context?}
```

**Parameters:**
- `date` (required): YYYY-MM-DD
- `people` (required): Integer party size
- `opening_hour_id` (optional): Filter by specific period
- `context` (optional): "chrome-extension" for HTML

**Response (JSON):**
```json
{
  "success": true,
  "times": ["18:00", "18:15", "18:30", ...],
  "periods": [...]
}
```

**Response (HTML):**
```json
{
  "success": true,
  "html": "<div class=\"bma-time-slots-grid\">...</div>",
  "times": [...],
  "periods": [...]
}
```

**Caching:** None (real-time availability)

---

#### 3. Dietary Choices
```
GET /bma/v1/dietary-choices?context={context}
```

**Response (JSON):**
```json
{
  "success": true,
  "choices": [
    {"_id": "choice_1", "name": "Gluten Free"},
    {"_id": "choice_2", "name": "Vegetarian"}
  ]
}
```

**Response (HTML):**
```json
{
  "success": true,
  "html": "<label><input type=\"checkbox\"...>Gluten Free</label>...",
  "choices": [...]
}
```

**Caching:** 24 hours

**Important:** Field name in Resos has a **leading space**: `" Dietary Requirements"`

---

#### 4. Special Events
```
GET /bma/v1/special-events?date={date}&context={context}
```

**Response (JSON):**
```json
{
  "success": true,
  "events": [
    {
      "date": "2025-01-15",
      "type": "closed",
      "reason": "Private Event",
      "isOpen": false
    }
  ]
}
```

**Caching:** 30 minutes

---

#### 5. Create Booking
```
POST /bma/v1/bookings/create
```

**Full Request Body:**
```json
{
  "date": "2025-01-15",
  "time": "19:00",
  "people": 4,
  "guest_name": "John Smith",
  "opening_hour_id": "507f1f77bcf86cd799439011",
  "guest_phone": "+441234567890",
  "guest_email": "john@example.com",
  "booking_ref": "12345",
  "hotel_guest": "Yes",
  "dbb": "Yes",
  "notification_sms": true,
  "notification_email": true,
  "dietary_requirements": "choice_1,choice_2",
  "dietary_other": "No shellfish",
  "booking_note": "Anniversary celebration",
  "language_code": "en"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Booking created successfully",
  "booking_id": "507f1f77bcf86cd799439011"
}
```

**Process:**
1. Validates required fields
2. Formats phone number to E.164
3. Maps custom fields to Resos format
4. Creates booking via Resos API
5. Adds restaurant note (separate API call)

---

## Resos API Integration

### Authentication

⚠️ **CRITICAL:** Resos API requires **Basic Authentication** with base64 encoding, NOT Bearer tokens.

**Correct Implementation:**
```php
$resos_api_key = get_option('hotel_booking_resos_api_key');

$headers = array(
    'Authorization' => 'Basic ' . base64_encode($resos_api_key . ':'),
    'Content-Type' => 'application/json',
);
```

**Important Notes:**
- Must use `Basic` authentication scheme
- API key must be base64 encoded with a trailing colon (`:`)
- Using `Bearer` tokens will result in 401 Unauthorized errors
- This format is required for ALL Resos API endpoints

**Common Mistake (Fixed in commit de4a091):**
```php
// ❌ WRONG - Will return 401 Unauthorized
'Authorization' => 'Bearer ' . $resos_api_key

// ✅ CORRECT - Required format
'Authorization' => 'Basic ' . base64_encode($resos_api_key . ':')
```

### Base URL
```
https://api.resos.com/v1/
```

### Caching Strategy

| Data Type | Cache Duration | Reason |
|-----------|----------------|--------|
| Opening Hours (All) | 1 hour | Changes infrequently |
| Dietary Choices | 24 hours | Rarely changes |
| Special Events | 30 minutes | May be time-sensitive |
| Available Times | None | Real-time availability |

**Cache Keys:**
```php
'bma_opening_hours_all'               // All opening hours (filtered per date)
'bma_dietary_choices'                 // Global
'bma_special_events_' . $date         // Per date
```

**Opening Hours Implementation:**
- Fetches ALL opening hours from `/openingHours` (no date in URL)
- Filters client-side by day-of-week (1=Monday through 7=Sunday)
- Checks for special date overrides first (e.g., Christmas hours)
- Returns sorted hours for the requested date

**Available Times Implementation:**
- Uses `/bookingFlow/times` endpoint (NOT `/openingHours/{date}`)
- Date passed as query parameter: `?date=2026-01-30&people=4&areaId=X`
- Returns array of opening hour periods with `availableTimes` arrays
- Merges all `availableTimes` from all periods into single array

**Clearing Cache:**
```php
delete_transient('bma_opening_hours_all');
delete_transient('bma_dietary_choices');
```

---

## Chrome Extension UI

### Form Structure

The create booking form in the Chrome extension uses a **vertical accordion interface** for service period selection.

**Key Components:**

1. **Gantt Chart** (Compact Mode)
   - Visual timeline showing opening hours as colored bands
   - No navigation controls (arrows/title removed for space efficiency)
   - Auto-generated from opening hours data

2. **Accordion Service Period Selector**
   - Vertical list of collapsible section headers
   - Each header represents one service period (e.g., "Lunch Service", "Evening Service")
   - **Exclusive behavior**: Only one section can be open at a time
   - Latest period (dinner) expanded by default
   - Lazy loading: time slots fetched only when section expanded

3. **Hidden Form Fields**
   - `form-date`: Hidden input (date defined by which day's create button clicked)
   - `form-time-selected`: Stores selected time slot value
   - `form-opening-hour-id`: Stores period ID (captured when time slot clicked)

4. **Dynamic Booking Summary Header**
   - Positioned above Create/Cancel buttons
   - Format: `{guest name} - {selected time} ({people}pax)`
   - Time updates in real-time when user selects time slot
   - Selected time displayed in blue highlight

**Template Location:** `templates/chrome-sidepanel-response.php`

**JavaScript Functions:**
- `initializeCreateFormForDate(date, form)` - Auto-initializes form when visible
- `togglePeriodSection(date, periodIndex)` - Handles accordion expand/collapse
- `loadAvailableTimesForPeriod(date, people, periodId, periodIndex)` - Lazy loads time slots
- `buildGanttChart(openingHours)` - Generates Gantt chart HTML

**Form Validation:**

The form validates that both time and period are selected:

```javascript
// Time validation
const timeField = document.getElementById('time-selected-' + date);
if (!timeField.value) {
  showFeedback(feedback, 'Please select a time slot', 'error');
  return;
}

// Period validation
const openingHourIdField = document.getElementById('opening-hour-id-' + date);
if (!openingHourIdField.value) {
  showFeedback(feedback, 'Please select a time slot from a service period', 'error');
  return;
}
```

Both fields are automatically populated when user clicks a time slot button.

**Time Slot Generation Logic:**

The time slot buttons are generated using the **management plugin pattern**:

1. **Generate ALL time slots** from opening hours:
   - Calculate from start time (open) + interval minutes
   - Continue until last seating time is reached
   - Example: 18:00 open, 15min interval, 21:00 last seating = 18:00, 18:15, 18:30...21:00

2. **Grey out slots** based on TWO criteria (either condition triggers greying):
   - **Not available**: Slot time not in `availableTimes` array from `/bookingFlow/times` endpoint (fully booked)
   - **Restricted**: Slot falls within special event restriction (closures/limitations)

3. **Special Events handling**:
   - Events with `isOpen: true` are **skipped** (these are special opening hours, NOT restrictions)
   - Events with `isOpen: false` or no `isOpen` property are treated as restrictions
   - Full-day closures: Events with no `open`/`close` times grey out entire period
   - Time-range restrictions: Events with `open`/`close` grey out specific time range

4. **Tooltip display** (`data-restriction` attribute):
   - **Restricted slots**: Show event name (e.g., "Private Event", "Christmas Closure")
   - **Fully booked slots**: Show "No availability"
   - Falls back to period name + "closed" if event has no name

**Backend Implementation:**
- `format_time_slots_html()` - Generates all slots and applies greying logic
- `check_time_restriction()` - Helper method checking special event restrictions

**Frontend CSS:**
- `.time-slot-btn.time-slot-unavailable` - Styles greyed out slots
- `::before` pseudo-element displays `data-restriction` attribute on hover

---

## Custom Fields Mapping

### Field Name Map
```php
$custom_field_map = array(
    'booking_ref' => 'Booking #',
    'hotel_guest' => 'Hotel Guest',
    'dbb' => 'DBB',
    'dietary_requirements' => ' Dietary Requirements',  // Note: leading space!
    'dietary_other' => 'Other Dietary Requirements'
);
```

### Single Choice Fields
For fields like "Hotel Guest" and "DBB":
```php
$field_value_data = array(
    '_id' => $field_id,
    'name' => 'Hotel Guest',
    'value' => $choice_id,              // Choice ID from Resos
    'multipleChoiceValueName' => 'Yes'  // Display value
);
```

### Multi-Select Fields (Dietary Requirements)
```php
$selected_ids = explode(',', $dietary_requirements);
$choice_objects = array();

foreach ($selected_ids as $id) {
    $choice_objects[] = array(
        '_id' => $id,
        'name' => $choice_name,
        'value' => true
    );
}

$field_value_data = array(
    '_id' => $field_id,
    'name' => ' Dietary Requirements',
    'value' => $choice_objects  // Array of choice objects
);
```

### Text Fields
```php
$field_value_data = array(
    '_id' => $field_id,
    'name' => 'Booking #',
    'value' => '12345'  // Direct string value
);
```

---

## Gantt Chart Class

### Usage Example
```php
require_once plugin_dir_path(__FILE__) . 'includes/class-bma-gantt-chart.php';

$gantt = new BMA_Gantt_Chart();
$html = $gantt
    ->set_bookings($restaurant_bookings)
    ->set_opening_hours($opening_hours)
    ->set_available_times($available_times)
    ->set_display_mode(BMA_Gantt_Chart::MODE_COMPACT)
    ->set_viewport_hours(4)
    ->set_initial_center_time('1900')
    ->set_chart_id('gantt-2025-01-15')
    ->generate();

echo $html;
```

### Display Modes

| Mode | Bar Height | Grid Row | Names | Room # | Use Case |
|------|-----------|----------|-------|--------|----------|
| `MODE_FULL` | 40px | 14px | ✓ | ✓ | Large displays, full detail |
| `MODE_MEDIUM` | 28px | 10px | ✓ | ✗ | Tablet, reduced detail |
| `MODE_COMPACT` | 14px | 7px | ✗ | ✗ | Mobile, sidebar, minimal |

### Viewport Mode
When `viewport_hours` is set:
- Shows scrollable window (e.g., 4-hour view of full day)
- Includes scroll controls (left/right arrows)
- Auto-centers on `initial_center_time`
- JavaScript handles smooth scrolling

### Grid-Based Positioning
The algorithm prevents booking overlaps:
1. Sort bookings by start time
2. Calculate row span based on party size (1-3 people = 2 rows, 4-5 = 3 rows, etc.)
3. Find first available grid position with 5-minute buffer
4. Place booking and mark grid rows as occupied
5. Continue until all bookings positioned

---

## Error Handling

### API Response Format
```php
// Success
array(
    'success' => true,
    'message' => 'Operation successful',
    'data' => $result
)

// Error
array(
    'success' => false,
    'message' => 'Error description',
    'errors' => array('Detail 1', 'Detail 2')
)
```

### Common Errors

**Missing API Key:**
```
{'success': false, 'message': 'Resos API key not configured'}
```

**Invalid Date Format:**
```
{'success': false, 'message': 'Date must be in YYYY-MM-DD format'}
```

**Custom Field Not Found:**
```
{'success': false, 'message': 'Required custom field not found in Resos'}
```

### Error Logging
All operations log to WordPress error log:
```php
error_log('BMA: Operation description - Details');
```

**View Logs:**
- Enable WP_DEBUG_LOG in wp-config.php
- Check wp-content/debug.log

---

## Testing

### Manual API Testing

**Using cURL:**
```bash
curl -X POST https://your-site.com/wp-json/bma/v1/opening-hours \
  -u "username:application-password" \
  -H "Content-Type: application/json" \
  -d '{"date":"2025-01-15","context":"chrome-extension"}'
```

**Using Postman:**
1. Set Authorization to Basic Auth
2. Username: WordPress username
3. Password: Application Password
4. Headers: Content-Type: application/json

### Cache Testing
```php
// Force cache refresh
delete_transient('bma_opening_hours_general');

// Check cache status
$cached = get_transient('bma_opening_hours_general');
var_dump($cached);
```

### Custom Fields Testing
```php
// List all custom fields
$actions = new BMA_Booking_Actions();
$fields = $actions->fetch_dietary_choices();
error_log('Dietary fields: ' . print_r($fields, true));
```

---

## Performance Optimization

### Database Queries
All Resos data is cached in transients:
- Reduces external API calls
- Improves response times
- Configurable durations per data type

### Best Practices
1. **Use transients** for all Resos API data
2. **Set appropriate expiry** based on data volatility
3. **Handle cache misses** gracefully
4. **Log cache hits/misses** for monitoring
5. **Invalidate cache** when data changes

### Monitoring
```php
// Add to fetch methods
error_log('BMA: Cache HIT for opening_hours_' . $date);
error_log('BMA: Cache MISS for opening_hours_' . $date);
```

---

## Security Considerations

### Input Sanitization
```php
$date = sanitize_text_field($_POST['date']);
$people = intval($_POST['people']);
$email = sanitize_email($_POST['email']);
```

### Output Escaping
```php
echo esc_html($guest_name);
echo esc_attr($booking_id);
echo esc_url($redirect_url);
```

### Authentication
- All endpoints require WordPress authentication
- Application Passwords only (no regular passwords)
- Check `is_user_logged_in()` or manual auth

### API Key Storage
```php
// Never expose in frontend
$resos_api_key = get_option('hotel_booking_resos_api_key');

// Store securely in WordPress options
update_option('hotel_booking_resos_api_key', $api_key);
```

---

## Troubleshooting

### Resos API Returns 401 Unauthorized

**Symptom:**
- Opening hours, dietary choices, or available times fail to load
- Console shows: `BMA: Opening hours fetch failed with status: 401`
- Error message: "Unauthorized: API key is malformed"

**Root Cause:**
Resos API requires Basic authentication with base64-encoded API key, but the code was using Bearer tokens.

**Error Message from Resos:**
```
Unauthorized: API key is malformed (Remember to pass it as the username, not the password. Remember to base64 encode it.)
```

**Solution:**
Update all Resos API calls in `class-bma-booking-actions.php`:

```php
// Before (WRONG):
'Authorization' => 'Bearer ' . $resos_api_key,

// After (CORRECT):
'Authorization' => 'Basic ' . base64_encode($resos_api_key . ':'),
```

**Affected Methods:**
- `fetch_opening_hours()` - Line 852
- `fetch_available_times()` - Line 925
- `fetch_special_events()` - Line 1012
- `fetch_dietary_choices()` - Line 1071
- All booking creation/update methods

**Verification:**
Test the API key directly with curl:
```bash
# Replace YOUR_API_KEY with your actual Resos API key
curl "https://api.resos.com/v1/customFields" \
  -H "Authorization: Basic $(echo -n 'YOUR_API_KEY:' | base64)"
```

Expected result: HTTP 200 with JSON array of custom fields

**History:**
- Issue discovered: 2025-01-09
- Fixed in commit: de4a091
- Documented in: CHANGELOG.md (lines 15-22)

---

### Opening Hours Returns 404 Not Found

**Symptom:**
- Opening hours endpoint returns 404 error
- Console shows: `Opening hours response: {success: false, message: 'No opening hours found', data: []}`
- Server logs show: `BMA: Opening hours fetch failed with status: 404`

**Root Cause:**
The Resos API does NOT support date-specific endpoints like `/openingHours/2026-01-31`. The plugin was incorrectly constructing URLs with the date in the path.

**Incorrect Implementation:**
```php
$url = 'https://api.resos.com/v1/openingHours/' . $date;  // Returns 404
```

**Correct Implementation:**
```php
// Fetch ALL opening hours (no date in URL)
$url = 'https://api.resos.com/v1/openingHours?showDeleted=false&onlySpecial=false&type=restaurant';

// Then filter client-side by day-of-week
$day_of_week = date('N', strtotime($date));  // 1=Monday, 7=Sunday
```

**Solution:**
The plugin now:
1. Fetches ALL opening hours from the general endpoint
2. Caches the complete list (1 hour)
3. Filters by day-of-week when specific date requested
4. Checks for special date overrides first

**Affected Methods:**
- `fetch_opening_hours()` - Now calls helper methods
- `get_all_opening_hours()` - Fetches and caches all hours (NEW)
- `filter_opening_hours_for_date()` - Filters by date (NEW)

**Verification:**
After deploying the fix, check server logs for:
```
BMA: Cached X opening hours entries
BMA: Found X regular opening hours for 2026-01-31 (day 6)
```

**History:**
- Issue discovered: 2025-01-09
- Fixed in: (current update)
- Management plugin uses same approach

---

### Available Times Returns 404 Not Found

**Symptom:**
- Time slot buttons don't populate in create booking form
- Server logs show: `BMA: Available times fetch failed with status: 404`
- URL in logs: `https://api.resos.com/v1/openingHours/2026-01-30?people=4&expand=availableTimes&areaId=X`

**Root Cause:**
The Resos API does NOT support the `/openingHours/{date}` endpoint with `expand=availableTimes` parameter. The correct endpoint is `/bookingFlow/times`.

**Incorrect Implementation:**
```php
$url = 'https://api.resos.com/v1/openingHours/' . $date . '?people=4&expand=availableTimes';
```

**Correct Implementation:**
```php
$url = 'https://api.resos.com/v1/bookingFlow/times';
$url .= '?date=' . urlencode($date);
$url .= '&people=' . intval($people);
if ($area_id) {
    $url .= '&areaId=' . urlencode($area_id);
}
```

**Solution:**
Changed from `/openingHours/{date}` to `/bookingFlow/times` with date as query parameter.

**Affected Methods:**
- `fetch_available_times()` - Line ~1000-1007

**Verification:**
After deploying, check server logs for:
```
BMA: Fetching available times from Resos API: https://api.resos.com/v1/bookingFlow/times?date=2026-01-30&people=4&areaId=X
```

Should return HTTP 200 with array of opening hour periods containing `availableTimes` arrays.

**History:**
- Issue discovered: 2025-01-09
- Fixed in: (current update)
- Related to opening hours 404 fix

---

### WordPress Endpoint Returns 401
- Check Application Password is correct
- Verify Authorization header is present
- Ensure user has appropriate capabilities

### Cache Not Updating
- Check transient expiry time
- Manually delete transient
- Verify cache key matches

### Custom Fields Not Saving
- Check field name matches exactly (including spaces!)
- Verify choice IDs are correct
- Check Resos API response format

### Gantt Chart Not Displaying
- Check bookings data format
- Verify opening hours structure
- Inspect browser console for JavaScript errors
- Check chart container element exists

---

## File Structure

```
booking-match-api/
├── booking-match-api.php           # Main plugin file
├── includes/
│   ├── class-bma-rest-controller.php      # REST API endpoints
│   ├── class-bma-booking-actions.php      # Core business logic
│   ├── class-bma-gantt-chart.php          # Chart generator
│   ├── class-bma-matcher.php              # Matching algorithm
│   └── class-bma-response-formatter.php   # HTML formatting
├── templates/
│   └── chrome-sidepanel-response.php      # Extension UI template
└── reference/
    ├── DEVELOPER_NOTES.md          # This file
    ├── API_ENDPOINTS.md            # Detailed API docs
    ├── GANTT_CHART_CLASS.md        # Chart usage guide
    └── BOOKING_CREATION_FLOW.md    # End-to-end flow
```

---

## Changelog

### Version 1.1.0 (Current)
- Added Gantt chart generator class
- Added 4 new API endpoints (opening-hours, available-times, dietary-choices, special-events)
- Added reusable Resos API methods in booking actions
- Enhanced create booking form in extension template
- Added context-aware response formatting
- Improved caching strategy

### Version 1.0.0
- Initial release
- Basic booking matching
- Create/update booking endpoints
- Chrome extension integration
