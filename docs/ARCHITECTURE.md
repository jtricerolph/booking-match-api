# System Architecture

Comprehensive documentation of the Booking Match API plugin architecture, data flow, and integration points.

## Table of Contents

- [Overview](#overview)
- [System Components](#system-components)
- [Matching Algorithm](#matching-algorithm)
- [Data Flow](#data-flow)
- [Integration Points](#integration-points)
- [Caching Strategy](#caching-strategy)
- [Database Schema](#database-schema)
- [Security Model](#security-model)
- [Performance Considerations](#performance-considerations)
- [Deployment Architecture](#deployment-architecture)

---

## Overview

The Booking Match API plugin is a WordPress REST API plugin that bridges NewBook PMS (Property Management System) and ResOS (Restaurant Booking System) by intelligently matching hotel bookings with restaurant reservations.

### Key Objectives

1. **Automated Matching**: Automatically find and match hotel bookings with restaurant reservations
2. **Confidence Scoring**: Use multiple data points to determine match confidence
3. **Multi-Client Support**: Serve multiple client types (Chrome extension, web apps, mobile)
4. **Performance**: Minimize API calls through intelligent caching
5. **Extensibility**: Plugin-based architecture for easy customization

### Architecture Principles

- **Stateless API**: Each request is independent
- **Cache-First**: Minimize external API calls
- **Fail-Safe**: Graceful degradation when APIs unavailable
- **Extensible**: Filter and action hooks for customization
- **Secure**: WordPress authentication and capability checks

---

## System Components

### Core Plugin Architecture

```
booking-match-api/
├── booking-match-api.php          # Main plugin file, bootstrapping
├── includes/
│   ├── class-bma-rest-controller.php      # REST API endpoint handler
│   ├── class-bma-matcher.php              # Matching logic engine
│   ├── class-bma-comparison.php           # Booking comparison
│   ├── class-bma-newbook-search.php       # NewBook API client
│   ├── class-bma-response-formatter.php   # Response formatting
│   ├── class-bma-authenticator.php        # Authentication
│   ├── class-bma-template-helper.php      # Template utilities
│   ├── class-bma-booking-actions.php      # Booking CRUD operations
│   ├── class-bma-booking-source.php       # Source detection
│   ├── class-bma-issue-checker.php        # Issue detection
│   ├── class-bma-gantt-chart.php          # Timeline visualization
│   └── class-bma-admin.php                # Admin settings page
└── templates/
    ├── chrome-sidepanel-response.php      # Chrome extension HTML
    ├── chrome-summary-response.php        # Summary view HTML
    ├── chrome-staying-response.php        # Staying view HTML
    ├── webapp-restaurant-response.php     # Mobile restaurant view
    ├── webapp-summary-response.php        # Mobile summary view
    └── webapp-checks-response.php         # Mobile checks view
```

### Component Responsibilities

#### BMA_REST_Controller
- Registers REST API routes
- Validates request parameters
- Authenticates users
- Orchestrates search and matching workflow
- Formats responses

#### BMA_Matcher
- Matches hotel bookings with restaurant bookings
- Implements priority-based matching algorithm
- Calculates confidence scores
- Handles group bookings
- Detects orphaned bookings

#### BMA_Comparison
- Compares hotel and restaurant booking data
- Identifies discrepancies
- Suggests updates
- Normalizes data for comparison

#### BMA_NewBook_Search
- Searches NewBook API
- Implements confidence-based search
- Handles various search criteria
- Manages NewBook cache integration

#### BMA_Response_Formatter
- Formats JSON responses
- Loads and renders HTML templates
- Calculates badge counts
- Determines auto-open behavior

---

## Matching Algorithm

The matching algorithm uses a priority-based approach with composite scoring for fuzzy matches.

### Matching Priority Levels

```
Priority 1: PRIMARY MATCHES (Highest Confidence)
├── Booking ID in Resos custom field "Booking #"
└── Agent reference in Resos custom field

Priority 2: GROUP MATCHES
├── GROUP/EXCLUDE field: "GROUP: 12345, 12346, 12347"
└── GROUP/EXCLUDE field: "INDIVIDUAL: 12345"

Priority 3: EXCLUSIONS (Negative Matching)
├── GROUP/EXCLUDE field: "EXCLUDE: 12345"
└── Legacy notes: "NOT-#12345"

Priority 4: SUGGESTED MATCHES (Medium Confidence)
├── Booking ID in Resos notes
└── Agent reference in Resos notes

Priority 5: COMPOSITE MATCHES (Scored)
├── Room number in notes: +8 points
├── Surname match: +7 points
├── Phone match (last 8 digits): +9 points
└── Email match: +10 points
```

### Composite Scoring System

```
Confidence Levels:
- HIGH:   Score ≥20 OR ≥3 matching factors
- MEDIUM: Score ≥15 OR ≥2 matching factors
- LOW:    Score >0
```

**Example Scores:**
- Email + Phone = 19 points (Medium, but 2 factors = Medium)
- Email + Phone + Surname = 26 points (High, 3 factors = High)
- Room + Surname = 15 points (Medium)
- Phone alone = 9 points (Low)

### Matching Flow Diagram

```
┌─────────────────────────┐
│  Search for Hotel       │
│  Booking                │
└───────────┬─────────────┘
            │
            ├─ By Booking ID → Direct lookup
            ├─ By Email → Search guest contact details
            ├─ By Phone → Search guest contact details
            ├─ By Agent Ref → Search booking references
            └─ By Name + X → Combination search
            │
┌───────────▼─────────────┐
│  Fetch Resos Bookings   │
│  for Each Night         │
└───────────┬─────────────┘
            │
┌───────────▼─────────────┐
│  For Each Resos Booking │
│  on the Night           │
└───────────┬─────────────┘
            │
            ├─ Priority 1: Check custom fields
            │  ├─ "Booking #" matches? → PRIMARY MATCH
            │  └─ Agent ref matches? → PRIMARY MATCH
            │
            ├─ Priority 2: Check GROUP/EXCLUDE field
            │  ├─ Is excluded? → NO MATCH
            │  ├─ In group list? → GROUP MATCH
            │  └─ Individual match? → INDIVIDUAL MATCH
            │
            ├─ Priority 3: Check if matched elsewhere
            │  └─ "Booking #" = other hotel booking? → MATCHED ELSEWHERE
            │
            ├─ Priority 4: Check notes
            │  ├─ Booking ID in notes? → SUGGESTED MATCH
            │  └─ Agent ref in notes? → SUGGESTED MATCH
            │
            └─ Priority 5: Composite scoring
               ├─ Check room number in notes
               ├─ Compare surnames
               ├─ Compare phone numbers
               ├─ Compare email addresses
               └─ Calculate score → COMPOSITE MATCH (if >0)
            │
┌───────────▼─────────────┐
│  Sort Matches by Score  │
│  (Highest First)        │
└───────────┬─────────────┘
            │
┌───────────▼─────────────┐
│  Return Match Results   │
│  for All Nights         │
└─────────────────────────┘
```

### Group Booking Logic

Group bookings allow multiple hotel rooms to share a single restaurant reservation.

**GROUP/EXCLUDE Field Format:**
```
GROUP: 12345, 12346, 12347
```

**Matching Behavior:**
- Lead booking (12345) shows primary match
- Member bookings (12346, 12347) show "with Room 101" (lead room)
- All bookings display group indicator
- Group members don't show update suggestions

### Exclusion Logic

**Purpose:** Prevent incorrect matches when names/details overlap

**Format:**
```
EXCLUDE: 12345, 12348
```

**Behavior:**
- Resos booking will NOT match excluded hotel booking IDs
- Used when guest names are similar but different guests
- Checked BEFORE any matching logic

---

## Data Flow

### Complete Request Flow

```
┌─────────────────────────────────────────────────────────────┐
│                        CLIENT                                │
│  (Chrome Extension, Web App, Mobile App, cURL)              │
└────────────────────────┬────────────────────────────────────┘
                         │
                         │ POST /bma/v1/bookings/match
                         │ {email_address: "guest@example.com"}
                         │
┌────────────────────────▼────────────────────────────────────┐
│                   WORDPRESS REST API                         │
│              (Authentication & Routing)                      │
└────────────────────────┬────────────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────────────┐
│               BMA_REST_Controller                            │
│  • Validate parameters                                       │
│  • Check search confidence                                   │
│  • Extract request context                                   │
└────────────────────────┬────────────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────────────┐
│             BMA_NewBook_Search                               │
│  • Search NewBook by email                                   │
│  • Check newbook-api-cache first                            │
│  • Fetch from NewBook API if needed                         │
│  • Return matching bookings                                  │
└────────────────────────┬────────────────────────────────────┘
                         │
                         │ Found booking(s)
                         │
┌────────────────────────▼────────────────────────────────────┐
│        PRE-CACHE: Collect All Dates                          │
│  • Extract dates from all bookings                           │
│  • Remove duplicates                                         │
│  • Warm caches for all dates                                │
└────────────────────────┬────────────────────────────────────┘
                         │
        ┌────────────────┴────────────────┐
        │                                  │
┌───────▼───────┐                 ┌───────▼────────┐
│   Warm ResOS  │                 │ Warm NewBook   │
│     Cache     │                 │     Cache      │
│               │                 │                │
│ Fetch all     │                 │ Fetch all      │
│ restaurant    │                 │ hotel bookings │
│ bookings for  │                 │ for each date  │
│ each date     │                 │                │
└───────┬───────┘                 └───────┬────────┘
        │                                  │
        └────────────────┬─────────────────┘
                         │
┌────────────────────────▼────────────────────────────────────┐
│                  BMA_Matcher                                 │
│  For each booking:                                           │
│    For each night:                                           │
│      • Fetch Resos bookings (from cache)                    │
│      • Fetch hotel bookings (from cache)                    │
│      • Match using priority algorithm                       │
│      • Calculate scores                                      │
│      • Sort by score                                         │
└────────────────────────┬────────────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────────────┐
│           BMA_Response_Formatter                             │
│  • Check context (json, chrome-sidepanel, etc.)             │
│  • Calculate badge counts                                    │
│  • Determine auto-open behavior                             │
│  • Format JSON or load HTML template                        │
└────────────────────────┬────────────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────────────┐
│                WP_REST_Response                              │
│  • Set headers (CORS, Content-Type)                         │
│  • Send response to client                                   │
└────────────────────────┬────────────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────────────┐
│                      CLIENT                                  │
│  • Receive JSON or HTML                                      │
│  • Render UI                                                 │
│  • Handle user interactions                                  │
└─────────────────────────────────────────────────────────────┘
```

### Cache Pre-warming Strategy

**Problem:** Fetching Resos/NewBook data for each booking/night individually causes excessive API calls.

**Solution:** Pre-cache all unique dates before matching.

```
BEFORE (Slow - 6 API calls):
Booking A (3 nights) → fetch date1, fetch date2, fetch date3
Booking B (3 nights) → fetch date1, fetch date2, fetch date3

AFTER (Fast - 3 API calls):
Collect dates: [date1, date2, date3]
Pre-fetch all: fetch date1, fetch date2, fetch date3
Match Booking A → use cached data
Match Booking B → use cached data
```

---

## Integration Points

### NewBook PMS Integration

**Via:** `newbook-api-cache` plugin

**Data Flow:**
```
BMA_NewBook_Search
    ↓
NewBook_API_Cache::call_api()
    ↓
Check WordPress Transients Cache
    ↓ (if miss)
NewBook REST API
```

**API Endpoints Used:**
- `bookings_get` - Get single booking by ID
- `bookings_list` - Search bookings by date range/type

**Caching Strategy:**
- Transient key: `newbook_api_{endpoint}_{hash(params)}`
- TTL: Configurable (default: 1 hour)
- Force refresh: `force_refresh=true` clears and refetches

### ResOS Integration

**Via:** Direct Resos class (from `newbook-api-cache` plugin)

**Data Flow:**
```
BMA_Matcher::fetch_resos_bookings()
    ↓
Resos::get_bookings_by_date()
    ↓
Check WordPress Transients Cache
    ↓ (if miss)
ResOS GraphQL API
```

**API Operations:**
- Get bookings by date
- Get booking by ID
- Update booking custom fields
- Create new booking

**Caching Strategy:**
- Transient key: `resos_bookings_{date}`
- TTL: 5 minutes (shorter than NewBook due to real-time updates)
- Force refresh available

### WordPress Integration

**Hooks Used:**

```php
// Actions
add_action('rest_api_init', 'register_routes');
add_action('plugins_loaded', 'init');

// Filters
add_filter('rest_post_dispatch', 'add_cors_headers');
```

**Capabilities Required:**
- `read` - Minimum capability for API access
- Extensible via filters for custom requirements

---

## Caching Strategy

### Three-Tier Caching

```
┌─────────────────────────────────────────────────────────────┐
│                    TIER 1: Browser Cache                     │
│  Client-side caching (Chrome extension localStorage, etc.)  │
│  TTL: Session-based or 5 minutes                            │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          │ (cache miss or force refresh)
                          │
┌─────────────────────────▼───────────────────────────────────┐
│              TIER 2: WordPress Transients                    │
│  Server-side cache (wp_options table)                       │
│  TTL: 1 hour (NewBook), 5 min (ResOS)                      │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          │ (cache miss or force refresh)
                          │
┌─────────────────────────▼───────────────────────────────────┐
│            TIER 3: External API (Source of Truth)            │
│  NewBook API, ResOS API                                      │
│  Direct HTTP requests                                        │
└─────────────────────────────────────────────────────────────┘
```

### Cache Keys

**NewBook:**
```
newbook_api_bookings_get_{booking_id}
newbook_api_bookings_list_{hash(params)}
```

**ResOS:**
```
resos_bookings_{YYYY-MM-DD}
```

### Cache Invalidation

**Automatic:**
- TTL expiration
- `force_refresh=true` parameter

**Manual:**
```php
// Clear all NewBook cache
delete_transient('newbook_api_*');

// Clear specific date ResOS cache
delete_transient('resos_bookings_2025-11-20');
```

### Stale Cache Fallback

If API fails but cached data exists:
```php
if (!$fresh_data && $stale_cache) {
    $result = $stale_cache;
    $result['is_stale'] = true; // Flag for UI
    return $result;
}
```

**UI Indication:** Stale data shown with warning icon in templates.

---

## Database Schema

The plugin does not create custom database tables. It uses WordPress core tables.

### WordPress Options

| Option Name | Type | Description |
|-------------|------|-------------|
| `bma_booking_page_url` | string | URL to booking management page |
| `bma_enable_debug_logging` | boolean | Enable debug logging |
| `bma_package_inventory_name` | string | Inventory item name for packages |
| `bma_excluded_email_domains` | string | Comma-separated list of excluded email domains |

### WordPress Transients

| Transient Key | TTL | Data |
|---------------|-----|------|
| `newbook_api_bookings_get_{id}` | 1 hour | Single booking data |
| `newbook_api_bookings_list_{hash}` | 1 hour | List of bookings |
| `resos_bookings_{date}` | 5 min | Restaurant bookings for date |

### External Data Structures

**NewBook Booking Object:**
```php
array(
    'booking_id' => int,
    'booking_reference_id' => string,
    'booking_arrival' => datetime,
    'booking_departure' => datetime,
    'booking_status' => string,
    'guests' => array(
        array(
            'primary_client' => bool,
            'firstname' => string,
            'lastname' => string,
            'contact_details' => array(
                array('type' => 'email', 'content' => string),
                array('type' => 'phone', 'content' => string)
            )
        )
    ),
    'tariffs_quoted' => array(...),
    'inventory_items' => array(...)
)
```

**ResOS Booking Object:**
```php
array(
    '_id' => string,
    'restaurantId' => string,
    'guest' => array(
        'name' => string,
        'email' => string,
        'phone' => string
    ),
    'people' => int,
    'time' => datetime,
    'status' => string,
    'customFields' => array(
        array('name' => 'Booking #', 'value' => string),
        array('name' => 'Hotel Guest', 'multipleChoiceValueName' => string)
    ),
    'restaurantNotes' => array(...)
)
```

---

## Security Model

### Authentication Flow

```
┌────────────────────────────────────────────────────────────┐
│                      API Request                            │
└───────────────────────┬────────────────────────────────────┘
                        │
            ┌───────────┴──────────┐
            │                      │
┌───────────▼──────────┐  ┌───────▼──────────┐
│  Session Cookies     │  │  HTTP Basic Auth │
│  (WordPress login)   │  │  (App Password)  │
└───────────┬──────────┘  └───────┬──────────┘
            │                      │
            └───────────┬──────────┘
                        │
            ┌───────────▼──────────┐
            │  is_user_logged_in() │
            │  OR                  │
            │  wp_authenticate_    │
            │  application_        │
            │  password()          │
            └───────────┬──────────┘
                        │
            ┌───────────▼──────────┐
            │  Check 'read'        │
            │  Capability          │
            └───────────┬──────────┘
                        │
                  ┌─────┴─────┐
                  │           │
           ┌──────▼────┐  ┌──▼──────┐
           │  ALLOW    │  │  DENY   │
           │  (200 OK) │  │  (401)  │
           └───────────┘  └─────────┘
```

### Input Validation

**Parameter Sanitization:**
```php
'email_address' => sanitize_email($input)
'guest_name' => sanitize_text_field($input)
'booking_id' => intval($input)
'context' => in_array($input, $allowed_contexts) ? $input : 'json'
```

**SQL Injection Prevention:**
- Uses WordPress `$wpdb->prepare()` for all queries
- No direct SQL queries in plugin

**XSS Prevention:**
- All template output escaped: `esc_html()`, `esc_attr()`, `esc_url()`

### CORS Policy

```php
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: POST, GET, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization
```

**Note:** `*` allows all origins for Chrome extension support. Restrict for production if needed.

### Privacy Considerations

**IP Anonymization:**
```php
// IPv4: 192.168.1.123 → 192.168.1.0
// IPv6: 2001:db8::1 → 2001:db8::
```

**Excluded Email Domains:**
- Configure domains to exclude from matching (e.g., booking platforms)

**Debug Logging:**
- Respects `bma_enable_debug_logging` setting
- Always logs errors regardless of setting

---

## Performance Considerations

### Optimization Strategies

1. **Pre-cache Strategy**
   - Warm caches for all dates before matching
   - Reduces API calls from O(n*m) to O(n+m)

2. **Transient Caching**
   - NewBook: 1 hour TTL
   - ResOS: 5 minute TTL
   - Reduces external API load

3. **Lazy Loading**
   - HTML templates don't load full data until needed
   - Expandable cards prevent initial render bloat

4. **Database Indexes**
   - Relies on WordPress wp_options indexes for transients
   - No custom tables = no custom indexes needed

### Performance Metrics

**Typical Request Times:**

| Operation | Cold Cache | Warm Cache |
|-----------|------------|------------|
| Match by email | 2-5s | 100-300ms |
| Get summary (5 bookings) | 3-8s | 200-500ms |
| Get staying (15 bookings) | 5-12s | 300-800ms |

**API Call Reduction:**

| Scenario | Without Pre-cache | With Pre-cache | Improvement |
|----------|------------------|----------------|-------------|
| 1 booking, 3 nights | 6 calls | 3 calls | 50% |
| 5 bookings, 15 nights | 30 calls | 15 calls | 50% |
| 15 bookings, 45 nights | 90 calls | 45 calls | 50% |

### Bottlenecks

1. **NewBook API Speed** - External API can be slow (1-3s per request)
2. **ResOS GraphQL** - GraphQL queries can be complex
3. **WordPress Transients** - wp_options table can get large
4. **Template Rendering** - Large HTML templates can slow response

### Scalability

**Current Limits:**
- No rate limiting on API
- No request queuing
- Synchronous processing

**Recommended for:**
- Small to medium hotels (up to 50 rooms)
- Moderate request volume (< 100 req/min)

**For larger scale:**
- Implement rate limiting
- Use persistent object cache (Redis, Memcached)
- Add request queuing
- Consider async processing

---

## Deployment Architecture

### Production Setup

```
┌──────────────────────────────────────────────────────────┐
│                    Load Balancer                          │
│              (CloudFlare, AWS ALB, etc.)                 │
└────────────────────────┬─────────────────────────────────┘
                         │
         ┌───────────────┴───────────────┐
         │                               │
┌────────▼────────┐             ┌───────▼────────┐
│  Web Server 1   │             │  Web Server 2  │
│  (WordPress)    │             │  (WordPress)   │
│  + BMA Plugin   │             │  + BMA Plugin  │
└────────┬────────┘             └───────┬────────┘
         │                               │
         └───────────────┬───────────────┘
                         │
┌────────────────────────▼─────────────────────────────────┐
│                  MySQL Database                           │
│  (WordPress tables + transients)                         │
└───────────────────────┬──────────────────────────────────┘
                        │
┌───────────────────────▼──────────────────────────────────┐
│              Object Cache (Redis/Memcached)              │
│  (Optional - for persistent transient storage)           │
└──────────────────────────────────────────────────────────┘

         External APIs:
┌────────────────────┐       ┌─────────────────────┐
│   NewBook PMS API  │       │    ResOS API        │
└────────────────────┘       └─────────────────────┘
```

### Environment Variables

```php
// wp-config.php
define('BMA_BOOKING_PAGE_URL', 'https://admin.example.com/bookings/');
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', true);
```

### Plugin Dependencies

**Required:**
- WordPress 5.0+
- PHP 7.4+
- `newbook-api-cache` plugin (for NewBook integration)

**Optional:**
- Object cache plugin (Redis/Memcached)
- Debug Bar plugin (for debugging)

### Monitoring

**Key Metrics:**
- API response times
- Cache hit/miss rates
- Error rates
- Request volume

**Logging:**
```php
bma_log('Message', 'level');
// Writes to: wp-content/debug.log
```

**Error Tracking:**
- WordPress debug log
- HTTP status codes
- WP_Error objects in responses

---

## Related Documentation

- [API_REFERENCE.md](API_REFERENCE.md) - Class and method documentation
- [REST_API_ENDPOINTS.md](REST_API_ENDPOINTS.md) - API endpoint reference
- [TEMPLATE_REFERENCE.md](TEMPLATE_REFERENCE.md) - Template documentation
- [FUNCTION_CHEAT_SHEET.md](FUNCTION_CHEAT_SHEET.md) - Quick reference
