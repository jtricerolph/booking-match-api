# Template Reference

Complete documentation for all template files used in the Booking Match API plugin.

## Table of Contents

- [Overview](#overview)
- [Template Files](#template-files)
- [Common Variables](#common-variables)
- [Data Structures](#data-structures)
- [Customization Guide](#customization-guide)
- [CSS Classes Reference](#css-classes-reference)

---

## Overview

The Booking Match API plugin uses PHP templates to render HTML responses for different contexts. Templates are located in the `templates/` directory and are loaded dynamically based on the `context` parameter in API requests.

### Template Loading Process

1. Client sends request with `context` parameter
2. `BMA_Response_Formatter` processes the results
3. Template file is loaded based on context
4. Variables are passed to template scope
5. HTML is rendered and returned to client

---

## Template Files

### chrome-sidepanel-response.php

**Purpose:** Displays booking list for Chrome extension sidepanel

**Context:** `chrome-sidepanel`

**Location:** `templates/chrome-sidepanel-response.php`

**Used By:** Chrome extension sidepanel (Summary tab)

**Variables Available:**

| Variable | Type | Description |
|----------|------|-------------|
| `$bookings` | array | Array of processed bookings with match details |

**Booking Structure:**
```php
array(
    'booking_id' => 12345,
    'guest_name' => 'John Smith',
    'arrival_date' => '2025-11-20',
    'departure_date' => '2025-11-22',
    'nights' => 2,
    'status' => 'confirmed',
    'group_id' => null,
    'booking_placed' => '2025-11-01 10:30:00',
    'booking_cancelled' => null,
    'is_cancelled' => false,
    'occupants' => array(
        'adults' => 2,
        'children' => 1,
        'infants' => 0
    ),
    'tariffs' => array('BB', 'Dinner'),
    'booking_source' => 'Booking.com',
    'critical_count' => 1,
    'warning_count' => 0,
    'match_details' => array(
        'nights' => array(
            array(
                'date' => '2025-11-20',
                'has_package' => true,
                'is_stale' => false,
                'resos_matches' => array(...)
            )
        )
    )
)
```

**Features:**
- Expandable booking cards
- Night-by-night restaurant status
- Issue badges (critical/warning)
- "New booking" highlighting (placed/cancelled within 24 hours)
- Status badges (confirmed, arrived, departed, cancelled)
- Group ID badges for grouped bookings
- Deep links to restaurant tab
- Time since placed/cancelled display

**CSS Classes:**
- `.bma-summary` - Container
- `.booking-card` - Individual booking card
- `.booking-card.new-booking` - Recently placed/cancelled
- `.booking-card.cancelled-booking` - Cancelled booking
- `.booking-header` - Clickable header
- `.booking-details` - Expandable details
- `.status-badge` - Status indicator
- `.issue-count-badge` - Issue counter
- `.night-row` - Restaurant status row
- `.clickable-issue` - Clickable to navigate to restaurant tab

**Example Usage:**
```php
// In formatter
$template_file = BMA_PLUGIN_DIR . 'templates/chrome-sidepanel-response.php';
include $template_file;
```

---

### chrome-summary-response.php

**Purpose:** Compact summary display for Chrome extension

**Context:** `chrome-extension` (legacy), `chrome-sidepanel` (Summary tab)

**Location:** `templates/chrome-summary-response.php`

**Variables Available:**

| Variable | Type | Description |
|----------|------|-------------|
| `$bookings` | array | Processed bookings array |

**Similar to chrome-sidepanel-response.php** - Same structure and features.

---

### chrome-staying-response.php

**Purpose:** Displays bookings staying on a specific date with Gantt-style timeline visualization

**Context:** `chrome-sidepanel` (Staying tab)

**Location:** `templates/chrome-staying-response.php`

**Variables Available:**

| Variable | Type | Description |
|----------|------|-------------|
| `$bookings` | array | Bookings staying on the date (includes vacant rooms) |
| `$departing_bookings` | array | Bookings departing on the date (for stats only) |
| `$date` | string | Target date (YYYY-MM-DD) |

**Booking Structure:**
```php
array(
    'booking_id' => 12345,
    'guest_name' => 'John Smith',
    'site_name' => '101',          // Room number
    'arrival_date' => '2025-11-19',
    'departure_date' => '2025-11-22',
    'nights' => 3,
    'current_night' => 2,           // Which night of the stay
    'status' => 'arrived',
    'group_id' => null,
    'occupants' => array(...),
    'tariffs' => array('BB'),
    'booking_source' => 'Direct',
    'critical_count' => 0,
    'warning_count' => 0,
    'has_package' => false,
    'is_stale' => false,
    'resos_matches' => array(...),
    'custom_fields' => array(
        array(
            'label' => 'Bed Type',
            'value' => 'Twin'
        )
    ),
    // Gantt timeline data
    'previous_night_status' => 'arrived',
    'next_night_status' => 'arrived',
    'spans_from_previous' => true,  // Multi-night booking continuing from yesterday
    'spans_to_next' => true,        // Multi-night booking continuing to tomorrow
    'previous_vacant' => false,     // Room was vacant yesterday
    'next_vacant' => false,         // Room will be vacant tomorrow
    // Vacant room entry
    'is_vacant' => false            // Set to true for vacant room entries
)
```

**Features:**
- Stats row with filters (departures, stopovers, arrivals, in-house, occupancy, twins, restaurant)
- Room number badges colored by status
- Night progress indicator (night 2/3)
- Gantt-style timeline indicators showing previous/next night bookings
- Vacant room lines
- Restaurant status per booking
- Group highlighting
- Expandable booking details

**Stats Row:**
- Departs: Count departing (or departed/total if today)
- Stopovers: Arrived before date, departing after
- Arrivals: Count arriving (or arrived/total if today)
- In-house: Total non-vacant bookings
- Occupancy: Adults+children (e.g., "15+3")
- Twins: Twin bed bookings
- Restaurant: Bookings with matches / total (e.g., "12/15")

**Timeline Visualization:**
- Colored boxes on left/right indicate different bookings on adjacent nights
- Gray outlines indicate vacant adjacent nights
- Header extends left/right for multi-night bookings
- Status-colored borders (green=confirmed, blue=arrived, purple=departed, etc.)

**CSS Classes:**
- `.staying-stats-row` - Stats filter bar
- `.stat-filter` - Clickable stat (filter)
- `.stat-filter.active` - Active filter
- `.staying-card` - Booking card
- `.staying-header` - Card header with status border
- `.vacant-room-line` - Vacant room entry
- `.room-number` - Room badge (colored by status)
- `.night-progress` - Night indicator
- `.group-id-badge` - Group ID badge

**Data Attributes:**
```html
<div class="staying-card"
     data-booking-id="12345"
     data-status="arrived"
     data-previous-status="confirmed"
     data-next-status="arrived"
     data-spans-previous="true"
     data-spans-next="true"
     data-is-arriving="false"
     data-is-departing="false"
     data-is-stopover="true"
     data-has-twin="false"
     data-has-restaurant-match="true"
     data-group-id="5">
```

---

### chrome-extension-response.php

**Purpose:** Legacy template for Chrome extension popup/iframe

**Context:** `chrome-extension`

**Location:** `templates/chrome-extension-response.php`

**Note:** Deprecated in favor of chrome-sidepanel-response.php

---

### webapp-restaurant-response.php

**Purpose:** Mobile-optimized restaurant booking view

**Context:** `webapp-restaurant`

**Location:** `templates/webapp-restaurant-response.php`

**Variables Available:**

| Variable | Type | Description |
|----------|------|-------------|
| `$results` | array | Match results from matcher |
| `$search_method` | string | Search method used |

**Features:**
- Mobile-optimized layout
- Touch-friendly buttons
- Restaurant booking details
- Comparison data
- Action buttons (update, create)

---

### webapp-summary-response.php

**Purpose:** Mobile-optimized summary view

**Context:** `webapp`

**Location:** `templates/webapp-summary-response.php`

**Features:**
- Mobile-optimized card layout
- Summary statistics
- Quick action buttons

---

### webapp-checks-response.php

**Purpose:** Mobile-optimized checks/issues view

**Context:** `webapp-checks`

**Location:** `templates/webapp-checks-response.php`

**Features:**
- Issue categorization
- Priority indicators
- Action buttons

---

## Common Variables

These variables are commonly available across multiple templates:

### Booking Object

```php
array(
    // Identity
    'booking_id' => int,
    'booking_reference' => string,
    'group_id' => int|null,

    // Guest
    'guest_name' => string,
    'phone' => string,
    'email' => string,

    // Dates
    'arrival_date' => string,      // YYYY-MM-DD
    'departure_date' => string,    // YYYY-MM-DD
    'nights' => int,
    'current_night' => int,

    // Room
    'site_name' => string,          // Room number

    // Status
    'status' => string,             // confirmed, arrived, departed, cancelled, etc.
    'booking_source' => string,     // Booking.com, Direct, etc.
    'is_cancelled' => bool,

    // Timestamps
    'booking_placed' => string,     // YYYY-MM-DD HH:MM:SS
    'booking_cancelled' => string|null,

    // Occupancy
    'occupants' => array(
        'adults' => int,
        'children' => int,
        'infants' => int
    ),

    // Tariffs
    'tariffs' => array,             // ['BB', 'Dinner']

    // Issues
    'critical_count' => int,
    'warning_count' => int,

    // Match details
    'match_details' => array(...)
)
```

### Match Details Structure

```php
array(
    'nights' => array(
        array(
            'date' => string,               // YYYY-MM-DD
            'has_package' => bool,
            'is_stale' => bool,            // Using cached data
            'resos_matches' => array(
                array(
                    'resos_booking_id' => string,
                    'restaurant_id' => string,
                    'guest_name' => string,
                    'people' => int,
                    'time' => string,       // HH:MM or ISO timestamp
                    'status' => string,
                    'is_hotel_guest' => bool,
                    'is_dbb' => bool,
                    'booking_number' => string,
                    'score' => int,
                    'has_suggestions' => bool,
                    'is_orphaned' => bool,
                    'match_info' => array(
                        'matched' => bool,
                        'match_type' => string,     // booking_id, agent_ref, composite
                        'confidence' => string,     // high, medium, low
                        'is_primary' => bool,
                        'match_label' => string,
                        'is_group_member' => bool,
                        'lead_booking_room' => string|null
                    )
                )
            )
        )
    )
)
```

### Resos Match Object

```php
array(
    'resos_booking_id' => string,
    'restaurant_id' => string,
    'guest_name' => string,
    'people' => int,
    'time' => string,
    'status' => string,             // confirmed, request, cancelled
    'is_hotel_guest' => bool,
    'is_dbb' => bool,
    'booking_number' => string,
    'score' => int,
    'has_suggestions' => bool,      // Has suggested updates
    'is_orphaned' => bool,          // Resos booking for cancelled hotel booking
    'match_info' => array(
        'matched' => bool,
        'match_type' => string,
        'confidence' => string,
        'is_primary' => bool,
        'match_label' => string,
        'is_group_member' => bool,
        'lead_booking_room' => string
    )
)
```

---

## Data Structures

### Status Values

**Hotel Booking Status:**
- `confirmed` - Confirmed booking
- `provisional` - Provisional/unconfirmed
- `arrived` - Guest has checked in
- `departed` - Guest has checked out
- `cancelled` - Booking cancelled

**Resos Booking Status:**
- `confirmed` - Confirmed reservation
- `request` - Pending confirmation
- `cancelled` - Cancelled reservation
- `seated` - Guest seated
- `finished` - Completed

### Match Types

- `booking_id` - Matched by booking ID in custom field
- `agent_ref` - Matched by agent reference
- `composite` - Matched by multiple factors (name, phone, email)
- `group` - Group booking member
- `individual` - Individual match in GROUP/EXCLUDE field

### Confidence Levels

- `high` - Primary match (booking ID, agent ref, or high composite score)
- `medium` - Moderate confidence (medium composite score)
- `low` - Low confidence (low composite score)

### Booking Sources

Common sources detected by the system:
- `Booking.com`
- `Expedia`
- `Airbnb`
- `Direct`
- `Phone`
- `Walk-in`
- `Travel Agent`

---

## Customization Guide

### Creating a Custom Template

1. **Create template file** in `templates/` directory:
```php
// templates/custom-response.php
<?php
/**
 * Custom Response Template
 */

// Access variables
foreach ($bookings as $booking) {
    echo '<div class="custom-booking">';
    echo '<h3>' . esc_html($booking['guest_name']) . '</h3>';
    // ... your custom HTML
    echo '</div>';
}
?>

<style>
.custom-booking {
    padding: 20px;
    border: 1px solid #ccc;
}
</style>
```

2. **Register custom context** in formatter:
```php
// In class-bma-response-formatter.php
public function format_response($results, $search_method, $context = 'json') {
    if ($context === 'custom') {
        return $this->format_html_response($results, $search_method, 'custom');
    }
    // ...
}

private function format_html_response($results, $search_method, $context = 'chrome-extension') {
    // ...
    if ($context === 'custom') {
        $template_file = BMA_PLUGIN_DIR . 'templates/custom-response.php';
    }
    // ...
}
```

3. **Use custom context** in API request:
```bash
curl -X POST https://example.com/wp-json/bma/v1/bookings/match \
  -H "Authorization: Basic ..." \
  -d '{"email_address": "john@example.com", "context": "custom"}'
```

### Modifying Existing Templates

**Best Practice:** Create a child template or use WordPress filters instead of modifying core files.

**Option 1: Filter Template Path**
```php
add_filter('bma_template_path', function($template_path, $context) {
    if ($context === 'chrome-sidepanel') {
        return '/path/to/custom/chrome-sidepanel-response.php';
    }
    return $template_path;
}, 10, 2);
```

**Option 2: Extend Template**
```php
// In your custom template
<?php
// Load base template
$base_template = BMA_PLUGIN_DIR . 'templates/chrome-sidepanel-response.php';
ob_start();
include $base_template;
$base_html = ob_get_clean();

// Modify HTML
$custom_html = str_replace('<div class="bma-summary">', '<div class="bma-summary custom-class">', $base_html);
echo $custom_html;
?>
```

### Adding Custom Data to Templates

**Filter booking data** before template renders:
```php
add_filter('bma_format_booking_data', function($booking_data) {
    // Add custom field
    $booking_data['custom_field'] = 'Custom Value';
    return $booking_data;
});
```

**Access in template:**
```php
<?php foreach ($bookings as $booking): ?>
    <div><?php echo esc_html($booking['custom_field']); ?></div>
<?php endforeach; ?>
```

---

## CSS Classes Reference

### Common Classes

#### Layout
- `.bma-summary` - Main container
- `.booking-card` - Individual booking card
- `.booking-header` - Clickable card header
- `.booking-details` - Expandable details section
- `.booking-main-info` - Main info area
- `.staying-card` - Staying tab booking card
- `.staying-header` - Staying card header
- `.vacant-room-line` - Vacant room entry

#### States
- `.expanded` - Expanded card
- `.new-booking` - Recently placed/cancelled (24h)
- `.cancelled-booking` - Cancelled booking
- `.highlighted` - Highlighted (group hover)

#### Status Badges
- `.status-badge` - Generic status badge
- `.status-confirmed` - Confirmed status (green)
- `.status-unconfirmed` - Unconfirmed status (amber)
- `.status-arrived` - Arrived status (blue)
- `.status-departed` - Departed status (purple)
- `.status-cancelled` - Cancelled status (red)

#### Issue Indicators
- `.issue-count-badge` - Issue counter badge
- `.critical-badge` - Critical issue (red)
- `.warning-badge` - Warning issue (amber)
- `.night-alert` - Night-level alert
- `.critical-alert` - Critical alert message
- `.warning-alert` - Warning alert message

#### Interactive Elements
- `.clickable-issue` - Clickable row (navigate to restaurant)
- `.resos-deep-link` - Deep link to ResOS
- `.create-booking-link` - Create booking action
- `.clickable-status` - Clickable status link
- `.restaurant-header-link` - Restaurant section header link
- `.checks-header-link` - Checks section header link

#### Restaurant Status
- `.restaurant-status` - Restaurant status display
- `.has-booking` - Has restaurant booking
- `.no-booking` - No restaurant booking
- `.has-package` - Has package (critical if no booking)
- `.has-updates` - Has suggested updates
- `.has-issue` - Has issue/warning
- `.group-member-status` - Group booking member

#### Stats (Staying Tab)
- `.staying-stats-row` - Stats filter bar
- `.stat-item` - Individual stat
- `.stat-filter` - Clickable filter stat
- `.stat-filter.active` - Active filter
- `.stat-value` - Stat value text
- `.stat-divider` - Stat separator

#### Details Sections
- `.compact-details` - Compact details section
- `.compact-row` - Detail row
- `.detail-section` - Detail section container
- `.detail-separator` - Visual separator
- `.detail-actions` - Action buttons area

#### Badges and Icons
- `.nights-badge` - Night count badge
- `.group-id-badge` - Group ID badge
- `.room-number` - Room number badge (status-colored)
- `.expand-icon` - Expand/collapse icon
- `.status-icon` - Status icon container
- `.stale-indicator` - Stale cache indicator

#### Timeline (Staying Tab)
- Booking cards use `::before` and `::after` pseudo-elements for timeline indicators
- Left/right colored boxes show adjacent night statuses
- Header extends left/right for multi-night stays

### CSS Variables

Templates use inline styles, but you can define CSS variables for customization:

```css
:root {
    --bma-primary-color: #3182ce;
    --bma-success-color: #10b981;
    --bma-warning-color: #f59e0b;
    --bma-danger-color: #dc2626;
    --bma-info-color: #3b82f6;
    --bma-gray: #6b7280;
    --bma-border: #e2e8f0;
}
```

### Responsive Design

Templates are designed for specific contexts:
- **chrome-sidepanel-response.php**: Fixed-width sidepanel (320-400px)
- **chrome-staying-response.php**: Fixed-width sidepanel with horizontal timeline indicators
- **webapp-*.php**: Mobile-first responsive (320px+)

**Media query example:**
```css
@media (max-width: 360px) {
    .booking-card {
        padding: 8px;
    }
}
```

---

## Template Variables Quick Reference

### chrome-sidepanel-response.php
```php
$bookings = array(/* booking objects */);
```

### chrome-staying-response.php
```php
$bookings = array(/* staying bookings with vacant entries */);
$departing_bookings = array(/* departing bookings */);
$date = '2025-11-20';
```

### webapp-restaurant-response.php
```php
$results = array(/* match results */);
$search_method = 'email';
```

---

## Example: Complete Template Usage

```php
// API Request
$response = wp_remote_post('https://example.com/wp-json/bma/v1/bookings/match', array(
    'headers' => array(
        'Authorization' => 'Basic ' . base64_encode('username:password'),
        'Content-Type' => 'application/json'
    ),
    'body' => json_encode(array(
        'email_address' => 'john@example.com',
        'context' => 'chrome-sidepanel'
    ))
));

// Returns HTML from chrome-sidepanel-response.php
$html = wp_remote_retrieve_body($response);

// Inject into page
echo '<div id="booking-results">' . $html . '</div>';
```

---

## Debugging Templates

### Enable Debug Mode

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Template Variable Inspection

Add to template:
```php
<?php
// Dump all available variables
error_log('BMA Template Variables: ' . print_r(get_defined_vars(), true));
?>
```

### Common Issues

**Issue:** Template not loading
- Check file path: `templates/template-name.php`
- Verify context parameter matches registered context
- Check file permissions (644)

**Issue:** Variables undefined
- Ensure variables are passed to template scope in formatter
- Check template file has access to required data structures

**Issue:** CSS not applying
- Styles are inline in templates
- Check for conflicting parent styles
- Use browser DevTools to inspect elements

---

## Best Practices

1. **Always escape output**: Use `esc_html()`, `esc_attr()`, `esc_url()`
2. **Check for empty data**: Use `empty()`, `isset()` before accessing arrays
3. **Provide fallbacks**: Default values for missing data
4. **Use semantic HTML**: Proper heading hierarchy, ARIA labels
5. **Mobile-first**: Design for smallest screen first
6. **Performance**: Minimize inline styles, use CSS classes
7. **Accessibility**: Keyboard navigation, screen reader support
8. **Internationalization**: Use translation functions if needed

---

## Related Documentation

- [REST_API_ENDPOINTS.md](REST_API_ENDPOINTS.md) - API endpoint details
- [API_REFERENCE.md](API_REFERENCE.md) - Class and method reference
- [ARCHITECTURE.md](ARCHITECTURE.md) - System architecture
