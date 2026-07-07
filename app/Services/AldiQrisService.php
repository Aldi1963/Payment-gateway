<?php
/**
 * AldiQRIS Payment Gateway Service
 * Handles all communication with the AldiQRIS API
 */

class AldiQrisService
{
    private string $baseUrl;
    private int $timeout;
    private bool $sslVerify;
    private string $endpoint;

    public function __construct()
    {
        // Dynamic: DB settings override file config
        $this->baseUrl = setting('aldiqris_base_url', config('gateway.aldiqris.base_url', 'https://aldiqris.pages.dev'));
        $this->timeout = (int)setting('aldiqris_timeout', config('gateway.aldiqris.timeout', 30));
        $this->sslVerify = setting('aldiqris_ssl_verify', '1') === '1';
        $this->endpoint = setting('aldiqris_endpoint', config('gateway.aldiqris.endpoint_create', '/api/trx'));
    }

    /**
     * Create a transaction via AldiQRIS API
     * 
     * @param array $payload Transaction data
     * @param string $apiKey Merchant API key
     * @return array Response with success status and data
     */
    public function createTransaction(array $payload, string $apiKey): array
    {
        $url = rtrim($this->baseUrl, '/') . $this->endpoint;
        
        // Build the request payload (only order_id and amount are required)
        $requestData = [
            'order_id' => $payload['order_id'],
            'amount' => (int)$payload['amount'],
        ];

        // Optional fields
        if (!empty($payload['link_name'])) {
            $requestData['link_name'] = $payload['link_name'];
        }
        if (!empty($payload['webhook_url'])) {
            $requestData['webhook_url'] = $payload['webhook_url'];
        }
        if (!empty($payload['redirect_url'])) {
            $requestData['redirect_url'] = $payload['redirect_url'];
        }
        // Customer data (optional, for extended info)
        if (!empty($payload['customer'])) {
            $requestData['customer'] = $payload['customer'];
        }

        $jsonPayload = json_encode($requestData);

        app_log("AldiQRIS Request: {$url} - Payload: {$jsonPayload}", 'INFO');

        try {
            $ch = curl_init();
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $jsonPayload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => $this->sslVerify,
                CURLOPT_SSL_VERIFYHOST => $this->sslVerify ? 2 : 0,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            
            curl_close($ch);

            if ($curlErrno !== 0) {
                app_log("AldiQRIS cURL Error [{$curlErrno}]: {$curlError}", 'ERROR');
                return [
                    'success' => false,
                    'error' => "Connection error: {$curlError}",
                    'http_code' => 0,
                    'raw_response' => null,
                ];
            }

            app_log("AldiQRIS Response [{$httpCode}]: {$response}", 'INFO');

            $responseData = json_decode($response, true);

            if ($httpCode >= 200 && $httpCode < 300) {
                // Extract payment URL from response
                $paymentUrl = $this->extractPaymentUrl($responseData);
                $qrUrl = $this->extractQrUrl($responseData);

                return [
                    'success' => true,
                    'http_code' => $httpCode,
                    'data' => $responseData,
                    'payment_url' => $paymentUrl,
                    'qr_url' => $qrUrl,
                    'raw_response' => $response,
                ];
            }

            return [
                'success' => false,
                'error' => $responseData['message'] ?? $responseData['error'] ?? "HTTP {$httpCode} Error",
                'http_code' => $httpCode,
                'data' => $responseData,
                'raw_response' => $response,
            ];

        } catch (\Throwable $e) {
            app_log("AldiQRIS Exception: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'error' => 'Internal error: ' . $e->getMessage(),
                'http_code' => 0,
                'raw_response' => null,
            ];
        }
    }

    /**
     * Extract payment URL from API response
     */
    private function extractPaymentUrl(?array $data): ?string
    {
        if (!$data) return null;
        $keys = config('gateway.payment_url_keys', []);
        return extract_from_keys($data, $keys);
    }

    /**
     * Extract QR URL from API response
     */
    private function extractQrUrl(?array $data): ?string
    {
        if (!$data) return null;
        $keys = config('gateway.qr_url_keys', []);
        return extract_from_keys($data, $keys);
    }

    /**
     * Validate webhook signature
     */
    public function validateWebhookSignature(string $payload, string $signature, string $secretKey): bool
    {
        $calculatedSignature = hash_hmac('sha256', $payload, $secretKey);
        return hash_equals($calculatedSignature, $signature);
    }

    /**
     * Extract order_id from webhook payload
     */
    public function extractOrderId(array $data): ?string
    {
        $keys = config('gateway.order_id_keys', []);
        return extract_from_keys($data, $keys);
    }

    /**
     * Extract and map status from webhook payload
     */
    public function extractStatus(array $data): ?string
    {
        $keys = config('gateway.status_keys', []);
        $rawStatus = extract_from_keys($data, $keys);
        
        if (!$rawStatus) return null;
        
        $mapping = config('gateway.status_mapping', []);
        $normalized = strtolower(trim($rawStatus));
        
        return $mapping[$normalized] ?? strtoupper($rawStatus);
    }
}
