<?php

/**
 * UserService
 *
 * Purpose:
 * Handles user business logic, validation, and transaction control.
 *
 * Architecture Rule:
 * Controller -> Service -> Repository -> Database
 *
 * IMPORTANT RULES:
 * - No SQL is allowed in this service
 * - All database access must go through UserRepository
 * - Passwords must be hashed before persistence
 * - Soft-deleted users must not be operated on
 * - Transactions are handled here only when multiple DB operations are involved
 */

require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/AuditService.php';
require_once __DIR__ . '/../../config/database.php';

class UserService
{
    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var AuditService
     */
    private AuditService $auditService;

    /**
     * @var PDO
     */
    private $db;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
        $this->auditService = new AuditService();
        $this->db = Database::getConnection();
    }

    /**
     * Get one non-deleted user by ID.
     */
    public function getUserById($userId)
    {
        $this->validateUserId($userId);

        $user = $this->userRepository->getUserById($userId);

        if ($user === null) {
            throw new RuntimeException('User not found or has been deleted.');
        }

        return $this->formatUserResponse($user);
    }

    /**
     * Get one non-deleted user by email.
     */
    public function getUserByEmail($email)
    {
        $normalizedEmail = $this->validateAndNormalizeEmail($email);

        $user = $this->userRepository->getUserByEmail($normalizedEmail);

        if ($user === null) {
            throw new RuntimeException('User not found or has been deleted.');
        }

        return $this->formatUserResponse($user);
    }

    /**
     * Get all non-deleted users, including inactive accounts.
     *
     * NOTE:
     * This list is for lifecycle visibility and admin control, not "usable users" only.
     * Consumers must check is_active before allowing authentication or operational actions.
     */
    public function getAllUsers()
    {
        return $this->formatUserListResponse($this->userRepository->getAllUsers());
    }

    /**
     * Get active, non-deleted users by role.
     */
    public function getUsersByRole($roleId)
    {
        $this->validateRoleId($roleId);

        return $this->formatUserListResponse($this->userRepository->getUsersByRole($roleId));
    }

    /**
     * Determine whether a user is operationally usable.
     * Visible users may still be inactive, so callers must not assume list membership
     * means the user can authenticate or perform actions.
     */
    public function isUserActive(array $user): bool
    {
        return (int) ($user['is_deleted'] ?? 0) === 0
            && (int) ($user['is_active'] ?? 0) === 1;
    }

    /**
     * Create user with validation and DB-level uniqueness enforcement.
     */
    public function createUser(array $data, int $actorUserId)
    {
        $validatedData = $this->validateCreateUserData($data);
        $this->validateUserId($actorUserId);

        try {
            $this->assertRoleExists($validatedData['role_id']);

            if ($this->userRepository->emailExists($validatedData['email'])) {
                throw new InvalidArgumentException('Email already exists');
            }

            $this->db->beginTransaction();

            $userId = $this->userRepository->createUser([
                'full_name' => $validatedData['full_name'],
                'email' => $validatedData['email'],
                'password_hash' => password_hash($validatedData['password'], PASSWORD_DEFAULT),
                'role_id' => $validatedData['role_id']
            ]);

            $sanitizedData = $validatedData;
            unset($sanitizedData['password']);

            $this->auditService->logAction(
                $actorUserId,
                'USER_CREATED',
                'USER',
                (int) $userId,
                null,
                $sanitizedData
            );

            $this->db->commit();

            return $this->getUserById($userId);
        } catch (PDOException $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if ($exception->getCode() === '23000') {
                $message = $exception->getMessage();

                if (strpos($message, 'users.email') !== false || strpos($message, 'Duplicate entry') !== false) {
                    throw new InvalidArgumentException('Email already exists', 0, $exception);
                }

                if (strpos($message, 'role_id') !== false || strpos($message, 'foreign key constraint fails') !== false) {
                    throw new InvalidArgumentException('Invalid role_id (role does not exist)', 0, $exception);
                }
            }

            throw $exception;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Update user with validation and global email uniqueness enforcement.
     */
    public function updateUser(array $data, int $actorUserId)
    {
        $userId = $data['user_id'] ?? null;
        $this->validateUserId($userId);
        $this->validateUserId($actorUserId);
        $validatedData = $this->validateUpdateUserData($data);

        try {
            $oldUser = $this->userRepository->getUserById($userId);

            if ($oldUser === null) {
                throw new RuntimeException('User not found or has been deleted.');
            }

            $this->assertRoleExists($validatedData['role_id']);

            $existingUser = $this->userRepository->getUserByEmail($validatedData['email']);

            if (
                $existingUser !== null &&
                (int) $existingUser['id'] !== (int) $userId
            ) {
                throw new InvalidArgumentException('Email already exists');
            }

            $this->db->beginTransaction();

            $updated = $this->userRepository->updateUser($userId, [
                'full_name' => $validatedData['full_name'],
                'email' => $validatedData['email'],
                'role_id' => $validatedData['role_id']
            ]);

            if (!$updated) {
                throw new RuntimeException('Failed to update user.');
            }

            $safeOldUser = $oldUser;

            // IMPORTANT: Never log sensitive fields like password_hash in audit logs.
            unset($safeOldUser['password_hash']);

            $this->auditService->logAction(
                $actorUserId,
                'USER_UPDATED',
                'USER',
                $userId,
                $safeOldUser,
                $validatedData
            );

            $this->db->commit();

            return $this->getUserById($userId);
        } catch (PDOException $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if ($exception->getCode() === '23000') {
                throw new InvalidArgumentException('Email already exists');
            }

            throw $exception;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Soft delete user with validation and audit tracking.
     */
    public function softDeleteUser(int $userId, int $deletedBy)
    {
        $this->validateUserId($userId);
        $this->validateUserId($deletedBy);

        if ((int) $userId === (int) $deletedBy) {
            throw new InvalidArgumentException('User cannot delete themselves');
        }

        $user = $this->userRepository->getUserById($userId);

        if ($user === null) {
            throw new RuntimeException('User not found or has already been deleted.');
        }

        $actor = $this->userRepository->getUserById($deletedBy);

        if ($actor === null || !$this->isUserActive($actor)) {
            throw new RuntimeException('Invalid actor');
        }

        $this->db->beginTransaction();

        try {
            $deleted = $this->userRepository->softDeleteUser($userId, $deletedBy);

            if (!$deleted) {
                throw new RuntimeException('Failed to soft delete user.');
            }

            $this->auditService->logAction(
                $deletedBy,
                'USER_DELETED',
                'USER',
                $userId,
                null,
                ['is_deleted' => 1, 'is_active' => 0]
            );

            $this->db->commit();

            return true;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    public function activateUser(int $userId, int $actorId): void
    {
        $this->validateUserId($userId);
        $this->validateUserId($actorId);

        if ((int) $userId === (int) $actorId) {
            throw new InvalidArgumentException('User cannot modify themselves');
        }

        $user = $this->userRepository->getUserByIdIncludingDeleted($userId);

        if ($user === null) {
            throw new RuntimeException('User not found.');
        }

        $actor = $this->userRepository->getUserById($actorId);

        if ($actor === null || !$this->isUserActive($actor)) {
            throw new RuntimeException('Invalid actor');
        }

        if ((int) $user['is_deleted'] === 1) {
            throw new RuntimeException('Deleted users cannot be activated.');
        }

        if ($this->isUserActive($user)) {
            throw new RuntimeException('User already active');
        }

        $this->db->beginTransaction();

        try {
            if (!$this->userRepository->activateUser($userId)) {
                throw new RuntimeException('Failed to activate user.');
            }

            $this->auditService->logAction(
                $actorId,
                'USER_ACTIVATED',
                'USER',
                $userId,
                ['is_active' => (int) $user['is_active']],
                ['is_active' => 1]
            );

            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    public function deactivateUser(int $userId, int $actorId): void
    {
        $this->validateUserId($userId);
        $this->validateUserId($actorId);

        if ((int) $userId === (int) $actorId) {
            throw new InvalidArgumentException('User cannot modify themselves');
        }

        $user = $this->userRepository->getUserByIdIncludingDeleted($userId);

        if ($user === null) {
            throw new RuntimeException('User not found.');
        }

        $actor = $this->userRepository->getUserById($actorId);

        if ($actor === null || !$this->isUserActive($actor)) {
            throw new RuntimeException('Invalid actor');
        }

        if ((int) $user['is_deleted'] === 1) {
            throw new RuntimeException('Deleted users cannot be deactivated.');
        }

        if (!$this->isUserActive($user)) {
            throw new RuntimeException('User already inactive');
        }

        $this->db->beginTransaction();

        try {
            if (!$this->userRepository->deactivateUser($userId)) {
                throw new RuntimeException('Failed to deactivate user.');
            }

            $this->auditService->logAction(
                $actorId,
                'USER_DEACTIVATED',
                'USER',
                $userId,
                ['is_active' => (int) $user['is_active']],
                ['is_active' => 0]
            );

            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    public function restoreUser(int $userId, int $actorId): void
    {
        $this->validateUserId($userId);
        $this->validateUserId($actorId);

        if ((int) $userId === (int) $actorId) {
            throw new InvalidArgumentException('User cannot modify themselves');
        }

        $user = $this->userRepository->getUserByIdIncludingDeleted($userId);

        if ($user === null) {
            throw new RuntimeException('User not found.');
        }

        $actor = $this->userRepository->getUserById($actorId);

        if ($actor === null || !$this->isUserActive($actor)) {
            throw new RuntimeException('Invalid actor');
        }

        if ((int) $user['is_deleted'] !== 1) {
            throw new RuntimeException('Only deleted users can be restored.');
        }

        $this->db->beginTransaction();

        try {
            if (!$this->userRepository->restoreUser($userId)) {
                throw new RuntimeException('Failed to restore user.');
            }

            $this->auditService->logAction(
                $actorId,
                'USER_RESTORED',
                'USER',
                $userId,
                [
                    'is_deleted' => (int) $user['is_deleted'],
                    'is_active' => (int) $user['is_active'],
                    'force_password_reset' => (int) ($user['force_password_reset'] ?? 0)
                ],
                [
                    'is_deleted' => 0,
                    'is_active' => 1,
                    'force_password_reset' => 1
                ]
            );

            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Validate create payload and keep schema field names exact.
     */
    private function validateCreateUserData($data)
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException('User data must be provided as an array.');
        }

        $fullName = $this->validateFullName($data['full_name'] ?? null);
        $email = $this->validateAndNormalizeEmail($data['email'] ?? null);
        $password = $this->validatePassword($data['password'] ?? null);
        $roleId = $this->validateRoleId($data['role_id'] ?? null);

        return [
            'full_name' => $fullName,
            'email' => $email,
            'password' => $password,
            'role_id' => $roleId
        ];
    }

    /**
     * Validate update payload.
     *
     * Password is intentionally excluded because the repository does not
     * provide a password update method in the current scope.
     */
    private function validateUpdateUserData($data)
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException('User data must be provided as an array.');
        }

        return [
            'full_name' => $this->validateFullName($data['full_name'] ?? null),
            'email' => $this->validateAndNormalizeEmail($data['email'] ?? null),
            'role_id' => $this->validateRoleId($data['role_id'] ?? null)
        ];
    }

    /**
     * full_name is required by schema and business flow.
     */
    private function validateFullName($fullName)
    {
        if (!is_string($fullName)) {
            throw new InvalidArgumentException('Full name is required.');
        }

        $fullName = trim($fullName);

        if ($fullName === '') {
            throw new InvalidArgumentException('Full name is required.');
        }

        return $fullName;
    }

    /**
     * Normalize email before uniqueness checks and persistence.
     */
    private function validateAndNormalizeEmail($email)
    {
        if (!is_string($email)) {
            throw new InvalidArgumentException('Email is required.');
        }

        $email = strtolower(trim($email));

        if ($email === '') {
            throw new InvalidArgumentException('Email is required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email format is invalid.');
        }

        return $email;
    }

    /**
     * Password is accepted only for creation and never stored in plain text.
     */
    private function validatePassword($password)
    {
        if (!is_string($password)) {
            throw new InvalidArgumentException('Password is required.');
        }

        if (trim($password) === '') {
            throw new InvalidArgumentException('Password is required.');
        }

        return $password;
    }

    /**
     * role_id must be a valid positive integer.
     */
    private function validateRoleId($roleId)
    {
        if (filter_var($roleId, FILTER_VALIDATE_INT) === false || (int) $roleId <= 0) {
            throw new InvalidArgumentException('Role ID must be a valid positive integer.');
        }

        return (int) $roleId;
    }

    /**
     * role_id must reference an existing role before persistence.
     */
    private function assertRoleExists(int $roleId): void
    {
        if (!$this->userRepository->roleExists($roleId)) {
            throw new InvalidArgumentException('Invalid role_id');
        }
    }

    /**
     * User IDs must be positive integers for repository access.
     */
    private function validateUserId($userId)
    {
        if (filter_var($userId, FILTER_VALIDATE_INT) === false || (int) $userId <= 0) {
            throw new InvalidArgumentException('User ID must be a valid positive integer.');
        }

        return (int) $userId;
    }

    /**
     * Return only safe user fields for API responses.
     * Status fields are intentionally included so clients can distinguish
     * visible-but-inactive users from deleted or operationally usable users.
     */
    private function formatUserResponse($user)
    {
        return [
            'id' => $user['id'] ?? null,
            'role_id' => $user['role_id'] ?? null,
            'full_name' => $user['full_name'] ?? null,
            'email' => $user['email'] ?? null,
            'is_active' => $user['is_active'] ?? null,
            'is_deleted' => $user['is_deleted'] ?? null,
            'created_at' => $user['created_at'] ?? null,
            'updated_at' => $user['updated_at'] ?? null
        ];
    }

    /**
     * Return only safe user fields for API list responses.
     */
    private function formatUserListResponse($users)
    {
        $formattedUsers = [];

        foreach ($users as $user) {
            $formattedUsers[] = $this->formatUserResponse($user);
        }

        return $formattedUsers;
    }
}
