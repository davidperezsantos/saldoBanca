<?php

namespace App\Services\Balance;

use App\DTO\Balance\InvoiceDto;
use App\Entity\Balance\InvoicePayment;
use App\Repository\Balance\InvoicePaymentRepository;
use App\Repository\Balance\AccountRepository;
use App\Services\BaseService;
use Doctrine\ORM\EntityManagerInterface;

class InvoiceService extends BaseService
{
    private InvoicePaymentRepository $invoiceRepository;
    private AccountRepository $accountRepository;
    private BalanceService $balanceService;

    public function __construct(
        EntityManagerInterface $entityManager,
        InvoicePaymentRepository $invoiceRepository,
        AccountRepository $accountRepository,
        BalanceService $balanceService
    ) {
        parent::__construct($entityManager);
        $this->invoiceRepository = $invoiceRepository;
        $this->accountRepository = $accountRepository;
        $this->balanceService = $balanceService;
    }

    public function createInvoice(InvoiceDto $dto): InvoicePayment
    {
        $account = $this->accountRepository->find($dto->accountId);
        if (!$account) {
            throw new \RuntimeException('Account not found');
        }

        $invoice = new InvoicePayment();
        $invoice->setAccount($account);
        $invoice->setInvoiceNumber($dto->invoiceNumber);
        $invoice->setInvoiceDate(new \DateTimeImmutable($dto->invoiceDate));
        $invoice->setDueDate($dto->dueDate ? new \DateTimeImmutable($dto->dueDate) : null);
        $invoice->setAmount($dto->amount);
        $invoice->setTaxAmount($dto->taxAmount);
        $invoice->setTotalAmount($dto->totalAmount);
        $invoice->setCurrency($dto->currency);
        $invoice->setExternalRef($dto->externalRef);
        $invoice->setExternalSystem($dto->externalSystem);
        $invoice->setCustomerCode($dto->customerCode);
        $invoice->setCustomerName($dto->customerName);
        $invoice->setNotes($dto->notes);
        $invoice->setStatus('pending');

        $this->persist($invoice);
        $this->flush();

        return $invoice;
    }

    public function processPayment(string $id): InvoicePayment
    {
        $invoice = $this->invoiceRepository->find($id);
        if (!$invoice) {
            throw new \RuntimeException('Invoice not found');
        }

        if ($invoice->getStatus() !== 'pending') {
            throw new \RuntimeException('Invoice is not in pending status');
        }

        $this->balanceService->deductBalance(
            accountId: $invoice->getAccount()->getId()->toString(),
            amount: $invoice->getTotalAmount(),
            currency: $invoice->getCurrency(),
            type: 'invoice_pay',
            referenceType: 'invoice',
            referenceId: $invoice->getId()->toString(),
            description: 'Invoice payment: ' . $invoice->getInvoiceNumber(),
            performedBy: null
        );

        $invoice->setStatus('paid');
        $invoice->setPaymentDate(new \DateTimeImmutable());
        $this->flush();

        return $invoice;
    }

    public function cancelPayment(string $id): InvoicePayment
    {
        $invoice = $this->invoiceRepository->find($id);
        if (!$invoice) {
            throw new \RuntimeException('Invoice not found');
        }

        if ($invoice->getStatus() !== 'paid') {
            throw new \RuntimeException('Can only cancel paid invoices');
        }

        $this->balanceService->addBalance(
            accountId: $invoice->getAccount()->getId()->toString(),
            amount: $invoice->getTotalAmount(),
            currency: $invoice->getCurrency(),
            type: 'adjustment',
            referenceType: 'invoice',
            referenceId: $invoice->getId()->toString(),
            description: 'Invoice payment cancelled: ' . $invoice->getInvoiceNumber(),
            performedBy: null
        );

        $invoice->setStatus('cancelled');
        $invoice->setPaymentDate(null);
        $this->flush();

        return $invoice;
    }

    public function refundPayment(string $id): InvoicePayment
    {
        $invoice = $this->invoiceRepository->find($id);
        if (!$invoice) {
            throw new \RuntimeException('Invoice not found');
        }

        if ($invoice->getStatus() !== 'paid') {
            throw new \RuntimeException('Can only refund paid invoices');
        }

        $this->balanceService->addBalance(
            accountId: $invoice->getAccount()->getId()->toString(),
            amount: $invoice->getTotalAmount(),
            currency: $invoice->getCurrency(),
            type: 'adjustment',
            referenceType: 'invoice',
            referenceId: $invoice->getId()->toString(),
            description: 'Invoice refunded: ' . $invoice->getInvoiceNumber(),
            performedBy: null
        );

        $invoice->setStatus('refunded');
        $this->flush();

        return $invoice;
    }

    public function getInvoice(string $id): ?InvoicePayment
    {
        return $this->invoiceRepository->find($id);
    }

    public function listInvoices(array $filters = []): array
    {
        $qb = $this->invoiceRepository->createQueryBuilder('ip');

        if (isset($filters['accountId'])) {
            $qb->andWhere('ip.account = :accountId')
               ->setParameter('accountId', $filters['accountId']);
        }

        if (isset($filters['status'])) {
            $qb->andWhere('ip.status = :status')
               ->setParameter('status', $filters['status']);
        }

        $qb->orderBy('ip.createdAt', 'DESC');

        if (isset($filters['limit'])) {
            $qb->setMaxResults($filters['limit']);
        }

        if (isset($filters['offset'])) {
            $qb->setFirstResult($filters['offset']);
        }

        return $qb->getQuery()->getResult();
    }

    public function getInvoiceSummary(string $accountId): array
    {
        $invoices = $this->listInvoices(['accountId' => $accountId, 'limit' => 1000]);

        $summary = [
            'total_pending' => '0.00',
            'total_paid' => '0.00',
            'total_cancelled' => '0.00',
            'total_refunded' => '0.00',
            'count' => count($invoices),
        ];

        foreach ($invoices as $invoice) {
            $amount = $invoice->getTotalAmount();
            switch ($invoice->getStatus()) {
                case 'pending':
                    $summary['total_pending'] = bcadd($summary['total_pending'], $amount, 2);
                    break;
                case 'paid':
                    $summary['total_paid'] = bcadd($summary['total_paid'], $amount, 2);
                    break;
                case 'cancelled':
                    $summary['total_cancelled'] = bcadd($summary['total_cancelled'], $amount, 2);
                    break;
                case 'refunded':
                    $summary['total_refunded'] = bcadd($summary['total_refunded'], $amount, 2);
                    break;
            }
        }

        return $summary;
    }
}
