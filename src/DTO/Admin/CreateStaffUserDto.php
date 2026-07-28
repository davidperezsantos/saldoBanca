<?php

namespace App\DTO\Admin;

class CreateStaffUserDto
{
    /**
     * @param list<string> $roleIds
     */
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly ?string $name = null,
        public readonly array $roleIds = [],
        public readonly bool $isActive = true,
    ) {
    }

    public static function fromJson(array $data): self
    {
        return new self(
            email: $data['email'] ?? '',
            password: $data['password'] ?? '',
            name: $data['name'] ?? null,
            roleIds: $data['roleIds'] ?? [],
            isActive: $data['isActive'] ?? true,
        );
    }
}
