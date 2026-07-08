<?php
/**
 * Midtrans Payment Channel
 * 
 * Integrates with Midtrans Snap API for:
 * - Credit/Debit Card
 * - Bank Transfer (Virtual Account): BCA, BNI, BRI, Mandiri, Permata, CIMB
 * - E-Wallet: GoPay, ShopeePay, QRIS
 * - Convenience Store: Alfamart, Indomaret
 * - Cardless Credit: Akulaku, Kredivo
 * 
 * Authentication: HTTP Basic Auth (Server Key as username, empty password)
 * 
 * Endpoints:
 *   Sandbox: https://app.sandbox.midtrans.com/snap/v1/transactions
 *   Production: https://app.midtrans.com/snap/v1/transactions
 * 
 * Webhook: Midtrans sends HTTP POST notification to configured URL
 * Signature: SHA512(order_id + status_code + gross_amount + server_key)
 * 
 * References:
 * - Snap API: https://docs.midtrans.com/reference/snap-api-overview
 * - Notification: https://docs.midtrans.com/docs/https-notification-webhooks
 */

require_once base_path('app/Interfaces/PaymentChannelInterface.php');

class MidtransChannel implements PaymentChannelInterface
{
    private string $serverKey;
    private string $clientKey;
    private bool $isProduction;
    private string $snapUrl;
    private string $apiUrl;

    public function __construct()
    {
        $this->serverKey = setting('midtrans_server_key', '');
        $this->clientKey = setting('midtrans_client_key', '');
        $this->isProduction = setting('midtrans_is_production', '0') === '1';

        $this->snapUrl = $this->isProduction
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';

        $this->apiUrl = $this->isProduction
            ? 'https://api.midtrans.com/v2'
            : 'https://api.sandbox.midtrans.com/v2';
    }

    public function getChannelCode(): string
    {
        return 'midtrans';
    }

    public function getChannelName(): string
    {
        return 'Midtrans';
    }

    public function isEnabled(): bool
    {
        return setting('channel_midtrans_enabled', '0') === '1'
            && !empty($this->serverKey);
    }

    /**
     * Get all supported payment methods
     */
    public function getSupportedMethods(): array
    {
        $methods = [];

        // Check which methods are enabled in settings
        $available = [
            'credit_card' => 'Kartu Kredit/Debit',
            'bca_va' => 'VA BCA',
            'bni_va' => 'VA BNI',
            'bri_va' => 'VA BRI',
            'permata_va' => 'VA Permata',
            'mandiri_bill' => 'Mandiri Bill',
            'cimb_va' => 'VA CIMB',
            'gopay' => 'GoPay',
            'shopeepay' => 'ShopeePay',
            'qris' => 'QRIS',
            'alfamart' => 'Alfamart',
            'indomaret' => 'Indomaret',
            'akulaku' => 'Akulaku',
            'kredivo' => 'Kredivo',
        ];

        foreach ($available as $code => $name) {
            if (setting("midtrans_{$code}_enabled", '1') === '1') {
                $methods[] = [
                    'code' => "midtrans_{$code}",
                    'name' => $name,
                    'icon' => str_replace('_va', '', str_replace('_bill', '', $code)),
                ];
            }
        }

        return $methods;
    }

    /**
     * Create payment via Midtrans Snap API
     * Returns Snap token and redirect URL
     */
    public function createPayment(array $payload): array
    {
        if (empty($this->serverKey)) {
            return ['success' => false, 'error' => 'Midtrans Server Key not configured'];
        }

        // Build Snap API request payload
        $orderId = $payload['order_id'] ?? generate_order_id();
        $amount = (int)($payload['amount'] ?? 0);

        $snapPayload = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $amount,
            ],
        ];

        // Customer details (optional but recommended)
        $customer = [];
        if (!empty($payload['customer_name'])) {
            $names = explode(' ', $payload['customer_name'], 2);
            $customer['first_name'] = $names[0];
            $customer['last_name'] = $names[1] ?? '';
        }
        if (!empty($payload['customer_email'])) {
            $customer['email'] = $payload['customer_email'];
        }
        if (!empty($payload['customer_phone'] ?? $payload['customer_wa'] ?? '')) {
            $customer['phone'] = $payload['customer_phone'] ?? $payload['customer_wa'];
        }
        if (!empty($customer)) {
            $snapPayload['customer_details'] = $customer;
        }

        // Item details (optional)
        if (!empty($payload['link_name'])) {
            $snapPayload['item_details'] = [[
                'id' => $orderId,
                'price' => $amount,
                'quantity' => 1,
                'name' => substr($payload['link_name'], 0, 50),
            ]];
        }

        // Payment methods to enable (if specific method requested)
        $enabledPayments = $this->getEnabledPaymentTypes($payload['payment_method'] ?? null);
        if (!empty($enabledPayments)) {
            $snapPayload['enabled_payments'] = $enabledPayments;
        }

        // Callbacks
        if (!empty($payload['redirect_url'])) {
            $snapPayload['callbacks'] = [
                'finish' => $payload['redirect_url'],
            ];
        }

        // Expiry
        $expiryMinutes = (int)($payload['expiry_minutes'] ?? setting('midtrans_expiry_minutes', 60));
        $snapPayload['expiry'] = [
            'unit' => 'minutes',
            'duration' => $expiryMinutes,
        ];

        // Custom fields for internal reference
        $snapPayload['custom_field1'] = $payload['merchant_id'] ?? '';

        // Call Midtrans Snap API
        $result = $this->callSnapApi($snapPayload);

        if ($result['success']) {
            $snapToken = $result['data']['token'] ?? '';
            $redirectUrl = $result['data']['redirect_url'] ?? '';

            return [
                'success' => true,
                'payment_url' => $redirectUrl,
                'qr_url' => null,
                'snap_token' => $snapToken,
                'reference_id' => $orderId,
                'raw_response' => json_encode($result['data']),
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Failed to create Snap transaction',
            'raw_response' => $result['raw_response'] ?? null,
        ];
    }

    /**
     * Check payment status via Midtrans Core API
     */
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
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true) ?: [];

        if ($httpCode === 200 && !empty($data)) {
            $status = $this->mapMidtransStatus($data['transaction_status'] ?? '', $data['fraud_status'] ?? '');
            return [
                'status' => $status,
                'paid_at' => $data['settlement_time'] ?? $data['transaction_time'] ?? null,
                'payment_type' => $data['payment_type'] ?? null,
                'va_number' => $this->extractVaNumber($data),
                'raw' => $data,
            ];
        }

        return ['status' => 'PENDING', 'paid_at' => null];
    }

    /**
     * Validate Midtrans webhook notification signature
     * 
     * Midtrans signature formula:
     * SHA512(order_id + status_code + gross_amount + server_key)
     */
    public function validateWebhook(string $rawPayload, array $headers): bool
    {
        $data = json_decode($rawPayload, true);
        if (!$data) return false;

        $signatureKey = $data['signature_key'] ?? '';
        if (empty($signatureKey)) return false;

        $orderId = $data['order_id'] ?? '';
        $statusCode = $data['status_code'] ?? '';
        $grossAmount = $data['gross_amount'] ?? '';

        // Midtrans signature: SHA512(order_id + status_code + gross_amount + server_key)
        $calculatedSignature = hash('sha512',
            $orderId . $statusCode . $grossAmount . $this->serverKey
        );

        return hash_equals($calculatedSignature, $signatureKey);
    }

    /**
     * Parse Midtrans webhook notification to standard format
     */
    public function parseWebhook(string $rawPayload): array
    {
        $data = json_decode($rawPayload, true) ?: [];

        $transactionStatus = $data['transaction_status'] ?? '';
        $fraudStatus = $data['fraud_status'] ?? 'accept';
        $status = $this->mapMidtransStatus($transactionStatus, $fraudStatus);

        return [
            'order_id' => $data['order_id'] ?? '',
            'status' => $status,
            'amount' => (int)($data['gross_amount'] ?? 0),
            'paid_at' => $data['settlement_time'] ?? $data['transaction_time'] ?? null,
            'payment_type' => $data['payment_type'] ?? '',
            'transaction_id' => $data['transaction_id'] ?? '',
            'status_code' => $data['status_code'] ?? '',
            'fraud_status' => $fraudStatus,
            'va_number' => $this->extractVaNumber($data),
        ];
    }

    // =========================================================
    // PRIVATE METHODS
    // =========================================================

    /**
     * Call Midtrans Snap API
     */
    private function callSnapApi(array $payload): array
    {
        $jsonPayload = json_encode($payload);

        app_log("Midtrans Snap Request: " . $jsonPayload, 'INFO');

        $ch = curl_init($this->snapUrl);
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
            app_log("Midtrans cURL Error: {$curlError}", 'ERROR');
            return ['success' => false, 'error' => "Connection error: {$curlError}", 'raw_response' => null];
        }

        app_log("Midtrans Snap Response [{$httpCode}]: {$response}", 'INFO');

        $data = json_decode($response, true) ?: [];

        if ($httpCode === 201 && !empty($data['token'])) {
            return ['success' => true, 'data' => $data, 'raw_response' => $response];
        }

        $errorMsg = '';
        if (isset($data['error_messages']) && is_array($data['error_messages'])) {
            $errorMsg = implode(', ', $data['error_messages']);
        } elseif (isset($data['message'])) {
            $errorMsg = $data['message'];
        } else {
            $errorMsg = "HTTP {$httpCode} Error";
        }

        return ['success' => false, 'error' => $errorMsg, 'data' => $data, 'raw_response' => $response];
    }

    /**
     * Map Midtrans transaction_status to internal status
     */
    private function mapMidtransStatus(string $transactionStatus, string $fraudStatus = 'accept'): string
    {
        // If fraud status is not accept, mark as failed
        if ($fraudStatus === 'deny') {
            return 'FAILED';
        }

        return match($transactionStatus) {
            'capture' => ($fraudStatus === 'accept') ? 'PAID' : 'PENDING',
            'settlement' => 'PAID',
            'pending' => 'PENDING',
            'deny', 'cancel' => 'FAILED',
            'expire' => 'EXPIRED',
            'refund', 'partial_refund' => 'REFUNDED',
            default => 'PENDING',
        };
    }

    /**
     * Get enabled payment types for Snap
     */
    private function getEnabledPaymentTypes(?string $specificMethod): array
    {
        if ($specificMethod) {
            // Map specific method to Midtrans payment type
            return match($specificMethod) {
                'midtrans_credit_card', 'credit_card' => ['credit_card'],
                'midtrans_bca_va', 'bca_va' => ['bca_va'],
                'midtrans_bni_va', 'bni_va' => ['bni_va'],
                'midtrans_bri_va', 'bri_va' => ['bri_va'],
                'midtrans_permata_va', 'permata_va' => ['permata_va'],
                'midtrans_mandiri_bill', 'mandiri_bill' => ['echannel'],
                'midtrans_cimb_va', 'cimb_va' => ['cimb_va'],
                'midtrans_gopay', 'gopay' => ['gopay'],
                'midtrans_shopeepay', 'shopeepay' => ['shopeepay'],
                'midtrans_qris', 'qris' => ['other_qris'],
                'midtrans_alfamart', 'alfamart' => ['alfamart'],
                'midtrans_indomaret', 'indomaret' => ['indomaret'],
                'midtrans_akulaku', 'akulaku' => ['akulaku'],
                'midtrans_kredivo', 'kredivo' => ['kredivo'],
                default => [], // empty = show all
            };
        }

        // Return empty to show all enabled methods (Midtrans handles it)
        return [];
    }

    /**
     * Extract VA number from notification data
     */
    private function extractVaNumber(array $data): ?string
    {
        // Different payment types store VA number differently
        if (!empty($data['va_numbers']) && is_array($data['va_numbers'])) {
            return $data['va_numbers'][0]['va_number'] ?? null;
        }
        if (!empty($data['permata_va_number'])) {
            return $data['permata_va_number'];
        }
        if (!empty($data['bill_key'])) {
            return $data['biller_code'] . ' ' . $data['bill_key']; // Mandiri
        }
        if (!empty($data['payment_code'])) {
            return $data['payment_code']; // Alfamart/Indomaret
        }
        return null;
    }

    /**
     * Cancel a transaction via Core API
     */
    public function cancelTransaction(string $orderId): array
    {
        $url = $this->apiUrl . '/' . urlencode($orderId) . '/cancel';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
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
        return [
            'success' => $httpCode === 200,
            'message' => $data['status_message'] ?? "HTTP {$httpCode}",
            'data' => $data,
        ];
    }

    /**
     * Refund a transaction via Core API
     */
    public function refundTransaction(string $orderId, int $amount, string $reason = ''): array
    {
        $url = $this->apiUrl . '/' . urlencode($orderId) . '/refund';

        $payload = ['amount' => $amount];
        if (!empty($reason)) {
            $payload['reason'] = $reason;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
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
        return [
            'success' => $httpCode === 200,
            'message' => $data['status_message'] ?? "HTTP {$httpCode}",
            'data' => $data,
        ];
    }

    /**
     * Get Client Key for frontend Snap.js
     */
    public function getClientKey(): string
    {
        return $this->clientKey;
    }

    /**
     * Get Snap JS URL
     */
    public function getSnapJsUrl(): string
    {
        return $this->isProduction
            ? 'https://app.midtrans.com/snap/snap.js'
            : 'https://app.sandbox.midtrans.com/snap/snap.js';
    }
}
