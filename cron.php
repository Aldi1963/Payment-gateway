<?php
/**
 * Cron Job Handler
 * Run via crontab: * * * * * php /path/to/cron.php
 * 
 * Tasks:
 * 1. Process webhook retry queue
 * 2. Auto-expire pending transactions
 * 3. Cleanup old rate limit files
 * 4. Process recurring payments / subscriptions
 * 5. Cleanup expired idempotency keys
 * 6. Cleanup expired sessions
 * 7. Process ended subscriptions
 * 8. Check overdue invoices
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

// ============================================
// 4. PROCESS RECURRING PAYMENTS
// ============================================
if (in_array($action, ['all', 'subscriptions', 'recurring'])) {
    require_once base_path('app/Services/RecurringPaymentService.php');
    $recurringService = new RecurringPaymentService();
    
    // Process due billings
    $billingResult = $recurringService->processDueBillings(50);
    $results[] = "Subscriptions billed: {$billingResult['success']}/{$billingResult['processed']} (failed: {$billingResult['failed']})";
    
    // Process ended subscriptions (cancelled_at reached)
    $ended = $recurringService->processEndedSubscriptions();
    if ($ended > 0) {
        $results[] = "Subscriptions ended: {$ended}";
    }
}

// ============================================
// 5. CLEANUP EXPIRED IDEMPOTENCY KEYS
// ============================================
if (in_array($action, ['all', 'cleanup', 'idempotency'])) {
    require_once base_path('app/Services/IdempotencyService.php');
    $idempotencyService = new IdempotencyService();
    $cleaned = $idempotencyService->cleanup();
    if ($cleaned > 0) {
        $results[] = "Idempotency keys cleaned: {$cleaned}";
    }
}

// ============================================
// 6. CLEANUP EXPIRED SESSIONS
// ============================================
if (in_array($action, ['all', 'cleanup', 'sessions'])) {
    require_once base_path('app/Services/SecurityService.php');
    $securityService = new SecurityService();
    $expiredSessions = $securityService->cleanupExpiredSessions();
    if ($expiredSessions > 0) {
        $results[] = "Expired sessions cleaned: {$expiredSessions}";
    }
}

// ============================================
// 7. CHECK OVERDUE INVOICES
// ============================================
if (in_array($action, ['all', 'invoices'])) {
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "UPDATE invoices SET status = 'overdue', updated_at = NOW() 
             WHERE status = 'sent' AND due_date < CURDATE()"
        );
        $stmt->execute();
        $overdue = $stmt->rowCount();
        if ($overdue > 0) {
            $results[] = "Invoices marked overdue: {$overdue}";
        }
    } catch (\Throwable $e) {
        $results[] = "Invoice check error: " . $e->getMessage();
    }
}

// ============================================
// 8. PAYMENT LINK EXPIRY CHECK
// ============================================
if (in_array($action, ['all', 'payment_links'])) {
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "UPDATE payment_links SET status = 'expired', updated_at = NOW() 
             WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at < NOW()"
        );
        $stmt->execute();
        $expiredLinks = $stmt->rowCount();
        if ($expiredLinks > 0) {
            $results[] = "Payment links expired: {$expiredLinks}";
        }
    } catch (\Throwable $e) {
        $results[] = "Payment link check error: " . $e->getMessage();
    }
}

// Output results
$output = '[' . date('Y-m-d H:i:s') . '] Cron: ' . implode(' | ', $results);
echo $output . PHP_EOL;
app_log($output, 'CRON');
