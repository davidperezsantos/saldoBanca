<?php

namespace App\Services\Balance;

use App\DTO\Balance\TransferDto;
use App\Entity\Balance\Transfer;
use App\Repository\Balance\TransferRepository;
use App\Repository\Balance\AccountRepository;
use App\Services\BaseService;
use Doctrine\ORM\EntityManagerInterface;

class TransferService extends BaseService
{
    private TransferRepository $transferRepository;
    private AccountRepository $accountRepository;
    private BalanceService $balanceService;

    public function __construct(
        EntityManagerInterface $entityManager,
        TransferRepository $transferRepository,
        AccountRepository $accountRepository,
        BalanceService $balanceService
    ) {
        parent::__construct($entityManager);
        $this->transferRepository = $transferRepository;
        $this->accountRepository = $accountRepository;
        $this->balanceService = $balanceService;
    }

    public function createTransfer(TransferDto $dto): Transfer
    {
        $originAccount = $this->accountRepository->find($dto->originAccountId);
        if (!$originAccount) {
            throw new \RuntimeException('Origin account not found');
        }

        if ($originAccount->getStatus() !== 'active') {
            throw new \RuntimeException('Origin account is not active');
        }

        if (!$originAccount->isAllowTransfers()) {
            throw new \RuntimeException('Transfers are not allowed for this account');
        }

        $destAccount = $this->accountRepository->findByAccountNumber($dto->destinationAccountNumber);
        if (!$destAccount) {
            throw new \RuntimeException('Destination account not found');
        }

        if ($destAccount->getStatus() !== 'active') {
            throw new \RuntimeException('Destination account is not active');
        }

        if ($originAccount->getId()->toString() === $destAccount->getId()->toString()) {
            throw new \RuntimeException('Cannot transfer to the same account');
        }

        $transfer = new Transfer();
        $transfer->setOriginAccount($originAccount);
        $transfer->setDestinationAccount($destAccount);
        $transfer->setAmount($dto->amount);
        $transfer->setCurrency($dto->currency);
        $transfer->setNotes($dto->notes);
        $transfer->setOriginAccountNumber($originAccount->getAccountNumber());
        $transfer->setDestAccountNumber($destAccount->getAccountNumber());
        $transfer->setStatus('pending');

        $this->persist($transfer);
        $this->flush();

        return $transfer;
    }

    public function processTransfer(string $id): Transfer
    {
        $transfer = $this->transferRepository->find($id);
        if (!$transfer) {
            throw new \RuntimeException('Transfer not found');
        }

        if ($transfer->getStatus() !== 'pending') {
            throw new \RuntimeException('Transfer is not in pending status');
        }

        $this->balanceService->transferBalance(
            originAccountId: $transfer->getOriginAccount()->getId()->toString(),
            destAccountId: $transfer->getDestinationAccount()->getId()->toString(),
            amount: $transfer->getAmount(),
            currency: $transfer->getCurrency(),
            performedBy: $transfer->getAuthorizedBy()
        );

        $transfer->setStatus('completed');
        $this->flush();

        return $transfer;
    }

    public function cancelTransfer(string $id): Transfer
    {
        $transfer = $this->transferRepository->find($id);
        if (!$transfer) {
            throw new \RuntimeException('Transfer not found');
        }

        if ($transfer->getStatus() !== 'pending') {
            throw new \RuntimeException('Can only cancel pending transfers');
        }

        $transfer->setStatus('cancelled');
        $this->flush();

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
            throw new \RuntimeException('Account not found');
        }

        $balance = $this->balanceService->getBalance($accountId, $account->getDefaultCurrency());

        return [
            'available' => $balance ? $balance->getAvailableBalance() : '0.00',
            'currency' => $account->getDefaultCurrency(),
            'allowTransfers' => $account->isAllowTransfers(),
        ];
    }
}
