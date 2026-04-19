<?php

require_once __DIR__ . '/BaseRepository.php';

class SystemSettingsRepository extends BaseRepository
{
    public function getAllSettings(): array
    {
        return $this->fetchAll(
            "SELECT setting_key, setting_value, value_type, description FROM system_settings ORDER BY setting_key ASC"
        );
    }

    public function getSettingByKey(string $key): ?array
    {
        $row = $this->fetchOne(
            "SELECT setting_key, setting_value, value_type, description FROM system_settings WHERE setting_key = :key",
            ['key' => $key]
        );
        return $row ?: null;
    }

    public function updateSetting(string $key, ?string $value): void
    {
        $this->execute(
            "UPDATE system_settings SET setting_value = :value WHERE setting_key = :key",
            ['key' => $key, 'value' => $value]
        );
    }
}
