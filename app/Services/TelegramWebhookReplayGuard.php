<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Claims a Telegram update ID once on the local private filesystem.
 *
 * fopen(..., 'x') is an atomic create operation, so two PHP workers sharing
 * this storage cannot both claim the same update. Deployments with multiple
 * nodes must point this directory at shared storage or replace it with a
 * database/cache implementation that preserves the same atomic claim.
 */
final class TelegramWebhookReplayGuard
{
    private const RETENTION_SECONDS = 604800; // 7 days

    public function __construct(private readonly ?string $directory = null)
    {
    }

    public function claim(int $updateId): bool
    {
        if ($updateId < 0) {
            throw new RuntimeException('Invalid Telegram update ID.');
        }

        $directory = $this->directory();
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to initialize Telegram replay storage.');
        }

        $this->cleanup($directory);
        $path = $this->claimPath($directory, $updateId);
        $handle = @fopen($path, 'x');
        if ($handle === false) {
            if (is_file($path)) {
                return false;
            }

            throw new RuntimeException('Unable to claim Telegram update.');
        }

        fwrite($handle, (string) time());
        fclose($handle);

        return true;
    }

    public function release(int $updateId): void
    {
        $path = $this->claimPath($this->directory(), $updateId);
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function directory(): string
    {
        $configured = getenv('TELEGRAM_WEBHOOK_REPLAY_DIR');
        if (is_string($configured) && $configured !== '') {
            return rtrim($configured, DIRECTORY_SEPARATOR);
        }

        return dirname(__DIR__, 2) . '/storage/private/telegram-webhook-replay';
    }

    private function claimPath(string $directory, int $updateId): string
    {
        return rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'update-' . $updateId . '.claim';
    }

    private function cleanup(string $directory): void
    {
        $marker = $directory . DIRECTORY_SEPARATOR . '.cleanup';
        if (is_file($marker) && filemtime($marker) >= time() - 86400) {
            return;
        }

        $handle = @fopen($marker, 'x');
        if ($handle === false) {
            return;
        }
        fclose($handle);

        $expiresAt = time() - self::RETENTION_SECONDS;
        foreach (glob($directory . DIRECTORY_SEPARATOR . 'update-*.claim') ?: [] as $path) {
            if (filemtime($path) < $expiresAt) {
                unlink($path);
            }
        }
        touch($marker);
    }
}
