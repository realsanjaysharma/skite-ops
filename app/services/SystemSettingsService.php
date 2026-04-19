<?php

require_once __DIR__ . '/../repositories/SystemSettingsRepository.php';
require_once __DIR__ . '/AuditService.php';

class SystemSettingsService
{
    private $repository;

    public function __construct()
    {
        $this->repository = new SystemSettingsRepository();
    }

    public function listSettings(): array
    {
        $settings = $this->repository->getAllSettings();
        
        foreach ($settings as &$setting) {
            // Format output smartly based on type
            if ($setting['value_type'] === 'BOOLEAN') {
                $setting['setting_value'] = ($setting['setting_value'] === '1' || strtolower((string)$setting['setting_value']) === 'true');
            } elseif ($setting['value_type'] === 'INTEGER') {
                $setting['setting_value'] = $setting['setting_value'] !== null ? (int)$setting['setting_value'] : null;
            } elseif ($setting['value_type'] === 'JSON') {
                $setting['setting_value'] = $setting['setting_value'] !== null ? json_decode($setting['setting_value'], true) : null;
            }
        }
        
        return $settings;
    }

    public function updateSetting(string $key, $value, int $actorId): void
    {
        $existing = $this->repository->getSettingByKey($key);
        if (!$existing) {
            throw new InvalidArgumentException("Setting key not found");
        }

        // Validate and format value based on type
        $stringValue = null;

        if ($existing['value_type'] === 'BOOLEAN') {
            $stringValue = (bool)$value ? '1' : '0';
        } elseif ($existing['value_type'] === 'INTEGER') {
            if ($value !== null && $value !== '') {
                if (!is_numeric($value)) {
                    throw new InvalidArgumentException("Value must be an integer");
                }
                $stringValue = (string)(int)$value;
            }
        } elseif ($existing['value_type'] === 'JSON') {
            if ($value !== null && $value !== '') {
                if (is_array($value)) {
                    $stringValue = json_encode($value);
                } else {
                    json_decode($value);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new InvalidArgumentException("Value must be valid JSON");
                    }
                    $stringValue = $value;
                }
            }
        } else {
            // STRING type
            $stringValue = $value !== null ? (string)$value : null;
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->updateSetting($key, $stringValue);
            
            // Log update
            $auditService = new AuditService();
            $auditService->logAction(
                $actorId,
                'UPDATE_SETTING',
                'system_settings',
                (int)$existing['id'],
                ['setting_key' => $key, 'old_value' => $existing['setting_value']],
                ['setting_key' => $key, 'new_value' => $stringValue]
            );

            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }
}
