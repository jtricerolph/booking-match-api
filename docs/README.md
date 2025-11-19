# Booking Match API Documentation

Comprehensive documentation for the Booking Match API WordPress plugin.

## Documentation Files

### [API_REFERENCE.md](API_REFERENCE.md)
Complete class and method reference with detailed documentation for all PHP classes:
- BMA_REST_Controller - REST API endpoint handler
- BMA_Matcher - Matching logic engine
- BMA_Comparison - Booking comparison logic
- BMA_NewBook_Search - NewBook PMS integration
- BMA_Response_Formatter - Response formatting
- Helper classes and utility functions

**Use this when:** You need to understand how classes work, method signatures, or want to extend the plugin.

### [TEMPLATE_REFERENCE.md](TEMPLATE_REFERENCE.md)
Template documentation covering all HTML response templates:
- Available templates (chrome-sidepanel, chrome-staying, webapp, etc.)
- Variables available in each template
- Data structures passed to templates
- CSS class reference
- Customization examples

**Use this when:** You're building a client application, customizing HTML responses, or creating new templates.

### [REST_API_ENDPOINTS.md](REST_API_ENDPOINTS.md)
Complete REST API endpoint reference:
- All available endpoints with URLs and HTTP methods
- Request parameters and response formats
- Authentication requirements
- Error codes and handling
- Usage examples in multiple languages (cURL, JavaScript, Python)

**Use this when:** You're integrating with the API, building a client, or troubleshooting API calls.

### [ARCHITECTURE.md](ARCHITECTURE.md)
System architecture and design documentation:
- Overview of the matching algorithm
- Integration with NewBook PMS and ResOS
- Data flow diagrams
- Caching strategy
- Performance considerations
- Deployment architecture

**Use this when:** You want to understand how the system works, optimize performance, or plan integration.

### [FUNCTION_CHEAT_SHEET.md](FUNCTION_CHEAT_SHEET.md)
Quick reference guide with common functions and use cases:
- Function signatures
- Input parameters and output formats
- Common workflows
- Code examples
- Quick lookup for common tasks

**Use this when:** You need a quick reference or want to copy-paste working code examples.

## Quick Start

### For Developers Integrating with the API

1. Start with [REST_API_ENDPOINTS.md](REST_API_ENDPOINTS.md) to understand available endpoints
2. Review [FUNCTION_CHEAT_SHEET.md](FUNCTION_CHEAT_SHEET.md) for quick examples
3. Check [TEMPLATE_REFERENCE.md](TEMPLATE_REFERENCE.md) if building a UI client

### For Developers Extending the Plugin

1. Read [ARCHITECTURE.md](ARCHITECTURE.md) to understand the system
2. Study [API_REFERENCE.md](API_REFERENCE.md) for class documentation
3. Use [FUNCTION_CHEAT_SHEET.md](FUNCTION_CHEAT_SHEET.md) for common patterns

### For System Administrators

1. Review [ARCHITECTURE.md](ARCHITECTURE.md) for deployment guidance
2. Check [REST_API_ENDPOINTS.md](REST_API_ENDPOINTS.md) for authentication setup
3. Use [FUNCTION_CHEAT_SHEET.md](FUNCTION_CHEAT_SHEET.md) for debugging

## Common Tasks

### Search for a Booking by Email

**REST API:**
```bash
curl -X POST https://yoursite.com/wp-json/bma/v1/bookings/match \
  -u "username:app_password" \
  -H "Content-Type: application/json" \
  -d '{"email_address": "guest@example.com"}'
```

**PHP:**
```php
$searcher = new BMA_NewBook_Search();
$results = $searcher->search_bookings(array('email' => 'guest@example.com'));
```

See [FUNCTION_CHEAT_SHEET.md](FUNCTION_CHEAT_SHEET.md#search--matching) for more examples.

### Get Bookings Staying on a Date

**REST API:**
```bash
curl -X GET "https://yoursite.com/wp-json/bma/v1/staying?date=2025-11-20" \
  -u "username:app_password"
```

See [REST_API_ENDPOINTS.md](REST_API_ENDPOINTS.md#get-bmav1staying) for details.

### Understand the Matching Algorithm

See [ARCHITECTURE.md](ARCHITECTURE.md#matching-algorithm) for a complete explanation with diagrams.

### Customize HTML Templates

See [TEMPLATE_REFERENCE.md](TEMPLATE_REFERENCE.md#customization-guide) for customization examples.

## API Versions

**Current Version:** 1.5.0

**API Namespace:** `/bma/v1`

## Authentication

The API uses WordPress authentication. Two methods are supported:

1. **Session Cookies** - For logged-in WordPress users
2. **Application Passwords** - For programmatic access (recommended)

Generate Application Password:
1. WordPress Admin > Users > Profile
2. Scroll to "Application Passwords"
3. Enter name and click "Add New Application Password"
4. Use with HTTP Basic Auth

See [REST_API_ENDPOINTS.md](REST_API_ENDPOINTS.md#authentication) for details.

## Support

### Debug Logging

Enable in wp-config.php:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Or via plugin setting:
```php
update_option('bma_enable_debug_logging', true);
```

View logs:
```bash
tail -f wp-content/debug.log
```

### Common Issues

**Authentication Errors:**
- Verify Application Password is correct
- Check user has 'read' capability
- See [REST_API_ENDPOINTS.md](REST_API_ENDPOINTS.md#authentication)

**Not Confident Errors:**
- Provide email, phone, or agent reference
- Name alone is not confident enough
- See [REST_API_ENDPOINTS.md](REST_API_ENDPOINTS.md#search-confidence-rules)

**No Results Found:**
- Check date ranges in NewBook
- Verify booking exists
- Try force_refresh=true parameter

## Contributing

When contributing to documentation:

1. Follow existing format and style
2. Include code examples
3. Test all code examples
4. Update table of contents
5. Cross-reference related sections

## License

GPL v2 or later

---

## Documentation Index

### By Topic

**Getting Started:**
- [REST API Endpoints](REST_API_ENDPOINTS.md)
- [Function Cheat Sheet](FUNCTION_CHEAT_SHEET.md)

**API Integration:**
- [REST API Endpoints](REST_API_ENDPOINTS.md)
- [Template Reference](TEMPLATE_REFERENCE.md)
- [Function Cheat Sheet](FUNCTION_CHEAT_SHEET.md)

**Plugin Development:**
- [API Reference](API_REFERENCE.md)
- [Architecture](ARCHITECTURE.md)
- [Function Cheat Sheet](FUNCTION_CHEAT_SHEET.md)

**System Administration:**
- [Architecture](ARCHITECTURE.md)
- [REST API Endpoints](REST_API_ENDPOINTS.md#authentication)

### By Audience

**Frontend Developers:**
- [REST API Endpoints](REST_API_ENDPOINTS.md)
- [Template Reference](TEMPLATE_REFERENCE.md)
- [Function Cheat Sheet](FUNCTION_CHEAT_SHEET.md)

**Backend Developers:**
- [API Reference](API_REFERENCE.md)
- [Architecture](ARCHITECTURE.md)
- [Function Cheat Sheet](FUNCTION_CHEAT_SHEET.md)

**DevOps/System Admins:**
- [Architecture](ARCHITECTURE.md#deployment-architecture)
- [REST API Endpoints](REST_API_ENDPOINTS.md#authentication)
- [Function Cheat Sheet](FUNCTION_CHEAT_SHEET.md#debugging)

---

Last Updated: 2025-11-19
Version: 1.5.0
