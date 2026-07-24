<?php

namespace App\Services\Balance;

use App\DTO\Balance\RechargeDto;
use App\Entity\Balance\Recharge;
use App\Entity\Balance\ExchangeRate;
use App\Exception\BusinessException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\Balance\RechargeRepository;
use App\Repository\Balance\AccountRepository;
use App\Services\BaseService;
use App\Services\SystemCurrencyService;
use App\Services\ExchangeRate\APIExchangeRate;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

class RechargeService extends BaseService
{
    private RechargeRepository $rechargeRepository;
    private AccountRepository $accountRepository;
    private BalanceService $balanceService;
    private APIExchangeRate $apiExchangeRate;
    private SystemCurrencyService $systemCurrencyService;
    private DocumentNumberService $documentNumberService;
    private OperationEventService $operationEventService;

    public function __construct(
        EntityManagerInterface $entityManager,
        RechargeRepository $rechargeRepository,
        AccountRepository $accountRepository,
        BalanceService $balanceService,
        APIExchangeRate $apiExchangeRate,
        SystemCurrencyService $systemCurrencyService,
        DocumentNumberService $documentNumberService,
        OperationEventService $operationEventService
    ) {
        parent::__construct($entityManager);
        $this->rechargeRepository = $rechargeRepository;
        $this->accountRepository = $accountRepository;
        $this->balanceService = $balanceService;
        $this->apiExchangeRate = $apiExchangeRate;
        $this->systemCurrencyService = $systemCurrencyService;
        $this->documentNumberService = $documentNumberService;
        $this->operationEventService = $operationEventService;
    }

    public function createRecharge(RechargeDto $dto, ?string $performedBy = null): Recharge
    {
        $account = $this->accountRepository->find($dto->accountId);
        if (!$account) {
            throw new NotFoundException('Account not found');
        }

        if ($account->getStatus() !== 'active') {
            throw new BusinessException('Account is not active');
        }

        $baseCurrency = $this->systemCurrencyService->getBaseCurrency();
        $amount = $dto->amount;
        $currency = $dto->currency;
        $originalAmount = $dto->originalAmount;
        $originalCurrency = $dto->originalCurrency;
        $exchangeRate = null;

        if ($originalAmount !== null && $originalCurrency !== null) {
            $amount = $dto->amount;
            $currency = $baseCurrency;
            if ($originalCurrency !== $baseCurrency) {
                $exchangeRate = $this->apiExchangeRate->getRate($originalCurrency);
                if ($exchangeRate === null) {
                    throw new BusinessException("No se encontró tasa para {$originalCurrency}");
                }
                $exchangeRate = (string)$exchangeRate;
            }
        } elseif ($currency !== $baseCurrency) {
            try {
                $rate = $this->apiExchangeRate->getRate($currency);
                if ($rate === null) {
                    throw new BusinessException("No se encontró tasa para {$currency}");
                }
                $originalAmount = $amount;
                $originalCurrency = $currency;
                $amount = (string)round((float)bcdiv($amount, sprintf('%.8f', $rate), 4), 2);
                $exchangeRate = sprintf('%.8f', $rate);
                $currency = $baseCurrency;
            } catch (\Exception $e) {
                throw new BusinessException("No se pudo convertir {$currency} a {$baseCurrency}: " . $e->getMessage());
            }
        }

        $recharge = new Recharge();
        $recharge->setAccount($account);
        $recharge->setReceiptNumber($this->documentNumberService->next('recharge_receipt', 'REC-'));
        $recharge->setAmount($amount);
        $recharge->setCurrency($currency);
        $recharge->setOriginalAmount($originalAmount);
        $recharge->setOriginalCurrency($originalCurrency);
        $recharge->setExchangeRate($exchangeRate);
        $recharge->setRechargeType($dto->rechargeType);
        $recharge->setReferenceNumber($dto->referenceNumber);
        $recharge->setExternalSystem($dto->externalSystem);
        $recharge->setPaymentMethod($dto->paymentMethod);
        $recharge->setNotes($dto->notes);
        $recharge->setStatus('pending');

        $this->persist($recharge);
        $this->flush();

        $this->operationEventService->log('recharge', $recharge->getId()->toString(), 'pending', $performedBy);

        return $recharge;
    }

    public function processExternalRecharge(array $data): Recharge
    {
        $accountNumber = $data['accountNumber'] ?? null;
        if (!$accountNumber) {
            throw new ValidationException('Account number is required');
        }

        $account = $this->accountRepository->findByAccountNumber($accountNumber);
        if (!$account) {
            throw new NotFoundException('Account not found');
        }

        $dto = new RechargeDto(
            accountId: $account->getId()->toString(),
            amount: $data['amount'],
            currency: $data['currency'] ?? 'USD',
            rechargeType: 'external',
            referenceNumber: $data['referenceNumber'] ?? null,
            externalSystem: $data['externalSystem'] ?? null,
            paymentMethod: $data['paymentMethod'] ?? null,
            notes: $data['notes'] ?? null,
        );

        return $this->createRecharge($dto);
    }

    /**
     * Punto de entrada idempotente para webhooks de pasarelas de pago: si ya existe una recarga
     * para el mismo (externalSystem, referenceNumber), la devuelve tal cual sin reprocesar
     * (evita duplicar el crédito ante reintentos del webhook). Si no existe, crea la recarga y la
     * completa de una vez (la pasarela ya está confirmando que el dinero llegó, no hace falta un
     * paso manual de "completar" aparte).
     */
    public function processWebhookRecharge(array $data): Recharge
    {
        $externalSystem = $data['externalSystem'] ?? null;
        $referenceNumber = $data['referenceNumber'] ?? null;

        if (!$externalSystem || !$referenceNumber) {
            throw new ValidationException('externalSystem and referenceNumber are required for webhook recharges');
        }

        $existing = $this->rechargeRepository->findByExternalReference($externalSystem, $referenceNumber);
        if ($existing) {
            return $existing;
        }

        try {
            $recharge = $this->processExternalRecharge($data);
        } catch (UniqueConstraintViolationException $e) {
            // Carrera: dos entregas del mismo webhook llegaron casi al mismo tiempo y ambas
            // pasaron el chequeo de arriba antes de que la primera terminara de insertar.
            $existing = $this->rechargeRepository->findByExternalReference($externalSystem, $referenceNumber);
            if ($existing) {
                return $existing;
            }
            throw $e;
        }

        return $this->completeRecharge($recharge->getId()->toString());
    }

    public function completeRecharge(string $id, ?string $performedBy = null): Recharge
    {
        return $this->entityManager->wrapInTransaction(function () use ($id, $performedBy) {
            $recharge = $this->rechargeRepository->find($id);
            if (!$recharge) {
                throw new NotFoundException('Recharge not found');
            }

            if (!$this->rechargeRepository->markStatusIfCurrent($id, 'pending', 'completed')) {
                throw new BusinessException('Recharge is not in pending status');
            }
            $recharge->setStatus('completed');
            $recharge->setAuthorizedBy($performedBy);

            $this->balanceService->addBalance(
                accountId: $recharge->getAccount()->getId()->toString(),
                amount: $recharge->getAmount(),
                currency: $recharge->getCurrency(),
                type: 'recharge',
                referenceType: 'recharge',
                referenceId: $recharge->getId()->toString(),
                description: 'Recharge completed',
                performedBy: $performedBy
            );

            $this->flush();

            $this->operationEventService->log('recharge', $id, 'completed', $performedBy);

            return $recharge;
        });
    }

    public function failRecharge(string $id, string $reason, ?string $performedBy = null): Recharge
    {
        $recharge = $this->rechargeRepository->find($id);
        if (!$recharge) {
            throw new NotFoundException('Recharge not found');
        }

        if (!$this->rechargeRepository->markStatusIfCurrent($id, $recharge->getStatus(), 'failed')) {
            throw new BusinessException('Recharge already changed status concurrently, retry');
        }
        $recharge->setStatus('failed');
        $recharge->setNotes($reason);
        $this->flush();

        $this->operationEventService->log('recharge', $id, 'failed', $performedBy, $reason);

        return $recharge;
    }

    public function cancelRecharge(string $id, ?string $performedBy = null): Recharge
    {
        $recharge = $this->rechargeRepository->find($id);
        if (!$recharge) {
            throw new NotFoundException('Recharge not found');
        }

        if (!$this->rechargeRepository->markStatusIfCurrent($id, 'pending', 'cancelled')) {
            throw new BusinessException('Can only cancel pending recharges');
        }
        $recharge->setStatus('cancelled');
        $this->flush();

        $this->operationEventService->log('recharge', $id, 'cancelled', $performedBy);

        return $recharge;
    }

    public function getRecharge(string $id): ?Recharge
    {
        return $this->rechargeRepository->find($id);
    }

    public function findByExternalReference(string $externalSystem, string $referenceNumber): ?Recharge
    {
        return $this->rechargeRepository->findByExternalReference($externalSystem, $referenceNumber);
    }

    public function listRecharges(array $filters = []): array
    {
        $qb = $this->rechargeRepository->createQueryBuilder('r');

        if (isset($filters['accountId'])) {
            $qb->andWhere('r.account = :accountId')
               ->setParameter('accountId', $filters['accountId']);
        }

        if (isset($filters['status'])) {
            $qb->andWhere('r.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (isset($filters['rechargeType'])) {
            $qb->andWhere('r.rechargeType = :rechargeType')
               ->setParameter('rechargeType', $filters['rechargeType']);
        }

        $qb->orderBy('r.createdAt', 'DESC');

        if (isset($filters['limit'])) {
            $qb->setMaxResults($filters['limit']);
        }

        if (isset($filters['offset'])) {
            $qb->setFirstResult($filters['offset']);
        }

        return $qb->getQuery()->getResult();
    }
}
