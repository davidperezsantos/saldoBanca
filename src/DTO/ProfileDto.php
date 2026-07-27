<?php

namespace App\DTO;

class ProfileDto
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $phone = null,
    ) {
    }

    public static function fromJson(array $data): self
    {
        return new self(
            name: trim($data['name'] ?? ''),
            phone: isset($data['phone']) ? trim((string) $data['phone']) : null,
        );
    }
}
