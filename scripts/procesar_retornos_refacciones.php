<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
Dotenv\Dotenv::createImmutable($root)->safeLoad();

App\Core\Bootstrap::init();
App\Core\Database::initialize();

$result = App\Services\RefaccionesService::procesarRetornosVencidos();
fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
exit(($result['success'] ?? false) ? 0 : 1);
