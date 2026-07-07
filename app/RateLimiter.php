<?php
/**
 * File-based Rate Limiter
 * Cannot be bypassed by clearing cookies (unlike session-based)
 * 
 * Stores attempts in storage/rate_limits/ directory, keyed by IP + action hash.
 * Auto-cleanup of expired entries.
 */

class RateLimiter
{
    private string $storageDir;

    public function __construct()
    {
        $this->storageDir = dirname(__DIR__) . '/storage/rate_limits';
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    /**
     * Check if request is rate limited
     * 
     * @param string $key Unique key (e.g., IP + action)
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $windowSeconds Time window in seconds
     * @return array ['allowed' => bool, 'remaining' => int, 'retry_after' => int]
     */
    public function check(string $key, int $maxAttempts, int $windowSeconds): array
    {
        $file = $this->getFilePath($key);
        $data = $this->readData($file);
        
        // Clean expired entries
        $now = time();
        $data['attempts'] = array_filter(
            $data['attempts'] ?? [],
            fn($ts) => ($now - $ts) < $windowSeconds
        );

        $count = count($data['attempts']);

        if ($count >= $maxAttempts) {
            $oldestInWindow = min($data['attempts']);
            $retryAfter = $windowSeconds - ($now - $oldestInWindow);
            return [
                'allowed' => false,
                'remaining' => 0,
                'retry_after' => max(1, $retryAfter),
                'count' => $count,
            ];
        }

        return [
            'allowed' => true,
            'remaining' => $maxAttempts - $count,
            'retry_after' => 0,
            'count' => $count,
        ];
    }

    /**
     * Record an attempt
     */
    public function hit(string $key, int $windowSeconds): void
    {
        $file = $this->getFilePath($key);
        $data = $this->readData($file);
        
        $now = time();
        $data['attempts'] = array_filter(
            $data['attempts'] ?? [],
            fn($ts) => ($now - $ts) < $windowSeconds
        );
        $data['attempts'][] = $now;
        $data['last_attempt'] = $now;

        $this->writeData($file, $data);
    }

    /**
     * Clear attempts for a key (e.g., after successful login)
     */
    public function clear(string $key): void
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * Generate a rate limit key for login
     */
    public static function loginKey(string $email, string $ip): string
    {
        return 'login_' . hash('sha256', strtolower($email) . '|' . $ip);
    }

    /**
     * Generate a rate limit key for API
     */
    public static function apiKey(string $apiKeyHash, string $ip): string
    {
        return 'api_' . hash('sha256', $apiKeyHash . '|' . $ip);
    }

    /**
     * Generate a rate limit key for registration
     */
    public static function registerKey(string $ip): string
    {
        return 'register_' . hash('sha256', $ip);
    }

    /**
     * Generate a rate limit key for webhook
     */
    public static function webhookKey(string $ip): string
    {
        return 'webhook_' . hash('sha256', $ip);
    }

    /**
     * Cleanup old rate limit files (call periodically)
     */
    public function cleanup(int $maxAge = 7200): void
    {
        $files = glob($this->storageDir . '/*.json');
        $now = time();
        foreach ($files as $file) {
            if (($now - filemtime($file)) > $maxAge) {
                @unlink($file);
            }
        }
    }

    private function getFilePath(string $key): string
    {
        // Sanitize key to safe filename
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '', $key);
        return $this->storageDir . '/' . $safeKey . '.json';
    }

    private function readData(string $file): array
    {
        if (!file_exists($file)) return ['attempts' => []];
        $content = @file_get_contents($file);
        if (!$content) return ['attempts' => []];
        $data = json_decode($content, true);
        return is_array($data) ? $data : ['attempts' => []];
    }

    private function writeData(string $file, array $data): void
    {
        file_put_contents($file, json_encode($data), LOCK_EX);
    }
}
