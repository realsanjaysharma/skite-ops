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
require_once __DIR__ . '/../../config/database.php';

class UserService
{
    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var PDO
     */
    private $db;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
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
     * Get all active users that are not soft deleted.
     */
    public function getAllActiveUsers()
    {
        return $this->formatUserListResponse($this->userRepository->getAllActiveUsers());
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
     * Create user with validation and DB-level uniqueness enforcement.
     */
    public function createUser($data)
    {
        $validatedData = $this->validateCreateUserData($data);

        try {
            $existingUser = $this->userRepository->getUserByEmail($validatedData['email']);

            if ($existingUser !== null) {
                throw new InvalidArgumentException('Email already exists');
            }

            $userId = $this->userRepository->createUser([
                'full_name' => $validatedData['full_name'],
                'email' => $validatedData['email'],
                'password_hash' => password_hash($validatedData['password'], PASSWORD_DEFAULT),
                'role_id' => $validatedData['role_id']
            ]);

            return $this->getUserById($userId);
        } catch (PDOException $exception) {
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
        }
    }

    /**
     * Update user with validation and global email uniqueness enforcement.
     */
    public function updateUser($userId, $data)
    {
        $this->validateUserId($userId);
        $validatedData = $this->validateUpdateUserData($data);

        $existingUser = $this->userRepository->getUserById($userId);

        if ($existingUser === null) {
            throw new RuntimeException('User not found or has been deleted.');
        }

        $userWithSameEmail = $this->userRepository->getUserByEmail($validatedData['email']);

        if ($userWithSameEmail !== null && (int) $userWithSameEmail['id'] !== (int) $userId) {
            throw new InvalidArgumentException('Email already exists');
        }

        $updated = $this->userRepository->updateUser($userId, [
            'full_name' => $validatedData['full_name'],
            'email' => $validatedData['email'],
            'role_id' => $validatedData['role_id']
        ]);

        if (!$updated) {
            throw new RuntimeException('Failed to update user.');
        }

        return $this->getUserById($userId);
    }

    /**
     * Soft delete user with validation and audit tracking.
     */
    public function softDeleteUser($userId, $deletedBy)
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

        $deletedByUser = $this->userRepository->getUserById($deletedBy);

        if ($deletedByUser === null) {
            throw new RuntimeException('Deleted-by user not found or has been deleted.');
        }

        $deleted = $this->userRepository->softDeleteUser($userId, $deletedBy);

        if (!$deleted) {
            throw new RuntimeException('Failed to soft delete user.');
        }

        return true;
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
