<?php

namespace App\Controllers;

use App\Services\TelegramService;

class TelegramWebhookController
{
public function handle()
{
$update = json_decode(file_get_contents('php://input'), true);

if (empty($update)) {
http_response_code(200);
exit;
}

$telegram = new TelegramService();
$telegram->processUpdate($update);

http_response_code(200);
exit;
}

public function poll()
{
header('Content-Type: application/json; charset=utf-8');

$telegram = new TelegramService();
$offset = (int) ($_GET['offset'] ?? 0);
$updates = $telegram->pollUpdates($offset);

echo json_encode(['success' => true, 'data' => $updates]);
exit;
}
}
