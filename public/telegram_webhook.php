<?php

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Core\Bootstrap;
use App\Core\Database;
use App\Controllers\TelegramWebhookController;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

Bootstrap::init();
Database::initialize();

$controller = new TelegramWebhookController();
$controller->handle();