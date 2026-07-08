<?php
/**
 * Midtrans Payment Channel - Core API Integration
 * 
 * Uses Midtrans Core API (NOT Snap) to get payment details directly:
 * - VA numbers displayed on our own page
 * - QRIS image/string displayed on our own page
 * - GoPay/ShopeePay deeplink on our own page
 * - No redirect to Midtrans
 * 
 * Core API Endpoint:
 *   Sandbox: https://api.sandbox.midtrans.com/v2/charge
 *   Production: https://api.midtrans.com/v2/charge
 * 
 * Webhook: Midtrans sends HTTP POST notification
 * Signature: SHA512(order_id + status_code + gross_amount + server_key)
 */

require_once base_path('app/Interfaces/PaymentChannelInterface.php');

class MidtransChannel implements PaymentChannelInterface
{
    private string $serverKey;
    private string $clientKey;
    private bool $isProduction;
    private string $apiUrl;

    public function __construct()
    {
        $this->serverKey = setting('midtrans_server_key', '');
        $this->clientKey = setting('midtrans_client_key', '');
        $this->isProduction = setting('midtrans_is_production', '0') === '1';

        $this->apiUrl = $this->isProduction
            ? 'https://api.midtrans.com/v2'
            : 'https://api.sandbox.midtrans.com/v2';
    }

    public function getChannelCode(): string { return 'midtrans'; }
    public function getChannelName(): string { return 'Midtrans'; }

    public function isEnabled(): bool
    {
        return setting('channel_midtrans_enabled', '0') === '1' && !empty($this->serverKey);
    }

    public function getSupportedMethods(): array
    {
        $methods = [];
        // Simple codes → display name, internal midtrans code for settings check
        $available = [
            'BCAVA' => ['name' => 'VA BCA', 'setting' => 'bca_va', 'icon' => 'bca'],
            'BNIVA' => ['name' => 'VA BNI', 'setting' => 'bni_va', 'icon' => 'bni'],
            'BRIVA' => ['name' => 'VA BRI', 'setting' => 'bri_va', 'icon' => 'bri'],
            'PERMATAVA' => ['name' => 'VA Permata', 'setting' => 'permata_va', 'icon' => 'permata'],
            'MANDIRI' => ['name' => 'Mandiri Bill', 'setting' => 'mandiri_bill', 'icon' => 'mandiri'],
            'CIMBVA' => ['name' => 'VA CIMB', 'setting' => 'cimb_va', 'icon' => 'cimb'],
            'GOPAY' => ['name' => 'GoPay', 'setting' => 'gopay', 'icon' => 'gopay'],
            'SHOPEEPAY' => ['name' => 'ShopeePay', 'setting' => 'shopeepay', 'icon' => 'shopeepay'],
            'MTQRIS' => ['name' => 'QRIS (Midtrans)', 'setting' => 'qris', 'icon' => 'qris_mt'],
        ];
        foreach ($available as $code => $info) {
            if (setting("midtrans_{$info['setting']}_enabled", '1') === '1') {
                $methods[] = ['code' => $code, 'name' => $info['name'], 'icon' => $info['icon']];
            }
        }
        return $methods;
    }

    /**
     * Map simple method code to internal Midtrans payment type
     */
    private function mapMethodToInternal(string $code): string
    {
        return match(strtoupper($code)) {
            'BCAVA' => 'bca_va',
            'BNIVA' => 'bni_va',
            'BRIVA' => 'bri_va',
            'PERMATAVA' => 'permata_va',
            'CIMBVA' => 'cimb_va',
            'MANDIRI' => 'mandiri_bill',
            'GOPAY' => 'gopay',
            'SHOPEEPAY' => 'shopeepay',
            'MTQRIS' => 'qris',
            // Backward compat: old midtrans_xxx codes still work
            default => str_replace('midtrans_', '', strtolower($code)),
        };
    }

    /**
     * Create payment via Midtrans Core API (/v2/charge)
     * Returns VA number, QRIS URL, or deeplink directly (no Snap redirect)
     */
    public function createPayment(array $payload): array
    {
        if (empty($this->serverKey)) {
            return ['success' => false, 'error' => 'Midtrans Server Key not configured'];
        }

        $orderId = $payload['order_id'] ?? generate_order_id();
        $amount = (int)($payload['amount'] ?? 0);
        $method = $payload['payment_method'] ?? 'BCAVA';

        // Map simple code to internal Midtrans payment type
        $methodCode = $this->mapMethodToInternal($method);

        // Build Core API charge payload
        $chargePayload = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $amount,
            ],
        ];

        // Customer details
        $customer = [];
        if (!empty($payload['customer_name'])) {
            $names = explode(' ', $payload['customer_name'], 2);
            $customer['first_name'] = $names[0];
            $customer['last_name'] = $names[1] ?? '';
        }
        if (!empty($payload['customer_email'])) {
            $customer['email'] = $payload['customer_email'];
        }
        if (!empty($payload['customer_wa'] ?? $payload['customer_phone'] ?? '')) {
            $customer['phone'] = $payload['customer_wa'] ?? $payload['customer_phone'] ?? '';
        }
        if (!empty($customer)) {
            $chargePayload['customer_details'] = $customer;
        }

        // Item details
        if (!empty($payload['link_name'])) {
            $chargePayload['item_details'] = [[
                'id' => $orderId,
                'price' => $amount,
                'quantity' => 1,
                'name' => substr($payload['link_name'], 0, 50),
            ]];
        }

        // Custom expiry
        $expiryMinutes = (int)($payload['expiry_minutes'] ?? setting('midtrans_expiry_minutes', 60));
        $chargePayload['custom_expiry'] = [
            'expiry_duration' => $expiryMinutes,
            'unit' => 'minute',
        ];

        // Set payment_type and method-specific config
        $chargePayload = $this->buildPaymentTypePayload($chargePayload, $methodCode);

        // Call Core API
        $result = $this->callCoreApi($chargePayload);

        if (!$result['success']) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Midtrans charge failed',
                'raw_response' => $result['raw_response'] ?? null,
            ];
        }

        $data = $result['data'];

        // Extract payment info based on method
        $paymentInfo = $this->extractPaymentInfo($data, $methodCode);

        return [
            'success' => true,
            'payment_url' => null, // We display on our own page
            'qr_url' => $paymentInfo['qr_url'] ?? null,
            'va_number' => $paymentInfo['va_number'] ?? null,
            'va_bank' => $paymentInfo['va_bank'] ?? null,
            'deeplink' => $paymentInfo['deeplink'] ?? null,
            'payment_code' => $paymentInfo['payment_code'] ?? null,
            'payment_type' => $data['payment_type'] ?? $methodCode,
            'midtrans_transaction_id' => $data['transaction_id'] ?? null,
            'expiry_time' => $data['expiry_time'] ?? null,
            'snap_token' => null,
            'raw_response' => json_encode($data),
        ];
    }

    /**
     * Build payment_type specific payload for Core API
     */
    private function buildPaymentTypePayload(array $payload, string $methodCode): array
    {
        switch ($methodCode) {
            case 'bca_va':
                $payload['payment_type'] = 'bank_transfer';
                $payload['bank_transfer'] = ['bank' => 'bca'];
                break;
            case 'bni_va':
                $payload['payment_type'] = 'bank_transfer';
                $payload['bank_transfer'] = ['bank' => 'bni'];
                break;
            case 'bri_va':
                $payload['payment_type'] = 'bank_transfer';
                $payload['bank_transfer'] = ['bank' => 'bri'];
                break;
            case 'permata_va':
                $payload['payment_type'] = 'bank_transfer';
                $payload['bank_transfer'] = ['bank' => 'permata'];
                break;
            case 'cimb_va':
                $payload['payment_type'] = 'bank_transfer';
                $payload['bank_transfer'] = ['bank' => 'cimb'];
                break;
            case 'mandiri_bill':
                $payload['payment_type'] = 'echannel';
                $payload['echannel'] = [
                    'bill_info1' => 'Payment',
                    'bill_info2' => 'Online Purchase',
                ];
                break;
            case 'gopay':
                $payload['payment_type'] = 'gopay';
                $payload['gopay'] = ['enable_callback' => true];
                break;
            case 'shopeepay':
                $payload['payment_type'] = 'shopeepay';
                break;
            case 'qris':
                $payload['payment_type'] = 'qris';
                break;
            default:
                // Default to QRIS
                $payload['payment_type'] = 'qris';
                break;
        }
        return $payload;
    }

    /**
     * Extract payment information from Core API response
     */
    private function extractPaymentInfo(array $data, string $methodCode): array
    {
        $info = [];

        // VA Numbers
        if (!empty($data['va_numbers']) && is_array($data['va_numbers'])) {
            $info['va_number'] = $data['va_numbers'][0]['va_number'] ?? null;
            $info['va_bank'] = strtoupper($data['va_numbers'][0]['bank'] ?? $methodCode);
        }

        // Permata VA (different format)
        if (!empty($data['permata_va_number'])) {
            $info['va_number'] = $data['permata_va_number'];
            $info['va_bank'] = 'PERMATA';
        }

        // Mandiri Bill
        if (!empty($data['bill_key'])) {
            $info['va_number'] = $data['bill_key'];
            $info['va_bank'] = 'MANDIRI';
            $info['biller_code'] = $data['biller_code'] ?? '70012';
        }

        // QRIS
        if (!empty($data['actions'])) {
            foreach ($data['actions'] as $action) {
                if ($action['name'] === 'generate-qr-code') {
                    $info['qr_url'] = $action['url'] ?? null;
                }
                if ($action['name'] === 'deeplink-redirect') {
                    $info['deeplink'] = $action['url'] ?? null;
                }
            }
        }

        // GoPay
        if (!empty($data['actions'])) {
            foreach ($data['actions'] as $action) {
                if ($action['name'] === 'generate-qr-code') {
                    $info['qr_url'] = $action['url'] ?? null;
                }
                if (in_array($action['name'], ['deeplink-redirect', 'get-status'])) {
                    if ($action['name'] === 'deeplink-redirect') {
                        $info['deeplink'] = $action['url'] ?? null;
                    }
                }
            }
        }

        // ShopeePay
        if (!empty($data['actions'])) {
            foreach ($data['actions'] as $action) {
                if ($action['name'] === 'deeplink-redirect') {
                    $info['deeplink'] = $action['url'] ?? null;
                }
            }
        }

        // Payment code (Alfamart/Indomaret)
        if (!empty($data['payment_code'])) {
            $info['payment_code'] = $data['payment_code'];
        }

        return $info;
    }

    /**
     * Call Midtrans Core API (/v2/charge)
     */
    private function callCoreApi(array $payload): array
    {
        $url = $this->apiUrl . '/charge';
        $jsonPayload = json_encode($payload);

        app_log("Midtrans Core API Request: {$jsonPayload}", 'INFO');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($this->serverKey . ':'),
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if (!empty($curlError)) {
            app_log("Midtrans Core cURL Error: {$curlError}", 'ERROR');
            return ['success' => false, 'error' => "Connection error: {$curlError}", 'raw_response' => null];
        }

        app_log("Midtrans Core Response [{$httpCode}]: {$response}", 'INFO');

        $data = json_decode($response, true) ?: [];
        $statusCode = $data['status_code'] ?? $httpCode;

        // 200 = success (capture), 201 = pending (VA/QRIS created)
        if (in_array((int)$statusCode, [200, 201])) {
            return ['success' => true, 'data' => $data, 'raw_response' => $response];
        }

        $errorMsg = '';
        if (!empty($data['status_message'])) {
            $errorMsg = $data['status_message'];
        } elseif (isset($data['error_messages']) && is_array($data['error_messages'])) {
            $errorMsg = implode(', ', $data['error_messages']);
        } else {
            $errorMsg = "HTTP {$httpCode}: " . ($data['message'] ?? 'Unknown error');
        }

        return ['success' => false, 'error' => $errorMsg, 'data' => $data, 'raw_response' => $response];
    }

    // =========================================================
    // PUBLIC UTILITY METHODS
    // =========================================================

    public function checkStatus(string $referenceId): array
    {
        $url = $this->apiUrl . '/' . urlencode($referenceId) . '/status';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($this->serverKey . ':'),
                'Content-Type: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true) ?: [];
        if ($httpCode === 200 && !empty($data)) {
            return [
                'status' => $this->mapMidtransStatus($data['transaction_status'] ?? '', $data['fraud_status'] ?? ''),
                'paid_at' => $data['settlement_time'] ?? $data['transaction_time'] ?? null,
                'payment_type' => $data['payment_type'] ?? null,
            ];
        }
        return ['status' => 'PENDING', 'paid_at' => null];
    }

    public function validateWebhook(string $rawPayload, array $headers): bool
    {
        $data = json_decode($rawPayload, true);
        if (!$data) return false;
        $signatureKey = $data['signature_key'] ?? '';
        if (empty($signatureKey)) return false;

        $calculated = hash('sha512',
            ($data['order_id'] ?? '') . ($data['status_code'] ?? '') .
            ($data['gross_amount'] ?? '') . $this->serverKey
        );
        return hash_equals($calculated, $signatureKey);
    }

    public function parseWebhook(string $rawPayload): array
    {
        $data = json_decode($rawPayload, true) ?: [];
        return [
            'order_id' => $data['order_id'] ?? '',
            'status' => $this->mapMidtransStatus($data['transaction_status'] ?? '', $data['fraud_status'] ?? 'accept'),
            'amount' => (int)($data['gross_amount'] ?? 0),
            'paid_at' => $data['settlement_time'] ?? $data['transaction_time'] ?? null,
            'payment_type' => $data['payment_type'] ?? '',
        ];
    }

    public function cancelTransaction(string $orderId): array
    {
        $url = $this->apiUrl . '/' . urlencode($orderId) . '/cancel';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Authorization: Basic ' . base64_encode($this->serverKey . ':'), 'Content-Type: application/json'],
        ]);
        $response = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $data = json_decode($response, true) ?: [];
        return ['success' => $httpCode === 200, 'message' => $data['status_message'] ?? "HTTP {$httpCode}"];
    }

    private function mapMidtransStatus(string $status, string $fraud = 'accept'): string
    {
        if ($fraud === 'deny') return 'FAILED';
        return match($status) {
            'capture' => ($fraud === 'accept') ? 'PAID' : 'PENDING',
            'settlement' => 'PAID',
            'pending' => 'PENDING',
            'deny', 'cancel' => 'FAILED',
            'expire' => 'EXPIRED',
            'refund', 'partial_refund' => 'REFUNDED',
            default => 'PENDING',
        };
    }

    public function getClientKey(): string { return $this->clientKey; }
}
