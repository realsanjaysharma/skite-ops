# Monitoring Upload Overhaul — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign the monitoring upload page with rich card-based site selection (client name, board size, creative thumbnail, GPS distance), planned/unplanned tabs, shift tracking, condition reporting, quick issue creation/resolution, and creative artwork management on site detail.

**Architecture:** Extends existing Controller → Service → Repository → Database pattern. New `MonitoringUploadService` orchestrates enriched site queries, shift management, and condition processing. New `MonitoringShiftRepository` handles shift CRUD. Existing `SiteRepository`, `SiteService`, `UploadService`, `IssueService` are extended. Frontend redesigns `monitoring.upload` module in `modules.js`.

**Tech Stack:** PHP 8+ / MySQL / XAMPP, vanilla JS SPA, PDO

**Design Spec:** `docs/superpowers/specs/2026-05-25-monitoring-upload-overhaul-design.md`

---

## Phase 1: Schema + Foundation (Tasks 1–3)

### Task 1: Migration — Schema Changes

**Files:**
- Create: `migrations/006_monitoring_upload_overhaul.sql`

- [ ] **Step 1: Create the migration file**

```sql
-- Migration 006: Monitoring Upload Overhaul schema changes
-- Date: 2026-05-25
-- Spec: docs/superpowers/specs/2026-05-25-monitoring-upload-overhaul-design.md

-- 1. ALTER sites — board dimensions, creative reference, last monitored tracking, spatial index
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

-- 2. ALTER site_monitoring_due_dates — completion tracking
ALTER TABLE site_monitoring_due_dates
  ADD COLUMN completed_at DATETIME NULL AFTER due_date;

-- 3. ALTER uploads — site condition observation
ALTER TABLE uploads
  ADD COLUMN site_condition ENUM('GOOD','DAMAGED','FADED','CREATIVE_MISSING','LIGHTS_OFF') NULL AFTER photo_label;

-- 4. CREATE monitoring_shifts table
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

- [ ] **Step 2: Run the migration**

```bash
C:\xampp\mysql\bin\mysql.exe -u root skite_ops < migrations/006_monitoring_upload_overhaul.sql
```

Expected: Query OK for all 4 statements. Verify with:
```bash
C:\xampp\mysql\bin\mysql.exe -u root -e "DESCRIBE sites" skite_ops | findstr "board_width_ft board_height_ft creative_upload_id last_monitored_at last_monitored_by_user_id"
C:\xampp\mysql\bin\mysql.exe -u root -e "DESCRIBE site_monitoring_due_dates" skite_ops | findstr "completed_at"
C:\xampp\mysql\bin\mysql.exe -u root -e "DESCRIBE uploads" skite_ops | findstr "site_condition"
C:\xampp\mysql\bin\mysql.exe -u root -e "DESCRIBE monitoring_shifts" skite_ops
```

- [ ] **Step 3: Commit**

```bash
git add migrations/006_monitoring_upload_overhaul.sql
git commit -m "feat(schema): add board dimensions, creative FK, monitoring shifts, site condition"
```

---

### Task 2: Update Schema Documentation

**Files:**
- Modify: `docs/06_schema/schema_v1_full.sql`
- Modify: `docs/06_schema/11_SCHEMA_BASELINE_v1_FINAL_WITH_DDL.md`
- Modify: `docs/06_schema/12_SCHEMA_SPECIFICATION_v1.md`

- [ ] **Step 1: Update schema_v1_full.sql — sites table**

In `docs/06_schema/schema_v1_full.sql`, find the `CREATE TABLE sites` block and add the new columns after their respective positions:

After `board_type VARCHAR(100) NULL,` add:
```sql
    board_width_ft SMALLINT UNSIGNED NULL,
    board_height_ft SMALLINT UNSIGNED NULL,
```

After `longitude DECIMAL(10,7) NULL,` add:
```sql
    creative_upload_id BIGINT UNSIGNED NULL,
    last_monitored_at DATETIME NULL,
    last_monitored_by_user_id BIGINT UNSIGNED NULL,
```

In the constraints section of sites, before the UNIQUE KEY line, add:
```sql
    CONSTRAINT fk_sites_creative_upload_id FOREIGN KEY (creative_upload_id) REFERENCES uploads(id) ON DELETE SET NULL,
    CONSTRAINT fk_sites_last_monitored_by FOREIGN KEY (last_monitored_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
```

After `KEY idx_sites_route_or_group (route_or_group)` add:
```sql
    KEY idx_sites_lat_lng (latitude, longitude)
```

- [ ] **Step 2: Update schema_v1_full.sql — site_monitoring_due_dates table**

After `due_date DATE NOT NULL,` add:
```sql
    completed_at DATETIME NULL,
```

- [ ] **Step 3: Update schema_v1_full.sql — uploads table**

After `photo_label ENUM('BEFORE_WORK','AFTER_WORK','GENERAL') NOT NULL DEFAULT 'GENERAL',` add:
```sql
    site_condition ENUM('GOOD','DAMAGED','FADED','CREATIVE_MISSING','LIGHTS_OFF') NULL,
```

- [ ] **Step 4: Add monitoring_shifts table to schema_v1_full.sql**

Add the full `CREATE TABLE monitoring_shifts` block (from Task 1 Step 1) after the `site_monitoring_due_dates` table definition and before the `campaigns` table.

- [ ] **Step 5: Update 11_SCHEMA_BASELINE_v1_FINAL_WITH_DDL.md**

Read the file, find the `sites` table section, and mirror all DDL changes from Steps 1–4 above. This file documents the exact DDL — keep it in sync with `schema_v1_full.sql`.

- [ ] **Step 6: Update 12_SCHEMA_SPECIFICATION_v1.md**

Read the file, find the `sites` table specification section, and add documentation for each new column:
- `board_width_ft` — Board width in feet. Nullable. Entered during site creation/editing.
- `board_height_ft` — Board height in feet. Nullable. Entered during site creation/editing.
- `creative_upload_id` — FK to uploads.id. The current creative artwork image for the site. ON DELETE SET NULL. Mandatory for active sites (service-layer validation).
- `last_monitored_at` — Denormalized timestamp of the last monitoring upload for this site. Updated automatically by UploadService post-upload. Avoids expensive join queries.
- `last_monitored_by_user_id` — FK to users.id. Who performed the last monitoring upload.

Add a new section for `monitoring_shifts` table documenting all columns and the UNIQUE constraint on (user_id, shift_date).

Add `site_condition` ENUM documentation to the uploads table section.

Add `completed_at` column documentation to the `site_monitoring_due_dates` section.

- [ ] **Step 7: Commit**

```bash
git add docs/06_schema/schema_v1_full.sql docs/06_schema/11_SCHEMA_BASELINE_v1_FINAL_WITH_DDL.md docs/06_schema/12_SCHEMA_SPECIFICATION_v1.md
git commit -m "docs(schema): update all schema docs for monitoring upload overhaul"
```

---

### Task 3: Constants + Backend Foundation

**Files:**
- Modify: `config/constants.php`
- Create: `app/repositories/MonitoringShiftRepository.php`

- [ ] **Step 1: Add constants**

In `config/constants.php`, add after the existing monitoring/discovery constants:

```php
// Radius in meters for nearby unmonitored site nudge on monitoring upload page
define('NEARBY_NUDGE_RADIUS_METERS', 300);

// Days since last monitoring before a site is considered stale for nudge
define('NEARBY_NUDGE_STALE_DAYS', 15);

// Site condition ENUM values (mirror uploads.site_condition)
define('SITE_CONDITIONS', ['GOOD', 'DAMAGED', 'FADED', 'CREATIVE_MISSING', 'LIGHTS_OFF']);
```

- [ ] **Step 2: Create MonitoringShiftRepository**

```php
<?php

require_once __DIR__ . '/BaseRepository.php';

/**
 * MonitoringShiftRepository
 *
 * Data access for monitoring_shifts table.
 * Tracks monitoring shift start/end for MONITORING_TEAM users.
 *
 * Schema: monitoring_shifts (id, user_id, shift_date, started_at, completed_at,
 *         planned_count, completed_count, unplanned_count, created_at, updated_at)
 * Unique: (user_id, shift_date)
 */
class MonitoringShiftRepository extends BaseRepository
{
    /**
     * Find today's shift for a user. Returns null if no shift started.
     */
    public function findByUserAndDate(int $userId, string $date): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM monitoring_shifts WHERE user_id = ? AND shift_date = ?",
            [$userId, $date]
        );
    }

    /**
     * Create a new shift record. Returns the new ID.
     */
    public function create(array $data): int
    {
        $this->execute(
            "INSERT INTO monitoring_shifts
                (user_id, shift_date, started_at, planned_count, completed_count, unplanned_count)
             VALUES (?, ?, NOW(), ?, 0, 0)",
            [
                $data['user_id'],
                $data['shift_date'],
                $data['planned_count'],
            ]
        );
        return (int) $this->lastInsertId();
    }

    /**
     * Mark shift as completed.
     */
    public function completeShift(int $shiftId, int $completedCount, int $unplannedCount): bool
    {
        return $this->execute(
            "UPDATE monitoring_shifts
             SET completed_at = NOW(), completed_count = ?, unplanned_count = ?
             WHERE id = ? AND completed_at IS NULL",
            [$completedCount, $unplannedCount, $shiftId]
        );
    }

    /**
     * Increment the completed or unplanned count by 1.
     * Called after each monitoring upload.
     *
     * @param string $column Either 'completed_count' or 'unplanned_count'
     */
    public function incrementCount(int $userId, string $date, string $column): bool
    {
        if (!in_array($column, ['completed_count', 'unplanned_count'], true)) {
            throw new InvalidArgumentException("Invalid column: $column");
        }
        return $this->execute(
            "UPDATE monitoring_shifts
             SET {$column} = {$column} + 1
             WHERE user_id = ? AND shift_date = ? AND completed_at IS NULL",
            [$userId, $date]
        );
    }
}
```

- [ ] **Step 3: Verify syntax**

```bash
C:\xampp\php\php.exe -l app/repositories/MonitoringShiftRepository.php
C:\xampp\php\php.exe -l config/constants.php
```

Expected: No syntax errors detected.

- [ ] **Step 4: Commit**

```bash
git add config/constants.php app/repositories/MonitoringShiftRepository.php
git commit -m "feat: add MonitoringShiftRepository and monitoring constants"
```

---

## Phase 2: Backend APIs (Tasks 4–8)

### Task 4: SiteRepository — Enriched Queries

**Files:**
- Modify: `app/repositories/SiteRepository.php`

- [ ] **Step 1: Add enriched site query method**

Add this method to `SiteRepository` class, after the existing `searchBySiteCode` method:

```php
    /**
     * Get enriched site data with client name, creative URL, board size.
     * Used by monitoring upload page for card display.
     *
     * Joins campaign_sites + campaigns for active client name.
     * Joins uploads for today's upload status by the given user.
     * Joins issues for open issue count.
     *
     * @param array $siteIds  If non-empty, filter to these site IDs only
     * @param int   $userId   Current user ID (for uploaded_today check)
     * @param array $filters  Optional: ['site_category' => ?, 'route_or_group' => ?]
     */
    public function findEnrichedSites(array $siteIds, int $userId, array $filters = []): array
    {
        $where = ['s.is_active = 1'];
        $params = [];

        if (!empty($siteIds)) {
            $placeholders = implode(',', array_fill(0, count($siteIds), '?'));
            $where[] = "s.id IN ($placeholders)";
            $params = array_merge($params, $siteIds);
        }

        if (!empty($filters['site_category'])) {
            $where[] = 's.site_category = ?';
            $params[] = $filters['site_category'];
        }

        if (!empty($filters['route_or_group'])) {
            $where[] = 's.route_or_group = ?';
            $params[] = $filters['route_or_group'];
        }

        $today = date('Y-m-d');
        $params[] = $userId;
        $params[] = $today;

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT s.id, s.site_code, s.location_text, s.site_category,
                       s.route_or_group, s.board_type,
                       s.board_width_ft, s.board_height_ft,
                       s.latitude, s.longitude,
                       s.creative_upload_id, s.last_monitored_at,
                       s.last_monitored_by_user_id,
                       lmu.full_name AS last_monitored_by_name,
                       -- Active client name (most recently linked campaign)
                       (SELECT c.client_name
                        FROM campaign_sites cs2
                        INNER JOIN campaigns c ON c.id = cs2.campaign_id AND c.status = 'ACTIVE'
                        WHERE cs2.site_id = s.id AND cs2.linked_to_date IS NULL
                        ORDER BY cs2.linked_from_date DESC
                        LIMIT 1
                       ) AS client_name,
                       -- Open issue count
                       (SELECT COUNT(*)
                        FROM issues i
                        WHERE i.site_id = s.id AND i.status IN ('OPEN', 'IN_PROGRESS')
                       ) AS open_issue_count,
                       -- Uploaded today by current user
                       (SELECT MAX(u.created_at)
                        FROM uploads u
                        WHERE u.parent_type = 'SITE'
                          AND u.parent_id = s.id
                          AND u.created_by_user_id = ?
                          AND DATE(u.created_at) = ?
                          AND u.is_deleted = 0
                       ) AS uploaded_today_at
                FROM sites s
                LEFT JOIN users lmu ON lmu.id = s.last_monitored_by_user_id
                WHERE {$whereClause}
                ORDER BY s.site_code ASC";

        return $this->fetchAll($sql, $params);
    }

    /**
     * Get distinct route_or_group values for a site category, with site counts.
     * Used by monitoring upload "Unplanned" tab route chips.
     */
    public function getRoutesByCategory(string $category): array
    {
        return $this->fetchAll(
            "SELECT route_or_group, COUNT(*) AS site_count
             FROM sites
             WHERE is_active = 1
               AND site_category = ?
               AND route_or_group IS NOT NULL
               AND route_or_group != ''
             GROUP BY route_or_group
             ORDER BY route_or_group ASC",
            [$category]
        );
    }

    /**
     * Search active sites by client name, location text, or site code.
     * Returns enriched card data (same shape as findEnrichedSites).
     */
    public function searchSitesEnriched(string $query, int $userId, int $limit = 20): array
    {
        $today = date('Y-m-d');
        $likeQuery = '%' . $query . '%';

        $sql = "SELECT s.id, s.site_code, s.location_text, s.site_category,
                       s.route_or_group, s.board_type,
                       s.board_width_ft, s.board_height_ft,
                       s.latitude, s.longitude,
                       s.creative_upload_id, s.last_monitored_at,
                       s.last_monitored_by_user_id,
                       lmu.full_name AS last_monitored_by_name,
                       (SELECT c.client_name
                        FROM campaign_sites cs2
                        INNER JOIN campaigns c ON c.id = cs2.campaign_id AND c.status = 'ACTIVE'
                        WHERE cs2.site_id = s.id AND cs2.linked_to_date IS NULL
                        ORDER BY cs2.linked_from_date DESC
                        LIMIT 1
                       ) AS client_name,
                       (SELECT COUNT(*)
                        FROM issues i
                        WHERE i.site_id = s.id AND i.status IN ('OPEN', 'IN_PROGRESS')
                       ) AS open_issue_count,
                       (SELECT MAX(u.created_at)
                        FROM uploads u
                        WHERE u.parent_type = 'SITE'
                          AND u.parent_id = s.id
                          AND u.created_by_user_id = ?
                          AND DATE(u.created_at) = ?
                          AND u.is_deleted = 0
                       ) AS uploaded_today_at
                FROM sites s
                LEFT JOIN users lmu ON lmu.id = s.last_monitored_by_user_id
                LEFT JOIN campaign_sites cs ON cs.site_id = s.id AND cs.linked_to_date IS NULL
                LEFT JOIN campaigns c ON c.id = cs.campaign_id AND c.status = 'ACTIVE'
                WHERE s.is_active = 1
                  AND (s.site_code LIKE ?
                       OR s.location_text LIKE ?
                       OR c.client_name LIKE ?)
                GROUP BY s.id
                ORDER BY s.site_code ASC
                LIMIT {$limit}";

        return $this->fetchAll($sql, [$userId, $today, $likeQuery, $likeQuery, $likeQuery]);
    }
```

- [ ] **Step 2: Update SiteRepository::create() and update() for new columns**

Find the existing `create` method and update the INSERT query to include the new columns. Replace the entire `create` method:

```php
    public function create(array $data): int {
        $query = "INSERT INTO sites (
            site_code, location_text, site_category, green_belt_id, route_or_group,
            ownership_name, board_type, board_width_ft, board_height_ft,
            lighting_type, latitude, longitude, creative_upload_id, is_active, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $this->execute($query, [
            $data['site_code'],
            $data['location_text'] ?? null,
            $data['site_category'],
            $data['green_belt_id'] ?? null,
            $data['route_or_group'] ?? null,
            $data['ownership_name'] ?? null,
            $data['board_type'] ?? null,
            $data['board_width_ft'] ?? null,
            $data['board_height_ft'] ?? null,
            $data['lighting_type'],
            $data['latitude'] ?? null,
            $data['longitude'] ?? null,
            $data['creative_upload_id'] ?? null,
            $data['is_active'] ?? 1
        ]);

        return (int) $this->lastInsertId();
    }
```

Replace the entire `update` method:

```php
    public function update(int $id, array $data): bool {
        $query = "UPDATE sites SET
            location_text = ?,
            site_category = ?,
            green_belt_id = ?,
            route_or_group = ?,
            ownership_name = ?,
            board_type = ?,
            board_width_ft = ?,
            board_height_ft = ?,
            lighting_type = ?,
            latitude = ?,
            longitude = ?,
            creative_upload_id = ?,
            is_active = ?,
            updated_at = NOW()
        WHERE id = ?";

        return $this->execute($query, [
            $data['location_text'] ?? null,
            $data['site_category'],
            $data['green_belt_id'] ?? null,
            $data['route_or_group'] ?? null,
            $data['ownership_name'] ?? null,
            $data['board_type'] ?? null,
            $data['board_width_ft'] ?? null,
            $data['board_height_ft'] ?? null,
            $data['lighting_type'],
            $data['latitude'] ?? null,
            $data['longitude'] ?? null,
            $data['creative_upload_id'] ?? null,
            $data['is_active'] ?? 1,
            $id
        ]);
    }
```

Also add a helper to update just the monitoring tracking fields:

```php
    /**
     * Update last_monitored_at and last_monitored_by_user_id after a monitoring upload.
     */
    public function updateLastMonitored(int $siteId, int $userId): bool
    {
        return $this->execute(
            "UPDATE sites SET last_monitored_at = NOW(), last_monitored_by_user_id = ? WHERE id = ?",
            [$userId, $siteId]
        );
    }

    /**
     * Update just the creative_upload_id for a site.
     */
    public function updateCreative(int $siteId, int $uploadId): bool
    {
        return $this->execute(
            "UPDATE sites SET creative_upload_id = ?, updated_at = NOW() WHERE id = ?",
            [$uploadId, $siteId]
        );
    }
```

- [ ] **Step 3: Verify syntax**

```bash
C:\xampp\php\php.exe -l app/repositories/SiteRepository.php
```

- [ ] **Step 4: Commit**

```bash
git add app/repositories/SiteRepository.php
git commit -m "feat(sites): add enriched queries, board dimensions, creative FK, last-monitored tracking"
```

---

### Task 5: MonitoringPlanRepository — Completion Tracking

**Files:**
- Modify: `app/repositories/MonitoringPlanRepository.php`

- [ ] **Step 1: Add completion tracking methods**

Add these methods to `MonitoringPlanRepository`:

```php
    /**
     * Mark a due date as completed for a site on a given date.
     * Called after a monitoring upload is submitted.
     */
    public function markCompleted(int $siteId, string $dueDate): bool
    {
        return $this->execute(
            "UPDATE site_monitoring_due_dates
             SET completed_at = NOW()
             WHERE site_id = ? AND due_date = ? AND completed_at IS NULL",
            [$siteId, $dueDate]
        );
    }

    /**
     * Get today's planned site IDs for use in determining planned vs unplanned uploads.
     */
    public function getTodaysPlannedSiteIds(): array
    {
        $today = date('Y-m-d');
        $rows = $this->fetchAll(
            "SELECT DISTINCT site_id FROM site_monitoring_due_dates WHERE due_date = ?",
            [$today]
        );
        return array_column($rows, 'site_id');
    }

    /**
     * Count planned and completed for today (for shift summary).
     */
    public function getTodaysPlanSummary(): array
    {
        $today = date('Y-m-d');
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS planned_count,
                    SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) AS completed_count
             FROM site_monitoring_due_dates
             WHERE due_date = ?",
            [$today]
        );
        return [
            'planned_count' => (int) ($row['planned_count'] ?? 0),
            'completed_count' => (int) ($row['completed_count'] ?? 0),
        ];
    }

    /**
     * Get plan list with completion status for the monitoring plan page.
     * Adds completed_at to the existing plan data for missed-site filtering.
     */
    public function getPlanListWithCompletion(array $filters, string $month): array
    {
        $query = "SELECT s.id as site_id, s.site_code, s.location_text, s.site_category,
                         s.lighting_type, s.route_or_group,
                         smdd.due_date, smdd.completed_at,
                         COUNT(smdd.id) OVER (PARTITION BY s.id) as total_due_dates
                  FROM sites s
                  INNER JOIN site_monitoring_due_dates smdd
                    ON s.id = smdd.site_id AND smdd.plan_month = ?
                  WHERE s.is_active = 1";

        $params = [$month];

        if (!empty($filters['site_category'])) {
            $query .= " AND s.site_category = ?";
            $params[] = $filters['site_category'];
        }

        if (isset($filters['completion_status'])) {
            $today = date('Y-m-d');
            if ($filters['completion_status'] === 'completed') {
                $query .= " AND smdd.completed_at IS NOT NULL";
            } elseif ($filters['completion_status'] === 'missed') {
                $query .= " AND smdd.completed_at IS NULL AND smdd.due_date < ?";
                $params[] = $today;
            }
        }

        $query .= " ORDER BY smdd.due_date ASC, s.site_code ASC";

        return $this->fetchAll($query, $params);
    }
```

- [ ] **Step 2: Verify syntax**

```bash
C:\xampp\php\php.exe -l app/repositories/MonitoringPlanRepository.php
```

- [ ] **Step 3: Commit**

```bash
git add app/repositories/MonitoringPlanRepository.php
git commit -m "feat(monitoring-plan): add completion tracking and missed-site queries"
```

---

### Task 6: MonitoringUploadService — Orchestration

**Files:**
- Create: `app/services/MonitoringUploadService.php`

- [ ] **Step 1: Create the service**

```php
<?php

require_once __DIR__ . '/../repositories/SiteRepository.php';
require_once __DIR__ . '/../repositories/MonitoringPlanRepository.php';
require_once __DIR__ . '/../repositories/MonitoringShiftRepository.php';
require_once __DIR__ . '/../repositories/IssueRepository.php';
require_once __DIR__ . '/AuditService.php';
require_once __DIR__ . '/UploadService.php';

/**
 * MonitoringUploadService
 *
 * Orchestration for the monitoring upload page:
 * - Enriched site card data (planned + unplanned + search)
 * - Shift lifecycle (start / complete)
 * - Post-upload side effects (last_monitored, due_date completion, shift counts)
 * - Quick issue creation from condition tags
 * - Issue resolution from field with mandatory photo proof
 *
 * Does NOT own the upload transaction — UploadService manages its own internally.
 */
class MonitoringUploadService
{
    private SiteRepository $siteRepo;
    private MonitoringPlanRepository $planRepo;
    private MonitoringShiftRepository $shiftRepo;
    private IssueRepository $issueRepo;
    private AuditService $auditService;

    public function __construct()
    {
        $this->siteRepo = new SiteRepository();
        $this->planRepo = new MonitoringPlanRepository();
        $this->shiftRepo = new MonitoringShiftRepository();
        $this->issueRepo = new IssueRepository();
        $this->auditService = new AuditService();
    }

    // ─── Enriched Site Queries ─────────────────────────────────────────

    /**
     * Get today's planned sites with enriched card data.
     */
    public function getPlannedSites(int $userId): array
    {
        $today = date('Y-m-d');
        $month = date('Y-m');

        $allSites = $this->planRepo->getPlanList([], $month);
        $plannedSiteIds = [];

        foreach ($allSites as $site) {
            $dueDates = !empty($site['due_dates_list'])
                ? explode(',', $site['due_dates_list'])
                : [];
            if (in_array($today, $dueDates, true)) {
                $plannedSiteIds[] = (int) $site['site_id'];
            }
        }

        if (empty($plannedSiteIds)) {
            return ['items' => [], 'planned_count' => 0, 'completed_count' => 0];
        }

        $enriched = $this->siteRepo->findEnrichedSites($plannedSiteIds, $userId);
        $items = $this->formatSiteCards($enriched);

        $completedCount = 0;
        foreach ($items as $item) {
            if ($item['uploaded_today']) $completedCount++;
        }

        return [
            'items' => $items,
            'planned_count' => count($items),
            'completed_count' => $completedCount,
        ];
    }

    /**
     * Get distinct routes for a site category (for route chips).
     */
    public function getRoutesByCategory(string $category): array
    {
        $validCategories = ['GREEN_BELT', 'CITY', 'HIGHWAY'];
        if (!in_array($category, $validCategories, true)) {
            throw new InvalidArgumentException("Invalid site_category: $category");
        }
        return $this->siteRepo->getRoutesByCategory($category);
    }

    /**
     * Get sites for unplanned browsing by category + route.
     */
    public function browseSites(string $category, string $route, int $userId): array
    {
        $filters = [
            'site_category' => $category,
            'route_or_group' => $route,
        ];
        $enriched = $this->siteRepo->findEnrichedSites([], $userId, $filters);
        $items = $this->formatSiteCards($enriched);

        $completedCount = 0;
        foreach ($items as $item) {
            if ($item['uploaded_today']) $completedCount++;
        }

        return [
            'items' => $items,
            'total' => count($items),
            'completed_count' => $completedCount,
        ];
    }

    /**
     * Search sites by client/location/code with enriched data.
     */
    public function searchSites(string $query, int $userId): array
    {
        $enriched = $this->siteRepo->searchSitesEnriched($query, $userId);
        return ['items' => $this->formatSiteCards($enriched)];
    }

    // ─── Shift Lifecycle ───────────────────────────────────────────────

    /**
     * Start a monitoring shift for today.
     */
    public function startShift(int $userId): array
    {
        $today = date('Y-m-d');
        $existing = $this->shiftRepo->findByUserAndDate($userId, $today);

        if ($existing) {
            return $existing;
        }

        $summary = $this->planRepo->getTodaysPlanSummary();

        $shiftId = $this->shiftRepo->create([
            'user_id' => $userId,
            'shift_date' => $today,
            'planned_count' => $summary['planned_count'],
        ]);

        $this->auditService->logAction(
            $userId,
            'MONITORING_SHIFT_STARTED',
            'monitoring_shifts',
            $shiftId,
            null,
            ['shift_date' => $today, 'planned_count' => $summary['planned_count']]
        );

        return $this->shiftRepo->findByUserAndDate($userId, $today);
    }

    /**
     * Complete today's monitoring shift.
     */
    public function completeShift(int $userId): array
    {
        $today = date('Y-m-d');
        $shift = $this->shiftRepo->findByUserAndDate($userId, $today);

        if (!$shift) {
            throw new DomainException("No shift started for today.");
        }

        if ($shift['completed_at'] !== null) {
            return $shift;
        }

        $this->shiftRepo->completeShift(
            (int) $shift['id'],
            (int) $shift['completed_count'],
            (int) $shift['unplanned_count']
        );

        $updated = $this->shiftRepo->findByUserAndDate($userId, $today);

        $this->auditService->logAction(
            $userId,
            'MONITORING_SHIFT_COMPLETED',
            'monitoring_shifts',
            (int) $shift['id'],
            ['completed_at' => null],
            ['completed_at' => $updated['completed_at'],
             'completed_count' => $updated['completed_count'],
             'unplanned_count' => $updated['unplanned_count']]
        );

        return $updated;
    }

    /**
     * Get today's shift status (or null if not started).
     */
    public function getTodayShift(int $userId): ?array
    {
        return $this->shiftRepo->findByUserAndDate($userId, date('Y-m-d'));
    }

    // ─── Post-Upload Side Effects ──────────────────────────────────────

    /**
     * Called after a successful monitoring upload.
     * Updates last_monitored, marks due date completed, increments shift count.
     *
     * This runs AFTER UploadService commits its own transaction.
     */
    public function handlePostUploadSideEffects(int $siteId, int $userId): void
    {
        $today = date('Y-m-d');

        // 1. Update site's last_monitored tracking
        $this->siteRepo->updateLastMonitored($siteId, $userId);

        // 2. Mark today's due date completed (if exists)
        $this->planRepo->markCompleted($siteId, $today);

        // 3. Increment shift counter (if shift active)
        $plannedSiteIds = $this->planRepo->getTodaysPlannedSiteIds();
        $isPlanned = in_array($siteId, $plannedSiteIds, true);
        $column = $isPlanned ? 'completed_count' : 'unplanned_count';
        $this->shiftRepo->incrementCount($userId, $today, $column);
    }

    // ─── Quick Issue Report ────────────────────────────────────────────

    /**
     * Create an issue from a monitoring condition observation.
     * Used when site_condition is not GOOD (DAMAGED, FADED, etc.).
     *
     * @param int    $siteId    The site with the issue
     * @param string $condition The site_condition ENUM value
     * @param string $comment   Optional description
     * @param int    $userId    The monitoring person reporting it
     * @return array The created issue record
     */
    public function reportConditionIssue(int $siteId, string $condition, ?string $comment, int $userId): array
    {
        $validConditions = ['DAMAGED', 'FADED', 'CREATIVE_MISSING', 'LIGHTS_OFF'];
        if (!in_array($condition, $validConditions, true)) {
            throw new InvalidArgumentException("Invalid condition for issue: $condition");
        }

        $site = $this->siteRepo->findById($siteId);
        if (!$site) {
            throw new InvalidArgumentException("Site not found.");
        }

        $conditionLabel = str_replace('_', ' ', ucfirst(strtolower($condition)));
        $title = "Site Condition: {$conditionLabel} — " . ($site['location_text'] ?? $site['site_code']);

        $issueData = [
            'source_type' => 'MONITORING_CONDITION',
            'site_id' => $siteId,
            'title' => $title,
            'description' => $comment,
            'priority' => in_array($condition, ['DAMAGED', 'CREATIVE_MISSING'], true) ? 'HIGH' : 'MEDIUM',
            'status' => 'OPEN',
            'raised_by_user_id' => $userId,
        ];

        $issueId = $this->issueRepo->create($issueData);

        $this->auditService->logAction(
            $userId,
            'MONITORING_ISSUE_REPORTED',
            'issues',
            $issueId,
            null,
            $issueData
        );

        return $this->issueRepo->findById($issueId);
    }

    // ─── Issue Resolution from Field ───────────────────────────────────

    /**
     * Resolve an issue from the field with mandatory photo proof.
     * The photo upload is handled separately (by UploadService), this just
     * transitions the issue status.
     *
     * MONITORING_TEAM can resolve site issues — this extends the existing
     * closeIssue() which is OPS_MANAGER only. This is a field-resolution
     * with required photo proof, audited separately.
     */
    public function resolveIssueFromField(int $issueId, ?string $comment, int $userId): array
    {
        $issue = $this->issueRepo->findById($issueId);
        if (!$issue) {
            throw new InvalidArgumentException("Issue not found.");
        }

        if (!in_array($issue['status'], ['OPEN', 'IN_PROGRESS'], true)) {
            throw new DomainException("Only OPEN or IN_PROGRESS issues can be resolved.");
        }

        $this->issueRepo->update([
            'id' => $issueId,
            'status' => 'CLOSED',
            'closed_by_user_id' => $userId,
            'closed_at' => date('Y-m-d H:i:s'),
        ]);

        $this->auditService->logAction(
            $userId,
            'ISSUE_RESOLVED_FROM_FIELD',
            'issues',
            $issueId,
            ['status' => $issue['status']],
            ['status' => 'CLOSED', 'closed_by_user_id' => $userId, 'resolution_note' => $comment]
        );

        return $this->issueRepo->findById($issueId);
    }

    // ─── Helpers ───────────────────────────────────────────────────────

    /**
     * Format raw enriched site rows into frontend card data.
     */
    private function formatSiteCards(array $rows): array
    {
        return array_map(static function (array $row): array {
            $creativeUrl = null;
            if (!empty($row['creative_upload_id'])) {
                $creativeUrl = '../index.php?route=upload/serve&id=' . $row['creative_upload_id'];
            }

            return [
                'id' => (int) $row['id'],
                'site_code' => $row['site_code'],
                'location_text' => $row['location_text'],
                'site_category' => $row['site_category'],
                'route_or_group' => $row['route_or_group'],
                'board_width_ft' => $row['board_width_ft'] ? (int) $row['board_width_ft'] : null,
                'board_height_ft' => $row['board_height_ft'] ? (int) $row['board_height_ft'] : null,
                'latitude' => $row['latitude'] ? (float) $row['latitude'] : null,
                'longitude' => $row['longitude'] ? (float) $row['longitude'] : null,
                'client_name' => $row['client_name'] ?? null,
                'creative_url' => $creativeUrl,
                'last_monitored_at' => $row['last_monitored_at'],
                'last_monitored_by' => $row['last_monitored_by_name'] ?? null,
                'uploaded_today' => !empty($row['uploaded_today_at']),
                'uploaded_today_at' => $row['uploaded_today_at'],
                'open_issue_count' => (int) ($row['open_issue_count'] ?? 0),
            ];
        }, $rows);
    }
}
```

- [ ] **Step 2: Verify syntax**

```bash
C:\xampp\php\php.exe -l app/services/MonitoringUploadService.php
```

- [ ] **Step 3: Commit**

```bash
git add app/services/MonitoringUploadService.php
git commit -m "feat: add MonitoringUploadService — enriched sites, shifts, conditions, issue resolution"
```

---

### Task 7: MonitoringUploadController — New Endpoints

**Files:**
- Modify: `app/controllers/MonitoringUploadController.php`

- [ ] **Step 1: Rewrite the controller with all new endpoints**

Replace the entire contents of `app/controllers/MonitoringUploadController.php`:

```php
<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/MonitoringUploadService.php';
require_once __DIR__ . '/../services/UploadService.php';
require_once __DIR__ . '/../repositories/SiteRepository.php';

/**
 * MonitoringUploadController
 *
 * Handles monitoring upload page endpoints:
 * - Landing (recent uploads)
 * - Enriched site queries (planned, browse, search)
 * - Shift lifecycle (start, complete, status)
 * - Quick issue report + issue resolution from field
 * - Creative upload for site detail page
 */
class MonitoringUploadController extends BaseController
{
    private UploadService $uploadService;
    private MonitoringUploadService $monService;

    public function __construct()
    {
        $this->uploadService = new UploadService();
        $this->monService = new MonitoringUploadService();
    }

    /**
     * GET monitoring/upload — recent uploads for the monitoring landing page.
     */
    public function index(): void
    {
        if (!$this->requireMethod('GET')) return;

        $actor = $this->getActor();
        if ($actor['role_key'] !== 'MONITORING_TEAM') {
            Response::error('Access denied', 403);
            return;
        }

        try {
            $filters = [
                'date_from' => $_GET['date_from'] ?? null,
                'date_to'   => $_GET['date_to'] ?? null,
            ];
            $page  = max(1, (int) ($_GET['page'] ?? 1));
            $limit = max(1, min(100, (int) ($_GET['limit'] ?? 50)));

            $result = $this->uploadService->listCreatorUploads($actor['user_id'], $filters, $page, $limit);
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET monitoring/site-search?q=... — search by client/location/code.
     */
    public function siteSearch(): void
    {
        if (!$this->requireMethod('GET')) return;

        $actor = $this->getActor();
        if (!in_array($actor['role_key'], ['MONITORING_TEAM', 'OPS_MANAGER'], true)) {
            Response::error('Access denied', 403);
            return;
        }

        $query = trim($_GET['q'] ?? '');
        if (strlen($query) < 1) {
            Response::success(['items' => []]);
            return;
        }

        try {
            $result = $this->monService->searchSites($query, $actor['user_id']);
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET monitoring/browse-routes?category=HIGHWAY — distinct routes with counts.
     */
    public function browseRoutes(): void
    {
        if (!$this->requireMethod('GET')) return;

        $actor = $this->getActor();
        if ($actor['role_key'] !== 'MONITORING_TEAM') {
            Response::error('Access denied', 403);
            return;
        }

        $category = strtoupper(trim($_GET['category'] ?? ''));
        if (empty($category)) {
            Response::error('category is required', 400);
            return;
        }

        try {
            $routes = $this->monService->getRoutesByCategory($category);
            Response::success(['routes' => $routes]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET monitoring/browse-sites?category=HIGHWAY&route=NH-24 — filtered site list.
     */
    public function browseSites(): void
    {
        if (!$this->requireMethod('GET')) return;

        $actor = $this->getActor();
        if ($actor['role_key'] !== 'MONITORING_TEAM') {
            Response::error('Access denied', 403);
            return;
        }

        $category = strtoupper(trim($_GET['category'] ?? ''));
        $route = trim($_GET['route'] ?? '');
        if (empty($category) || empty($route)) {
            Response::error('category and route are required', 400);
            return;
        }

        try {
            $result = $this->monService->browseSites($category, $route, $actor['user_id']);
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST monitoring/start-shift — begin today's monitoring shift.
     */
    public function startShift(): void
    {
        if (!$this->requireMethod('POST')) return;

        $actor = $this->getActor();
        if ($actor['role_key'] !== 'MONITORING_TEAM') {
            Response::error('Access denied', 403);
            return;
        }

        try {
            $shift = $this->monService->startShift($actor['user_id']);
            Response::success($shift);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST monitoring/complete-shift — end today's monitoring shift.
     */
    public function completeShift(): void
    {
        if (!$this->requireMethod('POST')) return;

        $actor = $this->getActor();
        if ($actor['role_key'] !== 'MONITORING_TEAM') {
            Response::error('Access denied', 403);
            return;
        }

        try {
            $shift = $this->monService->completeShift($actor['user_id']);
            Response::success($shift);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST monitoring/resolve-issue — resolve issue from field with photo proof.
     * Photos must be uploaded separately via upload/create first.
     * Body: { issue_id, comment_text? }
     */
    public function resolveIssue(): void
    {
        if (!$this->requireMethod('POST')) return;

        $actor = $this->getActor();
        if ($actor['role_key'] !== 'MONITORING_TEAM') {
            Response::error('Access denied', 403);
            return;
        }

        $input = $this->getInput();
        $issueId = (int) ($input['issue_id'] ?? 0);
        if (!$issueId) {
            Response::error('issue_id is required', 400);
            return;
        }

        try {
            $result = $this->monService->resolveIssueFromField(
                $issueId,
                $input['comment_text'] ?? null,
                $actor['user_id']
            );
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST site/upload-creative — upload creative artwork for a site.
     * Accepts multipart form: site_id + single image file.
     * Updates sites.creative_upload_id to point to the new upload.
     *
     * Access: CLIENT_SERVICE, OPS_MANAGER, MEDIA_PLANNING
     */
    public function uploadCreative(): void
    {
        if (!$this->requireMethod('POST')) return;

        $actor = $this->getActor();
        $allowedRoles = ['CLIENT_SERVICING', 'OPS_MANAGER', 'MEDIA_PLANNING'];
        if (!in_array($actor['role_key'], $allowedRoles, true)) {
            Response::error('Access denied', 403);
            return;
        }

        $siteId = (int) ($_POST['site_id'] ?? 0);
        if (!$siteId) {
            Response::error('site_id is required', 400);
            return;
        }

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Response::error('A single image file is required', 400);
            return;
        }

        try {
            $siteRepo = new SiteRepository();
            $site = $siteRepo->findById($siteId);
            if (!$site) {
                Response::error('Site not found', 404);
                return;
            }

            // Upload the creative as a regular SITE upload
            $data = [
                'parent_type' => 'SITE',
                'parent_id' => $siteId,
                'upload_type' => 'WORK',
                'photo_label' => 'GENERAL',
                'comment_text' => 'Creative artwork',
            ];

            $result = $this->uploadService->createUploadsForSurface(
                'MONITORING',
                $data,
                ['file' => $_FILES['file']],
                $actor['user_id']
            );

            // Update the site's creative_upload_id
            $uploadId = $result['created_uploads'][0]['id'] ?? null;
            if ($uploadId) {
                $siteRepo->updateCreative($siteId, $uploadId);
            }

            Response::success([
                'site_id' => $siteId,
                'creative_upload_id' => $uploadId,
                'creative_url' => $uploadId ? "../index.php?route=upload/serve&id={$uploadId}" : null,
            ]);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
```

- [ ] **Step 2: Verify syntax**

```bash
C:\xampp\php\php.exe -l app/controllers/MonitoringUploadController.php
```

- [ ] **Step 3: Commit**

```bash
git add app/controllers/MonitoringUploadController.php
git commit -m "feat(monitoring-controller): add browse, shift, issue, creative endpoints"
```

---

### Task 8: Route Registry + Post-Upload Hook

**Files:**
- Modify: `config/route_registry.php`
- Modify: `app/services/UploadService.php`

- [ ] **Step 1: Add new routes to route_registry.php**

Find the monitoring upload routes section (around line 666) and add after the existing `monitoring/site-search` entry:

```php
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
    'monitoring/resolve-issue' => [
        'controller' => 'MonitoringUploadController',
        'method'     => 'resolveIssue',
        'module_key' => 'monitoring.upload',
        'capability' => 'upload',
    ],
```

Add the creative upload route in the SITE MASTER section (after `site/update`):

```php
    'site/upload-creative' => [
        'controller' => 'MonitoringUploadController',
        'method'     => 'uploadCreative',
        'module_key' => 'advertisement.site_master',
        'capability' => 'manage',
    ],
```

- [ ] **Step 2: Add post-upload side effects hook to UploadService**

In `app/services/UploadService.php`, find the `createUploadsForSurface` method. After the existing discovery record logic (around line 159: `if ($normalized['parent_type'] === 'SITE' && $normalized['is_discovery_mode']...`) and BEFORE the `$this->uploadRepository->commit();` line, add:

```php
            // Post-upload side effects for monitoring surface
            if ($surface === 'MONITORING' && !$normalized['is_discovery_mode']) {
                // Defer side effects to after commit — store for caller
                $this->_pendingMonitoringSideEffect = [
                    'site_id' => $normalized['parent_id'],
                    'user_id' => $actorUserId,
                ];
            }
```

Then, AFTER the `$this->uploadRepository->commit();` line and BEFORE the `return` statement, add:

```php
            // Execute deferred monitoring side effects after commit
            if (isset($this->_pendingMonitoringSideEffect)) {
                try {
                    require_once __DIR__ . '/MonitoringUploadService.php';
                    $monService = new MonitoringUploadService();
                    $monService->handlePostUploadSideEffects(
                        $this->_pendingMonitoringSideEffect['site_id'],
                        $this->_pendingMonitoringSideEffect['user_id']
                    );
                } catch (Throwable $e) {
                    // Side effects are non-critical — upload already committed.
                    // Log silently but don't fail the upload.
                    error_log("Monitoring post-upload side effect error: " . $e->getMessage());
                }
                unset($this->_pendingMonitoringSideEffect);
            }
```

Also add the `site_condition` field to the `normalizeCreateData` method. Find the return array in `normalizeCreateData` (around line 385) and add `'site_condition'` to the normalized data:

After `'gps_longitude' => ...` line, add:
```php
            'site_condition' => $this->normalizeOptionalEnum(
                $data['site_condition'] ?? null,
                ['GOOD', 'DAMAGED', 'FADED', 'CREATIVE_MISSING', 'LIGHTS_OFF']
            ),
```

Add the helper method to UploadService (after `normalizeOptionalDecimal`):

```php
    private function normalizeOptionalEnum(?string $value, array $allowed): ?string
    {
        if ($value === null || $value === '') return null;
        $value = strtoupper(trim($value));
        return in_array($value, $allowed, true) ? $value : null;
    }
```

Then update the INSERT in `UploadRepository` to include `site_condition`. Find the `create` method in `app/repositories/UploadRepository.php` and add `site_condition` to the INSERT columns and values.

- [ ] **Step 3: Update UploadRepository::create() for site_condition**

In `app/repositories/UploadRepository.php`, find the `create` method's INSERT query. Add `site_condition` to the column list and the corresponding `$data['site_condition'] ?? null` to the params array.

- [ ] **Step 4: Run syntax checks**

```bash
C:\xampp\php\php.exe -l config/route_registry.php
C:\xampp\php\php.exe -l app/services/UploadService.php
C:\xampp\php\php.exe -l app/repositories/UploadRepository.php
```

- [ ] **Step 5: Run route validation**

```bash
C:\xampp\php\php.exe tests/test_frontend_route_map.php
```

- [ ] **Step 6: Commit**

```bash
git add config/route_registry.php app/services/UploadService.php app/repositories/UploadRepository.php
git commit -m "feat: add monitoring routes, post-upload side effects, site_condition support"
```

---

## Phase 3: Site Detail — Creative + Board Size (Task 9)

### Task 9: SiteService + Frontend — Board Size + Creative Upload

**Files:**
- Modify: `app/services/SiteService.php`
- Modify: `public/js/views/modules.js` (site detail section)

- [ ] **Step 1: Update SiteService validation for creative + board size**

In `app/services/SiteService.php`, update the `updateSite` method. After the existing lighting_type validation, add:

```php
        // Validate board dimensions
        if (isset($data['board_width_ft'])) {
            $data['board_width_ft'] = (int) $data['board_width_ft'];
            if ($data['board_width_ft'] < 0 || $data['board_width_ft'] > 9999) {
                throw new Exception("board_width_ft must be between 0 and 9999");
            }
        }
        if (isset($data['board_height_ft'])) {
            $data['board_height_ft'] = (int) $data['board_height_ft'];
            if ($data['board_height_ft'] < 0 || $data['board_height_ft'] > 9999) {
                throw new Exception("board_height_ft must be between 0 and 9999");
            }
        }

        // Creative mandatory for active sites
        $isActive = (int) ($data['is_active'] ?? $existing['is_active']);
        if ($isActive === 1) {
            $creativeId = $data['creative_upload_id'] ?? $existing['creative_upload_id'] ?? null;
            if (empty($creativeId)) {
                throw new Exception("Active sites must have a creative image uploaded.");
            }
        }
```

Add the same creative validation to `createSite`, before the `$this->repo->create($data)` call:

```php
        // Creative mandatory for active sites
        $isActive = (int) ($data['is_active'] ?? 1);
        if ($isActive === 1 && empty($data['creative_upload_id'])) {
            throw new Exception("Active sites must have a creative image uploaded.");
        }
```

- [ ] **Step 2: Update site detail frontend to include board size fields + creative section**

In `public/js/views/modules.js`, find the site detail/edit view (look for `Views.register('advertisement.site_master'` or the site edit form). Add:

1. Board dimensions fields (two side-by-side number inputs for width/height)
2. Creative upload section (thumbnail preview + upload/replace button)

The exact code depends on the current site detail view structure. The implementor should:
- Read the current site detail/edit form
- Add `board_width_ft` and `board_height_ft` number inputs after `board_type`
- Add a creative image section after the coordinate fields
- Wire creative upload to POST `site/upload-creative` with FormData
- Show current creative thumbnail from `creative_url` (if creative_upload_id exists)
- Wire the Replace button to upload a new file and refresh

- [ ] **Step 3: Verify syntax**

```bash
C:\xampp\php\php.exe -l app/services/SiteService.php
node --check public/js/views/modules.js
```

- [ ] **Step 4: Commit**

```bash
git add app/services/SiteService.php public/js/views/modules.js
git commit -m "feat(site-detail): add board dimensions, creative upload section"
```

---

## Phase 4: Frontend — Monitoring Upload Page Redesign (Tasks 10–12)

### Task 10: Enriched upload/targets Endpoint

**Files:**
- Modify: `app/controllers/UploadController.php`

- [ ] **Step 1: Update the MONITORING_TEAM branch of targets()**

In `app/controllers/UploadController.php`, find the `targets()` method, line ~214 where `if ($roleKey === 'MONITORING_TEAM')` begins. Replace the entire MONITORING_TEAM block (from line 214 to line 238) with:

```php
            if ($roleKey === 'MONITORING_TEAM') {
                $monService = new MonitoringUploadService();
                $result = $monService->getPlannedSites($actor['user_id']);

                // Also include shift status
                $shift = $monService->getTodayShift($actor['user_id']);

                Response::success([
                    'items' => $result['items'],
                    'planned_count' => $result['planned_count'],
                    'completed_count' => $result['completed_count'],
                    'shift' => $shift,
                    'pagination' => ['page' => 1, 'limit' => count($result['items']), 'total' => count($result['items'])],
                ]);
                return;
            }
```

Add the require at the top of the file (after existing requires):

```php
require_once __DIR__ . '/../services/MonitoringUploadService.php';
```

- [ ] **Step 2: Verify syntax**

```bash
C:\xampp\php\php.exe -l app/controllers/UploadController.php
```

- [ ] **Step 3: Commit**

```bash
git add app/controllers/UploadController.php
git commit -m "feat: enrich upload/targets with client, creative, shift data for monitoring"
```

---

### Task 11: Frontend — Monitoring Upload Page Redesign

**Files:**
- Modify: `public/js/views/modules.js`
- Modify: `public/css/style.css`

This is the largest task. The implementor should completely replace the `Views.register('monitoring.upload', {...})` block (lines 1400–1770 approximately) with the new design.

- [ ] **Step 1: Add CSS for monitoring upload cards**

In `public/css/style.css`, add at the end:

```css
/* ── Monitoring Upload — Site Cards ────────────────── */
.mon-tabs { display:flex; gap:0; margin-bottom:16px; border-radius:var(--radius,8px); overflow:hidden; border:1px solid var(--line-strong,#b8c4d2); }
.mon-tab { flex:1; padding:12px; text-align:center; font-weight:600; cursor:pointer; background:var(--surface,#fff); color:var(--ink-500); transition:background .15s, color .15s; border:none; font:inherit; }
.mon-tab.active { background:var(--primary,#2563eb); color:#fff; }

.mon-summary { display:flex; gap:12px; flex-wrap:wrap; align-items:center; padding:10px 14px; background:var(--surface-alt,#f4f6fb); border-radius:var(--radius,8px); margin-bottom:16px; font-size:0.88rem; color:var(--ink-600); }
.mon-summary span { white-space:nowrap; }

.mon-shift-bar { padding:12px 14px; border-radius:var(--radius,8px); margin-bottom:16px; display:flex; align-items:center; gap:12px; }
.mon-shift-bar.idle { background:var(--surface-alt,#f4f6fb); }
.mon-shift-bar.active { background:#ecfdf5; border:1px solid #86efac; }
.mon-shift-bar.done { background:#f0fdf4; border:1px solid #4ade80; }

.mon-site-list { max-height:380px; overflow-y:auto; display:flex; flex-direction:column; gap:8px; margin-bottom:12px; }
.mon-site-card { display:flex; align-items:center; gap:12px; padding:10px 12px; border:2px solid var(--line,#e2e8f0); border-radius:var(--radius,8px); cursor:pointer; transition:border-color .15s, background .15s; }
.mon-site-card:hover { border-color:var(--primary,#2563eb); }
.mon-site-card.selected { border-color:var(--primary,#2563eb); background:#eff6ff; }
.mon-site-card.done { border-color:#86efac; background:#f0fdf4; opacity:0.85; }

.mon-site-thumb { width:56px; height:56px; border-radius:8px; object-fit:cover; background:var(--surface-alt,#f4f6fb); flex-shrink:0; }
.mon-site-thumb-placeholder { width:56px; height:56px; border-radius:8px; background:var(--surface-alt,#f4f6fb); display:flex; align-items:center; justify-content:center; flex-shrink:0; color:var(--ink-400); font-size:1.4rem; }

.mon-site-info { flex:1; min-width:0; }
.mon-site-primary { font-weight:600; color:var(--ink-900); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.mon-site-location { font-size:0.85rem; color:var(--ink-600); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.mon-site-meta { font-size:0.78rem; color:var(--ink-400); margin-top:2px; }
.mon-site-issue { font-size:0.78rem; color:#d97706; margin-top:2px; }

.mon-site-nav { flex-shrink:0; }
.mon-map-btn { display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:50%; background:var(--surface-alt,#f4f6fb); text-decoration:none; font-size:1.1rem; }

.mon-done-divider { text-align:center; font-size:0.78rem; color:var(--ink-400); padding:8px 0; border-top:1px solid var(--line,#e2e8f0); margin-top:4px; }

.mon-category-chips, .mon-route-chips { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:12px; }
.mon-chip { padding:8px 16px; border-radius:999px; border:1px solid var(--line-strong,#b8c4d2); background:var(--surface,#fff); cursor:pointer; font-size:0.85rem; font-weight:500; transition:all .15s; }
.mon-chip:hover { border-color:var(--primary,#2563eb); }
.mon-chip.active { background:var(--primary,#2563eb); color:#fff; border-color:var(--primary,#2563eb); }
.mon-chip .chip-count { font-size:0.75rem; opacity:0.7; margin-left:4px; }

.mon-progress { font-size:0.85rem; color:var(--ink-500); margin-bottom:8px; font-weight:500; }

.mon-condition-strip { display:flex; flex-wrap:wrap; gap:6px; margin:12px 0; }
.mon-condition-chip { padding:6px 12px; border-radius:999px; border:1px solid var(--line-strong,#b8c4d2); background:var(--surface,#fff); cursor:pointer; font-size:0.8rem; transition:all .15s; }
.mon-condition-chip:hover { border-color:var(--primary,#2563eb); }
.mon-condition-chip.active { background:var(--primary,#2563eb); color:#fff; border-color:var(--primary,#2563eb); }
.mon-condition-chip.warn.active { background:#d97706; border-color:#d97706; }

.mon-nearby-nudge { padding:10px 12px; border:1px dashed #60a5fa; border-radius:var(--radius,8px); background:#eff6ff; margin-bottom:12px; font-size:0.85rem; }

.mon-selected-actions { padding:12px; background:var(--surface-alt,#f4f6fb); border-radius:var(--radius,8px); margin-bottom:12px; }

.mon-prev-photo { display:flex; align-items:center; gap:10px; margin-bottom:8px; }
.mon-prev-photo img { width:48px; height:48px; border-radius:6px; object-fit:cover; cursor:pointer; }
```

- [ ] **Step 2: Replace the monitoring.upload view**

Replace the entire `Views.register('monitoring.upload', {...})` block in `modules.js` with the new implementation.

The new view should implement:

**render():**
1. Load planned sites via `Api.get('upload/targets')` (now returns enriched cards + shift status)
2. Build the page layout:
   - Shift control bar (Start Monitoring / active status / Complete Day)
   - Summary banner (planned count, completed, nearest distance)
   - Tab toggle (Planned / Unplanned)
   - Planned tab: site card list (GPS sorted)
   - Unplanned tab: category chips → route chips → site card list
   - Search bar (both tabs)
   - Selected site actions: previous photo, condition tags, map link, issue resolved button
   - Upload form: comment, camera picker, photo preview, progress, submit
   - Success card with auto-advance
   - Recent uploads strip

**afterRender():**
1. Tab switching logic
2. Category/route chip click handlers (API calls for unplanned)
3. Site card selection (tap to select/deselect)
4. GPS distance calculation + sorting (client-side Haversine)
5. Shift start/complete API calls
6. Condition tag selection
7. Quick issue report form
8. Issue resolution with photo proof
9. Google Maps direction link
10. Search debounce + results
11. Upload form submission (existing `uploadWithProgress` with `site_condition` in FormData)
12. Auto-advance to next nearest site after upload
13. Recent uploads strip (existing logic)

**Key implementation notes:**
- GPS: use existing page-load geolocation (already in the code). Compute Haversine distance client-side:
```javascript
function haversineDistance(lat1, lng1, lat2, lng2) {
  const R = 6371000; // meters
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLng = (lng2 - lng1) * Math.PI / 180;
  const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180) * Math.cos(lat2*Math.PI/180) * Math.sin(dLng/2)**2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}
```
- Card format: `{client_name} · {W}×{H} ft · {location_text}` — skip parts that are null
- Done cards: green tint + ✅ + "Done {time}", pushed below divider
- Google Maps: `https://www.google.com/maps/dir/?api=1&destination={lat},{lng}` (only if lat/lng exist)
- `site_condition` added to the FormData before submit (if a condition chip is selected)
- Previous photo: fetch `Api.get('monitoring/upload', { parent_id: siteId, limit: 1 })` or use a lightweight endpoint
- Remember unplanned category/route selection using module-scoped variables (reset on page navigation, not on "Upload more")

This is a large frontend piece. The implementor should build it incrementally:
1. First: tabs + planned card list (no GPS sorting yet)
2. Then: GPS sorting + distance display
3. Then: unplanned tab with chips
4. Then: shift controls
5. Then: condition tags + issue report
6. Then: search
7. Then: auto-advance + polish

- [ ] **Step 3: Verify syntax**

```bash
node --check public/js/views/modules.js
```

- [ ] **Step 4: Commit**

```bash
git add public/js/views/modules.js public/css/style.css
git commit -m "feat(monitoring-upload): redesign with tabs, cards, GPS, shifts, conditions"
```

---

### Task 12: Monitoring History — Card Enrichment

**Files:**
- Modify: `app/controllers/MonitoringHistoryController.php` or `app/services/MonitoringHistoryService.php`
- Modify: `public/js/views/modules.js` (monitoring.history section)

- [ ] **Step 1: Enrich history API response with client name + board size**

In the monitoring history query (in `MonitoringHistoryRepository` or `MonitoringHistoryService`), join campaigns for client name and include board dimensions from sites. Add to the SELECT:

```sql
s.board_width_ft, s.board_height_ft,
(SELECT c.client_name
 FROM campaign_sites cs2
 INNER JOIN campaigns c ON c.id = cs2.campaign_id AND c.status = 'ACTIVE'
 WHERE cs2.site_id = s.id AND cs2.linked_to_date IS NULL
 ORDER BY cs2.linked_from_date DESC LIMIT 1
) AS client_name
```

- [ ] **Step 2: Update history card display in frontend**

In the `monitoring.history` view in `modules.js`, update the card rendering to show:
- Client name (bold, primary)
- Board size (if available)
- Location text
- Site code as small secondary text

Instead of the current site_code-primary display.

- [ ] **Step 3: Verify syntax**

```bash
C:\xampp\php\php.exe -l app/repositories/MonitoringHistoryRepository.php
C:\xampp\php\php.exe -l app/services/MonitoringHistoryService.php
node --check public/js/views/modules.js
```

- [ ] **Step 4: Commit**

```bash
git add app/repositories/MonitoringHistoryRepository.php app/services/MonitoringHistoryService.php public/js/views/modules.js
git commit -m "feat(monitoring-history): enrich cards with client name and board size"
```

---

## Phase 5: Monitoring Plan — Missed Filter (Task 13)

### Task 13: Monitoring Plan — Completion Filter

**Files:**
- Modify: `public/js/views/modules.js` (monitoring.plan section)
- Modify: `app/controllers/MonitoringPlanController.php`

- [ ] **Step 1: Add completion_status filter to plan controller**

In the monitoring plan list endpoint, pass the `completion_status` filter parameter through to the repository. If `completion_status` param is present (`completed` or `missed`), use `getPlanListWithCompletion()` from Task 5 instead of the existing `getPlanList()`.

- [ ] **Step 2: Add filter chips to monitoring plan frontend**

In the `monitoring.plan` view in `modules.js`, add filter chips at the top:

```
Show: [All] [Completed ✅] [Missed ❌]
```

Wire each chip to navigate with `completion_status` param.

- [ ] **Step 3: Verify syntax**

```bash
C:\xampp\php\php.exe -l app/controllers/MonitoringPlanController.php
node --check public/js/views/modules.js
```

- [ ] **Step 4: Commit**

```bash
git add app/controllers/MonitoringPlanController.php public/js/views/modules.js
git commit -m "feat(monitoring-plan): add completed/missed filter chips"
```

---

## Phase 6: Cache + Docs (Tasks 14–15)

### Task 14: Cache Bump + Data Flow Docs

**Files:**
- Modify: `public/index.html`
- Modify: `docs/01_structure/05_DATA_AND_FLOW_NOTES_FINAL.md`

- [ ] **Step 1: Bump cache version in index.html**

Find the `modules.js` and `style.css` script/link tags and increment the `?v=` parameter.

- [ ] **Step 2: Update data flow docs**

In `docs/01_structure/05_DATA_AND_FLOW_NOTES_FINAL.md`, add a section for the monitoring upload flow:

```markdown
## Monitoring Upload Flow

### Planned Monitoring
1. MONITORING_TEAM opens monitoring.upload page
2. Frontend calls `upload/targets` → returns enriched planned sites (client, size, creative, GPS, done status)
3. Person taps "Start Monitoring" → POST `monitoring/start-shift`
4. Person selects a site card → condition strip + upload form appear
5. Person selects condition tag (Good/Damaged/etc.) + takes photos
6. Person submits → POST `upload/create` with surface=MONITORING, site_condition in payload
7. Post-upload side effects: update sites.last_monitored_at, mark due_date completed, increment shift count
8. If condition != GOOD: quick issue created via MonitoringUploadService::reportConditionIssue()
9. Success card → auto-advance to next nearest unvisited site
10. At end of day: "Complete Day" → POST `monitoring/complete-shift`

### Unplanned Monitoring
Same as above but site selection via: Category chip → Route chip → GPS-sorted site list.
Route chips from `monitoring/browse-routes`, sites from `monitoring/browse-sites`.

### Creative Upload
1. CLIENT_SERVICING / OPS / MEDIA_PLANNING opens site detail page
2. Uploads creative via `site/upload-creative` (single image)
3. sites.creative_upload_id updated to new upload ID
4. Creative thumbnail visible on monitoring upload cards

### Issue Resolution from Field
1. Monitoring person sees ⚠️ open issue badge on site card
2. Taps "Issue Resolved" → must upload proof photo
3. POST `monitoring/resolve-issue` closes the issue
```

- [ ] **Step 3: Commit**

```bash
git add public/index.html docs/01_structure/05_DATA_AND_FLOW_NOTES_FINAL.md
git commit -m "docs: update data flow for monitoring upload overhaul, bump cache"
```

---

### Task 15: Governance Docs Update

**Files:**
- Modify: `docs/AGENT_START.md`
- Modify: `docs/PRODUCT_BACKLOG.md`
- Modify: `docs/PRODUCT_LOG.md`
- Modify: `docs/AI_TOOL_HANDOFF_GUIDE.md`

- [ ] **Step 1: Update AGENT_START.md**

Update:
- "Last updated by" line
- "Last commit" line
- "What was recently completed" — add monitoring upload overhaul entries
- "Current focus" — update to next item (Media Discovery implementation or next role)
- "What NOT to touch" — add monitoring.upload (just redesigned), monitoring_shifts table, creative_upload_id FK
- "Known open issues" — update as appropriate

- [ ] **Step 2: Update PRODUCT_BACKLOG.md**

Update:
- MONITORING_TEAM section: mark monitoring upload overhaul as ✅ Done
- Add entries for creative management, board dimensions, shift tracking
- Update overall status

- [ ] **Step 3: Update PRODUCT_LOG.md**

Append a dated entry documenting:
- Schema additions approved (board_width_ft, board_height_ft, creative_upload_id, last_monitored_at, site_condition, monitoring_shifts table)
- Why each was added
- Design decisions (card list vs dropdown, GPS sorting client-side, condition tags merged with issue creation)

- [ ] **Step 4: Update AI_TOOL_HANDOFF_GUIDE.md**

Add new pitfalls:
- `site_condition` ENUM on uploads — new values: GOOD, DAMAGED, FADED, CREATIVE_MISSING, LIGHTS_OFF. Only set for MONITORING surface uploads.
- `monitoring_shifts` table — one shift per user per day (UNIQUE on user_id+shift_date). Do not create duplicate shifts.
- `creative_upload_id` on sites — FK to uploads with ON DELETE SET NULL. Must be non-null for active sites (service validation, not DB constraint).
- `last_monitored_at` on sites — denormalized, auto-updated after monitoring uploads. Do not update manually.
- `completed_at` on site_monitoring_due_dates — marks a planned visit as done. Updated by post-upload hook.
- Post-upload side effects run AFTER UploadService commits — they are non-critical and caught silently. Do not rely on them being transactional with the upload.

- [ ] **Step 5: Commit**

```bash
git add docs/AGENT_START.md docs/PRODUCT_BACKLOG.md docs/PRODUCT_LOG.md docs/AI_TOOL_HANDOFF_GUIDE.md
git commit -m "docs: governance updates for monitoring upload overhaul"
```

---

## Implementation Notes

### Transaction Boundaries
- **UploadService** manages its own transaction for upload creation (including discovery records). Do NOT wrap `createUploadsForSurface()` in another transaction.
- **MonitoringUploadService** methods (shift, issue, side effects) each auto-commit individually — they are not wrapped in a single transaction because they are independent operations.
- **Post-upload side effects** (last_monitored, completed_at, shift count) run after UploadService commits. They are non-critical — if they fail, the upload is still saved.

### GPS Sorting
- Done entirely client-side using the browser's `navigator.geolocation` position.
- Server returns lat/lng per site; frontend computes Haversine distance and sorts.
- Sites without coordinates go to the bottom of the unvisited section.
- Distance recalculated on "Upload more" (user may have moved).

### Issue Creation RBAC
- Existing `IssueService::createIssue()` requires OPS_MANAGER role.
- `MonitoringUploadService::reportConditionIssue()` bypasses this by calling `IssueRepository::create()` directly (with `source_type = 'MONITORING_CONDITION'`). This is intentional — field condition reports are auto-created, not manually created issues.
- Issue resolution from field also bypasses `IssueService::closeIssue()` (which requires OPS_MANAGER). The `ISSUE_RESOLVED_FROM_FIELD` audit action distinguishes field resolutions from ops closures.

### Creative Upload Surface
- Creative uploads use the MONITORING surface config in UploadService (parent_type=SITE, upload_type=WORK).
- The `uploadCreative()` controller method handles the file upload and then updates `sites.creative_upload_id`.
- This is a single-file upload, not multi-file.

---

## Phase 2 (Future — NOT in this plan)
- Media Discovery page implementation (separate plan exists: `docs/superpowers/plans/2026-05-24-media-discovery.md`)
- Monitoring plan auto-generation from frequency rules
- Offline upload queue
