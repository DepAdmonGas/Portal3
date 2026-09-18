<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Helpers/helpers.php';
require_once __DIR__ . '/../app/Services/RequisitosLegalesStorageService.php';
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Models/Sasisopa/RequisitosLegalesMatriz.php';

use App\Core\Database;
use App\Models\Sasisopa\RequisitosLegalesMatriz;
use App\Services\RequisitosLegalesStorageService;

$apply = in_array('--apply', $argv, true);
$root = dirname(__DIR__);
$legacyRoot = $root . '/public/uploads/archivos/reuisitos-legales/';
$privateRoot = RequisitosLegalesStorageService::privateRoot();
$manifest = [
    'generated_at' => gmdate('c'), 'mode' => $apply ? 'APPLY' : 'DRY_RUN',
    'legacy_root' => 'public/uploads/archivos/reuisitos-legales/',
    'private_root' => 'storage/private/requisitos-legales/', 'db_available' => false,
    'db_rows_scanned' => 0, 'unique_references' => 0, 'entries' => [],
    'summary' => ['files_copied' => 0, 'files_deleted' => 0, 'db_rows_updated' => 0],
];

try {
    if (!isset($_ENV['DB_CONNECTION'])) {
        $dotenv = Dotenv\Dotenv::createImmutable($root);
        $dotenv->safeLoad();
    }
    Database::initialize();
    $rows = RequisitosLegalesMatriz::query()->get(['acusepdf', 'requisitolegalpdf']);
    $manifest['db_available'] = true;
    $refs = [];
    foreach ($rows as $row) {
        foreach ([$row->acusepdf, $row->requisitolegalpdf] as $value) {
            if ($value === null || trim((string) $value) === '') continue;
            $normalized = RequisitosLegalesStorageService::normalizeReference((string) $value);
            if ($normalized === null) {
                $manifest['entries'][] = ['reference' => (string) $value, 'classification' => 'INVALID_REFERENCE', 'planned_action' => 'STOP_INVALID'];
                continue;
            }
            $refs[$normalized] = ($refs[$normalized] ?? 0) + 1;
        }
    }
    $manifest['db_rows_scanned'] = count($rows);
    $manifest['unique_references'] = count($refs);
    foreach ($refs as $filename => $count) {
        $legacy = RequisitosLegalesStorageService::resolveReadablePath('archivos/reuisitos-legales/' . $filename);
        $private = RequisitosLegalesStorageService::resolveReadablePath($filename);
        $legacyPath = realpath($legacyRoot . $filename) ?: null;
        $privatePath = realpath($privateRoot . $filename) ?: null;
        $legacyExists = $legacyPath !== null && is_file($legacyPath);
        $privateExists = $privatePath !== null && is_file($privatePath);
        $legacyHash = $legacyExists ? hash_file('sha256', $legacyPath) : null;
        $privateHash = $privateExists ? hash_file('sha256', $privatePath) : null;
        $classification = !$legacyExists && !$privateExists ? 'MISSING_BOTH' : ($legacyExists && $privateExists ? ($legacyHash === $privateHash ? 'BOTH_IDENTICAL' : 'BOTH_DIFFERENT') : ($legacyExists ? 'LEGACY_ONLY' : 'PRIVATE_ONLY'));
        $action = match ($classification) { 'LEGACY_ONLY' => 'COPY_TO_PRIVATE', 'BOTH_IDENTICAL' => 'NONE_ALREADY_MIGRATED', 'PRIVATE_ONLY' => 'NONE', 'BOTH_DIFFERENT' => 'STOP_CONFLICT', default => 'STOP_MISSING' };
        $manifest['entries'][] = ['reference' => $filename, 'normalized_filename' => $filename, 'classification' => $classification, 'legacy_exists' => $legacyExists, 'private_exists' => $privateExists, 'legacy_size' => $legacyExists ? filesize($legacyPath) : null, 'private_size' => $privateExists ? filesize($privatePath) : null, 'legacy_sha256' => $legacyHash, 'private_sha256' => $privateHash, 'db_reference_count' => $count, 'planned_action' => $action, 'result' => 'PENDING'];
    }
} catch (Throwable $e) {
    $manifest['error'] = 'DB_INVENTORY_UNAVAILABLE';
}

$blockers = array_filter($manifest['entries'], static fn(array $e): bool => in_array($e['classification'] ?? '', ['INVALID_REFERENCE', 'BOTH_DIFFERENT', 'MISSING_BOTH'], true));
$manifest['apply_allowed'] = $apply && $manifest['db_available'] && !$blockers;
if ($apply && $manifest['apply_allowed']) {
    foreach ($manifest['entries'] as &$entry) {
        if (($entry['classification'] ?? '') !== 'LEGACY_ONLY') continue;
        $source = $legacyRoot . $entry['normalized_filename'];
        $destination = $privateRoot . $entry['normalized_filename'];
        $temp = $privateRoot . '.' . $entry['normalized_filename'] . '.migrating-' . bin2hex(random_bytes(8));
        if (!copy($source, $temp) || filesize($temp) !== filesize($source) || hash_file('sha256', $temp) !== hash_file('sha256', $source) || !rename($temp, $destination)) {
            @unlink($temp); $entry['result'] = 'FAIL'; $manifest['apply_allowed'] = false; break;
        }
        $entry['result'] = 'COPIED_VERIFIED'; $manifest['summary']['files_copied']++;
    }
    unset($entry);
}
foreach ($manifest['entries'] as &$entry) { if (($entry['result'] ?? '') === 'PENDING') $entry['result'] = $manifest['apply_allowed'] ? 'NO_ACTION' : 'BLOCKED'; }
unset($entry);
echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(($apply && !$manifest['apply_allowed']) ? 1 : 0);
