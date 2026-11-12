<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

use App\Core\Database;
use App\Core\Session;
use App\Services\TelegramService;

\App\Core\Bootstrap::init();
Database::initialize();
Session::init();

$telegram = new TelegramService();
$offset = 0;

echo "Poller local de Telegram iniciado (bot local). Pulsa Ctrl+C para salir.\n";
echo "APP_ENV=" . ($_ENV['APP_ENV'] ?? 'prod') . "\n";

while (true) {
    $updates = $telegram->pollUpdates($offset, 5);
    if (!empty($updates)) {
        echo "Recibidas " . count($updates) . " actualizaciones.\n";
        foreach ($updates as $update) {
            $updateId = $update['update_id'] ?? null;
            if ($updateId) {
                $offset = max($offset, $updateId + 1);
            }
            $ok = $telegram->processUpdate($update);
            echo "  update_id=" . ($updateId ?? 'n/a') . " -> " . ($ok ? 'PROCESADO' : 'IGNORADO/ERROR') . "\n";
        }
    }
    usleep(500000);
}