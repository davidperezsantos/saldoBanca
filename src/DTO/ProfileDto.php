<?php

namespace App\DTO;

class ProfileDto
{
    public function __construct(
        public readonly string $name,
    ) {
    }

    public static function fromJson(array $data): self
    {
        return new self(
            name: trim($data['name'] ?? ''),
        );
    }
}
