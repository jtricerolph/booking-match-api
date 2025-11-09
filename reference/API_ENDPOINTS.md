# Booking Match API - REST Endpoints Reference

Complete reference for all REST API endpoints provided by the Booking Match API plugin.

## Base URL

```
https://your-wordpress-site.com/wp-json/bma/v1/
```

## Authentication

All endpoints require WordPress authentication via Application Passwords (HTTP Basic Auth).

**Headers:**
```
Authorization: Basic base64(username:application_password)
Content-Type: application/json
```

### ⚠️ CRITICAL: Resos API Authentication Requirements

The WordPress plugin acts as a proxy between the Chrome extension and the Resos API. The Resos API has specific authentication requirements:

**Resos API Authentication:**
- **Scheme:** Basic Authentication (NOT Bearer tokens)
- **Format:** `Authorization: Basic base64(api_key + ':')`
- **Important:** The API key must be base64 encoded with a trailing colon
- **Example:** `base64_encode($resos_api_key . ':')`

**Common Issue (Fixed in commit de4a091):**

Early versions incorrectly used `Bearer` token authentication for fetch operations:
```php
// ❌ WRONG - Causes 401 Unauthorized
'Authorization' => 'Bearer ' . $resos_api_key
```

**Correct implementation:**
```php
// ✅ CORRECT - Required for Resos API
'Authorization' => 'Basic ' . base64_encode($resos_api_key . ':')
```

This affects all methods in `BMA_Booking_Actions` that communicate with Resos API:
- `fetch_opening_hours()`
- `fetch_available_times()`
- `fetch_special_events()`
- `fetch_dietary_choices()`
- `create_resos_booking()`
- `update_resos_booking()`
- `exclude_resos_match()`

**Verification:**
```bash
# Test with curl (replace YOUR_API_KEY with actual key)
curl "https://api.resos.com/v1/customFields" \
  -H "Authorization: Basic $(echo -n 'YOUR_API_KEY:' | base64)"
```

---

## GET/POST `/opening-hours`

Fetch restaurant opening hours/service periods.

### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `date` | string | No | Date in YYYY-MM-DD format. Omit for general opening hours |
| `context` | string | No | Response format: "chrome-extension" for HTML, omit for JSON |

### Request Examples

**JSON Response:**
```bash
curl -X GET "https://site.com/wp-json/bma/v1/opening-hours?date=2025-01-15" \
  -u "username:app_password"
```

**HTML Response:**
```bash
curl -X POST "https://site.com/wp-json/bma/v1/opening-hours" \
  -u "username:app_password" \
  -H "Content-Type: application/json" \
  -d '{"date":"2025-01-15","context":"chrome-extension"}'
```

### Response (JSON)

```json
{
  "success": true,
  "data": [
    {
      "_id": "507f1f77bcf86cd799439011",
      "name": "Lunch Service",
      "open": 1200,
      "close": 1430,
      "interval": 15,
      "duration": 90,
      "isSpecial": false,
      "dates": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"]
    },
    {
      "_id": "507f1f77bcf86cd799439012",
      "name": "Dinner Service",
      "open": 1800,
      "close": 2200,
      "interval": 15,
      "duration": 120,
      "isSpecial": false,
      "dates": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"]
    }
  ]
}
```

### Response (HTML - context=chrome-extension)

```json
{
  "success": true,
  "html": "<option value=\"507f1f77bcf86cd799439011\">Lunch Service (12:00-14:30)</option>\n<option value=\"507f1f77bcf86cd799439012\">Dinner Service (18:00-22:00)</option>",
  "data": [...]
}
```

### Field Descriptions

- `_id`: Unique opening hour period ID (use for booking creation)
- `name`: Display name for the service period
- `open`: Opening time in HHMM format (1800 = 18:00)
- `close`: Closing time in HHMM format (2200 = 22:00)
- `interval`: Time slot interval in minutes (15 = every 15 minutes)
- `duration`: Default booking duration in minutes (120 = 2 hours)
- `isSpecial`: Whether this is a special event opening hour
- `dates`: Array of days when this period applies

### Caching

- **Duration:** 1 hour
- **Key:** `bma_opening_hours_{date}` or `bma_opening_hours_general`
- **Clear:** `delete_transient('bma_opening_hours_2025-01-15')`

### Error Responses

```json
{
  "success": false,
  "message": "No opening hours found",
  "data": []
}
```

---

## POST `/available-times`

Fetch available time slots for a specific date and party size.

### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `date` | string | **Yes** | Date in YYYY-MM-DD format |
| `people` | integer | **Yes** | Number of people (party size) |
| `opening_hour_id` | string | No | Filter by specific opening hour period ID |
| `context` | string | No | Response format: "chrome-extension" for HTML |

### Request Example

```bash
curl -X POST "https://site.com/wp-json/bma/v1/available-times" \
  -u "username:app_password" \
  -H "Content-Type: application/json" \
  -d '{
    "date": "2025-01-15",
    "people": 4,
    "context": "chrome-extension"
  }'
```

### Response (JSON)

```json
{
  "success": true,
  "times": [
    "18:00",
    "18:15",
    "18:30",
    "18:45",
    "19:00",
    "19:15",
    "20:00",
    "20:15"
  ],
  "periods": [
    {
      "_id": "507f1f77bcf86cd799439012",
      "name": "Dinner Service",
      "open": 1800,
      "close": 2200,
      "availableTimes": ["18:00", "18:15", "18:30", ...]
    }
  ]
}
```

### Response (HTML - context=chrome-extension)

```json
{
  "success": true,
  "html": "<div class=\"bma-time-slots-grid\">...</div>",
  "times": [...],
  "periods": [...]
}
```

**HTML Structure:**
```html
<div class="bma-time-slots-grid">
  <div class="time-slot-period">
    <div class="time-slot-period-header">Dinner Service</div>
    <div class="time-slot-buttons">
      <button class="time-slot-btn" data-time="1800">18:00</button>
      <button class="time-slot-btn" data-time="1815">18:15</button>
      <button class="time-slot-btn unavailable" data-time="1830" data-tooltip="Fully booked - Override allowed">18:30</button>
      ...
    </div>
  </div>
</div>
```

### Availability Logic

Times are **available** if:
- Within opening hours
- Not already fully booked
- Before last seating time (close time - duration)
- Not blocked by special events

Times show as **unavailable but selectable** (greyed with tooltip) if:
- Fully booked but override allowed
- Near capacity for party size

### Caching

**None** - Real-time availability check on every request

### Error Responses

```json
{
  "success": false,
  "message": "Date and people are required",
  "times": [],
  "periods": []
}
```

---

## GET `/dietary-choices`

Fetch dietary requirement options from Resos custom fields.

### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `context` | string | No | Response format: "chrome-extension" for HTML |

### Request Example

```bash
curl -X GET "https://site.com/wp-json/bma/v1/dietary-choices?context=chrome-extension" \
  -u "username:app_password"
```

### Response (JSON)

```json
{
  "success": true,
  "choices": [
    {
      "_id": "choice_5f8a1b2c3d4e5678901234ab",
      "name": "Gluten Free"
    },
    {
      "_id": "choice_5f8a1b2c3d4e5678901234ac",
      "name": "Vegetarian"
    },
    {
      "_id": "choice_5f8a1b2c3d4e5678901234ad",
      "name": "Vegan"
    },
    {
      "_id": "choice_5f8a1b2c3d4e5678901234ae",
      "name": "Dairy Free"
    },
    {
      "_id": "choice_5f8a1b2c3d4e5678901234af",
      "name": "Nut Allergy"
    }
  ]
}
```

### Response (HTML - context=chrome-extension)

```json
{
  "success": true,
  "html": "<label><input type=\"checkbox\" class=\"diet-checkbox\" data-choice-id=\"choice_5f8a1b2c3d4e5678901234ab\" data-choice-name=\"Gluten Free\"> Gluten Free</label>\n<label><input type=\"checkbox\" class=\"diet-checkbox\" data-choice-id=\"choice_5f8a1b2c3d4e5678901234ac\" data-choice-name=\"Vegetarian\"> Vegetarian</label>...",
  "choices": [...]
}
```

### Implementation Notes

1. Fetches from Resos `/customFields` endpoint
2. Filters for field with name `" Dietary Requirements"` **(note leading space!)**
3. Extracts `multipleChoiceSelections` array
4. Returns choice objects with `_id` and `name`

### Usage in Booking Creation

When submitting a booking, collect selected choice IDs:
```javascript
const selectedIds = Array.from(document.querySelectorAll('.diet-checkbox:checked'))
  .map(cb => cb.dataset.choiceId)
  .join(',');

// Submit as: dietary_requirements: "choice_abc,choice_def,choice_xyz"
```

### Caching

- **Duration:** 24 hours
- **Key:** `bma_dietary_choices`
- **Clear:** `delete_transient('bma_dietary_choices')`

### Error Responses

```json
{
  "success": true,
  "choices": []
}
```
*(Returns empty array if field not found)*

---

## GET `/special-events`

Fetch special events, closures, or restrictions for a specific date.

### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `date` | string | **Yes** | Date in YYYY-MM-DD format |
| `context` | string | No | Response format: "chrome-extension" for HTML alerts |

### Request Example

```bash
curl -X GET "https://site.com/wp-json/bma/v1/special-events?date=2025-01-15&context=chrome-extension" \
  -u "username:app_password"
```

### Response (JSON)

```json
{
  "success": true,
  "events": [
    {
      "date": "2025-01-15",
      "name": "Private Event",
      "type": "closed",
      "isOpen": false,
      "open": null,
      "close": null
    },
    {
      "date": "2025-01-15",
      "name": "Early Close for Maintenance",
      "type": "restricted",
      "isOpen": false,
      "open": 1800,
      "close": 2000
    }
  ]
}
```

### Response (HTML - context=chrome-extension)

```json
{
  "success": true,
  "html": "<div class=\"bma-special-event-alert\">Private Event</div>\n<div class=\"bma-special-event-alert\">Early Close for Maintenance</div>",
  "events": [...]
}
```

### Event Types

**Full Day Closure:** `open` and `close` are `null`
```json
{
  "isOpen": false,
  "open": null,
  "close": null
}
```
→ Entire day unavailable

**Partial Restriction:** `open` and `close` define blocked period
```json
{
  "isOpen": false,
  "open": 1800,
  "close": 2000
}
```
→ 18:00-20:00 unavailable

**Special Opening:** `isOpen: true`
```json
{
  "isOpen": true,
  "open": 1200,
  "close": 1500
}
```
→ Usually closed but open on this date (skip in restriction checks)

### Caching

- **Duration:** 30 minutes
- **Key:** `bma_special_events_{date}`
- **Clear:** `delete_transient('bma_special_events_2025-01-15')`

### Error Responses

```json
{
  "success": true,
  "events": []
}
```
*(Returns empty array if no events)*

---

## POST `/bookings/create`

Create a new restaurant booking in Resos.

### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `date` | string | **Yes** | Booking date (YYYY-MM-DD) |
| `time` | string | **Yes** | Booking time (HH:MM) |
| `people` | integer | **Yes** | Party size |
| `guest_name` | string | **Yes** | Guest name |
| `opening_hour_id` | string | **Yes** | Opening hour period ID |
| `guest_phone` | string | No | Phone number (auto-formatted to E.164) |
| `guest_email` | string | No | Email address |
| `notification_sms` | boolean | No | Allow SMS notifications |
| `notification_email` | boolean | No | Allow email notifications |
| `booking_ref` | string | No | Hotel booking reference |
| `hotel_guest` | string | No | "Yes" or "No" |
| `dbb` | string | No | "Yes" or "No" (package status) |
| `dietary_requirements` | string | No | Comma-separated choice IDs |
| `dietary_other` | string | No | Free-text dietary notes |
| `booking_note` | string | No | Internal restaurant note |
| `language_code` | string | No | Language code (default: "en") |

### Request Example

```bash
curl -X POST "https://site.com/wp-json/bma/v1/bookings/create" \
  -u "username:app_password" \
  -H "Content-Type: application/json" \
  -d '{
    "date": "2025-01-15",
    "time": "19:00",
    "people": 4,
    "guest_name": "John Smith",
    "opening_hour_id": "507f1f77bcf86cd799439012",
    "guest_phone": "+441234567890",
    "guest_email": "john@example.com",
    "notification_sms": true,
    "notification_email": true,
    "booking_ref": "NB12345",
    "hotel_guest": "Yes",
    "dbb": "Yes",
    "dietary_requirements": "choice_abc,choice_def",
    "dietary_other": "No shellfish",
    "booking_note": "Anniversary celebration",
    "language_code": "en"
  }'
```

### Response (Success)

```json
{
  "success": true,
  "message": "Booking created successfully",
  "booking_id": "507f1f77bcf86cd799439099",
  "booking_data": {
    "date": "2025-01-15",
    "time": "19:00",
    "people": 4,
    "guest": {
      "name": "John Smith",
      "phone": "+441234567890",
      "email": "john@example.com"
    }
  }
}
```

### Response (Error)

```json
{
  "success": false,
  "message": "Validation error: Missing required field",
  "errors": [
    "guest_name is required",
    "opening_hour_id is required"
  ]
}
```

### Processing Flow

1. **Validate Required Fields**
   - date, time, people, guest_name, opening_hour_id

2. **Format Phone Number**
   - Convert to E.164 format (+44...)
   - Add country code if missing

3. **Map Custom Fields**
   - Fetch custom field definitions from Resos
   - Map internal names to Resos field IDs
   - Build field value objects

4. **Create Booking**
   - POST to `https://api.resos.com/v1/bookings`
   - Include all guest and booking data
   - Attach custom fields array

5. **Add Restaurant Note** (if provided)
   - Separate POST to `/bookings/{id}/restaurantNote`
   - Only if booking creation successful

### Custom Fields Submission Format

**Single Choice (Hotel Guest, DBB):**
```json
{
  "_id": "field_id_123",
  "name": "Hotel Guest",
  "value": "choice_id_yes",
  "multipleChoiceValueName": "Yes"
}
```

**Multi-Select (Dietary Requirements):**
```json
{
  "_id": "field_id_456",
  "name": " Dietary Requirements",
  "value": [
    {"_id": "choice_abc", "name": "Gluten Free", "value": true},
    {"_id": "choice_def", "name": "Vegetarian", "value": true}
  ]
}
```

**Text Field (Booking #):**
```json
{
  "_id": "field_id_789",
  "name": "Booking #",
  "value": "NB12345"
}
```

### Important Notes

1. **Phone Formatting:** Always use international format (+44...)
2. **Leading Space:** " Dietary Requirements" has a space at the start
3. **Choice IDs:** Must match exact IDs from Resos
4. **Booking Note:** Added via separate API call (may fail without failing booking)
5. **All-or-Nothing:** Custom fields either all succeed or all fail

### Error Scenarios

| Error | Cause | Solution |
|-------|-------|----------|
| Missing API key | Resos API key not configured | Set in WordPress options |
| Invalid opening_hour_id | ID doesn't exist in Resos | Use ID from /opening-hours endpoint |
| Custom field not found | Field name mismatch | Verify exact field name (including spaces) |
| Time not available | Fully booked | Use override or select different time |
| Phone format error | Invalid phone number | Use E.164 format |

---

## Rate Limiting

No rate limiting currently implemented, but consider:
- Resos API may have rate limits
- WordPress server capacity
- Cache heavily to reduce external calls

---

## Webhooks

Not currently supported. Future implementation could include:
- Booking created webhook
- Booking updated webhook
- Booking cancelled webhook

---

## Versioning

Current API version: **v1**

Endpoint structure: `/wp-json/bma/v1/endpoint`

Future versions will use: `/wp-json/bma/v2/endpoint`

---

## Support

For issues or questions:
1. Check WordPress error logs (wp-content/debug.log)
2. Verify Resos API key is correct
3. Test endpoints manually with cURL/Postman
4. Review this documentation for correct parameter formats
