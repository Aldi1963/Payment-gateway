<?php
/**
 * Virtual Account Payment Channel
 * Supports: BCA VA, BNI VA, BRI VA, Mandiri VA, Permata VA
 * 
 * Note: Requires bank VA API integration (configurable per provider)
 */

require_once base_path('app/Interfaces/PaymentChannelInterface.php');

class VirtualAccountChannel implements PaymentChannelInterface
{
    public function getChannelCode(): string { return 'va'; }
    public function getChannelName(): string { return 'Virtual Account'; }

    public function isEnabled(): bool
    {
        return setting('channel_va_enabled', '0') === '1';
    }

    public function getSupportedMethods(): array
    {
        $methods = [];
        $banks = ['bca' => 'BCA', 'bni' => 'BNI', 'bri' => 'BRI', 'mandiri' => 'Mandiri', 'permata' => 'Permata'];
        foreach ($banks as $code => $name) {
            if (setting("va_{$code}_enabled", '0') === '1') {
                $methods[] = ['code' => "va_{$code}", 'name' => "VA {$name}", 'icon' => $code];
            }
        }
        return $methods;
    }

    public function createPayment(array $payload): array
    {
        $bank = $payload['bank_code'] ?? 'bca';
        $apiUrl = setting("va_{$bank}_api_url", '');
        $apiKey = setting("va_{$bank}_api_key", '');

        if (empty($apiUrl) || empty($apiKey)) {
            return ['success' => false, 'error' => "VA {$bank} not configured"];
        }

        // Standard VA creation request
        $requestData = [
            'order_id' => $payload['order_id'],
            'amount' => $payload['amount'],
            'customer_name' => $payload['customer_name'] ?? '',
            'bank_code' => strtoupper($bank),
            'expiry_minutes' => $payload['expiry_minutes'] ?? 60,
        ];

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true) ?: [];

        if ($httpCode >= 200 && $httpCode < 300 && !empty($data)) {
            return [
                'success' => true,
                'payment_url' => $data['payment_url'] ?? null,
                'qr_url' => null,
                'va_number' => $data['va_number'] ?? $data['account_number'] ?? '',
                'reference_id' => $data['reference_id'] ?? $data['id'] ?? '',
                'raw_response' => $response,
            ];
        }

        return ['success' => false, 'error' => $data['message'] ?? "HTTP {$httpCode}", 'raw_response' => $response];
    }

    public function checkStatus(string $referenceId): array
    {
        return ['status' => 'PENDING', 'paid_at' => null];
    }

    public function validateWebhook(string $rawPayload, array $headers): bool
    {
        return !empty($headers['HTTP_X_SIGNATURE'] ?? '');
    }

    public function parseWebhook(string $rawPayload): array
    {
        $data = json_decode($rawPayload, true) ?: [];
        return [
            'order_id' => $data['order_id'] ?? $data['external_id'] ?? '',
            'status' => strtoupper($data['status'] ?? 'PENDING'),
            'amount' => (int)($data['amount'] ?? 0),
            'paid_at' => $data['paid_at'] ?? $data['payment_time'] ?? null,
        ];
    }
}
