<?php

require_once __DIR__ . '/BaseRepository.php';

/**
 * FreeMediaRepository
 *
 * Purpose:
 * Data access for `free_media_records` table.
 *
 * Schema Reference: docs/06_schema/schema_v1_full.sql — free_media_records
 *
 * Columns: id, site_id, source_type (MONITORING_DISCOVERY|CAMPAIGN_END),
 *          source_reference_id, discovered_date,
 *          confirmed_by_user_id, confirmed_date,
 *          status (DISCOVERED|CONFIRMED_ACTIVE|EXPIRED|CONSUMED),
 *          expiry_date, created_at, updated_at
 */
class FreeMediaRepository extends BaseRepository
{
    /**
     * List free media records with filters and pagination.
     */
    public function getList(array $filters, int $page, int $limit): array
    {
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = "fm.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['site_category'])) {
            $where[] = "s.site_category = ?";
            $params[] = $filters['site_category'];
        }
        if (!empty($filters['route_or_group'])) {
            $where[] = "s.route_or_group = ?";
            $params[] = $filters['route_or_group'];
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $limit;

        $query = "SELECT fm.*, s.site_code, s.location_text, s.site_category, s.route_or_group,
                  confirmer.full_name AS confirmed_by_user_name
                  FROM free_media_records fm
                  INNER JOIN sites s ON s.id = fm.site_id
                  LEFT JOIN users confirmer ON confirmer.id = fm.confirmed_by_user_id
                  WHERE {$whereClause}
                  ORDER BY fm.id DESC
                  LIMIT {$limit} OFFSET {$offset}";

        return $this->fetchAll($query, $params);
    }

    /**
     * Count free media records matching filters.
     */
    public function countList(array $filters): int
    {
        $where = ["1=1"];
        $params = [];
        if (!empty($filters['status'])) {
            $where[] = "fm.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['site_category'])) {
            $where[] = "s.site_category = ?";
            $params[] = $filters['site_category'];
        }
        if (!empty($filters['route_or_group'])) {
            $where[] = "s.route_or_group = ?";
            $params[] = $filters['route_or_group'];
        }

        $whereClause = implode(' AND ', $where);
        $row = $this->fetchOne("
            SELECT COUNT(*) as total
            FROM free_media_records fm
            INNER JOIN sites s ON s.id = fm.site_id
            WHERE {$whereClause}
        ", $params);
        return (int)($row['total'] ?? 0);
    }

    /**
     * Find a record by ID.
     */
    public function findById(int $recordId): ?array
    {
        return $this->fetchOne("SELECT * FROM free_media_records WHERE id = ?", [$recordId]);
    }

    /**
     * Confirm a DISCOVERED record → CONFIRMED_ACTIVE.
     */
    public function confirmRecord(int $recordId, int $confirmedByUserId, string $confirmedDate, ?string $expiryDate): void
    {
        $this->execute(
            "UPDATE free_media_records
             SET status = 'CONFIRMED_ACTIVE',
                 confirmed_by_user_id = ?,
                 confirmed_date = ?,
                 expiry_date = ?,
                 updated_at = NOW()
             WHERE id = ?",
            [$confirmedByUserId, $confirmedDate, $expiryDate, $recordId]
        );
    }

    /**
     * Mark a record as EXPIRED.
     */
    public function markExpired(int $recordId): void
    {
        $this->execute(
            "UPDATE free_media_records SET status = 'EXPIRED', updated_at = NOW() WHERE id = ?",
            [$recordId]
        );
    }

    /**
     * Mark a record as CONSUMED.
     */
    public function markConsumed(int $recordId): void
    {
        $this->execute(
            "UPDATE free_media_records SET status = 'CONSUMED', updated_at = NOW() WHERE id = ?",
            [$recordId]
        );
    }

    /**
     * Create a CONFIRMED_ACTIVE record (e.g. from campaign end).
     */
    public function createConfirmedRecord(array $data): int
    {
        $this->execute(
            "INSERT INTO free_media_records
                (site_id, source_type, source_reference_id, discovered_date,
                 confirmed_by_user_id, confirmed_date, status, expiry_date,
                 created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 'CONFIRMED_ACTIVE', ?, NOW(), NOW())",
            [
                $data['site_id'],
                $data['source_type'],
                $data['source_reference_id'] ?? null,
                $data['discovered_date'],
                $data['confirmed_by_user_id'],
                $data['confirmed_date'],
                $data['expiry_date'] ?? null,
            ]
        );
        return (int)$this->lastInsertId();
    }
}
