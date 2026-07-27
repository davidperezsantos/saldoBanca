<?php

namespace App\Services;

class SystemTaxService
{
    private string $taxPercent;

    public function __construct()
    {
        $this->taxPercent = $_ENV['TAXES'] ?? '0';
    }

    public function getTaxPercent(): string
    {
        return $this->taxPercent;
    }
}
