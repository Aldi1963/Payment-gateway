<?php
/**
 * E-Wallet Payment Channel
 * Supports: DANA, OVO, GoPay, ShopeePay, LinkAja
 */

require_once base_path('app/Interfaces/PaymentChannelInterface.php');

class EWalletChannel implements PaymentChannelInterface
{
    public function getChannelCode(): string { return 'ewallet'; }
    public function getChannelName(): string { return 'E-Wallet'; }

    public function isEnabled(): bool
    {
        return setting('channel_ewallet_enabled', '0') === '1';
    }

    public function getSupportedMethods(): array
    {
        $methods = [];
        $wallets = ['dana'=>'DANA','ovo'=>'OVO','gopay'=>'GoPay','shopeepay'=>'ShopeePay','linkaja'=>'LinkAja'];
        foreach ($wallets as $code => $name) {
            if (setting("ewallet_{$code}_enabled", '0') === '1') {
                $methods[] = ['code' => "ewallet_{$code}", 'name' => $name, 'icon' => $code];
            }
        }
        return $methods;
    }

    public function createPayment(array $payload): array
    {
        $wallet = $payload['wallet_code'] ?? 'dana';
        $apiUrl = setting("ewallet_{$wallet}_api_url", '');
        $apiKey = setting("ewallet_{$wallet}_api_key", '');

        if (empty($apiUrl) || empty($apiKey)) {
            return ['success' => false, 'error' => "E-Wallet {$wallet} not configured"];
        }

        $requestData = [
            'order_id' => $payload['order_id'],
            'amount' => $payload['amount'],
            'customer_phone' => $payload['customer_phone'] ?? '',
            'wallet_type' => strtoupper($wallet),
            'callback_url' => $payload['callback_url'] ?? '',
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
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'payment_url' => $data['checkout_url'] ?? $data['payment_url'] ?? '',
                'qr_url' => $data['qr_url'] ?? null,
                'reference_id' => $data['reference_id'] ?? $data['id'] ?? '',
                'raw_response' => $response,
            ];
        }
        return ['success' => false, 'error' => $data['message'] ?? "HTTP {$httpCode}", 'raw_response' => $response];
    }

    public function checkStatus(string $referenceId): array
    {
        // Determine which wallet provider based on reference pattern or settings
        $wallets = ['dana', 'ovo', 'gopay', 'shopeepay', 'linkaja'];
        
        foreach ($wallets as $wallet) {
            $apiUrl = setting("ewallet_{$wallet}_api_url", '');
            $apiKey = setting("ewallet_{$wallet}_api_key", '');
            
            if (empty($apiUrl) || empty($apiKey)) {
                continue;
            }

            // Build status check URL
            $statusUrl = rtrim($apiUrl, '/') . '/status/' . urlencode($referenceId);

            $ch = curl_init($statusUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                ],
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                $data = json_decode($response, true) ?: [];
                if (!empty($data['status'])) {
                    $status = strtoupper($data['status']);
                    $mappedStatus = match($status) {
                        'SUCCESS', 'COMPLETED', 'SETTLEMENT', 'PAID' => 'PAID',
                        'PENDING', 'PROCESSING' => 'PENDING',
                        'FAILED', 'DENIED', 'CANCELLED' => 'FAILED',
                        'EXPIRED' => 'EXPIRED',
                        'REFUNDED' => 'REFUNDED',
                        default => 'PENDING',
                    };
                    return [
                        'status' => $mappedStatus,
                        'paid_at' => $data['paid_at'] ?? $data['completed_at'] ?? null,
                        'raw_response' => $response,
                    ];
                }
            }
        }

        // Fallback: could not determine status
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
            'paid_at' => $data['paid_at'] ?? null,
        ];
    }
}
