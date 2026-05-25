# Monitoring Upload Overhaul — Design Spec

**Date:** 2026-05-25  
**Status:** Approved  
**Modules affected:** `monitoring.upload`, `monitoring.history`, site detail pages  
**Approach:** Enrich site data (schema additions) + redesign upload page UX + creative management

---

## 1. Purpose

Overhaul the monitoring upload page for MONITORING_TEAM to make site selection intuitive, field-efficient, and GPS-aware. Replace cryptic site codes with rich card-based site selection showing client names, board dimensions, creative thumbnails, and proximity. Add planned vs unplanned monitoring tabs, shift tracking, condition reporting, and issue resolution — all from one page.

**Also includes:** creative artwork upload on site detail page, board dimension fields, monitoring history enrichment, and missed-site tracking on monitoring plan page.

---

## 2. Schema Changes

### 2.1 ALTER `sites` — 5 new columns + 1 index

```sql
ALTER TABLE sites
  ADD COLUMN board_width_ft SMALLINT UNSIGNED NULL AFTER board_type,
  ADD COLUMN board_height_ft SMALLINT UNSIGNED NULL AFTER board_width_ft,
  ADD COLUMN creative_upload_id BIGINT UNSIGNED NULL AFTER longitude,
  ADD COLUMN last_monitored_at DATETIME NULL AFTER creative_upload_id,
  ADD COLUMN last_monitored_by_user_id BIGINT UNSIGNED NULL AFTER last_monitored_at,
  ADD CONSTRAINT fk_sites_creative_upload_id
    FOREIGN KEY (creative_upload_id) REFERENCES uploads(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_sites_last_monitored_by
    FOREIGN KEY (last_monitored_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  ADD INDEX idx_sites_lat_lng (latitude, longitude);
```

| Column | Type | Purpose |
|---|---|---|
| `board_width_ft` | SMALLINT UNSIGNED NULL | Board width in feet |
| `board_height_ft` | SMALLINT UNSIGNED NULL | Board height in feet |
| `creative_upload_id` | BIGINT UNSIGNED NULL (FK → uploads.id) | Current creative artwork image. ON DELETE SET NULL. |
| `last_monitored_at` | DATETIME NULL | Timestamp of last monitoring upload. Updated automatically on upload submission. |
| `last_monitored_by_user_id` | BIGINT UNSIGNED NULL (FK → users.id) | Who performed the last monitoring upload. |
| `idx_sites_lat_lng` | INDEX | Spatial index for GPS proximity sorting. |

**Validation rule (service layer):** Active sites (`is_active = 1`) must have `creative_upload_id IS NOT NULL`. Enforced when activating a site or editing an active site.

### 2.2 ALTER `site_monitoring_due_dates` — 1 new column

```sql
ALTER TABLE site_monitoring_due_dates
  ADD COLUMN completed_at DATETIME NULL AFTER due_date;
```

| Column | Type | Purpose |
|---|---|---|
| `completed_at` | DATETIME NULL | When monitoring was completed for this due date. NULL = not yet done. Updated when a monitoring upload is submitted for the site on this due_date. |

### 2.3 ALTER `uploads` — 1 new column

```sql
ALTER TABLE uploads
  ADD COLUMN site_condition ENUM('GOOD','DAMAGED','FADED','CREATIVE_MISSING','LIGHTS_OFF') NULL AFTER photo_label;
```

| Column | Type | Purpose |
|---|---|---|
| `site_condition` | ENUM NULL | Condition of the site observed during this upload. NULL for non-monitoring uploads. |

### 2.4 CREATE TABLE `monitoring_shifts`

```sql
CREATE TABLE monitoring_shifts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    shift_date DATE NOT NULL,
    started_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    planned_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    completed_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    unplanned_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_shift_date (user_id, shift_date),
    CONSTRAINT fk_shifts_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

| Column | Type | Purpose |
|---|---|---|
| `user_id` | FK → users.id | Monitoring person |
| `shift_date` | DATE | The calendar date of the shift |
| `started_at` | DATETIME | When "Start Monitoring" was tapped |
| `completed_at` | DATETIME NULL | When "Complete Day" was tapped. NULL = still in field. |
| `planned_count` | SMALLINT | Number of planned sites for the day (snapshot at shift start) |
| `completed_count` | SMALLINT | Number of sites uploaded to during this shift |
| `unplanned_count` | SMALLINT | Number of unplanned sites uploaded to |

---

## 3. Architecture

```
MonitoringUploadController (existing, extended)
    |
    |-- targets()              --> enriched plan sites with client, size, creative, last_monitored
    |-- siteSearch()           --> search by client/location/code
    |-- browseRoutes()         --> distinct routes for a category with counts
    |-- browseSites()          --> sites by category+route with upload-today status
    |-- startShift()           --> create monitoring_shifts record
    |-- completeShift()        --> update completed_at + counts
    |-- resolveIssue()         --> mark issue resolved with mandatory photo
    |
MonitoringUploadService (new — orchestration for shift + enriched queries)
    |
    |-- SiteRepository         --> enriched site queries with campaign joins
    |-- MonitoringPlanRepository --> due dates + completion tracking
    |-- MonitoringShiftRepository (new) --> shift CRUD
    |-- UploadService          --> reused for creative upload + monitoring uploads
    |-- IssueRepository        --> open issue lookup + resolution
    |-- AuditService           --> audit trail
```

Follows existing pattern: Controller → Service → Repository → Database.

---

## 4. New Files

| File | Purpose |
|---|---|
| `app/services/MonitoringUploadService.php` | Orchestration: enriched site queries, shift management, condition processing, issue resolution |
| `app/repositories/MonitoringShiftRepository.php` | CRUD for monitoring_shifts table |
| `migrations/006_monitoring_upload_overhaul.sql` | Schema changes: ALTER sites, ALTER site_monitoring_due_dates, ALTER uploads, CREATE monitoring_shifts |

## 5. Modified Files

| File | Change |
|---|---|
| `app/controllers/MonitoringUploadController.php` | Add browseRoutes, browseSites, startShift, completeShift, resolveIssue endpoints; enrich targets + siteSearch responses |
| `app/repositories/SiteRepository.php` | Add enriched query methods with campaign/creative joins; update create/update for new columns |
| `app/repositories/MonitoringPlanRepository.php` | Add markCompleted(), getMissedSites() methods |
| `app/services/UploadService.php` | After monitoring upload: update sites.last_monitored_at, site_monitoring_due_dates.completed_at |
| `config/route_registry.php` | Add new routes for browse, shift, resolve |
| `config/constants.php` | Add NEARBY_NUDGE_RADIUS_METERS, NEARBY_NUDGE_STALE_DAYS |
| `public/js/views/modules.js` | Redesign monitoring.upload (tabs, cards, GPS, shifts, conditions); enrich monitoring.history cards |
| `public/js/core/api.js` | No changes needed — existing Api.get/Api.upload sufficient |
| `public/index.html` | Cache bump |
| `docs/06_schema/schema_v1_full.sql` | Add new columns, table, index |
| `docs/06_schema/11_SCHEMA_BASELINE_v1_FINAL_WITH_DDL.md` | Mirror DDL changes |
| `docs/06_schema/12_SCHEMA_SPECIFICATION_v1.md` | Document new fields, ENUMs, constraints |
| `docs/01_structure/05_DATA_AND_FLOW_NOTES_FINAL.md` | Monitoring upload flow changes |
| `docs/AGENT_START.md` | Current focus update |
| `docs/PRODUCT_BACKLOG.md` | Feature status |
| `docs/PRODUCT_LOG.md` | Decision log |
| `docs/AI_TOOL_HANDOFF_GUIDE.md` | New pitfalls |

---

## 6. Routes

```php
// MONITORING UPLOAD — ENRICHED BROWSING
'monitoring/browse-routes' => [
    'controller' => 'MonitoringUploadController',
    'method'     => 'browseRoutes',
    'module_key' => 'monitoring.upload',
    'capability' => 'read',
],
'monitoring/browse-sites' => [
    'controller' => 'MonitoringUploadController',
    'method'     => 'browseSites',
    'module_key' => 'monitoring.upload',
    'capability' => 'read',
],

// MONITORING SHIFT TRACKING
'monitoring/start-shift' => [
    'controller' => 'MonitoringUploadController',
    'method'     => 'startShift',
    'module_key' => 'monitoring.upload',
    'capability' => 'upload',
],
'monitoring/complete-shift' => [
    'controller' => 'MonitoringUploadController',
    'method'     => 'completeShift',
    'module_key' => 'monitoring.upload',
    'capability' => 'upload',
],

// QUICK ISSUE RESOLUTION FROM FIELD
'monitoring/resolve-issue' => [
    'controller' => 'MonitoringUploadController',
    'method'     => 'resolveIssue',
    'module_key' => 'monitoring.upload',
    'capability' => 'upload',
],
```

Existing routes (`upload/targets`, `monitoring/site-search`, `monitoring/upload`) are modified in-place, not replaced.

---

## 7. Data Flow

### 7.1 Enriched Site Card Data

Every site card (planned, unplanned, search) returns the same enriched shape:

```json
{
  "id": 42,
  "site_code": "CITY-MUM-047",
  "location_text": "Andheri East Junction",
  "site_category": "CITY",
  "route_or_group": "Greater Noida",
  "board_width_ft": 20,
  "board_height_ft": 30,
  "latitude": 28.4744,
  "longitude": 77.5040,
  "client_name": "Samsung",
  "creative_url": "../index.php?route=upload/serve&id=185",
  "last_monitored_at": "2026-05-22 14:30:00",
  "last_monitored_by": "Ramesh Kumar",
  "uploaded_today": false,
  "uploaded_today_at": null,
  "open_issue_count": 1
}
```

**Client name query logic:**
```sql
SELECT s.*, c.client_name
FROM sites s
LEFT JOIN campaign_sites cs
  ON cs.site_id = s.id
  AND cs.linked_to_date IS NULL
LEFT JOIN campaigns c
  ON c.id = cs.campaign_id
  AND c.status = 'ACTIVE'
WHERE s.is_active = 1
ORDER BY cs.linked_from_date DESC
```
If multiple active campaigns: use the most recently linked (`MAX(linked_from_date)`).
If no active campaign: `client_name = NULL`.

**Open issue count:**
```sql
LEFT JOIN (
    SELECT site_id, COUNT(*) as open_issue_count
    FROM issues
    WHERE status IN ('OPEN', 'IN_PROGRESS')
    GROUP BY site_id
) iss ON iss.site_id = s.id
```

**Uploaded today check (for done indicator):**
```sql
LEFT JOIN (
    SELECT parent_id, MAX(created_at) as uploaded_today_at
    FROM uploads
    WHERE parent_type = 'SITE'
      AND created_by_user_id = :current_user_id
      AND DATE(created_at) = CURDATE()
      AND is_deleted = 0
    GROUP BY parent_id
) ut ON ut.parent_id = s.id
```

### 7.2 Planned Tab — `upload/targets` (modified)

```
INPUT:  (none — uses session user + today's date)

PROCESS:
  1. Get today's due sites from site_monitoring_due_dates
  2. Enrich each site with: client_name, board size, creative_url, 
     last_monitored_at, open_issue_count, uploaded_today status
  3. Return enriched site list

OUTPUT: { items: [enriched site cards], planned_count: N, completed_count: N }
```

### 7.3 Unplanned Tab — Browse Routes

```
INPUT:  { category: 'HIGHWAY' }

PROCESS:
  SELECT route_or_group, COUNT(*) as site_count
  FROM sites
  WHERE is_active = 1
    AND site_category = :category
    AND route_or_group IS NOT NULL
  GROUP BY route_or_group
  ORDER BY route_or_group ASC

OUTPUT: { routes: [{ route_or_group: 'NH-24', site_count: 8 }, ...] }
```

### 7.4 Unplanned Tab — Browse Sites

```
INPUT:  { category: 'HIGHWAY', route: 'NH-24' }

PROCESS:
  1. Query active sites matching category + route_or_group
  2. Enrich with same fields as planned tab
  3. Return enriched list

OUTPUT: { items: [enriched site cards], total: N }
```

### 7.5 Site Search (modified `monitoring/site-search`)

```
INPUT:  { q: 'samsung' }  (search term — client name, location, or site code)

PROCESS:
  SELECT enriched site card data
  FROM sites s
  LEFT JOIN campaign_sites cs ...
  LEFT JOIN campaigns c ...
  WHERE s.is_active = 1
    AND (s.site_code LIKE :q%
         OR s.location_text LIKE %:q%
         OR c.client_name LIKE %:q%)
  GROUP BY s.id
  LIMIT 20

OUTPUT: { items: [enriched site cards] }
```

### 7.6 Shift Lifecycle

```
START MONITORING:
  INPUT:  (none — uses session user + today)
  PROCESS:
    1. Check no existing shift for user+today (or return existing)
    2. Count today's planned sites for this user
    3. INSERT monitoring_shifts (user_id, shift_date, started_at, planned_count)
    4. Audit log: MONITORING_SHIFT_STARTED
  OUTPUT: { shift_id, started_at, planned_count }

COMPLETE DAY:
  INPUT:  (none — uses session user + today)
  PROCESS:
    1. Find today's shift record
    2. Count completed + unplanned uploads for today
    3. UPDATE monitoring_shifts SET completed_at=NOW(), completed_count=N, unplanned_count=N
    4. Audit log: MONITORING_SHIFT_COMPLETED
  OUTPUT: { completed_at, planned_count, completed_count, unplanned_count }
```

### 7.7 Quick Issue Report (merged condition tags + issue creation)

```
INPUT:
  - site_id: required
  - site_condition: ENUM value (DAMAGED, FADED, CREATIVE_MISSING, LIGHTS_OFF)
  - photos: 1+ image files (required)
  - comment_text: description (optional)

PROCESS:
  1. Create issue for the site:
     - title: auto-generated from condition (e.g., "Site Condition: DAMAGED")
     - status: OPEN
     - linked to site_id
  2. Upload photos as issue uploads (parent_type=ISSUE, upload_type=ISSUE)
  3. Audit log: MONITORING_ISSUE_REPORTED

OUTPUT: { issue_id, message: 'Issue reported' }
```

Note: When `site_condition = 'GOOD'` is selected during a regular upload, no issue is created — the condition is just recorded on the upload record.

### 7.8 Issue Resolution from Field

```
INPUT:
  - issue_id: required
  - photos: 1+ image files (required — proof of resolution)
  - comment_text: resolution note (optional)

PROCESS:
  1. Verify issue exists and is OPEN or IN_PROGRESS
  2. Upload resolution photos (parent_type=ISSUE, upload_type=WORK)
  3. Update issue status → CLOSED
  4. Audit log: ISSUE_RESOLVED_FROM_FIELD

OUTPUT: { issue_id, status: 'CLOSED', message: 'Issue resolved' }
```

### 7.9 Post-Upload Side Effects

When a monitoring upload is successfully submitted (existing `upload/create` flow):

```
SIDE EFFECTS (in UploadService or MonitoringUploadService):
  1. UPDATE sites SET last_monitored_at = NOW(), 
                      last_monitored_by_user_id = :actor_id
     WHERE id = :site_id

  2. UPDATE site_monitoring_due_dates SET completed_at = NOW()
     WHERE site_id = :site_id AND due_date = CURDATE() AND completed_at IS NULL

  3. If shift exists for today:
     UPDATE monitoring_shifts SET completed_count = completed_count + 1
     WHERE user_id = :actor_id AND shift_date = CURDATE()
     (or unplanned_count + 1 if site was not in today's plan)
```

---

## 8. Frontend — Monitoring Upload Page

### 8.1 Page Layout

```
+--------------------------------------------------+
| Monitoring Upload                                 |
| Submit site monitoring proof                      |
+--------------------------------------------------+
|                                                   |
| [SHIFT CONTROLS]                                  |
|  Before shift: [▶ Start Monitoring]               |
|  During shift: "Started at 9:15 AM"               |
|                [■ Complete Day]                    |
|  After complete: Summary card                     |
|                                                   |
| [SUMMARY BANNER]                                  |
|  📋 12 planned · ✅ 3 done · 📍 Nearest: 0.5 km  |
|                                                   |
| [TAB TOGGLE]                                      |
|  ┌──────────────┬───────────────┐                 |
|  │ 📋 Planned   │ 📍 Unplanned  │                 |
|  └──────────────┴───────────────┘                 |
|                                                   |
| [PLANNED TAB]                                     |
|  Progress: "3 of 12 sites done"                   |
|  Site cards (GPS sorted, nearest first):          |
|   ┌──────┬───────────────────────────┐            |
|   │[img] │ Samsung · 20×30 ft        │ [🗺️]      |
|   │      │ Andheri East Jn           │            |
|   │      │ 📍 0.3 km · Last: 3d ago  │            |
|   │      │ ⚠️ 1 open issue           │            |
|   └──────┴───────────────────────────┘            |
|   (tap to select → highlighted)                   |
|                                                   |
|   ── Done ──                                      |
|   ┌──────┬───────────────────────────┐            |
|   │ ✅   │ Nike · 30×40 ft           │ (green)    |
|   │      │ Bandra West · Done 10:34  │            |
|   └──────┴───────────────────────────┘            |
|                                                   |
|  Search: 🔍 client, location, or code             |
|                                                   |
| [UNPLANNED TAB]                                   |
|  Category chips:                                  |
|   [🏙 City] [🛣 Highway] [🌿 Green Belt]          |
|  Route chips (after category tap):                |
|   [NH-24 (8)] [NH-58 (5)] [Delhi Meerut (12)]    |
|  Progress: "1 of 8 sites done"                    |
|  Site cards (same format, GPS sorted)             |
|  Nearby nudge (if applicable):                    |
|   "📍 Unmonitored site 200m away — Nike, Juhu Rd" |
|                                                   |
| [SELECTED SITE ACTIONS]                           |
|  Previous photo: [last photo thumbnail + date]    |
|  Condition: [Good] [Damaged] [Faded] [Missing] ...|
|  🗺️ Navigate  ⚠️ Report Issue                    |
|  (if open issue): ✅ Issue Resolved               |
|                                                   |
| [UPLOAD SECTION]                                  |
|  Comment (optional)                               |
|  Camera picker + photo preview                    |
|  Progress bar                                     |
|  [Upload N photos]                                |
|                                                   |
| [SUCCESS CARD]                                    |
|  "3 photos uploaded" + auto-advance to next site  |
|                                                   |
+--------------------------------------------------+
| Recent Uploads strip                              |
+--------------------------------------------------+
```

### 8.2 Tab Behavior

- Default tab: **Planned** (if today has planned sites), otherwise **Unplanned**
- Tab selection persists during session (not reset on "Upload more")
- Both tabs share the same upload form section below

### 8.3 Site Card Format

```html
<div class="mon-site-card" data-site-id="42">
  <div class="mon-site-thumb">
    <img src="creative_url" alt="" />  <!-- or placeholder icon -->
  </div>
  <div class="mon-site-info">
    <div class="mon-site-primary">Samsung · 20×30 ft</div>
    <div class="mon-site-location">Andheri East Junction</div>
    <div class="mon-site-meta">
      📍 0.3 km · Last: 3 days ago
    </div>
    <div class="mon-site-issue">⚠️ 1 open issue</div>
  </div>
  <div class="mon-site-nav">
    <a href="https://www.google.com/maps/dir/?api=1&destination=28.4744,77.5040"
       target="_blank" class="mon-map-btn">🗺️</a>
  </div>
</div>
```

**Card states:**
- **Default:** White background, normal border
- **Selected:** Primary color border, light tint background
- **Done:** Green tint, checkmark overlay on thumbnail, upload timestamp shown, pushed below "Done" divider

### 8.4 GPS Sorting (client-side)

```javascript
// On page load / tab switch:
1. Browser GPS already captured (existing page-load geolocation)
2. For each site card, compute Haversine distance from user position
3. Sort: not-done sites by distance ASC, then done sites by upload time DESC
4. Sites without lat/lng go to bottom of not-done section
5. Recalculate on "Upload more" (position may have changed)
```

### 8.5 Condition Tags + Quick Issue

After selecting a site, a condition strip appears:

```
Site condition:
[Good ✓] [Damaged] [Faded] [Creative Missing] [Lights Off]
```

- Default: none selected. Condition selection is **optional** — monitoring person can upload without tagging a condition. This avoids friction for routine visits where everything is fine.
- **Good:** Recorded on upload's `site_condition` field. No issue created.
- **Damaged / Faded / Creative Missing / Lights Off:** 
  - Recorded on upload's `site_condition` field
  - Quick issue form expands: photo (can reuse upload photos) + note
  - On submit: creates issue + uploads photos as issue evidence
  - Issue title auto-generated: "Site Condition: DAMAGED — Andheri East Junction"

### 8.6 Issue Resolution

If the selected site has an open issue, show below the condition tags:

```
⚠️ Open Issue: "Board damaged — reported 3 days ago"
[✅ Mark Resolved]  (tap → expands)
  Upload proof photo (required): [📷 Take photo]
  Resolution note (optional): [________]
  [Submit Resolution]
```

Mandatory photo prevents false resolutions — the monitoring person must prove the fix.

### 8.7 Previous Photo Comparison

When a site is selected, show the last uploaded photo for that site:

```
Last photo (22 May):
┌─────────────┐
│  [thumbnail] │  ← clickable for full preview
│  3 days ago  │
└─────────────┘
```

Shows the most recent upload by **any** monitoring user for that site (not just the current user) — the point is to see the current state of the board. Fetched via a lightweight query: `SELECT id FROM uploads WHERE parent_type='SITE' AND parent_id=:site_id AND is_deleted=0 ORDER BY created_at DESC LIMIT 1`. The creative thumbnail (from `creative_upload_id`) shows the intended artwork; the previous photo shows the actual field condition.

### 8.8 Nearby Unmonitored Site Nudge (Unplanned Tab)

When GPS is available, check for active sites within `NEARBY_NUDGE_RADIUS_METERS` (300m) that haven't been monitored in `NEARBY_NUDGE_STALE_DAYS` (15) days:

```
📍 Unmonitored site nearby
┌──────┬────────────────────────────┐
│[img] │ Nike · 20×30 ft            │
│      │ Juhu Beach Road             │
│      │ 200m away · Last: 18d ago   │
│      │ [Tap to add to today's list]│
└──────┴────────────────────────────┘
```

Query runs client-side against the already-loaded site list (no extra API call). Only shows for **active** sites (`is_active = 1`).

### 8.9 Shift Controls

**Before shift started:**
```
┌────────────────────────────────┐
│  ▶ Start Monitoring            │
│  Tap to begin your shift       │
└────────────────────────────────┘
```

**During shift:**
```
┌────────────────────────────────┐
│  🟢 Shift started at 9:15 AM  │
│  [■ Complete Day]              │
└────────────────────────────────┘
```

**After completing:**
```
┌────────────────────────────────┐
│  ✅ Shift Complete             │
│  12 planned · 10 done · 2 extra│
│  9:15 AM → 4:30 PM (7h 15m)   │
└────────────────────────────────┘
```

### 8.10 Auto-Advance After Upload

After successful upload → "Upload more" tap:
1. Mark current site as done (green card, move to bottom)
2. Scroll to the next nearest unvisited site
3. Auto-highlight it (but don't auto-select — user confirms with tap)
4. Category/route selection persists (unplanned tab)

---

## 9. Site Detail Page — Creative + Board Size

### 9.1 Board Size Fields

Added to the site edit form:

```
Board Dimensions (ft)
┌──────────────┐  ┌──────────────┐
│ Width (ft)   │  │ Height (ft)  │
└──────────────┘  └──────────────┘
```

- Side-by-side numeric inputs, nullable
- Stored as `board_width_ft` / `board_height_ft` (SMALLINT UNSIGNED)

### 9.2 Creative Upload Section

New section on site detail page:

```
Current Creative
┌─────────────────────────────────┐
│                                 │
│      [Creative image]           │
│      (click to preview)         │
│                                 │
├─────────────────────────────────┤
│  [Upload New Creative]          │
│  or [Replace Creative]          │
└─────────────────────────────────┘
```

**States:**
- No creative: Placeholder + "Upload Creative" button
- Has creative: Thumbnail (clickable → `openPhotoGallery`) + "Replace Creative" button

**Upload flow:**
1. File picker (single image, same validation as other uploads)
2. Image saved as: `parent_type=SITE`, `parent_id=site_id`, `upload_type=WORK`, `photo_label=GENERAL`
3. `sites.creative_upload_id` updated to new upload's ID
4. Old creative upload NOT deleted — stays as history

**Access:** CLIENT_SERVICE, OPS_MANAGER, MEDIA_PLANNING — anyone who can edit site details.

**Validation:** When `is_active = 1`, creative is mandatory. Cannot activate a site without uploading a creative first.

---

## 10. Monitoring History Page — Enrichment

### 10.1 Card Enrichment

Current history cards show site code + basic info. Update to show:

- **Client name** (from active campaign)
- **Board size** (W × H ft)
- **Location text** (instead of or alongside site code)
- Site code shown as small secondary text

### 10.2 No Other Changes

The rest of the history page (date chips, category chips, discovery chips, photo preview, self-delete) remains unchanged.

---

## 11. Monitoring Plan Page — Missed Site Filter

### 11.1 Filter Addition

Add filter to existing monitoring plan page:

```
Show: [All] [Completed ✅] [Missed ❌]
```

**Logic:**
- **Completed:** `site_monitoring_due_dates.completed_at IS NOT NULL` for dates ≤ today
- **Missed:** `completed_at IS NULL AND due_date < CURDATE()`
- **All:** No filter

### 11.2 No Other Changes

The rest of the monitoring plan page remains unchanged.

---

## 12. Constants

```php
// Radius in meters for nearby unmonitored site nudge
define('NEARBY_NUDGE_RADIUS_METERS', 300);

// Days since last monitoring before a site is considered stale for nudge
define('NEARBY_NUDGE_STALE_DAYS', 15);
```

---

## 13. Access Control

| Role | What they can do (new) |
|---|---|
| MONITORING_TEAM | All upload page features: tabs, cards, shift, condition, issue report/resolve |
| CLIENT_SERVICE | Upload creative on site detail page |
| OPS_MANAGER | Upload creative, edit board size, view shift data, missed site filter |
| MEDIA_PLANNING | Upload creative, edit board size |

No new module keys needed. Creative upload uses existing site management module. Shift and browse use `monitoring.upload`.

---

## 14. Security and Validation

- File validation: same rules as existing uploads (ALLOWED_UPLOAD_EXTENSIONS, ALLOWED_UPLOAD_MIME_TYPES, MAX_UPLOAD_SIZE_MB)
- Max files per submission: MAX_UPLOAD_FILES_PER_SUBMISSION (10)
- `site_condition` validated against ENUM values
- Issue resolution requires at least 1 photo (enforced in service layer)
- Creative upload: single file only
- Board dimensions: positive integers, max 9999
- GPS coordinates: decimal range validation (-90 to 90 lat, -180 to 180 lng)
- Shift: one per user per day (UNIQUE constraint)
- Only MONITORING_TEAM can start/complete shifts

---

## 15. Post-Upload Automation

When a monitoring upload is submitted successfully, the system automatically:

1. **Updates `sites.last_monitored_at`** and `last_monitored_by_user_id` for the site
2. **Marks due date completed** if the site had a `site_monitoring_due_dates` entry for today
3. **Increments shift counter** (`completed_count` or `unplanned_count`) if a shift is active
4. **Stores site_condition** on the upload record (if condition was selected)

All side effects happen within the existing upload transaction or as immediate post-commit updates.

---

## 16. Documents to Update

| Document | What to update |
|---|---|
| `docs/06_schema/schema_v1_full.sql` | Add new columns to sites, site_monitoring_due_dates, uploads; add monitoring_shifts table; add index |
| `docs/06_schema/11_SCHEMA_BASELINE_v1_FINAL_WITH_DDL.md` | Mirror all DDL changes |
| `docs/06_schema/12_SCHEMA_SPECIFICATION_v1.md` | Document new fields, ENUMs, constraints, relationships |
| `docs/01_structure/05_DATA_AND_FLOW_NOTES_FINAL.md` | Monitoring upload flow: tabs, enriched cards, shift lifecycle, condition reporting, issue resolution |
| `docs/AGENT_START.md` | Update current focus, what not to touch |
| `docs/PRODUCT_BACKLOG.md` | Update monitoring status, add new feature entries |
| `docs/PRODUCT_LOG.md` | Log schema addition decisions, design rationale |
| `docs/AI_TOOL_HANDOFF_GUIDE.md` | New pitfalls: site_condition ENUM, monitoring_shifts table, creative_upload_id FK, last_monitored_at denormalization |
