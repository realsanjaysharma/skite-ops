<?php

require_once __DIR__ . '/BaseRepository.php';

/**
 * BoardIssueRepository
 *
 * SQL for electrician board issue queries.
 */
class BoardIssueRepository extends BaseRepository
{
    /**
     * Get open/in-progress board monitoring issues on belts assigned to the user.
     */
    public function getIssuesByBeltAssignment(array $beltIds): array
    {
        if (empty($beltIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($beltIds), '?'));

        return $this->fetchAll(
            "SELECT i.*, gb.belt_code, gb.common_name AS belt_name,
                    u.full_name AS raised_by_name,
                    bmr.off_count, bmr.total_boards, bmr.status AS report_status
             FROM issues i
             INNER JOIN green_belts gb ON gb.id = i.belt_id
             LEFT JOIN users u ON u.id = i.raised_by_user_id
             LEFT JOIN board_monitoring_reports bmr
                ON bmr.id = i.source_reference_id AND i.source_type = 'BOARD_MONITORING'
             WHERE i.belt_id IN ({$placeholders})
               AND i.status IN ('OPEN', 'IN_PROGRESS')
               AND i.source_type = 'BOARD_MONITORING'
             ORDER BY FIELD(i.priority, 'CRITICAL', 'HIGH', 'MEDIUM', 'LOW'),
                      i.created_at ASC",
            $beltIds
        );
    }

    /**
     * Get issues linked to tasks assigned to the user.
     */
    public function getIssuesByTaskAssignment(int $userId): array
    {
        return $this->fetchAll(
            "SELECT i.*, gb.belt_code, gb.common_name AS belt_name,
                    u.full_name AS raised_by_name,
                    bmr.off_count, bmr.total_boards, bmr.status AS report_status,
                    t.id AS task_id
             FROM tasks t
             INNER JOIN issues i ON i.id = t.linked_issue_id
             INNER JOIN green_belts gb ON gb.id = i.belt_id
             LEFT JOIN users u ON u.id = i.raised_by_user_id
             LEFT JOIN board_monitoring_reports bmr
                ON bmr.id = i.source_reference_id AND i.source_type = 'BOARD_MONITORING'
             WHERE t.assigned_lead_user_id = ?
               AND t.status IN ('OPEN', 'RUNNING')
               AND i.status IN ('OPEN', 'IN_PROGRESS')
             ORDER BY FIELD(i.priority, 'CRITICAL', 'HIGH', 'MEDIUM', 'LOW'),
                      i.created_at ASC",
            [$userId]
        );
    }

    /**
     * Get issue detail with linked report and photos.
     */
    public function getIssueDetail(int $issueId): ?array
    {
        return $this->fetchOne(
            "SELECT i.*, gb.belt_code, gb.common_name AS belt_name,
                    u.full_name AS raised_by_name,
                    resolver.full_name AS resolved_by_name,
                    bmr.id AS report_id, bmr.status AS report_status,
                    bmr.off_count, bmr.total_boards, bmr.report_date,
                    bmr.notes AS report_notes
             FROM issues i
             INNER JOIN green_belts gb ON gb.id = i.belt_id
             LEFT JOIN users u ON u.id = i.raised_by_user_id
             LEFT JOIN users resolver ON resolver.id = i.resolved_by_user_id
             LEFT JOIN board_monitoring_reports bmr
                ON bmr.id = i.source_reference_id AND i.source_type = 'BOARD_MONITORING'
             WHERE i.id = ?",
            [$issueId]
        );
    }
}
