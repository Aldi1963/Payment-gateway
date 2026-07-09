<?php
/**
 * Deprecated page.
 * API key management has moved to Settings > API Key (account-level key).
 */
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();
redirect('/merchant/settings.php?tab=apikey');
