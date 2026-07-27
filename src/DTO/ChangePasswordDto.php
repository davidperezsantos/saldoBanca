<?php

namespace App\DTO;

class ChangePasswordDto
{
    public function __construct(
        public readonly string $currentPassword,
        public readonly string $newPassword,
    ) {
    }

    public static function fromJson(array $data): self
    {
        return new self(
            currentPassword: (string) ($data['currentPassword'] ?? ''),
            newPassword: (string) ($data['newPassword'] ?? ''),
        );
    }
}
