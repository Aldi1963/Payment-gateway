<?php
/**
 * Pagination Service
 * Provides consistent pagination for API responses
 * 
 * Supports:
 * - Offset-based pagination (page, per_page)
 * - Cursor-based pagination for large datasets
 * - Consistent response format across all API endpoints
 */

class PaginationService
{
    private int $defaultPerPage = 20;
    private int $maxPerPage = 100;

    /**
     * Parse pagination parameters from request
     * 
     * @return array ['page', 'per_page', 'offset']
     */
    public function parseParams(): array
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = (int)($_GET['per_page'] ?? $_GET['limit'] ?? $this->defaultPerPage);
        $perPage = max(1, min($perPage, $this->maxPerPage));
        $offset = ($page - 1) * $perPage;

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => $offset,
        ];
    }

    /**
     * Parse sort parameters from request
     * 
     * @param array $allowedFields Whitelist of sortable fields
     * @param string $defaultSort Default sort field
     * @param string $defaultOrder Default sort order (asc/desc)
     * @return array ['sort_by', 'sort_order']
     */
    public function parseSortParams(array $allowedFields, string $defaultSort = 'created_at', string $defaultOrder = 'desc'): array
    {
        $sortBy = $_GET['sort_by'] ?? $_GET['sort'] ?? $defaultSort;
        $sortOrder = strtolower($_GET['sort_order'] ?? $_GET['order'] ?? $defaultOrder);

        // Validate sort field against whitelist
        if (!in_array($sortBy, $allowedFields)) {
            $sortBy = $defaultSort;
        }

        // Validate sort order
        if (!in_array($sortOrder, ['asc', 'desc'])) {
            $sortOrder = $defaultOrder;
        }

        return [
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * Parse filter parameters from request
     * 
     * @param array $allowedFilters Whitelist of filterable fields
     * @return array Associative array of filter => value
     */
    public function parseFilters(array $allowedFilters): array
    {
        $filters = [];
        foreach ($allowedFilters as $field) {
            if (isset($_GET[$field]) && $_GET[$field] !== '') {
                $filters[$field] = $_GET[$field];
            }
        }

        // Date range filters
        if (isset($_GET['date_from']) && $_GET['date_from'] !== '') {
            $filters['date_from'] = $_GET['date_from'];
        }
        if (isset($_GET['date_to']) && $_GET['date_to'] !== '') {
            $filters['date_to'] = $_GET['date_to'];
        }

        return $filters;
    }

    /**
     * Build paginated response with metadata
     * 
     * @param array $data The paginated data items
     * @param int $totalCount Total number of items (before pagination)
     * @param int $page Current page number
     * @param int $perPage Items per page
     * @return array Formatted pagination response
     */
    public function buildResponse(array $data, int $totalCount, int $page, int $perPage): array
    {
        $totalPages = (int)max(1, ceil($totalCount / $perPage));
        
        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_items' => $totalCount,
                'total_pages' => $totalPages,
                'has_previous' => $page > 1,
                'has_next' => $page < $totalPages,
                'first_page' => 1,
                'last_page' => $totalPages,
            ],
        ];
    }

    /**
     * Apply pagination to a SQL query using PDO
     * Returns the paginated results and total count
     * 
     * @param \PDO $db Database connection
     * @param string $baseQuery The base SELECT query (without LIMIT/OFFSET)
     * @param string $countQuery The COUNT query for total
     * @param array $params Query parameters
     * @param int $page Page number
     * @param int $perPage Items per page
     * @param string $orderBy ORDER BY clause (e.g., "created_at DESC")
     * @return array ['items' => array, 'total' => int]
     */
    public function queryPaginated(\PDO $db, string $baseQuery, string $countQuery, array $params, int $page, int $perPage, string $orderBy = 'created_at DESC'): array
    {
        // Get total count
        $countStmt = $db->prepare($countQuery);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Get paginated data
        $offset = ($page - 1) * $perPage;
        $paginatedQuery = $baseQuery . " ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}";
        $dataStmt = $db->prepare($paginatedQuery);
        $dataStmt->execute($params);
        $items = $dataStmt->fetchAll() ?: [];

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * Set default per page value
     */
    public function setDefaultPerPage(int $perPage): self
    {
        $this->defaultPerPage = $perPage;
        return $this;
    }

    /**
     * Set maximum per page value
     */
    public function setMaxPerPage(int $maxPerPage): self
    {
        $this->maxPerPage = $maxPerPage;
        return $this;
    }
}
