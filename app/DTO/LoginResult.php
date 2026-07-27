<?php

namespace App\DTO;

final class LoginResult
{
    public function __construct(
        public readonly string $type,
        public readonly string $message,
        public readonly int $status = 200,
        public readonly array $data = []
    ) {}
}
