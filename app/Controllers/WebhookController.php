<?php
/**
 * Webhook Controller
 * Handles incoming webhook from payment gateway
 */

require_once base_path('app/Helpers.php');
require_once base_path('app/Services/WebhookService.php');

class WebhookController
{
    private WebhookService $webhookService;

    public function __construct()
    {
        $this->webhookService = new WebhookService();
    }

    /**
     * Handle incoming webhook POST
     */
    public function handle(): void
    {
        // Only accept POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['error' => 'Method not allowed'], 405);
        }

        // Read raw input
        $rawPayload = file_get_contents('php://input');

        if (empty($rawPayload)) {
            json_response(['error' => 'Empty payload'], 400);
        }

        // Check payload size
        $maxSize = config('gateway.webhook.max_payload_size', 65536);
        if (strlen($rawPayload) > $maxSize) {
            json_response(['error' => 'Payload too large'], 413);
        }

        // Get all headers
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[$key] = $value;
            }
        }

        // Process webhook
        $result = $this->webhookService->process($rawPayload, $headers);

        json_response(
            ['success' => $result['success'], 'message' => $result['message']],
            $result['code']
        );
    }
}
