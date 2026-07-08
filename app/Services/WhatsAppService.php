<?php
/**
 * WhatsApp Service
 * Sends WhatsApp notifications using per-project (merchant) configuration.
 *
 * Each project can integrate its own WA provider (Fonnte, Wablas, Zenziva,
 * or a Custom endpoint) with its own API key / sender number.
 *
 * This fixes the previously missing sendWhatsApp() implementation and makes
 * notifications project-scoped instead of global.
 *
 * Supported providers:
 *   - fonnte  : https://api.fonnte.com/send  (Header: Authorization: <token>)
 *   - wablas  : <api_url>/api/send-message   (Body: token=<key>)
 *   - zenziva : <api_url>                     (Body: userkey + passkey)
 *   - custom  : <api_url>                     (Bearer <api_key>, JSON body)
 */

require_once base_path('app/Repositories/WaConfigRepository.php');

class WhatsAppService
{
    private WaConfigRepository $configRepo;

    public function __construct()
    {
        $this->configRepo = new WaConfigRepository();
    }

    /**
     * Get active WA config for a project.
     */
    public function getConfig(string $merchantId): ?array
    {
        return $this->configRepo->findActiveByMerchant($merchantId);
    }

    /**
     * Send a WhatsApp message using the project's configured provider.
     *
     * @return array ['success' => bool, 'skipped' => bool, 'error' => ?string, 'http_code' => ?int]
     */
    public function send(string $merchantId, string $phone, string $message): array
    {
        $cfg = $this->getConfig($merchantId);
        if (!$cfg) {
            return ['success' => false, 'skipped' => true, 'error' => 'WA belum dikonfigurasi untuk proyek ini.'];
        }

        $phone = $this->normalizePhone($phone);
        if ($phone === '') {
            return ['success' => false, 'skipped' => true, 'error' => 'Nomor tujuan kosong/tidak valid.'];
        }

        try {
            $result = match ($cfg['provider']) {
                'fonnte'  => $this->sendFonnte($cfg, $phone, $message),
                'wablas'  => $this->sendWablas($cfg, $phone, $message),
                'zenziva' => $this->sendZenziva($cfg, $phone, $message),
                'custom'  => $this->sendCustom($cfg, $phone, $message),
                default   => ['success' => false, 'error' => "Provider '{$cfg['provider']}' tidak didukung."],
            };
        } catch (\Throwable $e) {
            $result = ['success' => false, 'error' => $e->getMessage()];
        }

        // Record statistics
        $this->configRepo->recordSend($merchantId, $result['success'] ?? false, $result['error'] ?? null);

        if (!($result['success'] ?? false)) {
            app_log("WA send failed for merchant {$merchantId}: " . ($result['error'] ?? 'unknown'), 'WARNING');
        }

        return array_merge(['success' => false, 'skipped' => false], $result);
    }

    /**
     * Send payment success notification to the customer (and optionally admin).
     * Called from TransactionService when a transaction becomes PAID.
     */
    public function sendPaymentNotification(string $merchantId, array $transaction): bool
    {
        $cfg = $this->getConfig($merchantId);
        if (!$cfg || (int)($cfg['notify_on_payment'] ?? 0) !== 1) {
            return false;
        }

        // Load project name for template
        require_once base_path('app/Repositories/MerchantRepository.php');
        $project = (new MerchantRepository())->find($merchantId);
        $projectName = $project['business_name'] ?? 'Toko';

        $template = $cfg['message_template_payment']
            ?: setting('wa_default_template_payment', 'Halo {customer}! Pembayaran order {order_id} sebesar {amount} telah {status}.');

        $message = $this->renderTemplate($template, [
            'customer' => $transaction['customer_name'] ?: 'Pelanggan',
            'order_id' => $transaction['order_id'] ?? '',
            'amount'   => format_currency($transaction['amount'] ?? 0),
            'net'      => format_currency($transaction['net_amount'] ?? 0),
            'status'   => $this->statusLabel($transaction['status'] ?? 'PAID'),
            'project'  => $projectName,
        ]);

        $sentAny = false;

        // Notify customer
        if (!empty($transaction['customer_wa'])) {
            $r = $this->send($merchantId, $transaction['customer_wa'], $message);
            $sentAny = $sentAny || ($r['success'] ?? false);
        }

        // Notify project admin number if set
        if (!empty($cfg['notify_admin_number'])) {
            $adminMsg = "Pembayaran masuk di {$projectName}!\nOrder: " . ($transaction['order_id'] ?? '-') .
                        "\nJumlah: " . format_currency($transaction['amount'] ?? 0);
            $r = $this->send($merchantId, $cfg['notify_admin_number'], $adminMsg);
            $sentAny = $sentAny || ($r['success'] ?? false);
        }

        return $sentAny;
    }

    /**
     * Test the WA connection by sending a test message.
     */
    public function testConnection(string $merchantId, string $testPhone): array
    {
        $project = null;
        require_once base_path('app/Repositories/MerchantRepository.php');
        $project = (new MerchantRepository())->find($merchantId);
        $name = $project['business_name'] ?? 'Toko';

        return $this->send(
            $merchantId,
            $testPhone,
            "✅ Test koneksi WhatsApp untuk proyek *{$name}* berhasil! Notifikasi pembayaran Anda sudah aktif."
        );
    }

    // ==============================
    // PROVIDER ADAPTERS
    // ==============================

    /**
     * Fonnte: POST https://api.fonnte.com/send
     * Header: Authorization: <token>
     * Body: target, message
     */
    private function sendFonnte(array $cfg, string $phone, string $message): array
    {
        $url = $cfg['api_url'] ?: 'https://api.fonnte.com/send';
        return $this->httpPost($url, [
            'target' => $phone,
            'message' => $message,
        ], [
            'Authorization: ' . $cfg['api_key'],
        ], 'form');
    }

    /**
     * Wablas: POST <api_url>/api/send-message
     * Body: phone, message, token
     */
    private function sendWablas(array $cfg, string $phone, string $message): array
    {
        $url = rtrim($cfg['api_url'], '/');
        if (!str_contains($url, '/api/')) {
            $url .= '/api/send-message';
        }
        return $this->httpPost($url, [
            'phone' => $phone,
            'message' => $message,
            'token' => $cfg['api_key'],
        ], [], 'form');
    }

    /**
     * Zenziva: POST <api_url>
     * Body: userkey, passkey, to, message
     */
    private function sendZenziva(array $cfg, string $phone, string $message): array
    {
        return $this->httpPost($cfg['api_url'], [
            'userkey' => $cfg['api_key'],
            'passkey' => $cfg['api_secret'] ?? '',
            'to' => $phone,
            'message' => $message,
        ], [], 'form');
    }

    /**
     * Custom: POST <api_url> with Bearer <api_key>, JSON body { to, message, sender }
     */
    private function sendCustom(array $cfg, string $phone, string $message): array
    {
        $headers = ['Content-Type: application/json'];
        if (!empty($cfg['api_key'])) {
            $headers[] = 'Authorization: Bearer ' . $cfg['api_key'];
        }
        return $this->httpPost($cfg['api_url'], [
            'to' => $phone,
            'message' => $message,
            'sender' => $cfg['sender_number'] ?? '',
        ], $headers, 'json');
    }

    // ==============================
    // HTTP + HELPERS
    // ==============================

    /**
     * Generic HTTP POST helper.
     *
     * @param string $bodyType 'form' or 'json'
     */
    private function httpPost(string $url, array $data, array $headers = [], string $bodyType = 'form'): array
    {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'error' => 'API URL tidak valid.'];
        }

        $ch = curl_init($url);
        $body = $bodyType === 'json' ? json_encode($data) : http_build_query($data);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => getenv('APP_ENV') === 'production',
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'error' => $curlError, 'http_code' => $httpCode];
        }

        $success = $httpCode >= 200 && $httpCode < 300;

        // Try to detect provider-level failure in the JSON response body
        $decoded = json_decode($response, true);
        if ($success && is_array($decoded)) {
            // Common failure indicators across providers
            if (isset($decoded['status']) && ($decoded['status'] === false || $decoded['status'] === 'error')) {
                $success = false;
            }
        }

        return [
            'success' => $success,
            'error' => $success ? null : ('Provider response HTTP ' . $httpCode),
            'http_code' => $httpCode,
            'response' => is_string($response) ? substr($response, 0, 500) : null,
        ];
    }

    /**
     * Render a message template, replacing {placeholders}.
     */
    private function renderTemplate(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $template = str_replace('{' . $key . '}', (string)$value, $template);
        }
        return $template;
    }

    /**
     * Normalize an Indonesian phone number to international format (62...).
     */
    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if ($phone === '') return '';
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        } elseif (str_starts_with($phone, '8')) {
            $phone = '62' . $phone;
        }
        return $phone;
    }

    /**
     * Human-friendly status label for WA messages.
     */
    private function statusLabel(string $status): string
    {
        return match (strtoupper($status)) {
            'PAID' => 'BERHASIL',
            'PENDING' => 'MENUNGGU',
            'FAILED' => 'GAGAL',
            'EXPIRED' => 'KADALUARSA',
            'REFUNDED' => 'DIKEMBALIKAN',
            default => $status,
        };
    }
}
