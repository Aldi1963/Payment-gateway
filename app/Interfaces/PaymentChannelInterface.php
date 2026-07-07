<?php
/**
 * Payment Channel Interface
 * All payment channel adapters must implement this
 */

interface PaymentChannelInterface
{
    /**
     * Get channel identifier
     */
    public function getChannelCode(): string;

    /**
     * Get channel display name
     */
    public function getChannelName(): string;

    /**
     * Create a payment request
     * Returns: ['success'=>bool, 'payment_url'=>string, 'qr_url'=>string|null, 'reference_id'=>string, 'raw_response'=>array]
     */
    public function createPayment(array $payload): array;

    /**
     * Check payment status
     * Returns: ['status'=>string, 'paid_at'=>string|null]
     */
    public function checkStatus(string $referenceId): array;

    /**
     * Validate incoming webhook/notification
     */
    public function validateWebhook(string $rawPayload, array $headers): bool;

    /**
     * Parse webhook payload to standard format
     * Returns: ['order_id'=>string, 'status'=>string, 'amount'=>int, 'paid_at'=>string|null]
     */
    public function parseWebhook(string $rawPayload): array;

    /**
     * Check if channel is available/enabled
     */
    public function isEnabled(): bool;

    /**
     * Get supported payment methods within this channel
     */
    public function getSupportedMethods(): array;
}
