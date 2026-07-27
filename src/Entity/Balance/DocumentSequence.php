<?php

namespace App\Entity\Balance;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\Balance\DocumentSequenceRepository;

/**
 * Contador correlativo por tipo de comprobante generado por el propio sistema (recibos de recarga,
 * de transferencia, etc. — distinto de InvoicePayment.invoiceNumber, que lo asigna el sistema
 * externo). Una fila por tipo, pre-sembrada en la migración para que el primer
 * DocumentNumberRepository::findForUpdate() siempre encuentre una fila que bloquear en vez de
 * competir por insertarla (evita la carrera de "primer uso").
 */
#[ORM\Entity(repositoryClass: DocumentSequenceRepository::class)]
#[ORM\Table(name: 'balance_document_sequence')]
class DocumentSequence
{
    #[ORM\Id]
    #[ORM\Column(length: 50)]
    private string $documentType;

    #[ORM\Column(type: 'bigint')]
    private string $lastValue = '0';

    public function getDocumentType(): string
    {
        return $this->documentType;
    }

    public function setDocumentType(string $documentType): static
    {
        $this->documentType = $documentType;
        return $this;
    }

    public function getLastValue(): string
    {
        return $this->lastValue;
    }

    public function setLastValue(string $lastValue): static
    {
        $this->lastValue = $lastValue;
        return $this;
    }
}
