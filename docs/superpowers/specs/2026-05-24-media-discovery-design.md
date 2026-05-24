# Media Discovery Feature — Design Spec

**Date:** 2026-05-24  
**Status:** Approved  
**Module key:** `monitoring.discovery`  
**Approach:** B — Dedicated MediaDiscoveryService + reuse UploadService for file storage

---

## 1. Purpose

A dedicated page for MONITORING_TEAM to report newly discovered advertising media (poles, boards, hoardings) spotted in the field. Auto-creates placeholder sites, extracts GPS from photos and browser geolocation, and feeds discoveries to Media Planning for review, detailing, and activation into the campaign pipeline.

**Separate from `monitoring.upload`** — discovery has different intent (reporting opportunities vs routine proof), different inputs (no site selection needed), and different downstream handling (site creation + free media record).

---

## 2. Architecture

```
MediaDiscoveryController (request handling only)
        |
        v
MediaDiscoveryService (orchestration + transaction control)
        |
        |-- SiteRepository::create()               --> placeholder site (is_active=0)
        |-- UploadService::createUpload()          --> file storage (reused, proven)
        |-- FreeMediaRepository methods            --> free_media_record
        |-- AuditService::logAction()              --> audit trail
        
Single transaction wraps all steps in MediaDiscoveryService.
```

Follows existing pattern: Controller -> Service -> Repository -> Database.

---

## 3. New Files

| File | Purpose |
|------|---------|
| `app/controllers/MediaDiscoveryController.php` | Endpoints: submit discovery, list own discoveries |
| `app/services/MediaDiscoveryService.php` | Orchestration: GPS check, site creation, upload, free_media_record |
| `migrations/004_media_discovery_module.sql` | Add `monitoring.discovery` to role_module_scopes for MONITORING_TEAM |

## 4. Modified Files

| File | Change |
|------|--------|
| `config/route_registry.php` | Add `discovery/submit` and `discovery/my-list` routes |
| `config/constants.php` | Add `DISCOVERY_PENDING_ALERT_DAYS`, `DISCOVERY_GPS_DEDUP_RADIUS_METERS`; remove `FREE_MEDIA_DEFAULT_SITE_ID` |
| `public/js/views/modules.js` | Add `monitoring.discovery` page; remove discovery toggle from `monitoring.upload` |
| `public/index.html` | Cache bump on modules.js |

---

## 5. Routes

```php
// MEDIA DISCOVERY (MONITORING_TEAM)
'discovery/submit' => [
    'controller' => 'MediaDiscoveryController',
    'method'     => 'submit',
    'module_key' => 'monitoring.discovery',
    'capability' => 'manage',
],
'discovery/my-list' => [
    'controller' => 'MediaDiscoveryController',
    'method'     => 'myList',
    'module_key' => 'monitoring.discovery',
    'capability' => 'read',
],
```

---

## 6. Data Flow

### 6.1 Submission (Monitoring Person in Field)

```
INPUT:
  - photos: 1-10 image files (required)
  - comment_text: location/description note (optional, encouraged if no GPS)
  - browser_lat / browser_lng: from navigator.geolocation (optional, captured client-side)

PROCESS (single transaction in MediaDiscoveryService):

  Step 1: Determine GPS coordinates
    - Extract EXIF GPS from first photo (server-side, exif_read_data)
    - Receive browser geolocation from request params
    - Priority logic:
      * If browser GPS available -> use as site coordinates (person is AT the spot)
      * Else if EXIF GPS available -> use EXIF
      * Else -> no GPS (site created without coordinates)

  Step 2: GPS proximity dedup check
    - If GPS available: query for existing DISC-* sites within DISCOVERY_GPS_DEDUP_RADIUS_METERS (50m)
      that also have a free_media_record with status = 'DISCOVERED' (pending review only)
      * Match found -> reuse that site_id (add photos to existing pending discovery)
      * No match (or match has status != DISCOVERED) -> create new site
    - If no GPS: always create new site (no dedup possible)

  Step 3: Create placeholder site (if not reusing)
    - site_code = 'DISC-YYYYMMDD-NNN' (date + daily sequence number)
    - location_text = "{lat}N, {lng}E" (if GPS) or "Discovery - pending details"
    - site_category = 'CITY' (default)
    - lighting_type = 'NON_LIT' (default)
    - latitude = from Step 1
    - longitude = from Step 1
    - is_active = 0  <-- DRAFT, invisible to all regular dropdowns

  Step 4: Create upload(s) via UploadService
    - parent_type = 'SITE'
    - parent_id = site_id (new or reused)
    - upload_type = 'WORK'
    - is_discovery_mode = 1
    - comment_text = user's location note
    - gps_latitude / gps_longitude = EXIF values (if present in photo)
    - authority_visibility = 'NOT_ELIGIBLE'
    - photo_label = 'GENERAL'

  Step 5: Create/refresh free_media_record
    - If reusing existing site (dedup match):
      * Keep original discovered_date (don't overwrite — preserve first sighting date)
      * Optionally update source_reference_id to latest upload (for thumbnail freshness)
    - If new site:
      * site_id = new site ID
      * source_type = 'MONITORING_DISCOVERY'
      * source_reference_id = first upload's ID
      * status = 'DISCOVERED'
      * discovered_date = today

  Step 6: Audit log
    - action = 'DISCOVERY_SUBMITTED'
    - entity_type = 'free_media_records'
    - entity_id = record ID

OUTPUT:
  - Success response with discovery details (site_code, photo count, GPS status)
```

### 6.2 Site Code Generation

Format: `DISC-YYYYMMDD-NNN` (e.g., `DISC-20260524-001`)

```sql
SELECT COUNT(*) + 1 AS next_seq
FROM sites
WHERE site_code LIKE CONCAT('DISC-', :today_str, '-%')
```

Race condition: UNIQUE constraint on site_code. On duplicate key error, retry with incremented sequence (max 3 retries).

### 6.3 GPS Proximity Dedup Query

```sql
SELECT s.id, s.site_code, s.latitude, s.longitude,
       (6371000 * acos(
           cos(radians(:lat)) * cos(radians(s.latitude)) *
           cos(radians(s.longitude) - radians(:lng)) +
           sin(radians(:lat)) * sin(radians(s.latitude))
       )) AS distance_meters
FROM sites s
INNER JOIN free_media_records fm ON fm.site_id = s.id AND fm.status = 'DISCOVERED'
WHERE s.site_code LIKE 'DISC-%'
  AND s.is_active = 0
  AND s.latitude IS NOT NULL
  AND s.longitude IS NOT NULL
HAVING distance_meters <= :radius
ORDER BY distance_meters ASC
LIMIT 1
```

Uses Haversine formula. Only matches DISC-* sites with a pending (DISCOVERED) free_media_record — if the site was already confirmed/expired/consumed, a new discovery is created (situation may have changed).

---

## 7. Frontend — Discovery Page

### 7.1 Page Layout

```
+--------------------------------------------------+
| Media Discovery                                    |
| Report new advertising media you've spotted        |
+--------------------------------------------------+
|                                                    |
| [PHOTO SECTION]                                    |
|  Camera picker: "Take Photo" / "Choose from Gallery"|
|  Photo preview grid (1-10 thumbnails, removable)   |
|                                                    |
| [GPS STATUS]                                       |
|  Green: "GPS captured" (with coordinates)          |
|  Amber: "No GPS in photo - please describe below"  |
|                                                    |
| [LOCATION NOTE]                                    |
|  Textarea: "Describe the location (landmark, road)"|
|  Visually emphasized when no GPS detected          |
|                                                    |
| [SUBMIT BUTTON]                                    |
|  "Submit Discovery"                                |
|  On click: request browser geolocation, then submit|
|                                                    |
+--------------------------------------------------+
| My Recent Discoveries                              |
|  Horizontal scroll strip of own discovery cards    |
|  Each card: thumbnail + date + status indicator    |
|  Clickable for photo preview modal                 |
+--------------------------------------------------+
```

### 7.2 Client-Side GPS Flow

```javascript
// On submit click:
1. Read EXIF GPS from photos (client-side using FileReader + DataView)
2. Request browser geolocation: navigator.geolocation.getCurrentPosition()
3. Include browser_lat, browser_lng in FormData
4. Show loading state during geolocation request
5. If geolocation denied/unavailable: submit without browser GPS (EXIF or none)
6. If geolocation times out (5s): submit without it
```

### 7.3 GPS Warning Logic (client-side)

```
After photo selection:
  - Read EXIF from selected file(s)
  - If first photo has GPS EXIF: show green "GPS detected" badge
  - If no GPS in EXIF: show amber warning + emphasize location note field
```

### 7.4 Recent Discoveries Strip

- Endpoint: `discovery/my-list` (paginated, newest first)
- Shows: thumbnail, date, site_code
- Click: opens photo gallery modal (existing `openPhotoGallery()`)
- Limit: last 20 discoveries

---

## 8. Distinguishing Discovery Sites From Other Inactive Sites

| Site Type | site_code pattern | is_active | How identified |
|-----------|------------------|-----------|----------------|
| Normal active site | Regular codes (e.g., `CITY-MUM-047`) | 1 | Standard operations |
| Deactivated site | Regular codes | 0 | Was active, now closed |
| Discovery placeholder | `DISC-YYYYMMDD-NNN` | 0 | Pending Media Planner review |
| Merged (absorbed) | `MERGED-YYYYMMDD-NNN` | 0 | Was duplicate, absorbed into another |

**Query patterns:**
- Discovery drafts: `WHERE is_active = 0 AND site_code LIKE 'DISC-%'`
- Merged/discarded: `WHERE is_active = 0 AND site_code LIKE 'MERGED-%'`
- Genuinely deactivated: `WHERE is_active = 0 AND site_code NOT LIKE 'DISC-%' AND site_code NOT LIKE 'MERGED-%'`

---

## 9. Media Planner / Ops Actions

### 9.1 View Discoveries (in Free Media Inventory)

Filter `free_media_records` by `status = 'DISCOVERED'` joined with `sites` for display. Each item shows:
- Photo thumbnails (from uploads linked to that site)
- GPS location (latitude/longitude if available)
- Comment/location note (from upload's comment_text)
- Discoverer name + date
- Age indicator: "2 days ago" or warning "12 days ago - needs review"

### 9.2 Fill Details and Confirm

Media Planner edits the site record:
- site_code: from `DISC-*` to real code (e.g., `CITY-MUM-052`)
- location_text: real description
- site_category: correct ENUM value
- lighting_type: correct value
- board_type, ownership_name, route_or_group: fill in

On confirm:
- `sites.is_active = 1` (site now live everywhere)
- `free_media_records.status = 'CONFIRMED_ACTIVE'`
- `free_media_records.confirmed_by_user_id = actor`
- `free_media_records.confirmed_date = today`
- Audit log: `FREE_MEDIA_CONFIRMED`

### 9.3 Merge Two Discoveries

When two discoveries are the same physical location:

1. User selects PRIMARY (keep) and SECONDARY (discard)
2. System (single transaction):
   - Move all uploads: `UPDATE uploads SET parent_id = {keep_id} WHERE parent_id = {discard_id}`
   - Expire discard's free_media_record: `status = 'EXPIRED'`
   - Rename discard site_code: `DISC-*` -> `MERGED-*`
   - Update keep's free_media_record: refresh source_reference_id if needed
   - Audit log: `DISCOVERY_MERGED`

### 9.4 Dismiss (Delete) Discovery

When a discovery is junk/irrelevant:

1. User clicks "Dismiss" with confirmation dialog
2. System (single transaction):
   - `free_media_records.status = 'EXPIRED'`
   - `uploads.is_deleted = 1, deleted_at = NOW()` for all uploads of that site
   - Site stays `is_active = 0` (already invisible)
   - Audit log: `DISCOVERY_DISMISSED`
3. After `SELF_DELETED_UPLOAD_PURGE_DAYS` (30 days): physical files eligible for purge by Ops cleanup page

Confirmation message: "This will dismiss the discovery and mark N photos for cleanup. Files will be purged after 30 days."

---

## 10. Notifications for Media Planner

All computed at runtime (no background jobs, governance-compliant):

| Mechanism | Where | Logic |
|-----------|-------|-------|
| Dashboard counter | Media Planning landing | `COUNT(*) FROM free_media_records WHERE status='DISCOVERED'` |
| Stale alert | Dashboard | Discoveries where `DATEDIFF(NOW(), discovered_date) > DISCOVERY_PENDING_ALERT_DAYS` |
| Age indicator | Free Media list | Each item shows days since discovery, amber warning if stale |

---

## 11. Constants

```php
// Days before a pending discovery triggers a stale alert on dashboard
define('DISCOVERY_PENDING_ALERT_DAYS', 7);

// GPS radius in meters for proximity dedup check before creating new site
define('DISCOVERY_GPS_DEDUP_RADIUS_METERS', 50);
```

**Removed:** `FREE_MEDIA_DEFAULT_SITE_ID = 38` (no longer needed — each discovery gets its own site).

**Removed from DB:** The `FREE-MEDIA-DEFAULT` site row (ID 38) can remain but is no longer used.

---

## 12. Access Control

| Role | Module | Capability | Actions |
|------|--------|------------|---------|
| MONITORING_TEAM | `monitoring.discovery` | manage | Submit discoveries, view own list |
| OPS_MANAGER | `media.free_media_inventory` | manage | Confirm, merge, dismiss discoveries |
| MEDIA_PLANNING | `commercial.media_planning_inventory` | manage | View, confirm, merge, dismiss discoveries |

Migration adds `monitoring.discovery` to `role_module_scopes` for MONITORING_TEAM.

---

## 13. Changes to Existing monitoring.upload Page

**Remove from monitoring.upload:**
- Discovery toggle chips (Regular Visit / Free Media Discovery)
- The `js-mon-fmd-auto-site` hidden section
- `FREE_MEDIA_DEFAULT_SITE_ID` usage in `getParentId()`
- Discovery note text and related handler code

**monitoring.upload becomes purely:** routine monitoring proof upload with required site selection.

---

## 14. Security and Validation

- File validation: same rules as existing uploads (ALLOWED_UPLOAD_EXTENSIONS, ALLOWED_UPLOAD_MIME_TYPES, MAX_UPLOAD_SIZE_MB)
- Max files per submission: MAX_UPLOAD_FILES_PER_SUBMISSION (10)
- Role check: only MONITORING_TEAM can submit discoveries
- GPS coordinates validated as decimal range (-90 to 90 lat, -180 to 180 lng)
- comment_text: sanitized, max 500 characters
- Rate limit: not enforced in v1 (shared hosting constraint), but site_code sequence provides natural visibility into volume

---

## 15. Purge Lifecycle

```
Day 0:  Discovery dismissed (is_deleted=1 on uploads)
        -> Photos still on disk (safety net)
        -> Invisible to all views

Day 30: Ops purges from cleanup page (is_purged=1)
        -> Physical files deleted from disk
        -> file_path nulled on upload record
        -> Metadata row retained for audit trail
```

Same mechanism as existing self-deleted upload purge. No new purge logic needed.

---

## 16. Dual GPS Strategy

| Source | Capture method | When used | Reliability |
|--------|---------------|-----------|-------------|
| Browser Geolocation | `navigator.geolocation.getCurrentPosition()` on submit | Person is physically at the spot (camera capture) | High |
| Photo EXIF GPS | `exif_read_data()` server-side | Gallery uploads, backup for camera | Medium (depends on settings) |

**Priority logic:**
1. If browser geolocation available -> use for site coordinates (most accurate for "right now")
2. Else if EXIF GPS available -> use EXIF
3. Else -> no GPS, site created without coordinates, warning shown

**Both stored:**
- Site record: `latitude`/`longitude` = best available (browser > EXIF)
- Upload record: `gps_latitude`/`gps_longitude` = EXIF values (if present)

**Client-side behavior:**
- Geolocation requested on submit button click (not on page load)
- 5-second timeout — if too slow, submit without browser GPS
- Permission denied — submit without browser GPS, rely on EXIF
- One-time browser permission prompt (persists for domain)
