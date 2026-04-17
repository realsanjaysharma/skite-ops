<?php

require_once __DIR__ . '/../repositories/RbacRepository.php';

class RbacService
{
    private RbacRepository $rbacRepository;
    private array $rbacConfig;

    public function __construct()
    {
        $this->rbacRepository = new RbacRepository();
        $this->rbacConfig = require __DIR__ . '/../../config/rbac.php';
    }

    public function getUserAccessContext(int $userId): array
    {
        $user = $this->rbacRepository->getActiveUserIdentity($userId);

        if ($user === null) {
            throw new RuntimeException('User is not authorized');
        }

        $roleContext = $this->getRoleAccessContext((int) $user['role_id']);

        return array_merge($roleContext, [
            'user_id' => (int) $user['user_id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'force_password_reset' => (bool) $user['force_password_reset'],
        ]);
    }

    public function getRoleAccessContext(int $roleId): array
    {
        $role = $this->rbacRepository->getRoleAccessRowById($roleId);

        if ($role === null) {
            throw new RuntimeException('Role not found');
        }

        if ((int) $role['is_active'] !== 1) {
            throw new RuntimeException('Role is inactive');
        }

        $permissionGroupKey = $role['permission_group_key'] ?? null;

        if (!is_string($permissionGroupKey) || !isset($this->rbacConfig['capability_matrix'][$permissionGroupKey])) {
            throw new RuntimeException('Role permission mapping is not configured');
        }

        $allowedModuleKeys = $this->rbacRepository->getRoleModuleKeys($roleId);

        if ($allowedModuleKeys === []) {
            throw new RuntimeException('Role module scope is not configured');
        }

        foreach ($allowedModuleKeys as $moduleKey) {
            if (!$this->isApprovedModuleKey($moduleKey)) {
                throw new RuntimeException('Role contains unsupported module scope');
            }
        }

        $landingModuleKey = $role['landing_module_key'] ?? null;

        if (!is_string($landingModuleKey) || trim($landingModuleKey) === '') {
            throw new RuntimeException('Landing module is not configured');
        }

        if (!$this->isApprovedModuleKey($landingModuleKey)) {
            throw new RuntimeException('Landing module is not approved');
        }

        if (!in_array($landingModuleKey, $allowedModuleKeys, true)) {
            throw new RuntimeException('Landing module is outside allowed module scope');
        }

        return [
            'role_id' => (int) $role['id'],
            'role_key' => $role['role_key'],
            'role_name' => $role['role_name'],
            'role_description' => $role['description'],
            'permission_group_id' => (int) $role['permission_group_id'],
            'permission_group_key' => $permissionGroupKey,
            'permission_group_name' => $role['permission_group_name'],
            'landing_module_key' => $landingModuleKey,
            'landing_route' => $this->mapLandingModuleToRoute($landingModuleKey),
            'allowed_module_keys' => $allowedModuleKeys,
            'is_system_role' => (bool) $role['is_system_role'],
            'is_active' => (bool) $role['is_active'],
        ];
    }

    public function authorizeModuleAccess(array $accessContext, string $moduleKey, string $capability): void
    {
        if (!$this->isApprovedModuleKey($moduleKey)) {
            throw new RuntimeException('Unsupported module access request');
        }

        if (!in_array($moduleKey, $accessContext['allowed_module_keys'] ?? [], true)) {
            throw new RuntimeException('Module scope denied');
        }

        $permissionGroupKey = $accessContext['permission_group_key'] ?? null;

        if (!is_string($permissionGroupKey) || !$this->canPermissionGroupPerform($permissionGroupKey, $capability)) {
            throw new RuntimeException('Capability denied');
        }
    }

    public function getApprovedModuleCatalog(): array
    {
        return $this->rbacConfig['module_catalog'];
    }

    public function mapLandingModuleToRoute(string $landingModuleKey): string
    {
        $landingRoutes = $this->rbacConfig['landing_routes'];

        if (!isset($landingRoutes[$landingModuleKey])) {
            throw new RuntimeException('Landing route is not configured');
        }

        return $landingRoutes[$landingModuleKey];
    }

    public function isApprovedModuleKey(string $moduleKey): bool
    {
        return in_array($moduleKey, $this->rbacConfig['module_catalog'], true);
    }

    public function canPermissionGroupPerform(string $permissionGroupKey, string $capability): bool
    {
        $capabilityMatrix = $this->rbacConfig['capability_matrix'];

        if (!isset($capabilityMatrix[$permissionGroupKey])) {
            return false;
        }

        return in_array($capability, $capabilityMatrix[$permissionGroupKey], true);
    }
}
