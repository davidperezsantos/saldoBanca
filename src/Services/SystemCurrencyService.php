<?php

namespace App\Services;

class SystemCurrencyService
{
    private string $baseCurrency;

    public function __construct()
    {
        $this->baseCurrency = $_ENV['CURRENCY'] ?? 'USD';
    }

    public function getBaseCurrency(): string
    {
        return $this->baseCurrency;
    }
}
