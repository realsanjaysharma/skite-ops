<?php

require_once __DIR__ . '/BaseRepository.php';
require_once __DIR__ . '/../../config/constants.php';

class RbacRepository extends BaseRepository
{
    public function getActiveUserIdentity(int $userId): ?array
    {
        return $this->fetchOne(
            "SELECT u.id AS user_id,
                    u.role_id,
                    u.full_name,
                    u.email,
                    u.force_password_reset,
                    u.is_active AS user_is_active,
                    u.is_deleted
             FROM users u
             WHERE u.id = ? AND u.is_deleted = 0 AND u.is_active = 1
             LIMIT 1",
            [$userId]
        );
    }

    public function getRoleAccessRowById(int $roleId): ?array
    {
        return $this->fetchOne(
            "SELECT r.*,
                    pg.id AS permission_group_id,
                    pg.group_key AS permission_group_key,
                    pg.group_name AS permission_group_name
             FROM roles r
             LEFT JOIN role_permission_mappings rpm ON rpm.role_id = r.id
             LEFT JOIN permission_groups pg ON pg.id = rpm.permission_group_id
             WHERE r.id = ?
             LIMIT 1",
            [$roleId]
        );
    }

    public function getRoleModuleKeys(int $roleId): array
    {
        $rows = $this->fetchAll(
            "SELECT module_key
             FROM role_module_scopes
             WHERE role_id = ?
             ORDER BY module_key ASC",
            [$roleId]
        );

        return array_values(array_map(
            static fn(array $row): string => $row['module_key'],
            $rows
        ));
    }

    public function listRoles(array $filters): array
    {
        $where = [];
        $params = [];

        if (isset($filters['is_active'])) {
            $where[] = 'r.is_active = ?';
            $params[] = (int) $filters['is_active'];
        }

        if (isset($filters['is_system_role'])) {
            $where[] = 'r.is_system_role = ?';
            $params[] = (int) $filters['is_system_role'];
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = max(1, (int) ($filters['limit'] ?? DEFAULT_PAGE_LIMIT));
        $offset = ($page - 1) * $limit;

        $countRow = $this->fetchOne(
            "SELECT COUNT(*) AS total
             FROM roles r" . $whereSql,
            $params
        );

        $items = $this->fetchAll(
            "SELECT r.id,
                    r.role_key,
                    r.role_name,
                    r.description,
                    r.landing_module_key,
                    r.is_system_role,
                    r.is_active,
                    pg.group_key AS permission_group_key,
                    pg.group_name AS permission_group_name
             FROM roles r
             LEFT JOIN role_permission_mappings rpm ON rpm.role_id = r.id
             LEFT JOIN permission_groups pg ON pg.id = rpm.permission_group_id" . $whereSql . "
             ORDER BY r.created_at DESC, r.id DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );

        return [
            'items' => $items,
            'total' => (int) ($countRow['total'] ?? 0),
            'page' => $page,
            'limit' => $limit,
        ];
    }

    public function getPermissionGroupById(int $permissionGroupId): ?array
    {
        return $this->fetchOne(
            "SELECT *
             FROM permission_groups
             WHERE id = ?
             LIMIT 1",
            [$permissionGroupId]
        );
    }

    public function roleKeyExists(string $roleKey, ?int $excludeRoleId = null): bool
    {
        $sql = "SELECT id
                FROM roles
                WHERE role_key = ?";
        $params = [$roleKey];

        if ($excludeRoleId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeRoleId;
        }

        $sql .= " LIMIT 1";

        return $this->fetchOne($sql, $params) !== null;
    }

    public function roleNameExists(string $roleName, ?int $excludeRoleId = null): bool
    {
        $sql = "SELECT id
                FROM roles
                WHERE role_name = ?";
        $params = [$roleName];

        if ($excludeRoleId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeRoleId;
        }

        $sql .= " LIMIT 1";

        return $this->fetchOne($sql, $params) !== null;
    }

    public function createRole(array $data): int
    {
        $this->execute(
            "INSERT INTO roles
            (role_key, role_name, description, landing_module_key, is_system_role, is_active, created_by_user_id, created_at)
            VALUES (?, ?, ?, ?, 0, 1, ?, NOW())",
            [
                $data['role_key'],
                $data['role_name'],
                $data['description'],
                $data['landing_module_key'],
                $data['created_by_user_id'],
            ]
        );

        return (int) $this->lastInsertId();
    }

    public function updateRole(int $roleId, array $data): bool
    {
        return $this->execute(
            "UPDATE roles
             SET role_name = ?,
                 description = ?,
                 landing_module_key = ?,
                 updated_at = NOW()
             WHERE id = ?",
            [
                $data['role_name'],
                $data['description'],
                $data['landing_module_key'],
                $roleId,
            ]
        );
    }

    public function upsertRolePermissionMapping(int $roleId, int $permissionGroupId): bool
    {
        return $this->execute(
            "INSERT INTO role_permission_mappings (role_id, permission_group_id, created_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                 permission_group_id = VALUES(permission_group_id),
                 updated_at = CURRENT_TIMESTAMP",
            [$roleId, $permissionGroupId]
        );
    }

    public function deleteRoleModuleScopes(int $roleId): bool
    {
        return $this->execute(
            "DELETE FROM role_module_scopes
             WHERE role_id = ?",
            [$roleId]
        );
    }

    public function insertRoleModuleScope(int $roleId, string $moduleKey): bool
    {
        return $this->execute(
            "INSERT INTO role_module_scopes (role_id, module_key, created_at)
             VALUES (?, ?, NOW())",
            [$roleId, $moduleKey]
        );
    }
}
