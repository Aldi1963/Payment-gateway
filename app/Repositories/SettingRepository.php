<?php
/**
 * Setting Repository
 */

require_once __DIR__ . '/BaseRepository.php';

class SettingRepository extends BaseRepository
{
    protected array $jsonColumns = [];

    public function __construct()
    {
        parent::__construct('settings');
    }

    /**
     * Get a setting by key
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $row = $this->fetchOne("SELECT `value` FROM `{$this->table}` WHERE `key` = :key LIMIT 1", [
            'key' => $key
        ]);
        return $row ? ($row['value'] ?? $default) : $default;
    }

    /**
     * Set a setting value (insert or update)
     */
    public function set(string $key, mixed $value): bool
    {
        $now = now();
        $id = generate_uuid();
        $sql = "INSERT INTO `{$this->table}` (`id`, `key`, `value`, `created_at`, `updated_at`) 
                VALUES (:id, :key, :value, :created_at, :updated_at) 
                ON DUPLICATE KEY UPDATE `value` = :value2, `updated_at` = :updated_at2";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'key' => $key,
            'value' => (string)$value,
            'created_at' => $now,
            'updated_at' => $now,
            'value2' => (string)$value,
            'updated_at2' => $now,
        ]);
    }

    /**
     * Get all settings as key-value pairs
     */
    public function getAllSettings(): array
    {
        $stmt = $this->db->prepare("SELECT `key`, `value` FROM `{$this->table}`");
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['key']] = $row['value'];
        }
        return $settings;
    }
}
