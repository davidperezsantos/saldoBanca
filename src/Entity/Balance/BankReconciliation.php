<?php

namespace App\Entity\Balance;

use App\Entity\Base\BaseEntity;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\Balance\BankReconciliationRepository;

/**
 * Un "run" de conciliación bancaria: compara lo que el sistema acreditó (Recharge.status=completed)
 * para una pasarela/sistema externo contra la lista de movimientos que ese externo reporta haber
 * procesado. `discrepancies` guarda el detalle (solo lo que NO cuadra, para no duplicar en JSON lo
 * que ya está en balance_recharge); los contadores permiten listar runs sin tener que releer el JSON.
 */
#[ORM\Entity(repositoryClass: BankReconciliationRepository::class)]
#[ORM\Table(name: 'balance_bank_reconciliation')]
#[ORM\HasLifecycleCallbacks]
class BankReconciliation extends BaseEntity
{
    #[ORM\Column(length: 100)]
    private ?string $gatewayCode = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $periodStart = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $periodEnd = null;

    #[ORM\Column(type: 'integer')]
    private int $totalExternal = 0;

    #[ORM\Column(type: 'integer')]
    private int $totalMatched = 0;

    #[ORM\Column(type: 'integer')]
    private int $totalMismatched = 0;

    #[ORM\Column(type: 'integer')]
    private int $totalMissingInternal = 0;

    #[ORM\Column(type: 'integer')]
    private int $totalMissingExternal = 0;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $discrepancies = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $performedBy = null;

    public function getGatewayCode(): ?string
    {
        return $this->gatewayCode;
    }

    public function setGatewayCode(string $gatewayCode): static
    {
        $this->gatewayCode = $gatewayCode;
        return $this;
    }

    public function getPeriodStart(): ?\DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function setPeriodStart(?\DateTimeImmutable $periodStart): static
    {
        $this->periodStart = $periodStart;
        return $this;
    }

    public function getPeriodEnd(): ?\DateTimeImmutable
    {
        return $this->periodEnd;
    }

    public function setPeriodEnd(?\DateTimeImmutable $periodEnd): static
    {
        $this->periodEnd = $periodEnd;
        return $this;
    }

    public function getTotalExternal(): int
    {
        return $this->totalExternal;
    }

    public function setTotalExternal(int $totalExternal): static
    {
        $this->totalExternal = $totalExternal;
        return $this;
    }

    public function getTotalMatched(): int
    {
        return $this->totalMatched;
    }

    public function setTotalMatched(int $totalMatched): static
    {
        $this->totalMatched = $totalMatched;
        return $this;
    }

    public function getTotalMismatched(): int
    {
        return $this->totalMismatched;
    }

    public function setTotalMismatched(int $totalMismatched): static
    {
        $this->totalMismatched = $totalMismatched;
        return $this;
    }

    public function getTotalMissingInternal(): int
    {
        return $this->totalMissingInternal;
    }

    public function setTotalMissingInternal(int $totalMissingInternal): static
    {
        $this->totalMissingInternal = $totalMissingInternal;
        return $this;
    }

    public function getTotalMissingExternal(): int
    {
        return $this->totalMissingExternal;
    }

    public function setTotalMissingExternal(int $totalMissingExternal): static
    {
        $this->totalMissingExternal = $totalMissingExternal;
        return $this;
    }

    public function getDiscrepancies(): ?array
    {
        return $this->discrepancies;
    }

    public function setDiscrepancies(?array $discrepancies): static
    {
        $this->discrepancies = $discrepancies;
        return $this;
    }

    public function getPerformedBy(): ?string
    {
        return $this->performedBy;
    }

    public function setPerformedBy(?string $performedBy): static
    {
        $this->performedBy = $performedBy;
        return $this;
    }
}
