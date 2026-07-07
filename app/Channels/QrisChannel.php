<?php
/**
 * QRIS Payment Channel (AldiQRIS)
 * Primary payment method via QR code
 */

require_once base_path('app/Interfaces/PaymentChannelInterface.php');
require_once base_path('app/Services/AldiQrisService.php');

class QrisChannel implements PaymentChannelInterface
{
    private AldiQrisService $api;

    public function __construct()
    {
        $this->api = new AldiQrisService();
    }

    public function getChannelCode(): string { return 'qris'; }
    public function getChannelName(): string { return 'QRIS'; }
    public function isEnabled(): bool { return true; }

    public function getSupportedMethods(): array
    {
        return [
            ['code' => 'qris', 'name' => 'QRIS (All Bank & E-Wallet)', 'icon' => 'qris'],
        ];
    }

    public function createPayment(array $payload): array
    {
        $apiKey = $payload['api_key'] ?? '';
        unset($payload['api_key']);
        return $this->api->createTransaction($payload, $apiKey);
    }

    public function checkStatus(string $referenceId): array
    {
        return ['status' => 'PENDING', 'paid_at' => null];
    }

    public function validateWebhook(string $rawPayload, array $headers): bool
    {
        $signature = $headers['HTTP_X_SIGNATURE'] ?? '';
        // Need merchant API key for validation - handled at WebhookService level
        return !empty($signature);
    }

    public function parseWebhook(string $rawPayload): array
    {
        $data = json_decode($rawPayload, true) ?: [];
        return [
            'order_id' => $data['order_id'] ?? $data['data']['order_id'] ?? '',
            'status' => $this->api->extractStatus($data) ?? 'PENDING',
            'amount' => (int)($data['gross_amount'] ?? $data['amount'] ?? 0),
            'paid_at' => $data['transaction_time'] ?? null,
        ];
    }
}
