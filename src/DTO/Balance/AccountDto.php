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
        public readonly ?string $maxPerTransfer = null,
        public readonly ?string $maxDaily = null,
        public readonly ?string $maxMonthly = null,
        public readonly ?string $payoutCurrencyPercent = null,
        public readonly ?string $payoutSecondaryCurrencyPercent = null,
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
            maxPerTransfer: $data['maxPerTransfer'] ?? null,
            maxDaily: $data['maxDaily'] ?? null,
            maxMonthly: $data['maxMonthly'] ?? null,
            payoutCurrencyPercent: $data['payoutCurrencyPercent'] ?? null,
            payoutSecondaryCurrencyPercent: $data['payoutSecondaryCurrencyPercent'] ?? null,
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
            'maxPerTransfer' => $this->maxPerTransfer,
            'maxDaily' => $this->maxDaily,
            'maxMonthly' => $this->maxMonthly,
            'payoutCurrencyPercent' => $this->payoutCurrencyPercent,
            'payoutSecondaryCurrencyPercent' => $this->payoutSecondaryCurrencyPercent,
        ];
    }
}
