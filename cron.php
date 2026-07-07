<?php
/**
 * Cron Job Handler
 * Run via crontab: * * * * * php /path/to/cron.php
 * 
 * Tasks:
 * 1. Process webhook retry queue
 * 2. Auto-expire pending transactions
 * 3. Cleanup old rate limit files
 */

require_once __DIR__ . '/includes/init.php';

$action = $argv[1] ?? $_GET['action'] ?? 'all';
$results = [];

// ============================================
// 1. WEBHOOK RETRY QUEUE
// ============================================
if (in_array($action, ['all', 'webhooks'])) {
    require_once base_path('app/Services/WebhookRetryService.php');
    $retryService = new WebhookRetryService();
    $processed = $retryService->processQueue(50);
    $results[] = "Webhooks processed: {$processed}";
}

// ============================================
// 2. AUTO-EXPIRE TRANSACTIONS
// ============================================
if (in_array($action, ['all', 'expire'])) {
    require_once base_path('app/Repositories/TransactionRepository.php');
    require_once base_path('app/Repositories/MerchantRepository.php');
    
    $txRepo = new TransactionRepository();
    $merchantRepo = new MerchantRepository();
    $defaultExpiry = (int)setting('payment_expiry_minutes', 60);
    
    $pendingTx = $txRepo->findByStatus('PENDING');
    $expired = 0;
    $now = time();
    
    foreach ($pendingTx as $tx) {
        // Get merchant-specific expiry or default
        $merchant = $merchantRepo->find($tx['merchant_id']);
        $expiryMinutes = (int)($merchant['payment_expiry_minutes'] ?? $defaultExpiry);
        
        $createdAt = strtotime($tx['created_at']);
        $expiryTime = $createdAt + ($expiryMinutes * 60);
        
        if ($now > $expiryTime) {
            $txRepo->update($tx['id'], [
                'status' => 'EXPIRED',
                'expired_at' => now(),
                'updated_at' => now(),
            ]);
            $expired++;
        }
    }
    $results[] = "Transactions expired: {$expired}";
}

// ============================================
// 3. CLEANUP OLD RATE LIMIT FILES
// ============================================
if (in_array($action, ['all', 'cleanup'])) {
    require_once base_path('app/RateLimiter.php');
    $rl = new RateLimiter();
    $rl->cleanup(7200); // Remove files older than 2 hours
    $results[] = "Rate limit cleanup done";
}

// Output results
$output = '[' . date('Y-m-d H:i:s') . '] Cron: ' . implode(' | ', $results);
echo $output . PHP_EOL;
app_log($output, 'CRON');
