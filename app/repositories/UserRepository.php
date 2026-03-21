<?php

/**
 * UserRepository
 *
 * Purpose:
 * Handles all database interactions related to users.
 *
 * IMPORTANT RULES:
 * - This class ONLY executes SQL
 * - No business logic (validation, hashing, rules)
 * - Must strictly follow schema_v1_full.sql
 *
 * Schema Mapping:
 * users table columns:
 * - id
 * - role_id
 * - full_name
 * - email (UNIQUE)
 * - password_hash
 * - is_active
 * - is_deleted
 * - deleted_at
 * - deleted_by
 * - created_at
 * - updated_at
 *
 * Soft Delete Rule:
 * - All reads must filter is_deleted = 0
 * - No hard deletes allowed
 */

require_once __DIR__ . '/BaseRepository.php';

class UserRepository extends BaseRepository
{
    /**
     * Get user by primary ID
     */
    public function getUserById($userId)
    {
        return $this->fetchOne(
            "SELECT * FROM users 
             WHERE id = ? AND is_deleted = 0",
            [$userId]
        );
    }

    /**
     * Get user by email (used for authentication)
     * Email is UNIQUE in schema
     */
    public function getUserByEmail($email)
    {
        return $this->fetchOne(
            "SELECT * FROM users 
             WHERE email = ? AND is_deleted = 0",
            [$email]
        );
    }

    /**
     * Get user by email including soft-deleted records
     */
    public function getUserByEmailIncludingDeleted($email)
    {
        return $this->fetchOne(
            "SELECT * FROM users
             WHERE email = ?
                LIMIT 1",
            [$email]
        );
    }

    /**
     * Get all active users (not deleted + active)
     */
    public function getAllActiveUsers()
    {
        return $this->fetchAll(
            "SELECT * FROM users 
             WHERE is_deleted = 0 AND is_active = 1"
        );
    }

    /**
     * Get users filtered by role
     */
    public function getUsersByRole($roleId)
    {
        return $this->fetchAll(
            "SELECT * FROM users 
             WHERE role_id = ? 
             AND is_deleted = 0 
             AND is_active = 1",
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

    /**
     * Create new user
     *
     * NOTE:
     * - password must already be hashed (handled in service layer)
     */
    public function createUser($data)
    {
        $this->execute(
            "INSERT INTO users 
            (full_name, email, password_hash, role_id, created_at)
            VALUES (?, ?, ?, ?, NOW())",
            [
                $data['full_name'],
                $data['email'],
                $data['password_hash'],
                $data['role_id']
            ]
        );

        return $this->lastInsertId();
    }

    /**
     * Update user basic information
     */
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

    /**
     * Soft delete user
     *
     * IMPORTANT:
     * - Data is not removed
     * - Required for audit/history
     */
    public function softDeleteUser($userId, $deletedBy)
    {
        return $this->execute(
            "UPDATE users 
             SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
             WHERE id = ?",
            [$deletedBy, $userId]
        );
    }
}
