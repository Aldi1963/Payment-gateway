<?php
/**
 * White Label Service
 * Per-merchant branding for checkout pages
 * 
 * Merchants can customize: logo, colors, favicon, custom domain
 */

require_once base_path('app/Database.php');

class WhiteLabelService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Get branding config for a merchant
     */
    public function getBranding(string $merchantId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM `merchant_branding` WHERE `merchant_id` = :mid LIMIT 1");
        $stmt->execute(['mid' => $merchantId]);
        $row = $stmt->fetch();

        if (!$row) {
            return $this->getDefaults();
        }
        return array_merge($this->getDefaults(), array_filter($row));
    }

    /**
     * Save branding config
     */
    public function saveBranding(string $merchantId, array $data): array
    {
        $existing = $this->db->prepare("SELECT id FROM `merchant_branding` WHERE `merchant_id`=:mid");
        $existing->execute(['mid' => $merchantId]);

        $fields = [
            'logo_url' => sanitize($data['logo_url'] ?? ''),
            'favicon_url' => sanitize($data['favicon_url'] ?? ''),
            'primary_color' => sanitize($data['primary_color'] ?? '#2563eb'),
            'secondary_color' => sanitize($data['secondary_color'] ?? '#10b981'),
            'background_color' => sanitize($data['background_color'] ?? '#f8fafc'),
            'text_color' => sanitize($data['text_color'] ?? '#1e293b'),
            'button_color' => sanitize($data['button_color'] ?? '#2563eb'),
            'button_text_color' => sanitize($data['button_text_color'] ?? '#ffffff'),
            'custom_css' => $data['custom_css'] ?? '',
            'custom_domain' => sanitize($data['custom_domain'] ?? ''),

            'footer_text' => sanitize($data['footer_text'] ?? ''),
            'show_powered_by' => isset($data['show_powered_by']) ? 1 : 0,
            'updated_at' => now(),
        ];

        if ($existing->fetch()) {
            $sets = implode(', ', array_map(fn($k) => "`{$k}` = :{$k}", array_keys($fields)));
            $fields['_mid'] = $merchantId;
            $this->db->prepare("UPDATE `merchant_branding` SET {$sets} WHERE `merchant_id` = :_mid")->execute($fields);
        } else {
            $fields['id'] = generate_uuid();
            $fields['merchant_id'] = $merchantId;
            $fields['created_at'] = now();
            $cols = implode(',', array_map(fn($k) => "`{$k}`", array_keys($fields)));
            $vals = implode(',', array_map(fn($k) => ":{$k}", array_keys($fields)));
            $this->db->prepare("INSERT INTO `merchant_branding` ({$cols}) VALUES ({$vals})")->execute($fields);
        }

        return ['success' => true, 'message' => 'Branding berhasil disimpan.'];
    }

    /**
     * Get default branding
     */
    private function getDefaults(): array
    {
        return [
            'logo_url' => '',
            'favicon_url' => '',
            'primary_color' => '#2563eb',
            'secondary_color' => '#10b981',
            'background_color' => '#f8fafc',
            'text_color' => '#1e293b',
            'button_color' => '#2563eb',
            'button_text_color' => '#ffffff',
            'custom_css' => '',
            'custom_domain' => '',
            'footer_text' => '',
            'show_powered_by' => 1,
        ];
    }

    /**
     * Resolve merchant from custom domain
     */
    public function resolveDomain(string $domain): ?string
    {
        $stmt = $this->db->prepare("SELECT `merchant_id` FROM `merchant_branding` WHERE `custom_domain` = :domain AND `custom_domain` != '' LIMIT 1");
        $stmt->execute(['domain' => $domain]);
        $row = $stmt->fetch();
        return $row ? $row['merchant_id'] : null;
    }
}
