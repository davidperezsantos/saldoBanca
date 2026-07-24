<?php

namespace App\Services\Balance;

use App\DTO\Balance\InvoiceDto;
use App\Entity\Balance\Account;
use App\Entity\Balance\InvoicePayment;
use App\Exception\BusinessException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\Balance\InvoicePaymentRepository;
use App\Repository\Balance\AccountRepository;
use App\Services\BaseService;
use App\Services\ExchangeRate\APIExchangeRate;
use App\Services\SystemCurrencyService;
use Doctrine\ORM\EntityManagerInterface;

class InvoiceService extends BaseService
{
    private InvoicePaymentRepository $invoiceRepository;
    private AccountRepository $accountRepository;
    private BalanceService $balanceService;
    private APIExchangeRate $apiExchangeRate;
    private SystemCurrencyService $systemCurrencyService;
    private OperationEventService $operationEventService;

    public function __construct(
        EntityManagerInterface $entityManager,
        InvoicePaymentRepository $invoiceRepository,
        AccountRepository $accountRepository,
        BalanceService $balanceService,
        APIExchangeRate $apiExchangeRate,
        SystemCurrencyService $systemCurrencyService,
        OperationEventService $operationEventService,
        private AccountService $accountService,
        private DocumentNumberService $documentNumberService,
    ) {
        parent::__construct($entityManager);
        $this->invoiceRepository = $invoiceRepository;
        $this->accountRepository = $accountRepository;
        $this->balanceService = $balanceService;
        $this->apiExchangeRate = $apiExchangeRate;
        $this->systemCurrencyService = $systemCurrencyService;
        $this->operationEventService = $operationEventService;
    }

    public function createInvoice(InvoiceDto $dto, ?string $performedBy = null): InvoicePayment
    {
        $account = $this->accountRepository->find($dto->accountId);
        if (!$account) {
            throw new NotFoundException('Account not found');
        }

        $businessAccount = $this->resolveBusinessAccount($dto->businessAccountId);

        [$amount, $taxAmount, $totalAmount, $currency, $originalAmount, $originalCurrency, $exchangeRate] = $this->resolveAmounts($dto);

        $invoice = new InvoicePayment();
        $invoice->setAccount($account);
        $invoice->setBusinessAccount($businessAccount);
        $invoice->setInvoiceNumber($dto->invoiceNumber);
        $invoice->setInvoiceDate(new \DateTimeImmutable($dto->invoiceDate));
        $invoice->setDueDate($dto->dueDate ? new \DateTimeImmutable($dto->dueDate) : null);
        $invoice->setAmount($amount);
        $invoice->setTaxAmount($taxAmount);
        $invoice->setTotalAmount($totalAmount);
        $invoice->setCurrency($currency);
        $invoice->setOriginalAmount($originalAmount);
        $invoice->setOriginalCurrency($originalCurrency);
        $invoice->setExchangeRate($exchangeRate);
        $invoice->setExternalRef($dto->externalRef);
        $invoice->setExternalSystem($dto->externalSystem);
        $invoice->setCustomerCode($dto->customerCode);
        $invoice->setCustomerName($dto->customerName);
        $invoice->setNotes($dto->notes);
        $invoice->setStatus('pending');
        $invoice->setReceiptNumber($this->documentNumberService->next('invoice_receipt', 'FAC-'));

        $this->persist($invoice);
        $this->flush();

        $this->operationEventService->log('invoice', $invoice->getId()->toString(), 'pending', $performedBy);

        return $invoice;
    }

    /**
     * El negocio que realizó la operación es opcional (puede dejarse vacío) — pero si se manda,
     * tiene que ser una cuenta accountType=business real, para no terminar registrando cualquier
     * cuenta como "negocio" en el histórico de facturas.
     */
    private function resolveBusinessAccount(?string $businessAccountId): ?Account
    {
        if ($businessAccountId === null) {
            return null;
        }

        $businessAccount = $this->accountRepository->find($businessAccountId);
        if (!$businessAccount) {
            throw new NotFoundException('Business account not found');
        }

        if ($businessAccount->getAccountType() !== 'business') {
            throw new ValidationException('businessAccountId debe ser una cuenta de tipo business');
        }

        return $businessAccount;
    }

    /**
     * Resuelve amount/taxAmount/totalAmount/currency finales (siempre en la moneda base del
     * sistema), igual que Recharge/Transfer: si el cliente ya mandó originalAmount/
     * originalCurrency (calculados con el conversor del formulario), solo se registran; si no y
     * la moneda pedida no es la base, se convierten aquí los tres montos con la misma tasa para
     * que queden consistentes entre sí.
     *
     * @return array{0: string, 1: ?string, 2: string, 3: string, 4: ?string, 5: ?string, 6: ?string}
     */
    private function resolveAmounts(InvoiceDto $dto): array
    {
        $baseCurrency = $this->systemCurrencyService->getBaseCurrency();
        $amount = $dto->amount;
        $taxAmount = $dto->taxAmount;
        $totalAmount = $dto->totalAmount;
        $currency = $dto->currency;
        $originalAmount = $dto->originalAmount;
        $originalCurrency = $dto->originalCurrency;
        $exchangeRate = null;

        if ($originalAmount !== null && $originalCurrency !== null) {
            $currency = $baseCurrency;
            if ($originalCurrency !== $baseCurrency) {
                $rate = $this->apiExchangeRate->getRate($originalCurrency);
                if ($rate === null) {
                    throw new BusinessException("No se encontró tasa para {$originalCurrency}");
                }
                $exchangeRate = (string) $rate;
            }
        } elseif ($currency !== $baseCurrency) {
            $rate = $this->apiExchangeRate->getRate($currency);
            if ($rate === null) {
                throw new BusinessException("No se encontró tasa para {$currency}");
            }

            $originalAmount = $totalAmount;
            $originalCurrency = $currency;
            $exchangeRate = sprintf('%.8f', $rate);
            $amount = (string) round((float) bcdiv($amount, $exchangeRate, 4), 2);
            $taxAmount = $taxAmount !== null ? (string) round((float) bcdiv($taxAmount, $exchangeRate, 4), 2) : null;
            $totalAmount = (string) round((float) bcdiv($totalAmount, $exchangeRate, 4), 2);
            $currency = $baseCurrency;
        }

        return [$amount, $taxAmount, $totalAmount, $currency, $originalAmount, $originalCurrency, $exchangeRate];
    }

    public function processPayment(string $id, string $pinCode, ?string $performedBy = null): InvoicePayment
    {
        $invoice = $this->invoiceRepository->find($id);
        if (!$invoice) {
            throw new NotFoundException('Invoice not found');
        }

        $account = $invoice->getAccount();

        // Autocompletar: cuentas creadas antes de que existiera este PIN (o sin teléfono al
        // crearse) todavía no tienen uno — en vez de quedar bloqueadas para siempre, se genera acá
        // y se pide reintentar con el código recién enviado por WhatsApp.
        if ($account->getPinCode() === null) {
            $this->accountService->rotatePin($account);
            throw new BusinessException(
                'Esta cuenta no tenía código configurado; se generó uno nuevo y se envió por WhatsApp. Usa ese código para pagar.'
            );
        }

        if (!$this->accountService->verifyPin($account, $pinCode)) {
            throw new BusinessException('Invalid PIN');
        }

        $result = $this->entityManager->wrapInTransaction(function () use ($id, $invoice, $performedBy) {
            if (!$this->invoiceRepository->markStatusIfCurrent($id, 'pending', 'paid')) {
                throw new BusinessException('Invoice is not in pending status');
            }
            $invoice->setStatus('paid');
            $invoice->setPaymentDate(new \DateTimeImmutable());

            $this->balanceService->deductBalance(
                accountId: $invoice->getAccount()->getId()->toString(),
                amount: $invoice->getTotalAmount(),
                currency: $invoice->getCurrency(),
                type: 'invoice_pay',
                referenceType: 'invoice',
                referenceId: $invoice->getId()->toString(),
                description: 'Invoice payment: ' . $invoice->getInvoiceNumber(),
                performedBy: $performedBy
            );

            if ($invoice->getBusinessAccount() !== null) {
                $this->balanceService->addBalance(
                    accountId: $invoice->getBusinessAccount()->getId()->toString(),
                    amount: $invoice->getTotalAmount(),
                    currency: $invoice->getCurrency(),
                    type: 'invoice_credit',
                    referenceType: 'invoice',
                    referenceId: $invoice->getId()->toString(),
                    description: 'Invoice credit: ' . $invoice->getInvoiceNumber(),
                    performedBy: $performedBy
                );
            }

            $this->flush();

            $this->operationEventService->log('invoice', $id, 'paid', $performedBy);

            return $invoice;
        });

        $this->accountService->rotatePin($account);

        return $result;
    }

    public function cancelPayment(string $id, ?string $performedBy = null): InvoicePayment
    {
        return $this->entityManager->wrapInTransaction(function () use ($id, $performedBy) {
            $invoice = $this->invoiceRepository->find($id);
            if (!$invoice) {
                throw new NotFoundException('Invoice not found');
            }

            if (!$this->invoiceRepository->markStatusIfCurrent($id, 'paid', 'cancelled')) {
                throw new BusinessException('Can only cancel paid invoices');
            }
            $invoice->setStatus('cancelled');
            $invoice->setPaymentDate(null);

            $this->balanceService->addBalance(
                accountId: $invoice->getAccount()->getId()->toString(),
                amount: $invoice->getTotalAmount(),
                currency: $invoice->getCurrency(),
                type: 'adjustment',
                referenceType: 'invoice',
                referenceId: $invoice->getId()->toString(),
                description: 'Invoice payment cancelled: ' . $invoice->getInvoiceNumber(),
                performedBy: $performedBy
            );

            if ($invoice->getBusinessAccount() !== null) {
                $this->balanceService->deductBalance(
                    accountId: $invoice->getBusinessAccount()->getId()->toString(),
                    amount: $invoice->getTotalAmount(),
                    currency: $invoice->getCurrency(),
                    type: 'adjustment',
                    referenceType: 'invoice',
                    referenceId: $invoice->getId()->toString(),
                    description: 'Invoice credit reversed (cancelled): ' . $invoice->getInvoiceNumber(),
                    performedBy: $performedBy
                );
            }

            $this->flush();

            $this->operationEventService->log('invoice', $id, 'cancelled', $performedBy);

            return $invoice;
        });
    }

    public function refundPayment(string $id, ?string $performedBy = null): InvoicePayment
    {
        return $this->entityManager->wrapInTransaction(function () use ($id, $performedBy) {
            $invoice = $this->invoiceRepository->find($id);
            if (!$invoice) {
                throw new NotFoundException('Invoice not found');
            }

            if (!$this->invoiceRepository->markStatusIfCurrent($id, 'paid', 'refunded')) {
                throw new BusinessException('Can only refund paid invoices');
            }
            $invoice->setStatus('refunded');

            $this->balanceService->addBalance(
                accountId: $invoice->getAccount()->getId()->toString(),
                amount: $invoice->getTotalAmount(),
                currency: $invoice->getCurrency(),
                type: 'adjustment',
                referenceType: 'invoice',
                referenceId: $invoice->getId()->toString(),
                description: 'Invoice refunded: ' . $invoice->getInvoiceNumber(),
                performedBy: $performedBy
            );

            if ($invoice->getBusinessAccount() !== null) {
                $this->balanceService->deductBalance(
                    accountId: $invoice->getBusinessAccount()->getId()->toString(),
                    amount: $invoice->getTotalAmount(),
                    currency: $invoice->getCurrency(),
                    type: 'adjustment',
                    referenceType: 'invoice',
                    referenceId: $invoice->getId()->toString(),
                    description: 'Invoice credit reversed (refunded): ' . $invoice->getInvoiceNumber(),
                    performedBy: $performedBy
                );
            }

            $this->flush();

            $this->operationEventService->log('invoice', $id, 'refunded', $performedBy);

            return $invoice;
        });
    }

    public function getInvoice(string $id): ?InvoicePayment
    {
        return $this->invoiceRepository->find($id);
    }

    /**
     * En balance_invoice_payment, amount/taxAmount/totalAmount/currency SIEMPRE quedan en la
     * moneda base del sistema (así lo espera processPayment() al descontar saldo — ver
     * deductBalance()), y originalAmount/originalCurrency/exchangeRate registran en qué moneda
     * llegó la factura antes de esa conversión (puede ser cualquier moneda, sin relación con la
     * del cliente). Lo que se le muestra al usuario es una TERCERA cosa: los montos convertidos a
     * la moneda configurada en la cuenta del cliente (account.defaultCurrency) — la misma que ya
     * se usa para transferencias/saldo de cuenta. Los tres campos base* quedan disponibles aparte
     * por si hace falta ver la cifra real sin convertir. Usado por los controladores de Admin y de
     * la API pública, para que ambos muestren exactamente lo mismo.
     */
    public function serializeForDisplay(InvoicePayment $invoice, bool $detailed = false): array
    {
        $baseCurrency = $this->systemCurrencyService->getBaseCurrency();
        $displayCurrency = $invoice->getAccount()->getDefaultCurrency();
        $rate = $displayCurrency !== $baseCurrency ? $this->apiExchangeRate->getRate($displayCurrency) : null;
        $convert = fn(?string $amount) => $amount !== null && $rate !== null
            ? (string) round((float) bcmul($amount, (string) $rate, 4), 2)
            : $amount;

        $data = [
            'id' => $invoice->getId(),
            'receiptNumber' => $invoice->getReceiptNumber(),
            'accountId' => $invoice->getAccount()->getId(),
            'accountNumber' => $invoice->getAccount()->getAccountNumber(),
            'accountName' => $invoice->getAccount()->getBusinessName(),
            'businessAccountId' => $invoice->getBusinessAccount()?->getId(),
            'businessAccountName' => $invoice->getBusinessAccount()?->getBusinessName(),
            'invoiceNumber' => $invoice->getInvoiceNumber(),
            'invoiceDate' => $invoice->getInvoiceDate()->format('Y-m-d'),
            'dueDate' => $invoice->getDueDate()?->format('Y-m-d'),
            'amount' => $convert($invoice->getAmount()),
            'taxAmount' => $convert($invoice->getTaxAmount()),
            'totalAmount' => $convert($invoice->getTotalAmount()),
            'currency' => $displayCurrency,
            'baseAmount' => $invoice->getAmount(),
            'baseTaxAmount' => $invoice->getTaxAmount(),
            'baseTotalAmount' => $invoice->getTotalAmount(),
            'baseCurrency' => $baseCurrency,
            'originalAmount' => $invoice->getOriginalAmount(),
            'originalCurrency' => $invoice->getOriginalCurrency(),
            'exchangeRate' => $invoice->getExchangeRate(),
            'status' => $invoice->getStatus(),
            'paymentDate' => $invoice->getPaymentDate()?->format('Y-m-d'),
            'customerCode' => $invoice->getCustomerCode(),
            'customerName' => $invoice->getCustomerName(),
            'createdAt' => $invoice->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];

        if ($detailed) {
            $data['externalRef'] = $invoice->getExternalRef();
            $data['externalSystem'] = $invoice->getExternalSystem();
            $data['notes'] = $invoice->getNotes();
        }

        return $data;
    }

    /**
     * Busca una factura por su número legible (ej. "FAC-2000"), acotado a la cuenta —
     * el número lo asigna el sistema externo y no es único a nivel global.
     */
    public function findByAccountAndNumber(string $accountId, string $invoiceNumber): ?InvoicePayment
    {
        return $this->invoiceRepository->createQueryBuilder('i')
            ->andWhere('i.account = :accountId')
            ->andWhere('i.invoiceNumber = :invoiceNumber')
            ->setParameter('accountId', $accountId)
            ->setParameter('invoiceNumber', $invoiceNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Marca una factura como pagada usando el saldo de un autorizado (llamado desde
     * AuthorizedService::spend()) en vez del flujo normal de processPayment(), que descuenta
     * directamente del availableBalance de la cuenta.
     */
    public function markPaidExternally(InvoicePayment $invoice, ?string $performedBy = null, ?string $paymentMethod = null): InvoicePayment
    {
        if (!$this->invoiceRepository->markStatusIfCurrent($invoice->getId()->toString(), 'pending', 'paid')) {
            throw new BusinessException('Invoice is not in pending status');
        }

        $invoice->setStatus('paid');
        $invoice->setPaymentDate(new \DateTimeImmutable());
        $invoice->setPaymentMethod($paymentMethod);
        $this->flush();

        $this->operationEventService->log('invoice', $invoice->getId()->toString(), 'paid', $performedBy);

        return $invoice;
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
