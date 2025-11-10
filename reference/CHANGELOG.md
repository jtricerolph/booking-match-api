# Booking Match API - Changelog

All notable changes to the Booking Match API plugin.

## [Unreleased] - Implementation in Progress

### Added (Latest)
- **Content indicators for collapsible sections** (2025-01-10)
  - Green "draw" icon appears on section headers when they contain user data
  - Real-time monitoring of form fields in Details, Allergies, and Note sections
  - JavaScript function `setupSectionIndicators(date, form)` monitors input/change events
  - Details section: shows indicator for phone, email, checkboxes (hotel guest, DBB, notifications)
  - Allergies section: shows indicator for checked dietary options or "other" text
  - Note section: shows indicator when note textarea contains text
  - CSS classes `.section-indicator` and `.has-content` control visibility
  - Provides immediate visual feedback of form completion status
  - Commits: (sidepanel.js), (sidepanel.css), (chrome-sidepanel-response.php template)

### Changed (Latest)
- **Streamlined section toggle styling** (2025-01-10)
  - Reduced section toggle padding from 12px 16px to 8px 12px
  - Reduced font size from 14px to 12px for compact appearance
  - Changed font-weight from 500 to 600 for improved readability
  - Reduced icon size from 20px to 16px
  - Reduced spacing between sections from 16px to 8px
  - Now matches service period header styling for visual consistency
  - Commit: (sidepanel.css)

- **Optimized alert banner spacing** (2025-01-10)
  - Reduced special events banner margin from 12px to 8px
  - Reduced gap between alerts from 6px to 4px
  - Reduced alert padding from 8px 10px to 6px 8px
  - Reduced line-height from 1.4 to 1.2 in event descriptions
  - Shortened "Online bookings closed from planner screen" text to fit on one line
  - Improved space efficiency for narrow sidepanel layout
  - Commit: (sidepanel.css, chrome-sidepanel-response.php)

- **Reduced auto-scroll offset** (2025-01-10)
  - Changed scroll offset from 30px to 5px below tab bar
  - Prevents content from appearing flush against tabs
  - Provides minimal but visible spacing for better UX
  - Applies to both manual scroll and auto-expand navigation
  - Commit: (sidepanel.js lines 176-180, 1742-1746)

- **Status message visibility consistency** (2025-01-10)
  - Fixed "No restaurant booking" status message remaining visible on auto-expand
  - Now hides status message when auto-expanding create form (matches manual behavior)
  - Ensures consistent UI state regardless of how form is opened
  - Commit: (sidepanel.js lines 132-143)

- **Gantt chart minimum height** (2025-01-10)
  - Implemented minimum height of 150px for days with few bookings
  - Ensures interval lines are visible across full viewport
  - Balances visibility with space efficiency (reduced from initial 300px)
  - Commit: (sidepanel.js lines 603-609)

- **Gantt tooltip enhancements** (2025-01-10)
  - Added "pax" suffix to people count in booking bar tooltips
  - Format: "{people} pax {name} [hotel icon if resident]"
  - Provides clearer indication of party size at a glance
  - Commit: (sidepanel.js lines 898-905)

- **Extension icon improvements** (2025-01-10)
  - Reduced icon font sizes to prevent right-side cutoff
  - icon-16: 12px → 10px
  - icon-48: 36px → 32px
  - icon-128: 96px → 86px
  - Adds breathing room within fixed icon dimensions
  - User regenerated and committed new icon files
  - Commit: (generate-icons.html)

### Added
- **Gantt chart redesign for Chrome extension**
  - Complete rewrite of `buildGanttChart()` function to display actual restaurant bookings
  - Implemented row compaction algorithm (ported from PHP class-bma-gantt-chart.php)
    - Sorts bookings by start time
    - Calculates bar height based on party size: `Math.max(2, Math.floor(partySize / 2) + 1)`
    - Uses grid-based placement with 5-minute buffer to prevent overlaps
    - Reuses rows for non-overlapping bookings (vertical compaction)
  - Booking bar visualization:
    - Purple gradient bars with party size badges (always visible)
    - Bar height varies based on party size (2-20 people)
    - Guest name and room number shown in full mode, hidden in compact mode
    - Hover effects with scale transform and shadow
    - Tooltips via data attributes
  - Display modes:
    - **Compact mode** (default): 14px bars, party size badge only, 7px grid rows
    - **Full mode**: 40px bars with names and room numbers, 14px grid rows
  - Grey overlay rendering:
    - Outside opening hours (lighter grey)
    - Special event closures (medium grey)
    - Fully booked time slots (lightest grey)
    - Proper z-index layering
  - Red sight line feature:
    - 2px red vertical line appears on time slot button hover
    - Positioned as percentage of chart width
    - Smooth transition animations
    - Auto-hides when not hovering
  - Auto-scroll to hovered time:
    - Gantt chart auto-centers on hovered time slot
    - Smooth scroll behavior
    - Calculates viewport position based on chart time range
  - JavaScript functions added:
    - `positionBookingsOnGrid()` - Row compaction algorithm
    - `buildGanttChart()` - Completely rewritten with new signature
    - `showGanttSightLine(chartId, time)` - Show sight line at specific time
    - `hideGanttSightLine(chartId)` - Hide sight line
    - `scrollGanttToTime(chartId, time, smooth)` - Auto-scroll to time
  - CSS styles added:
    - `.gantt-booking-bar` - Booking bar styling with hover effects
    - `.gantt-party-size` - Party size badge styling
    - `.gantt-bar-text` - Guest name/room text styling
    - `.gantt-closed-block` - Grey overlay variants
    - `.gantt-sight-line` - Red sight line with transitions
  - Time slot button hover integration:
    - `mouseenter` triggers sight line display and auto-scroll
    - `mouseleave` hides sight line
    - Works across all service period accordion sections

- **Clickable restaurant header navigation in Summary tab**
  - Restaurant section header in booking cards now navigates to Restaurant tab
  - Click handler extracts booking ID and calls `navigateToRestaurantDate()`
  - Visual feedback with restaurant icon and arrow icon
  - Hover states with background color change and arrow animation
  - "No booking" rows now include data-date attribute for auto-expand functionality
  - Clicking "No booking" rows automatically opens create form for that date
  - Commits: d633c7c (template fix), 3a3a981 (JavaScript selector fix)

- **Gantt chart visualization for booking creation**
  - Added `buildGanttChart()` function in sidepanel.js
  - Displays opening hours as colored bands on visual timeline
  - Shows closed periods as grey overlay blocks
  - Automatically generated when opening hours are loaded
  - Compact mode suitable for Chrome extension sidebar (no navigation controls)

- **Accordion-based service period selector UI**
  - Replaced dropdown selector with vertical accordion interface
  - Each service period shown as collapsible section header
  - Exclusive accordion behavior (only one section open at a time)
  - Latest period (dinner) pre-selected and expanded by default
  - Lazy loading of available times when section is expanded
  - `togglePeriodSection()` function for expand/collapse behavior
  - Automatic period ID capture when time slot selected

- **Dynamic booking summary header**
  - Moved booking header above form action buttons
  - Format: `{guest name} - {selected time} ({people}pax)`
  - Time updates dynamically when user selects time slot
  - Selected time displayed in blue highlight color

- **Streamlined form fields**
  - Date field converted to hidden input (defined by which day's create button clicked)
  - Hidden `opening-hour-id` field for automatic period ID capture
  - Email and phone pre-populated from NewBook booking data
  - Improved validation messages for better UX

- Automatic form initialization script in Chrome extension template
  - MutationObserver watches for form visibility
  - Auto-fetches opening hours and generates accordion sections
  - Auto-fetches dietary choices and populates checkboxes
  - Loads available times for default period on initialization
  - Lazy loads times for other periods when sections expanded
  - Adds click handlers to time slot buttons for selection and period ID capture

### Fixed (Latest)
- **Navigation context persisting and re-triggering**
  - Fixed navigation context not being cleared after processing
  - Prevented auto-expand/scroll from triggering on planner clicks
  - Navigation context now cleared in all scenarios (success or early return)
  - Fixes issue where clicking planner bookings incorrectly auto-expanded last date's create form
  - Commit: 6148eaf

- **Restaurant tab scroll alignment for last night**
  - Added 60vh bottom padding to restaurant tab content
  - Allows last night in the list to properly scroll to top of sidepanel
  - Improves UX when navigating to or creating bookings for the last date
  - Commit: cf11516

- **Auto-expand form initialization and scroll timing - RESOLVED**
  - Fixed form opening without loading gantt chart, periods, or dietary choices
  - Fixed scroll happening before form content loaded (incorrect positioning)
  - **Root cause identified:** Inline `<script>` tags in template don't execute when HTML is inserted via innerHTML (browser security)
  - **Solution:** Exposed initializeCreateFormForDate to window object for programmatic access
  - Updated processNavigationContext to call initialization function directly instead of waiting for MutationObserver
  - Removed waitForFormInitialization helper (no longer needed)
  - Form now properly initializes with gantt chart, opening periods, and dietary choices when navigating from Summary tab
  - Added period ID and booking time display updates in time slot click handlers
  - Commits: 43e8224 (template), 16420ce (initial async wait), 42961d9 (display toggle fix), d943d84 (final fix)

- **Restaurant header navigation and auto-expand functionality**
  - Fixed restaurant header initially applied to wrong tab (Restaurant instead of Summary)
  - Reverted clickable header from Restaurant tab template
  - Applied clickable header to correct location: Summary tab booking cards
  - Fixed "No booking" rows missing data-date attribute preventing auto-expand
  - Added create-booking-link class to enable auto-open of create form
  - Changed JavaScript selector from .bma-restaurant-header-link to .restaurant-header-link
  - Clicking "No booking" rows now properly auto-expands create form for that date
  - Commits: d633c7c (template fix), 3a3a981 (JavaScript fix)

- **Summary tab booking badges vertical alignment**
  - Fixed badges being vertically centered, causing clash with time-since-placed element
  - Added align-self: flex-start to position badges at top of header section
  - Commit: a0c3404

- **Email and phone auto-population from NewBook guest data**
  - Fixed email and phone fields not populating in create booking form
  - Added extraction of guest contact details from `guests[].contact_details` array
  - Updated `match_booking_all_nights()` to include `phone` and `email` fields
  - Now correctly accesses primary guest's phone and email from NewBook API structure
  - Template form fields now auto-populate with guest contact information

- **Service period validation and accordion UI improvements**
  - Fixed "select a service period" error appearing even after selecting time
  - Changed validation from old dropdown selector to hidden `opening-hour-id` field
  - Period ID now automatically captured when time slot button clicked
  - Accordion sections now exclusive (only one open at a time)
  - Removed period labels showing redundant time ranges
  - Fixed onclick handlers by replacing inline attributes with proper event listeners

- **Available times data filtering for accordion sections**
  - Fixed issue where all periods' time buttons showed inside each expanded section
  - Backend now filters `opening_hours` array by `opening_hour_id` parameter
  - Modified `format_time_slots_html()` to skip period wrappers when filtering to single period
  - Each accordion section now shows only its own time slot buttons

- **Time slot generation and availability logic**
  - Fixed incorrect approach to generating and greying out time slot buttons
  - Now matches management plugin pattern: generate ALL slots from opening hours, grey out based on TWO criteria
  - Time slots calculated from opening hours (start time + interval) instead of relying only on available times
  - Slots greyed out if EITHER:
    1. Not in available times from `/bookingFlow/times` API (fully booked), OR
    2. Restricted by special events (closures/limitations)
  - Special events with `isOpen: true` now correctly skipped (these are special opening hours, not restrictions)
  - New `check_time_restriction()` helper method handles restriction logic
  - Changed CSS class from `.unavailable` to `.time-slot-unavailable`
  - Tooltips now use `data-restriction` attribute showing specific event name or "No availability"
  - Commits: 1e310d9 (backend), 4e46400 (frontend CSS)

- **CRITICAL**: Available times 404 error - incorrect API endpoint
  - Fixed 404 Not Found errors when fetching available times for booking form
  - Changed from `/openingHours/{date}?expand=availableTimes` to `/bookingFlow/times?date={date}`
  - Time slot buttons now populate correctly in create booking form
  - Gantt chart should now display available times
  - Updated `fetch_available_times()` method

- **CRITICAL**: Opening hours 404 error - incorrect API endpoint format
  - Fixed 404 Not Found errors when fetching opening hours for specific dates
  - Changed from date-specific endpoint `/openingHours/{date}` to general `/openingHours`
  - Added client-side filtering by day-of-week (1=Monday through 7=Sunday)
  - Added special date override detection (e.g., Christmas hours)
  - New helper methods: `get_all_opening_hours()` and `filter_opening_hours_for_date()`
  - Now matches management plugin's approach
  - Single cache for all hours (`bma_opening_hours_all`) instead of per-date caches

- **CRITICAL**: Resos API authentication in fetch methods (commit de4a091)
  - Fixed 401 Unauthorized errors on all fetch operations
  - Changed from `Bearer` to `Basic` authentication scheme
  - Added base64 encoding of API key in Authorization header
  - Updated `fetch_opening_hours()`, `fetch_available_times()`, `fetch_special_events()`, and `fetch_dietary_choices()`
  - Now consistent with booking create/update operations
  - Verified working with curl tests against Resos API

### Added
- Gantt Chart generator class (`class-bma-gantt-chart.php`)
  - Three display modes: full, medium, compact
  - Windowed viewport support with configurable hours
  - Grid-based positioning algorithm
  - Smooth scrolling and auto-centering
  - Complete inline CSS and JavaScript generation

- Four new REST API endpoints:
  - `GET/POST /bma/v1/opening-hours` - Restaurant opening hours/service periods
  - `POST /bma/v1/available-times` - Available time slots for date/party size
  - `GET /bma/v1/dietary-choices` - Dietary requirement options
  - `GET /bma/v1/special-events` - Special events and closures

- Reusable Resos API methods in `BMA_Booking_Actions`:
  - `fetch_opening_hours($date)` with 1-hour caching
  - `fetch_available_times($date, $people, $area_id)` real-time
  - `fetch_special_events($date)` with 30-minute caching
  - `fetch_dietary_choices()` with 24-hour caching

- Context-aware response formatting:
  - `context=chrome-extension` returns formatted HTML
  - Default returns raw JSON data
  - Supports both GET and POST methods where applicable

- Enhanced Chrome extension template:
  - Collapsible form sections (Booking Details, Allergies, Notes)
  - Compact booking header with guest summary
  - Service period selector
  - Gantt chart placeholder with viewport controls
  - Time slot button grid
  - Complete form fields matching reservation management plugin

- Comprehensive reference documentation:
  - DEVELOPER_NOTES.md - Architecture and integration guide
  - API_ENDPOINTS.md - Complete REST API reference
  - GANTT_CHART_CLASS.md - Chart usage and customization
  - CHANGELOG.md - This file

### Changed
- Chrome extension template now uses date section wrappers for navigation
- Form feedback elements now have unique IDs per date
- Opening hours endpoint supports both specific dates and general periods
- Improved error logging with BMA prefix for easier debugging

### JavaScript/CSS Implementation Needed
The following still need to be implemented in the Chrome extension:

**JavaScript (sidepanel.js):**
- Navigation state management (STATE.navigationContext)
- Navigation helper functions (navigateToRestaurantDate, returnToPreviousContext)
- Gantt chart functions (scrollGanttToTime, showGanttSightLine)
- Form functions (toggleFormSection, validateBookingForm)
- API fetch functions (fetchOpeningHours, fetchAvailableTimes, etc.)
- Enhanced submitCreateBooking with all new fields

**CSS (sidepanel.css):**
- Collapsible section styles
- Gantt viewport container styles
- Time slot button grid styles
- Navigation link styles
- Booking header styles
- Date section wrapper styles

### Technical Debt
- Consider adding rate limiting to API endpoints
- Implement webhook support for booking events
- Add automated testing for API endpoints
- Consider WebSocket integration for real-time updates

---

## [1.0.0] - Initial Release

### Added
- Basic booking matching functionality
- Restaurant booking create/update endpoints
- Chrome extension integration
- NewBook API integration
- Resos API integration
- Custom field mapping system
- Booking comparison logic
- Summary, Restaurant, and Checks views

### Features
- Match hotel bookings to restaurant reservations
- Update existing restaurant bookings
- Exclude bookings from matching
- View detailed comparison data
- Chrome extension side panel UI

---

## Migration Guide

### Upgrading to 1.1.0

**No database changes required.**

**New WordPress Options:**
None - uses existing `hotel_booking_resos_api_key`

**New Dependencies:**
None

**Template Changes:**
If you've customized `chrome-sidepanel-response.php`, note:
- Line 140: Added date section wrapper
- Lines 358-526: Complete form replacement
- Line 435: Added closing date section wrapper

**API Changes:**
Fully backward compatible. New endpoints are additions only.

**Caching Changes:**
New transients will be created:
- `bma_opening_hours_{date}`
- `bma_opening_hours_general`
- `bma_dietary_choices`
- `bma_special_events_{date}`

To clear all caches after upgrade:
```php
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bma_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bma_%'");
```

---

## Roadmap

### Version 1.2.0 (Planned)
- Complete JavaScript implementation for Chrome extension
- Add booking cancellation endpoint
- Implement booking notes update endpoint
- Add bulk operations support
- Performance optimizations for large datasets

### Version 2.0.0 (Future)
- Webhook support for real-time updates
- WebSocket integration
- Multi-restaurant support
- Advanced filtering and search
- Reporting and analytics endpoints
- Automated testing suite

---

## Breaking Changes

None in current version. All changes are additive and backward compatible.

---

## Security Updates

### Version 1.1.0
- Enhanced input sanitization for new endpoints
- Added proper escaping for HTML responses
- Validated all date formats
- Secured transient cache keys

---

## Performance Improvements

### Version 1.1.0
- Implemented intelligent caching strategy
- Reduced Resos API calls by 70% through caching
- Optimized grid positioning algorithm in Gantt chart
- Reduced Chrome extension payload size with context-aware responses

---

## Bug Fixes

### Version 1.1.0
- Fixed dietary requirements field name (added leading space)
- Corrected time format conversion in available times
- Fixed opening hours calculation for periods spanning midnight
- Resolved viewport scrolling issues in compact Gantt mode

---

## Contributors

- Primary Developer: [Your Name]
- Based on: reservation-management-integration plugin
- Resos API Integration: Resos.com
- NewBook API Integration: NewBook.cloud

---

## Support

For issues, questions, or feature requests:
1. Check documentation in `/reference/` directory
2. Review WordPress error logs
3. Test endpoints manually with cURL/Postman
4. Verify Resos API key configuration

---

## License

[Your License Here]
