<?php
/**
 * Payment Channel Manager
 * Registry and factory for payment channel adapters
 * 
 * Supported channels: QRIS (AldiQRIS), Virtual Account, E-Wallet
 * Plugin architecture: add new channels without modifying core
 */

require_once base_path('app/Interfaces/PaymentChannelInterface.php');

class PaymentChannelManager
{
    private array $channels = [];
    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->registerDefaults();
        }
        return self::$instance;
    }

    /**
     * Register a payment channel
     */
    public function register(string $code, PaymentChannelInterface $channel): void
    {
        $this->channels[$code] = $channel;
    }

    /**
     * Get a channel by code
     */
    public function getChannel(string $code): ?PaymentChannelInterface
    {
        return $this->channels[$code] ?? null;
    }

    /**
     * Get all enabled channels
     */
    public function getEnabledChannels(): array
    {
        return array_filter($this->channels, fn($ch) => $ch->isEnabled());
    }

    /**
     * Get all registered channels
     */
    public function getAllChannels(): array
    {
        return $this->channels;
    }


    /**
     * Create payment via specified channel
     */
    public function createPayment(string $channelCode, array $payload): array
    {
        $channel = $this->getChannel($channelCode);
        if (!$channel) {
            return ['success' => false, 'error' => "Channel '{$channelCode}' not found"];
        }
        if (!$channel->isEnabled()) {
            return ['success' => false, 'error' => "Channel '{$channelCode}' is disabled"];
        }
        return $channel->createPayment($payload);
    }

    /**
     * Get available payment methods for display
     */
    public function getAvailableMethods(): array
    {
        $methods = [];
        foreach ($this->getEnabledChannels() as $code => $channel) {
            $methods[] = [
                'code' => $code,
                'name' => $channel->getChannelName(),
                'methods' => $channel->getSupportedMethods(),
            ];
        }
        return $methods;
    }

    /**
     * Register default channels
     */
    private function registerDefaults(): void
    {
        // QRIS via AldiQRIS (always available)
        require_once base_path('app/Channels/QrisChannel.php');
        $this->register('qris', new QrisChannel());

        // Virtual Account (if configured)
        require_once base_path('app/Channels/VirtualAccountChannel.php');
        $this->register('va', new VirtualAccountChannel());

        // E-Wallet (if configured)
        require_once base_path('app/Channels/EWalletChannel.php');
        $this->register('ewallet', new EWalletChannel());
    }
}
