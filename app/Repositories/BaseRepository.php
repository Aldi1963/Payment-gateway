<?php
/**
 * Base Repository
 * JSON file storage with LOCK_EX, validation, backup, and error handling
 */

class BaseRepository
{
    protected string $file;
    protected string $storageDir;

    public function __construct(string $filename)
    {
        $this->storageDir = dirname(__DIR__, 2) . '/storage';
        $this->file = $this->storageDir . '/' . $filename;
        $this->ensureFile();
    }

    /**
     * Ensure storage file exists
     */
    protected function ensureFile(): void
    {
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
        if (!file_exists($this->file)) {
            file_put_contents($this->file, '[]', LOCK_EX);
        }
    }

    /**
     * Read all records from file
     */
    protected function readAll(): array
    {
        $content = file_get_contents($this->file);
        
        if ($content === false || trim($content) === '') {
            return [];
        }

        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try backup
            $backupFile = $this->file . '.backup';
            if (file_exists($backupFile)) {
                $backupContent = file_get_contents($backupFile);
                $data = json_decode($backupContent, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    // Restore from backup
                    file_put_contents($this->file, $backupContent, LOCK_EX);
                    app_log("Restored {$this->file} from backup", 'WARNING');
                    return $data;
                }
            }
            // If backup also fails, reset
            app_log("JSON corrupt in {$this->file}, resetting", 'ERROR');
            file_put_contents($this->file, '[]', LOCK_EX);
            return [];
        }

        return $data;
    }

    /**
     * Write all records to file with backup
     */
    protected function writeAll(array $data): bool
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if ($json === false) {
            app_log("Failed to encode JSON for {$this->file}: " . json_last_error_msg(), 'ERROR');
            return false;
        }

        // Validate JSON before writing
        $testDecode = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            app_log("JSON validation failed for {$this->file}", 'ERROR');
            return false;
        }

        // Create backup of current file
        if (file_exists($this->file) && filesize($this->file) > 2) {
            copy($this->file, $this->file . '.backup');
        }

        // Write with exclusive lock
        $result = file_put_contents($this->file, $json, LOCK_EX);
        
        if ($result === false) {
            app_log("Failed to write {$this->file}", 'ERROR');
            return false;
        }

        return true;
    }

    /**
     * Find record by ID
     */
    public function find(string $id): ?array
    {
        $records = $this->readAll();
        foreach ($records as $record) {
            if (($record['id'] ?? '') === $id) {
                return $record;
            }
        }
        return null;
    }

    /**
     * Create new record
     */
    public function create(array $record): bool
    {
        $records = $this->readAll();
        $records[] = $record;
        return $this->writeAll($records);
    }

    /**
     * Update record by ID
     */
    public function update(string $id, array $data): bool
    {
        $records = $this->readAll();
        $found = false;
        
        foreach ($records as &$record) {
            if (($record['id'] ?? '') === $id) {
                $record = array_merge($record, $data);
                $found = true;
                break;
            }
        }
        
        if (!$found) return false;
        return $this->writeAll($records);
    }

    /**
     * Delete record by ID
     */
    public function delete(string $id): bool
    {
        $records = $this->readAll();
        $records = array_values(array_filter($records, fn($r) => ($r['id'] ?? '') !== $id));
        return $this->writeAll($records);
    }

    /**
     * Get all records with optional sorting (newest first by default)
     */
    public function findAll(array $filters = []): array
    {
        $records = $this->readAll();
        
        // Apply filters
        if (!empty($filters)) {
            $records = array_filter($records, function($record) use ($filters) {
                foreach ($filters as $key => $value) {
                    if ($key === 'search') continue;
                    if (isset($record[$key]) && $record[$key] !== $value) {
                        return false;
                    }
                }
                return true;
            });
        }

        // Search filter
        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $records = array_filter($records, function($record) use ($search) {
                $searchable = strtolower(implode(' ', array_map(function($v) {
                    return is_string($v) ? $v : '';
                }, $record)));
                return str_contains($searchable, $search);
            });
        }

        // Sort by created_at descending (newest first)
        usort($records, function($a, $b) {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });

        return array_values($records);
    }

    /**
     * Count records
     */
    public function count(array $filters = []): int
    {
        return count($this->findAll($filters));
    }
}
