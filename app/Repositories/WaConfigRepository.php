<?php
/**
 * WhatsApp Config Repository
 * Manages per-project (merchant) WhatsApp integration settings.
 */

require_once __DIR__ . '/BaseRepository.php';

class WaConfigRepository extends BaseRepository
{
    protected array $jsonColumns = [];

    public function __construct()
    {
        parent::__construct('merchant_wa_configs');
    }

    /**
     * Get WA config for a merchant/project.
     */
    public function findByMerchant(string $merchantId): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM `{$this->table}` WHERE `merchant_id` = :mid LIMIT 1",
            ['mid' => $merchantId]
        );
    }

    /**
     * Get active WA config for a merchant/project.
     */
    public function findActiveByMerchant(string $merchantId): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM `{$this->table}` WHERE `merchant_id` = :mid AND `is_active` = 1 LIMIT 1",
            ['mid' => $merchantId]
        );
    }

    /**
     * Create or update WA config for a merchant (upsert on unique merchant_id).
     */
    public function upsert(string $merchantId, array $data): bool
    {
        $existing = $this->findByMerchant($merchantId);

        if ($existing) {
            $data['updated_at'] = now();
            return $this->update($existing['id'], $data);
        }

        $data['id'] = generate_uuid();
        $data['merchant_id'] = $merchantId;
        $data['created_at'] = now();
        $data['updated_at'] = now();
        return $this->create($data);
    }

    /**
     * Increment sent counter and record last sent time / error.
     */
    public function recordSend(string $merchantId, bool $success, ?string $error = null): void
    {
        if ($success) {
            $this->execute(
                "UPDATE `{$this->table}`
                 SET `total_sent` = `total_sent` + 1, `last_sent_at` = :now, `last_error` = NULL, `updated_at` = :now
                 WHERE `merchant_id` = :mid",
                ['now' => now(), 'mid' => $merchantId]
            );
        } else {
            $this->execute(
                "UPDATE `{$this->table}`
                 SET `last_error` = :err, `updated_at` = :now
                 WHERE `merchant_id` = :mid",
                ['err' => $error, 'now' => now(), 'mid' => $merchantId]
            );
        }
    }
}
