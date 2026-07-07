<?php
require_once __DIR__ . '/includes/init.php';
require_once base_path('app/Controllers/AuthController.php');

$controller = new AuthController();
$controller->logout();
