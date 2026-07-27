<?php

namespace App\DTO\Balance;

class TransferDto
{
    public function __construct(
        public readonly string $originAccountId,
        public readonly string $destinationAccountNumber,
        public readonly string $amount,
        public readonly string $currency = 'USD',
        public readonly ?string $notes = null,
        public readonly ?string $originalAmount = null,
        public readonly ?string $originalCurrency = null,
    ) {
    }

    public static function fromJson(array $data): self
    {
        return new self(
            originAccountId: $data['originAccountId'],
            destinationAccountNumber: $data['destinationAccountNumber'],
            amount: $data['amount'],
            currency: $data['currency'] ?? 'USD',
            notes: $data['notes'] ?? null,
            originalAmount: $data['originalAmount'] ?? null,
            originalCurrency: $data['originalCurrency'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'originAccountId' => $this->originAccountId,
            'destinationAccountNumber' => $this->destinationAccountNumber,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'originalAmount' => $this->originalAmount,
            'originalCurrency' => $this->originalCurrency,
        ];
    }
}
