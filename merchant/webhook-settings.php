<?php
/**
 * Deprecated page.
 * Webhook configuration has moved to Project Settings.
 */
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();
$mid = Auth::merchantId();
redirect($mid ? '/merchant/project-settings.php?id=' . urlencode($mid) : '/merchant/projects.php');
