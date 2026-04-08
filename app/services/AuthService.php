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
require_once __DIR__ . '/../../config/database.php';

class AuthService
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
            $user['failed_attempt_count'] >= MAX_FAILED_LOGIN_ATTEMPTS &&
            $user['last_failed_attempt_at'] !== null
        ) {
            $lastAttemptTime = strtotime($user['last_failed_attempt_at']);
            $lockExpiryTime = $lastAttemptTime + (LOGIN_LOCK_DURATION_MINUTES * 60);

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

            if ($failedAttemptCount >= MAX_FAILED_LOGIN_ATTEMPTS) {
                $this->safeAuditLog(
                    (int) $user['id'],
                    'ACCOUNT_LOCKED',
                    'USER',
                    (int) $user['id'],
                    null,
                    [
                        'lock_time_minutes' => LOGIN_LOCK_DURATION_MINUTES
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
        $landingContext = $this->buildLandingContext((int) $user['role_id']);

        return [
            'user' => $safeUser,
            'requires_password_reset' => ((int) $user['force_password_reset'] === 1),
            'landing_module_key' => $landingContext['landing_module_key'],
            'landing_route' => $landingContext['landing_route']
        ];
    }

    /**
     * Return authenticated session bootstrap data for an already logged-in user.
     */
    public function getSessionData(int $userId): array
    {
        $user = $this->userRepository->getActiveUserById($userId);

        if ($user === null) {
            throw new RuntimeException('User not found');
        }

        $safeUser = $this->formatAuthUserResponse($user);
        $landingContext = $this->buildLandingContext((int) $user['role_id']);

        return [
            'user' => $safeUser,
            'requires_password_reset' => ((int) $user['force_password_reset'] === 1),
            'landing_module_key' => $landingContext['landing_module_key'],
            'landing_route' => $landingContext['landing_route']
        ];
    }

    public function resetPassword(int $userId, string $newPassword): void
    {
        if (strlen($newPassword) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters');
        }

        $user = $this->userRepository->getActiveUserById($userId);

        if ($user === null) {
            throw new RuntimeException('User not found');
        }

        if ((int) $user['force_password_reset'] !== 1) {
            throw new RuntimeException('Password reset not allowed');
        }

        $this->db->beginTransaction();

        try {
            $this->userRepository->updatePassword(
                $userId,
                password_hash($newPassword, PASSWORD_DEFAULT)
            );

            $this->userRepository->resetFailedAttempts($userId);
            $this->userRepository->clearForcePasswordReset($userId);

            $this->auditService->logAction(
                $userId,
                'PASSWORD_RESET',
                'USER',
                $userId,
                null,
                null
            );

            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
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
            'role_key' => $user['role_key'] ?? null,
            'role_name' => $user['role_name'] ?? null,
            'full_name' => $user['full_name'] ?? null,
            'email' => $user['email'] ?? null,
            'is_active' => $user['is_active'] ?? null
        ];
    }

    /**
     * Resolve landing module and route from the canonical roles table.
     */
    private function buildLandingContext(int $roleId): array
    {
        $role = $this->userRepository->getRoleById($roleId);

        if ($role === null) {
            throw new RuntimeException('Role not found');
        }

        $landingModuleKey = $role['landing_module_key'] ?? null;

        if (!is_string($landingModuleKey) || trim($landingModuleKey) === '') {
            throw new RuntimeException('Landing module not configured');
        }

        return [
            'landing_module_key' => $landingModuleKey,
            'landing_route' => $this->mapLandingModuleToRoute($landingModuleKey)
        ];
    }

    /**
     * v1 landing route map from the API contract.
     */
    private function mapLandingModuleToRoute(string $landingModuleKey): string
    {
        $map = [
            'dashboard.master_ops' => 'dashboard/master',
            'green_belt.watering_oversight' => 'oversight/watering',
            'green_belt.supervisor_upload' => 'upload/supervisor',
            'green_belt.outsourced_upload' => 'upload/outsourced',
            'monitoring.upload' => 'monitoring/upload',
            'task.my_tasks' => 'task/my',
            'task.progress_read' => 'taskprogress/list',
            'green_belt.authority_view' => 'authority/view',
            'dashboard.management' => 'dashboard/management',
        ];

        if (!isset($map[$landingModuleKey])) {
            throw new RuntimeException('Landing route not configured');
        }

        return $map[$landingModuleKey];
    }
}
