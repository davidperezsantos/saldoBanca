<?php

namespace App\Services\Balance;

use App\DTO\Balance\RechargeDto;
use App\Entity\Balance\Recharge;
use App\Entity\Balance\ExchangeRate;
use App\Repository\Balance\RechargeRepository;
use App\Repository\Balance\AccountRepository;
use App\Services\BaseService;
use App\Services\SystemCurrencyService;
use App\Services\ExchangeRate\APIExchangeRate;
use Doctrine\ORM\EntityManagerInterface;

class RechargeService extends BaseService
{
    private RechargeRepository $rechargeRepository;
    private AccountRepository $accountRepository;
    private BalanceService $balanceService;
    private APIExchangeRate $apiExchangeRate;
    private SystemCurrencyService $systemCurrencyService;

    public function __construct(
        EntityManagerInterface $entityManager,
        RechargeRepository $rechargeRepository,
        AccountRepository $accountRepository,
        BalanceService $balanceService,
        APIExchangeRate $apiExchangeRate,
        SystemCurrencyService $systemCurrencyService
    ) {
        parent::__construct($entityManager);
        $this->rechargeRepository = $rechargeRepository;
        $this->accountRepository = $accountRepository;
        $this->balanceService = $balanceService;
        $this->apiExchangeRate = $apiExchangeRate;
        $this->systemCurrencyService = $systemCurrencyService;
    }

    public function createRecharge(RechargeDto $dto): Recharge
    {
        $account = $this->accountRepository->find($dto->accountId);
        if (!$account) {
            throw new \RuntimeException('Account not found');
        }

        if ($account->getStatus() !== 'active') {
            throw new \RuntimeException('Account is not active');
        }

        $baseCurrency = $this->systemCurrencyService->getBaseCurrency();
        $amount = $dto->amount;
        $currency = $dto->currency;
        $originalAmount = $dto->originalAmount;
        $originalCurrency = $dto->originalCurrency;
        $exchangeRate = null;

        if ($currency !== $baseCurrency) {
            try {
                $rate = $this->apiExchangeRate->getRate($currency);
                if ($rate === null) {
                    throw new \RuntimeException("No se encontró tasa para {$currency}");
                }
                $originalAmount = $amount;
                $originalCurrency = $currency;
                $amount = bcdiv($amount, (string)$rate, 2);
                $exchangeRate = (string)$rate;
                $currency = $baseCurrency;
            } catch (\Exception $e) {
                throw new \RuntimeException("No se pudo convertir {$currency} a {$baseCurrency}: " . $e->getMessage());
            }
        }

        $recharge = new Recharge();
        $recharge->setAccount($account);
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

        return $recharge;
    }

    public function processExternalRecharge(array $data): Recharge
    {
        $accountNumber = $data['accountNumber'] ?? null;
        if (!$accountNumber) {
            throw new \RuntimeException('Account number is required');
        }

        $account = $this->accountRepository->findByAccountNumber($accountNumber);
        if (!$account) {
            throw new \RuntimeException('Account not found');
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

    public function completeRecharge(string $id): Recharge
    {
        $recharge = $this->rechargeRepository->find($id);
        if (!$recharge) {
            throw new \RuntimeException('Recharge not found');
        }

        if ($recharge->getStatus() !== 'pending') {
            throw new \RuntimeException('Recharge is not in pending status');
        }

        $recharge->setStatus('completed');
        $this->flush();

        $this->balanceService->addBalance(
            accountId: $recharge->getAccount()->getId()->toString(),
            amount: $recharge->getAmount(),
            currency: $recharge->getCurrency(),
            type: 'recharge',
            referenceType: 'recharge',
            referenceId: $recharge->getId()->toString(),
            description: 'Recharge completed',
            performedBy: $recharge->getAuthorizedBy()
        );

        return $recharge;
    }

    public function failRecharge(string $id, string $reason): Recharge
    {
        $recharge = $this->rechargeRepository->find($id);
        if (!$recharge) {
            throw new \RuntimeException('Recharge not found');
        }

        $recharge->setStatus('failed');
        $recharge->setNotes($reason);
        $this->flush();

        return $recharge;
    }

    public function cancelRecharge(string $id): Recharge
    {
        $recharge = $this->rechargeRepository->find($id);
        if (!$recharge) {
            throw new \RuntimeException('Recharge not found');
        }

        if ($recharge->getStatus() !== 'pending') {
            throw new \RuntimeException('Can only cancel pending recharges');
        }

        $recharge->setStatus('cancelled');
        $this->flush();

        return $recharge;
    }

    public function getRecharge(string $id): ?Recharge
    {
        return $this->rechargeRepository->find($id);
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
