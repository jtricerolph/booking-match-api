# Booking Match API Plugin

A WordPress REST API plugin that searches for hotel bookings and matches them with restaurant reservations.

## Overview

This plugin provides a REST API endpoint that:
- Searches NewBook PMS for hotel bookings using various criteria
- Matches found bookings with Resos restaurant bookings for each night
- Returns results in JSON or HTML format depending on context
- Supports multiple client types (Chrome extension, mobile apps, etc.)

## Installation

1. Upload the `booking-match-api` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin interface
3. Ensure the main hotel admin plugin is installed (required for API credentials)

## API Endpoint

**URL**: `POST /wp-json/bma/v1/bookings/match`

**Authentication**: WordPress REST API authentication (user must be logged in)

## Request Parameters

All parameters are optional, but certain combinations are required for confident searches.

| Parameter | Type | Description |
|-----------|------|-------------|
| `booking_id` | integer | NewBook booking ID (if known) |
| `guest_name` | string | Guest's full name |
| `email_address` | string | Guest's email address |
| `phone_number` | string | Guest's phone number |
| `group_id` | integer | NewBook group ID |
| `travelagent_reference` | string | Travel agent booking reference |
| `context` | string | Response format: `json` (default) or `chrome-extension` |

## Search Confidence Rules

The API enforces confidence rules to prevent false matches:

✅ **Confident searches** (will proceed):
- Email address alone
- Phone number alone
- Travel agent reference alone
- Name + email
- Name + phone
- Booking ID (always confident)

❌ **Not confident** (will return error):
- Name alone (too many potential false matches)

## Response Formats

### JSON Format (default)

```json
{
  "success": true,
  "search_method": "email",
  "bookings_found": 1,
  "bookings": [
    {
      "booking_id": 12345,
      "guest_name": "John Smith",
      "room": "101",
      "arrival": "2025-11-04",
      "departure": "2025-11-06",
      "total_nights": 2,
      "nights": [
        {
          "date": "2025-11-04",
          "has_match": true,
          "match_confidence": "high",
          "match_type": "booking_id",
          "resos_booking_id": "abc123",
          "deep_link": "https://yoursite.com/booking-page/?booking_id=12345&date=2025-11-04",
          "action": "update"
        },
        {
          "date": "2025-11-05",
          "has_match": false,
          "deep_link": "https://yoursite.com/booking-page/?booking_id=12345&date=2025-11-05",
          "action": "create"
        }
      ]
    }
  ]
}
```

### HTML Format (chrome-extension context)

Returns styled HTML suitable for display in a Chrome extension popup or iframe. Includes:
- Booking summary with guest details
- Night-by-night breakdown
- Visual indicators (checkmarks, warnings)
- Action links to create/update restaurant bookings

## Example Requests

### Search by Email

```bash
curl -X POST https://yoursite.com/wp-json/bma/v1/bookings/match \
  -H "Content-Type: application/json" \
  --cookie "wordpress_logged_in_cookie=..." \
  -d '{
    "email_address": "john@example.com",
    "context": "json"
  }'
```

### Search by Phone

```bash
curl -X POST https://yoursite.com/wp-json/bma/v1/bookings/match \
  -H "Content-Type: application/json" \
  --cookie "wordpress_logged_in_cookie=..." \
  -d '{
    "phone_number": "+61412345678",
    "context": "json"
  }'
```

### Search by Name + Email (Chrome Extension)

```bash
curl -X POST https://yoursite.com/wp-json/bma/v1/bookings/match \
  -H "Content-Type: application/json" \
  --cookie "wordpress_logged_in_cookie=..." \
  -d '{
    "guest_name": "John Smith",
    "email_address": "john@example.com",
    "context": "chrome-extension"
  }'
```

### Direct Booking ID Lookup

```bash
curl -X POST https://yoursite.com/wp-json/bma/v1/bookings/match \
  -H "Content-Type: application/json" \
  --cookie "wordpress_logged_in_cookie=..." \
  -d '{
    "booking_id": 12345,
    "context": "json"
  }'
```

## Matching Logic

The plugin uses priority-based matching to connect hotel bookings with restaurant reservations:

### Primary Matches (High Confidence)
1. Booking ID in Resos custom fields
2. Agent reference in Resos custom fields
3. Booking ID in Resos notes
4. Agent reference in Resos notes

### Composite Matching (Scored)
- Room number in notes: +8 points
- Surname match: +7 points
- Phone match (last 8 digits): +9 points
- Email match: +10 points

**Confidence Levels**:
- High: Score ≥20 or ≥3 matches
- Medium: Score ≥15 or ≥2 matches
- Low: Score >0

## Multiple Results Handling

When multiple bookings match search criteria, all results are returned sorted by:
1. Confidence score (highest first)
2. Arrival date (most recent first)

The client application should present options to the user to select the correct booking.

## Error Responses

### Not Confident Error
```json
{
  "success": false,
  "error": "not_confident",
  "message": "Search criteria not confident enough. Please provide email, phone, or travel agent reference.",
  "provided_fields": ["guest_name"]
}
```

### Booking Not Found
```json
{
  "success": false,
  "error": "booking_not_found",
  "message": "No bookings found matching the search criteria"
}
```

### Authentication Error
```json
{
  "code": "rest_forbidden",
  "message": "Sorry, you are not allowed to do that.",
  "data": {
    "status": 401
  }
}
```

## Deep Link Format

The API generates deep links to the booking management page:

**Single night or direct link**:
```
https://yoursite.com/booking-page/?booking_id=12345&date=2025-11-04
```

**Multi-night stays**: The user will see a popup to select which night to view.

## Configuration

### Settings Page

Access the plugin settings at **Settings → Booking Match API** in your WordPress admin.

**Booking Management Page URL**: Enter the full URL of the page that displays your hotel bookings (with the `[hotel-table-bookings-by-date]` shortcode).

Example: `https://admin.hotelnumberfour.com/bookings/`

If left empty, the plugin will default to: `https://yoursite.com/bookings/`

### API Credentials

The plugin reuses configuration from the main hotel admin plugin:

- NewBook API credentials (username, password, API key, region)
- Resos API key
- Hotel ID

Configure these at **Settings → Hotel Booking Table**.

### Alternative Configuration Methods

You can also set the booking page URL programmatically:

**Via PHP:**
```php
update_option('bma_booking_page_url', 'https://yoursite.com/booking-page/');
```

**Via wp-config.php:**
```php
define('BMA_BOOKING_PAGE_URL', 'https://yoursite.com/booking-page/');
```

## Architecture

### Files Structure
```
booking-match-api/
├── booking-match-api.php              # Main plugin file
├── includes/
│   ├── class-bma-rest-controller.php  # REST API endpoint handler
│   ├── class-bma-newbook-search.php   # NewBook search logic
│   ├── class-bma-matcher.php          # Booking matching logic
│   ├── class-bma-response-formatter.php # Response formatting
│   ├── class-bma-authenticator.php    # Authentication (stub)
│   ├── class-bma-admin.php            # Admin settings page
│   └── class-bma-template-helper.php  # HTML template helper
└── templates/
    └── chrome-extension-response.php  # HTML template
```

### Classes

**BMA_Admin**: Admin settings page for configuring booking page URL and viewing API documentation

**BMA_REST_Controller**: Registers REST route, validates parameters, orchestrates search and matching

**BMA_NewBook_Search**: Searches NewBook API by various criteria, implements confidence scoring

**BMA_Matcher**: Matches hotel bookings with Resos restaurant bookings for each night

**BMA_Response_Formatter**: Formats results as JSON or HTML based on context

**BMA_Authenticator**: Authentication stub (currently uses WordPress authentication, extensible for API keys/JWT)

## Security

- WordPress REST API authentication required (user must be logged in)
- All input sanitized using WordPress functions
- All output escaped in HTML templates
- Read-only API (no booking modifications)
- Prepared statements for database queries

## Future Enhancements

Possible improvements:
- API key authentication for external clients
- JWT token support
- Webhook notifications for booking changes
- Caching layer for frequently searched bookings
- Rate limiting
- Detailed logging and analytics

## Support

For issues or questions, check:
- WordPress debug log: `wp-content/debug.log`
- Browser console for client-side errors
- Network tab for API request/response inspection

## Version

**Version**: 1.0.0
**Author**: Your Name
**License**: GPL v2 or later

## Changelog

### 1.0.0 - 2025-11-04
- Initial release
- REST API endpoint for booking search and match
- Support for email, phone, agent reference searches
- JSON and HTML response formats
- Chrome extension template
- Priority-based matching algorithm
- Confidence scoring system
