# Media Discovery Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a dedicated Media Discovery page for MONITORING_TEAM with auto-site-creation, GPS extraction, and free_media_record lifecycle.

**Architecture:** MediaDiscoveryController -> MediaDiscoveryService -> SiteRepository + UploadService (reused) + FreeMediaRepository. Single transaction in service layer. Frontend is a new `Views.register('monitoring.discovery', {...})` block in modules.js.

**Tech Stack:** PHP 8+ / PDO / MySQL, vanilla JavaScript SPA, XAMPP shared hosting

**Spec:** `docs/superpowers/specs/2026-05-24-media-discovery-design.md`

---

## File Map

| File | Action | Responsibility |
|------|--------|----------------|
| `app/controllers/MediaDiscoveryController.php` | CREATE | Request handling: submit, myList |
| `app/services/MediaDiscoveryService.php` | CREATE | Orchestration: GPS logic, site creation, upload delegation, free_media_record |
| `migrations/004_media_discovery_module.sql` | CREATE | Add `monitoring.discovery` to role_module_scopes |
| `config/route_registry.php` | MODIFY | Add 2 discovery routes |
| `config/constants.php` | MODIFY | Add 2 constants, remove 1 |
| `app/repositories/SiteRepository.php` | MODIFY | Add `findDiscoveryNearby()`, `generateDiscoverySiteCode()` |
| `app/repositories/FreeMediaRepository.php` | MODIFY | Add `createDiscoveredRecord()`, `findDiscoveredBySiteId()`, `refreshSourceReference()` |
| `public/js/core/navigation.js` | MODIFY | Add `monitoring.discovery` nav entry |
| `public/js/views/modules.js` | MODIFY | Add discovery module, remove discovery toggle from monitoring.upload |
| `public/index.html` | MODIFY | Cache bump |

---

## Task 1: Constants and Migration

**Files:**
- Modify: `config/constants.php`
- Create: `migrations/004_media_discovery_module.sql`

- [ ] **Step 1: Add constants**

In `config/constants.php`, add after the `FREE MEDIA` section and remove `FREE_MEDIA_DEFAULT_SITE_ID`:

```php
// Remove this line:
// define('FREE_MEDIA_DEFAULT_SITE_ID', 38);

// Add these in the FREE MEDIA section:

// Days before a pending discovery triggers a stale alert on dashboard.
// Governance Reference: DATA_AND_FLOW section (future)
define('DISCOVERY_PENDING_ALERT_DAYS', 7);

// GPS radius in meters for proximity dedup check before creating new discovery site.
define('DISCOVERY_GPS_DEDUP_RADIUS_METERS', 50);
```

- [ ] **Step 2: Create migration**

Create `migrations/004_media_discovery_module.sql`:

```sql
-- Migration: Add monitoring.discovery module_key for MONITORING_TEAM role
-- Date: 2026-05-24
-- Feature: Media Discovery page

INSERT INTO role_module_scopes (role_id, module_key)
SELECT id, 'monitoring.discovery' FROM roles WHERE role_key = 'MONITORING_TEAM'
ON DUPLICATE KEY UPDATE module_key = module_key;
```

- [ ] **Step 3: Run migration**

Run: Open `http://localhost/skite/migrations/004_media_discovery_module.sql` in phpMyAdmin or execute via CLI:
```bash
mysql -u root skite_ops < migrations/004_media_discovery_module.sql
```
Expected: 1 row inserted into role_module_scopes.

- [ ] **Step 4: Commit**

```bash
git add config/constants.php migrations/004_media_discovery_module.sql
git commit -m "feat(discovery): add constants and migration for monitoring.discovery module"
```

---

## Task 2: SiteRepository — GPS dedup and code generation

**Files:**
- Modify: `app/repositories/SiteRepository.php`

- [ ] **Step 1: Add findDiscoveryNearby method**

Add to `SiteRepository.php` after the existing `create()` method:

```php
/**
 * Find the nearest pending-discovery site within a radius using Haversine.
 * Only matches DISC-* sites with a DISCOVERED free_media_record.
 *
 * @param float $lat Latitude of the new discovery
 * @param float $lng Longitude of the new discovery
 * @param int $radiusMeters Maximum distance in meters
 * @return array|null Nearest matching site row or null
 */
public function findDiscoveryNearby(float $lat, float $lng, int $radiusMeters): ?array
{
    $sql = "SELECT s.id, s.site_code, s.latitude, s.longitude,
                   (6371000 * ACOS(
                       COS(RADIANS(?)) * COS(RADIANS(s.latitude)) *
                       COS(RADIANS(s.longitude) - RADIANS(?)) +
                       SIN(RADIANS(?)) * SIN(RADIANS(s.latitude))
                   )) AS distance_meters
            FROM sites s
            INNER JOIN free_media_records fm ON fm.site_id = s.id AND fm.status = 'DISCOVERED'
            WHERE s.site_code LIKE 'DISC-%'
              AND s.is_active = 0
              AND s.latitude IS NOT NULL
              AND s.longitude IS NOT NULL
            HAVING distance_meters <= ?
            ORDER BY distance_meters ASC
            LIMIT 1";

    return $this->fetchOne($sql, [$lat, $lng, $lat, $radiusMeters]);
}
```

- [ ] **Step 2: Add generateDiscoverySiteCode method**

```php
/**
 * Generate next sequential discovery site code for today.
 * Format: DISC-YYYYMMDD-NNN (e.g., DISC-20260524-001)
 *
 * @return string The generated unique site_code
 */
public function generateDiscoverySiteCode(): string
{
    $today = date('Ymd');
    $prefix = 'DISC-' . $today . '-';

    $row = $this->fetchOne(
        "SELECT COUNT(*) AS cnt FROM sites WHERE site_code LIKE ?",
        [$prefix . '%']
    );

    $next = ((int)($row['cnt'] ?? 0)) + 1;

    return $prefix . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
}
```

- [ ] **Step 3: Commit**

```bash
git add app/repositories/SiteRepository.php
git commit -m "feat(discovery): add GPS dedup and site code generation to SiteRepository"
```

---

## Task 3: FreeMediaRepository — discovery record methods

**Files:**
- Modify: `app/repositories/FreeMediaRepository.php`

- [ ] **Step 1: Add createDiscoveredRecord method**

Add after `createConfirmedRecord()`:

```php
/**
 * Create a DISCOVERED free_media_record for a new discovery site.
 *
 * @param int $siteId The newly created discovery site ID
 * @param int $sourceUploadId The first upload's ID (representative photo)
 * @param string $discoveredDate Date of discovery (Y-m-d)
 * @return int The new record ID
 */
public function createDiscoveredRecord(int $siteId, int $sourceUploadId, string $discoveredDate): int
{
    $this->execute(
        "INSERT INTO free_media_records
            (site_id, source_type, source_reference_id, discovered_date,
             status, created_at, updated_at)
         VALUES (?, 'MONITORING_DISCOVERY', ?, ?, 'DISCOVERED', NOW(), NOW())",
        [$siteId, $sourceUploadId, $discoveredDate]
    );
    return (int)$this->lastInsertId();
}
```

- [ ] **Step 2: Add findDiscoveredBySiteId method**

```php
/**
 * Find the DISCOVERED free_media_record for a given site (for dedup refresh).
 *
 * @param int $siteId
 * @return array|null
 */
public function findDiscoveredBySiteId(int $siteId): ?array
{
    return $this->fetchOne(
        "SELECT * FROM free_media_records
         WHERE site_id = ? AND source_type = 'MONITORING_DISCOVERY' AND status = 'DISCOVERED'
         LIMIT 1",
        [$siteId]
    );
}
```

- [ ] **Step 3: Add refreshSourceReference method**

```php
/**
 * Update the source_reference_id of a DISCOVERED record (when new photos added via dedup).
 *
 * @param int $recordId
 * @param int $newUploadId
 */
public function refreshSourceReference(int $recordId, int $newUploadId): void
{
    $this->execute(
        "UPDATE free_media_records
         SET source_reference_id = ?, updated_at = NOW()
         WHERE id = ?",
        [$newUploadId, $recordId]
    );
}
```

- [ ] **Step 4: Commit**

```bash
git add app/repositories/FreeMediaRepository.php
git commit -m "feat(discovery): add discovered record methods to FreeMediaRepository"
```

---

## Task 4: MediaDiscoveryService

**Files:**
- Create: `app/services/MediaDiscoveryService.php`

**Important design note:** `UploadService::createUploadsForSurface('MONITORING', ...)` already manages its own transaction AND already calls `createOrRefreshDiscoveryRecord()` internally when `is_discovery_mode=true`. Therefore MediaDiscoveryService does NOT start its own transaction — it:
1. Creates the placeholder site (auto-committed, orphan is harmless if later step fails)
2. Delegates to UploadService which handles upload + free_media_record in its own transaction

- [ ] **Step 1: Create the service file**

Create `app/services/MediaDiscoveryService.php`:

```php
<?php

/**
 * MediaDiscoveryService
 *
 * Purpose:
 * Orchestrates the media discovery submission flow:
 * GPS resolution -> dedup check -> site creation -> delegate to UploadService.
 *
 * Transaction note:
 * UploadService::createUploadsForSurface() manages its own transaction internally
 * and already calls createOrRefreshDiscoveryRecord() for discovery uploads.
 * This service does NOT start a separate transaction to avoid PDO nesting issues.
 * Site creation is committed immediately — an orphan DISC-* site (is_active=0) is
 * harmless if the upload step fails.
 *
 * Architecture: Controller -> Service -> Repository -> Database
 */

require_once __DIR__ . '/../repositories/SiteRepository.php';
require_once __DIR__ . '/../repositories/FreeMediaRepository.php';
require_once __DIR__ . '/UploadService.php';
require_once __DIR__ . '/AuditService.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';

class MediaDiscoveryService
{
    private SiteRepository $siteRepo;
    private FreeMediaRepository $freeMediaRepo;
    private UploadService $uploadService;
    private AuditService $auditService;

    public function __construct()
    {
        $this->siteRepo = new SiteRepository();
        $this->freeMediaRepo = new FreeMediaRepository();
        $this->uploadService = new UploadService();
        $this->auditService = new AuditService();
    }

    /**
     * Submit a new media discovery.
     *
     * @param array $data Keys: comment_text, browser_lat, browser_lng, exif_lat, exif_lng
     * @param array $rawFiles $_FILES array
     * @param int $actorUserId The monitoring person's user ID
     * @return array Summary of what was created
     */
    public function submitDiscovery(array $data, array $rawFiles, int $actorUserId): array
    {
        // Step 1: Resolve best GPS coordinates
        $gps = $this->resolveGps($data);

        // Step 2: GPS dedup check
        $existingSite = null;
        if ($gps['lat'] !== null && $gps['lng'] !== null) {
            $existingSite = $this->siteRepo->findDiscoveryNearby(
                $gps['lat'],
                $gps['lng'],
                DISCOVERY_GPS_DEDUP_RADIUS_METERS
            );
        }

        // Step 3: Create site or reuse existing
        $isNewSite = ($existingSite === null);
        if ($isNewSite) {
            $siteId = $this->createPlaceholderSite($gps);
        } else {
            $siteId = (int)$existingSite['id'];
        }

        // Step 4: Delegate to UploadService (handles its own transaction + free_media_record)
        $uploadResult = $this->uploadService->createUploadsForSurface('MONITORING', [
            'parent_type'    => 'SITE',
            'parent_id'      => $siteId,
            'upload_type'    => 'WORK',
            'discovery_mode' => true,
            'comment_text'   => $this->sanitizeComment($data['comment_text'] ?? null),
            'gps_latitude'   => $gps['exif_lat'],
            'gps_longitude'  => $gps['exif_lng'],
            'photo_label'    => 'GENERAL',
        ], $rawFiles, $actorUserId);

        // Step 5: Audit the overall discovery action
        $photoCount = count($uploadResult['created_uploads'] ?? []);
        $firstUploadId = $uploadResult['created_uploads'][0]['id'] ?? 0;

        // Find the free_media_record that UploadService just created/refreshed
        $fmRecord = $this->freeMediaRepo->findDiscoveredBySiteId($siteId);
        $recordId = $fmRecord ? (int)$fmRecord['id'] : 0;

        $this->auditService->logAction(
            $actorUserId,
            'DISCOVERY_SUBMITTED',
            'free_media_records',
            $recordId,
            null,
            [
                'site_id'     => $siteId,
                'is_new_site' => $isNewSite,
                'photo_count' => $photoCount,
                'has_gps'     => ($gps['lat'] !== null),
            ]
        );

        // Fetch the site_code for response
        $site = $this->siteRepo->findById($siteId);

        return [
            'success'     => true,
            'site_id'     => $siteId,
            'site_code'   => $site['site_code'] ?? '',
            'is_new_site' => $isNewSite,
            'photo_count' => $photoCount,
            'has_gps'     => ($gps['lat'] !== null),
            'record_id'   => $recordId,
        ];
    }

    /**
     * List the actor's own discoveries with pagination.
     */
    public function listMyDiscoveries(int $actorUserId, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        // Get uploads created by this user with is_discovery_mode=1, grouped by parent site
        $sql = "SELECT u.id AS upload_id, u.parent_id AS site_id, u.file_path,
                       u.comment_text, u.gps_latitude, u.gps_longitude, u.created_at,
                       s.site_code, s.latitude AS site_lat, s.longitude AS site_lng,
                       fm.status AS discovery_status
                FROM uploads u
                INNER JOIN sites s ON s.id = u.parent_id
                LEFT JOIN free_media_records fm ON fm.site_id = s.id
                    AND fm.source_type = 'MONITORING_DISCOVERY'
                WHERE u.created_by_user_id = ?
                  AND u.is_discovery_mode = 1
                  AND u.is_deleted = 0
                  AND u.is_purged = 0
                ORDER BY u.created_at DESC
                LIMIT {$limit} OFFSET {$offset}";

        $db = Database::getConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute([$actorUserId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Count total
        $countSql = "SELECT COUNT(*) AS total
                     FROM uploads u
                     WHERE u.created_by_user_id = ?
                       AND u.is_discovery_mode = 1
                       AND u.is_deleted = 0
                       AND u.is_purged = 0";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute([$actorUserId]);
        $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        return [
            'items' => $items,
            'pagination' => [
                'page'  => $page,
                'limit' => $limit,
                'total' => $total,
            ],
        ];
    }

    /**
     * Resolve the best GPS coordinates from browser and EXIF sources.
     * Priority: browser GPS > EXIF GPS > null.
     */
    private function resolveGps(array $data): array
    {
        $browserLat = $this->parseCoord($data['browser_lat'] ?? null, -90, 90);
        $browserLng = $this->parseCoord($data['browser_lng'] ?? null, -180, 180);
        $exifLat = $this->parseCoord($data['exif_lat'] ?? null, -90, 90);
        $exifLng = $this->parseCoord($data['exif_lng'] ?? null, -180, 180);

        // Priority: browser > exif
        if ($browserLat !== null && $browserLng !== null) {
            return ['lat' => $browserLat, 'lng' => $browserLng, 'exif_lat' => $exifLat, 'exif_lng' => $exifLng];
        }

        if ($exifLat !== null && $exifLng !== null) {
            return ['lat' => $exifLat, 'lng' => $exifLng, 'exif_lat' => $exifLat, 'exif_lng' => $exifLng];
        }

        return ['lat' => null, 'lng' => null, 'exif_lat' => null, 'exif_lng' => null];
    }

    private function parseCoord($value, float $min, float $max): ?float
    {
        if ($value === null || $value === '') return null;
        if (!is_numeric($value)) return null;
        $f = (float)$value;
        if ($f < $min || $f > $max) return null;
        return $f;
    }

    private function createPlaceholderSite(array $gps): int
    {
        $siteCode = $this->siteRepo->generateDiscoverySiteCode();

        $locationText = 'Discovery - pending details';
        if ($gps['lat'] !== null && $gps['lng'] !== null) {
            $locationText = sprintf('%.5f°N, %.5f°E', $gps['lat'], $gps['lng']);
        }

        // Retry up to 3 times for race condition on UNIQUE site_code
        $maxRetries = 3;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                return $this->siteRepo->create([
                    'site_code'      => $siteCode,
                    'location_text'  => $locationText,
                    'site_category'  => 'CITY',
                    'lighting_type'  => 'NON_LIT',
                    'latitude'       => $gps['lat'],
                    'longitude'      => $gps['lng'],
                    'is_active'      => 0,
                ]);
            } catch (PDOException $e) {
                if ($attempt === $maxRetries || strpos($e->getMessage(), 'Duplicate entry') === false) {
                    throw $e;
                }
                // Regenerate code and retry
                $siteCode = $this->siteRepo->generateDiscoverySiteCode();
            }
        }

        throw new RuntimeException('Failed to generate unique discovery site code after retries.');
    }

    private function sanitizeComment(?string $value): ?string
    {
        if ($value === null) return null;
        $trimmed = trim($value);
        if ($trimmed === '') return null;
        return mb_substr($trimmed, 0, 500);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/services/MediaDiscoveryService.php
git commit -m "feat(discovery): create MediaDiscoveryService with submit and list logic"
```

---

## Task 5: MediaDiscoveryController

**Files:**
- Create: `app/controllers/MediaDiscoveryController.php`

- [ ] **Step 1: Create the controller**

Create `app/controllers/MediaDiscoveryController.php`:

```php
<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/MediaDiscoveryService.php';

/**
 * MediaDiscoveryController
 *
 * Purpose:
 * Handles the media discovery page endpoints for MONITORING_TEAM.
 * Routes: discovery/submit (POST), discovery/my-list (GET)
 */
class MediaDiscoveryController extends BaseController
{
    private MediaDiscoveryService $discoveryService;

    public function __construct()
    {
        $this->discoveryService = new MediaDiscoveryService();
    }

    /**
     * POST discovery/submit
     * Accepts multipart form data with photos + optional GPS + comment.
     */
    public function submit(): void
    {
        if (!$this->requireMethod('POST')) return;

        $actor = $this->getActor();
        if ($actor['role_key'] !== 'MONITORING_TEAM') {
            Response::error('Access denied', 403);
            return;
        }

        $rawFiles = $_FILES['photos'] ?? [];
        if (empty($rawFiles) || empty($rawFiles['name'][0])) {
            Response::error('At least one photo is required.', 422);
            return;
        }

        $data = [
            'comment_text' => $_POST['comment_text'] ?? null,
            'browser_lat'  => $_POST['browser_lat'] ?? null,
            'browser_lng'  => $_POST['browser_lng'] ?? null,
            'exif_lat'     => $_POST['exif_lat'] ?? null,
            'exif_lng'     => $_POST['exif_lng'] ?? null,
        ];

        try {
            $result = $this->discoveryService->submitDiscovery($data, $rawFiles, $actor['user_id']);
            Response::success($result);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * GET discovery/my-list
     * Returns the actor's own discovery uploads with pagination.
     */
    public function myList(): void
    {
        if (!$this->requireMethod('GET')) return;

        $actor = $this->getActor();
        if ($actor['role_key'] !== 'MONITORING_TEAM') {
            Response::error('Access denied', 403);
            return;
        }

        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));

        try {
            $result = $this->discoveryService->listMyDiscoveries($actor['user_id'], $page, $limit);
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/controllers/MediaDiscoveryController.php
git commit -m "feat(discovery): create MediaDiscoveryController with submit and myList endpoints"
```

---

## Task 6: Route Registry

**Files:**
- Modify: `config/route_registry.php`

- [ ] **Step 1: Add discovery routes**

Add after the MONITORING HISTORY section (after line ~688):

```php
    // ==========================================
    // MEDIA DISCOVERY (MONITORING_TEAM)
    // ==========================================

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

- [ ] **Step 2: Commit**

```bash
git add config/route_registry.php
git commit -m "feat(discovery): register discovery routes in route_registry"
```

---

## Task 7: Navigation Entry

**Files:**
- Modify: `public/js/core/navigation.js`

- [ ] **Step 1: Add monitoring.discovery to nav config**

Add after the `monitoring.upload` line (line ~27):

```javascript
  'monitoring.discovery': { label: 'Media Discovery', icon: 'ph-binoculars', route: 'discovery/my-list', section: 'Monitoring', roles: ['MONITORING_TEAM'] },
```

- [ ] **Step 2: Commit**

```bash
git add public/js/core/navigation.js
git commit -m "feat(discovery): add Media Discovery nav entry"
```

---

## Task 8: Frontend — Discovery Module in modules.js

**Files:**
- Modify: `public/js/views/modules.js`

- [ ] **Step 1: Remove FREE_MEDIA_DEFAULT_SITE_ID constant**

Find and remove the line (near line ~1805):
```javascript
// Default site for Free Media Discovery uploads — matches config/constants.php FREE_MEDIA_DEFAULT_SITE_ID
const FREE_MEDIA_DEFAULT_SITE_ID = 38;
```

- [ ] **Step 2: Remove discovery toggle from monitoring.upload**

In the `Views.register('monitoring.upload', {...})` block:
- Remove the discovery toggle chips HTML (Regular Visit / Free Media Discovery)
- Remove the `js-mon-fmd-auto-site` hidden section HTML
- Remove the discovery toggle event listener in `afterRender()`
- Remove the `FREE_MEDIA_DEFAULT_SITE_ID` usage in `getParentId()` — make it always require a site
- Remove the discovery note element and related visibility toggling
- Remove the `if (siteSection_) siteSection_.hidden = false;` and `if (fmdAutoSite) fmdAutoSite.hidden = true;` in the upload-more reset

After removal, `getParentId()` simplifies to:
```javascript
const getParentId = () => {
  if (siteSelect && siteSelect.value) return siteSelect.value;
  if (searchIdInput && searchIdInput.value) return searchIdInput.value;
  return '';
};
```

- [ ] **Step 3: Add the monitoring.discovery module**

Add before or after the `Views.register('monitoring.history', {...})` block:

```javascript
// ============================================================
// MEDIA DISCOVERY (MONITORING_TEAM)
// ============================================================

Views.register('monitoring.discovery', {
  async render() {
    const recentRes = await Api.get('discovery/my-list', { limit: 20 });
    const recent = recentRes?.data?.items || [];

    const recentStrip = recent.length > 0
      ? `<div class="upload-section">
           <div class="upload-section-label">My Recent Discoveries</div>
           <div class="recent-uploads-strip">
             ${recent.map(r => `
               <div class="recent-upload-card js-disc-preview" data-id="${r.upload_id}" title="${r.site_code} — ${r.created_at}">
                 <img src="${Api.url('upload/serve', { id: r.upload_id })}" alt="" onerror="this.style.display='none'">
                 <span class="recent-upload-date">${UI.shortDate(r.created_at)}</span>
               </div>
             `).join('')}
           </div>
         </div>`
      : '';

    return UI.page(
      'Media Discovery',
      'Report new advertising media you\'ve spotted in the field',
      ''
    ) + `
      <form id="discovery-form" class="upload-form" enctype="multipart/form-data">
        <div class="upload-section">
          <div class="upload-section-label">Photos</div>
          <p style="font-size:0.82rem;color:var(--text-muted);margin:0 0 8px;">
            Take or select 1-${MAX_UPLOAD_FILES_PER_SUBMISSION || 10} photos of the discovered media
          </p>
          <label class="file-picker">
            <input type="file" name="photos" id="disc-photos" accept="image/jpeg,image/png,image/webp"
                   multiple capture="environment" style="display:none">
            <span class="btn btn-primary"><i class="ph ph-camera"></i> Take / Select Photos</span>
          </label>
          <div id="disc-preview-grid" class="upload-preview-grid"></div>
        </div>

        <div id="disc-gps-status" class="upload-section" hidden>
          <div id="disc-gps-badge" class="gps-badge"></div>
        </div>

        <div class="upload-section">
          <div class="upload-section-label">Location / Description</div>
          <textarea id="disc-comment" name="comment_text" rows="3" maxlength="500"
                    class="form-control"
                    placeholder="Describe the location (landmark, road, nearby building)..."></textarea>
          <p id="disc-gps-warning" class="form-hint" hidden style="color:var(--warning-text);margin-top:4px;">
            <i class="ph ph-warning"></i> No GPS detected in photo. Please describe the location above.
          </p>
        </div>

        <input type="hidden" id="disc-browser-lat" name="browser_lat">
        <input type="hidden" id="disc-browser-lng" name="browser_lng">
        <input type="hidden" id="disc-exif-lat" name="exif_lat">
        <input type="hidden" id="disc-exif-lng" name="exif_lng">

        <button type="submit" class="btn btn-primary btn-block" id="disc-submit-btn">
          <i class="ph ph-upload-simple"></i> Submit Discovery
        </button>
      </form>

      <div id="disc-success" class="upload-success-state" hidden>
        <i class="ph ph-check-circle" style="font-size:2.5rem;color:var(--success)"></i>
        <p class="upload-success-msg">Discovery submitted!</p>
        <p class="upload-success-sub" id="disc-success-detail"></p>
        <div class="upload-success-actions">
          <button type="button" class="btn btn-ghost" id="disc-another-btn">Report Another</button>
        </div>
      </div>

      ${recentStrip}
    `;
  },

  async afterRender() {
    const form = document.getElementById('discovery-form');
    const photosInput = document.getElementById('disc-photos');
    const previewGrid = document.getElementById('disc-preview-grid');
    const gpsBadge = document.getElementById('disc-gps-badge');
    const gpsStatus = document.getElementById('disc-gps-status');
    const gpsWarning = document.getElementById('disc-gps-warning');
    const browserLatInput = document.getElementById('disc-browser-lat');
    const browserLngInput = document.getElementById('disc-browser-lng');
    const exifLatInput = document.getElementById('disc-exif-lat');
    const exifLngInput = document.getElementById('disc-exif-lng');
    const submitBtn = document.getElementById('disc-submit-btn');
    const successDiv = document.getElementById('disc-success');
    const successDetail = document.getElementById('disc-success-detail');
    const anotherBtn = document.getElementById('disc-another-btn');

    let selectedFiles = [];
    let exifGps = null;

    // --- EXIF GPS extraction (client-side) ---
    function readExifGps(file) {
      return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = function(e) {
          try {
            const view = new DataView(e.target.result);
            // Quick JPEG check
            if (view.getUint16(0) !== 0xFFD8) { resolve(null); return; }
            let offset = 2;
            while (offset < view.byteLength - 1) {
              const marker = view.getUint16(offset);
              if (marker === 0xFFE1) {
                const exifData = parseExifGps(view, offset + 4);
                resolve(exifData);
                return;
              }
              offset += 2 + view.getUint16(offset + 2);
            }
            resolve(null);
          } catch { resolve(null); }
        };
        reader.readAsArrayBuffer(file.slice(0, 128 * 1024)); // First 128KB is enough for EXIF
      });
    }

    function parseExifGps(view, start) {
      // Simplified EXIF GPS parser — looks for GPS IFD
      try {
        const exifStr = String.fromCharCode(view.getUint8(start), view.getUint8(start+1),
                                            view.getUint8(start+2), view.getUint8(start+3));
        if (exifStr !== 'Exif') return null;

        const tiffStart = start + 6;
        const bigEndian = view.getUint16(tiffStart) === 0x4D4D;
        const getU16 = (o) => view.getUint16(o, !bigEndian);
        const getU32 = (o) => view.getUint32(o, !bigEndian);

        const ifdOffset = getU32(tiffStart + 4);
        const ifd0Start = tiffStart + ifdOffset;
        const ifd0Count = getU16(ifd0Start);

        let gpsIfdOffset = null;
        for (let i = 0; i < ifd0Count; i++) {
          const entryOff = ifd0Start + 2 + i * 12;
          if (getU16(entryOff) === 0x8825) { // GPSInfo tag
            gpsIfdOffset = getU32(entryOff + 8);
            break;
          }
        }
        if (gpsIfdOffset === null) return null;

        const gpsStart = tiffStart + gpsIfdOffset;
        const gpsCount = getU16(gpsStart);
        let latRef = null, lngRef = null, latVals = null, lngVals = null;

        for (let i = 0; i < gpsCount; i++) {
          const eOff = gpsStart + 2 + i * 12;
          const tag = getU16(eOff);
          if (tag === 1) latRef = String.fromCharCode(view.getUint8(eOff + 8));
          if (tag === 3) lngRef = String.fromCharCode(view.getUint8(eOff + 8));
          if (tag === 2) latVals = readRationals(view, tiffStart + getU32(eOff + 8), 3, bigEndian);
          if (tag === 4) lngVals = readRationals(view, tiffStart + getU32(eOff + 8), 3, bigEndian);
        }

        if (!latVals || !lngVals) return null;
        let lat = latVals[0] + latVals[1] / 60 + latVals[2] / 3600;
        let lng = lngVals[0] + lngVals[1] / 60 + lngVals[2] / 3600;
        if (latRef === 'S') lat = -lat;
        if (lngRef === 'W') lng = -lng;
        return { lat, lng };
      } catch { return null; }
    }

    function readRationals(view, offset, count, bigEndian) {
      const vals = [];
      for (let i = 0; i < count; i++) {
        const num = view.getUint32(offset + i * 8, !bigEndian);
        const den = view.getUint32(offset + i * 8 + 4, !bigEndian);
        vals.push(den ? num / den : 0);
      }
      return vals;
    }

    // --- Photo selection handler ---
    photosInput?.addEventListener('change', async () => {
      const maxFiles = MAX_UPLOAD_FILES_PER_SUBMISSION || 10;
      selectedFiles = Array.from(photosInput.files).slice(0, maxFiles);
      previewGrid.innerHTML = selectedFiles.map((f, i) => {
        const url = URL.createObjectURL(f);
        return `<div class="upload-preview-item">
          <img src="${url}" alt="Photo ${i + 1}">
          <button type="button" class="upload-preview-remove" data-idx="${i}">&times;</button>
        </div>`;
      }).join('');

      // Read EXIF from first photo
      if (selectedFiles.length > 0) {
        exifGps = await readExifGps(selectedFiles[0]);
        if (exifGps) {
          exifLatInput.value = exifGps.lat.toFixed(7);
          exifLngInput.value = exifGps.lng.toFixed(7);
          gpsStatus.hidden = false;
          gpsBadge.innerHTML = `<i class="ph ph-map-pin" style="color:var(--success)"></i> GPS detected (${exifGps.lat.toFixed(4)}°, ${exifGps.lng.toFixed(4)}°)`;
          gpsBadge.className = 'gps-badge gps-badge--ok';
          gpsWarning.hidden = true;
        } else {
          exifLatInput.value = '';
          exifLngInput.value = '';
          gpsStatus.hidden = false;
          gpsBadge.innerHTML = `<i class="ph ph-map-pin-line" style="color:var(--warning-text)"></i> No GPS in photo`;
          gpsBadge.className = 'gps-badge gps-badge--warn';
          gpsWarning.hidden = false;
        }
      }
    });

    // Remove photo from preview
    previewGrid?.addEventListener('click', (e) => {
      const removeBtn = e.target.closest('.upload-preview-remove');
      if (!removeBtn) return;
      const idx = parseInt(removeBtn.dataset.idx, 10);
      selectedFiles.splice(idx, 1);
      // Re-trigger preview rebuild
      const dt = new DataTransfer();
      selectedFiles.forEach(f => dt.items.add(f));
      photosInput.files = dt.files;
      photosInput.dispatchEvent(new Event('change'));
    });

    // --- Form submit ---
    form?.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (selectedFiles.length === 0) {
        UI.toast('Please select at least one photo', 'error');
        return;
      }

      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Submitting...';

      // Request browser geolocation (5s timeout)
      try {
        const pos = await new Promise((resolve, reject) => {
          if (!navigator.geolocation) { reject('no-geo'); return; }
          navigator.geolocation.getCurrentPosition(resolve, reject, {
            enableHighAccuracy: true,
            timeout: 5000,
            maximumAge: 30000,
          });
        });
        browserLatInput.value = pos.coords.latitude.toFixed(7);
        browserLngInput.value = pos.coords.longitude.toFixed(7);
      } catch {
        // Geolocation unavailable or denied — proceed without it
        browserLatInput.value = '';
        browserLngInput.value = '';
      }

      // Build FormData
      const fd = new FormData();
      selectedFiles.forEach(f => fd.append('photos[]', f));
      fd.append('comment_text', document.getElementById('disc-comment')?.value || '');
      fd.append('browser_lat', browserLatInput.value);
      fd.append('browser_lng', browserLngInput.value);
      fd.append('exif_lat', exifLatInput.value);
      fd.append('exif_lng', exifLngInput.value);

      try {
        const res = await Api.postFormData('discovery/submit', fd);
        if (res?.success || res?.data?.success) {
          const d = res.data || res;
          form.hidden = true;
          successDiv.hidden = false;
          successDetail.textContent = `${d.photo_count || selectedFiles.length} photo(s) • ${d.site_code || 'New site'} • ${d.has_gps ? 'GPS captured' : 'No GPS'}`;
        } else {
          UI.toast(res?.message || 'Submission failed', 'error');
        }
      } catch (err) {
        UI.toast(err.message || 'Submission failed', 'error');
      }

      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="ph ph-upload-simple"></i> Submit Discovery';
    });

    // "Report Another" button
    anotherBtn?.addEventListener('click', () => {
      form.hidden = false;
      successDiv.hidden = true;
      form.reset();
      previewGrid.innerHTML = '';
      selectedFiles = [];
      exifGps = null;
      gpsStatus.hidden = true;
      gpsWarning.hidden = true;
      exifLatInput.value = '';
      exifLngInput.value = '';
      browserLatInput.value = '';
      browserLngInput.value = '';
    });

    // Recent discovery photo preview
    document.querySelectorAll('.js-disc-preview').forEach(card => {
      card.addEventListener('click', () => {
        const uploadId = card.dataset.id;
        if (uploadId && typeof openPhotoGallery === 'function') {
          openPhotoGallery([{ id: uploadId }], 0);
        }
      });
    });
  }
});
```

- [ ] **Step 4: Commit**

```bash
git add public/js/views/modules.js
git commit -m "feat(discovery): add monitoring.discovery module, remove discovery toggle from monitoring.upload"
```

---

## Task 9: Api.postFormData helper (if not existing)

**Files:**
- Modify: `public/js/core/api.js` (check if `postFormData` exists)

- [ ] **Step 1: Check if Api.postFormData exists**

Search `api.js` for `postFormData`. If it already exists, skip this task.

If it does NOT exist, add this method to the Api object:

```javascript
async postFormData(route, formData) {
  const url = this.url(route);
  try {
    const response = await fetch(url, {
      method: 'POST',
      body: formData,
      credentials: 'include',
    });
    const json = await response.json();
    if (!response.ok) {
      throw new Error(json.message || `HTTP ${response.status}`);
    }
    return json;
  } catch (err) {
    throw err;
  }
},
```

Note: Do NOT set `Content-Type` header — the browser sets it automatically with the multipart boundary for FormData.

- [ ] **Step 2: Commit (only if changed)**

```bash
git add public/js/core/api.js
git commit -m "feat(api): add postFormData helper for multipart uploads"
```

---

## Task 10: Cache bump and final wiring

**Files:**
- Modify: `public/index.html`

- [ ] **Step 1: Bump modules.js cache version**

Change `modules.js?v=50` to `modules.js?v=51`:

```html
<script src="js/views/modules.js?v=51"></script>
```

Also bump `navigation.js` if it was versioned:
```html
<script src="js/core/navigation.js?v=13"></script>
```

- [ ] **Step 2: Commit**

```bash
git add public/index.html
git commit -m "chore: bump cache versions for discovery feature"
```

---

## Task 11: Manual Integration Test

- [ ] **Step 1: Run migration**

Execute `migrations/004_media_discovery_module.sql` against the database.

- [ ] **Step 2: Login as MONITORING_TEAM**

Open the app in browser. Verify "Media Discovery" appears in the sidebar under Monitoring section.

- [ ] **Step 3: Test submission without GPS**

- Click "Media Discovery" nav item
- Select a photo from gallery (one without GPS EXIF)
- Verify amber "No GPS" warning appears
- Type a location note
- Click "Submit Discovery"
- Verify success message with site code and "No GPS"
- Verify photo appears in recent discoveries strip

- [ ] **Step 4: Test submission with camera (GPS)**

- Click "Report Another"
- Use "Take Photo" (if on mobile or has camera)
- Grant location permission when prompted
- Verify green GPS badge appears
- Submit
- Verify success with "GPS captured"

- [ ] **Step 5: Verify database state**

```sql
-- Check new site was created
SELECT * FROM sites WHERE site_code LIKE 'DISC-%' ORDER BY id DESC LIMIT 5;

-- Check free_media_record was created
SELECT fm.*, s.site_code FROM free_media_records fm
JOIN sites s ON s.id = fm.site_id
WHERE fm.source_type = 'MONITORING_DISCOVERY'
ORDER BY fm.id DESC LIMIT 5;

-- Check uploads linked correctly
SELECT id, parent_id, is_discovery_mode, comment_text, gps_latitude
FROM uploads WHERE is_discovery_mode = 1
ORDER BY id DESC LIMIT 5;
```

- [ ] **Step 6: Verify monitoring.upload no longer has discovery toggle**

Navigate to Monitoring Upload page. Confirm:
- No "Regular Visit / Free Media Discovery" chips
- Site selection is always required
- Form works normally for regular uploads

- [ ] **Step 7: Final commit**

```bash
git add -A
git commit -m "feat(discovery): media discovery feature complete — monitoring.discovery module"
```

---

## Summary of Commits

1. `feat(discovery): add constants and migration for monitoring.discovery module`
2. `feat(discovery): add GPS dedup and site code generation to SiteRepository`
3. `feat(discovery): add discovered record methods to FreeMediaRepository`
4. `feat(discovery): create MediaDiscoveryService with submit and list logic`
5. `feat(discovery): create MediaDiscoveryController with submit and myList endpoints`
6. `feat(discovery): register discovery routes in route_registry`
7. `feat(discovery): add Media Discovery nav entry`
8. `feat(discovery): add monitoring.discovery module, remove discovery toggle from monitoring.upload`
9. `feat(api): add postFormData helper for multipart uploads` (if needed)
10. `chore: bump cache versions for discovery feature`
11. `feat(discovery): media discovery feature complete — monitoring.discovery module`

---

## Phase 2 (Separate Plan — Media Planner Side)

The following spec sections are NOT covered in this plan and require their own implementation cycle:

- **Spec Section 9.2:** Fill Details and Confirm (Media Planner edits site, activates)
- **Spec Section 9.3:** Merge Two Discoveries
- **Spec Section 9.4:** Dismiss/Delete Discovery
- **Spec Section 10:** Dashboard notifications for Media Planner (pending count, stale alerts)

These enhance the existing `media.free_media_inventory` module and require their own brainstorm → plan → build cycle. The current plan delivers a fully working discovery submission flow that creates data visible in the existing Free Media list view.
