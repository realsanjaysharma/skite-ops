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
require_once __DIR__ . '/AuditService.php';

class AuthService
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCK_TIME_MINUTES = 15;

    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var AuditService
     */
    private AuditService $auditService;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
        $this->auditService = new AuditService();
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
            $this->safeAuditLog(
                0,
                'LOGIN_FAILED',
                'USER',
                0,
                null,
                [
                    'email' => $normalizedEmail,
                    'reason' => 'user_not_found'
                ]
            );

            throw new RuntimeException('Invalid email or password');
        }

        if (
            $user['failed_attempt_count'] >= self::MAX_FAILED_ATTEMPTS &&
            $user['last_failed_attempt_at'] !== null
        ) {
            $lastAttemptTime = strtotime($user['last_failed_attempt_at']);
            $lockExpiryTime = $lastAttemptTime + (self::LOCK_TIME_MINUTES * 60);

            if (time() < $lockExpiryTime) {
                $this->safeAuditLog(
                    (int) $user['id'],
                    'LOGIN_BLOCKED_LOCKED',
                    'USER',
                    (int) $user['id'],
                    null,
                    [
                        'remaining_lock_seconds' => $lockExpiryTime - time()
                    ]
                );

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

            $failedAttemptCount = (int) $user['failed_attempt_count'] + 1;

            $this->safeAuditLog(
                (int) $user['id'],
                'LOGIN_FAILED',
                'USER',
                (int) $user['id'],
                null,
                [
                    'failed_attempt_count' => $failedAttemptCount
                ]
            );

            if ($failedAttemptCount >= self::MAX_FAILED_ATTEMPTS) {
                $this->safeAuditLog(
                    (int) $user['id'],
                    'ACCOUNT_LOCKED',
                    'USER',
                    (int) $user['id'],
                    null,
                    [
                        'lock_time_minutes' => self::LOCK_TIME_MINUTES
                    ]
                );
            }

            throw new RuntimeException('Invalid email or password');
        }

        $this->resetFailedAttempts((int) $user['id']);
        $this->safeAuditLog(
            (int) $user['id'],
            'LOGIN_SUCCESS',
            'USER',
            (int) $user['id'],
            null,
            null
        );

        $safeUser = $this->formatAuthUserResponse($user);

        return [
            'user' => $safeUser,
            'requires_password_reset' => ((int) $user['force_password_reset'] === 1)
        ];
    }

    public function resetPassword(int $userId, string $newPassword): void
    {
        if (strlen($newPassword) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters');
        }

        $this->userRepository->updatePassword(
            $userId,
            password_hash($newPassword, PASSWORD_DEFAULT)
        );

        $this->userRepository->resetFailedAttempts($userId);
        $this->userRepository->clearForcePasswordReset($userId);

        $this->safeAuditLog(
            $userId,
            'PASSWORD_RESET',
            'USER',
            $userId,
            null,
            null
        );
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
     * Audit failures must never block authentication flow.
     */
    private function safeAuditLog(
        int $userId,
        string $action,
        string $entityType,
        int $entityId,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        try {
            $this->auditService->logAction(
                $userId,
                $action,
                $entityType,
                $entityId,
                $oldValues,
                $newValues
            );
        } catch (Throwable $exception) {
            // Intentionally swallow audit failures so auth behavior remains unchanged.
        }
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
