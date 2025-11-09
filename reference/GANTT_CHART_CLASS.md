# Gantt Chart Class - Usage Guide

Complete documentation for the `BMA_Gantt_Chart` class - a reusable restaurant booking timeline generator.

## Overview

The Gantt Chart class generates visual timelines showing restaurant bookings throughout a day. It supports multiple display modes, windowed viewports, and smart positioning to prevent overlaps.

## Class Location

```
booking-match-api/includes/class-bma-gantt-chart.php
```

## Basic Usage

```php
require_once plugin_dir_path(__FILE__) . 'includes/class-bma-gantt-chart.php';

$gantt = new BMA_Gantt_Chart();
$html = $gantt
    ->set_bookings($restaurant_bookings)
    ->set_opening_hours($opening_hours)
    ->generate();

echo $html;
```

---

## Display Modes

### MODE_FULL - Full Detail View

**Best for:** Large displays, detailed management interfaces

**Characteristics:**
- Bar Height: 40px
- Grid Row Height: 14px
- Shows: Guest names, room numbers, party size
- Font Size: 13px
- Tooltips: Enabled

```php
$gantt->set_display_mode(BMA_Gantt_Chart::MODE_FULL);
```

**Example Output:**
```
┌────────────────────────────────────┐
│ 4  John Smith - Room 201         │  ← 40px high
├────────────────────────────────────┤
│ 2  Jane Doe - Non-Resident       │
└────────────────────────────────────┘
```

### MODE_MEDIUM - Reduced Detail View

**Best for:** Tablets, medium-sized displays

**Characteristics:**
- Bar Height: 28px
- Grid Row Height: 10px
- Shows: Guest names only (no room numbers)
- Font Size: 11px
- Tooltips: Enabled

```php
$gantt->set_display_mode(BMA_Gantt_Chart::MODE_MEDIUM);
```

**Example Output:**
```
┌──────────────────────┐
│ 4  John Smith       │  ← 28px high
├──────────────────────┤
│ 2  Jane Doe         │
└──────────────────────┘
```

### MODE_COMPACT - Minimal View

**Best for:** Mobile devices, sidebars, Chrome extension

**Characteristics:**
- Bar Height: 14px
- Grid Row Height: 7px
- Shows: Party size badge only (no names)
- Font Size: 10px
- Tooltips: Enabled

```php
$gantt->set_display_mode(BMA_Gantt_Chart::MODE_COMPACT);
```

**Example Output:**
```
┌──────┐
│  4  │  ← 14px high, badge only
├──────┤
│  2  │
└──────┘
```

---

## Viewport Modes

### Full Day View (Default)

Shows entire restaurant day with horizontal scrollbar if needed.

```php
$gantt
    ->set_display_mode(BMA_Gantt_Chart::MODE_FULL)
    ->set_viewport_hours(null);  // Full day
```

### Windowed View

Shows a scrollable window of specified hours.

```php
$gantt
    ->set_display_mode(BMA_Gantt_Chart::MODE_COMPACT)
    ->set_viewport_hours(4)              // 4-hour viewport
    ->set_initial_center_time('1900');   // Center on 19:00
```

**Features:**
- Left/right scroll arrows
- Smooth scrolling animation
- Auto-centers on specified time
- JavaScript integration for time slot hover

---

## Complete Configuration Example

```php
// Prepare data
$restaurant_bookings = array(
    'today' => array(
        array(
            'resos_booking' => array(
                'time' => '18:00',
                'people' => 4,
                'name' => 'John Smith',
                'room' => 'Room 201',
                'notes' => array('Anniversary'),
                'tables' => array('Table 5')
            )
        ),
        array(
            'resos_booking' => array(
                'time' => '19:30',
                'people' => 2,
                'name' => 'Jane Doe',
                'room' => 'Non-Resident',
                'notes' => array(),
                'tables' => array()
            )
        )
    )
);

$opening_hours = array(
    array(
        '_id' => '507f1f77bcf86cd799439011',
        'name' => 'Dinner Service',
        'open' => 1800,    // 18:00
        'close' => 2200,   // 22:00
        'interval' => 15,
        'duration' => 120
    )
);

$available_times = array('18:00', '18:15', '18:30', '19:00', '19:15', '19:30');

$special_events = array(
    array(
        'name' => 'Private Event',
        'open' => 2000,    // 20:00
        'close' => 2100,   // 21:00
        'isOpen' => false
    )
);

// Generate chart
$gantt = new BMA_Gantt_Chart();
$html = $gantt
    ->set_bookings($restaurant_bookings)
    ->set_opening_hours($opening_hours)
    ->set_available_times($available_times)
    ->set_special_events($special_events)
    ->set_online_booking_available(true)
    ->set_display_mode(BMA_Gantt_Chart::MODE_COMPACT)
    ->set_viewport_hours(4)
    ->set_initial_center_time('1900')
    ->set_chart_id('gantt-2025-01-15')
    ->generate();

echo $html;
```

---

## Data Format Specifications

### Bookings Data

**Structure:**
```php
array(
    'category_key' => array(
        array(
            'resos_booking' => array(  // or direct booking object
                'time' => 'HH:MM',            // Required
                'people' => integer,           // Required
                'name' => string,              // Required
                'room' => string,              // Optional
                'notes' => array,              // Optional
                'tables' => array              // Optional
            )
        )
    )
)
```

**Example:**
```php
array(
    'matched' => array(
        array(
            'resos_booking' => array(
                'time' => '19:00',
                'people' => 4,
                'name' => 'John Smith',
                'room' => 'Room 201',
                'notes' => array('Anniversary', 'Window table'),
                'tables' => array('Table 5', 'Table 6')
            )
        )
    ),
    'unmatched' => array(
        array(
            'time' => '20:00',  // Can also be direct format
            'people' => 2,
            'name' => 'Jane Doe',
            'room' => 'Non-Resident'
        )
    )
)
```

### Opening Hours Data

**Structure:**
```php
array(
    array(
        '_id' => string,        // Unique ID
        'name' => string,       // Display name
        'open' => integer,      // HHMM format (1800 = 18:00)
        'close' => integer,     // HHMM format (2200 = 22:00)
        'interval' => integer,  // Minutes (15)
        'duration' => integer   // Minutes (120)
    )
)
```

**Example:**
```php
array(
    array(
        '_id' => '507f...',
        'name' => 'Lunch Service',
        'open' => 1200,
        'close' => 1430,
        'interval' => 15,
        'duration' => 90
    ),
    array(
        '_id' => '508f...',
        'name' => 'Dinner Service',
        'open' => 1800,
        'close' => 2200,
        'interval' => 15,
        'duration' => 120
    )
)
```

### Available Times Data

**Structure:**
```php
array('HH:MM', 'HH:MM', ...)
```

**Example:**
```php
array(
    '18:00', '18:15', '18:30', '18:45',
    '19:00', '19:15', '19:30', '19:45',
    '20:00'  // 20:15 and 20:30 are fully booked
)
```

### Special Events Data

**Structure:**
```php
array(
    array(
        'name' => string,
        'open' => integer|null,   // HHMM or null for full day
        'close' => integer|null,  // HHMM or null for full day
        'isOpen' => boolean       // true = special opening, false = closure
    )
)
```

**Example:**
```php
array(
    array(
        'name' => 'Private Event',
        'open' => 2000,
        'close' => 2100,
        'isOpen' => false  // Closed from 20:00-21:00
    ),
    array(
        'name' => 'Closed All Day',
        'open' => null,
        'close' => null,
        'isOpen' => false  // Entire day closed
    )
)
```

---

## Visual Elements Generated

### Time Axis
Half-hourly labels across the top:
```
18:00  18:30  19:00  19:30  20:00  20:30  21:00  21:30
```

### Booking Bars
Colored bars with:
- **Position:** Calculated from time and duration
- **Width:** Based on booking duration (default 2 hours)
- **Height:** Based on party size and display mode
- **Content:** Party size badge + text (if mode allows)
- **Color:** Gradient purple (customizable via CSS)

**HTML Structure:**
```html
<div class="gantt-booking-bar"
     data-name="John Smith"
     data-people="4"
     data-time="19:00"
     data-room="Room 201"
     data-notes='["Anniversary"]'
     data-tables='["Table 5"]'
     style="left: 16.67%; top: 22px; width: 33.33%; height: 24px;">
  <span class="gantt-party-size">4</span>
  <span class="gantt-bar-text">John Smith - Room 201</span>
</div>
```

### Grey Overlay Blocks

**Outside Opening Hours:**
```
Before 18:00 and after 22:00 = grey overlay
```

**Special Events:**
```
Private event 20:00-21:00 = grey overlay during those times
```

**Fully Booked Times:**
```
20:15 and 20:30 not in available times = grey overlay at those intervals
```

**Online Booking Closed:**
```
If online_booking_available = false, entire day = grey overlay
```

### Interval Lines
Vertical lines every 15 minutes for time reference.

### Sight Line
Red vertical line shown when hovering over time slot buttons (controlled by JavaScript).

---

## Grid-Based Positioning Algorithm

### How It Works

1. **Sort** bookings by start time (earliest first)

2. **Calculate Row Span** based on party size:
   ```
   1-3 people  = 2 rows
   4-5 people  = 3 rows
   6-7 people  = 4 rows
   8-9 people  = 5 rows
   10-11 people = 6 rows
   ... up to 20 people = 11 rows
   ```

3. **Find Placement:**
   - Start at grid row 0
   - Check if booking fits (all required rows free with 5-min buffer)
   - If yes, place and mark rows as occupied
   - If no, try next grid row
   - Repeat until placed

4. **Mark Occupied:**
   ```php
   $grid_rows[$row]['occupied'][] = array(
       'start' => $booking_start_minutes,
       'end' => $booking_end_minutes
   );
   ```

5. **Calculate Position:**
   ```php
   $y_position = 10 + ($grid_row * $grid_row_height);
   $bar_height = ($row_span * $grid_row_height) - 4;  // -4 for borders/gap
   ```

### Example

```
Party Size 4 (3 rows) booking at 18:00:
┌────────┬────────┬────────┬────────┐
│ Row 0  │ [===== 4 people =====]    │  ← Placed here
│ Row 1  │ [==================]      │
│ Row 2  │ [==================]      │
├────────┼────────┼────────┼────────┤
│ Row 3  │                           │  ← Next booking tries here first
│ Row 4  │                           │
└────────┴────────┴────────┴────────┘
```

### Buffer Zone
5-minute buffer prevents bookings from touching:
```
Booking 1: 18:00-20:00
Buffer: 20:00-20:05
Booking 2: Can start at 20:05 or later in same row
```

---

## JavaScript Integration

### Auto-Center on Load

The generated HTML includes an initialization script:

```javascript
var viewport = document.getElementById("gantt-2025-01-15-viewport");
var centerTime = "1900";  // From set_initial_center_time()

var timeMinutes = parseInt(centerTime.substring(0, 2)) * 60 +
                  parseInt(centerTime.substring(2, 4));
var viewportWidth = viewport.clientWidth;
var totalDayMinutes = 24 * 60;
var scrollPercentage = timeMinutes / totalDayMinutes;
var scrollPosition = (viewport.scrollWidth * scrollPercentage) - (viewportWidth / 2);

viewport.scrollTo({
    left: scrollPosition,
    behavior: "smooth"
});
```

### Scroll Arrows

Buttons are generated with `data-chart-id` attribute:

```html
<button class="gantt-scroll-btn" data-direction="left" data-chart-id="gantt-2025-01-15">◄</button>
```

Attach handlers in your JavaScript:

```javascript
document.querySelectorAll('.gantt-scroll-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const chartId = this.dataset.chartId;
        const direction = this.dataset.direction;
        const viewport = document.getElementById(chartId + '-viewport');

        const scrollAmount = direction === 'left' ? -60 : 60;  // 1 hour in minutes
        const pixelsPerMinute = viewport.scrollWidth / (24 * 60);

        viewport.scrollBy({
            left: scrollAmount * pixelsPerMinute,
            behavior: 'smooth'
        });
    });
});
```

### Time Slot Hover Integration

When hovering over a time slot button, scroll Gantt and show sight line:

```javascript
timeButton.addEventListener('mouseenter', function() {
    const time = this.dataset.time;  // e.g., "1900"
    const chartId = 'gantt-2025-01-15';

    scrollGanttToTime(chartId, time, true);
    showGanttSightLine(chartId, time);
});

function scrollGanttToTime(chartId, time, smooth = true) {
    const viewport = document.getElementById(chartId + '-viewport');
    if (!viewport) return;

    const timeMinutes = parseInt(time.substring(0, 2)) * 60 + parseInt(time.substring(2, 4));
    const viewportWidth = viewport.clientWidth;
    const scrollPercentage = timeMinutes / (24 * 60);
    const scrollPosition = (viewport.scrollWidth * scrollPercentage) - (viewportWidth / 2);

    viewport.scrollTo({
        left: scrollPosition,
        behavior: smooth ? 'smooth' : 'auto'
    });
}

function showGanttSightLine(chartId, time) {
    const viewport = document.getElementById(chartId + '-viewport');
    let sightLine = viewport.querySelector('.gantt-sight-line');

    if (!sightLine) {
        sightLine = document.createElement('div');
        sightLine.className = 'gantt-sight-line';
        viewport.appendChild(sightLine);
    }

    const timeMinutes = parseInt(time.substring(0, 2)) * 60 + parseInt(time.substring(2, 4));
    const leftPercentage = (timeMinutes / (24 * 60)) * 100;

    sightLine.style.left = leftPercentage + '%';
    sightLine.style.display = 'block';
}
```

---

## CSS Customization

The class generates inline styles, but you can override with CSS:

```css
/* Customize booking bar colors */
.gantt-booking-bar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    border-color: #5568d3 !important;
}

/* Customize party size badge */
.gantt-party-size {
    background: rgba(255, 255, 255, 0.3) !important;
    font-weight: bold !important;
}

/* Customize grey overlays */
.gantt-closed-block {
    background: rgba(200, 200, 200, 0.2) !important;
}

.gantt-closed-block.outside-hours {
    background: rgba(100, 100, 100, 0.15) !important;
}

/* Customize sight line */
.gantt-sight-line {
    background: #ef4444 !important;
    width: 3px !important;
}
```

---

## Performance Considerations

### Booking Count
- **<20 bookings:** Excellent performance
- **20-50 bookings:** Good performance
- **50-100 bookings:** Moderate (may have many grid rows)
- **>100 bookings:** Consider pagination or date filtering

### Optimization Tips

1. **Cache Generated HTML:**
   ```php
   $cache_key = 'gantt_' . $date . '_' . md5(serialize($bookings));
   $html = get_transient($cache_key);

   if (!$html) {
       $html = $gantt->generate();
       set_transient($cache_key, $html, 15 * MINUTE_IN_SECONDS);
   }
   ```

2. **Use Compact Mode for Many Bookings:**
   ```php
   $mode = count($all_bookings) > 30
       ? BMA_Gantt_Chart::MODE_COMPACT
       : BMA_Gantt_Chart::MODE_FULL;
   ```

3. **Limit Viewport Hours:**
   ```php
   ->set_viewport_hours(4)  // Reduces rendering complexity
   ```

---

## Troubleshooting

### Chart Not Displaying

**Check:**
1. Bookings data format is correct
2. Opening hours array is not empty
3. Container element exists in DOM
4. JavaScript console for errors

### Bookings Overlapping

**Cause:** Grid algorithm failure (rare)

**Fix:**
- Ensure bookings have valid time format (HH:MM)
- Check party sizes are reasonable (<= 20)
- Verify opening hours define valid time range

### Grey Blocks Incorrect

**Check:**
1. Opening hours `open`/`close` in HHMM format
2. Special events `isOpen` flag is correct
3. Available times array format is HH:MM strings

### Scroll Not Working

**Check:**
1. Viewport mode is enabled (`set_viewport_hours()`)
2. Chart ID matches button `data-chart-id`
3. JavaScript is running after DOM load
4. Viewport has `overflow-x: auto` style

---

## Complete Working Example

```php
<?php
// Fetch data
$actions = new BMA_Booking_Actions();
$date = '2025-01-15';

$bookings = get_restaurant_bookings_for_date($date);  // Your function
$opening_hours = $actions->fetch_opening_hours($date);
$available_times = $actions->fetch_available_times($date, 4, null);
$special_events = $actions->fetch_special_events($date);

// Generate compact Gantt for sidebar
require_once plugin_dir_path(__FILE__) . 'includes/class-bma-gantt-chart.php';

$gantt = new BMA_Gantt_Chart();
$chart_html = $gantt
    ->set_bookings($bookings)
    ->set_opening_hours($opening_hours)
    ->set_available_times($available_times['times'])
    ->set_special_events($special_events)
    ->set_online_booking_available(true)
    ->set_display_mode(BMA_Gantt_Chart::MODE_COMPACT)
    ->set_viewport_hours(4)
    ->set_initial_center_time('1900')
    ->set_chart_id('gantt-' . $date)
    ->generate();

echo $chart_html;
?>
```

---

## Future Enhancements

Potential improvements to consider:

1. **Drag & Drop:** Allow repositioning bookings
2. **Click Handlers:** Attach booking click events
3. **Real-Time Updates:** WebSocket integration
4. **Color Coding:** Different colors for booking status
5. **Table Indicators:** Show table assignments
6. **Zoom Levels:** Dynamic viewport adjustment
7. **Print Optimization:** CSS for printing
8. **Accessibility:** ARIA labels and keyboard navigation
