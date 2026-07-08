<?php
/**
 * Payment Link Service
 * Manages reusable payment links with custom branding
 * 
 * Features:
 * - Create reusable or single-use payment links
 * - Custom slug (short URL)
 * - Fixed or variable amount
 * - Expiry dates
 * - Usage limits
 * - Custom fields (collect additional customer data)
 * - Custom branding (logo, colors, messages)
 */

require_once base_path('app/Database.php');

class PaymentLinkService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Create a new payment link
     */
    public function create(string $merchantId, array $data): array
    {
        // Validate required fields
        if (empty($data['title'])) {
            return ['success' => false, 'message' => 'Title is required'];
        }

        // Generate or validate slug
        $slug = $data['slug'] ?? $this->generateSlug($data['title']);
        $slug = $this->sanitizeSlug($slug);

        // Check slug uniqueness
        if ($this->slugExists($slug)) {
            return ['success' => false, 'message' => 'Slug already in use. Please choose another.'];
        }

        // Validate amount settings
        $isFixedAmount = (bool)($data['is_fixed_amount'] ?? true);
        $amount = $isFixedAmount ? (int)($data['amount'] ?? 0) : null;
        $minAmount = !$isFixedAmount ? (int)($data['min_amount'] ?? 1000) : null;
        $maxAmount = !$isFixedAmount ? (int)($data['max_amount'] ?? 100000000) : null;

        if ($isFixedAmount && $amount <= 0) {
            return ['success' => false, 'message' => 'Amount must be greater than 0 for fixed amount links'];
        }

        // Build branding config
        $branding = null;
        if (!empty($data['branding'])) {
            $branding = json_encode([
                'logo_url' => $data['branding']['logo_url'] ?? null,
                'primary_color' => $data['branding']['primary_color'] ?? '#3b82f6',
                'background_color' => $data['branding']['background_color'] ?? '#ffffff',
                'header_text' => $data['branding']['header_text'] ?? null,
                'footer_text' => $data['branding']['footer_text'] ?? null,
                'thank_you_message' => $data['branding']['thank_you_message'] ?? null,
            ]);
        }

        // Custom fields configuration
        $customFields = null;
        if (!empty($data['custom_fields'])) {
            $customFields = json_encode($data['custom_fields']);
        }

        $id = generate_uuid();
        $paymentLink = [
            'id' => $id,
            'merchant_id' => $merchantId,
            'title' => sanitize($data['title']),
            'description' => sanitize($data['description'] ?? ''),
            'amount' => $amount,
            'is_fixed_amount' => $isFixedAmount ? 1 : 0,
            'min_amount' => $minAmount,
            'max_amount' => $maxAmount,
            'currency' => $data['currency'] ?? 'IDR',
            'slug' => $slug,
            'is_reusable' => (int)($data['is_reusable'] ?? 1),
            'max_usage' => !empty($data['max_usage']) ? (int)$data['max_usage'] : null,
            'usage_count' => 0,
            'status' => 'active',
            'custom_fields' => $customFields,
            'redirect_url' => $data['redirect_url'] ?? null,
            'webhook_url' => $data['webhook_url'] ?? null,
            'branding' => $branding,
            'expires_at' => $data['expires_at'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $stmt = $this->db->prepare(
            "INSERT INTO `payment_links` (`id`, `merchant_id`, `title`, `description`, `amount`, `is_fixed_amount`, `min_amount`, `max_amount`, `currency`, `slug`, `is_reusable`, `max_usage`, `usage_count`, `status`, `custom_fields`, `redirect_url`, `webhook_url`, `branding`, `expires_at`, `created_at`, `updated_at`)
             VALUES (:id, :merchant_id, :title, :description, :amount, :is_fixed_amount, :min_amount, :max_amount, :currency, :slug, :is_reusable, :max_usage, :usage_count, :status, :custom_fields, :redirect_url, :webhook_url, :branding, :expires_at, :created_at, :updated_at)"
        );
        $stmt->execute([
            'id' => $paymentLink['id'],
            'merchant_id' => $paymentLink['merchant_id'],
            'title' => $paymentLink['title'],
            'description' => $paymentLink['description'],
            'amount' => $paymentLink['amount'],
            'is_fixed_amount' => $paymentLink['is_fixed_amount'],
            'min_amount' => $paymentLink['min_amount'],
            'max_amount' => $paymentLink['max_amount'],
            'currency' => $paymentLink['currency'],
            'slug' => $paymentLink['slug'],
            'is_reusable' => $paymentLink['is_reusable'],
            'max_usage' => $paymentLink['max_usage'],
            'usage_count' => $paymentLink['usage_count'],
            'status' => $paymentLink['status'],
            'custom_fields' => $paymentLink['custom_fields'],
            'redirect_url' => $paymentLink['redirect_url'],
            'webhook_url' => $paymentLink['webhook_url'],
            'branding' => $paymentLink['branding'],
            'expires_at' => $paymentLink['expires_at'],
            'created_at' => $paymentLink['created_at'],
            'updated_at' => $paymentLink['updated_at'],
        ]);

        $paymentLink['url'] = app_url("pay/{$slug}");

        return ['success' => true, 'payment_link' => $paymentLink];
    }

    /**
     * Get payment link by slug (public - for checkout page)
     */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT pl.*, m.business_name, m.email as merchant_email 
             FROM payment_links pl 
             LEFT JOIN merchants m ON m.id = pl.merchant_id 
             WHERE pl.slug = :slug"
        );
        $stmt->execute(['slug' => $slug]);
        $link = $stmt->fetch();
        
        if (!$link) return null;

        // Parse JSON fields
        if ($link['branding']) $link['branding_parsed'] = json_decode($link['branding'], true);
        if ($link['custom_fields']) $link['custom_fields_parsed'] = json_decode($link['custom_fields'], true);

        return $link;
    }

    /**
     * Use a payment link (create transaction from it)
     */
    public function useLink(string $slug, array $customerData): array
    {
        $link = $this->findBySlug($slug);
        
        if (!$link) {
            return ['success' => false, 'message' => 'Payment link not found'];
        }

        // Check if link is active
        if ($link['status'] !== 'active') {
            return ['success' => false, 'message' => 'This payment link is no longer active'];
        }

        // Check expiry
        if ($link['expires_at'] && strtotime($link['expires_at']) < time()) {
            $this->updateStatus($link['id'], 'expired');
            return ['success' => false, 'message' => 'This payment link has expired'];
        }

        // Check usage limit
        if ($link['max_usage'] && (int)$link['usage_count'] >= (int)$link['max_usage']) {
            $this->updateStatus($link['id'], 'inactive');
            return ['success' => false, 'message' => 'This payment link has reached its usage limit'];
        }

        // Determine amount
        $amount = $link['is_fixed_amount'] ? (int)$link['amount'] : (int)($customerData['amount'] ?? 0);
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Invalid amount'];
        }

        // Validate variable amount range
        if (!$link['is_fixed_amount']) {
            if ($link['min_amount'] && $amount < (int)$link['min_amount']) {
                return ['success' => false, 'message' => 'Amount below minimum: ' . format_currency((int)$link['min_amount'])];
            }
            if ($link['max_amount'] && $amount > (int)$link['max_amount']) {
                return ['success' => false, 'message' => 'Amount exceeds maximum: ' . format_currency((int)$link['max_amount'])];
            }
        }

        // Create transaction
        require_once base_path('app/Services/TransactionService.php');
        $transactionService = new TransactionService();
        $result = $transactionService->create([
            'amount' => $amount,
            'link_name' => $link['title'],
            'customer_name' => $customerData['customer_name'] ?? '',
            'customer_email' => $customerData['customer_email'] ?? '',
            'customer_wa' => $customerData['customer_wa'] ?? $customerData['customer_phone'] ?? '',
            'webhook_url' => $link['webhook_url'] ?? '',
            'redirect_url' => $link['redirect_url'] ?? '',
            'note' => 'Created from payment link: ' . $link['slug'],
        ], $link['merchant_id']);

        if ($result['success']) {
            // Increment usage count
            $this->db->prepare("UPDATE payment_links SET usage_count = usage_count + 1, updated_at = NOW() WHERE id = :id")
                ->execute(['id' => $link['id']]);

            // Deactivate if single-use
            if (!$link['is_reusable']) {
                $this->updateStatus($link['id'], 'inactive');
            }
        }

        return $result;
    }

    /**
     * Get payment links for a merchant
     */
    public function getByMerchant(string $merchantId, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM payment_links WHERE merchant_id = :mid ORDER BY created_at DESC LIMIT :lim"
        );
        $stmt->bindValue(':mid', $merchantId);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $links = $stmt->fetchAll() ?: [];

        foreach ($links as &$link) {
            $link['url'] = app_url("pay/{$link['slug']}");
        }

        return $links;
    }

    /**
     * Update payment link
     */
    public function update(string $id, string $merchantId, array $data): array
    {
        $link = $this->find($id);
        if (!$link || $link['merchant_id'] !== $merchantId) {
            return ['success' => false, 'message' => 'Payment link not found'];
        }

        $updates = [];
        $params = ['id' => $id];
        $allowedFields = ['title', 'description', 'amount', 'min_amount', 'max_amount', 'redirect_url', 'webhook_url', 'expires_at', 'status'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "`{$field}` = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        if (!empty($data['branding'])) {
            $updates[] = "`branding` = :branding";
            $params['branding'] = json_encode($data['branding']);
        }

        if (empty($updates)) {
            return ['success' => false, 'message' => 'No fields to update'];
        }

        $updates[] = "`updated_at` = NOW()";
        $sql = "UPDATE payment_links SET " . implode(', ', $updates) . " WHERE id = :id";
        $this->db->prepare($sql)->execute($params);

        return ['success' => true, 'message' => 'Payment link updated'];
    }

    /**
     * Delete payment link
     */
    public function delete(string $id, string $merchantId): array
    {
        $stmt = $this->db->prepare("DELETE FROM payment_links WHERE id = :id AND merchant_id = :mid");
        $stmt->execute(['id' => $id, 'mid' => $merchantId]);
        return ['success' => $stmt->rowCount() > 0, 'message' => $stmt->rowCount() > 0 ? 'Deleted' : 'Not found'];
    }

    /**
     * Find payment link by ID
     */
    public function find(string $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM payment_links WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Update link status
     */
    private function updateStatus(string $id, string $status): void
    {
        $this->db->prepare("UPDATE payment_links SET status = :status, updated_at = NOW() WHERE id = :id")
            ->execute(['status' => $status, 'id' => $id]);
    }

    /**
     * Check if slug exists
     */
    private function slugExists(string $slug): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM payment_links WHERE slug = :slug");
        $stmt->execute(['slug' => $slug]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Generate slug from title
     */
    private function generateSlug(string $title): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        $slug = substr($slug, 0, 50);
        
        // Add random suffix to avoid conflicts
        $slug .= '-' . substr(generate_random(6), 0, 6);
        
        return $slug;
    }

    /**
     * Sanitize slug input
     */
    private function sanitizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        return substr($slug, 0, 100);
    }
}
