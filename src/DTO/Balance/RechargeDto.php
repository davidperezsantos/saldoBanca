<?php

namespace App\DTO\Balance;

class RechargeDto
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $amount,
        public readonly string $currency = 'USD',
        public readonly string $rechargeType = 'manual',
        public readonly ?string $referenceNumber = null,
        public readonly ?string $externalSystem = null,
        public readonly ?string $paymentMethod = null,
        public readonly ?string $notes = null,
        public readonly ?string $originalAmount = null,
        public readonly ?string $originalCurrency = null,
    ) {
    }

    public static function fromJson(array $data): self
    {
        return new self(
            accountId: $data['accountId'],
            amount: $data['amount'],
            currency: $data['currency'] ?? 'USD',
            rechargeType: $data['rechargeType'] ?? 'manual',
            referenceNumber: $data['referenceNumber'] ?? null,
            externalSystem: $data['externalSystem'] ?? null,
            paymentMethod: $data['paymentMethod'] ?? null,
            notes: $data['notes'] ?? null,
            originalAmount: $data['originalAmount'] ?? null,
            originalCurrency: $data['originalCurrency'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'accountId' => $this->accountId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'rechargeType' => $this->rechargeType,
            'referenceNumber' => $this->referenceNumber,
            'externalSystem' => $this->externalSystem,
            'paymentMethod' => $this->paymentMethod,
            'notes' => $this->notes,
            'originalAmount' => $this->originalAmount,
            'originalCurrency' => $this->originalCurrency,
        ];
    }
}
