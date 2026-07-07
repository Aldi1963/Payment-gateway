<?php
/**
 * Webhook Endpoint
 * Receives payment notifications from AldiQRIS
 */

require_once __DIR__ . '/includes/init.php';
require_once base_path('app/Controllers/WebhookController.php');

$controller = new WebhookController();
$controller->handle();
