<?php

namespace App\DTO\Balance;

class InvoiceDto
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $invoiceNumber,
        public readonly string $invoiceDate,
        public readonly ?string $dueDate = null,
        public readonly string $amount,
        public readonly ?string $taxAmount = null,
        public readonly string $totalAmount,
        public readonly string $currency = 'USD',
        public readonly ?string $externalRef = null,
        public readonly ?string $externalSystem = null,
        public readonly ?string $customerCode = null,
        public readonly ?string $customerName = null,
        public readonly ?string $notes = null,
    ) {
    }

    public static function fromJson(array $data): self
    {
        return new self(
            accountId: $data['accountId'],
            invoiceNumber: $data['invoiceNumber'],
            invoiceDate: $data['invoiceDate'],
            dueDate: $data['dueDate'] ?? null,
            amount: $data['amount'],
            taxAmount: $data['taxAmount'] ?? null,
            totalAmount: $data['totalAmount'],
            currency: $data['currency'] ?? 'USD',
            externalRef: $data['externalRef'] ?? null,
            externalSystem: $data['externalSystem'] ?? null,
            customerCode: $data['customerCode'] ?? null,
            customerName: $data['customerName'] ?? null,
            notes: $data['notes'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'accountId' => $this->accountId,
            'invoiceNumber' => $this->invoiceNumber,
            'invoiceDate' => $this->invoiceDate,
            'dueDate' => $this->dueDate,
            'amount' => $this->amount,
            'taxAmount' => $this->taxAmount,
            'totalAmount' => $this->totalAmount,
            'currency' => $this->currency,
            'externalRef' => $this->externalRef,
            'externalSystem' => $this->externalSystem,
            'customerCode' => $this->customerCode,
            'customerName' => $this->customerName,
            'notes' => $this->notes,
        ];
    }
}
