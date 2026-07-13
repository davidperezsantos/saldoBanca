<?php

namespace App\DTO\Balance;

class AccountDto
{
    public function __construct(
        public readonly string $accountType,
        public readonly string $businessName,
        public readonly string $documentType,
        public readonly string $documentNumber,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly string $defaultCurrency = 'USD',
        public readonly string $creditLimit = '0.00',
        public readonly bool $allowTransfers = true,
        public readonly bool $allowAuthorizedUsers = true,
    ) {
    }

    public static function fromJson(array $data): self
    {
        return new self(
            accountType: $data['accountType'] ?? 'business',
            businessName: $data['businessName'],
            documentType: $data['documentType'],
            documentNumber: $data['documentNumber'],
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            defaultCurrency: $data['defaultCurrency'] ?? 'USD',
            creditLimit: $data['creditLimit'] ?? '0.00',
            allowTransfers: $data['allowTransfers'] ?? true,
            allowAuthorizedUsers: $data['allowAuthorizedUsers'] ?? true,
        );
    }

    public function toArray(): array
    {
        return [
            'accountType' => $this->accountType,
            'businessName' => $this->businessName,
            'documentType' => $this->documentType,
            'documentNumber' => $this->documentNumber,
            'email' => $this->email,
            'phone' => $this->phone,
            'defaultCurrency' => $this->defaultCurrency,
            'creditLimit' => $this->creditLimit,
            'allowTransfers' => $this->allowTransfers,
            'allowAuthorizedUsers' => $this->allowAuthorizedUsers,
        ];
    }
}
