# GROUP/EXCLUDE Feature Implementation Plan

## Overview

This document outlines the implementation plan for adding GROUP and EXCLUDE functionality to the Booking Match API, replacing the current note-based exclusion system with a custom field-based approach that also supports grouping multiple NewBook bookings to a single Resos reservation.

## Problem Statement

### Current Issues
1. **Exclude Feature:** Uses restaurant notes (`NOT-#{booking_id}`) which cannot be edited or removed via API
2. **Multiple Exclusions:** Results in multiple notes accumulating on a booking
3. **No Group Support:** Cannot link multiple room bookings to a single restaurant reservation

### Use Case
Hotels often have group bookings where multiple rooms are booked together but only one restaurant reservation is made for the entire group. Currently, each room booking matches separately, causing confusion and duplicate matches.

## Solution Design

### Custom Field Specification

**Field Name:** `GROUP/EXCLUDE`
**Field Type:** Text (single-line text field in Resos)
**Format:** Comma-separated list of prefixed booking IDs

**Value Format:**
```
G-{booking_id},G-{booking_id},...,N-{booking_id},N-{booking_id},...
```

**Prefix Meanings:**
- `G-` = Group member (other NewBook booking IDs that are part of this group)
- `N-` = Excluded (NewBook booking IDs to exclude from matching)

**Examples:**
```
# Single restaurant booking for rooms 12345 and 12346
G-12346

# Exclude bookings 12347 and 12348 from matching
N-12347,N-12348

# Combined: Group with 12346, and exclude 12349
G-12346,N-12349
```

### Field Value Structure

**Lead Booking (Primary):**
- The Resos booking has the `GROUP/EXCLUDE` field
- Lists all other NewBook booking IDs in the group (G-prefix)
- Lists all excluded NewBook booking IDs (N-prefix)

**Member Bookings (Secondary):**
- When a NewBook booking is listed in a Resos booking's group list
- The NewBook booking doesn't need its own Resos reservation
- Display shows linkage to lead booking

### Advantages Over Notes

1. **Editable:** Can update/remove entries via API
2. **Structured:** Programmatic parsing of comma-separated values
3. **Dual Purpose:** Single field for both groups and excludes
4. **Clean:** No accumulation of multiple notes
5. **Queryable:** Can fetch and parse field values easily

## Implementation Components

### 1. Backend API Changes (WordPress Plugin)

#### A. Add Helper Functions (class-bma-booking-actions.php)

```php
/**
 * Parse GROUP/EXCLUDE field value
 * Returns array with 'groups' and 'excludes' keys
 */
private function parse_group_exclude_field($value)

/**
 * Build GROUP/EXCLUDE field value from arrays
 */
private function build_group_exclude_value($groups, $excludes)

/**
 * Add booking ID to GROUP/EXCLUDE field
 */
public function add_to_group_exclude($resos_booking_id, $booking_id, $type)

/**
 * Remove booking ID from GROUP/EXCLUDE field
 */
public function remove_from_group_exclude($resos_booking_id, $booking_id, $type)

/**
 * Update entire GROUP/EXCLUDE field
 */
public function update_group_exclude($resos_booking_id, $groups, $excludes)
```

#### B. Update Custom Field Map

```php
// In update_resos_booking() and process_custom_field_updates()
$custom_field_map = array(
    'dbb' => 'DBB',
    'booking_ref' => 'Booking #',
    'hotel_guest' => 'Hotel Guest',
    'group_exclude' => 'GROUP/EXCLUDE'  // NEW
);
```

#### C. Modify Exclude Endpoint

Change from note-based to field-based exclusion:

```php
// Old: POST /bookings/exclude
// - Added "NOT-#{booking_id}" note

// New: POST /bookings/exclude
// - Updates GROUP/EXCLUDE field with N-{booking_id}
public function exclude_resos_match($resos_booking_id, $hotel_booking_id) {
    // Fetch current GROUP/EXCLUDE value
    // Parse existing groups and excludes
    // Add N-{hotel_booking_id} to excludes
    // Update field via process_custom_field_updates()
}
```

#### D. New Group Management Endpoint

```php
// POST /bookings/group
// Parameters: resos_booking_id, hotel_booking_ids (array)
// Updates GROUP/EXCLUDE field with G-prefixed IDs
public function update_group_bookings($resos_booking_id, $hotel_booking_ids)
```

### 2. Matching Logic Changes (class-bma-matcher.php)

#### A. Update Exclusion Check

```php
// In match_resos_to_hotel()
// BEFORE (line 204-217):
$notes = $this->get_resos_notes($resos_booking);
if (stripos($notes, 'NOT-#' . $hotel_booking_id) !== false) {
    return array('matched' => false, 'excluded' => true);
}

// AFTER:
$group_exclude = $this->get_group_exclude_field($resos_booking);
if ($this->is_excluded($hotel_booking_id, $group_exclude)) {
    return array('matched' => false, 'excluded' => true,
                 'exclusion_reason' => 'Excluded via GROUP/EXCLUDE field');
}
```

#### B. Add Group Matching Logic

```php
/**
 * Check if a hotel booking ID is in the group list
 */
private function is_in_group($hotel_booking_id, $group_exclude_data)

/**
 * Check if a hotel booking ID is excluded
 */
private function is_excluded($hotel_booking_id, $group_exclude_data)

/**
 * Get the lead booking ID for a grouped booking
 */
private function get_lead_booking_id($hotel_booking_id, $all_resos_bookings)
```

#### C. Group Member Match Result

When a NewBook booking is found in a Resos booking's group list:

```php
return array(
    'matched' => true,
    'is_group_member' => true,
    'lead_resos_booking_id' => $resos_booking['_id'],
    'group_members' => $parsed_groups,  // All G- IDs
    'confidence' => 'high',
    'match_type' => 'group_member'
);
```

### 3. Display Changes (Templates)

#### A. Update chrome-sidepanel-response.php

**Current Display:**
```html
<div class="match-info">
    <span class="match-time">19:00</span>
    <span class="match-people">4 pax</span>
</div>
```

**New Display for Grouped Bookings:**
```html
<div class="match-info grouped">
    <span class="match-time">19:00</span>
    <span class="material-symbols-outlined group-icon">groups</span>
    <span class="lead-booking-id">#12345</span>
    <span class="match-people">4 pax</span>
</div>
```

**Display Logic:**
```php
if ($match['is_group_member'] ?? false) {
    // Show grouped format with icon and lead booking ID
    echo '<span class="material-symbols-outlined group-icon">groups</span>';
    echo '<span class="lead-booking-id">#' . esc_html($match['lead_resos_booking_id']) . '</span>';
} else {
    // Show regular format
    echo '<span class="match-people">' . esc_html($people) . ' pax</span>';
}
```

#### B. Add Group Management UI Elements

**Group Management Button:**
```html
<button class="bma-button group-manage-btn"
        data-resos-id="<?php echo esc_attr($resos_id); ?>"
        data-date="<?php echo esc_attr($date); ?>">
    <span class="material-symbols-outlined">group_add</span>
    Manage Group
</button>
```

**Group Member Display:**
```html
<div class="group-members">
    <strong>Grouped with:</strong>
    <span class="group-member-id">#12346</span>
    <span class="group-member-id">#12347</span>
</div>
```

### 4. Frontend Chrome Extension Changes

#### A. Add Group Management Modal (sidepanel.js)

**Modal Structure:**
```html
<div id="group-management-modal" class="modal">
    <div class="modal-content">
        <h3>Manage Group Bookings</h3>
        <p>Select other bookings on <strong id="group-date"></strong> to link to this reservation:</p>

        <div id="available-bookings">
            <!-- Dynamically populated checkboxes -->
            <label>
                <input type="checkbox" value="12346" data-guest="John Smith" data-room="101">
                #12346 - John Smith - Room 101
            </label>
            <!-- More checkboxes... -->
        </div>

        <div class="modal-buttons">
            <button id="save-group-btn" class="primary">Save Group</button>
            <button id="cancel-group-btn">Cancel</button>
        </div>
    </div>
</div>
```

**JavaScript Functions:**
```javascript
// Open group management modal
function openGroupManagementModal(resosId, date, currentGroups)

// Fetch all bookings for date
async function fetchBookingsForDate(date)

// Save group configuration
async function saveGroupConfiguration(resosId, selectedBookingIds)

// API call to update group
async function updateGroupBookings(resosId, bookingIds)
```

#### B. Update Exclude Button Behavior

```javascript
// Before: Called POST /bookings/exclude which added note
// After: Calls POST /bookings/exclude which updates GROUP/EXCLUDE field

async function excludeMatch(resosBookingId, hotelBookingId) {
    const response = await apiClient.excludeMatch(resosBookingId, hotelBookingId);
    // Refresh display to show exclusion
    refreshCurrentTab();
}
```

#### C. Display Group Icon

**CSS Addition (sidepanel.css):**
```css
.match-info.grouped {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.group-icon {
    color: #3b82f6;
    font-size: 1.25rem;
}

.lead-booking-id {
    font-weight: 600;
    color: #1f2937;
}

.group-members {
    margin-top: 0.5rem;
    padding: 0.5rem;
    background: #f3f4f6;
    border-radius: 0.25rem;
    font-size: 0.875rem;
}

.group-member-id {
    display: inline-block;
    margin-right: 0.5rem;
    padding: 0.125rem 0.375rem;
    background: #e5e7eb;
    border-radius: 0.25rem;
    font-family: monospace;
}
```

## API Endpoint Specifications

### 1. Update Exclude Endpoint

**Endpoint:** `POST /wp-json/bma/v1/bookings/exclude`

**Current Behavior:** Adds `NOT-#{hotel_booking_id}` note

**New Behavior:** Updates `GROUP/EXCLUDE` custom field

**Parameters:**
- `resos_booking_id` (required): Resos booking ID
- `hotel_booking_id` (required): NewBook booking ID to exclude

**Response:**
```json
{
    "success": true,
    "message": "Booking excluded successfully",
    "resos_booking_id": "abc123",
    "excluded_booking_id": "12345",
    "updated_field": "N-12345"
}
```

### 2. New Group Management Endpoint

**Endpoint:** `POST /wp-json/bma/v1/bookings/group`

**Purpose:** Update group relationships for a Resos booking

**Parameters:**
- `resos_booking_id` (required): Resos booking ID
- `group_booking_ids` (required): Array of NewBook booking IDs to group

**Request Body:**
```json
{
    "resos_booking_id": "abc123",
    "group_booking_ids": ["12346", "12347"]
}
```

**Response:**
```json
{
    "success": true,
    "message": "Group updated successfully",
    "resos_booking_id": "abc123",
    "group_members": ["12346", "12347"],
    "updated_field": "G-12346,G-12347"
}
```

### 3. New Bookings for Date Endpoint

**Endpoint:** `GET /wp-json/bma/v1/bookings/for-date`

**Purpose:** Get all NewBook bookings for a specific date (for group management UI)

**Parameters:**
- `date` (required): Date in YYYY-MM-DD format
- `exclude_booking_id` (optional): Booking ID to exclude from results

**Response:**
```json
{
    "success": true,
    "date": "2026-01-15",
    "bookings": [
        {
            "booking_id": "12346",
            "guest_name": "John Smith",
            "room": "101",
            "arrival": "2026-01-15",
            "departure": "2026-01-18"
        }
    ]
}
```

## Implementation Order

### Phase 1: Backend Foundation
1. ✅ Review current implementation
2. Add helper functions to BMA_Booking_Actions
   - `parse_group_exclude_field()`
   - `build_group_exclude_value()`
   - `add_to_group_exclude()`
   - `remove_from_group_exclude()`
3. Update custom field map to include 'GROUP/EXCLUDE'
4. Modify `exclude_resos_match()` to use field instead of notes

### Phase 2: Matching Logic
1. Add helper methods to BMA_Matcher
   - `get_group_exclude_field()`
   - `is_excluded()`
   - `is_in_group()`
2. Update exclusion check in `match_resos_to_hotel()`
3. Add group member detection logic
4. Return group information in match results

### Phase 3: API Endpoints
1. Update `/bookings/exclude` endpoint
2. Add `/bookings/group` endpoint (new)
3. Add `/bookings/for-date` endpoint (new)
4. Test all endpoints with cURL

### Phase 4: Display Updates
1. Update chrome-sidepanel-response.php template
   - Add grouped booking display format
   - Add group management button
   - Display group members
2. Update chrome-summary-response.php template
   - Show group indicator in summary cards

### Phase 5: Frontend UI
1. Update sidepanel.css with group styling
2. Add group management modal to sidepanel.html
3. Implement JavaScript functions in sidepanel.js
   - Modal open/close
   - Fetch bookings for date
   - Save group configuration
   - Update exclude button behavior
4. Add event listeners for group management buttons

### Phase 6: Testing & Refinement
1. Test exclude feature with field updates
2. Test group creation and display
3. Test edge cases (empty values, multiple groups)
4. Test backward compatibility (existing notes)
5. Documentation updates

## Migration Strategy

### Handling Existing Exclusions

**Backward Compatibility:**
- Keep existing note-based exclusion check as fallback
- System checks both notes AND custom field
- New exclusions use field only

**Code Example:**
```php
// Check new field first
$group_exclude = $this->get_group_exclude_field($resos_booking);
if ($this->is_excluded($hotel_booking_id, $group_exclude)) {
    return array('matched' => false, 'excluded' => true);
}

// Fallback to old notes (for backward compatibility)
$notes = $this->get_resos_notes($resos_booking);
if (stripos($notes, 'NOT-#' . $hotel_booking_id) !== false) {
    return array('matched' => false, 'excluded' => true);
}
```

## Testing Checklist

### Unit Tests
- [ ] Parse empty GROUP/EXCLUDE field
- [ ] Parse field with only groups
- [ ] Parse field with only excludes
- [ ] Parse field with both
- [ ] Build field value from arrays
- [ ] Add/remove individual entries

### Integration Tests
- [ ] Exclude booking via API updates field
- [ ] Excluded booking doesn't match
- [ ] Group bookings link correctly
- [ ] Group member shows in display
- [ ] Group management modal populates
- [ ] Save group updates field
- [ ] Multiple groups on same date

### Edge Cases
- [ ] Empty field value
- [ ] Malformed field value
- [ ] Non-existent booking IDs
- [ ] Duplicate entries
- [ ] Very long field value (50+ bookings)

## Performance Considerations

1. **Field Parsing:** Cache parsed values during request lifecycle
2. **API Calls:** Batch field updates when possible
3. **UI Loading:** Lazy-load booking list for date (only when modal opens)
4. **Caching:** Clear Resos booking cache after field updates

## Security Considerations

1. **Input Validation:** Sanitize booking IDs before adding to field
2. **Authorization:** Verify user has permission to modify Resos bookings
3. **Injection Prevention:** Escape all output in templates
4. **Rate Limiting:** Consider rate limits on group management endpoint

## Future Enhancements

1. **Bulk Operations:** Manage multiple groups at once
2. **Group Naming:** Add optional group name/label
3. **Group Notes:** Separate notes for groups
4. **Group Analytics:** Track group booking patterns
5. **Import/Export:** Bulk import group configurations

---

## Summary

This implementation replaces the note-based exclusion system with a structured custom field approach that serves dual purposes:
1. **Exclusions:** Clean, editable exclusion list
2. **Groups:** Link multiple room bookings to single restaurant reservation

The design maintains backward compatibility while providing a more robust and maintainable solution for managing booking relationships.
