# Booking Match API - Changelog

All notable changes to this plugin will be documented in this file.

## [1.3.1] - 2025-11-10

### Added
- **All Bookings for Date Endpoint**: New REST endpoint `/bma/v1/all-bookings-for-date`
  - Returns ALL restaurant bookings for a specific date (not filtered by hotel guest matching)
  - Provides comprehensive view of restaurant occupancy for Gantt chart display
  - Uses existing `BMA_Matcher::fetch_resos_bookings()` method
  - Filters out canceled/no-show bookings automatically
  - Formats data specifically for Gantt chart display (time, people, name, room)

- **New Method**: `BMA_Matcher::fetch_all_bookings_for_gantt()`
  - Public method to fetch and format all restaurant bookings for a date
  - Extracts room numbers from Resos customFields
  - Returns simplified array structure optimized for Gantt chart rendering

### Changed
- **BMA_Matcher Class**: Made `fetch_resos_bookings()` method public
  - Previously private, now accessible for reuse across the plugin
  - Enables fetching all Resos bookings without duplicate code

- **Gantt Chart in Chrome Extension**: Now shows ALL restaurant bookings
  - Previously only displayed bookings matched to current hotel guest
  - Provides complete restaurant capacity view when creating new bookings
  - Helps staff understand availability and conflicts across all guests

### Technical Details
- Reuses existing Resos API integration instead of creating duplicate functionality
- No caching (real-time data for accurate availability)
- Response includes booking count for debugging and UI display
- Integrates with existing Gantt chart rendering in chrome-newbook-assistant extension

---

## [1.3.0] - 2025-11-08

### Added
- **Summary Endpoint Implementation**: Fully functional summary of recent bookings
  - Fetches recently placed bookings from NewBook (last 72 hours by default)
  - Uses `bookings_list` API with `list_type: 'placed'` to get recently created bookings
  - Passes bookings through restaurant matching logic for night-by-night analysis
  - Calculates badge counts for restaurant and check issues
  - Expandable accordion card design showing basic info + detailed view
  - Color-coded status badges (confirmed, provisional, cancelled)
  - Issue count badges for restaurant and check flags
  - "Open in NewBook" button to view full booking details
  - Supports `limit` parameter from Chrome extension settings (default: 5)

- **New Classes**:
  - `BMA_Booking_Source`: Determines booking source/channel (placeholder for future implementation)
  - `BMA_Issue_Checker`: Validates bookings for issues (placeholder for future implementation)

- **New Method**: `BMA_NewBook_Search::fetch_recent_placed_bookings()`
  - Fetches bookings by creation date (not arrival date)
  - Sorts by booking_id descending (most recent first)
  - Applies limit parameter to return X most recent bookings

### Changed
- Updated `BMA_REST_Controller::get_summary()`: Full implementation replacing stub data
- Updated `chrome-summary-response.php`: Redesigned with accordion-style expandable sections
- Summary endpoint now accepts `limit` parameter from Chrome extension settings
- Summary endpoint processes real booking data through matcher and issue checker

### Technical Details
- Restaurant matching analyzes: package bookings without reservations, multiple matches, non-primary matches
- Placeholder classes ready for future booking source detection and issue checking logic
- Helper methods added: `extract_guest_name()`, `calculate_nights()`, `process_booking_for_summary()`
- Template includes inline JavaScript for expand/collapse functionality
- "Open in NewBook" links to `https://appeu.newbook.cloud/bookings_view/{id}`

---

## [1.2.1] - 2025-11-08

### Fixed
- **Chrome Sidepanel Badge Counts**: Added `badge_count` to HTML response format (chrome-sidepanel context)
  - Previously only JSON responses included badge counts
  - Chrome extension sidepanel now receives badge counts for all tabs (Restaurant, Summary, Checks)
  - Badge counts indicate issues requiring attention:
    - Package nights without restaurant bookings (critical)
    - Multiple matches requiring manual selection
    - Non-primary (lower confidence) matches
  - Enables proper icon badge display and tab prioritization in chrome-newbook-assistant extension

### Technical Details
- Updated `BMA_Response_Formatter::format_html_response()` to calculate and include badge_count
- Badge calculation logic matches existing JSON response format for consistency
- All three endpoints now properly return badge_count in all response formats

---

## [1.1.0] - 2025-11-07

### Added
- **Exclusion Note Support**: API now respects manual exclusion notes in Resos bookings
  - Resos bookings with "NOT-#{hotel_booking_id}" notes will not match that specific hotel booking
  - Prevents false positive matches after staff manually excludes incorrect suggestions
  - Works seamlessly with Reservation Management Integration plugin's "Exclude Match" feature
  - Exclusion check happens first, before any other matching logic runs

### Technical Details
- Added exclusion pattern check in `match_resos_to_hotel()` method
- Checks `restaurantNotes` for "NOT-#" pattern followed by hotel booking ID
- Returns `matched: false, excluded: true` when exclusion note is found
- Case-insensitive matching for reliability

### Workflow Integration
1. Staff uses "Exclude Match" button in Reservation Management Integration plugin
2. System adds "NOT-#{booking_id}" note to Resos booking via API
3. Booking Match API (this plugin) automatically excludes that combination in future searches
4. Chrome extension and other API clients receive clean results without excluded matches

### Benefits
- Reduces repeat false positives
- Staff decisions are respected across all platforms
- Cleaner match results in API responses
- Better user experience for Chrome extension users

---

## [1.0.0] - 2025-11-06

### Added
- Initial release of Booking Match API plugin
- REST API endpoint: `/wp-json/bma/v1/search`
- NewBook PMS integration for hotel booking search
- Resos restaurant booking matching
- Multiple search criteria support:
  - Booking ID
  - Guest name
  - Email
  - Phone number
  - Booking reference
- Confidence-based matching algorithm:
  - High confidence: Booking ID, Agent Reference, or composite matches
  - Prioritized match scoring
  - Multiple match results per night
- JSON and HTML response formats
- Chrome extension support with CORS headers
- Multi-night booking support
- Package detection (DBB/Dinner Bed & Breakfast)

### Features
- **Multi-Criteria Search**: Find bookings using any combination of identifiers
- **Night-by-Night Matching**: Returns restaurant matches for each night of stay
- **Confidence Scoring**: Prioritizes matches by reliability
- **Flexible Response Formats**: JSON for API clients, HTML for direct viewing
- **CORS Support**: Works with Chrome extensions and web apps

### Technical Details
- Modular architecture with separate classes:
  - `BMA_REST_Controller`: REST API endpoint handling
  - `BMA_NewBook_Search`: NewBook PMS integration
  - `BMA_Matcher`: Booking matching logic
  - `BMA_Response_Formatter`: Response formatting
  - `BMA_Authenticator`: WordPress authentication
  - `BMA_Template_Helper`: HTML template rendering
- WordPress REST API integration
- Basic authentication support
- Reuses settings from Reservation Management Integration plugin
- Compatible with PHP 7.4+
- WordPress 5.0+ required

### Configuration
- Uses existing WordPress settings:
  - NewBook PMS credentials
  - Resos API key
  - Hotel ID
  - Package inventory item name
