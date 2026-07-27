<?php

namespace App\Services\Balance;

use App\DTO\Balance\TransferDto;
use App\Entity\Balance\Account;
use App\Entity\Balance\Transfer;
use App\Exception\BusinessException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\Balance\TransferRepository;
use App\Repository\Balance\AccountRepository;
use App\Services\BaseService;
use App\Services\ExchangeRate\APIExchangeRate;
use App\Services\SystemCurrencyService;
use Doctrine\ORM\EntityManagerInterface;

class TransferService extends BaseService
{
    private TransferRepository $transferRepository;
    private AccountRepository $accountRepository;
    private BalanceService $balanceService;
    private DocumentNumberService $documentNumberService;
    private APIExchangeRate $apiExchangeRate;
    private SystemCurrencyService $systemCurrencyService;
    private OperationEventService $operationEventService;

    public function __construct(
        EntityManagerInterface $entityManager,
        TransferRepository $transferRepository,
        AccountRepository $accountRepository,
        BalanceService $balanceService,
        DocumentNumberService $documentNumberService,
        APIExchangeRate $apiExchangeRate,
        SystemCurrencyService $systemCurrencyService,
        OperationEventService $operationEventService
    ) {
        parent::__construct($entityManager);
        $this->transferRepository = $transferRepository;
        $this->accountRepository = $accountRepository;
        $this->balanceService = $balanceService;
        $this->documentNumberService = $documentNumberService;
        $this->apiExchangeRate = $apiExchangeRate;
        $this->systemCurrencyService = $systemCurrencyService;
        $this->operationEventService = $operationEventService;
    }

    public function createTransfer(TransferDto $dto, ?string $performedBy = null): Transfer
    {
        $originAccount = $this->accountRepository->find($dto->originAccountId);
        if (!$originAccount) {
            throw new NotFoundException('Origin account not found');
        }

        if ($originAccount->getStatus() !== 'active') {
            throw new BusinessException('Origin account is not active');
        }

        if (!$originAccount->isAllowTransfers()) {
            throw new BusinessException('Transfers are not allowed for this account');
        }

        [$amount, $currency, $originalAmount, $originalCurrency, $exchangeRate] = $this->resolveAmount($dto);

        $this->checkTransferLimits($originAccount, $amount);

        $destAccount = $this->accountRepository->findByAccountNumber($dto->destinationAccountNumber);
        if (!$destAccount) {
            throw new NotFoundException('Destination account not found');
        }

        if ($destAccount->getStatus() !== 'active') {
            throw new BusinessException('Destination account is not active');
        }

        if ($originAccount->getId()->toString() === $destAccount->getId()->toString()) {
            throw new ValidationException('Cannot transfer to the same account');
        }

        $transfer = new Transfer();
        $transfer->setOriginAccount($originAccount);
        $transfer->setReceiptNumber($this->documentNumberService->next('transfer_receipt', 'TRA-'));
        $transfer->setDestinationAccount($destAccount);
        $transfer->setAmount($amount);
        $transfer->setCurrency($currency);
        $transfer->setOriginalAmount($originalAmount);
        $transfer->setOriginalCurrency($originalCurrency);
        $transfer->setExchangeRate($exchangeRate);
        $transfer->setNotes($dto->notes);
        $transfer->setOriginAccountNumber($originAccount->getAccountNumber());
        $transfer->setDestAccountNumber($destAccount->getAccountNumber());
        $transfer->setStatus('pending');

        $this->persist($transfer);
        $this->flush();

        $this->operationEventService->log('transfer', $transfer->getId()->toString(), 'pending', $performedBy);

        return $transfer;
    }

    /**
     * Resuelve el monto/moneda final de la transferencia (siempre en la moneda base del sistema),
     * igual que RechargeService::createRecharge(): si el cliente ya mandó originalAmount/
     * originalCurrency (calculados con el conversor del formulario), solo se registran; si no y
     * la moneda pedida no es la base, se convierte aquí mismo consultando la tasa vigente.
     *
     * @return array{0: string, 1: string, 2: ?string, 3: ?string, 4: ?string} [amount, currency, originalAmount, originalCurrency, exchangeRate]
     */
    private function resolveAmount(TransferDto $dto): array
    {
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
                $rate = $this->apiExchangeRate->getRate($originalCurrency);
                if ($rate === null) {
                    throw new BusinessException("No se encontró tasa para {$originalCurrency}");
                }
                $exchangeRate = (string) $rate;
            }
        } elseif ($currency !== $baseCurrency) {
            try {
                $rate = $this->apiExchangeRate->getRate($currency);
                if ($rate === null) {
                    throw new BusinessException("No se encontró tasa para {$currency}");
                }
                $originalAmount = $amount;
                $originalCurrency = $currency;
                $amount = (string) round((float) bcdiv($amount, sprintf('%.8f', $rate), 4), 2);
                $exchangeRate = sprintf('%.8f', $rate);
                $currency = $baseCurrency;
            } catch (BusinessException $e) {
                throw $e;
            } catch (\Exception $e) {
                throw new BusinessException("No se pudo convertir {$currency} a {$baseCurrency}: " . $e->getMessage());
            }
        }

        return [$amount, $currency, $originalAmount, $originalCurrency, $exchangeRate];
    }

    public function processTransfer(string $id, ?string $performedBy = null): Transfer
    {
        return $this->entityManager->wrapInTransaction(function () use ($id, $performedBy) {
            $transfer = $this->transferRepository->find($id);
            if (!$transfer) {
                throw new NotFoundException('Transfer not found');
            }

            // Se revalida aquí (no solo en createTransfer) porque entre la creación y el
            // procesamiento pueden haberse completado otras transferencias de la misma cuenta que
            // ya consuman el cupo diario/mensual.
            $this->checkTransferLimits($transfer->getOriginAccount(), $transfer->getAmount());

            if (!$this->transferRepository->markStatusIfCurrent($id, 'pending', 'completed')) {
                throw new BusinessException('Transfer is not in pending status');
            }
            $transfer->setStatus('completed');
            $transfer->setAuthorizedBy($performedBy);

            $this->balanceService->transferBalance(
                originAccountId: $transfer->getOriginAccount()->getId()->toString(),
                destAccountId: $transfer->getDestinationAccount()->getId()->toString(),
                amount: $transfer->getAmount(),
                currency: $transfer->getCurrency(),
                performedBy: $performedBy,
                referenceType: 'transfer',
                referenceId: $id
            );

            $this->flush();

            $this->operationEventService->log('transfer', $id, 'completed', $performedBy);

            return $transfer;
        });
    }

    public function cancelTransfer(string $id, ?string $performedBy = null): Transfer
    {
        $transfer = $this->transferRepository->find($id);
        if (!$transfer) {
            throw new NotFoundException('Transfer not found');
        }

        if (!$this->transferRepository->markStatusIfCurrent($id, 'pending', 'cancelled')) {
            throw new BusinessException('Can only cancel pending transfers');
        }
        $transfer->setStatus('cancelled');
        $this->flush();

        $this->operationEventService->log('transfer', $id, 'cancelled', $performedBy);

        return $transfer;
    }

    public function getTransfer(string $id): ?Transfer
    {
        return $this->transferRepository->find($id);
    }

    public function listTransfers(array $filters = []): array
    {
        $qb = $this->transferRepository->createQueryBuilder('t');

        if (isset($filters['accountId'])) {
            $qb->andWhere('t.originAccount = :accountId OR t.destinationAccount = :accountId')
               ->setParameter('accountId', $filters['accountId']);
        }

        if (isset($filters['status'])) {
            $qb->andWhere('t.status = :status')
               ->setParameter('status', $filters['status']);
        }

        $qb->orderBy('t.createdAt', 'DESC');

        if (isset($filters['limit'])) {
            $qb->setMaxResults($filters['limit']);
        }

        if (isset($filters['offset'])) {
            $qb->setFirstResult($filters['offset']);
        }

        return $qb->getQuery()->getResult();
    }

    public function getTransferLimits(string $accountId): array
    {
        $account = $this->accountRepository->find($accountId);
        if (!$account) {
            throw new NotFoundException('Account not found');
        }

        // El saldo real de la cuenta vive en la moneda base del sistema (todo lo que entra se
        // convierte a esa moneda al recargar/transferir — ver resolveAmount()), no necesariamente
        // en account.defaultCurrency: una cuenta puede tener defaultCurrency=USD sin tener nunca
        // una fila de AccountBalance en USD si todo lo que recibió se convirtió a EUR. Se busca por
        // la base (ahí está el saldo real) y se convierte a defaultCurrency solo para mostrar —
        // igual que Admin\AccountController::index() — porque es la moneda en la que el usuario
        // configuró la cuenta y espera ver sus montos.
        $baseCurrency = $this->systemCurrencyService->getBaseCurrency();
        $displayCurrency = $account->getDefaultCurrency();
        $rate = $displayCurrency !== $baseCurrency ? $this->apiExchangeRate->getRate($displayCurrency) : null;
        $fromBase = fn(string $amount): string => $rate !== null
            ? (string) round((float) bcmul($amount, (string) $rate, 4), 2)
            : $amount;

        $balance = $this->balanceService->getBalance($accountId, $baseCurrency);
        $available = $fromBase($balance ? $balance->getAvailableBalance() : '0.00');

        // sumCompletedAmountSince() suma Transfer.amount, que siempre queda en la moneda base
        // (ver resolveAmount()) — hay que convertirlo a displayCurrency como el resto de la
        // respuesta, para no mezclar "usado" en una moneda con "máximo" (maxDaily/maxMonthly, tal
        // como los configuró el admin en defaultCurrency) en otra.
        $usedToday = $account->getMaxDaily() !== null
            ? $fromBase($this->transferRepository->sumCompletedAmountSince($account, new \DateTimeImmutable('today')))
            : null;
        $usedThisMonth = $account->getMaxMonthly() !== null
            ? $fromBase($this->transferRepository->sumCompletedAmountSince($account, new \DateTimeImmutable('first day of this month midnight')))
            : null;

        return [
            'available' => $available,
            'currency' => $displayCurrency,
            'allowTransfers' => $account->isAllowTransfers(),
            'maxPerTransfer' => $account->getMaxPerTransfer(),
            'maxDaily' => $account->getMaxDaily(),
            'usedToday' => $usedToday,
            'maxMonthly' => $account->getMaxMonthly(),
            'usedThisMonth' => $usedThisMonth,
        ];
    }

    /**
     * Valida maxPerTransfer/maxDaily/maxMonthly de la cuenta origen contra $amount. Se llama tanto
     * en createTransfer (validación temprana) como en processTransfer (revalidación justo antes de
     * mover el dinero, por si otras transferencias se completaron entre medio).
     *
     * $amount llega siempre en la moneda base (ver resolveAmount()); los límites de la cuenta
     * están configurados en su defaultCurrency (igual que creditLimit, y que lo que se muestra en
     * getTransferLimits()) — hay que convertirlos a la base antes de comparar, si son monedas
     * distintas, para no comparar montos en monedas distintas entre sí.
     */
    private function checkTransferLimits(Account $account, string $amount): void
    {
        $baseCurrency = $this->systemCurrencyService->getBaseCurrency();
        $displayCurrency = $account->getDefaultCurrency();

        $maxPerTransfer = $this->convertLimitToBase($account->getMaxPerTransfer(), $displayCurrency, $baseCurrency);
        $maxDaily = $this->convertLimitToBase($account->getMaxDaily(), $displayCurrency, $baseCurrency);
        $maxMonthly = $this->convertLimitToBase($account->getMaxMonthly(), $displayCurrency, $baseCurrency);

        if ($maxPerTransfer !== null && bccomp($amount, $maxPerTransfer, 2) > 0) {
            throw new BusinessException("El monto ({$amount} {$baseCurrency}) supera el máximo permitido por transferencia ({$maxPerTransfer} {$baseCurrency})");
        }

        if ($maxDaily !== null) {
            $used = $this->transferRepository->sumCompletedAmountSince($account, new \DateTimeImmutable('today'));
            if (bccomp(bcadd($used, $amount, 2), $maxDaily, 2) > 0) {
                throw new BusinessException("La transferencia supera el límite diario de la cuenta ({$maxDaily} {$baseCurrency})");
            }
        }

        if ($maxMonthly !== null) {
            $used = $this->transferRepository->sumCompletedAmountSince($account, new \DateTimeImmutable('first day of this month midnight'));
            if (bccomp(bcadd($used, $amount, 2), $maxMonthly, 2) > 0) {
                throw new BusinessException("La transferencia supera el límite mensual de la cuenta ({$maxMonthly} {$baseCurrency})");
            }
        }
    }

    private function convertLimitToBase(?string $value, string $fromCurrency, string $baseCurrency): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($fromCurrency === $baseCurrency) {
            return $value;
        }

        $rate = $this->apiExchangeRate->getRate($fromCurrency);
        if ($rate === null) {
            // Sin tasa disponible: se compara tal cual como fallback conservador, en vez de
            // reventar la validación completa por no poder convertir.
            return $value;
        }

        return (string) round((float) bcdiv($value, sprintf('%.8f', $rate), 4), 2);
    }
}
