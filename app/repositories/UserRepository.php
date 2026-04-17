<?php

require_once __DIR__ . '/BaseRepository.php';
require_once __DIR__ . '/../../config/constants.php';

class UserRepository extends BaseRepository
{
    public function getUserByIdIncludingDeleted($userId)
    {
        return $this->fetchOne(
            $this->baseUserSelect() . "
             WHERE u.id = ?",
            [$userId]
        );
    }

    public function getUserById($userId)
    {
        return $this->fetchOne(
            $this->baseUserSelect() . "
             WHERE u.id = ? AND u.is_deleted = 0",
            [$userId]
        );
    }

    public function getActiveUserById(int $userId)
    {
        return $this->fetchOne(
            $this->baseUserSelect() . "
             WHERE u.id = ? AND u.is_deleted = 0 AND u.is_active = 1",
            [$userId]
        );
    }

    public function getRoleById(int $roleId)
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

    public function getUserByEmail($email)
    {
        return $this->fetchOne(
            $this->baseUserSelect() . "
             WHERE u.email = ? AND u.is_deleted = 0",
            [$email]
        );
    }

    public function emailExists(string $email): bool
    {
        $user = $this->fetchOne(
            "SELECT id FROM users
             WHERE email = ?
             LIMIT 1",
            [$email]
        );

        return $user !== null;
    }

    public function roleExists(int $roleId): bool
    {
        $role = $this->fetchOne(
            "SELECT id FROM roles
             WHERE id = ?
             LIMIT 1",
            [$roleId]
        );

        return $role !== null;
    }

    public function getAllUsers(array $filters = []): array
    {
        $where = ['u.is_deleted = 0'];
        $params = [];

        if (isset($filters['is_active'])) {
            $where[] = 'u.is_active = ?';
            $params[] = (int) $filters['is_active'];
        }

        if (isset($filters['role_id'])) {
            $where[] = 'u.role_id = ?';
            $params[] = (int) $filters['role_id'];
        }

        $whereSql = ' WHERE ' . implode(' AND ', $where);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = max(1, (int) ($filters['limit'] ?? DEFAULT_PAGE_LIMIT));
        $offset = ($page - 1) * $limit;

        $countRow = $this->fetchOne(
            "SELECT COUNT(*) AS total
             FROM users u" . $whereSql,
            $params
        );

        $items = $this->fetchAll(
            $this->baseUserSelect() . $whereSql . "
             ORDER BY u.created_at DESC, u.id DESC
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

    public function getUsersByRole($roleId)
    {
        return $this->fetchAll(
            $this->baseUserSelect() . "
             WHERE u.role_id = ?
             AND u.is_deleted = 0
             AND u.is_active = 1
             ORDER BY u.full_name ASC",
            [$roleId]
        );
    }

    public function updateFailedAttempts(int $userId): void
    {
        $this->execute(
            "UPDATE users
             SET failed_attempt_count = failed_attempt_count + 1,
                 last_failed_attempt_at = NOW()
             WHERE id = ?",
            [$userId]
        );
    }

    public function resetFailedAttempts(int $userId): void
    {
        $this->execute(
            "UPDATE users
             SET failed_attempt_count = 0,
                 last_failed_attempt_at = NULL
             WHERE id = ?",
            [$userId]
        );
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        $this->execute(
            "UPDATE users
             SET password_hash = ?, updated_at = NOW()
             WHERE id = ?",
            [$passwordHash, $userId]
        );
    }

    public function clearForcePasswordReset(int $userId): void
    {
        $this->execute(
            "UPDATE users
             SET force_password_reset = 0
             WHERE id = ?",
            [$userId]
        );
    }

    public function activateUser(int $userId): bool
    {
        return $this->execute(
            "UPDATE users
             SET is_active = 1, updated_at = NOW()
             WHERE id = ? AND is_deleted = 0",
            [$userId]
        );
    }

    public function deactivateUser(int $userId): bool
    {
        return $this->execute(
            "UPDATE users
             SET is_active = 0, updated_at = NOW()
             WHERE id = ? AND is_deleted = 0",
            [$userId]
        );
    }

    public function restoreUser(int $userId): bool
    {
        return $this->execute(
            "UPDATE users
             SET is_deleted = 0,
                 deleted_at = NULL,
                 deleted_by_user_id = NULL,
                 is_active = 1,
                 force_password_reset = 1,
                 updated_at = NOW()
             WHERE id = ? AND is_deleted = 1",
            [$userId]
        );
    }

    public function createUser($data)
    {
        $this->execute(
            "INSERT INTO users
            (full_name, email, password_hash, role_id, force_password_reset, created_at)
            VALUES (?, ?, ?, ?, 1, NOW())",
            [
                $data['full_name'],
                $data['email'],
                $data['password_hash'],
                $data['role_id']
            ]
        );

        return $this->lastInsertId();
    }

    public function updateUser($userId, $data)
    {
        return $this->execute(
            "UPDATE users
             SET full_name = ?, email = ?, role_id = ?, updated_at = NOW()
             WHERE id = ? AND is_deleted = 0",
            [
                $data['full_name'],
                $data['email'],
                $data['role_id'],
                $userId
            ]
        );
    }

    public function softDeleteUser($userId, $deletedBy)
    {
        return $this->execute(
            "UPDATE users
             SET is_deleted = 1, is_active = 0, deleted_at = NOW(), deleted_by_user_id = ?, updated_at = NOW()
             WHERE id = ?",
            [$deletedBy, $userId]
        );
    }

    private function baseUserSelect(): string
    {
        return "SELECT u.*,
                       r.role_name,
                       r.role_key,
                       r.landing_module_key,
                       r.is_active AS role_is_active,
                       pg.group_key AS permission_group_key,
                       pg.group_name AS permission_group_name
                FROM users u
                JOIN roles r ON r.id = u.role_id
                LEFT JOIN role_permission_mappings rpm ON rpm.role_id = r.id
                LEFT JOIN permission_groups pg ON pg.id = rpm.permission_group_id";
    }
}
