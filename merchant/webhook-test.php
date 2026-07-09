<?php
/**
 * Deprecated page.
 * Webhook testing has moved to Integrasi API > Test Webhook.
 */
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();
redirect('/merchant/integration.php?tab=test');
