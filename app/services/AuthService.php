<?php

/**
 * AuthService
 *
 * Purpose:
 * Handles user authentication logic using UserRepository.
 *
 * Architecture Rule:
 * Controller -> Service -> Repository -> Database
 *
 * IMPORTANT RULES:
 * - No SQL is allowed in this service
 * - Authentication uses active, non-deleted users only
 * - Password hashes must never be returned to callers
 * - Authentication failures must use the same error message
 */

require_once __DIR__ . '/../repositories/UserRepository.php';

class AuthService
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCK_TIME_MINUTES = 15;

    /**
     * @var UserRepository
     */
    private $userRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
    }

    /**
     * Authenticate user with normalized email and password verification.
     */
    public function login($email, $password)
    {
        if (!is_string($email)) {
            throw new InvalidArgumentException('Email must be a string.');
        }

        if (!is_string($password)) {
            throw new InvalidArgumentException('Password must be a string.');
        }

        if (trim($email) === '') {
            throw new InvalidArgumentException('Email is required.');
        }

        if (trim($password) === '') {
            throw new InvalidArgumentException('Password is required.');
        }

        $normalizedEmail = strtolower(trim($email));

        if (!filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email format is invalid.');
        }

        $user = $this->userRepository->getUserByEmail($normalizedEmail);

        if (!$user) {
            throw new RuntimeException('Invalid email or password');
        }

        if (
            $user['failed_attempt_count'] >= self::MAX_FAILED_ATTEMPTS &&
            $user['last_failed_attempt_at'] !== null
        ) {
            $lastAttemptTime = strtotime($user['last_failed_attempt_at']);
            $lockExpiryTime = $lastAttemptTime + (self::LOCK_TIME_MINUTES * 60);

            if (time() < $lockExpiryTime) {
                throw new RuntimeException('Invalid email or password');
            }

            $this->resetFailedAttempts((int) $user['id']);
            $user['failed_attempt_count'] = 0;
            $user['last_failed_attempt_at'] = null;
        }

        if ((int) $user['is_deleted'] === 1) {
            throw new RuntimeException('Invalid email or password');
        }

        if ((int) $user['is_active'] === 0) {
            throw new RuntimeException('Invalid email or password');
        }

        if (!password_verify($password, $user['password_hash'])) {
            $this->incrementFailedAttempts((int) $user['id']);
            throw new RuntimeException('Invalid email or password');
        }

        $this->resetFailedAttempts((int) $user['id']);
        return $this->formatAuthUserResponse($user);
    }

    private function incrementFailedAttempts(int $userId): void
    {
        $this->userRepository->updateFailedAttempts($userId);
    }

    private function resetFailedAttempts(int $userId): void
    {
        $this->userRepository->resetFailedAttempts($userId);
    }

    /**
     * Return only safe authenticated user fields.
     */
    private function formatAuthUserResponse($user)
    {
        return [
            'id' => $user['id'] ?? null,
            'role_id' => $user['role_id'] ?? null,
            'full_name' => $user['full_name'] ?? null,
            'email' => $user['email'] ?? null,
            'is_active' => $user['is_active'] ?? null
        ];
    }
}
