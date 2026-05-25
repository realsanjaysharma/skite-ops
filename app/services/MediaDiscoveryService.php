<?php

/**
 * MediaDiscoveryService
 *
 * Purpose:
 * Orchestrates the media-discovery submission flow for MONITORING_TEAM:
 *   GPS resolution -> dedup check -> placeholder site creation
 *                  -> delegate to UploadService (uploads + free_media_record).
 *
 * Transaction note:
 * UploadService::createUploadsForSurface() manages its own PDO transaction
 * internally and already calls createOrRefreshDiscoveryRecord() for discovery
 * uploads. This service does NOT start a separate transaction to avoid PDO
 * nested-transaction errors (the singleton PDO does not allow nesting).
 * Site creation auto-commits — an orphan DISC-* site (is_active=0) is harmless
 * if the upload step fails afterwards.
 *
 * Architecture: Controller -> Service -> Repository -> Database
 * Schema reference: docs/06_schema/schema_v1_full.sql (sites, free_media_records, uploads)
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
     * @param array $rawFiles $_FILES['photos'] structure
     * @param int $actorUserId The monitoring person's user ID
     * @return array Summary: site_id, site_code, is_new_site, photo_count, has_gps, record_id
     */
    public function submitDiscovery(array $data, array $rawFiles, int $actorUserId): array
    {
        // Step 1: Resolve best GPS coordinates (browser priority, then EXIF)
        $gps = $this->resolveGps($data);

        // Step 2: GPS dedup check (only if we have coordinates)
        $existingSite = null;
        if ($gps['lat'] !== null && $gps['lng'] !== null) {
            $existingSite = $this->siteRepo->findDiscoveryNearby(
                $gps['lat'],
                $gps['lng'],
                DISCOVERY_GPS_DEDUP_RADIUS_METERS
            );
        }

        // Step 3: Create new placeholder site or reuse existing
        $isNewSite = ($existingSite === null);
        if ($isNewSite) {
            $siteId = $this->createPlaceholderSite($gps);
        } else {
            $siteId = (int)$existingSite['id'];
        }

        // Step 4: Delegate uploads to UploadService.
        // UploadService runs its own transaction AND creates / refreshes the
        // DISCOVERED free_media_record via UploadRepository internally.
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

        // Step 5: Retrieve the free_media_record id (UploadService already wrote it)
        $fmRecord = $this->freeMediaRepo->findDiscoveredBySiteId($siteId);
        $recordId = $fmRecord ? (int)$fmRecord['id'] : 0;

        // Step 6: Audit the overall discovery event (per-upload audits are written
        // by UploadService; this one captures the discovery-level summary).
        $photoCount = count($uploadResult['created_uploads'] ?? []);

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

        // Step 7: Fetch site_code for response (the placeholder/reused site)
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
     * List the actor's own discovery uploads with pagination.
     *
     * Returns one row per upload (newest first), enriched with the parent site_code
     * and the current free_media_record status. Front-end can group/dedup by site.
     */
    public function listMyDiscoveries(int $actorUserId, int $page = 1, int $limit = 20): array
    {
        $page  = max(1, $page);
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;

        $db = Database::getConnection();

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

        $stmt = $db->prepare($sql);
        $stmt->execute([$actorUserId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countStmt = $db->prepare(
            "SELECT COUNT(*) AS total
               FROM uploads u
              WHERE u.created_by_user_id = ?
                AND u.is_discovery_mode = 1
                AND u.is_deleted = 0
                AND u.is_purged = 0"
        );
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
     * Resolve the best GPS coordinates from browser + EXIF sources.
     * Priority: browser GPS (more accurate, captured at moment of submit) > EXIF > null.
     * Returns ['lat', 'lng', 'exif_lat', 'exif_lng'] — the exif_* values are kept
     * separately so they can be stored on the upload row for forensic purposes.
     */
    private function resolveGps(array $data): array
    {
        $browserLat = $this->parseCoord($data['browser_lat'] ?? null, -90, 90);
        $browserLng = $this->parseCoord($data['browser_lng'] ?? null, -180, 180);
        $exifLat    = $this->parseCoord($data['exif_lat'] ?? null, -90, 90);
        $exifLng    = $this->parseCoord($data['exif_lng'] ?? null, -180, 180);

        if ($browserLat !== null && $browserLng !== null) {
            return [
                'lat'      => $browserLat,
                'lng'      => $browserLng,
                'exif_lat' => $exifLat,
                'exif_lng' => $exifLng,
            ];
        }

        if ($exifLat !== null && $exifLng !== null) {
            return [
                'lat'      => $exifLat,
                'lng'      => $exifLng,
                'exif_lat' => $exifLat,
                'exif_lng' => $exifLng,
            ];
        }

        return [
            'lat'      => null,
            'lng'      => null,
            'exif_lat' => null,
            'exif_lng' => null,
        ];
    }

    private function parseCoord($value, float $min, float $max): ?float
    {
        if ($value === null || $value === '') return null;
        if (!is_numeric($value)) return null;
        $f = (float)$value;
        if ($f < $min || $f > $max) return null;
        return $f;
    }

    /**
     * Create a placeholder DISC-* site for a new discovery.
     * Retries on UNIQUE site_code collision (race condition with another submit).
     */
    private function createPlaceholderSite(array $gps): int
    {
        $siteCode = $this->siteRepo->generateDiscoverySiteCode();

        $locationText = 'Discovery - pending details';
        if ($gps['lat'] !== null && $gps['lng'] !== null) {
            $locationText = sprintf('%.5f, %.5f', $gps['lat'], $gps['lng']);
        }

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
                // Race condition — another submit took our code. Regenerate and retry.
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
