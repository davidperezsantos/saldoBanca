<?php

namespace App\DTO\Balance;

class AuthorizedDto
{
    public function __construct(
        public readonly ?string $accountId = null,
        public readonly string $userName,
        public readonly string $userEmail,
        public readonly ?string $userPhone = null,
        public readonly string $documentType,
        public readonly string $documentNumber,
        public readonly ?string $maxAmount = null,
        public readonly ?string $dailyLimit = null,
        public readonly ?string $monthlyLimit = null,
        public readonly ?string $pinCode = null,
    ) {
    }

    public static function fromJson(array $data): self
    {
        return new self(
            accountId: $data['accountId'] ?? null,
            userName: $data['userName'],
            userEmail: $data['userEmail'],
            userPhone: $data['userPhone'] ?? null,
            documentType: $data['documentType'],
            documentNumber: $data['documentNumber'],
            maxAmount: $data['maxAmount'] ?? null,
            dailyLimit: $data['dailyLimit'] ?? null,
            monthlyLimit: $data['monthlyLimit'] ?? null,
            pinCode: $data['pinCode'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'accountId' => $this->accountId,
            'userName' => $this->userName,
            'userEmail' => $this->userEmail,
            'userPhone' => $this->userPhone,
            'documentType' => $this->documentType,
            'documentNumber' => $this->documentNumber,
            'maxAmount' => $this->maxAmount,
            'dailyLimit' => $this->dailyLimit,
            'monthlyLimit' => $this->monthlyLimit,
            'pinCode' => $this->pinCode,
        ];
    }
}
