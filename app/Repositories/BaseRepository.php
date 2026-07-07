<?php
/**
 * Base Repository
 * MySQL/PDO storage with prepared statements
 */

require_once __DIR__ . '/../Database.php';

class BaseRepository
{
    protected string $table;
    protected PDO $db;
    protected array $jsonColumns = []; // columns that store JSON

    public function __construct(string $table)
    {
        $this->table = $table;
        $this->db = Database::getConnection();
    }

    /**
     * Find record by ID
     */
    public function find(string $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM `{$this->table}` WHERE `id` = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->decodeJsonColumns($row) : null;
    }

    /**
     * Create new record
     */
    public function create(array $record): bool
    {
        $record = $this->encodeJsonColumns($record);
        $columns = implode(', ', array_map(fn($k) => "`{$k}`", array_keys($record)));
        $placeholders = implode(', ', array_map(fn($k) => ":{$k}", array_keys($record)));
        $sql = "INSERT INTO `{$this->table}` ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($record);
    }

    /**
     * Update record by ID
     */
    public function update(string $id, array $data): bool
    {
        $data = $this->encodeJsonColumns($data);
        $sets = implode(', ', array_map(fn($k) => "`{$k}` = :{$k}", array_keys($data)));
        $data['_id'] = $id;
        $sql = "UPDATE `{$this->table}` SET {$sets} WHERE `id` = :_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    /**
     * Delete record by ID
     */
    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM `{$this->table}` WHERE `id` = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Get all records with optional filters (newest first by default)
     */
    public function findAll(array $filters = []): array
    {
        $where = [];
        $params = [];

        foreach ($filters as $key => $value) {
            if ($key === 'search') continue;
            $where[] = "`{$key}` = :{$key}";
            $params[$key] = $value;
        }

        $sql = "SELECT * FROM `{$this->table}`";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        if (!empty($filters['search'])) {
            $searchCols = $this->getSearchableColumns();
            if (!empty($searchCols)) {
                $searchWhere = array_map(fn($c) => "`{$c}` LIKE :_search", $searchCols);
                $sql .= (empty($where) ? " WHERE " : " AND ") . "(" . implode(' OR ', $searchWhere) . ")";
                $params['_search'] = '%' . $filters['search'] . '%';
            }
        }

        $sql .= " ORDER BY `created_at` DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        return array_map(fn($r) => $this->decodeJsonColumns($r), $rows);
    }

    /**
     * Count records
     */
    public function count(array $filters = []): int
    {
        return count($this->findAll($filters));
    }

    /**
     * Get searchable columns for full-text-like LIKE search
     * Override in child classes
     */
    protected function getSearchableColumns(): array
    {
        return [];
    }

    /**
     * Encode JSON columns before insert/update
     */
    protected function encodeJsonColumns(array $data): array
    {
        foreach ($this->jsonColumns as $col) {
            if (isset($data[$col]) && (is_array($data[$col]) || is_object($data[$col]))) {
                $data[$col] = json_encode($data[$col], JSON_UNESCAPED_UNICODE);
            }
        }
        return $data;
    }

    /**
     * Decode JSON columns after fetch
     */
    protected function decodeJsonColumns(array $row): array
    {
        foreach ($this->jsonColumns as $col) {
            if (isset($row[$col]) && is_string($row[$col])) {
                $decoded = json_decode($row[$col], true);
                $row[$col] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $row[$col];
            }
        }
        return $row;
    }

    /**
     * Execute raw query and return all rows
     */
    protected function query(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map(fn($r) => $this->decodeJsonColumns($r), $stmt->fetchAll());
    }

    /**
     * Execute raw statement (INSERT/UPDATE/DELETE)
     */
    protected function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Fetch a single row from raw query
     */
    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ? $this->decodeJsonColumns($row) : null;
    }

    /**
     * Fetch a single column value
     */
    protected function fetchColumn(string $sql, array $params = []): mixed
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
