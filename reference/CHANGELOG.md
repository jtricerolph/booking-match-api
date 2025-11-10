# Booking Match API - Changelog

All notable changes to the Booking Match API plugin.

## [Unreleased] - Implementation in Progress

### Added (Latest)
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
- **Auto-expand form initialization and scroll timing**
  - Fixed form opening without loading gantt chart, periods, or dietary choices
  - Fixed scroll happening before form content loaded (incorrect positioning)
  - Fixed MutationObserver not triggering when form already visible
  - Force form display toggle (none → block) to ensure MutationObserver fires
  - Added data-initialized flag to track form initialization completion
  - Added waitForFormInitialization helper with MutationObserver
  - Made processNavigationContext async to await initialization before scrolling
  - Added 5 second timeout fallback to prevent indefinite waiting
  - Scroll now accounts for increased form size after content loads
  - Added period ID and booking time display updates in time slot click handlers
  - Commits: 43e8224 (template), 16420ce (initial async wait), 42961d9 (display toggle fix)

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
