<?php
/**
 * Setting Repository
 */

require_once __DIR__ . '/BaseRepository.php';

class SettingRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('settings.json');
    }

    /**
     * Get a setting by key
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $records = $this->readAll();
        foreach ($records as $record) {
            if (($record['key'] ?? '') === $key) {
                return $record['value'] ?? $default;
            }
        }
        return $default;
    }

    /**
     * Set a setting value
     */
    public function set(string $key, mixed $value): bool
    {
        $records = $this->readAll();
        $found = false;
        
        foreach ($records as &$record) {
            if (($record['key'] ?? '') === $key) {
                $record['value'] = $value;
                $record['updated_at'] = now();
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $records[] = [
                'id' => generate_uuid(),
                'key' => $key,
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        return $this->writeAll($records);
    }

    /**
     * Get all settings as key-value pairs
     */
    public function getAllSettings(): array
    {
        $records = $this->readAll();
        $settings = [];
        foreach ($records as $record) {
            $settings[$record['key']] = $record['value'];
        }
        return $settings;
    }
}
