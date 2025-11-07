# Booking Match API - Changelog

All notable changes to this plugin will be documented in this file.

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
