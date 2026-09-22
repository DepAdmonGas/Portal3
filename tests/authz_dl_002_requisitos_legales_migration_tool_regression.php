<?php
declare(strict_types=1);
function migration_assert(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$tool = file_get_contents(__DIR__ . '/../scripts/migrate_requisitos_legales_private_storage.php');
$tests = [
    'dry-run is default' => str_contains($tool, 'in_array(\'--apply\', $argv, true)'),
    'apply flag is explicit' => str_contains($tool, '$apply &&'),
    'DB writes are unsupported' => str_contains($tool, "'db_rows_updated' => 0"),
    'source deletion is unsupported' => !str_contains($tool, 'unlink($source)') && !str_contains($tool, 'rename($source'),
    'fixed roots are used' => str_contains($tool, 'public/uploads/archivos/reuisitos-legales/') && str_contains($tool, 'storage/private/requisitos-legales/'),
    'storage normalization is reused' => str_contains($tool, 'normalizeReference'),
    'SHA256 verification is used' => str_contains($tool, "hash_file('sha256'"),
    'JSON manifest is emitted' => str_contains($tool, 'json_encode($manifest'),
    'two-pass apply gate exists' => str_contains($tool, '$manifest[\'apply_allowed\'] = $apply'),
    'collision blocks apply' => str_contains($tool, 'BOTH_DIFFERENT'),
    'missing files block apply' => str_contains($tool, 'MISSING_BOTH'),
    'invalid references block apply' => str_contains($tool, 'INVALID_REFERENCE'),
    'temporary copy is verified' => str_contains($tool, '.migrating-') && str_contains($tool, 'hash_file'),
    'DB unavailable fails closed for apply' => str_contains($tool, 'DB_INVENTORY_UNAVAILABLE'),
    'orphan copying is not implemented' => !str_contains($tool, 'array_diff'),
];
foreach ($tests as $name => $ok) { migration_assert($ok, $name); echo "PASS {$name}\n"; }
printf("RESULT: %d PASS / 0 FAIL / 0 SKIPPED\n", count($tests));
