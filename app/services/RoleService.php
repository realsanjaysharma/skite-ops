<?php

require_once __DIR__ . '/../repositories/RbacRepository.php';
require_once __DIR__ . '/AuditService.php';
require_once __DIR__ . '/RbacService.php';
require_once __DIR__ . '/../../config/database.php';

class RoleService
{
    private RbacRepository $rbacRepository;
    private RbacService $rbacService;
    private AuditService $auditService;
    private PDO $db;

    public function __construct()
    {
        $this->rbacRepository = new RbacRepository();
        $this->rbacService = new RbacService();
        $this->auditService = new AuditService();
        $this->db = Database::getConnection();
    }

    public function getAllRoles(array $filters = []): array
    {
        $result = $this->rbacRepository->listRoles($filters);

        $items = array_map(
            fn(array $role): array => $this->formatRoleListItem($role),
            $result['items']
        );

        return [
            'items' => $items,
            'pagination' => [
                'page' => $result['page'],
                'limit' => $result['limit'],
                'total' => $result['total'],
            ],
        ];
    }

    public function getRoleById($roleId): array
    {
        $roleContext = $this->rbacService->getRoleAccessContext($this->validateRoleId($roleId));

        return [
            'role' => $this->formatRoleDetail($roleContext),
            'allowed_module_keys' => $roleContext['allowed_module_keys'],
            'permission_group' => [
                'id' => $roleContext['permission_group_id'],
                'group_key' => $roleContext['permission_group_key'],
                'group_name' => $roleContext['permission_group_name'],
            ],
        ];
    }

    public function createRole(array $data, int $actorUserId): array
    {
        $this->validateActorUserId($actorUserId);
        $validated = $this->validateCreatePayload($data);

        if ($this->rbacRepository->roleKeyExists($validated['role_key'])) {
            throw new InvalidArgumentException('Role key already exists');
        }

        if ($this->rbacRepository->roleNameExists($validated['role_name'])) {
            throw new InvalidArgumentException('Role name already exists');
        }

        $this->assertPermissionGroupExists($validated['permission_group_id']);

        $this->db->beginTransaction();

        try {
            $roleId = $this->rbacRepository->createRole([
                'role_key' => $validated['role_key'],
                'role_name' => $validated['role_name'],
                'description' => $validated['description'],
                'landing_module_key' => $validated['landing_module_key'],
                'created_by_user_id' => $actorUserId,
            ]);

            $this->rbacRepository->upsertRolePermissionMapping($roleId, $validated['permission_group_id']);
            $this->replaceRoleModuleScopes($roleId, $validated['module_keys']);

            $roleResponse = $this->getRoleById($roleId);

            $this->auditService->logAction(
                $actorUserId,
                'ROLE_CREATED',
                'ROLE',
                $roleId,
                null,
                [
                    'role_key' => $validated['role_key'],
                    'role_name' => $validated['role_name'],
                    'description' => $validated['description'],
                    'permission_group_id' => $validated['permission_group_id'],
                    'landing_module_key' => $validated['landing_module_key'],
                    'module_keys' => $validated['module_keys'],
                ]
            );

            $this->db->commit();

            return $roleResponse;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    public function updateRole(array $data, int $actorUserId): array
    {
        $this->validateActorUserId($actorUserId);
        $validated = $this->validateUpdatePayload($data);
        $existingRole = $this->getRoleById($validated['role_id']);

        if ($this->rbacRepository->roleNameExists($validated['role_name'], $validated['role_id'])) {
            throw new InvalidArgumentException('Role name already exists');
        }

        $this->assertPermissionGroupExists($validated['permission_group_id']);

        $this->db->beginTransaction();

        try {
            $updated = $this->rbacRepository->updateRole($validated['role_id'], [
                'role_name' => $validated['role_name'],
                'description' => $validated['description'],
                'landing_module_key' => $validated['landing_module_key'],
            ]);

            if (!$updated) {
                throw new RuntimeException('Failed to update role');
            }

            $this->rbacRepository->upsertRolePermissionMapping($validated['role_id'], $validated['permission_group_id']);
            $this->replaceRoleModuleScopes($validated['role_id'], $validated['module_keys']);

            $roleResponse = $this->getRoleById($validated['role_id']);

            $this->auditService->logAction(
                $actorUserId,
                'ROLE_UPDATED',
                'ROLE',
                $validated['role_id'],
                [
                    'role' => $existingRole['role'],
                    'allowed_module_keys' => $existingRole['allowed_module_keys'],
                    'permission_group' => $existingRole['permission_group'],
                ],
                [
                    'role_name' => $validated['role_name'],
                    'description' => $validated['description'],
                    'permission_group_id' => $validated['permission_group_id'],
                    'landing_module_key' => $validated['landing_module_key'],
                    'module_keys' => $validated['module_keys'],
                ]
            );

            $this->db->commit();

            return $roleResponse;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    private function replaceRoleModuleScopes(int $roleId, array $moduleKeys): void
    {
        $this->rbacRepository->deleteRoleModuleScopes($roleId);

        foreach ($moduleKeys as $moduleKey) {
            $this->rbacRepository->insertRoleModuleScope($roleId, $moduleKey);
        }
    }

    private function validateCreatePayload(array $data): array
    {
        $moduleKeys = $this->validateModuleKeys($data['module_keys'] ?? null);

        return [
            'role_key' => $this->validateRoleKey($data['role_key'] ?? null),
            'role_name' => $this->validateRoleName($data['role_name'] ?? null),
            'description' => $this->validateDescription($data['description'] ?? null),
            'permission_group_id' => $this->validatePermissionGroupId($data['permission_group_id'] ?? null),
            'landing_module_key' => $this->validateLandingModuleKey(
                $data['landing_module_key'] ?? null,
                $moduleKeys
            ),
            'module_keys' => $moduleKeys,
        ];
    }

    private function validateUpdatePayload(array $data): array
    {
        $moduleKeys = $this->validateModuleKeys($data['module_keys'] ?? null);

        return [
            'role_id' => $this->validateRoleId($data['role_id'] ?? null),
            'role_name' => $this->validateRoleName($data['role_name'] ?? null),
            'description' => $this->validateDescription($data['description'] ?? null),
            'permission_group_id' => $this->validatePermissionGroupId($data['permission_group_id'] ?? null),
            'landing_module_key' => $this->validateLandingModuleKey(
                $data['landing_module_key'] ?? null,
                $moduleKeys
            ),
            'module_keys' => $moduleKeys,
        ];
    }

    private function validateRoleId($roleId): int
    {
        if (filter_var($roleId, FILTER_VALIDATE_INT) === false || (int) $roleId <= 0) {
            throw new InvalidArgumentException('Role ID must be a valid positive integer.');
        }

        return (int) $roleId;
    }

    private function validateActorUserId(int $actorUserId): void
    {
        if ($actorUserId <= 0) {
            throw new InvalidArgumentException('Invalid actor user ID');
        }
    }

    private function validateRoleKey($roleKey): string
    {
        if (!is_string($roleKey)) {
            throw new InvalidArgumentException('Role key is required');
        }

        $roleKey = trim($roleKey);

        if ($roleKey === '') {
            throw new InvalidArgumentException('Role key is required');
        }

        if (strlen($roleKey) > 100) {
            throw new InvalidArgumentException('Role key is too long');
        }

        return $roleKey;
    }

    private function validateRoleName($roleName): string
    {
        if (!is_string($roleName)) {
            throw new InvalidArgumentException('Role name is required');
        }

        $roleName = trim($roleName);

        if ($roleName === '') {
            throw new InvalidArgumentException('Role name is required');
        }

        if (strlen($roleName) > 150) {
            throw new InvalidArgumentException('Role name is too long');
        }

        return $roleName;
    }

    private function validateDescription($description): ?string
    {
        if ($description === null || $description === '') {
            return null;
        }

        if (!is_string($description)) {
            throw new InvalidArgumentException('Description must be a string');
        }

        return trim($description);
    }

    private function validatePermissionGroupId($permissionGroupId): int
    {
        if (filter_var($permissionGroupId, FILTER_VALIDATE_INT) === false || (int) $permissionGroupId <= 0) {
            throw new InvalidArgumentException('permission_group_id must be a valid positive integer');
        }

        return (int) $permissionGroupId;
    }

    private function validateModuleKeys($moduleKeys): array
    {
        if (!is_array($moduleKeys) || $moduleKeys === []) {
            throw new InvalidArgumentException('At least one module key is required');
        }

        $approvedModuleKeys = $this->rbacService->getApprovedModuleCatalog();
        $normalized = [];

        foreach ($moduleKeys as $moduleKey) {
            if (!is_string($moduleKey)) {
                throw new InvalidArgumentException('Module keys must be strings');
            }

            $moduleKey = trim($moduleKey);

            if ($moduleKey === '') {
                throw new InvalidArgumentException('Module key cannot be empty');
            }

            if (!in_array($moduleKey, $approvedModuleKeys, true)) {
                throw new InvalidArgumentException('Unsupported module key: ' . $moduleKey);
            }

            $normalized[$moduleKey] = $moduleKey;
        }

        return array_values($normalized);
    }

    private function validateLandingModuleKey($landingModuleKey, array $moduleKeys): string
    {
        if (!is_string($landingModuleKey)) {
            throw new InvalidArgumentException('landing_module_key is required');
        }

        $landingModuleKey = trim($landingModuleKey);

        if ($landingModuleKey === '') {
            throw new InvalidArgumentException('landing_module_key is required');
        }

        if (!in_array($landingModuleKey, $moduleKeys, true)) {
            throw new InvalidArgumentException('Landing module must be included in module_keys');
        }

        $this->rbacService->mapLandingModuleToRoute($landingModuleKey);

        return $landingModuleKey;
    }

    private function assertPermissionGroupExists(int $permissionGroupId): void
    {
        if ($this->rbacRepository->getPermissionGroupById($permissionGroupId) === null) {
            throw new InvalidArgumentException('Invalid permission_group_id');
        }
    }

    private function formatRoleListItem(array $role): array
    {
        return [
            'id' => (int) $role['id'],
            'role_key' => $role['role_key'],
            'role_name' => $role['role_name'],
            'is_system_role' => (bool) $role['is_system_role'],
            'is_active' => (bool) $role['is_active'],
            'permission_group_key' => $role['permission_group_key'],
            'landing_module_key' => $role['landing_module_key'],
        ];
    }

    private function formatRoleDetail(array $roleContext): array
    {
        return [
            'id' => $roleContext['role_id'],
            'role_key' => $roleContext['role_key'],
            'role_name' => $roleContext['role_name'],
            'description' => $roleContext['role_description'],
            'is_system_role' => $roleContext['is_system_role'],
            'is_active' => $roleContext['is_active'],
            'landing_module_key' => $roleContext['landing_module_key'],
            'landing_route' => $roleContext['landing_route'],
        ];
    }
}
