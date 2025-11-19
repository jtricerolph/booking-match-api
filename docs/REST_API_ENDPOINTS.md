# REST API Endpoints

Complete reference for all REST API endpoints provided by the Booking Match API plugin.

## Table of Contents

- [Base URL](#base-url)
- [Authentication](#authentication)
- [Core Endpoints](#core-endpoints)
  - [Match Bookings](#post-bmav1bookingsmatch)
  - [Get Summary](#get-bmav1summary)
  - [Get Staying Bookings](#get-bmav1staying)
  - [Get Checks](#get-bmav1checksbooking_id)
  - [Get Comparison](#post-bmav1comparison)
- [Booking Management](#booking-management)
  - [Create Booking](#post-bmav1bookingscreate)
  - [Update Booking](#post-bmav1bookingsupdate)
  - [Exclude Match](#post-bmav1bookingsexclude)
  - [Update Group](#post-bmav1bookingsgroup)
- [Date-Based Queries](#date-based-queries)
  - [Get Bookings for Date](#get-bmav1bookingsfor-date)
  - [Get All Bookings for Date](#get-bmav1all-bookings-for-date)
- [Booking Form Data](#booking-form-data)
  - [Get Opening Hours](#getpost-bmav1opening-hours)
  - [Get Available Times](#post-bmav1available-times)
  - [Get Dietary Choices](#get-bmav1dietary-choices)
  - [Get Special Events](#get-bmav1special-events)
- [Error Codes](#error-codes)
- [Rate Limits](#rate-limits)
- [Examples](#examples)

---

## Base URL

```
https://yoursite.com/wp-json/bma/v1
```

All endpoints are prefixed with `/bma/v1/`.

---

## Authentication

The API uses WordPress REST API authentication. Two methods are supported:

### 1. WordPress Session Cookies

If already logged into WordPress, session cookies will authenticate requests.

### 2. Application Passwords (Recommended)

Generate an Application Password in WordPress and use HTTP Basic Authentication.

**Generate Application Password:**
1. Go to WordPress Admin > Users > Profile
2. Scroll to "Application Passwords"
3. Enter name (e.g., "Chrome Extension")
4. Click "Add New Application Password"
5. Copy the generated password

**Use in requests:**
```bash
curl -X POST https://yoursite.com/wp-json/bma/v1/bookings/match \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{"email_address": "guest@example.com"}'
```

**JavaScript:**
```javascript
const auth = 'Basic ' + btoa('username:xxxx xxxx xxxx xxxx xxxx xxxx');
fetch('https://yoursite.com/wp-json/bma/v1/bookings/match', {
    headers: {
        'Authorization': auth,
        'Content-Type': 'application/json'
    }
});
```

**Required Capability:** `read` (all authenticated users by default)

---

## Core Endpoints

### `POST /bma/v1/bookings/match`

Searches for hotel bookings and matches them with restaurant reservations.

**Request Body:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `booking_id` | integer | No* | NewBook booking ID |
| `guest_name` | string | No* | Guest's full name |
| `email_address` | string | No* | Guest's email address |
| `phone_number` | string | No* | Guest's phone number |
| `group_id` | integer | No* | NewBook group ID |
| `travelagent_reference` | string | No* | Travel agent reference |
| `context` | string | No | Response format: `json`, `chrome-extension`, `chrome-sidepanel` (default: `json`) |
| `force_refresh` | boolean | No | Force cache refresh (default: `false`) |

*At least one search parameter is required. See [Search Confidence Rules](#search-confidence-rules).

**Response (JSON):**

```json
{
  "success": true,
  "search_method": "email",
  "bookings_found": 1,
  "bookings": [
    {
      "booking_id": 12345,
      "booking_reference": "ABC123",
      "guest_name": "John Smith",
      "room": "101",
      "arrival": "2025-11-20",
      "departure": "2025-11-22",
      "total_nights": 2,
      "nights": [
        {
          "date": "2025-11-20",
          "date_formatted": "20/11/25",
          "has_match": true,
          "match_count": 1,
          "has_package": true,
          "resos_bookings": [
            {
              "id": "5f8a7b2c3d1e4f5g6h7i8j9k",
              "resos_booking_id": "5f8a7b2c3d1e4f5g6h7i8j9k",
              "restaurant_id": "restaurant123",
              "guest_name": "John Smith",
              "people": 2,
              "time": "19:00",
              "status": "confirmed",
              "is_hotel_guest": true,
              "is_dbb": false,
              "booking_number": "12345",
              "match_type": "booking_id",
              "match_label": "Booking ID",
              "confidence": "high",
              "is_primary": true,
              "score": 100,
              "deep_link": "https://yoursite.com/bookings/?booking_id=12345&date=2025-11-20"
            }
          ],
          "deep_link": "https://yoursite.com/bookings/?booking_id=12345&date=2025-11-20",
          "action": "update"
        },
        {
          "date": "2025-11-21",
          "date_formatted": "21/11/25",
          "has_match": false,
          "match_count": 0,
          "has_package": true,
          "resos_bookings": [],
          "deep_link": "https://yoursite.com/bookings/?booking_id=12345&date=2025-11-21",
          "action": "create"
        }
      ]
    }
  ],
  "should_auto_open": true,
  "badge_count": 1
}
```

**Response (HTML - context: chrome-sidepanel):**

Returns HTML content from `chrome-sidepanel-response.php` template.

**Status Codes:**
- `200 OK` - Success
- `400 Bad Request` - Invalid or insufficient search criteria
- `401 Unauthorized` - Not authenticated
- `403 Forbidden` - Insufficient permissions
- `404 Not Found` - No bookings found
- `500 Internal Server Error` - Server error

**Examples:**

```bash
# Search by email
curl -X POST https://example.com/wp-json/bma/v1/bookings/match \
  -u "admin:app_password" \
  -H "Content-Type: application/json" \
  -d '{
    "email_address": "john@example.com",
    "context": "json"
  }'

# Search by booking ID
curl -X POST https://example.com/wp-json/bma/v1/bookings/match \
  -u "admin:app_password" \
  -H "Content-Type: application/json" \
  -d '{
    "booking_id": 12345,
    "force_refresh": true
  }'

# Search by name + phone
curl -X POST https://example.com/wp-json/bma/v1/bookings/match \
  -u "admin:app_password" \
  -H "Content-Type: application/json" \
  -d '{
    "guest_name": "John Smith",
    "phone_number": "+61412345678",
    "context": "chrome-sidepanel"
  }'
```

#### Search Confidence Rules

The API enforces confidence rules to prevent false matches:

**Confident Searches** (will proceed):
- ✓ Booking ID alone
- ✓ Email address alone
- ✓ Phone number alone
- ✓ Travel agent reference alone
- ✓ Name + email
- ✓ Name + phone

**Not Confident** (will return error):
- ✗ Name alone

---

### `GET /bma/v1/summary`

Returns summary of recent bookings (by creation date).

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `hours_back` | integer | No | Hours to look back (default: `72`) |
| `limit` | integer | No | Maximum bookings to return (default: `5`) |
| `context` | string | No | Response format (default: `json`) |

**Response (JSON):**

```json
{
  "success": true,
  "bookings_found": 3,
  "bookings": [
    {
      "booking_id": 12347,
      "guest_name": "Jane Doe",
      "arrival": "2025-11-22",
      "departure": "2025-11-24",
      "booking_placed": "2025-11-19 14:30:00",
      "nights": 2,
      "status": "confirmed"
    }
  ]
}
```

**Example:**

```bash
curl -X GET "https://example.com/wp-json/bma/v1/summary?hours_back=48&limit=10" \
  -u "admin:app_password"
```

---

### `GET /bma/v1/staying`

Returns all bookings staying on a specific date.

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `date` | string | No | Date in YYYY-MM-DD format (default: today) |
| `force_refresh` | boolean | No | Force cache refresh (default: `false`) |

**Response (JSON):**

```json
{
  "success": true,
  "date": "2025-11-20",
  "bookings_staying": 15,
  "bookings": [
    {
      "booking_id": 12345,
      "guest_name": "John Smith",
      "room": "101",
      "arrival": "2025-11-19",
      "departure": "2025-11-22",
      "current_night": 2,
      "total_nights": 3,
      "status": "arrived",
      "resos_matches": [...]
    }
  ]
}
```

**Response (HTML - context: chrome-sidepanel):**

Returns HTML from `chrome-staying-response.php` template with stats row and timeline visualization.

**Example:**

```bash
# Get today's staying bookings
curl -X GET "https://example.com/wp-json/bma/v1/staying" \
  -u "admin:app_password"

# Get specific date
curl -X GET "https://example.com/wp-json/bma/v1/staying?date=2025-11-25" \
  -u "admin:app_password"
```

---

### `GET /bma/v1/checks/{booking_id}`

Returns checks/issues for a specific booking.

**URL Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `booking_id` | integer | Yes | NewBook booking ID |

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `context` | string | No | Response format (default: `json`) |

**Response (JSON):**

```json
{
  "success": true,
  "booking_id": 12345,
  "checks": {
    "critical": [
      {
        "type": "missing_restaurant_booking",
        "date": "2025-11-20",
        "message": "Package booking missing restaurant reservation"
      }
    ],
    "warnings": [
      {
        "type": "multiple_matches",
        "date": "2025-11-21",
        "message": "Multiple restaurant bookings found - requires manual selection"
      }
    ],
    "info": []
  }
}
```

**Example:**

```bash
curl -X GET "https://example.com/wp-json/bma/v1/checks/12345" \
  -u "admin:app_password"
```

---

### `POST /bma/v1/comparison`

Returns detailed comparison data between hotel and restaurant bookings.

**Request Body:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `hotel_booking_id` | integer | Yes | Hotel booking ID |
| `resos_booking_id` | string | Yes | Resos booking ID |
| `date` | string | Yes | Date in YYYY-MM-DD format |
| `context` | string | No | Response format (default: `json`) |

**Response (JSON):**

```json
{
  "success": true,
  "hotel_booking_id": "12345",
  "resos_booking_id": "5f8a7b...",
  "date": "2025-11-20",
  "comparison": {
    "matches": {
      "name": true,
      "phone": true,
      "email": false,
      "people": true
    },
    "suggested_updates": [
      {
        "field": "Booking #",
        "current_value": "",
        "suggested_value": "12345",
        "reason": "Missing booking reference"
      }
    ],
    "discrepancies": [
      {
        "field": "email",
        "hotel_value": "john@example.com",
        "resos_value": "j.smith@example.com",
        "severity": "warning"
      }
    ]
  }
}
```

**Example:**

```bash
curl -X POST https://example.com/wp-json/bma/v1/comparison \
  -u "admin:app_password" \
  -H "Content-Type: application/json" \
  -d '{
    "hotel_booking_id": 12345,
    "resos_booking_id": "5f8a7b2c3d1e4f5g6h7i8j9k",
    "date": "2025-11-20"
  }'
```

---

## Booking Management

### `POST /bma/v1/bookings/create`

Creates a new restaurant booking.

**Request Body:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `hotel_booking_id` | integer | Yes | Hotel booking ID |
| `date` | string | Yes | Date in YYYY-MM-DD format |
| `time` | string | Yes | Time in HH:MM format |
| `people` | integer | Yes | Number of guests |
| `notes` | string | No | Additional notes |
| `dietary_requirements` | array | No | Dietary requirements |

**Response (JSON):**

```json
{
  "success": true,
  "resos_booking_id": "5f8a7b2c3d1e4f5g6h7i8j9k",
  "message": "Restaurant booking created successfully"
}
```

**Example:**

```bash
curl -X POST https://example.com/wp-json/bma/v1/bookings/create \
  -u "admin:app_password" \
  -H "Content-Type: application/json" \
  -d '{
    "hotel_booking_id": 12345,
    "date": "2025-11-20",
    "time": "19:00",
    "people": 2,
    "notes": "Window seat requested"
  }'
```

---

### `POST /bma/v1/bookings/update`

Updates an existing restaurant booking.

**Request Body:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `resos_booking_id` | string | Yes | Resos booking ID |
| `updates` | object | Yes | Fields to update |

**Response (JSON):**

```json
{
  "success": true,
  "resos_booking_id": "5f8a7b2c3d1e4f5g6h7i8j9k",
  "message": "Booking updated successfully",
  "updated_fields": ["Booking #", "people"]
}
```

**Example:**

```bash
curl -X POST https://example.com/wp-json/bma/v1/bookings/update \
  -u "admin:app_password" \
  -H "Content-Type: application/json" \
  -d '{
    "resos_booking_id": "5f8a7b2c3d1e4f5g6h7i8j9k",
    "updates": {
      "Booking #": "12345",
      "people": 3
    }
  }'
```

---

### `POST /bma/v1/bookings/exclude`

Excludes a Resos booking from matching with a hotel booking.

**Request Body:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `hotel_booking_id` | integer | Yes | Hotel booking ID |
| `resos_booking_id` | string | Yes | Resos booking ID to exclude |
| `reason` | string | No | Reason for exclusion |

**Response (JSON):**

```json
{
  "success": true,
  "message": "Match excluded successfully"
}
```

**Example:**

```bash
curl -X POST https://example.com/wp-json/bma/v1/bookings/exclude \
  -u "admin:app_password" \
  -H "Content-Type: application/json" \
  -d '{
    "hotel_booking_id": 12345,
    "resos_booking_id": "5f8a7b2c3d1e4f5g6h7i8j9k",
    "reason": "Different guest"
  }'
```

---

### `POST /bma/v1/bookings/group`

Updates group booking settings.

**Request Body:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `resos_booking_id` | string | Yes | Resos booking ID (lead booking) |
| `group_members` | array | Yes | Array of hotel booking IDs to group |
| `action` | string | Yes | `add` or `remove` |

**Response (JSON):**

```json
{
  "success": true,
  "message": "Group updated successfully",
  "group_members": [12345, 12346, 12347]
}
```

**Example:**

```bash
curl -X POST https://example.com/wp-json/bma/v1/bookings/group \
  -u "admin:app_password" \
  -H "Content-Type: application/json" \
  -d '{
    "resos_booking_id": "5f8a7b2c3d1e4f5g6h7i8j9k",
    "group_members": [12345, 12346],
    "action": "add"
  }'
```

---

## Date-Based Queries

### `GET /bma/v1/bookings/for-date`

Returns all bookings for a specific date.

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `date` | string | Yes | Date in YYYY-MM-DD format |
| `type` | string | No | Filter: `arriving`, `departing`, `staying` (default: all) |

**Response (JSON):**

```json
{
  "success": true,
  "date": "2025-11-20",
  "arriving": 5,
  "departing": 3,
  "staying": 15,
  "bookings": [...]
}
```

**Example:**

```bash
curl -X GET "https://example.com/wp-json/bma/v1/bookings/for-date?date=2025-11-20&type=arriving" \
  -u "admin:app_password"
```

---

### `GET /bma/v1/all-bookings-for-date`

Returns all bookings for a date (including Resos bookings without hotel matches).

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `date` | string | Yes | Date in YYYY-MM-DD format |

**Response (JSON):**

```json
{
  "success": true,
  "date": "2025-11-20",
  "hotel_bookings": [...],
  "resos_bookings": [...],
  "matched_pairs": [...]
}
```

**Example:**

```bash
curl -X GET "https://example.com/wp-json/bma/v1/all-bookings-for-date?date=2025-11-20" \
  -u "admin:app_password"
```

---

## Booking Form Data

### `GET|POST /bma/v1/opening-hours`

Returns restaurant opening hours.

**Query/Body Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `date` | string | No | Date in YYYY-MM-DD format (default: today) |
| `context` | string | No | Response format (default: `json`) |

**Response (JSON):**

```json
{
  "success": true,
  "date": "2025-11-20",
  "opening_hours": [
    {
      "id": "lunch",
      "name": "Lunch",
      "start": "12:00",
      "end": "14:30"
    },
    {
      "id": "dinner",
      "name": "Dinner",
      "start": "18:00",
      "end": "22:00"
    }
  ]
}
```

---

### `POST /bma/v1/available-times`

Returns available booking times.

**Request Body:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `date` | string | Yes | Date in YYYY-MM-DD format |
| `people` | integer | Yes | Number of guests |
| `opening_hour_id` | string | No | Filter by opening hour period |
| `context` | string | No | Response format (default: `json`) |

**Response (JSON):**

```json
{
  "success": true,
  "date": "2025-11-20",
  "people": 2,
  "available_times": [
    {
      "time": "18:00",
      "available": true
    },
    {
      "time": "18:30",
      "available": true
    },
    {
      "time": "19:00",
      "available": false
    }
  ]
}
```

---

### `GET /bma/v1/dietary-choices`

Returns available dietary choices/restrictions.

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `context` | string | No | Response format (default: `json`) |

**Response (JSON):**

```json
{
  "success": true,
  "dietary_choices": [
    {
      "id": "vegetarian",
      "label": "Vegetarian"
    },
    {
      "id": "vegan",
      "label": "Vegan"
    },
    {
      "id": "gluten_free",
      "label": "Gluten Free"
    }
  ]
}
```

---

### `GET /bma/v1/special-events`

Returns special events for a date.

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `date` | string | Yes | Date in YYYY-MM-DD format |
| `context` | string | No | Response format (default: `json`) |

**Response (JSON):**

```json
{
  "success": true,
  "date": "2025-11-20",
  "special_events": [
    {
      "id": "wine_tasting",
      "name": "Wine Tasting Evening",
      "description": "Special wine pairing menu",
      "time": "19:00"
    }
  ]
}
```

---

## Error Codes

### HTTP Status Codes

| Code | Description |
|------|-------------|
| `200` | Success |
| `400` | Bad Request - Invalid parameters |
| `401` | Unauthorized - Authentication required |
| `403` | Forbidden - Insufficient permissions |
| `404` | Not Found - Resource not found |
| `500` | Internal Server Error |

### Error Response Format

```json
{
  "code": "error_code",
  "message": "Human-readable error message",
  "data": {
    "status": 400,
    "additional_info": "..."
  }
}
```

### Common Error Codes

| Code | Description |
|------|-------------|
| `rest_forbidden` | Not authenticated or insufficient permissions |
| `missing_search_criteria` | No search parameters provided |
| `not_confident` | Search criteria not confident enough |
| `booking_not_found` | Booking ID not found |
| `no_bookings_found` | No bookings match search criteria |
| `invalid_date` | Date format invalid |
| `server_error` | Internal server error |

### Error Examples

**Not Confident:**
```json
{
  "code": "not_confident",
  "message": "Search criteria not confident enough. Please provide email, phone, or travel agent reference.",
  "data": {
    "status": 400,
    "provided_fields": ["guest_name"]
  }
}
```

**Not Found:**
```json
{
  "code": "booking_not_found",
  "message": "No bookings found matching the search criteria",
  "data": {
    "status": 404
  }
}
```

**Authentication Required:**
```json
{
  "code": "rest_forbidden",
  "message": "Authentication required. Please provide valid WordPress credentials.",
  "data": {
    "status": 401
  }
}
```

---

## Rate Limits

Currently, the API does not enforce rate limits, but this may change in future versions.

**Best Practices:**
- Cache responses when possible
- Use `force_refresh=true` sparingly
- Batch requests when searching multiple bookings
- Implement exponential backoff for retries

---

## Examples

### Complete cURL Examples

**Search by email with HTML response:**
```bash
curl -X POST https://admin.hotelnumberfour.com/wp-json/bma/v1/bookings/match \
  -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "email_address": "john@example.com",
    "context": "chrome-sidepanel"
  }'
```

**Get staying bookings for today:**
```bash
curl -X GET https://admin.hotelnumberfour.com/wp-json/bma/v1/staying \
  -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx"
```

**Get recent bookings (last 24 hours):**
```bash
curl -X GET "https://admin.hotelnumberfour.com/wp-json/bma/v1/summary?hours_back=24&limit=20" \
  -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx"
```

**Create restaurant booking:**
```bash
curl -X POST https://admin.hotelnumberfour.com/wp-json/bma/v1/bookings/create \
  -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "hotel_booking_id": 12345,
    "date": "2025-11-20",
    "time": "19:00",
    "people": 2,
    "notes": "Anniversary dinner"
  }'
```

### JavaScript Fetch Examples

**Search by phone:**
```javascript
const auth = 'Basic ' + btoa('admin:xxxx xxxx xxxx xxxx xxxx xxxx');

fetch('https://admin.hotelnumberfour.com/wp-json/bma/v1/bookings/match', {
    method: 'POST',
    headers: {
        'Authorization': auth,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        phone_number: '+61412345678',
        context: 'json'
    })
})
.then(res => res.json())
.then(data => {
    console.log('Found bookings:', data.bookings_found);
    data.bookings.forEach(booking => {
        console.log(`${booking.guest_name} - Room ${booking.room}`);
    });
})
.catch(err => console.error('Error:', err));
```

**Get comparison data:**
```javascript
const auth = 'Basic ' + btoa('admin:xxxx xxxx xxxx xxxx xxxx xxxx');

fetch('https://admin.hotelnumberfour.com/wp-json/bma/v1/comparison', {
    method: 'POST',
    headers: {
        'Authorization': auth,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        hotel_booking_id: 12345,
        resos_booking_id: '5f8a7b2c3d1e4f5g6h7i8j9k',
        date: '2025-11-20'
    })
})
.then(res => res.json())
.then(data => {
    console.log('Suggested updates:', data.comparison.suggested_updates);
    console.log('Discrepancies:', data.comparison.discrepancies);
});
```

### Python Example

```python
import requests
from requests.auth import HTTPBasicAuth

base_url = 'https://admin.hotelnumberfour.com/wp-json/bma/v1'
auth = HTTPBasicAuth('admin', 'xxxx xxxx xxxx xxxx xxxx xxxx')

# Search by email
response = requests.post(
    f'{base_url}/bookings/match',
    auth=auth,
    json={
        'email_address': 'john@example.com',
        'context': 'json'
    }
)

if response.status_code == 200:
    data = response.json()
    print(f"Found {data['bookings_found']} bookings")
    for booking in data['bookings']:
        print(f"{booking['guest_name']} - {booking['total_nights']} nights")
else:
    print(f"Error: {response.status_code}")
    print(response.json())
```

---

## Testing with Postman

**Setup:**
1. Create new request
2. Set method to POST/GET
3. Enter URL: `https://yoursite.com/wp-json/bma/v1/bookings/match`
4. Go to Authorization tab
5. Select "Basic Auth"
6. Enter username and Application Password
7. Go to Body tab (for POST)
8. Select "raw" and "JSON"
9. Enter request body
10. Click Send

**Example Collection:**

```json
{
  "info": {
    "name": "Booking Match API",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "Match by Email",
      "request": {
        "method": "POST",
        "url": "{{base_url}}/bookings/match",
        "body": {
          "mode": "raw",
          "raw": "{\"email_address\": \"john@example.com\"}"
        }
      }
    },
    {
      "name": "Get Summary",
      "request": {
        "method": "GET",
        "url": "{{base_url}}/summary?hours_back=48"
      }
    }
  ],
  "variable": [
    {
      "key": "base_url",
      "value": "https://yoursite.com/wp-json/bma/v1"
    }
  ]
}
```

---

## CORS Support

The API automatically adds CORS headers to support Chrome extension requests:

```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: POST, GET, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization
Access-Control-Allow-Credentials: true
```

---

## Changelog

### Version 1.5.0
- Added `/staying` endpoint
- Added `/comparison` endpoint
- Added booking management endpoints
- Added opening hours and form data endpoints

### Version 1.0.0
- Initial release
- `/bookings/match` endpoint
- `/summary` endpoint

---

## Related Documentation

- [API_REFERENCE.md](API_REFERENCE.md) - Class and method documentation
- [TEMPLATE_REFERENCE.md](TEMPLATE_REFERENCE.md) - Template documentation
- [ARCHITECTURE.md](ARCHITECTURE.md) - System architecture
- [FUNCTION_CHEAT_SHEET.md](FUNCTION_CHEAT_SHEET.md) - Quick reference
