<?php

namespace App\Services;

class SystemCurrencyService
{
    private string $baseCurrency;
    private string $secondaryCurrency;

    public function __construct()
    {
        $this->baseCurrency = $_ENV['CURRENCY'] ?? 'USD';
        $this->secondaryCurrency = $_ENV['CURRENCY_SECUNDARY'] ?? '';
    }

    public function getBaseCurrency(): string
    {
        return $this->baseCurrency;
    }

    public function getSecondaryCurrency(): string
    {
        return $this->secondaryCurrency;
    }
}
